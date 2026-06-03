<?php
/**
 * analytics persist 診断
 * 例: .../tools/analytics-persist-diagnose.php
 */

$wp_load = dirname(__DIR__, 4) . '/wp-load.php';
if (!is_readable($wp_load)) {
    header('Content-Type: text/plain; charset=utf-8');
    echo "wp-load.php not found: {$wp_load}\n";
    exit(1);
}

require_once $wp_load;

if (!is_user_logged_in() && PHP_SAPI !== 'cli') {
    wp_die('ログインが必要です。');
}

$lines = [];
$lines[] = '=== analytics persist diagnose ===';
$lines[] = 'persist_loaded=' . (function_exists('aidunite_analytics_persist_insert_page_event') ? 'yes' : 'no');

if (function_exists('aidunite_analytics_get_recent_page_events_sample')) {
    $sample = aidunite_analytics_get_recent_page_events_sample(5);
    $lines[] = 'table_available=' . var_export($sample['available'] ?? false, true);
    foreach ($sample['rows'] ?? [] as $row) {
        $lines[] = 'row=' . wp_json_encode($row, JSON_UNESCAPED_UNICODE);
    }
}

$lines[] = 'canonical_event_types=' . implode(',', aidunite_analytics_canonical_event_types());

header('Content-Type: text/plain; charset=utf-8');
echo implode("\n", $lines) . "\n";
