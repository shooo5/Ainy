<?php
/**
 * 決済設定・データ構造管理（ファサード）
 *
 * システム料の正本は team meta。代表者 user の stripe_customer_id のみ user 正本。
 * payment_status / stripe_subscription_id の read は team から解決する。
 *
 * @see docs/spec/payment.md §11 persist
 */

/**
 * プラン表示モードを取得
 * coming_soon = プランカードをカミングスーン表示、trial_card = お試しカードを表示（リリース前用）
 */
function aidunite_get_plan_display_mode() {
    $config = get_option('aidunite_payment_config');
    if (empty($config) || !isset($config['plan_display_mode'])) {
        return 'coming_soon';
    }
    $mode = $config['plan_display_mode'];
    return in_array($mode, ['coming_soon', 'trial_card'], true) ? $mode : 'coming_soon';
}

/**
 * デフォルトの決済設定を取得
 */
function aidunite_get_default_payment_config() {
    $match_plan = [
        'id' => 'plan_match',
        'name' => 'Matchプラン',
        'trial_type' => 'free_months',
        'trial_value' => aidunite_payment_get_default_trial_months(),
        'description' => '2ヶ月無料',
        'is_default' => true,
    ];

    return [
        'plan_display_mode' => 'coming_soon',
        'match' => [
            'monthly_amount' => 2000,
            'plans' => [$match_plan],
        ],
        'club' => [
            'per_player_amount' => 500,
            'minimum_addon' => 6000,
            'plans' => [
                array_merge($match_plan, [
                    'id' => 'plan_club',
                    'name' => 'Clubプラン',
                    'description' => 'Match機能＋クラブ運営（2ヶ月無料）',
                ]),
            ],
        ],
        'founding_team' => [
            'max_slots' => 50,
            'first_year_discount_percent' => 50,
        ],
        'multi_team_discount' => [
            'enabled' => true,
            'match_second_team_percent' => 20,
        ],
        'leader_transfer' => [
            'checkout_deadline_days' => 14,
        ],
        'exit_gates' => [
            'major_holiday_ranges' => [
                ['start' => '12-29', 'end' => '01-03', 'cross_year' => true],
                ['start' => '04-29', 'end' => '05-05', 'cross_year' => false],
                ['start' => '08-13', 'end' => '08-16', 'cross_year' => false],
            ],
        ],
        'first_match_billing_prompt' => [
            'payment_setup_snooze_hours' => 48,
        ],
        'tuition' => [
            'ainy_application_fee_percent' => 1.4,
            'stripe_connect_fee_percent_display' => 3.6,
        ],
    ];
}

/**
 * 無料トライアルの暦月数（C案: お申し込み月＋翌月）
 */
function aidunite_payment_get_default_trial_months() {
    return 2;
}

/**
 * 無料トライアル（2暦月）の共通文言
 *
 * @return array<string, mixed>
 */
function aidunite_payment_get_trial_copy() {
    $months = aidunite_payment_get_default_trial_months();

    return [
        'months' => $months,
        'short' => '2ヶ月無料',
        'label' => '2ヶ月無料でお試しいただけます',
        'cta' => '2ヶ月無料で始める',
        'plan_description' => '2ヶ月無料',
        'club_plan_description' => 'Match機能＋クラブ運営（2ヶ月無料）',
        'highlight' => 'お申し込み月と翌月は無料です。',
        'period_note' => 'お申し込み月と翌月は無料（翌々月1日から毎月1日に請求）',
        'period_fallback' => '2ヶ月無料期間中',
        'period_fallback_note' => 'お申し込み月と翌月は料金がかかりません',
        'campaign_fallback' => '2ヶ月無料でご利用いただけます',
        'fee_note' => '2ヶ月無料',
        'fee_applied_note' => '2ヶ月無料は適用済みです',
        'fee_metric_label' => '無料期間中の料金',
        'cta_fallback_note' => 'お申し込み月と翌月は無料です。翌々月1日から毎月1日に請求されます。',
    ];
}

/**
 * 決済設定を取得
 */
function aidunite_get_payment_config() {
    $config = get_option('aidunite_payment_config');

    if (empty($config)) {
        $config = aidunite_get_default_payment_config();
        update_option('aidunite_payment_config', $config);
    }

    $default_config = aidunite_get_default_payment_config();

    if (!isset($config['match'])) {
        $config['match'] = $default_config['match'];
    }
    if (!isset($config['club']['per_player_amount']) && isset($config['club']['base_amount'])) {
        $config['club']['per_player_amount'] = (int) $config['club']['base_amount'];
    }
    if (!isset($config['club']['per_player_amount'])) {
        $config['club']['per_player_amount'] = $default_config['club']['per_player_amount'];
    }
    if (!isset($config['club']['minimum_addon'])) {
        $config['club']['minimum_addon'] = $default_config['club']['minimum_addon'];
    }
    if (!isset($config['club']['plans'])) {
        $config['club']['plans'] = $default_config['club']['plans'];
    }
    if (!isset($config['founding_team'])) {
        $config['founding_team'] = $default_config['founding_team'];
    }
    if (!isset($config['multi_team_discount'])) {
        $config['multi_team_discount'] = $default_config['multi_team_discount'];
    }
    if (!isset($config['multi_team_discount']['match_second_team_percent'])) {
        $config['multi_team_discount']['match_second_team_percent']
            = $default_config['multi_team_discount']['match_second_team_percent'];
    }
    if (!isset($config['leader_transfer'])) {
        $config['leader_transfer'] = $default_config['leader_transfer'];
    }
    if (!isset($config['leader_transfer']['checkout_deadline_days'])) {
        $config['leader_transfer']['checkout_deadline_days'] = $default_config['leader_transfer']['checkout_deadline_days'];
    }
    if (!isset($config['exit_gates'])) {
        $config['exit_gates'] = $default_config['exit_gates'];
    }
    if (!isset($config['first_match_billing_prompt'])) {
        $config['first_match_billing_prompt'] = $default_config['first_match_billing_prompt'];
    }
    if (!isset($config['first_match_billing_prompt']['payment_setup_snooze_hours'])) {
        $config['first_match_billing_prompt']['payment_setup_snooze_hours']
            = $default_config['first_match_billing_prompt']['payment_setup_snooze_hours'];
    }
    if (!isset($config['tuition'])) {
        $config['tuition'] = $default_config['tuition'];
    }
    if (!isset($config['tuition']['ainy_application_fee_percent'])) {
        $config['tuition']['ainy_application_fee_percent'] = $default_config['tuition']['ainy_application_fee_percent'];
    }
    if (!isset($config['tuition']['stripe_connect_fee_percent_display'])) {
        $config['tuition']['stripe_connect_fee_percent_display'] = $default_config['tuition']['stripe_connect_fee_percent_display'];
    }

    if (function_exists('aidunite_payment_migrate_config_legacy')) {
        [$config, $legacy_migrated] = aidunite_payment_migrate_config_legacy($config, $default_config);
        if ($legacy_migrated) {
            update_option('aidunite_payment_config', $config);
        }
    }

    if (!isset($config['plan_display_mode']) || !in_array($config['plan_display_mode'], ['coming_soon', 'trial_card'], true)) {
        $config['plan_display_mode'] = $default_config['plan_display_mode'] ?? 'coming_soon';
    }

    update_option('aidunite_payment_config', $config);

    return $config;
}

/**
 * 決済設定を保存
 */
function aidunite_save_payment_config($config) {
    return update_option('aidunite_payment_config', $config);
}

/**
 * チームの商品プラン（match | club）を取得 — read payload 経由
 */
function aidunite_get_team_product_plan($team_id) {
    if (function_exists('aidunite_payment_read_canonical_team_meta')) {
        $meta = aidunite_payment_read_canonical_team_meta((int) $team_id);

        return (string) ($meta['product_plan'] ?? 'match');
    }

    return 'match';
}

/**
 * チームの商品プランを設定 — persist 経由
 */
function aidunite_set_team_product_plan($team_id, $plan) {
    if (function_exists('aidunite_team_write_product_plan_meta')) {
        return aidunite_team_write_product_plan_meta((int) $team_id, (string) $plan);
    }

    return '';
}

/**
 * 支払いステータスを取得
 */
function aidunite_get_payment_status($user_id) {
    $meta = aidunite_payment_read_canonical_user_meta((int) $user_id);
    $status = (string) ($meta['payment_status'] ?? '');

    return $status !== '' ? $status : null;
}

/**
 * 支払いステータスを設定（team 正本）
 */
function aidunite_set_payment_status($user_id, $status) {
    $user_id = (int) $user_id;
    $team_id = function_exists('aidunite_payment_resolve_user_billing_team_id')
        ? aidunite_payment_resolve_user_billing_team_id($user_id)
        : 0;
    if ($team_id <= 0) {
        error_log("aidunite_set_payment_status: team not resolved (user_id={$user_id})");

        return;
    }
    $status = (string) $status;
    if ($status === '') {
        if (function_exists('aidunite_team_clear_payment_status_meta')) {
            aidunite_team_clear_payment_status_meta($team_id);
        }

        return;
    }
    aidunite_set_team_payment_status($team_id, $status, $user_id);
}

/**
 * Stripe顧客IDを取得
 */
function aidunite_get_stripe_customer_id($user_id) {
    return (string) (aidunite_payment_read_canonical_user_meta((int) $user_id)['stripe_customer_id'] ?? '');
}

/**
 * Stripe顧客IDを設定
 */
function aidunite_set_stripe_customer_id($user_id, $customer_id) {
    aidunite_user_write_stripe_customer_meta((int) $user_id, (string) $customer_id);
}

/**
 * StripeサブスクリプションIDを取得
 */
function aidunite_get_stripe_subscription_id($user_id) {
    return (string) (aidunite_payment_read_canonical_user_meta((int) $user_id)['stripe_subscription_id'] ?? '');
}

/**
 * StripeサブスクリプションIDを設定（team 正本）
 */
function aidunite_set_stripe_subscription_id($user_id, $subscription_id) {
    $user_id = (int) $user_id;
    $team_id = function_exists('aidunite_payment_resolve_user_billing_team_id')
        ? aidunite_payment_resolve_user_billing_team_id($user_id)
        : 0;
    if ($team_id <= 0) {
        error_log("aidunite_set_stripe_subscription_id: team not resolved (user_id={$user_id})");

        return;
    }
    aidunite_set_team_stripe_subscription_id($team_id, (string) $subscription_id, $user_id);
}

/**
 * チーム単位の Stripe サブスクリプション ID（team meta 優先、未設定時は代表者 user meta）
 */
function aidunite_get_team_stripe_subscription_id($team_id, $user_id = 0) {
    if (function_exists('aidunite_payment_exit_read_subscription_state')) {
        $state = aidunite_payment_exit_read_subscription_state((int) $team_id, (int) $user_id);

        return (string) ($state['stripe_subscription_id'] ?? '');
    }

    return '';
}

/**
 * チーム単位の支払いステータス（team meta 優先）
 */
function aidunite_get_team_payment_status($team_id, $user_id = 0) {
    if (function_exists('aidunite_payment_exit_read_subscription_state')) {
        $state = aidunite_payment_exit_read_subscription_state((int) $team_id, (int) $user_id);
        $status = (string) ($state['payment_status'] ?? '');

        return $status !== '' ? $status : null;
    }

    return null;
}

/**
 * チーム単位の支払いステータス設定（team meta のみが正本）
 */
function aidunite_set_team_payment_status($team_id, $status, $user_id = 0) {
    $team_id = (int) $team_id;
    if ($team_id <= 0) {
        return;
    }
    if (function_exists('aidunite_team_write_payment_status_meta')) {
        aidunite_team_write_payment_status_meta($team_id, (string) $status);
    }
}

/**
 * チームへサブスクリプション ID を保存（team meta 正本）
 */
function aidunite_set_team_stripe_subscription_id($team_id, $subscription_id, $user_id = 0) {
    $team_id = (int) $team_id;
    if ($team_id <= 0) {
        return;
    }
    if (function_exists('aidunite_team_write_stripe_subscription_meta')) {
        aidunite_team_write_stripe_subscription_meta($team_id, (string) $subscription_id);
    }
}

/**
 * チームの支払いモードを取得（personal | corporate）
 */
function aidunite_get_team_payment_mode($team_id) {
    if (function_exists('aidunite_payment_read_canonical_team_meta')) {
        $meta = aidunite_payment_read_canonical_team_meta((int) $team_id);

        return (string) ($meta['payment_mode'] ?? '');
    }

    return '';
}

/**
 * チームの支払いモードを設定
 */
function aidunite_set_team_payment_mode($team_id, $mode) {
    if (function_exists('aidunite_team_write_payment_mode_meta')) {
        aidunite_team_write_payment_mode_meta((int) $team_id, (string) $mode);
    }
}

/**
 * チームタイプを設定
 */
function aidunite_set_team_type($team_id, $type) {
    aidunite_update_team_type_meta($team_id, $type);
}

/**
 * 選択プランIDを取得
 */
function aidunite_get_selected_plan_id($team_id) {
    if (function_exists('aidunite_payment_read_canonical_team_meta')) {
        $meta = aidunite_payment_read_canonical_team_meta((int) $team_id);

        return (string) ($meta['selected_plan_id'] ?? '');
    }

    return '';
}

/**
 * 選択プランIDを設定
 */
function aidunite_set_selected_plan_id($team_id, $plan_id) {
    if (function_exists('aidunite_team_write_selected_plan_meta')) {
        aidunite_team_write_selected_plan_meta((int) $team_id, (string) $plan_id);
    }
}

/**
 * 選択プランIDを削除
 */
function aidunite_delete_selected_plan_id($team_id) {
    if (!function_exists('aidunite_team_delete_selected_plan_meta')) {
        require_once get_stylesheet_directory() . '/functions/payment/payment-persist.php';
    }
    aidunite_team_delete_selected_plan_meta((int) $team_id);
}

/**
 * トライアル開始日を取得
 */
function aidunite_get_trial_start_date($team_id) {
    if (function_exists('aidunite_payment_read_canonical_team_meta')) {
        $meta = aidunite_payment_read_canonical_team_meta((int) $team_id);

        return (string) ($meta['trial_start_date'] ?? '');
    }

    return '';
}

/**
 * トライアル開始日を設定
 */
function aidunite_set_trial_start_date($team_id, $date = null) {
    if (function_exists('aidunite_team_write_trial_start_date_meta')) {
        aidunite_team_write_trial_start_date_meta((int) $team_id, $date);
    }
}








