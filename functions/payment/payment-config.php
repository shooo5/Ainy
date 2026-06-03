<?php
/**
 * 決済設定・データ構造管理
 * 金額設定、プラン設定、支払いステータス管理
 */

/**
 * デフォルトの決済設定を取得
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
    return [
        'plan_display_mode' => 'coming_soon', // リリース前: カミングスーン / お試しカード
        'school' => [
            'board_amount' => 2000,      // 教育委員会契約用
            'school_amount' => 2000,     // 学校契約用
            'personal_amount' => 2000,    // 個人契約用
            'plans' => [
                [
                    'id' => 'plan_standard',
                    'name' => 'スタンダードプラン',
                    'trial_type' => 'days',
                    'trial_value' => 30,
                    'description' => 'トライアル30日',
                    'is_default' => true,
                ],
                [
                    'id' => 'plan_premium',
                    'name' => 'プレミアムプラン',
                    'trial_type' => 'free_months',
                    'trial_value' => 2,
                    'description' => '入会で2か月分無料',
                    'is_default' => false,
                ],
            ],
        ],
        'club' => [
            'base_amount' => 300,         // 1人あたり
            'plans' => [
                [
                    'id' => 'plan_standard',
                    'name' => 'スタンダードプラン',
                    'trial_type' => 'days',
                    'trial_value' => 30,
                    'description' => 'トライアル30日',
                    'is_default' => true,
                ],
            ],
        ],
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

    // 既存設定の互換性チェック（旧形式から新形式への移行）
    $default_config = aidunite_get_default_payment_config();

    // 学校設定の互換性チェック
    if (isset($config['school']) && !isset($config['school']['personal_amount'])) {
        // 旧形式（amountのみ）から新形式（personal_amount, school_amount, board_amount）へ移行
        if (isset($config['school']['amount'])) {
            $amount = $config['school']['amount'];
            $config['school']['personal_amount'] = $amount;
            $config['school']['school_amount'] = $amount;
            $config['school']['board_amount'] = $amount;
        } else {
            // デフォルト値を設定
            $config['school']['personal_amount'] = $default_config['school']['personal_amount'];
            $config['school']['school_amount'] = $default_config['school']['school_amount'];
            $config['school']['board_amount'] = $default_config['school']['board_amount'];
        }
    }

    // プラン設定の互換性チェック
    if (isset($config['school']) && !isset($config['school']['plans'])) {
        $config['school']['plans'] = $default_config['school']['plans'];
    }
    if (isset($config['club']) && !isset($config['club']['plans'])) {
        $config['club']['plans'] = $default_config['club']['plans'];
    }

    // プラン表示モードの互換性（未設定時はカミングスーン）
    if (!isset($config['plan_display_mode']) || !in_array($config['plan_display_mode'], ['coming_soon', 'trial_card'], true)) {
        $config['plan_display_mode'] = $default_config['plan_display_mode'] ?? 'coming_soon';
    }

    // 更新された設定を保存
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
 * 支払いステータスを取得
 */
function aidunite_get_payment_status($user_id) {
    $status = get_user_meta($user_id, 'payment_status', true);

    if (empty($status)) {
        return null; // 未設定
    }

    return $status; // 'trial' | 'paid' | 'unpaid'
}

/**
 * 支払いステータスを設定
 */
function aidunite_set_payment_status($user_id, $status) {
    update_user_meta($user_id, 'payment_status', $status);
    update_user_meta($user_id, 'payment_status_updated', current_time('mysql'));
}

/**
 * Stripe顧客IDを取得
 */
function aidunite_get_stripe_customer_id($user_id) {
    return get_user_meta($user_id, 'stripe_customer_id', true);
}

/**
 * Stripe顧客IDを設定
 */
function aidunite_set_stripe_customer_id($user_id, $customer_id) {
    update_user_meta($user_id, 'stripe_customer_id', $customer_id);
}

/**
 * StripeサブスクリプションIDを取得
 */
function aidunite_get_stripe_subscription_id($user_id) {
    return get_user_meta($user_id, 'stripe_subscription_id', true);
}

/**
 * StripeサブスクリプションIDを設定
 */
function aidunite_set_stripe_subscription_id($user_id, $subscription_id) {
    update_user_meta($user_id, 'stripe_subscription_id', $subscription_id);
}

/**
 * チームの支払いモードを取得
 */
function aidunite_get_team_payment_mode($team_id) {
    return get_post_meta($team_id, 'payment_mode', true);
}

/**
 * チームの支払いモードを設定
 */
function aidunite_set_team_payment_mode($team_id, $mode) {
    if (function_exists('aidunite_update_team_payment_mode_meta')) {
        $saved = aidunite_update_team_payment_mode_meta($team_id, $mode);
        if ($saved !== '') {
            return;
        }
    }
    update_post_meta($team_id, 'payment_mode', $mode);
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
    return get_post_meta($team_id, 'selected_plan_id', true);
}

/**
 * 選択プランIDを設定
 */
function aidunite_set_selected_plan_id($team_id, $plan_id) {
    update_post_meta($team_id, 'selected_plan_id', $plan_id);
}

/**
 * トライアル開始日を取得
 */
function aidunite_get_trial_start_date($team_id) {
    return get_post_meta($team_id, 'trial_start_date', true);
}

/**
 * トライアル開始日を設定
 */
function aidunite_set_trial_start_date($team_id, $date = null) {
    if ($date === null) {
        $date = current_time('mysql');
    }
    update_post_meta($team_id, 'trial_start_date', $date);
}

/**
 * 教育委員会専用コードを取得
 */
function aidunite_get_board_registration_code($team_id) {
    return get_post_meta($team_id, 'board_registration_code', true);
}

/**
 * 教育委員会専用コードを設定
 */
function aidunite_set_board_registration_code($team_id, $code) {
    update_post_meta($team_id, 'board_registration_code', $code);
}

/**
 * 専用コードが使用済みかチェック
 */
function aidunite_is_registration_code_used($code) {
    global $wpdb;

    $result = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->postmeta}
         WHERE meta_key = 'board_registration_code'
         AND meta_value = %s",
        $code
    ));

    return $result > 0;
}

/**
 * 教育委員会契約IDを取得
 */
function aidunite_get_board_contract_id($team_id) {
    return get_post_meta($team_id, 'board_contract_id', true);
}

/**
 * 教育委員会契約IDを設定
 */
function aidunite_set_board_contract_id($team_id, $contract_id) {
    update_post_meta($team_id, 'board_contract_id', $contract_id);
}
