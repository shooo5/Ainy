<?php
/**
 * 通知 CPT の postmeta 保存本体（normalize 経由）
 *
 * 送信の入口は aidunite_notification_send（notification-api.php）。
 * type / is_read の書き込みは本モジュールに集約する。
 *
 * @package AidUnite
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * @param array<string, mixed> $payload
 * @return array<string, mixed>
 */
function aidunite_notification_normalize_input(array $payload) {
    if (function_exists('aidunite_normalize_notification_payload')) {
        return aidunite_normalize_notification_payload($payload);
    }

    return $payload;
}

/**
 * boolean 正規化（DB には '1' / '0' で保存）
 *
 * @param mixed $raw
 * @return string '1'|'0'
 */
function aidunite_notification_normalize_is_read_storage($raw) {
    if (function_exists('aidunite_normalize_boolean_payload_value')) {
        return aidunite_normalize_boolean_payload_value($raw) ? '1' : '0';
    }
    return filter_var($raw, FILTER_VALIDATE_BOOLEAN) ? '1' : '0';
}

/**
 * 通知 postmeta を正規化して保存（唯一の書き込み口）
 *
 * @param int                  $notification_id
 * @param array<string, mixed> $meta type, is_read, related_id, link_url
 * @return bool
 */
function aidunite_notification_write_post_meta($notification_id, array $meta) {
    $notification_id = (int) $notification_id;
    if ($notification_id < 1 || get_post_type($notification_id) !== 'notification') {
        return false;
    }

    $normalized = aidunite_notification_normalize_input($meta);

    if (array_key_exists('type', $normalized)) {
        $type = sanitize_text_field((string) $normalized['type']);
        if ($type !== '') {
            update_post_meta($notification_id, 'type', $type);
        }
    }

    if (array_key_exists('is_read', $normalized)) {
        update_post_meta($notification_id, 'is_read', aidunite_notification_normalize_is_read_storage($normalized['is_read']));
    }

    if (array_key_exists('related_id', $normalized) && $normalized['related_id'] !== '' && $normalized['related_id'] !== null) {
        update_post_meta($notification_id, 'related_id', (int) $normalized['related_id']);
    }

    if (!empty($normalized['link_url'])) {
        update_post_meta($notification_id, 'link_url', esc_url_raw((string) $normalized['link_url']));
    }

    return true;
}

/**
 * ユーザー通知設定（notification_settings usermeta）の保存
 *
 * @param int                  $user_id
 * @param array<string, mixed> $settings
 * @return bool
 */
function aidunite_notification_persist_user_settings($user_id, array $settings) {
    $user_id = (int) $user_id;
    if ($user_id <= 0 || $settings === []) {
        return false;
    }

    $normalized = $settings;
    if (function_exists('aidunite_normalize_notification_payload')) {
        $normalized = aidunite_normalize_notification_payload($settings);
    }

    update_user_meta($user_id, 'notification_settings', $normalized);

    return true;
}

/**
 * 既読フラグを更新
 *
 * @param int  $notification_id
 * @param bool $is_read
 * @return bool
 */
function aidunite_notification_write_is_read($notification_id, $is_read = true) {
    return aidunite_notification_write_post_meta($notification_id, ['is_read' => $is_read]);
}

/**
 * @param int $notification_id
 * @return array<string, mixed>
 */
function aidunite_notification_get_canonical_meta($notification_id) {
    $notification_id = (int) $notification_id;
    if ($notification_id < 1 || get_post_type($notification_id) !== 'notification') {
        return [];
    }

    $type_raw = (string) get_post_meta($notification_id, 'type', true);
    $type = function_exists('aidunite_normalize_notification_type_value')
        ? (string) aidunite_normalize_notification_type_value($type_raw)
        : $type_raw;

    $is_read_raw = get_post_meta($notification_id, 'is_read', true);
    $is_read = aidunite_notification_normalize_is_read_storage($is_read_raw) === '1';

    return [
        'notification_id' => $notification_id,
        'post_author' => (int) get_post_field('post_author', $notification_id),
        'type' => $type,
        'type_raw' => $type_raw,
        'type_is_legacy' => ($type_raw !== '' && $type_raw !== $type),
        'is_read' => $is_read,
        'related_id' => (int) get_post_meta($notification_id, 'related_id', true),
        'link_url' => (string) get_post_meta($notification_id, 'link_url', true),
    ];
}

/**
 * 既存通知の legacy type メタを canonical へ移行
 *
 * @param bool $dry_run
 * @return array{migrated_count:int,skipped_count:int,total:int,dry_run:bool}
 */
function aidunite_notification_migrate_legacy_type_meta($dry_run = false) {
    global $wpdb;

    $rows = $wpdb->get_results(
        "SELECT p.ID AS notification_id, pm.meta_value AS type_raw
         FROM {$wpdb->posts} p
         INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'type'
         WHERE p.post_type = 'notification'
         AND pm.meta_value != ''"
    );

    $migrated = 0;
    $skipped = 0;
    foreach ($rows ?: [] as $row) {
        $nid = (int) $row->notification_id;
        $raw = (string) $row->type_raw;
        $canonical = function_exists('aidunite_normalize_notification_type_value')
            ? (string) aidunite_normalize_notification_type_value($raw)
            : $raw;
        if ($canonical === '' || $canonical === $raw) {
            $skipped++;
            continue;
        }
        if (!$dry_run) {
            aidunite_notification_write_post_meta($nid, ['type' => $canonical]);
        }
        $migrated++;
    }

    return [
        'migrated_count' => $migrated,
        'skipped_count'  => $skipped,
        'total'          => count($rows ?: []),
        'dry_run'        => (bool) $dry_run,
    ];
}
