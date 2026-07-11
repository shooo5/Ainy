<?php
/**
 * 管理者向け決済メトリクス（一覧・売上見込みの共有ロジック）
 *
 * @package AidUnite
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * @param string $payment_mode
 * @return string
 */
function aidunite_admin_payment_list_contract_mode_label($payment_mode) {
    $mode_labels = [
        'corporate' => '法人契約',
        'personal' => '個人契約',
        'business' => '法人契約',
        'school' => '法人契約',
    ];
    $payment_mode = (string) $payment_mode;

    return $mode_labels[$payment_mode] ?? ($payment_mode !== '' ? $payment_mode : '未設定');
}

/**
 * @param int $team_id
 * @param int $leader_user_id
 * @return array{label: string, modifier: string, filter_key: string}
 */
function aidunite_admin_payment_list_team_contract_display($team_id, $leader_user_id) {
    $team_id = (int) $team_id;
    $leader_user_id = (int) $leader_user_id;

    $subscription_state = function_exists('aidunite_payment_exit_read_subscription_state')
        ? aidunite_payment_exit_read_subscription_state($team_id, $leader_user_id)
        : [];
    $exit_state = function_exists('aidunite_payment_exit_read_exit_state_payload')
        ? aidunite_payment_exit_read_exit_state_payload($team_id, $leader_user_id)
        : [];

    $status = (string) ($subscription_state['payment_status'] ?? '');
    $has_subscription = !empty($subscription_state['has_subscription']);
    $exit_pending = is_array($exit_state['pending'] ?? null) ? $exit_state['pending'] : null;
    $is_trial = function_exists('aidunite_is_trial_period') && aidunite_is_trial_period($team_id);

    if ($exit_pending !== null || $status === 'cancelling') {
        return ['label' => '解約手続き中', 'modifier' => 'cancelling', 'filter_key' => 'cancelling'];
    }
    if ($status === 'cancelled') {
        return ['label' => '解約済', 'modifier' => 'cancelled', 'filter_key' => 'cancelled'];
    }
    if ($status === 'unpaid') {
        return ['label' => '未払い', 'modifier' => 'unpaid', 'filter_key' => 'unpaid'];
    }
    if ($status === 'paid') {
        return ['label' => '有料契約', 'modifier' => 'paid', 'filter_key' => 'paid'];
    }
    if ($has_subscription && $is_trial) {
        return ['label' => 'トライアル（カード登録済）', 'modifier' => 'trial', 'filter_key' => 'trial'];
    }
    if ($is_trial) {
        return ['label' => 'トライアル', 'modifier' => 'trial', 'filter_key' => 'trial'];
    }
    if ($has_subscription) {
        return ['label' => 'サブスク連携済', 'modifier' => 'active', 'filter_key' => 'active'];
    }

    return ['label' => '未開始', 'modifier' => 'inactive', 'filter_key' => 'inactive'];
}

/**
 * @return int[]
 */
function aidunite_admin_payment_list_collect_team_ids() {
    $posts = get_posts([
        'post_type' => 'team',
        'post_status' => ['publish', 'draft', 'pending', 'private'],
        'posts_per_page' => -1,
        'orderby' => 'ID',
        'order' => 'DESC',
        'fields' => 'ids',
        'no_found_rows' => true,
    ]);

    return array_map('intval', is_array($posts) ? $posts : []);
}

/**
 * @param string $stripe_id
 * @return string
 */
function aidunite_admin_payment_metrics_short_stripe_id($stripe_id) {
    $stripe_id = trim((string) $stripe_id);
    if ($stripe_id === '') {
        return '—';
    }
    if (strlen($stripe_id) <= 14) {
        return $stripe_id;
    }

    return substr($stripe_id, 0, 10) . '…';
}

/**
 * @return array<int, array<string, mixed>>
 */
function aidunite_admin_payment_list_collect_tuition_rows() {
    $rows = [];

    foreach (aidunite_admin_payment_list_collect_team_ids() as $team_id) {
        if (!function_exists('aidunite_payment_read_team_tuition_collections_page_model')) {
            continue;
        }

        $model = aidunite_payment_read_team_tuition_collections_page_model($team_id);
        if ($model === []) {
            continue;
        }

        $team_name = (string) ($model['team_name'] ?? '');
        $monthly_fee = (int) ($model['monthly_fee'] ?? 0);
        $month_label = (string) ($model['month_label'] ?? '');

        foreach ((array) ($model['parents'] ?? []) as $parent_row) {
            $parent_user_id = (int) ($parent_row['user_id'] ?? 0);
            if ($parent_user_id <= 0) {
                continue;
            }

            $subscription_id = function_exists('aidunite_user_read_tuition_subscription_id')
                ? aidunite_user_read_tuition_subscription_id($parent_user_id, $team_id)
                : '';
            $connect_customer_id = function_exists('aidunite_user_read_connect_customer_id')
                ? aidunite_user_read_connect_customer_id($parent_user_id, $team_id)
                : '';

            $latest = aidunite_payment_read_parent_latest_tuition_payment($parent_user_id, $team_id);
            $latest_label = '—';
            if (is_array($latest)) {
                $amount = number_format((int) ($latest['amount'] ?? 0));
                $date = substr((string) ($latest['payment_date'] ?? ''), 0, 10);
                $status = strtolower((string) ($latest['status'] ?? ''));
                $status_label = function_exists('aidunite_payment_normalize_tuition_payment_status_label')
                    ? aidunite_payment_normalize_tuition_payment_status_label($status)
                    : $status;
                $latest_label = $date !== '' ? ($date . ' ' . $amount . '円（' . $status_label . '）') : ($amount . '円');
            }

            $rows[] = [
                'team_id' => $team_id,
                'team_name' => $team_name,
                'parent_user_id' => $parent_user_id,
                'parent_name' => (string) ($parent_row['parent_name'] ?? $parent_row['display_name'] ?? '—'),
                'parent_email' => (string) ($parent_row['parent_email'] ?? ''),
                'monthly_fee' => $monthly_fee > 0 ? number_format($monthly_fee) . '円' : '—',
                'month_label' => $month_label,
                'month_status' => (string) ($parent_row['month_status'] ?? ''),
                'month_status_label' => (string) ($parent_row['month_status_label'] ?? '—'),
                'has_subscription' => !empty($parent_row['has_subscription']),
                'registration_label' => !empty($parent_row['has_subscription']) ? '登録済' : '未登録',
                'latest_payment_label' => $latest_label,
                'stripe_subscription_id' => $subscription_id,
                'stripe_subscription_short' => aidunite_admin_payment_metrics_short_stripe_id($subscription_id),
                'connect_customer_id' => $connect_customer_id,
                'connect_customer_short' => aidunite_admin_payment_metrics_short_stripe_id($connect_customer_id),
                'tuition_open' => !empty($model['tuition_open']),
                'user_edit_url' => add_query_arg(['user_id' => $parent_user_id], home_url('/admin-user-list')),
                'team_settings_url' => add_query_arg('team_id', $team_id, home_url('/team-settings')),
            ];
        }
    }

    usort($rows, static function ($a, $b) {
        $order = ['failed' => 0, 'overdue' => 1, 'not_registered' => 2, 'pending_billing' => 3, 'paid' => 4, 'disabled' => 5];
        $rank_a = $order[$a['month_status'] ?? ''] ?? 9;
        $rank_b = $order[$b['month_status'] ?? ''] ?? 9;
        if ($rank_a !== $rank_b) {
            return $rank_a <=> $rank_b;
        }
        $team_cmp = strcmp((string) ($a['team_name'] ?? ''), (string) ($b['team_name'] ?? ''));
        if ($team_cmp !== 0) {
            return $team_cmp;
        }

        return strcmp((string) ($a['parent_name'] ?? ''), (string) ($b['parent_name'] ?? ''));
    });

    return $rows;
}
