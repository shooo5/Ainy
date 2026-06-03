<?php
/**
 * Stripe Connect関連機能
 * - チーム用Connectアカウントの作成
 * - オンボーディングURLの発行
 */

require_once get_template_directory() . '/functions/payment/stripe-core.php';
require_once get_template_directory() . '/functions/common/error-handler.php';
require_once get_template_directory() . '/functions/common/auth-middleware.php';

/**
 * チーム用Stripe ConnectアカウントIDを取得（なければ作成）
 *
 * @param int $team_id
 * @return string|WP_Error acct_xxx もしくは WP_Error
 */
function aidunite_get_or_create_connect_account($team_id) {
    $team_id = (int) $team_id;
    if (!$team_id) {
        return new WP_Error('invalid_team_id', '[TUITION][CONNECT] team_id が不正です');
    }

    // 既存IDがあればそれを返す
    $existing = get_post_meta($team_id, 'stripe_connect_account_id', true);
    if (!empty($existing)) {
        return $existing;
    }

    if (!class_exists('\Stripe\Stripe')) {
        AidUniteErrorHandler::error('[TUITION][CONNECT] Stripe SDKが見つかりません', ['team_id' => $team_id]);
        return new WP_Error('stripe_sdk_not_found', 'Stripe SDKがインストールされていません。ComposerでStripe SDKをインストールしてください: composer require stripe/stripe-php');
    }

    if (!aidunite_init_stripe()) {
        AidUniteErrorHandler::error('[TUITION][CONNECT] Stripe初期化に失敗しました', ['team_id' => $team_id]);
        return new WP_Error('stripe_init_failed', 'Stripeの初期化に失敗しました');
    }

    try {
        $team = get_post($team_id);
        $team_name = $team ? $team->post_title : 'Team ' . $team_id;

        // 日本向けExpressアカウントを作成
        $account = \Stripe\Account::create([
            'type'    => 'express',
            'country' => 'JP',
            'business_profile' => [
                'name' => $team_name,
            ],
            'metadata' => [
                'team_id' => $team_id,
                'source'  => 'aidunite_tuition',
            ],
        ]);

        $acct_id = $account->id;
        update_post_meta($team_id, 'stripe_connect_account_id', $acct_id);

        AidUniteErrorHandler::info('[TUITION][CONNECT] Connectアカウント作成成功', ['team_id' => $team_id, 'account' => $acct_id]);

        return $acct_id;
    } catch (\Stripe\Exception\ApiErrorException $e) {
        AidUniteErrorHandler::error('[TUITION][CONNECT] Connectアカウント作成エラー', [
            'message' => $e->getMessage(),
            'team_id' => $team_id,
            'exception' => get_class($e)
        ]);
        // 汎用的なエラーメッセージを返す（技術的詳細はログに記録済み）
        return new WP_Error('connect_account_create_failed', '決済設定の初期化に失敗しました。しばらく時間をおいて再度お試しください。問題が続く場合は、お問い合わせください。');
    }
}

/**
 * チーム用のStripe ConnectオンボーディングURLを取得
 *
 * @param int $team_id
 * @return string|WP_Error
 */
function aidunite_get_connect_onboarding_link($team_id) {
    $team_id = (int) $team_id;

    $acct_id = aidunite_get_or_create_connect_account($team_id);
    if (is_wp_error($acct_id)) {
        return $acct_id;
    }

    if (!class_exists('\Stripe\Stripe')) {
        AidUniteErrorHandler::error('[TUITION][CONNECT] Stripe SDKが見つかりません', ['team_id' => $team_id]);
        return new WP_Error('stripe_sdk_not_found', 'Stripe SDKがインストールされていません。ComposerでStripe SDKをインストールしてください: composer require stripe/stripe-php');
    }

    if (!aidunite_init_stripe()) {
        AidUniteErrorHandler::error('[TUITION][CONNECT] Stripe初期化に失敗しました', ['team_id' => $team_id]);
        return new WP_Error('stripe_init_failed', 'Stripeの初期化に失敗しました');
    }

    try {
        $refresh_url = home_url('/team-payment-management?onboarding=refresh');
        $return_url  = home_url('/team-payment-management?onboarding=return');

        $account_link = \Stripe\AccountLink::create([
            'account'     => $acct_id,
            'refresh_url' => $refresh_url,
            'return_url'  => $return_url,
            'type'        => 'account_onboarding',
        ]);

        AidUniteErrorHandler::info('[TUITION][CONNECT] オンボーディングURL発行', ['team_id' => $team_id, 'account' => $acct_id]);

        return $account_link->url;
    } catch (\Stripe\Exception\ApiErrorException $e) {
        AidUniteErrorHandler::error('[TUITION][CONNECT] オンボーディングURL発行エラー', [
            'message' => $e->getMessage(),
            'team_id' => $team_id,
            'exception' => get_class($e)
        ]);
        // 汎用的なエラーメッセージを返す（技術的詳細はログに記録済み）
        return new WP_Error('connect_onboarding_failed', '決済設定ページの準備に失敗しました。しばらく時間をおいて再度お試しください。問題が続く場合は、お問い合わせください。');
    }
}

/**
 * Ajax: Stripe ConnectオンボーディングURLを発行して返す
 */
add_action('wp_ajax_aidunite_create_connect_onboarding_link', 'aidunite_ajax_create_connect_onboarding_link');
function aidunite_ajax_create_connect_onboarding_link() {
    // 統一認証・権限チェック
    $auth_result = AidUniteAuthMiddleware::require_auth();
    if (!$auth_result->is_valid()) {
        // 認証エラーはcritical
        AidUniteApiResponse::send_critical_error(
            $auth_result->error ?: 'ログインが必要です',
            'authentication_required'
        );
        return;
    }

    $user_id = $auth_result->user_id;
    $user_team_id = $auth_result->team_id;

    // チームIDはPOST優先、なければユーザーのteam_idを使用
    $team_id = AidUniteAuthMiddleware::sanitize($_POST['team_id'] ?? 0, 'int');
    if (!$team_id) {
        $team_id = $user_team_id;
    }

    if (!$team_id) {
        // チームが見つからないエラーはnormal
        AidUniteApiResponse::send_error(
            'チームが見つかりません',
            null,
            'normal',
            'team_not_found'
        );
        return;
    }

    // 統一認証・権限チェック（チーム代表者のみ許可）
    $auth_result = AidUniteAuthMiddleware::require_payment_permission($team_id, true, false);
    if (!$auth_result->is_valid()) {
        // 権限エラーはcritical
        AidUniteApiResponse::send_critical_error(
            $auth_result->error ?: 'このチームの決済設定を変更する権限がありません',
            'permission_denied'
        );
        return;
    }

    $link = aidunite_get_connect_onboarding_link($team_id);

    if (is_wp_error($link)) {
        // 支払い関連のエラーはcriticalとして扱う
        AidUniteApiResponse::send_critical_error(
            $link->get_error_message(),
            'connect_onboarding_failed'
        );
        return;
    }

    wp_send_json_success(['url' => $link]);
}
