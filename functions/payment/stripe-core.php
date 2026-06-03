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
 * Stripe APIキー設定を保存
 */
function aidunite_save_stripe_keys($publishable_key, $secret_key, $webhook_secret = '') {
    update_option('aidunite_stripe_publishable_key', sanitize_text_field($publishable_key));
    update_option('aidunite_stripe_secret_key', sanitize_text_field($secret_key));

    if (!empty($webhook_secret)) {
        update_option('aidunite_stripe_webhook_secret', sanitize_text_field($webhook_secret));
    }

    // Stripeを再初期化
    aidunite_init_stripe();

    return true;
}

// 初期化実行
add_action('init', 'aidunite_init_stripe', 1);
