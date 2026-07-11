<?php
/**
 * Stripe基盤機能
 * Stripe SDK初期化・基本機能
 */

// ComposerでStripe PHP SDKをインストールする必要があります
// composer require stripe/stripe-php

// Stripe SDKの読み込み（Composer経由）- 複数のパスを試行
$autoload_paths = [
    get_template_directory() . '/vendor/autoload.php',
    get_stylesheet_directory() . '/vendor/autoload.php',
    dirname(dirname(dirname(__FILE__))) . '/vendor/autoload.php', // テーマディレクトリの親
    ABSPATH . 'vendor/autoload.php', // WordPressルート
];

$autoload_loaded = false;
foreach ($autoload_paths as $autoload_path) {
    if (file_exists($autoload_path)) {
        require_once $autoload_path;
        $autoload_loaded = true;
        break;
    }
}

if (!$autoload_loaded) {
    // 1リクエスト1回だけログ（admin-ajax / wp-cron 等で大量出力されないようにする）
    if (!defined('AIDUNITE_STRIPE_SDK_ERROR_LOGGED')) {
        error_log('Stripe SDKエラー: vendor/autoload.phpが見つかりません。ComposerでStripe SDKをインストールしてください: composer require stripe/stripe-php');
        define('AIDUNITE_STRIPE_SDK_ERROR_LOGGED', true);
    }
}

// useステートメントは条件分岐外で宣言（クラスが存在しない場合でもエラーを避けるため、完全修飾名を使用）

/**
 * Stripe初期化
 */
function aidunite_init_stripe() {
    // Stripeクラスが利用可能かチェック
    if (!class_exists('Stripe\Stripe')) {
        if (!defined('AIDUNITE_STRIPE_SDK_ERROR_LOGGED')) {
            error_log('Stripe SDKエラー: Stripe\Stripeクラスが見つかりません。ComposerでStripe SDKをインストールしてください: composer require stripe/stripe-php');
            define('AIDUNITE_STRIPE_SDK_ERROR_LOGGED', true);
        }
        return false;
    }

    $secret_key = get_option('aidunite_stripe_secret_key');

    if (empty($secret_key)) {
        // 秘密鍵未設定は運用中に想定されるため、ログは出さずに初期化のみスキップ
        return false;
    }

    try {
        // 完全修飾名を使用してクラスを呼び出し
        \Stripe\Stripe::setApiKey($secret_key);
        return true;
    } catch (Exception $e) {
        error_log('Stripe初期化エラー: ' . $e->getMessage());
        return false;
    }
}

/**
 * Stripe APIキー設定を取得
 */
function aidunite_get_stripe_keys() {
    return [
        'publishable_key' => get_option('aidunite_stripe_publishable_key', ''),
        'secret_key' => get_option('aidunite_stripe_secret_key', ''),
        'webhook_secret' => get_option('aidunite_stripe_webhook_secret', ''),
    ];
}

/**
 * 保存済み Stripe Customer ID を削除（別アカウントの残骸など）
 *
 * @param int $user_id
 */
function aidunite_clear_stripe_customer_id($user_id) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return;
    }

    if (function_exists('aidunite_user_delete_stripe_customer_meta')) {
        aidunite_user_delete_stripe_customer_meta($user_id);

        return;
    }
}

/**
 * Stripe API が「Customer が存在しない」と返したか
 *
 * @param \Throwable $exception
 */
function aidunite_stripe_is_missing_customer_error($exception) {
    if (!$exception instanceof \Stripe\Exception\ApiErrorException) {
        return stripos((string) $exception->getMessage(), 'No such customer') !== false;
    }

    $code = (string) $exception->getStripeCode();

    return $code === 'resource_missing'
        || stripos((string) $exception->getMessage(), 'No such customer') !== false;
}

/**
 * プラットフォーム Stripe Customer を取得または新規作成
 *
 * @param int $user_id
 * @param int $team_id
 * @return string|WP_Error
 */
function aidunite_ensure_stripe_platform_customer($user_id, $team_id = 0) {
    $user_id = (int) $user_id;
    $team_id = (int) $team_id;

    if ($user_id <= 0) {
        return new WP_Error('invalid_user', 'ユーザー情報が見つかりません');
    }

    if (!class_exists('\Stripe\Stripe') || !aidunite_init_stripe()) {
        return new WP_Error('stripe_init_failed', 'Stripeの初期化に失敗しました');
    }

    $user = get_userdata($user_id);
    if (!$user) {
        return new WP_Error('user_not_found', 'ユーザー情報が見つかりません');
    }

    $customer_id = function_exists('aidunite_get_stripe_customer_id')
        ? trim((string) aidunite_get_stripe_customer_id($user_id))
        : '';

    if ($customer_id !== '') {
        try {
            \Stripe\Customer::retrieve($customer_id);

            return $customer_id;
        } catch (\Stripe\Exception\ApiErrorException $e) {
            if (!aidunite_stripe_is_missing_customer_error($e)) {
                error_log('Stripe Customer 取得エラー: ' . $e->getMessage());
                return new WP_Error('customer_retrieve_failed', '決済情報の登録に失敗しました。しばらく時間をおいて再度お試しください。');
            }

            aidunite_clear_stripe_customer_id($user_id);
            $customer_id = '';
        }
    }

    try {
        $metadata = [
            'user_id' => $user_id,
        ];
        if ($team_id > 0) {
            $metadata['team_id'] = $team_id;
        }

        $customer = \Stripe\Customer::create([
            'email' => (string) $user->user_email,
            'name' => (string) $user->display_name,
            'metadata' => $metadata,
        ]);

        $customer_id = (string) ($customer->id ?? '');
        if ($customer_id === '') {
            return new WP_Error('customer_creation_failed', '決済情報の登録に失敗しました。');
        }

        if (function_exists('aidunite_set_stripe_customer_id')) {
            aidunite_set_stripe_customer_id($user_id, $customer_id);
        }

        return $customer_id;
    } catch (\Stripe\Exception\ApiErrorException $e) {
        error_log('Stripe顧客作成エラー: ' . $e->getMessage());

        $secret_key = (string) get_option('aidunite_stripe_secret_key', '');
        if (strpos($secret_key, 'whsec_') === 0) {
            return new WP_Error(
                'stripe_secret_key_is_webhook',
                '決済設定に誤りがあります（Secret Key に Webhook Secret が入っています）。管理者に Stripe API キーの再設定を依頼してください。'
            );
        }

        if (stripos($e->getMessage(), 'Invalid API Key') !== false) {
            return new WP_Error(
                'stripe_api_key_invalid',
                'Stripe API キーが無効です。管理者に決済管理画面でのキー設定を確認してください。'
            );
        }

        return new WP_Error('customer_creation_failed', '決済情報の登録に失敗しました。しばらく時間をおいて再度お試しください。問題が続く場合は、お問い合わせください。');
    }
}

/**
 * Secret Key の形式を検証（保存前・Checkout 前）
 *
 * @param string $secret_key
 * @return true|WP_Error
 */
function aidunite_validate_stripe_secret_key($secret_key) {
    $secret_key = trim((string) $secret_key);

    if ($secret_key === '') {
        return new WP_Error(
            'stripe_secret_key_missing',
            'Stripe Secret Key が未設定です。管理者画面の決済管理で sk_test_ または sk_live_ で始まるキーを設定してください。'
        );
    }

    if (strpos($secret_key, 'whsec_') === 0) {
        return new WP_Error(
            'stripe_secret_key_is_webhook',
            'Secret Key に Webhook Secret（whsec_…）が入力されています。Secret Key には sk_test_ または sk_live_ のキーを、Webhook Secret 欄には whsec_… をそれぞれ設定してください。'
        );
    }

    if (strpos($secret_key, 'pk_') === 0) {
        return new WP_Error(
            'stripe_secret_key_is_publishable',
            'Secret Key に Publishable Key（pk_…）が入力されています。sk_test_ または sk_live_ のキーを設定してください。'
        );
    }

    if (strpos($secret_key, 'sk_') !== 0 && strpos($secret_key, 'rk_') !== 0) {
        return new WP_Error(
            'stripe_secret_key_invalid_format',
            'Stripe Secret Key の形式が正しくありません。sk_test_ または sk_live_ で始まるキーを設定してください。'
        );
    }

    return true;
}

/**
 * Publishable / Webhook Secret の形式を検証
 *
 * @param string $publishable_key
 * @param string $webhook_secret
 * @return true|WP_Error
 */
function aidunite_validate_stripe_auxiliary_keys($publishable_key, $webhook_secret) {
    $publishable_key = trim((string) $publishable_key);
    $webhook_secret = trim((string) $webhook_secret);

    if ($publishable_key !== '' && strpos($publishable_key, 'pk_') !== 0) {
        return new WP_Error(
            'stripe_publishable_key_invalid',
            'Publishable Key は pk_test_ または pk_live_ で始まるキーを設定してください。'
        );
    }

    if ($webhook_secret !== '' && strpos($webhook_secret, 'whsec_') !== 0) {
        return new WP_Error(
            'stripe_webhook_secret_invalid',
            'Webhook Secret は whsec_ で始まる値を設定してください。'
        );
    }

    return true;
}

/**
 * Stripe APIキー設定を保存
 *
 * @return true|WP_Error
 */
function aidunite_save_stripe_keys($publishable_key, $secret_key, $webhook_secret = '') {
    $publishable_key = sanitize_text_field((string) $publishable_key);
    $secret_key = sanitize_text_field((string) $secret_key);
    $webhook_secret = sanitize_text_field((string) $webhook_secret);

    $secret_check = aidunite_validate_stripe_secret_key($secret_key);
    if (is_wp_error($secret_check)) {
        return $secret_check;
    }

    $aux_check = aidunite_validate_stripe_auxiliary_keys($publishable_key, $webhook_secret);
    if (is_wp_error($aux_check)) {
        return $aux_check;
    }

    update_option('aidunite_stripe_publishable_key', $publishable_key);
    update_option('aidunite_stripe_secret_key', $secret_key);

    if ($webhook_secret !== '') {
        update_option('aidunite_stripe_webhook_secret', $webhook_secret);
    }

    aidunite_init_stripe();

    return true;
}

// 初期化実行
add_action('init', 'aidunite_init_stripe', 1);
