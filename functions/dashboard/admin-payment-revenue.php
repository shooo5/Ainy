<?php
/**
 * 管理者向け：売上実績（Stripe / WP）と MRR 見込み
 *
 * @package AidUnite
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 直近 N ヶ月の Y-m キー（古い順）
 *
 * @param int $count
 * @return array<int, string>
 */
function aidunite_admin_revenue_build_month_keys($count = 12) {
    $count = max(1, min(24, (int) $count));
    $keys = [];
    $base = current_time('Y-m-01');

    for ($i = $count - 1; $i >= 0; $i--) {
        $keys[] = gmdate('Y-m', strtotime($base . ' -' . $i . ' months'));
    }

    return $keys;
}

/**
 * @param string $ym Y-m
 * @return array{start:string,end:string,label:string}
 */
function aidunite_admin_revenue_month_window($ym) {
    $ym = preg_match('/^\d{4}-\d{2}$/', (string) $ym) ? (string) $ym : current_time('Y-m');
    $start_ts = strtotime($ym . '-01 00:00:00');
    $end_ts = strtotime(date('Y-m-t 23:59:59', $start_ts));

    return [
        'start' => wp_date('Y-m-d H:i:s', $start_ts),
        'end' => wp_date('Y-m-d H:i:s', $end_ts),
        'label' => wp_date('Y年n月', $start_ts),
    ];
}

/**
 * プラットフォーム Stripe の paid Invoice を月別集計
 *
 * @param array<int, string> $month_keys
 * @param bool               $force_refresh
 * @return array{available:bool,error:string,months:array<string,int>,invoice_count:int}
 */
function aidunite_admin_revenue_fetch_stripe_platform_actuals_by_month(array $month_keys, $force_refresh = false) {
    $empty = [
        'available' => false,
        'error' => '',
        'months' => array_fill_keys($month_keys, 0),
        'invoice_count' => 0,
    ];

    if ($month_keys === []) {
        return $empty;
    }

    $cache_key = 'aidunite_admin_revenue_stripe_' . md5(implode(',', $month_keys));
    if (!$force_refresh) {
        $cached = get_transient($cache_key);
        if (is_array($cached)) {
            return $cached;
        }
    }

    if (!class_exists('\Stripe\Invoice')) {
        $empty['error'] = 'Stripe SDK が読み込まれていません。';

        return $empty;
    }

    if (!function_exists('aidunite_init_stripe') || !aidunite_init_stripe()) {
        $empty['error'] = 'Stripe API キーが未設定です。';

        return $empty;
    }

    $months = array_fill_keys($month_keys, 0);
    $oldest_ts = strtotime($month_keys[0] . '-01 00:00:00');
    $newest_window = aidunite_admin_revenue_month_window($month_keys[count($month_keys) - 1]);
    $newest_ts = strtotime($newest_window['end']);

    $invoice_count = 0;
    $params = [
        'status' => 'paid',
        'limit' => 100,
        'created' => [
            'gte' => $oldest_ts,
            'lte' => $newest_ts,
        ],
    ];

    try {
        do {
            $batch = \Stripe\Invoice::all($params);
            foreach ($batch->data ?? [] as $invoice) {
                if (!is_object($invoice)) {
                    continue;
                }
                // Connect 請求は connected account 側。platform では on_behalf_of が付く場合がある。
                if (!empty($invoice->on_behalf_of)) {
                    continue;
                }
                $billing_type = '';
                if (isset($invoice->subscription_details->metadata->billing_type)) {
                    $billing_type = (string) $invoice->subscription_details->metadata->billing_type;
                } elseif (isset($invoice->metadata->billing_type)) {
                    $billing_type = (string) $invoice->metadata->billing_type;
                }
                if ($billing_type === 'team_tuition' || $billing_type === 'competition_entry') {
                    continue;
                }

                $paid_at = (int) ($invoice->status_transitions->paid_at ?? $invoice->created ?? 0);
                if ($paid_at <= 0) {
                    continue;
                }
                $ym = wp_date('Y-m', $paid_at);
                if (!isset($months[$ym])) {
                    continue;
                }
                $amount = isset($invoice->amount_paid) ? (int) $invoice->amount_paid : (int) ($invoice->total ?? 0);
                $months[$ym] += (int) round($amount / 100);
                $invoice_count++;
            }
            if (!empty($batch->has_more) && !empty($batch->data)) {
                $last = end($batch->data);
                $params['starting_after'] = is_object($last) ? (string) ($last->id ?? '') : '';
                if ($params['starting_after'] === '') {
                    break;
                }
            } else {
                break;
            }
        } while (true);
    } catch (Exception $e) {
        $empty['error'] = $e->getMessage();

        return $empty;
    }

    $payload = [
        'available' => true,
        'error' => '',
        'months' => $months,
        'invoice_count' => $invoice_count,
    ];
    set_transient($cache_key, $payload, 15 * MINUTE_IN_SECONDS);

    return $payload;
}

/**
 * システム料 MRR 見込み（トライアル除外）
 *
 * @return array{
 *   total:int,
 *   team_count:int,
 *   rows:array<int, array<string, mixed>>
 * }
 */
function aidunite_admin_revenue_build_system_mrr_forecast() {
    $rows = [];
    $total = 0;
    $team_count = 0;

    if (!function_exists('aidunite_admin_payment_list_collect_team_ids')) {
        return ['total' => 0, 'team_count' => 0, 'rows' => []];
    }

    foreach (aidunite_admin_payment_list_collect_team_ids() as $team_id) {
        $team_id = (int) $team_id;
        if ($team_id <= 0) {
            continue;
        }

        if (function_exists('aidunite_is_trial_period') && aidunite_is_trial_period($team_id)) {
            continue;
        }

        $leader_user_id = function_exists('aidunite_team_resolve_leader_user_id')
            ? (int) aidunite_team_resolve_leader_user_id($team_id)
            : (int) get_post_field('post_author', $team_id);

        $contract = function_exists('aidunite_admin_payment_list_team_contract_display')
            ? aidunite_admin_payment_list_team_contract_display($team_id, $leader_user_id)
            : ['filter_key' => 'inactive'];
        $filter_key = (string) ($contract['filter_key'] ?? 'inactive');
        if (!in_array($filter_key, ['paid', 'cancelling', 'active'], true)) {
            continue;
        }

        $pricing = function_exists('aidunite_payment_read_pricing_payload')
            ? aidunite_payment_read_pricing_payload($team_id, $leader_user_id)
            : [];
        $monthly_fee = (int) ($pricing['monthly_fee'] ?? 0);
        if ($monthly_fee <= 0) {
            continue;
        }

        $payment_mode = function_exists('aidunite_get_team_payment_mode')
            ? (string) aidunite_get_team_payment_mode($team_id)
            : '';
        if ($payment_mode === '' && function_exists('aidunite_admin_payment_list_contract_mode_label')) {
            $contract_mode_label = '未設定（個人扱い）';
        } elseif (function_exists('aidunite_admin_payment_list_contract_mode_label')) {
            $contract_mode_label = aidunite_admin_payment_list_contract_mode_label($payment_mode);
        } else {
            $contract_mode_label = $payment_mode !== '' ? $payment_mode : '未設定';
        }

        $team_count++;
        $total += $monthly_fee;
        $rows[] = [
            'team_id' => $team_id,
            'team_name' => get_the_title($team_id) ?: ('チーム #' . $team_id),
            'product_plan' => (string) ($pricing['product_plan'] ?? 'match'),
            'monthly_fee' => $monthly_fee,
            'contract_label' => (string) ($contract['label'] ?? ''),
            'contract_mode_label' => $contract_mode_label,
            'payment_mode' => $payment_mode,
        ];
    }

    usort($rows, static function ($a, $b) {
        return ((int) ($b['monthly_fee'] ?? 0)) <=> ((int) ($a['monthly_fee'] ?? 0));
    });

    return [
        'total' => $total,
        'team_count' => $team_count,
        'rows' => $rows,
    ];
}

/**
 * 月謝 MRR 見込み（サブスク登録済み保護者）
 *
 * @return array{
 *   total_gross:int,
 *   total_ainy_fee:int,
 *   subscriber_count:int,
 *   rows:array<int, array<string, mixed>>
 * }
 */
function aidunite_admin_revenue_build_tuition_mrr_forecast() {
    $rows = [];
    $total_gross = 0;
    $subscriber_count = 0;

    if (!function_exists('aidunite_admin_payment_list_collect_tuition_rows')) {
        return [
            'total_gross' => 0,
            'total_ainy_fee' => 0,
            'subscriber_count' => 0,
            'rows' => [],
        ];
    }

    $fee_policy = function_exists('aidunite_payment_read_tuition_fee_policy')
        ? aidunite_payment_read_tuition_fee_policy()
        : ['ainy_application_fee_percent' => 1.4];
    $ainy_percent = (float) ($fee_policy['ainy_application_fee_percent'] ?? 1.4);

    foreach (aidunite_admin_payment_list_collect_tuition_rows() as $row) {
        if (empty($row['has_subscription'])) {
            continue;
        }
        $monthly_fee_raw = (string) ($row['monthly_fee'] ?? '');
        $monthly_fee = (int) preg_replace('/[^\d]/', '', $monthly_fee_raw);
        if ($monthly_fee <= 0) {
            continue;
        }

        $subscriber_count++;
        $total_gross += $monthly_fee;
        $rows[] = [
            'team_id' => (int) ($row['team_id'] ?? 0),
            'team_name' => (string) ($row['team_name'] ?? ''),
            'parent_name' => (string) ($row['parent_name'] ?? ''),
            'monthly_fee' => $monthly_fee,
            'month_status_label' => (string) ($row['month_status_label'] ?? ''),
            'ainy_fee' => (int) round($monthly_fee * $ainy_percent / 100),
        ];
    }

    return [
        'total_gross' => $total_gross,
        'total_ainy_fee' => (int) round($total_gross * $ainy_percent / 100),
        'subscriber_count' => $subscriber_count,
        'rows' => $rows,
    ];
}

/**
 * ページ用レポート一式
 *
 * @param array<string, mixed> $args
 * @return array<string, mixed>
 */
function aidunite_admin_revenue_build_report(array $args = []) {
    $months_back = isset($args['months_back']) ? (int) $args['months_back'] : 12;
    $force_refresh = !empty($args['force_refresh']);

    $month_keys = aidunite_admin_revenue_build_month_keys($months_back);
    $current_ym = $month_keys[count($month_keys) - 1] ?? current_time('Y-m');

    $tuition_actuals = aidunite_payment_read_tuition_actuals_by_month($month_keys);
    $stripe_actuals = aidunite_admin_revenue_fetch_stripe_platform_actuals_by_month($month_keys, $force_refresh);
    $system_mrr = aidunite_admin_revenue_build_system_mrr_forecast();
    $tuition_mrr = aidunite_admin_revenue_build_tuition_mrr_forecast();

    $monthly_rows = [];
    foreach ($month_keys as $ym) {
        $window = aidunite_admin_revenue_month_window($ym);
        $system_actual = (int) ($stripe_actuals['months'][$ym] ?? 0);
        $tuition = $tuition_actuals[$ym] ?? ['gross' => 0, 'paid_count' => 0, 'ainy_fee' => 0];
        $tuition_gross = (int) ($tuition['gross'] ?? 0);
        $tuition_ainy = (int) ($tuition['ainy_fee'] ?? 0);

        $monthly_rows[] = [
            'ym' => $ym,
            'label' => $window['label'],
            'system_actual' => $system_actual,
            'tuition_gross' => $tuition_gross,
            'tuition_ainy_fee' => $tuition_ainy,
            'actual_total' => $system_actual + $tuition_ainy,
            'tuition_paid_count' => (int) ($tuition['paid_count'] ?? 0),
            'is_current' => $ym === $current_ym,
        ];
    }

    $current = $monthly_rows[count($monthly_rows) - 1] ?? [
        'system_actual' => 0,
        'tuition_gross' => 0,
        'tuition_ainy_fee' => 0,
        'actual_total' => 0,
    ];

    return [
        'month_keys' => $month_keys,
        'current_ym' => $current_ym,
        'stripe' => $stripe_actuals,
        'monthly_rows' => $monthly_rows,
        'current_month' => $current,
        'system_mrr' => $system_mrr,
        'tuition_mrr' => $tuition_mrr,
        'mrr_total' => (int) ($system_mrr['total'] ?? 0) + (int) ($tuition_mrr['total_ainy_fee'] ?? 0),
        'generated_at' => current_time('mysql'),
    ];
}

/**
 * @param int $amount
 * @return string
 */
function aidunite_admin_revenue_format_yen($amount) {
    return number_format((int) $amount) . '円';
}
