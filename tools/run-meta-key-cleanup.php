<?php
/**
 * メタキー移行 + Phase 5 クリーンアップ（Local / CLI 向け）
 * 例: php tools/run-meta-key-cleanup.php
 */

require_once __DIR__ . '/wp-bootstrap.php';
if (!aidunite_tools_bootstrap_wp()) {
    exit(1);
}

if (!function_exists('aidunite_migrate_meta_keys')) {
    require_once get_template_directory() . '/functions/schedule/meta-key-migration.php';
}
if (!function_exists('aidunite_migrate_match_request_meta_keys')) {
    require_once get_template_directory() . '/functions/match/match-request-persist.php';
}

$is_cli = (PHP_SAPI === 'cli');
if (!$is_cli) {
    if (!is_user_logged_in() || !current_user_can('manage_options')) {
        wp_die('管理者でログインした状態で開くか、CLI で php tools/run-meta-key-cleanup.php を実行してください。');
    }
    header('Content-Type: text/plain; charset=utf-8');
}

$out = static function (string $line) use ($is_cli): void {
    echo $line . ($is_cli ? "\n" : "\n");
};

$out('=== Phase 4 migrate (copy to canonical if missing) ===');
$migrate = aidunite_migrate_meta_keys();
$out(json_encode($migrate, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

$out('');
$out('=== Phase 5 cleanup (delete legacy when canonical exists) ===');
$cleanup = aidunite_cleanup_old_meta_keys();
$out(json_encode($cleanup, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

global $wpdb;
$legacy_keys = [
    'matching_gender_condition',
    'schedule_place_option',
    'male_teams',
    'female_teams',
    'schedule_note',
];

$out('');
$out('=== remaining legacy meta (schedule posts, non-empty) ===');
foreach ($legacy_keys as $key) {
    $sql = $wpdb->prepare(
        "SELECT COUNT(DISTINCT pm.post_id)
         FROM {$wpdb->postmeta} pm
         INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
         WHERE p.post_type = 'schedule'
           AND pm.meta_key = %s
           AND pm.meta_value != ''",
        $key
    );
    $cnt = (int) $wpdb->get_var($sql);
    $out("{$key}={$cnt}");
}

update_option('aidunite_meta_key_migration_results', $migrate);
update_option('aidunite_meta_key_migration_date', current_time('mysql'));
update_option('aidunite_meta_key_cleanup_results', $cleanup);
update_option('aidunite_meta_key_cleanup_date', current_time('mysql'));

$out('');
if (function_exists('aidunite_migrate_match_request_meta_keys')) {
    $out('');
    $out('=== match_request migrate ===');
    $mr_migrate = aidunite_migrate_match_request_meta_keys();
    $out(json_encode($mr_migrate, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

    $out('');
    $out('=== match_request cleanup ===');
    $mr_cleanup = aidunite_cleanup_match_request_legacy_meta_keys();
    $out(json_encode($mr_cleanup, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

    global $wpdb;
    $mr_legacy = ['request_status', 'preferred_start', 'preferred_end', 'preferred_place', 'preferred_gender', 'canceled_reason', 'aidunite_cancel_reason'];
    $out('');
    $out('=== remaining match_request legacy (non-empty) ===');
    foreach ($mr_legacy as $key) {
        $sql = $wpdb->prepare(
            "SELECT COUNT(DISTINCT pm.post_id)
             FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
             WHERE p.post_type = 'match_request'
               AND pm.meta_key = %s
               AND pm.meta_value != ''",
            $key
        );
        $cnt = (int) $wpdb->get_var($sql);
        $out("{$key}={$cnt}");
    }
}

$out('');
$out('Done.');
