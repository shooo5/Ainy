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
 * Stripe Connect API エラーを利用者向けメッセージへ変換
 *
 * @param \Stripe\Exception\ApiErrorException $e
 * @param string                              $fallback
 * @return string
 */
function aidunite_stripe_connect_user_facing_error_from_exception($e, $fallback) {
    $message = trim((string) $e->getMessage());
    $lower = strtolower($message);

    if (
        strpos($lower, 'signed up for connect') !== false
        || (strpos($lower, 'connect') !== false && strpos($lower, 'not enabled') !== false)
        || strpos($lower, 'platform profile') !== false
        || strpos($lower, 'review the responsibilities') !== false
    ) {
        return 'Stripe Connect がプラットフォーム側で未有効、または設定が未完了です。Stripeダッシュボード（テストモード）で Connect を有効化し、プラットフォームプロフィールを完了してください。';
    }

    if (strpos($lower, 'invalid api key') !== false || strpos($lower, 'no api key') !== false) {
        return 'Stripe Secret Key が無効です。管理者の決済設定で sk_test_ / sk_live_ のキーを確認してください。';
    }

    if (strpos($lower, 'permission') !== false && strpos($lower, 'connect') !== false) {
        return 'この Stripe キーでは Connect を利用できません。Connect 対応のプラットフォームアカウントの Secret Key を設定してください。';
    }

    if (defined('WP_DEBUG') && WP_DEBUG && $message !== '') {
        return $fallback . '（詳細: ' . $message . '）';
    }

    return $fallback;
}

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
    $existing = function_exists('aidunite_payment_read_stripe_connect_account_id')
        ? aidunite_payment_read_stripe_connect_account_id($team_id)
        : '';
    if (!empty($existing)) {
        return $existing;
    }

    if (!class_exists('\Stripe\Stripe')) {
        AidUniteErrorHandler::error('[TUITION][CONNECT] Stripe SDKが見つかりません', ['team_id' => $team_id]);
        return new WP_Error('stripe_sdk_not_found', 'Stripe SDKがインストールされていません。ComposerでStripe SDKをインストールしてください: composer require stripe/stripe-php');
    }

    if (!aidunite_init_stripe()) {
        AidUniteErrorHandler::error('[TUITION][CONNECT] Stripe初期化に失敗しました', ['team_id' => $team_id]);
        $keys = function_exists('aidunite_get_stripe_keys') ? aidunite_get_stripe_keys() : [];
        $secret_check = function_exists('aidunite_validate_stripe_secret_key')
            ? aidunite_validate_stripe_secret_key((string) ($keys['secret_key'] ?? ''))
            : true;
        if (is_wp_error($secret_check)) {
            return $secret_check;
        }

        return new WP_Error(
            'stripe_init_failed',
            'Stripeの初期化に失敗しました。管理者の決済設定で Secret Key が保存されているか確認してください。'
        );
    }

    try {
        $team = get_post($team_id);
        $team_name = $team ? $team->post_title : 'Team ' . $team_id;

        // 日本向けExpressアカウントを作成
        $account = \Stripe\Account::create([
            'type'    => 'express',
            'country' => 'JP',
            'capabilities' => [
                'card_payments' => ['requested' => true],
                'transfers' => ['requested' => true],
            ],
            'business_profile' => [
                'name' => $team_name,
            ],
            'metadata' => [
                'team_id' => $team_id,
                'source'  => 'aidunite_tuition',
            ],
        ]);

        $acct_id = $account->id;
        if (function_exists('aidunite_team_write_stripe_connect_account_id_meta')) {
            aidunite_team_write_stripe_connect_account_id_meta($team_id, $acct_id);
        }

        AidUniteErrorHandler::info('[TUITION][CONNECT] Connectアカウント作成成功', ['team_id' => $team_id, 'account' => $acct_id]);

        return $acct_id;
    } catch (\Stripe\Exception\ApiErrorException $e) {
        AidUniteErrorHandler::error('[TUITION][CONNECT] Connectアカウント作成エラー', [
            'message' => $e->getMessage(),
            'team_id' => $team_id,
            'exception' => get_class($e)
        ]);
        // 汎用的なエラーメッセージを返す（技術的詳細はログに記録済み）
        return new WP_Error(
            'connect_account_create_failed',
            aidunite_stripe_connect_user_facing_error_from_exception(
                $e,
                '決済設定の初期化に失敗しました。しばらく時間をおいて再度お試しください。問題が続く場合は、お問い合わせください。'
            )
        );
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
        return new WP_Error(
            'connect_onboarding_failed',
            aidunite_stripe_connect_user_facing_error_from_exception(
                $e,
                '決済設定ページの準備に失敗しました。しばらく時間をおいて再度お試しください。問題が続く場合は、お問い合わせください。'
            )
        );
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

    // チームIDはPOST優先、なければ操作中チームを解決
    $team_id = AidUniteAuthMiddleware::sanitize($_POST['team_id'] ?? 0, 'int');
    if ($team_id <= 0 && function_exists('aidunite_team_settings_resolve_context')) {
        $ctx = aidunite_team_settings_resolve_context((int) $user_id);
        if (is_array($ctx) && !empty($ctx['team_id'])) {
            $team_id = (int) $ctx['team_id'];
        }
    }
    if ($team_id <= 0 && function_exists('aidunite_user_read_primary_team_id')) {
        $team_id = (int) aidunite_user_read_primary_team_id((int) $user_id);
    }

    if ($team_id <= 0) {
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
