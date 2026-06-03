<?php
/**
 * Webプッシュ通知 REST API
 * Service Workerと連携するためのバックエンドAPI
 */

// VAPIDキー生成・管理
class AidUniteWebPushAPI {

    const VAPID_KEYS_OPTION = 'aidunite_vapid_keys';
    const SUBSCRIPTIONS_TABLE = 'aidunite_push_subscriptions';

    /**
     * VAPIDキーを生成または取得
     */
    public static function get_or_generate_vapid_keys() {
        $keys = get_option(self::VAPID_KEYS_OPTION);

        if (!$keys || !isset($keys['public_key']) || !isset($keys['private_key'])) {
            $keys = self::generate_vapid_keys();
            update_option(self::VAPID_KEYS_OPTION, $keys);
        }

        return $keys;
    }

    /**
     * VAPIDキーを生成
     */
    private static function generate_vapid_keys() {
        // 実際の実装では、適切なVAPIDキー生成ライブラリを使用
        // 例: web-push-phpライブラリなど

        // ここでは仮の実装
        return [
            'public_key' => base64_encode(random_bytes(65)),
            'private_key' => base64_encode(random_bytes(32)),
            'subject' => 'mailto:' . get_option('admin_email')
        ];
    }

    /**
     * プッシュ購読テーブル作成
     */
    public static function create_subscriptions_table() {
        global $wpdb;

        $table_name = $wpdb->prefix . self::SUBSCRIPTIONS_TABLE;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            endpoint text NOT NULL,
            p256dh_key text NOT NULL,
            auth_key text NOT NULL,
            user_agent text,
            ip_address varchar(45),
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY endpoint (endpoint(255)),
            KEY is_active (is_active)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * プッシュ通知送信
     */
    public static function send_push_notification($user_id, $title, $message, $options = []) {
        global $wpdb;

        $table_name = $wpdb->prefix . self::SUBSCRIPTIONS_TABLE;

        // ユーザーの購読情報を取得
        $subscriptions = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM {$table_name}
            WHERE user_id = %d AND is_active = 1
        ", $user_id));

        if (empty($subscriptions)) {
            error_log("ℹ️ プッシュ購読が見つかりません: user_id={$user_id}");
            return false;
        }

        $vapid_keys = self::get_or_generate_vapid_keys();
        $success_count = 0;

        foreach ($subscriptions as $subscription) {
            $result = self::send_to_endpoint(
                $subscription,
                $title,
                $message,
                $options,
                $vapid_keys
            );

            if ($result) {
                $success_count++;
            } else {
                // 失敗した購読は無効化
                $wpdb->update(
                    $table_name,
                    ['is_active' => 0, 'updated_at' => current_time('mysql')],
                    ['id' => $subscription->id]
                );
            }
        }

        return $success_count > 0;
    }

    /**
     * 特定のエンドポイントにプッシュ通知送信
     */
    private static function send_to_endpoint($subscription, $title, $message, $options, $vapid_keys) {
        // プッシュ通知のペイロード
        $payload = json_encode([
            'title' => $title,
            'body' => $message,
            'icon' => $options['icon'] ?? '/assets/images/notification-icon.png',
            'badge' => $options['badge'] ?? '/assets/images/notification-badge.png',
            'image' => $options['image'] ?? null,
            'url' => $options['url'] ?? '/page-notifications.php',
            'notificationId' => $options['notification_id'] ?? null,
            'priority' => $options['priority'] ?? 'medium',
            'tag' => $options['tag'] ?? 'aidunite-notification',
            'data' => $options['data'] ?? []
        ]);

        // 実際のプッシュ送信（web-push-phpライブラリを使用する想定）
        try {
            // ここでは仮の実装
            // 実際にはweb-push-phpライブラリを使用してVAPID認証付きで送信

            $headers = [
                'Content-Type: application/json',
                'TTL: 86400', // 24時間
                'Urgency: ' . ($options['priority'] === 'high' ? 'high' : 'normal')
            ];

            // VAPID認証ヘッダーを追加（実際の実装では適切なJWT生成が必要）
            $headers[] = 'Authorization: vapid t=' . self::generate_vapid_jwt($vapid_keys, $subscription->endpoint);

            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => implode("\r\n", $headers),
                    'content' => $payload
                ]
            ]);

            $result = file_get_contents($subscription->endpoint, false, $context);

            return $result !== false;

        } catch (Exception $e) {
            error_log("❌ プッシュ通知送信エラー: " . $e->getMessage());
            return false;
        }
    }

    /**
     * VAPID JWT生成（簡易版）
     */
    private static function generate_vapid_jwt($vapid_keys, $endpoint) {
        // 実際の実装では適切なJWT生成ライブラリを使用
        // 例: firebase/php-jwt など

        $header = json_encode(['typ' => 'JWT', 'alg' => 'ES256']);
        $payload = json_encode([
            'aud' => parse_url($endpoint, PHP_URL_SCHEME) . '://' . parse_url($endpoint, PHP_URL_HOST),
            'exp' => time() + 3600,
            'sub' => $vapid_keys['subject']
        ]);

        // 実際にはES256署名が必要
        return base64_encode($header) . '.' . base64_encode($payload) . '.signature';
    }
}

// REST API エンドポイントの登録
add_action('rest_api_init', function() {

    // VAPIDキー取得
    register_rest_route('aidunite/v1', '/push/vapid-key', [
        'methods' => 'GET',
        'callback' => 'aidunite_get_vapid_public_key',
        'permission_callback' => function() {
            return is_user_logged_in();
        }
    ]);

    // プッシュ購読登録
    register_rest_route('aidunite/v1', '/push/subscribe', [
        'methods' => 'POST',
        'callback' => 'aidunite_register_push_subscription',
        'permission_callback' => function() {
            return is_user_logged_in();
        }
    ]);

    // プッシュ購読解除
    register_rest_route('aidunite/v1', '/push/unsubscribe', [
        'methods' => 'POST',
        'callback' => 'aidunite_unregister_push_subscription',
        'permission_callback' => function() {
            return is_user_logged_in();
        }
    ]);

    // テスト通知送信
    register_rest_route('aidunite/v1', '/push/test', [
        'methods' => 'POST',
        'callback' => 'aidunite_send_test_push_notification',
        'permission_callback' => function() {
            return is_user_logged_in();
        }
    ]);

    // 購読情報更新
    register_rest_route('aidunite/v1', '/push/update-subscription', [
        'methods' => 'POST',
        'callback' => 'aidunite_update_push_subscription',
        'permission_callback' => '__return_true' // Service Workerからの呼び出し
    ]);

    // 分析データ受信
    register_rest_route('aidunite/v1', '/push/analytics', [
        'methods' => 'POST',
        'callback' => 'aidunite_receive_push_analytics',
        'permission_callback' => '__return_true' // Service Workerからの呼び出し
    ]);

    // 通知同期
    register_rest_route('aidunite/v1', '/notifications/sync', [
        'methods' => 'POST',
        'callback' => 'aidunite_sync_notifications',
        'permission_callback' => '__return_true' // Service Workerからの呼び出し
    ]);
});

/**
 * VAPIDパブリックキー取得
 */
function aidunite_get_vapid_public_key($request) {
    $keys = AidUniteWebPushAPI::get_or_generate_vapid_keys();

    return new WP_REST_Response([
        'success' => true,
        'publicKey' => $keys['public_key']
    ]);
}

/**
 * プッシュ購読登録
 */
function aidunite_register_push_subscription($request) {
    global $wpdb;

    $user_id = get_current_user_id();
    $params = $request->get_json_params();
    $subscription = $params['subscription'];

    if (!isset($subscription['endpoint']) || !isset($subscription['keys'])) {
        return new WP_Error('invalid_subscription', '無効な購読データです。', ['status' => 400]);
    }

    $table_name = $wpdb->prefix . AidUniteWebPushAPI::SUBSCRIPTIONS_TABLE;

    // 既存の購読をチェック
    $existing = $wpdb->get_var($wpdb->prepare("
        SELECT id FROM {$table_name}
        WHERE user_id = %d AND endpoint = %s
    ", $user_id, $subscription['endpoint']));

    if ($existing) {
        // 既存の購読を更新
        $result = $wpdb->update(
            $table_name,
            [
                'p256dh_key' => $subscription['keys']['p256dh'],
                'auth_key' => $subscription['keys']['auth'],
                'is_active' => 1,
                'updated_at' => current_time('mysql')
            ],
            ['id' => $existing]
        );
    } else {
        // 新しい購読を登録
        $result = $wpdb->insert(
            $table_name,
            [
                'user_id' => $user_id,
                'endpoint' => $subscription['endpoint'],
                'p256dh_key' => $subscription['keys']['p256dh'],
                'auth_key' => $subscription['keys']['auth'],
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                'is_active' => 1
            ]
        );
    }

    if ($result !== false) {
        return new WP_REST_Response([
            'success' => true,
            'message' => 'プッシュ通知の購読を登録しました。'
        ]);
    } else {
        return new WP_Error('subscription_failed', '購読の登録に失敗しました。', ['status' => 500]);
    }
}

/**
 * プッシュ購読解除
 */
function aidunite_unregister_push_subscription($request) {
    global $wpdb;

    $user_id = get_current_user_id();
    $params = $request->get_json_params();
    $endpoint = $params['endpoint'];

    $table_name = $wpdb->prefix . AidUniteWebPushAPI::SUBSCRIPTIONS_TABLE;

    $result = $wpdb->update(
        $table_name,
        [
            'is_active' => 0,
            'updated_at' => current_time('mysql')
        ],
        [
            'user_id' => $user_id,
            'endpoint' => $endpoint
        ]
    );

    if ($result !== false) {
        return new WP_REST_Response([
            'success' => true,
            'message' => 'プッシュ通知の購読を解除しました。'
        ]);
    } else {
        return new WP_Error('unsubscription_failed', '購読の解除に失敗しました。', ['status' => 500]);
    }
}

/**
 * テスト通知送信
 */
function aidunite_send_test_push_notification($request) {
    $user_id = get_current_user_id();

    $result = AidUniteWebPushAPI::send_push_notification(
        $user_id,
        'AidUnite テスト通知',
        'プッシュ通知が正常に動作しています！',
        [
            'icon' => '/assets/images/notification-icon.png',
            'url' => '/page-notifications.php',
            'priority' => 'high'
        ]
    );

    if ($result) {
        return new WP_REST_Response([
            'success' => true,
            'message' => 'テスト通知を送信しました。'
        ]);
    } else {
        return new WP_Error('test_failed', 'テスト通知の送信に失敗しました。', ['status' => 500]);
    }
}

/**
 * 購読情報更新
 */
function aidunite_update_push_subscription($request) {
    global $wpdb;

    $params = $request->get_json_params();
    $old_endpoint = $params['oldEndpoint'];
    $new_subscription = $params['newSubscription'];

    if (!$new_subscription || !isset($new_subscription['endpoint'])) {
        return new WP_Error('invalid_data', '無効なデータです。', ['status' => 400]);
    }

    $table_name = $wpdb->prefix . AidUniteWebPushAPI::SUBSCRIPTIONS_TABLE;

    if ($old_endpoint) {
        // 既存の購読を更新
        $result = $wpdb->update(
            $table_name,
            [
                'endpoint' => $new_subscription['endpoint'],
                'p256dh_key' => $new_subscription['keys']['p256dh'],
                'auth_key' => $new_subscription['keys']['auth'],
                'updated_at' => current_time('mysql')
            ],
            ['endpoint' => $old_endpoint]
        );
    }

    return new WP_REST_Response(['success' => true]);
}

/**
 * 分析データ受信
 */
function aidunite_receive_push_analytics($request) {
    $params = $request->get_json_params();

    // 分析データをログに記録
    error_log("📊 プッシュ通知分析: " . json_encode($params));

    // 将来的にはデータベースに保存して分析に使用

    return new WP_REST_Response(['success' => true]);
}

/**
 * 通知同期
 */
function aidunite_sync_notifications($request) {
    // オフライン時に蓄積された通知を同期
    // 実装は必要に応じて

    return new WP_REST_Response([
        'success' => true,
        'notifications' => []
    ]);
}

// プラグイン有効化時にテーブル作成
register_activation_hook(__FILE__, [AidUniteWebPushAPI::class, 'create_subscriptions_table']);
