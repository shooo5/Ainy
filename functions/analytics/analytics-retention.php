<?php
/**
 * 分析データの保持期間に基づく削除（cron）
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/analytics-config.php';
require_once __DIR__ . '/analytics-db.php';

const AIDUNITE_ANALYTICS_PURGE_CRON_HOOK = 'aidunite_analytics_purge_old_data';
const AIDUNITE_ANALYTICS_PURGE_SCHEDULED_OPTION = 'aidunite_analytics_purge_cron_scheduled';

/**
 * 保持ポリシーに従い古いデータを削除
 */
function aidunite_analytics_purge_old_data() {
    global $wpdb;

    $raw_days = (int) AIDUNITE_ANALYTICS_RAW_EVENTS_RETENTION_DAYS;
    $daily_days = (int) AIDUNITE_ANALYTICS_DAILY_AGGREGATE_RETENTION_DAYS;

    $events_table = aidunite_analytics_page_events_table();
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $events_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $events_table)) === $events_table;
    if ($events_exists && $raw_days > 0) {
        $cutoff = gmdate('Y-m-d', strtotime('-' . $raw_days . ' days', strtotime(current_time('Y-m-d') . ' UTC')));
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$events_table} WHERE metric_date < %s",
                $cutoff
            )
        );
    }

    if ($daily_days > 0 && function_exists('aidunite_analytics_page_table')) {
        $cutoff = gmdate('Y-m-d', strtotime('-' . $daily_days . ' days', strtotime(current_time('Y-m-d') . ' UTC')));
        $page_table = aidunite_analytics_page_table();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$page_table} WHERE metric_date < %s",
                $cutoff
            )
        );
    }
}

function aidunite_analytics_schedule_purge_cron() {
    if (get_option(AIDUNITE_ANALYTICS_PURGE_SCHEDULED_OPTION)) {
        return;
    }
    if (!wp_next_scheduled(AIDUNITE_ANALYTICS_PURGE_CRON_HOOK)) {
        wp_schedule_event(strtotime('tomorrow 03:15'), 'daily', AIDUNITE_ANALYTICS_PURGE_CRON_HOOK);
    }
    update_option(AIDUNITE_ANALYTICS_PURGE_SCHEDULED_OPTION, true, false);
}

add_action('init', 'aidunite_analytics_schedule_purge_cron', 25);
add_action(AIDUNITE_ANALYTICS_PURGE_CRON_HOOK, 'aidunite_analytics_purge_old_data');
