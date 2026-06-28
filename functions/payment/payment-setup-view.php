<?php
/**
 * 契約・お支払いページ（/payment-setup）ビューモデル
 *
 * @package AidUnite
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once get_template_directory() . '/functions/payment/payment-plan-catalog.php';

/**
 * 契約状態の表示ラベル
 *
 * @param string $phase
 * @return array{label: string, modifier: string}
 */
function aidunite_payment_setup_contract_status_display($phase) {
    $map = [
        'trial' => ['label' => '無料期間中', 'modifier' => 'trial'],
        'active' => ['label' => '契約中', 'modifier' => 'active'],
        'unpaid' => ['label' => 'お支払い停止中', 'modifier' => 'unpaid'],
        'cancelling' => ['label' => '解約予定', 'modifier' => 'cancelling'],
        'inactive' => ['label' => '未契約', 'modifier' => 'inactive'],
    ];

    return $map[$phase] ?? $map['inactive'];
}

/**
 * プランカタログの機能一覧を表示用ラベル配列へ
 *
 * @param array<string, mixed> $plan_card
 * @return string[]
 */
function aidunite_payment_setup_plan_feature_labels(array $plan_card) {
    $labels = [];
    foreach (aidunite_payment_setup_plan_feature_items($plan_card) as $item) {
        $labels[] = (string) ($item['title'] ?? '');
    }

    return $labels;
}

/**
 * プランカタログの機能一覧（アイコン付き）
 *
 * @param array<string, mixed> $plan_card
 * @return array<int, array{title: string, icon: string}>
 */
function aidunite_payment_setup_plan_feature_items(array $plan_card) {
    $items = [];
    foreach ((array) ($plan_card['features'] ?? []) as $feature) {
        if (!is_array($feature)) {
            continue;
        }
        $title = trim((string) ($feature['title'] ?? ''));
        if ($title !== '') {
            $items[] = [
                'title' => $title,
                'icon' => (string) ($feature['icon'] ?? 'check_circle'),
            ];
        }
    }

    return $items;
}

/**
 * 契約状況ブロックの Primary CTA
 *
 * @param array<string, mixed> $context
 * @return array<string, mixed>
 */
function aidunite_payment_setup_build_primary_action(array $context) {
    $contract_phase = (string) ($context['contract_phase'] ?? 'trial');
    $exit_available_until_label = (string) ($context['exit_available_until_label'] ?? '');
    $show_checkout_cta = !empty($context['show_checkout_cta']);
    $portal_available = !empty($context['portal_available']);
    $has_registered_card = !empty($context['has_registered_card']);
    $has_subscription = !empty($context['has_subscription']);

    $action = [
        'headline' => '',
        'note' => '',
        'cta_type' => 'none',
        'cta_label' => '',
    ];

    if ($contract_phase === 'cancelling') {
        $action['headline'] = $exit_available_until_label !== ''
            ? sprintf('%sまでご利用いただけます', $exit_available_until_label)
            : '解約手続き中です';
        $action['note'] = '手続き中は翌月以降の試合募集・申請はできません。';

        return $action;
    }

    if ($contract_phase === 'unpaid') {
        $action['headline'] = 'お支払いの確認が必要です';
        $action['note'] = 'お支払い方法を更新すると、引き続きご利用いただけます。';
        if ($show_checkout_cta) {
            $action['cta_type'] = 'checkout';
            $action['cta_label'] = 'お支払いを更新する';
        } elseif ($portal_available) {
            $action['cta_type'] = 'portal';
            $action['cta_label'] = 'お支払い方法を変更する';
        }

        return $action;
    }

    if ($contract_phase === 'active') {
        $action['headline'] = 'ご契約中です';
        $action['note'] = '毎月1日に請求されます。';
        if ($portal_available) {
            $action['cta_type'] = 'portal';
            $action['cta_label'] = 'お支払い方法を変更する';
        }

        return $action;
    }

    if ($show_checkout_cta) {
        $action['headline'] = 'クレジットカードを登録すると、無料期間終了後も継続してご利用いただけます。';
        $action['note'] = '今すぐ課金はされません';
        $action['cta_type'] = 'checkout';
        $action['cta_label'] = 'カードを登録する';

        return $action;
    }

    if ($has_registered_card || $has_subscription) {
        if ($portal_available) {
            $action['cta_type'] = 'portal';
            $action['cta_label'] = 'お支払い方法を変更する';
        }

        return $action;
    }

    $action['note'] = '継続利用する場合は、無料期間内にカードを登録してください。';

    return $action;
}

/**
 * 2ヶ月無料などの無料期間表示を出すか（未課金の通常オンボーディング）
 *
 * @param string $contract_phase
 * @return bool
 */
function aidunite_payment_setup_should_show_free_period($contract_phase) {
    return $contract_phase === 'trial';
}

/**
 * 無料期間中か（契約フェーズまたは残日数）
 *
 * @param bool     $show_free_period
 * @param int|null $trial_days_remaining
 * @return bool
 */
function aidunite_payment_setup_is_intro_period($show_free_period, $trial_days_remaining) {
    if ($show_free_period) {
        return true;
    }

    return $trial_days_remaining !== null && (int) $trial_days_remaining > 0;
}

/**
 * 請求履歴行を表示用に正規化（¥0 はトライアル中は非表示、それ以外はカード確認ラベル）
 *
 * @param array<int, array<string, mixed>> $rows
 * @param bool                             $hide_zero_during_intro
 * @return array<int, array<string, mixed>>
 */
function aidunite_payment_setup_normalize_billing_history_rows(array $rows, $hide_zero_during_intro) {
    $normalized = [];

    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }

        $amount = (int) ($row['amount'] ?? 0);
        if ($hide_zero_during_intro && $amount === 0) {
            continue;
        }

        if ($amount === 0) {
            $row['amount_display'] = 'カード確認（¥0）';
            $row['status_label'] = '確認済み';
            $row['is_card_verification'] = true;
        } else {
            $row['amount_display'] = sprintf('¥%s', number_format($amount));
        }

        $normalized[] = $row;
    }

    return $normalized;
}

/**
 * 契約・お支払いページ用ビューモデル
 *
 * @param int                  $team_id
 * @param int                  $user_id
 * @param array<string, mixed> $setup_payload
 * @return array<string, mixed>
 */
function aidunite_payment_read_setup_page_model($team_id, $user_id, array $setup_payload) {
    $team_id = (int) $team_id;
    $user_id = (int) $user_id;
    $team = get_post($team_id);
    $team_name = $team instanceof WP_Post ? (string) $team->post_title : '';

    $catalog = aidunite_payment_get_product_plan_catalog();

    $product_plan = (string) ($setup_payload['product_plan'] ?? 'match');
    if ($product_plan !== 'club') {
        $product_plan = 'match';
    }
    $has_club_plan = $product_plan === 'club';

    $monthly_fee = (int) ($setup_payload['pricing']['monthly_fee'] ?? 0);
    if ($monthly_fee <= 0 && function_exists('aidunite_calculate_monthly_fee')) {
        $monthly_fee = (int) aidunite_calculate_monthly_fee($team_id, $user_id);
    }

    $trial_end_date = (string) ($setup_payload['trial_end_date'] ?? '');
    if ($trial_end_date === '' && function_exists('aidunite_payment_resolve_trial_end_date')) {
        $trial_end_date = aidunite_payment_resolve_trial_end_date($team_id, $user_id);
    } elseif ($trial_end_date === '' && function_exists('aidunite_calculate_trial_end_date')) {
        $calculated_end = aidunite_calculate_trial_end_date($team_id);
        if ($calculated_end) {
            $trial_end_date = (string) $calculated_end;
        }
    }

    $subscription_state = is_array($setup_payload['subscription_state'] ?? null)
        ? $setup_payload['subscription_state']
        : [];
    $exit_state = is_array($setup_payload['exit_state'] ?? null) ? $setup_payload['exit_state'] : [];
    $team_payment_status = (string) ($subscription_state['payment_status'] ?? '');
    $has_subscription = !empty($subscription_state['has_subscription']);
    $exit_pending = is_array($exit_state['pending'] ?? null) ? $exit_state['pending'] : null;
    $exit_available_until = (string) ($exit_state['complete_at'] ?? '');

    $stripe_billing = function_exists('aidunite_stripe_read_billing_summary')
        ? aidunite_stripe_read_billing_summary($user_id, $team_id)
        : [
            'available' => false,
            'payment_methods' => [],
            'default_payment_method' => null,
            'invoices' => [],
            'next_billing_date' => '',
            'portal_available' => false,
        ];

    $default_card = is_array($stripe_billing['default_payment_method'] ?? null)
        ? $stripe_billing['default_payment_method']
        : null;

    $is_paid_active = ($team_payment_status === 'paid' || $has_subscription) && $exit_pending === null;

    $contract_phase = 'trial';
    if ($exit_pending !== null || $team_payment_status === 'cancelling') {
        $contract_phase = 'cancelling';
    } elseif ($team_payment_status === 'unpaid') {
        $contract_phase = 'unpaid';
    } elseif ($is_paid_active) {
        $contract_phase = 'active';
    }

    $status_display = aidunite_payment_setup_contract_status_display($contract_phase);
    $current_catalog = $catalog[$product_plan] ?? $catalog['match'];
    $show_free_period = aidunite_payment_setup_should_show_free_period($contract_phase);
    $trial_copy = function_exists('aidunite_payment_get_trial_copy') ? aidunite_payment_get_trial_copy() : [];

    $next_billing_date = (string) ($stripe_billing['next_billing_date'] ?? '');
    $next_billing_conditional_note = '';
    if ($next_billing_date === '' && $show_free_period && function_exists('aidunite_payment_resolve_next_billing_date_1st')) {
        $after_ymd = $trial_end_date !== ''
            ? substr($trial_end_date, 0, 10)
            : wp_date('Y-m-d');
        $billing_ymd = aidunite_payment_resolve_next_billing_date_1st($after_ymd);
        $next_billing_date = aidunite_payment_format_billing_date_display($billing_ymd);
        if (!$has_subscription) {
            $next_billing_conditional_note = '※カード登録後、無料期間終了の翌月1日から請求';
        }
    } elseif ($next_billing_date === '' && $contract_phase === 'active' && function_exists('aidunite_payment_resolve_next_billing_date_1st')) {
        $billing_ymd = aidunite_payment_resolve_next_billing_date_1st(null);
        $next_billing_date = aidunite_payment_format_billing_date_display($billing_ymd);
    }

    $club_upgrade_locked = function_exists('aidunite_activation_is_mission_ui')
        && aidunite_activation_is_mission_ui($team_id);

    $plan_cards = [];
    foreach ($catalog as $key => $card) {
        $is_club = $key === 'club';
        $is_upgrade_locked = $is_club && $club_upgrade_locked && $product_plan !== 'club';
        $plan_cards[] = array_merge($card, [
            'is_selected' => $key === $product_plan,
            'feature_labels' => aidunite_payment_setup_plan_feature_labels($card),
            'is_upgrade_locked' => $is_upgrade_locked,
            'plan_badge_label' => $is_club
                ? ($is_upgrade_locked ? '試合成立後に利用可能' : 'チーム運営向け')
                : '',
            'plan_badge_modifier' => $is_club
                ? ($is_upgrade_locked ? 'locked' : 'club')
                : '',
            'plan_icon' => $is_club ? 'group' : 'trophy',
            'upgrade_locked_note' => '試合成立後にアップグレードできます',
        ]);
    }

    $has_registered_card = $default_card !== null
        || (!empty($stripe_billing['payment_methods']) && $has_subscription);

    $show_checkout_cta = !$has_subscription
        && $exit_pending === null
        && in_array($contract_phase, ['trial', 'unpaid'], true)
        && $default_card === null;

    $billing_history_raw = is_array($stripe_billing['invoices'] ?? null) ? $stripe_billing['invoices'] : [];
    if ($billing_history_raw === [] && $team_payment_status === 'paid') {
        $payment_status_updated = function_exists('aidunite_payment_read_team_payment_status_updated')
            ? aidunite_payment_read_team_payment_status_updated($team_id)
            : (string) (aidunite_payment_read_canonical_user_meta($user_id)['payment_status_updated'] ?? '');
        if ($payment_status_updated !== '') {
            $billing_history_raw[] = [
                'label' => aidunite_payment_format_date_display($payment_status_updated),
                'amount' => $monthly_fee,
                'status' => 'paid',
                'status_label' => '支払い済み',
                'date' => aidunite_payment_format_date_display($payment_status_updated),
                'invoice_pdf' => '',
                'hosted_invoice_url' => '',
            ];
        }
    }

    $can_change_plan = $exit_pending === null && $contract_phase !== 'cancelling';
    $portal_available = !empty($stripe_billing['portal_available']);

    $trial_end_date_label = $trial_end_date !== ''
        ? aidunite_payment_format_date_display($trial_end_date)
        : '';
    $trial_days_remaining = function_exists('aidunite_payment_get_trial_days_remaining')
        ? aidunite_payment_get_trial_days_remaining($team_id)
        : null;

    $is_intro_period = aidunite_payment_setup_is_intro_period($show_free_period, $trial_days_remaining);
    $billing_history = aidunite_payment_setup_normalize_billing_history_rows(
        $billing_history_raw,
        $is_intro_period
    );

    $free_until_short = '';
    if ($show_free_period) {
        if ($trial_end_date_label !== '') {
            $free_until_short = $trial_end_date_label . 'まで無料';
        } else {
            $free_until_short = (string) ($trial_copy['period_fallback'] ?? '2ヶ月無料期間中');
        }
    }

    $card_registration_label = $has_registered_card ? 'カード登録済み' : 'カード未登録';
    $available_features = aidunite_payment_setup_plan_feature_labels($current_catalog);
    $available_feature_items = aidunite_payment_setup_plan_feature_items($current_catalog);

    $primary_action = aidunite_payment_setup_build_primary_action([
        'contract_phase' => $contract_phase,
        'exit_available_until_label' => $exit_available_until !== ''
            ? aidunite_payment_format_date_display($exit_available_until)
            : '',
        'show_checkout_cta' => $show_checkout_cta,
        'portal_available' => $portal_available,
        'has_registered_card' => $has_registered_card,
        'has_subscription' => $has_subscription,
    ]);

    $primary_cta_type = (string) ($primary_action['cta_type'] ?? 'none');
    $show_registration_complete = $has_registered_card
        && !$show_checkout_cta
        && $primary_cta_type !== 'checkout'
        && $is_intro_period
        && !in_array($contract_phase, ['unpaid', 'cancelling'], true);

    $show_next_steps_column = !$show_registration_complete && (
        $primary_cta_type === 'checkout'
        || !empty($primary_action['headline'])
        || in_array($contract_phase, ['unpaid', 'cancelling'], true)
        || (!$has_registered_card && !empty($primary_action['note']))
        || ($primary_cta_type === 'portal' && $contract_phase === 'active' && !$is_intro_period)
    );

    $billing_history_empty_message = $show_free_period
        ? '無料期間中は請求はありません。初回請求後に表示されます。'
        : 'まだ請求履歴はありません。初回のお支払い完了後に表示されます。';

    return [
        'team_name' => $team_name,
        'product_plan' => $product_plan,
        'has_club_plan' => $has_club_plan,
        'contract_status_label' => $status_display['label'],
        'contract_status_modifier' => $status_display['modifier'],
        'plan_name' => (string) ($current_catalog['name'] ?? 'Matchプラン'),
        'plan_tagline' => (string) ($current_catalog['tagline'] ?? ''),
        'monthly_fee' => $monthly_fee,
        'monthly_fee_display' => sprintf('¥%s', number_format($monthly_fee)),
        'monthly_fee_unit' => '/ 月（税込）',
        'free_period_end_label' => $trial_end_date_label,
        'trial_period_note' => (string) ($trial_copy['highlight'] ?? 'お申し込み月と翌月は無料です。'),
        'card_registration_label' => $card_registration_label,
        'free_until_short' => $free_until_short,
        'available_features' => $available_features,
        'available_feature_items' => $available_feature_items,
        'primary_action' => $primary_action,
        'show_registration_complete' => $show_registration_complete,
        'show_next_steps_column' => $show_next_steps_column,
        'registration_complete_note' => '無料期間終了後、自動で課金が開始されます。',
        'trial_days_remaining' => $trial_days_remaining,
        'billing_history_collapsed' => $is_intro_period,
        'billing_history_collapsed_summary' => '無料期間中は請求はありません',
        'next_billing_date' => $next_billing_date,
        'next_billing_conditional_note' => $next_billing_conditional_note,
        'show_free_period' => $show_free_period,
        'plan_cards' => $plan_cards,
        'has_registered_card' => $has_registered_card,
        'default_card' => $default_card,
        'show_checkout_cta' => $show_checkout_cta,
        'show_portal_cta' => $portal_available && ($has_registered_card || $has_subscription),
        'billing_history' => array_slice($billing_history, 0, 3),
        'billing_history_total' => count($billing_history),
        'can_change_plan' => $can_change_plan,
        'billing_history_empty_message' => $billing_history_empty_message,
        'exit_pending' => $exit_pending,
        'exit_available_until_label' => $exit_available_until !== ''
            ? aidunite_payment_format_date_display($exit_available_until)
            : '',
        'plan_info_url' => home_url('/plan-info'),
    ];
}

/**
 * Embedded Checkout ページ（/payment-checkout）用ビューモデル
 *
 * @param int $team_id
 * @param int $user_id
 * @return array<string, mixed>
 */
function aidunite_payment_read_checkout_page_model($team_id, $user_id) {
    $team_id = (int) $team_id;
    $user_id = (int) $user_id;

    $setup_payload = function_exists('aidunite_payment_read_setup_display')
        ? aidunite_payment_read_setup_display($team_id, $user_id)
        : [];
    $setup_vm = aidunite_payment_read_setup_page_model($team_id, $user_id, $setup_payload);

    $product_plan = (string) ($setup_vm['product_plan'] ?? 'match');
    if ($product_plan !== 'club') {
        $product_plan = 'match';
    }

    $catalog = aidunite_payment_get_product_plan_catalog();
    $plan_card = $catalog[$product_plan] ?? $catalog['match'];

    $feature_items = [];
    foreach ((array) ($plan_card['features'] ?? []) as $feature) {
        if (!is_array($feature)) {
            continue;
        }
        $title = trim((string) ($feature['title'] ?? ''));
        if ($title === '') {
            continue;
        }
        $feature_items[] = [
            'icon' => (string) ($feature['icon'] ?? 'check_circle'),
            'title' => $title,
            'text' => trim((string) ($feature['text'] ?? '')),
        ];
    }

    $trial_copy = function_exists('aidunite_payment_get_trial_copy') ? aidunite_payment_get_trial_copy() : [];
    $monthly_fee = (int) ($setup_vm['monthly_fee'] ?? 0);
    $trial_end_label = (string) ($setup_vm['free_period_end_label'] ?? '');
    $next_billing = (string) ($setup_vm['next_billing_date'] ?? '');

    $billing_after_trial = $trial_end_label !== '' && $next_billing !== ''
        ? sprintf('その後、%s/月、%s以降', (string) ($setup_vm['monthly_fee_display'] ?? ''), $next_billing)
        : sprintf('月額 %s（税込）', (string) ($plan_card['price_label'] ?? ''));

    return [
        'team_name' => (string) ($setup_vm['team_name'] ?? ''),
        'product_plan' => $product_plan,
        'plan_name' => (string) ($plan_card['name'] ?? 'Matchプラン'),
        'plan_tagline' => (string) ($plan_card['tagline'] ?? ''),
        'price_label' => (string) ($plan_card['price_label'] ?? ''),
        'monthly_fee' => $monthly_fee,
        'monthly_fee_display' => (string) ($setup_vm['monthly_fee_display'] ?? ''),
        'minimum_note' => (string) ($plan_card['minimum_note'] ?? ''),
        'is_recommended' => !empty($plan_card['is_recommended']),
        'trial_banner' => (string) ($trial_copy['label'] ?? '2ヶ月無料でお試しいただけます'),
        'trial_period_note' => (string) ($setup_vm['trial_period_note'] ?? ''),
        'trial_days_remaining' => (int) ($setup_vm['trial_days_remaining'] ?? 0),
        'billing_after_trial_label' => $billing_after_trial,
        'feature_items' => $feature_items,
        'can_access_checkout' => !empty($setup_vm['show_checkout_cta'])
            || (empty($setup_vm['has_registered_card']) && empty($setup_vm['show_portal_cta'])),
        'show_checkout_cta' => !empty($setup_vm['show_checkout_cta']),
        'payment_setup_url' => home_url('/payment-setup'),
        'card_registration_note' => '今すぐ課金はされません',
    ];
}
