<?php
/**
 * 通知 CPT 読取正本
 *
 * @package AidUnite
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 通知の宛先ユーザー ID（target_user_id メタ → post_author）
 *
 * @param int $notification_id
 * @return int
 */
function aidunite_notification_read_recipient_user_id($notification_id) {
    $notification_id = (int) $notification_id;
    if ($notification_id < 1 || get_post_type($notification_id) !== 'notification') {
        return 0;
    }

    $target_user_id = (int) get_post_meta($notification_id, 'target_user_id', true);
    if ($target_user_id > 0) {
        return $target_user_id;
    }

    if (function_exists('aidunite_notification_get_canonical_meta')) {
        $meta = aidunite_notification_get_canonical_meta($notification_id);

        return (int) ($meta['post_author'] ?? 0);
    }

    return (int) get_post_field('post_author', $notification_id);
}

/**
 * 現在ユーザーが通知の宛先か
 *
 * @param int $notification_id
 * @param int $user_id
 * @return bool
 */
function aidunite_notification_read_user_may_access($notification_id, $user_id) {
    $notification_id = (int) $notification_id;
    $user_id = (int) $user_id;
    if ($notification_id < 1 || $user_id < 1) {
        return false;
    }

    return aidunite_notification_read_recipient_user_id($notification_id) === $user_id;
}
