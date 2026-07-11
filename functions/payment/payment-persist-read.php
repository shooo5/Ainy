<?php
/**
 * 決済ドメイン read payload（canonical）
 *
 * @see docs/spec/payment.md
 * @package AidUnite
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * @param mixed $raw
 * @return string match|club
 */
function aidunite_payment_normalize_product_plan_value($raw) {
    $v = strtolower(trim((string) $raw));
    if ($v === 'club') {
        return 'club';
    }

    return 'match';
}

/**
 * @param int $team_id
 * @return array<string, mixed>
 */
function aidunite_payment_read_canonical_team_meta($team_id) {
    $team_id = (int) $team_id;
    if ($team_id <= 0 || get_post_type($team_id) !== 'team') {
        return [];
    }

    $mode_raw = (string) get_post_meta($team_id, 'payment_mode', true);
    $mode = $mode_raw;
    if (function_exists('aidunite_normalize_payment_mode_value')) {
        $mode = aidunite_normalize_payment_mode_value($mode_raw);
    }

    $product_plan_raw = (string) get_post_meta($team_id, 'product_plan', true);
    $product_plan = aidunite_payment_normalize_product_plan_value($product_plan_raw !== '' ? $product_plan_raw : 'match');

    $founding_raw = (string) get_post_meta($team_id, 'founding_team', true);
    $founding_year_raw = get_post_meta($team_id, 'founding_year', true);

    return [
        'team_id' => $team_id,
        'product_plan' => $product_plan,
        'product_plan_raw' => $product_plan_raw,
        'payment_mode' => $mode,
        'payment_mode_raw' => $mode_raw,
        'selected_plan_id' => (string) get_post_meta($team_id, 'selected_plan_id', true),
        'trial_start_date' => (string) get_post_meta($team_id, 'trial_start_date', true),
        'selected_payment_method' => (string) get_post_meta($team_id, 'selected_payment_method', true),
        'founding_team' => $founding_raw === '1' || $founding_raw === 1,
        'founding_year' => $founding_year_raw !== '' && $founding_year_raw !== false ? (int) $founding_year_raw : 0,
    ];
}

/**
 * @param int $user_id
 * @return array<string, mixed>
 */
function aidunite_payment_read_canonical_user_meta($user_id) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return [];
    }

    $status_raw = (string) get_user_meta($user_id, 'payment_status', true);
    $status = $status_raw;
    if (function_exists('aidunite_payment_normalize_payment_status_value')) {
        $normalized = aidunite_payment_normalize_payment_status_value($status_raw);
        if ($normalized !== '') {
            $status = $normalized;
        }
    }

    $payment_status_updated = (string) get_user_meta($user_id, 'payment_status_updated', true);
    $stripe_subscription_id = (string) get_user_meta($user_id, 'stripe_subscription_id', true);

    $billing = aidunite_payment_read_user_team_billing_context($user_id);
    if ((int) ($billing['team_id'] ?? 0) > 0) {
        $team_status = trim((string) ($billing['payment_status'] ?? ''));
        if ($team_status !== '') {
            $status = $team_status;
            $status_raw = $team_status;
        }
        $team_updated = trim((string) ($billing['status_updated'] ?? ''));
        if ($team_updated !== '') {
            $payment_status_updated = $team_updated;
        }
        $team_state = aidunite_payment_read_team_subscription_state((int) $billing['team_id'], $user_id);
        $team_sub = trim((string) ($team_state['stripe_subscription_id'] ?? ''));
        if ($team_sub !== '') {
            $stripe_subscription_id = $team_sub;
        }
    }

    return [
        'user_id' => $user_id,
        'payment_status' => $status,
        'payment_status_raw' => $status_raw,
        'payment_status_updated' => $payment_status_updated,
        'stripe_customer_id' => (string) get_user_meta($user_id, 'stripe_customer_id', true),
        'stripe_subscription_id' => $stripe_subscription_id,
        'team_id' => (int) ($billing['team_id'] ?? 0),
    ];
}

/**
 * @return int
 */
function aidunite_payment_read_founding_team_count() {
    global $wpdb;

    return (int) $wpdb->get_var(
        "SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta}
         WHERE meta_key = 'founding_team' AND meta_value = '1'"
    );
}

/**
 * @return int
 */
function aidunite_payment_read_founding_slots_remaining() {
    $config = function_exists('aidunite_get_payment_config') ? aidunite_get_payment_config() : [];
    $max = (int) ($config['founding_team']['max_slots'] ?? 50);
    $used = aidunite_payment_read_founding_team_count();

    return max(0, $max - $used);
}

/**
 * @param int $team_id
 * @return bool
 */
function aidunite_payment_read_team_has_club_plan($team_id) {
    $meta = aidunite_payment_read_canonical_team_meta($team_id);

    return ($meta['product_plan'] ?? 'match') === 'club';
}

/**
 * @param int $team_id
 * @param int $user_id
 * @return array<string, mixed>
 */
function aidunite_payment_read_pricing_payload($team_id, $user_id = 0) {
    $team_id = (int) $team_id;
    $user_id = (int) $user_id;
    if ($team_id <= 0) {
        return [];
    }

    $config = function_exists('aidunite_get_payment_config') ? aidunite_get_payment_config() : [];
    $canonical = aidunite_payment_read_canonical_team_meta($team_id);
    $product_plan = (string) ($canonical['product_plan'] ?? 'match');

    $match_amount = (int) ($config['match']['monthly_amount'] ?? 2000);
    $club_addon = 0;
    if ($product_plan === 'club') {
        $per_player = (int) ($config['club']['per_player_amount'] ?? $config['club']['base_amount'] ?? 500);
        $minimum_addon = (int) ($config['club']['minimum_addon'] ?? 6000);
        $player_count = function_exists('aidunite_get_registered_player_count')
            ? aidunite_get_registered_player_count($team_id)
            : 0;
        $club_addon = max($minimum_addon, $player_count * $per_player);
    }

    $subtotal = $match_amount + $club_addon;
    $discounts = [];
    $total = $subtotal;

    if (!empty($canonical['founding_team']) && function_exists('aidunite_payment_read_founding_discount_applies')) {
        if (aidunite_payment_read_founding_discount_applies($team_id)) {
            $percent = (int) ($config['founding_team']['first_year_discount_percent'] ?? 50);
            $founding_discount = (int) floor($subtotal * $percent / 100);
            if ($founding_discount > 0) {
                $discounts[] = [
                    'type' => 'founding_first_year',
                    'label' => 'Founding Team 初年度割引',
                    'percent' => $percent,
                    'amount' => $founding_discount,
                ];
                $total -= $founding_discount;
            }
        }
    }

    if ($user_id > 0 && function_exists('aidunite_payment_read_multi_team_discount_for_team')) {
        $multi = aidunite_payment_read_multi_team_discount_for_team($team_id, $user_id, $match_amount);
        if (!empty($multi['amount'])) {
            $discounts[] = $multi;
            $total -= (int) $multi['amount'];
        }
    }

    $total = max(0, $total);

    return [
        'match_amount' => $match_amount,
        'club_addon' => $club_addon,
        'subtotal' => $subtotal,
        'monthly_fee' => $total,
        'discounts' => $discounts,
        'product_plan' => $product_plan,
    ];
}

/**
 * Founding 初年度半額が適用中か（founding_year が当年）
 *
 * @param int $team_id
 * @return bool
 */
function aidunite_payment_read_founding_discount_applies($team_id) {
    $canonical = aidunite_payment_read_canonical_team_meta($team_id);
    if (empty($canonical['founding_team'])) {
        return false;
    }
    $founding_year = (int) ($canonical['founding_year'] ?? 0);
    if ($founding_year <= 0) {
        return false;
    }

    return $founding_year === (int) current_time('Y');
}

/**
 * @param int $team_id
 * @param int $user_id
 * @param int $match_amount
 * @return array<string, mixed>
 */
function aidunite_payment_read_multi_team_discount_for_team($team_id, $user_id, $match_amount) {
    $team_id = (int) $team_id;
    $user_id = (int) $user_id;
    $match_amount = (int) $match_amount;

    $empty = [
        'type' => 'multi_team_match',
        'label' => '',
        'percent' => 0,
        'amount' => 0,
    ];

    if ($team_id <= 0 || $user_id <= 0 || $match_amount <= 0) {
        return $empty;
    }

    $config = function_exists('aidunite_get_payment_config') ? aidunite_get_payment_config() : [];
    $multi = $config['multi_team_discount'] ?? [];
    if (empty($multi['enabled'])) {
        return $empty;
    }
    $percent = (int) ($multi['match_second_team_percent'] ?? 20);
    if ($percent <= 0) {
        return $empty;
    }

    $managed = function_exists('aidunite_get_managed_team_ids')
        ? aidunite_get_managed_team_ids($user_id)
        : [];
    $managed = array_values(array_unique(array_filter(array_map('intval', (array) $managed))));
    if (count($managed) < 2 || !in_array($team_id, $managed, true)) {
        return $empty;
    }

    sort($managed, SORT_NUMERIC);
    $index = array_search($team_id, $managed, true);
    if ($index === false || $index === 0) {
        return $empty;
    }

    $amount = (int) floor($match_amount * $percent / 100);
    if ($amount <= 0) {
        return $empty;
    }

    return [
        'type' => 'multi_team_match',
        'label' => '2チーム目以降割引（Match基本額）',
        'percent' => $percent,
        'amount' => $amount,
    ];
}

/**
 * UI / REST 用 team payment payload
 *
 * @param int $team_id
 * @param int $user_id
 * @return array<string, mixed>
 */
function aidunite_payment_read_team_payload($team_id, $user_id = 0) {
    $team_id = (int) $team_id;
    $user_id = (int) $user_id;
    if ($team_id <= 0) {
        return [];
    }

    $canonical = aidunite_payment_read_canonical_team_meta($team_id);
    if ($canonical === []) {
        return [];
    }

    $product_plan = (string) ($canonical['product_plan'] ?? 'match');
    $pricing = aidunite_payment_read_pricing_payload($team_id, $user_id);

    $method = (string) ($canonical['selected_payment_method'] ?? '');
    if ($method === '') {
        $method = 'stripe';
    }

    return [
        'team_id' => $team_id,
        'product_plan' => $product_plan,
        'product_plan_label' => $product_plan === 'club' ? 'Clubプラン' : 'Matchプラン',
        'has_club_plan' => $product_plan === 'club',
        'payment_mode' => (string) ($canonical['payment_mode'] ?? 'personal'),
        'selected_plan_id' => (string) ($canonical['selected_plan_id'] ?? ''),
        'trial_start_date' => (string) ($canonical['trial_start_date'] ?? ''),
        'selected_payment_method' => $method,
        'founding' => [
            'is_founding_team' => !empty($canonical['founding_team']),
            'founding_year' => (int) ($canonical['founding_year'] ?? 0),
            'discount_applies' => aidunite_payment_read_founding_discount_applies($team_id),
            'slots_remaining' => aidunite_payment_read_founding_slots_remaining(),
        ],
        'pricing' => $pricing,
        'features' => aidunite_payment_read_feature_flags_payload($product_plan),
    ];
}

/**
 * @param string $product_plan match|club
 * @return array<string, bool>
 */
function aidunite_payment_read_feature_flags_payload($product_plan) {
    $product_plan = aidunite_payment_normalize_product_plan_value($product_plan);
    $has_club = $product_plan === 'club';

    return [
        'match_recruit' => true,
        'match_request' => true,
        'schedule' => true,
        'match_chat' => true,
        'attendance' => $has_club,
        'guardian_comms' => $has_club,
        'member_management' => $has_club,
        'tuition_connect' => $has_club,
        'parent_notifications' => $has_club,
        'team_chat' => $has_club,
    ];
}

/**
 * /payment-setup 表示用
 *
 * @param int $team_id
 * @param int $user_id
 * @return array<string, mixed>
 */
function aidunite_payment_read_setup_display($team_id, $user_id = 0) {
    $payload = aidunite_payment_read_team_payload($team_id, $user_id);
    if ($payload === []) {
        return [];
    }

    $trial_end = function_exists('aidunite_payment_resolve_trial_end_date')
        ? aidunite_payment_resolve_trial_end_date($team_id, $user_id)
        : (function_exists('aidunite_calculate_trial_end_date')
            ? aidunite_calculate_trial_end_date($team_id)
            : null);
    $is_trial = function_exists('aidunite_is_trial_period')
        ? aidunite_is_trial_period($team_id)
        : false;

    $config = function_exists('aidunite_get_payment_config') ? aidunite_get_payment_config() : [];
    $product_plan = (string) ($payload['product_plan'] ?? 'match');
    $plan_key = $product_plan === 'club' ? 'club' : 'match';
    $plans = $config[$plan_key]['plans'] ?? $config['match']['plans'] ?? [];

    $user_id = (int) $user_id;
    $subscription_state = function_exists('aidunite_payment_exit_read_subscription_state')
        ? aidunite_payment_exit_read_subscription_state($team_id, $user_id)
        : [];
    $exit_state = function_exists('aidunite_payment_exit_read_exit_state_payload')
        ? aidunite_payment_exit_read_exit_state_payload($team_id, $user_id)
        : [];
    $leader_transfer_checkout = null;
    if (function_exists('aidunite_team_read_leader_transfer_pending')) {
        $lt_pending = aidunite_team_read_leader_transfer_pending($team_id);
        if (is_array($lt_pending) && (int) ($lt_pending['to_user_id'] ?? 0) === $user_id) {
            $leader_transfer_checkout = $lt_pending;
        }
    }

    return array_merge($payload, [
        'plan_selection_required' => ($payload['selected_plan_id'] ?? '') === '',
        'plan_display_mode' => function_exists('aidunite_get_plan_display_mode') ? aidunite_get_plan_display_mode() : 'coming_soon',
        'available_plans' => $plans,
        'trial_end_date' => $trial_end ? (string) $trial_end : '',
        'is_trial' => (bool) $is_trial,
        'upgrade_url' => add_query_arg(['upgrade' => 'club'], home_url('/payment-setup')),
        'subscription_state' => $subscription_state,
        'exit_state' => $exit_state,
        'leader_transfer_checkout' => $leader_transfer_checkout,
    ]);
}

/**
 * 月謝 Connect の手数料ポリシー（管理者設定・表示用）
 *
 * @return array<string, mixed>
 */
function aidunite_payment_read_tuition_fee_policy() {
    $config = function_exists('aidunite_get_payment_config') ? aidunite_get_payment_config() : [];
    $defaults = function_exists('aidunite_get_default_payment_config')
        ? aidunite_get_default_payment_config()
        : [];
    $tuition_cfg = is_array($config['tuition'] ?? null) ? $config['tuition'] : [];
    $tuition_defaults = is_array($defaults['tuition'] ?? null) ? $defaults['tuition'] : [];

    $ainy_percent = function_exists('aidunite_payment_normalize_tuition_fee_percent_value')
        ? aidunite_payment_normalize_tuition_fee_percent_value(
            $tuition_cfg['ainy_application_fee_percent'] ?? $tuition_defaults['ainy_application_fee_percent'] ?? 1.4
        )
        : 1.4;
    $stripe_display_percent = function_exists('aidunite_payment_normalize_tuition_fee_percent_value')
        ? aidunite_payment_normalize_tuition_fee_percent_value(
            $tuition_cfg['stripe_connect_fee_percent_display'] ?? $tuition_defaults['stripe_connect_fee_percent_display'] ?? 3.6
        )
        : 3.6;
    $total_display_percent = round($ainy_percent + $stripe_display_percent, 1);

    return [
        'ainy_application_fee_percent' => $ainy_percent,
        'stripe_connect_fee_percent_display' => $stripe_display_percent,
        'display_total_fee_percent' => $total_display_percent,
        'parent_fee_note' => sprintf(
            '月謝のお支払い額から、決済手数料 約%s%%（決済代行 約%s%% ＋ システム利用 %s%%）が差し引かれ、残額がチームに入金されます。',
            number_format($total_display_percent, 1),
            number_format($stripe_display_percent, 1),
            number_format($ainy_percent, 1)
        ),
        'team_fee_note' => sprintf(
            '保護者の月謝決済には、決済手数料 約%s%%（システム利用 %s%% を含む）がかかります。試合マッチング等の Match プラン利用料とは別です。',
            number_format($total_display_percent, 1),
            number_format($ainy_percent, 1)
        ),
    ];
}

/**
 * 月謝 Connect 用 Stripe Product/Price ID（team meta）
 *
 * @param int $team_id
 * @return array<string, string>
 */
function aidunite_payment_read_tuition_connect_stripe_catalog($team_id) {
    $team_id = (int) $team_id;
    if ($team_id <= 0 || get_post_type($team_id) !== 'team') {
        return [
            'stripe_connect_product_id' => '',
            'stripe_connect_price_id' => '',
        ];
    }

    return [
        'stripe_connect_product_id' => trim((string) get_post_meta($team_id, 'stripe_connect_product_id', true)),
        'stripe_connect_price_id' => trim((string) get_post_meta($team_id, 'stripe_connect_price_id', true)),
    ];
}

/**
 * 月謝・Connect 設定画面用
 *
 * @param int $team_id
 * @return array<string, mixed>
 */
function aidunite_payment_read_tuition_display($team_id) {
    $team_id = (int) $team_id;
    if ($team_id <= 0 || get_post_type($team_id) !== 'team') {
        return [];
    }

    $enabled_raw = (string) get_post_meta($team_id, 'team_tuition_enabled', true);
    $monthly_fee = get_post_meta($team_id, 'team_monthly_fee', true);
    $monthly_fee_yen = $monthly_fee !== '' && $monthly_fee !== false ? (int) $monthly_fee : 0;
    $fee_policy = aidunite_payment_read_tuition_fee_policy();
    $ainy_percent = (float) ($fee_policy['ainy_application_fee_percent'] ?? 0);
    $stripe_display_percent = (float) ($fee_policy['stripe_connect_fee_percent_display'] ?? 0);
    $total_display_percent = (float) ($fee_policy['display_total_fee_percent'] ?? 0);
    $ainy_fee_yen = $monthly_fee_yen > 0 ? (int) round($monthly_fee_yen * $ainy_percent / 100) : 0;
    $stripe_fee_display_yen = $monthly_fee_yen > 0 ? (int) round($monthly_fee_yen * $stripe_display_percent / 100) : 0;
    $total_fee_display_yen = $monthly_fee_yen > 0 ? (int) round($monthly_fee_yen * $total_display_percent / 100) : 0;
    $team_net_display_yen = max(0, $monthly_fee_yen - $total_fee_display_yen);

    return [
        'team_id' => $team_id,
        'has_club_plan' => aidunite_payment_read_team_has_club_plan($team_id),
        'team_tuition_enabled' => $enabled_raw === '1',
        'team_monthly_fee' => $monthly_fee_yen,
        'stripe_connect_account_id' => (string) get_post_meta($team_id, 'stripe_connect_account_id', true),
        'fee_policy' => $fee_policy,
        'tuition_fee_ainy_yen' => $ainy_fee_yen,
        'tuition_fee_stripe_display_yen' => $stripe_fee_display_yen,
        'tuition_fee_total_display_yen' => $total_fee_display_yen,
        'tuition_team_net_display_yen' => $team_net_display_yen,
    ];
}

/**
 * @param int $team_id
 * @return string
 */
function aidunite_payment_read_stripe_price_id($team_id) {
    $team_id = (int) $team_id;
    if ($team_id <= 0) {
        return '';
    }

    return trim((string) get_post_meta($team_id, 'stripe_price_id', true));
}

/**
 * @param int $team_id
 * @return string
 */
function aidunite_payment_read_team_stripe_subscription_id($team_id) {
    $team_id = (int) $team_id;
    if ($team_id <= 0) {
        return '';
    }

    return trim((string) get_post_meta($team_id, 'team_stripe_subscription_id', true));
}

/**
 * team post_meta: team_payment_status_updated
 *
 * @param int $team_id
 * @return string
 */
function aidunite_payment_read_team_payment_status_updated($team_id) {
    $team_id = (int) $team_id;
    if ($team_id <= 0) {
        return '';
    }

    return trim((string) get_post_meta($team_id, 'team_payment_status_updated', true));
}

/**
 * システム料サブスク状態（team 正本）
 *
 * @param int $team_id
 * @param int $user_id
 * @return array<string, mixed>
 */
function aidunite_payment_read_team_subscription_state($team_id, $user_id = 0) {
    $team_id = (int) $team_id;
    if ($team_id <= 0) {
        return [];
    }

    $leader_id = (int) $user_id;
    if ($leader_id <= 0) {
        if (function_exists('aidunite_team_resolve_leader_user_id')) {
            $leader_id = (int) aidunite_team_resolve_leader_user_id($team_id);
        } elseif (function_exists('aidunite_team_read_leader_id')) {
            $leader_id = (int) aidunite_team_read_leader_id($team_id);
        }
    }

    $team_status = trim((string) get_post_meta($team_id, 'team_payment_status', true));
    $team_sub = aidunite_payment_read_team_stripe_subscription_id($team_id);

    return [
        'team_id' => $team_id,
        'leader_user_id' => $leader_id,
        'payment_status' => $team_status,
        'stripe_subscription_id' => $team_sub,
        'available_until' => trim((string) get_post_meta($team_id, 'team_payment_available_until', true)),
        'status_updated' => aidunite_payment_read_team_payment_status_updated($team_id),
        'has_subscription' => $team_sub !== '',
        'is_cancelling' => $team_status === 'cancelling',
        'is_cancelled' => $team_status === 'cancelled',
    ];
}

/**
 * Stripe サブスクリプション ID から team_id を逆引き
 *
 * @param string $subscription_id
 * @return int
 */
function aidunite_payment_resolve_team_id_by_stripe_subscription_id($subscription_id) {
    global $wpdb;

    $subscription_id = sanitize_text_field((string) $subscription_id);
    if ($subscription_id === '') {
        return 0;
    }

    $team_id = $wpdb->get_var($wpdb->prepare(
        "SELECT post_id FROM {$wpdb->postmeta}
         WHERE meta_key = 'team_stripe_subscription_id'
         AND meta_value = %s
         LIMIT 1",
        $subscription_id
    ));

    return $team_id ? (int) $team_id : 0;
}

/**
 * Stripe サブスクリプション ID から代表者 user_id を逆引き
 *
 * @param string $subscription_id
 * @return int
 */
function aidunite_payment_resolve_user_id_by_stripe_subscription_id($subscription_id) {
    global $wpdb;

    $subscription_id = sanitize_text_field((string) $subscription_id);
    if ($subscription_id === '') {
        return 0;
    }

    $user_id = $wpdb->get_var($wpdb->prepare(
        "SELECT user_id FROM {$wpdb->usermeta}
         WHERE meta_key = 'stripe_subscription_id'
         AND meta_value = %s
         LIMIT 1",
        $subscription_id
    ));
    if ($user_id) {
        return (int) $user_id;
    }

    $team_id = aidunite_payment_resolve_team_id_by_stripe_subscription_id($subscription_id);
    if ($team_id <= 0) {
        return 0;
    }

    if (function_exists('aidunite_team_resolve_leader_user_id')) {
        $leader_id = (int) aidunite_team_resolve_leader_user_id($team_id);
        if ($leader_id > 0) {
            return $leader_id;
        }
    }
    if (function_exists('aidunite_team_read_leader_id')) {
        return (int) aidunite_team_read_leader_id($team_id);
    }

    return 0;
}

/**
 * team_payment_status=paid のチーム数（課金単位）
 *
 * @return int
 */
function aidunite_payment_read_count_paid_teams() {
    global $wpdb;

    return (int) $wpdb->get_var(
        "SELECT COUNT(DISTINCT pm.post_id)
         FROM {$wpdb->postmeta} pm
         INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
         WHERE p.post_type = 'team'
         AND p.post_status IN ('publish', 'private')
         AND pm.meta_key = 'team_payment_status'
         AND pm.meta_value = 'paid'"
    );
}

/**
 * 有料チームごとの継続月数（team_payment_status_updated 基準）
 *
 * @return array<int, float>
 */
function aidunite_payment_read_paid_team_retention_months() {
    global $wpdb;

    $rows = $wpdb->get_results(
        "SELECT pm.post_id AS team_id, pm2.meta_value AS status_updated
         FROM {$wpdb->postmeta} pm
         INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
         LEFT JOIN {$wpdb->postmeta} pm2
            ON pm2.post_id = pm.post_id AND pm2.meta_key = 'team_payment_status_updated'
         WHERE p.post_type = 'team'
         AND p.post_status IN ('publish', 'private')
         AND pm.meta_key = 'team_payment_status'
         AND pm.meta_value = 'paid'",
        ARRAY_A
    );

    if (!is_array($rows) || $rows === []) {
        return [];
    }

    $now = current_time('timestamp');
    $months_list = [];
    foreach ($rows as $row) {
        $updated = trim((string) ($row['status_updated'] ?? ''));
        $base_ts = $updated !== '' ? strtotime($updated) : $now;
        if ($base_ts === false) {
            $base_ts = $now;
        }
        $months_list[] = max(0, ($now - $base_ts) / (30.44 * 24 * 3600));
    }

    return $months_list;
}

/**
 * システム料リマインド送信対象（team 正本）
 *
 * @return array<int, array{team_id:int,leader_user_id:int}>
 */
function aidunite_payment_read_system_billing_reminder_targets() {
    global $wpdb;

    $rows = $wpdb->get_results(
        "SELECT DISTINCT pm.post_id AS team_id
         FROM {$wpdb->postmeta} pm
         INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
         INNER JOIN {$wpdb->postmeta} pm_status
            ON pm_status.post_id = pm.post_id
            AND pm_status.meta_key = 'team_payment_status'
            AND pm_status.meta_value IN ('paid', 'trial')
         WHERE p.post_type = 'team'
         AND p.post_status IN ('publish', 'private')
         AND pm.meta_key = 'team_stripe_subscription_id'
         AND pm.meta_value != ''",
        ARRAY_A
    );

    if (!is_array($rows) || $rows === []) {
        return [];
    }

    $targets = [];
    foreach ($rows as $row) {
        $team_id = (int) ($row['team_id'] ?? 0);
        if ($team_id <= 0) {
            continue;
        }
        $leader_user_id = 0;
        if (function_exists('aidunite_team_resolve_leader_user_id')) {
            $leader_user_id = (int) aidunite_team_resolve_leader_user_id($team_id);
        } elseif (function_exists('aidunite_team_read_leader_id')) {
            $leader_user_id = (int) aidunite_team_read_leader_id($team_id);
        }
        if ($leader_user_id <= 0) {
            continue;
        }
        $targets[] = [
            'team_id' => $team_id,
            'leader_user_id' => $leader_user_id,
        ];
    }

    return $targets;
}

/**
 * 代表者の操作中チーム課金コンテキスト（制限・導線用）
 *
 * @param int $user_id
 * @return array{team_id:int,payment_status:string,status_updated:string}
 */
function aidunite_payment_read_user_team_billing_context($user_id) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return ['team_id' => 0, 'payment_status' => '', 'status_updated' => ''];
    }

    $team_id = 0;
    if (function_exists('aidunite_get_current_team_id')) {
        $team_id = (int) aidunite_get_current_team_id($user_id);
    }
    if ($team_id <= 0 && function_exists('aidunite_user_read_primary_team_id')) {
        $team_id = (int) aidunite_user_read_primary_team_id($user_id);
    }

    if ($team_id <= 0) {
        return ['team_id' => 0, 'payment_status' => '', 'status_updated' => ''];
    }

    $state = aidunite_payment_read_team_subscription_state($team_id, $user_id);

    return [
        'team_id' => $team_id,
        'payment_status' => (string) ($state['payment_status'] ?? ''),
        'status_updated' => (string) ($state['status_updated'] ?? ''),
    ];
}

/**
 * @param string $ym Y-m
 * @return array{start:string,end:string,label:string}
 */
function aidunite_payment_read_month_window($ym) {
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
 * 月謝支払い履歴テーブルが利用可能か
 *
 * @return bool
 */
function aidunite_payment_read_tuition_payments_table_ready() {
    if (function_exists('aidunite_tuition_payments_table_exists')) {
        return aidunite_tuition_payments_table_exists();
    }

    global $wpdb;
    $table = $wpdb->prefix . 'aidunite_tuition_payments';

    return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
}

/**
 * 月謝実績（WP テーブル）を月別集計
 *
 * @param array<int, string> $month_keys
 * @return array<string, array{gross:int,paid_count:int,ainy_fee:int}>
 */
function aidunite_payment_read_tuition_actuals_by_month(array $month_keys) {
    global $wpdb;

    $result = [];
    foreach ($month_keys as $ym) {
        $result[$ym] = ['gross' => 0, 'paid_count' => 0, 'ainy_fee' => 0];
    }
    if ($month_keys === []) {
        return $result;
    }

    $table = $wpdb->prefix . 'aidunite_tuition_payments';
    if (!aidunite_payment_read_tuition_payments_table_ready()) {
        return $result;
    }

    $fee_policy = function_exists('aidunite_payment_read_tuition_fee_policy')
        ? aidunite_payment_read_tuition_fee_policy()
        : ['ainy_application_fee_percent' => 1.4];
    $ainy_percent = (float) ($fee_policy['ainy_application_fee_percent'] ?? 1.4);

    $oldest = $month_keys[0] . '-01 00:00:00';
    $newest_window = aidunite_payment_read_month_window($month_keys[count($month_keys) - 1]);
    $newest = $newest_window['end'];

    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT DATE_FORMAT(payment_date, '%%Y-%%m') AS ym,
                    SUM(amount) AS gross,
                    COUNT(*) AS paid_count
             FROM {$table}
             WHERE status = %s
             AND payment_date >= %s
             AND payment_date <= %s
             GROUP BY ym",
            'paid',
            $oldest,
            $newest
        ),
        ARRAY_A
    );

    if (!is_array($rows)) {
        return $result;
    }

    foreach ($rows as $row) {
        $ym = (string) ($row['ym'] ?? '');
        if (!isset($result[$ym])) {
            continue;
        }
        $gross = (int) ($row['gross'] ?? 0);
        $result[$ym] = [
            'gross' => $gross,
            'paid_count' => (int) ($row['paid_count'] ?? 0),
            'ainy_fee' => (int) round($gross * $ainy_percent / 100),
        ];
    }

    return $result;
}

/**
 * 保護者の直近月謝支払い1件
 *
 * @param int $parent_user_id
 * @param int $team_id
 * @return array<string, mixed>|null
 */
function aidunite_payment_read_parent_latest_tuition_payment($parent_user_id, $team_id) {
    global $wpdb;

    $parent_user_id = (int) $parent_user_id;
    $team_id = (int) $team_id;
    if ($parent_user_id <= 0 || $team_id <= 0) {
        return null;
    }

    if (!aidunite_payment_read_tuition_payments_table_ready()) {
        return null;
    }

    $table = $wpdb->prefix . 'aidunite_tuition_payments';
    $row = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT amount, status, payment_date
             FROM {$table}
             WHERE team_id = %d AND parent_user_id = %d
             ORDER BY payment_date DESC
             LIMIT 1",
            $team_id,
            $parent_user_id
        ),
        ARRAY_A
    );

    return is_array($row) ? $row : null;
}

/**
 * @param int $team_id
 * @return string
 */
function aidunite_payment_read_stripe_connect_account_id($team_id) {
    $tuition = aidunite_payment_read_tuition_display($team_id);

    return (string) ($tuition['stripe_connect_account_id'] ?? '');
}

/**
 * 月謝徴収がブロックされている理由（代表者向け UX）
 *
 * @param int $team_id
 * @return string club_plan_required|tuition_disabled|monthly_fee_unset|connect_incomplete|''
 */
function aidunite_payment_read_team_tuition_connect_block_reason($team_id) {
    $team_id = (int) $team_id;
    if ($team_id <= 0) {
        return 'tuition_disabled';
    }

    $display = aidunite_payment_read_tuition_display($team_id);
    if (empty($display['has_club_plan'])) {
        return 'club_plan_required';
    }
    if (empty($display['team_tuition_enabled'])) {
        return 'tuition_disabled';
    }
    $monthly_fee = (int) ($display['team_monthly_fee'] ?? 0);
    if ($monthly_fee <= 0) {
        return 'monthly_fee_unset';
    }
    $connect_acct = trim((string) ($display['stripe_connect_account_id'] ?? ''));
    if ($connect_acct === '' || strpos($connect_acct, 'acct_') !== 0) {
        return 'connect_incomplete';
    }

    return '';
}

/**
 * 月謝 Connect ブロック理由に応じた表示文言（代表者 / 保護者でトーンを分ける）
 *
 * @param string $reason club_plan_required|tuition_disabled|monthly_fee_unset|connect_incomplete|''
 * @param string $audience leader|parent
 * @return string
 */
function aidunite_payment_read_tuition_block_message($reason, $audience = 'leader') {
    $reason = (string) $reason;
    $audience = $audience === 'parent' ? 'parent' : 'leader';

    $leader_messages = [
        'club_plan_required' => '月謝機能は Club プランのチームのみ利用できます。プラン変更はサポートまでお問い合わせください。',
        'tuition_disabled' => '月謝機能が無効です。有効にすると保護者がカード登録できるようになります。',
        'monthly_fee_unset' => '月謝金額が未設定です。金額を設定してから保護者への案内を開始してください。',
        'connect_incomplete' => 'Stripe Connect の連携が未完了です。口座・本人確認を完了するまで、保護者は月謝を支払えません。',
    ];

    $parent_messages = [
        'club_plan_required' => 'このチームでは月謝のお支払い機能はまだ利用できません。チーム代表者にお問い合わせください。',
        'tuition_disabled' => 'このチームでは月謝のお支払いがまだ有効になっていません。チーム代表者にお問い合わせください。',
        'monthly_fee_unset' => '月謝の金額設定が完了していません。チーム代表者に設定完了をお問い合わせください。',
        'connect_incomplete' => 'チームの決済連携が完了していません。設定が完了するまでお支払いはできません。チーム代表者にお問い合わせください。',
    ];

    $messages = $audience === 'parent' ? $parent_messages : $leader_messages;
    $fallback = $audience === 'parent'
        ? 'このチームでは月謝のお支払い設定はまだ利用できません。チーム代表者にお問い合わせください。'
        : '月謝機能が有効になっていないか、Stripe Connect の連携が未完了です。';

    if ($reason === '') {
        return $fallback;
    }

    return $messages[$reason] ?? $fallback;
}

/**
 * チームで保護者向け月謝徴収が有効か（Club・Connect・金額）
 *
 * @param int $team_id
 * @return bool
 */
function aidunite_payment_team_tuition_open_for_parents($team_id) {
    $team_id = (int) $team_id;
    if ($team_id <= 0) {
        return false;
    }

    $display = aidunite_payment_read_tuition_display($team_id);
    if (empty($display['team_tuition_enabled']) || empty($display['has_club_plan'])) {
        return false;
    }

    $monthly_fee = (int) ($display['team_monthly_fee'] ?? 0);
    if ($monthly_fee <= 0) {
        return false;
    }

    $connect_acct = trim((string) ($display['stripe_connect_account_id'] ?? ''));

    return $connect_acct !== '' && strpos($connect_acct, 'acct_') === 0;
}

/**
 * 保護者の Connect Customer ID（team 単位 user meta）
 *
 * @param int $parent_user_id
 * @param int $team_id
 * @return string
 */
function aidunite_user_read_connect_customer_id($parent_user_id, $team_id) {
    $parent_user_id = (int) $parent_user_id;
    $team_id = (int) $team_id;
    if ($parent_user_id <= 0 || $team_id <= 0) {
        return '';
    }

    return trim((string) get_user_meta($parent_user_id, 'stripe_connect_customer_' . $team_id, true));
}

/**
 * 保護者の月謝サブスクリプション ID（team 単位 user meta）
 *
 * @param int $parent_user_id
 * @param int $team_id
 * @return string
 */
function aidunite_user_read_tuition_subscription_id($parent_user_id, $team_id) {
    $parent_user_id = (int) $parent_user_id;
    $team_id = (int) $team_id;
    if ($parent_user_id <= 0 || $team_id <= 0) {
        return '';
    }

    return trim((string) get_user_meta($parent_user_id, 'stripe_tuition_subscription_' . $team_id, true));
}

/**
 * 保護者が契約している月謝サブスクリプションの team_id 一覧
 *
 * @param int $parent_user_id
 * @return int[]
 */
function aidunite_user_read_tuition_subscription_team_ids($parent_user_id) {
    global $wpdb;
    $parent_user_id = (int) $parent_user_id;
    if ($parent_user_id <= 0) {
        return [];
    }
    $like = $wpdb->esc_like('stripe_tuition_subscription_') . '%';
    $rows = $wpdb->get_col($wpdb->prepare(
        "SELECT meta_key FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key LIKE %s",
        $parent_user_id,
        $like
    ));
    $prefix = 'stripe_tuition_subscription_';
    $team_ids = [];
    foreach ((array) $rows as $meta_key) {
        if (strpos((string) $meta_key, $prefix) !== 0) {
            continue;
        }
        $team_id = (int) substr((string) $meta_key, strlen($prefix));
        if ($team_id > 0) {
            $team_ids[] = $team_id;
        }
    }

    return array_values(array_unique($team_ids));
}

/**
 * 保護者がチーム向け月謝サブスクを登録済みか
 *
 * @param int $parent_user_id
 * @param int $team_id
 * @return bool
 */
function aidunite_payment_parent_has_tuition_subscription($parent_user_id, $team_id) {
    $parent_user_id = (int) $parent_user_id;
    $team_id = (int) $team_id;
    if ($parent_user_id <= 0 || $team_id <= 0) {
        return false;
    }

    $subscription_id = aidunite_user_read_tuition_subscription_id($parent_user_id, $team_id);

    if ($subscription_id !== '') {
        return true;
    }

    if (function_exists('aidunite_payment_sync_parent_tuition_subscription_from_stripe')) {
        return aidunite_payment_sync_parent_tuition_subscription_from_stripe($parent_user_id, $team_id) !== '';
    }

    return false;
}

/**
 * 保護者マイページ「やること」用 — 月謝カード未登録のチーム
 *
 * @param int $parent_user_id
 * @return array<int, array<string, mixed>>
 */
function aidunite_payment_read_parent_tuition_action_required_items($parent_user_id) {
    $parent_user_id = (int) $parent_user_id;
    if ($parent_user_id <= 0) {
        return [];
    }

    if (!function_exists('aidunite_payment_parent_tuition_flows_enabled')
        || !aidunite_payment_parent_tuition_flows_enabled()) {
        return [];
    }

    if (function_exists('aidunite_parent_is_pending_only_mypage')
        && aidunite_parent_is_pending_only_mypage($parent_user_id)) {
        return [];
    }

    $team_ids = function_exists('aidunite_get_managed_team_ids')
        ? aidunite_get_managed_team_ids($parent_user_id)
        : [];
    if ($team_ids === []) {
        $legacy = (int) get_user_meta($parent_user_id, 'team_id', true);
        if ($legacy > 0) {
            $team_ids = [$legacy];
        }
    }

    $items = [];
    foreach ($team_ids as $team_id) {
        $team_id = (int) $team_id;
        if ($team_id <= 0) {
            continue;
        }

        if (function_exists('aidunite_parent_user_is_pending_for_team')
            && aidunite_parent_user_is_pending_for_team($parent_user_id, $team_id)) {
            continue;
        }

        if (!aidunite_payment_team_tuition_open_for_parents($team_id)) {
            continue;
        }

        if (aidunite_payment_parent_has_tuition_subscription($parent_user_id, $team_id)) {
            continue;
        }

        $team_name = '';
        if (function_exists('aidunite_team_get_display_bundle')) {
            $bundle = aidunite_team_get_display_bundle($team_id);
            $team_name = (string) ($bundle['team_name'] ?? '');
        }
        if ($team_name === '') {
            $team_post = get_post($team_id);
            $team_name = $team_post ? (string) $team_post->post_title : '';
        }

        $tuition_display = aidunite_payment_read_tuition_display($team_id);
        $monthly_fee = (int) ($tuition_display['team_monthly_fee'] ?? 0);
        $fee_label = $monthly_fee > 0
            ? '月額 ¥' . number_format($monthly_fee)
            : '月謝のカード登録が必要です';

        $description = $team_name !== ''
            ? $team_name . ' — ' . $fee_label . '（毎月自動お支払い）'
            : $fee_label . '（毎月自動お支払い）';

        $items[] = [
            'type' => 'tuition_payment',
            'id' => $team_id,
            'title' => '月謝のお支払い設定',
            'description' => $description,
            'deadline' => null,
            'link_url' => home_url('/parent-payment'),
            'actions' => [
                ['label' => 'カードを登録する', 'action' => 'open', 'type' => 'primary'],
            ],
        ];
    }

    return $items;
}

/**
 * 保護者メニューに月謝ページを出すか（登録済みでも履歴確認用に表示可）
 *
 * @param int $parent_user_id
 * @return bool
 */
function aidunite_payment_parent_should_show_tuition_menu($parent_user_id) {
    $parent_user_id = (int) $parent_user_id;
    if ($parent_user_id <= 0) {
        return false;
    }

    if (!function_exists('aidunite_payment_parent_tuition_flows_enabled')
        || !aidunite_payment_parent_tuition_flows_enabled()) {
        return false;
    }

    if (function_exists('aidunite_parent_is_pending_only_mypage')
        && aidunite_parent_is_pending_only_mypage($parent_user_id)) {
        return false;
    }

    $team_ids = function_exists('aidunite_get_managed_team_ids')
        ? aidunite_get_managed_team_ids($parent_user_id)
        : [];
    if ($team_ids === []) {
        $legacy = (int) get_user_meta($parent_user_id, 'team_id', true);
        if ($legacy > 0) {
            $team_ids = [$legacy];
        }
    }

    foreach ($team_ids as $team_id) {
        $team_id = (int) $team_id;
        if ($team_id <= 0) {
            continue;
        }
        if (function_exists('aidunite_parent_user_is_pending_for_team')
            && aidunite_parent_user_is_pending_for_team($parent_user_id, $team_id)) {
            continue;
        }
        if (aidunite_payment_team_tuition_open_for_parents($team_id)) {
            return true;
        }
    }

    return false;
}

/**
 * 月謝支払いステータス表示ラベル
 *
 * @param string $status
 * @return string
 */
function aidunite_payment_normalize_tuition_payment_status_label($status) {
    $status = strtolower(trim((string) $status));
    $labels = [
        'paid' => '済',
        'failed' => '失敗',
        'cancelled' => '解約',
        'unpaid' => '未払い',
    ];

    return $labels[$status] ?? $status;
}

/**
 * 代表者向け：保護者の当月徴収ステータスキー
 *
 * @param string $status paid|pending_billing|overdue|failed|not_registered|disabled
 * @return array{key: string, label: string}
 */
function aidunite_payment_normalize_tuition_parent_month_status($status) {
    $status = strtolower(trim((string) $status));
    $map = [
        'paid' => ['key' => 'paid', 'label' => '済'],
        'pending_billing' => ['key' => 'pending_billing', 'label' => '請求前'],
        'overdue' => ['key' => 'overdue', 'label' => '未払い'],
        'failed' => ['key' => 'failed', 'label' => '失敗'],
        'not_registered' => ['key' => 'not_registered', 'label' => '未登録'],
        'disabled' => ['key' => 'disabled', 'label' => '—'],
    ];

    return $map[$status] ?? ['key' => 'overdue', 'label' => '未払い'];
}

/**
 * 当月の月謝請求締め（月末 23:59:59）を過ぎたか
 */
function aidunite_payment_is_after_current_month_billing_deadline() {
    $tz = wp_timezone();
    $now = new DateTimeImmutable('now', $tz);
    $deadline = $now->modify('last day of this month')->setTime(23, 59, 59);

    return $now > $deadline;
}

/**
 * 当月の開始・終了（サイトTZ）
 *
 * @return array{start: string, end: string, label: string}
 */
function aidunite_payment_read_current_month_window() {
    $tz = wp_timezone();
    $now = new DateTimeImmutable('now', $tz);
    $start = $now->modify('first day of this month')->setTime(0, 0, 0);
    $end = $now->modify('last day of this month')->setTime(23, 59, 59);

    return [
        'start' => $start->format('Y-m-d H:i:s'),
        'end' => $end->format('Y-m-d H:i:s'),
        'label' => wp_date('Y年n月', $now->getTimestamp()),
    ];
}

/**
 * 保護者向け月謝ステータスラベル
 *
 * @param string $status_raw
 * @return string
 */
function aidunite_payment_format_tuition_status_label_for_parent($status_raw) {
    $status = sanitize_key((string) $status_raw);
    $labels = [
        'paid' => '支払い済み',
        'failed' => '支払い失敗',
        'unpaid' => '未払い',
        'cancelled' => '解約',
    ];

    return $labels[$status] ?? $status;
}

/**
 * stripe_invoice_id で月謝履歴1件を取得
 *
 * @param string $stripe_invoice_id
 * @param int    $parent_user_id
 * @param int    $team_id
 * @return array<string, mixed>|null
 */
function aidunite_payment_read_tuition_payment_row_by_invoice_id($stripe_invoice_id, $parent_user_id, $team_id) {
    global $wpdb;

    $stripe_invoice_id = trim((string) $stripe_invoice_id);
    $parent_user_id = (int) $parent_user_id;
    $team_id = (int) $team_id;
    if ($stripe_invoice_id === '' || $parent_user_id <= 0 || $team_id <= 0) {
        return null;
    }

    if (!aidunite_payment_read_tuition_payments_table_ready()) {
        return null;
    }

    $table = $wpdb->prefix . 'aidunite_tuition_payments';
    $row = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT id, payment_date, amount, status, stripe_invoice_id
             FROM {$table}
             WHERE stripe_invoice_id = %s
             AND parent_user_id = %d
             AND team_id = %d
             ORDER BY id DESC
             LIMIT 1",
            $stripe_invoice_id,
            $parent_user_id,
            $team_id
        ),
        ARRAY_A
    );

    return is_array($row) ? $row : null;
}

/**
 * 保護者の月謝支払い履歴（DB）
 *
 * @param int $parent_user_id
 * @param int $team_id
 * @param int $limit
 * @return array<int, array<string, mixed>>
 */
function aidunite_payment_read_parent_tuition_history($parent_user_id, $team_id, $limit = 50) {
    global $wpdb;

    $parent_user_id = (int) $parent_user_id;
    $team_id = (int) $team_id;
    $limit = max(1, min(100, (int) $limit));
    if ($parent_user_id <= 0 || $team_id <= 0) {
        return [];
    }

    if (!aidunite_payment_read_tuition_payments_table_ready()) {
        return [];
    }

    $table = $wpdb->prefix . 'aidunite_tuition_payments';
    // 同一 stripe_invoice_id の重複行（Webhook 再送等）は最新1件のみ表示
    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT t.payment_date, t.amount, t.status
             FROM {$table} t
             INNER JOIN (
                 SELECT MAX(id) AS keep_id
                 FROM {$table}
                 WHERE parent_user_id = %d
                 AND team_id = %d
                 GROUP BY IF(stripe_invoice_id <> '', stripe_invoice_id, CONCAT('row-', id))
             ) dedup ON t.id = dedup.keep_id
             ORDER BY t.payment_date DESC
             LIMIT %d",
            $parent_user_id,
            $team_id,
            $limit
        ),
        ARRAY_A
    );

    return is_array($rows) ? $rows : [];
}

/**
 * チームの月謝イベント（期間指定）
 *
 * @param int    $team_id
 * @param string $start mysql datetime
 * @param string $end   mysql datetime
 * @return array<int, array<string, mixed>>
 */
function aidunite_payment_read_team_tuition_payment_rows($team_id, $start, $end) {
    global $wpdb;

    $team_id = (int) $team_id;
    if ($team_id <= 0) {
        return [];
    }

    if (!aidunite_payment_read_tuition_payments_table_ready()) {
        return [];
    }

    $table = $wpdb->prefix . 'aidunite_tuition_payments';
    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT id, parent_user_id, amount, status, payment_date, stripe_invoice_id
             FROM {$table}
             WHERE team_id = %d
             AND payment_date >= %s
             AND payment_date <= %s
             ORDER BY payment_date DESC",
            $team_id,
            $start,
            $end
        ),
        ARRAY_A
    );

    return is_array($rows) ? $rows : [];
}

/**
 * チームの直近月謝イベント
 *
 * @param int $team_id
 * @param int $limit
 * @return array<int, array<string, mixed>>
 */
function aidunite_payment_read_team_tuition_recent_events($team_id, $limit = 30) {
    global $wpdb;

    $team_id = (int) $team_id;
    $limit = max(1, min(100, (int) $limit));
    if ($team_id <= 0) {
        return [];
    }

    if (!aidunite_payment_read_tuition_payments_table_ready()) {
        return [];
    }

    $table = $wpdb->prefix . 'aidunite_tuition_payments';
    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT id, parent_user_id, amount, status, payment_date
             FROM {$table}
             WHERE team_id = %d
             ORDER BY payment_date DESC
             LIMIT %d",
            $team_id,
            $limit
        ),
        ARRAY_A
    );

    return is_array($rows) ? $rows : [];
}

/**
 * カード未登録フォロー用の案内文
 *
 * @param string $team_name
 * @param int    $monthly_fee_yen
 * @param string $parent_payment_url
 * @return string
 */
function aidunite_payment_build_tuition_not_registered_followup_message($team_name, $monthly_fee_yen, $parent_payment_url) {
    $team_name = trim((string) $team_name);
    if ($team_name === '') {
        $team_name = 'チーム';
    }
    $fee_line = (int) $monthly_fee_yen > 0
        ? '月謝：' . number_format((int) $monthly_fee_yen) . '円（毎月末に自動決済）'
        : '月謝のお支払い（毎月末に自動決済）';
    $payment_url = trim((string) $parent_payment_url);
    if ($payment_url === '') {
        $payment_url = home_url('/parent-payment');
    }

    return implode("\n", [
        '【' . $team_name . '】月謝カード登録のお願い',
        '',
        'お世話になっております。',
        $team_name . 'の保護者会より、月謝のお支払いカード登録についてご連絡です。',
        '',
        $fee_line,
        '登録ページ：' . $payment_url,
        '',
        'ご不明点があれば、チーム代表までお問い合わせください。',
    ]);
}

/**
 * 決済失敗フォロー用の案内文
 *
 * @param string $team_name
 * @param string $parent_payment_url
 * @return string
 */
function aidunite_payment_build_tuition_failed_followup_message($team_name, $parent_payment_url) {
    $team_name = trim((string) $team_name);
    if ($team_name === '') {
        $team_name = 'チーム';
    }
    $payment_url = trim((string) $parent_payment_url);
    if ($payment_url === '') {
        $payment_url = home_url('/parent-payment');
    }

    return implode("\n", [
        '【' . $team_name . '】月謝のお支払いについて（再登録のお願い）',
        '',
        'お世話になっております。',
        '月謝の自動決済が完了しませんでした。カード情報のご確認をお願いいたします。',
        '',
        'お支払いページ：' . $payment_url,
        '',
        'ご不明点があれば、チーム代表までお問い合わせください。',
    ]);
}

/**
 * 未払いフォロー用の案内文
 *
 * @param string $team_name
 * @param int    $monthly_fee_yen
 * @param string $parent_payment_url
 * @return string
 */
function aidunite_payment_build_tuition_unpaid_followup_message($team_name, $monthly_fee_yen, $parent_payment_url) {
    $team_name = trim((string) $team_name);
    if ($team_name === '') {
        $team_name = 'チーム';
    }
    $fee_line = (int) $monthly_fee_yen > 0
        ? '月謝：' . number_format((int) $monthly_fee_yen) . '円（毎月末に自動決済）'
        : '月謝のお支払い（毎月末に自動決済）';
    $payment_url = trim((string) $parent_payment_url);
    if ($payment_url === '') {
        $payment_url = home_url('/parent-payment');
    }

    return implode("\n", [
        '【' . $team_name . '】月謝のお支払いのご案内',
        '',
        'お世話になっております。',
        $team_name . 'の保護者会より、月謝のお支払いについてご連絡です。',
        '',
        $fee_line,
        'お支払いページ：' . $payment_url,
        '',
        'すでにお支払い済みの場合は、本メッセージは行き違いの可能性があります。ご容赦ください。',
        'ご不明点があれば、チーム代表までお問い合わせください。',
    ]);
}

/**
 * 代表者向け：月謝徴収状況ページ model
 *
 * @param int $team_id
 * @return array<string, mixed>
 */
function aidunite_payment_read_team_tuition_collections_page_model($team_id) {
    $team_id = (int) $team_id;
    if ($team_id <= 0 || get_post_type($team_id) !== 'team') {
        return [];
    }

    $tuition_display = aidunite_payment_read_tuition_display($team_id);
    $tuition_enabled = !empty($tuition_display['team_tuition_enabled']);
    $monthly_fee = (int) ($tuition_display['team_monthly_fee'] ?? 0);
    $tuition_open = function_exists('aidunite_payment_team_tuition_open_for_parents')
        ? aidunite_payment_team_tuition_open_for_parents($team_id)
        : false;
    $connect_block_reason = function_exists('aidunite_payment_read_team_tuition_connect_block_reason')
        ? aidunite_payment_read_team_tuition_connect_block_reason($team_id)
        : '';
    $connect_ready = $connect_block_reason === '' && $tuition_enabled;

    $team_bundle = function_exists('aidunite_team_get_display_bundle')
        ? aidunite_team_get_display_bundle($team_id)
        : [];
    $team_name = (string) ($team_bundle['team_name'] ?? get_the_title($team_id));

    $month_window = aidunite_payment_read_current_month_window();
    $month_rows = aidunite_payment_read_team_tuition_payment_rows(
        $team_id,
        (string) $month_window['start'],
        (string) $month_window['end']
    );

    $month_rows_by_parent = [];
    $summary = [
        'parent_count' => 0,
        'paid_count' => 0,
        'pending_billing_count' => 0,
        'overdue_count' => 0,
        'failed_count' => 0,
        'not_registered_count' => 0,
        'action_needed_count' => 0,
        'registered_count' => 0,
        'collected_total_yen' => 0,
        'expected_total_yen' => 0,
    ];
    $billing_past_deadline = aidunite_payment_is_after_current_month_billing_deadline();
    foreach ($month_rows as $row) {
        $parent_id = (int) ($row['parent_user_id'] ?? 0);
        if ($parent_id <= 0) {
            continue;
        }
        if (!isset($month_rows_by_parent[$parent_id])) {
            $month_rows_by_parent[$parent_id] = [];
        }
        $month_rows_by_parent[$parent_id][] = $row;
        if (strtolower((string) ($row['status'] ?? '')) === 'paid') {
            $summary['collected_total_yen'] += (int) ($row['amount'] ?? 0);
        }
    }

    $parents = [];

    $parent_source = function_exists('aidunite_parent_read_team_parent_rows')
        ? aidunite_parent_read_team_parent_rows($team_id, 'active')
        : [];

    foreach ($parent_source as $parent_source_row) {
        $parent_user_id = (int) ($parent_source_row['user_id'] ?? 0);
        if ($parent_user_id <= 0) {
            continue;
        }

        $built = function_exists('aidunite_team_members_build_parent_row')
            ? aidunite_team_members_build_parent_row($parent_user_id, $team_id)
            : [];
        if ($built === []) {
            continue;
        }

        $has_subscription = function_exists('aidunite_payment_parent_has_tuition_subscription')
            && aidunite_payment_parent_has_tuition_subscription($parent_user_id, $team_id);

        $month_status_key = 'not_registered';
        if (!$tuition_open) {
            $month_status_key = 'disabled';
        } elseif ($has_subscription) {
            $summary['registered_count']++;
            $parent_month_rows = $month_rows_by_parent[$parent_user_id] ?? [];
            if ($parent_month_rows !== []) {
                $latest = $parent_month_rows[0];
                foreach ($parent_month_rows as $candidate) {
                    if (strcmp((string) ($candidate['payment_date'] ?? ''), (string) ($latest['payment_date'] ?? '')) > 0) {
                        $latest = $candidate;
                    }
                }
                $latest_status = strtolower((string) ($latest['status'] ?? ''));
                if ($latest_status === 'paid') {
                    $month_status_key = 'paid';
                } elseif ($latest_status === 'failed') {
                    $month_status_key = 'failed';
                } elseif ($billing_past_deadline) {
                    $month_status_key = 'overdue';
                } else {
                    $month_status_key = 'pending_billing';
                }
            } elseif ($billing_past_deadline) {
                $month_status_key = 'overdue';
            } else {
                $month_status_key = 'pending_billing';
            }
        }

        $status_payload = aidunite_payment_normalize_tuition_parent_month_status($month_status_key);
        $summary['parent_count']++;
        if ($month_status_key === 'paid') {
            $summary['paid_count']++;
        } elseif ($month_status_key === 'failed') {
            $summary['failed_count']++;
            $summary['action_needed_count']++;
        } elseif ($month_status_key === 'not_registered') {
            $summary['not_registered_count']++;
            $summary['action_needed_count']++;
        } elseif ($month_status_key === 'overdue') {
            $summary['overdue_count']++;
            $summary['action_needed_count']++;
        } elseif ($month_status_key === 'pending_billing') {
            $summary['pending_billing_count']++;
        }

        $parent_name = trim((string) (($built['parent_name_sei'] ?? '') . ' ' . ($built['parent_name_mei'] ?? '')));
        if ($parent_name === '') {
            $parent_name = (string) ($built['display_name'] ?? '');
        }

        $parents[] = [
            'user_id' => $parent_user_id,
            'display_name' => (string) ($built['display_name'] ?? ''),
            'parent_name' => $parent_name,
            'parent_email' => (string) ($built['parent_email'] ?? ''),
            'linked_child_summary' => (string) ($built['linked_child_summary'] ?? '—'),
            'month_status' => (string) $status_payload['key'],
            'month_status_label' => (string) $status_payload['label'],
            'has_subscription' => $has_subscription,
            'needs_action' => in_array($month_status_key, ['failed', 'overdue', 'not_registered'], true),
        ];
    }

    if ($monthly_fee > 0) {
        $summary['expected_total_yen'] = (int) $summary['registered_count'] * $monthly_fee;
    }

    usort($parents, static function ($a, $b) {
        $order = ['failed' => 0, 'overdue' => 1, 'not_registered' => 2, 'pending_billing' => 3, 'paid' => 4, 'disabled' => 5];
        $rank_a = $order[$a['month_status'] ?? ''] ?? 9;
        $rank_b = $order[$b['month_status'] ?? ''] ?? 9;
        if ($rank_a !== $rank_b) {
            return $rank_a <=> $rank_b;
        }

        return strcmp((string) ($a['parent_name'] ?? ''), (string) ($b['parent_name'] ?? ''));
    });

    $recent_events = [];
    foreach (aidunite_payment_read_team_tuition_recent_events($team_id, 10) as $event_row) {
        $parent_user_id = (int) ($event_row['parent_user_id'] ?? 0);
        $user = $parent_user_id > 0 ? get_userdata($parent_user_id) : false;
        $display_name = $user ? (string) $user->display_name : '—';
        $status = strtolower((string) ($event_row['status'] ?? ''));
        $recent_events[] = [
            'payment_date' => (string) ($event_row['payment_date'] ?? ''),
            'parent_user_id' => $parent_user_id,
            'parent_display_name' => $display_name,
            'amount_yen' => (int) ($event_row['amount'] ?? 0),
            'status' => $status,
            'status_label' => aidunite_payment_normalize_tuition_payment_status_label($status),
        ];
    }

    $parent_payment_url = home_url('/parent-payment');

    return [
        'team_id' => $team_id,
        'team_name' => $team_name,
        'month_label' => (string) $month_window['label'],
        'tuition_enabled' => $tuition_enabled,
        'tuition_open' => $tuition_open,
        'connect_ready' => $connect_ready,
        'connect_block_reason' => $connect_block_reason,
        'monthly_fee' => $monthly_fee,
        'billing_note' => '月謝は毎月末に自動決済されます。請求前の状態は未払いではありません。',
        'default_filter' => 'action_needed',
        'summary' => $summary,
        'parents' => $parents,
        'recent_events' => $recent_events,
        'followup_messages' => [
            'overdue' => aidunite_payment_build_tuition_unpaid_followup_message(
                $team_name,
                $monthly_fee,
                $parent_payment_url
            ),
            'failed' => aidunite_payment_build_tuition_failed_followup_message(
                $team_name,
                $parent_payment_url
            ),
            'not_registered' => aidunite_payment_build_tuition_not_registered_followup_message(
                $team_name,
                $monthly_fee,
                $parent_payment_url
            ),
        ],
        'parent_payment_url' => $parent_payment_url,
        'settings_url' => home_url('/team-payment-management'),
    ];
}
