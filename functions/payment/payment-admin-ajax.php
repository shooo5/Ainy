<?php
/**
 * 決済管理画面用 AJAX（金額設定・Stripe）
 * システム管理ページ廃止後は Ainy ダッシュボードから利用
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_ajax_aidunite_save_payment_config', 'aidunite_ajax_save_payment_config');
function aidunite_ajax_save_payment_config() {
    require_once get_template_directory() . '/functions/common/auth-middleware.php';
    require_once get_template_directory() . '/functions/common/error-handler.php';
    $auth_result = AidUniteAuthMiddleware::require_admin(false);
    if (!$auth_result->is_valid()) {
        AidUniteApiResponse::send_error(
            
            $auth_result->error ?: '権限がありません',
            null,
            'normal',
            'admin_required'
        );
        return;
    }
    $nonce_result = AidUniteAuthMiddleware::verify_nonce('nonce', 'aidunite_payment_admin');
    if (is_wp_error($nonce_result)) {
        AidUniteApiResponse::send_error(
            $nonce_result->get_error_message(),
            null,
            'normal',
            'csrf_verification_failed'
        );
        return;
    }
    $config = aidunite_get_payment_config();
    $config['match']['monthly_amount'] = (int) ($_POST['match_monthly_amount'] ?? 0);
    $config['club']['per_player_amount'] = (int) ($_POST['club_per_player_amount'] ?? 0);
    $config['club']['minimum_addon'] = (int) ($_POST['club_minimum_addon'] ?? 0);
    $config['club']['base_amount'] = $config['club']['per_player_amount'];
    if (isset($config['founding_team'])) {
        $config['founding_team']['max_slots'] = (int) ($_POST['founding_max_slots'] ?? $config['founding_team']['max_slots']);
        $config['founding_team']['first_year_discount_percent'] = (int) ($_POST['founding_discount_percent'] ?? $config['founding_team']['first_year_discount_percent']);
    }
    if (isset($config['multi_team_discount'])) {
        $config['multi_team_discount']['match_second_team_percent'] = (int) ($_POST['multi_team_discount_percent'] ?? 20);
    }
    aidunite_save_payment_config($config);
    wp_send_json_success(['message' => '設定を保存しました']);
}

add_action('wp_ajax_aidunite_save_plan_display_mode', 'aidunite_ajax_save_plan_display_mode');
function aidunite_ajax_save_plan_display_mode() {
    require_once get_template_directory() . '/functions/common/auth-middleware.php';
    require_once get_template_directory() . '/functions/common/error-handler.php';
    $auth_result = AidUniteAuthMiddleware::require_admin(false);
    if (!$auth_result->is_valid()) {
        AidUniteApiResponse::send_error(
            $auth_result->error ?: '権限がありません',
            null,
            'normal',
            'admin_required'
        );
        return;

    }
    $nonce_result = AidUniteAuthMiddleware::verify_nonce('nonce', 'aidunite_payment_admin');
    if (is_wp_error($nonce_result)) {
        AidUniteApiResponse::send_error(
            $nonce_result->get_error_message(),
            null,
            'normal',
            'csrf_verification_failed'
        );
        return;
    }
    $mode = sanitize_text_field($_POST['plan_display_mode'] ?? '');
    if (!in_array($mode, ['coming_soon', 'trial_card'], true)) {
        $mode = 'coming_soon';
    }
    $config = aidunite_get_payment_config();
    $config['plan_display_mode'] = $mode;
    aidunite_save_payment_config($config);
    wp_send_json_success(['message' => 'プラン表示モードを保存しました', 'plan_display_mode' => $mode]);
}

add_action('wp_ajax_aidunite_save_stripe_keys', 'aidunite_ajax_save_stripe_keys');
function aidunite_ajax_save_stripe_keys() {
    require_once get_template_directory() . '/functions/common/auth-middleware.php';
    require_once get_template_directory() . '/functions/common/error-handler.php';
    $auth_result = AidUniteAuthMiddleware::require_admin(false);
    if (!$auth_result->is_valid()) {
        AidUniteApiResponse::send_error(
            $auth_result->error ?: '権限がありません',
            null,
            'normal',
            'admin_required'
        );
        return;
    }
    $nonce_result = AidUniteAuthMiddleware::verify_nonce('nonce', 'aidunite_payment_admin');
    if (is_wp_error($nonce_result)) {
        AidUniteApiResponse::send_error(
            $nonce_result->get_error_message(),
            null,
            'normal',
            'csrf_verification_failed'
        );
        return;
    }
    require_once get_template_directory() . '/functions/payment/stripe-core.php';
    $saved = aidunite_save_stripe_keys(
        sanitize_text_field($_POST['publishable_key'] ?? ''),
        sanitize_text_field($_POST['secret_key'] ?? ''),
        sanitize_text_field($_POST['webhook_secret'] ?? '')
    );
    if (is_wp_error($saved)) {
        AidUniteApiResponse::send_error(
            $saved->get_error_message(),
            null,
            'normal',
            $saved->get_error_code()
        );
        return;
    }
    wp_send_json_success(['message' => 'Stripe設定を保存しました']);
}

