<?php
/**
 * マッチ申請の承認滞留リマインド（48時間経過・1回のみ）
 *
 * @package AidUnite
 */

if (!defined('ABSPATH')) {
    exit;
}

const AIDUNITE_MATCH_REQUEST_REMINDER_CRON_HOOK = 'aidunite_match_request_pending_reminder_cron';

add_action('init', static function () {
    if (!wp_next_scheduled(AIDUNITE_MATCH_REQUEST_REMINDER_CRON_HOOK)) {
        wp_schedule_event(time() + HOUR_IN_SECONDS, 'twicedaily', AIDUNITE_MATCH_REQUEST_REMINDER_CRON_HOOK);
    }
});

add_action(AIDUNITE_MATCH_REQUEST_REMINDER_CRON_HOOK, 'aidunite_match_request_run_pending_reminders');

/**
 * 48h 以上 pending の受信側申請にリマインドを送る
 */
function aidunite_match_request_run_pending_reminders() {
    $cutoff = gmdate('Y-m-d H:i:s', time() - (48 * HOUR_IN_SECONDS));

    $pending_statuses = ['pending', '申請中', 'publish', ''];
    $ids = get_posts([
        'post_type'      => 'match_request',
        'post_status'    => 'publish',
        'posts_per_page' => 50,
        'fields'         => 'ids',
        'date_query'     => [
            ['before' => $cutoff, 'inclusive' => true],
        ],
        'meta_query'     => [
            'relation' => 'AND',
            [
                'relation' => 'OR',
                ['key' => 'reminder_sent_at', 'compare' => 'NOT EXISTS'],
                ['key' => 'reminder_sent_at', 'value' => '', 'compare' => '='],
            ],
            [
                'relation' => 'OR',
                ['key' => 'status', 'value' => $pending_statuses, 'compare' => 'IN'],
                ['key' => 'request_status', 'value' => $pending_statuses, 'compare' => 'IN'],
            ],
        ],
    ]);

    if ($ids === []) {
        return;
    }

    foreach ($ids as $request_id) {
        $request_id = (int) $request_id;
        if ($request_id <= 0) {
            continue;
        }

        $canonical = function_exists('aidunite_match_request_get_canonical_meta')
            ? aidunite_match_request_get_canonical_meta($request_id)
            : [];
        $status = strtolower((string) ($canonical['status'] ?? ''));
        if (!in_array($status, ['pending', 'publish', '申請中', ''], true)) {
            continue;
        }
        if ((int) ($canonical['requires_reconfirm'] ?? 0) === 1) {
            continue;
        }

        $sent = function_exists('send_match_request_pending_reminder_notification')
            && send_match_request_pending_reminder_notification($request_id);
        if (!$sent) {
            continue;
        }

        if (function_exists('aidunite_match_request_persist_reminder_sent')) {
            aidunite_match_request_persist_reminder_sent($request_id);
        }
    }
}
