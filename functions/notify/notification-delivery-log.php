<?php
/**
 * 通知配信ログ（管理者向け監視用・docs/spec/notification.md 第24節）
 *
 * - 親: 1 回の aidunite_notification_send 呼び出し
 * - 子: チャンネル別（app / email / line / push）の success | failed | skipped
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('AIDUNITE_NOTIFY_DELIVERY_SCHEMA_VERSION')) {
    define('AIDUNITE_NOTIFY_DELIVERY_SCHEMA_VERSION', 1);
}

/**
 * @return string
 */
function aidunite_notification_delivery_events_table() {
    global $wpdb;
    return $wpdb->prefix . 'aidunite_notify_delivery_event';
}

/**
 * @return string
 */
function aidunite_notification_delivery_channels_table() {
    global $wpdb;
    return $wpdb->prefix . 'aidunite_notify_delivery_channel';
}

/**
 * スキーマを dbDelta で確保する（管理画面 init および送信直前の遅延作成）。
 */
function aidunite_notification_delivery_maybe_install_tables() {
    $v = (int) get_option('aidunite_notify_delivery_schema_version', 0);
    if ($v >= AIDUNITE_NOTIFY_DELIVERY_SCHEMA_VERSION) {
        return;
    }

    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $charset_collate = $wpdb->get_charset_collate();
    $events = aidunite_notification_delivery_events_table();
    $channels = aidunite_notification_delivery_channels_table();

    $sql_events = "CREATE TABLE {$events} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        correlation_id char(36) NOT NULL,
        idempotency_key varchar(191) NULL,
        user_id bigint(20) unsigned NOT NULL DEFAULT 0,
        notification_type varchar(64) NOT NULL DEFAULT '',
        related_id bigint(20) unsigned NULL,
        notification_post_id bigint(20) unsigned NULL,
        environment varchar(32) NOT NULL DEFAULT '',
        created_at datetime NOT NULL,
        PRIMARY KEY  (id),
        KEY correlation_id (correlation_id),
        KEY user_created (user_id, created_at),
        KEY type_created (notification_type, created_at),
        KEY idempotency_key (idempotency_key)
    ) {$charset_collate};";

    $sql_channels = "CREATE TABLE {$channels} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        event_id bigint(20) unsigned NOT NULL,
        channel varchar(32) NOT NULL,
        result varchar(16) NOT NULL,
        skip_reason varchar(64) NULL,
        detail text NULL,
        PRIMARY KEY  (id),
        KEY event_id (event_id)
    ) {$charset_collate};";

    dbDelta($sql_events);
    dbDelta($sql_channels);
    update_option('aidunite_notify_delivery_schema_version', AIDUNITE_NOTIFY_DELIVERY_SCHEMA_VERSION);
}

/**
 * 配信ログを書き込む（失敗しても通知本体は落とさない）。
 *
 * @param array $args {
 *   @type string $correlation_id
 *   @type string|null $idempotency_key
 *   @type int $user_id
 *   @type string $notification_type
 *   @type int|null $related_id
 *   @type int|null $notification_post_id 通知 CPT の ID（未作成なら null）
 *   @type array<int,array{channel:string,result:string,skip_reason?:string,detail?:string}> $channels
 * }
 */
function aidunite_notification_delivery_log_write(array $args) {
    if (apply_filters('aidunite_notification_delivery_log_disabled', false)) {
        return;
    }

    try {
        aidunite_notification_delivery_maybe_install_tables();
    } catch (Throwable $e) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[AidUnite] notify delivery log install failed: ' . $e->getMessage());
        }
        return;
    }

    global $wpdb;
    $events = aidunite_notification_delivery_events_table();
    $channels = aidunite_notification_delivery_channels_table();

    $correlation_id = isset($args['correlation_id']) ? sanitize_text_field($args['correlation_id']) : '';
    if (strlen($correlation_id) !== 36) {
        $correlation_id = wp_generate_uuid4();
    }

    $idempotency = isset($args['idempotency_key']) ? sanitize_text_field((string) $args['idempotency_key']) : null;
    if ($idempotency === '') {
        $idempotency = null;
    }

    $user_id = (int) ($args['user_id'] ?? 0);
    $notification_type = sanitize_text_field((string) ($args['notification_type'] ?? ''));
    $related_id = isset($args['related_id']) && $args['related_id'] !== null && $args['related_id'] !== ''
        ? (int) $args['related_id']
        : null;
    $notification_post_id = isset($args['notification_post_id']) && $args['notification_post_id']
        ? (int) $args['notification_post_id']
        : null;

    $env = function_exists('wp_get_environment_type') ? (string) wp_get_environment_type() : 'production';
    if ($env === '') {
        $env = 'production';
    }
    $created_at = current_time('mysql', true);

    $row = [
        'correlation_id' => $correlation_id,
        'user_id' => $user_id,
        'notification_type' => $notification_type,
        'environment' => substr($env, 0, 32),
        'created_at' => $created_at,
    ];
    $format = ['%s', '%d', '%s', '%s', '%s'];

    if ($idempotency !== null) {
        $row['idempotency_key'] = $idempotency;
        $format[] = '%s';
    }
    if ($related_id !== null) {
        $row['related_id'] = $related_id;
        $format[] = '%d';
    }
    if ($notification_post_id !== null) {
        $row['notification_post_id'] = $notification_post_id;
        $format[] = '%d';
    }

    $wpdb->insert($events, $row, $format);

    $event_id = (int) $wpdb->insert_id;
    if ($event_id <= 0) {
        return;
    }

    foreach ($args['channels'] ?? [] as $row) {
        if (!is_array($row) || empty($row['channel']) || empty($row['result'])) {
            continue;
        }
        $ch_row = [
            'event_id' => $event_id,
            'channel' => substr(sanitize_text_field((string) $row['channel']), 0, 32),
            'result' => substr(sanitize_text_field((string) $row['result']), 0, 16),
        ];
        $ch_fmt = ['%d', '%s', '%s'];
        if (!empty($row['skip_reason'])) {
            $ch_row['skip_reason'] = substr(sanitize_text_field((string) $row['skip_reason']), 0, 64);
            $ch_fmt[] = '%s';
        }
        if (!empty($row['detail'])) {
            $ch_row['detail'] = substr(sanitize_text_field((string) $row['detail']), 0, 500);
            $ch_fmt[] = '%s';
        }
        $wpdb->insert($channels, $ch_row, $ch_fmt);
    }
}

add_action('admin_init', 'aidunite_notification_delivery_maybe_install_tables', 5);
