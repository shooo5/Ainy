<?php
/**
 * Legacy メタのバックフィル（ローカル / ステージング向け CLI）
 *
 * 用法（テーマルートから・Local の Open site shell 推奨）:
 *   php scripts/backfill-legacy-meta.php --dry-run
 *   php scripts/backfill-legacy-meta.php --apply
 *   php scripts/backfill-legacy-meta.php --apply --only=notification
 *   php scripts/backfill-legacy-meta.php --apply --only=attendance
 *   php scripts/backfill-legacy-meta.php --apply --only=registration_source
 *
 * --only: notification | attendance | registration_source | all（既定 all）
 */

$wp_load = dirname(__DIR__, 4) . '/wp-load.php';
if (!is_readable($wp_load)) {
    fwrite(STDERR, "wp-load.php が見つかりません: {$wp_load}\n");
    exit(1);
}

define('WP_USE_THEMES', false);
ob_start();
require $wp_load;
ob_end_clean();

$apply = in_array('--apply', $argv, true);
$dry_run = !$apply;
$only = 'all';
foreach ($argv as $arg) {
    if (strpos($arg, '--only=') === 0) {
        $only = substr($arg, 7);
    }
}

$targets = $only === 'all' ? ['notification', 'attendance', 'registration_source'] : [$only];
$report = [
    'mode' => $dry_run ? 'dry-run' : 'apply',
    'notification' => ['updated' => 0, 'skipped' => 0],
    'attendance' => ['posts' => 0, 'status_fields' => 0],
    'registration_source' => ['updated' => 0],
];

/**
 * @param string $from
 * @param string $to
 */
function aidunite_backfill_notification_type($from, $to, $dry_run, &$report) {
    global $wpdb;
    $ids = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT pm.post_id FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
             WHERE pm.meta_key = 'type' AND p.post_type = 'notification' AND pm.meta_value = %s",
            $from
        )
    );
    foreach ($ids as $post_id) {
        if ($dry_run) {
            $report['notification']['updated']++;
            echo "[dry-run] notification #{$post_id}: type {$from} -> {$to}\n";
            continue;
        }
        update_post_meta((int) $post_id, 'type', $to);
        $report['notification']['updated']++;
        echo "[apply] notification #{$post_id}: type {$from} -> {$to}\n";
    }
}

if (in_array('notification', $targets, true)) {
    $map = [
        'match_accepted' => 'match_established',
        'team_approved' => 'team_approval_completed',
        'team_approval' => 'team_approval_completed',
        'admin_team_application' => 'team_approval_request',
    ];
    foreach ($map as $from => $to) {
        aidunite_backfill_notification_type($from, $to, $dry_run, $report);
    }
}

if (in_array('registration_source', $targets, true)) {
    global $wpdb;
    $patterns = ['Forminator 271', '271', 'forminator', 'Forminator'];
    foreach ($patterns as $pattern) {
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT user_id, meta_value FROM {$wpdb->usermeta}
                 WHERE meta_key = 'registration_source' AND meta_value = %s",
                $pattern
            )
        );
        foreach ($rows as $row) {
            if ($dry_run) {
                $report['registration_source']['updated']++;
                echo "[dry-run] user #{$row->user_id}: registration_source {$row->meta_value} -> legacy_forminator\n";
                continue;
            }
            update_user_meta((int) $row->user_id, 'registration_source', 'legacy_forminator');
            $report['registration_source']['updated']++;
            echo "[apply] user #{$row->user_id}: registration_source -> legacy_forminator\n";
        }
    }
}

if (in_array('attendance', $targets, true)) {
    if (!function_exists('aidunite_normalize_attendance_status_value')) {
        fwrite(STDERR, "aidunite_normalize_attendance_status_value が見つかりません。normalize-service.php を読み込んでください。\n");
        exit(1);
    }
    global $wpdb;
    $schedule_ids = $wpdb->get_col(
        "SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'attendance_data'"
    );
    foreach ($schedule_ids as $schedule_id) {
        $schedule_id = (int) $schedule_id;
        $data = get_post_meta($schedule_id, 'attendance_data', true);
        if (!is_array($data) || $data === []) {
            continue;
        }
        $changed = false;
        foreach ($data as $user_id => $row) {
            if (!is_array($row) || !isset($row['status'])) {
                continue;
            }
            $raw = (string) $row['status'];
            $norm = aidunite_normalize_attendance_status_value($raw);
            if ($norm === $raw) {
                continue;
            }
            $changed = true;
            $report['attendance']['status_fields']++;
            if ($dry_run) {
                echo "[dry-run] schedule #{$schedule_id} user #{$user_id}: status {$raw} -> {$norm}\n";
            } else {
                $data[$user_id]['status'] = $norm;
            }
        }
        if ($changed && !$dry_run) {
            update_post_meta($schedule_id, 'attendance_data', $data);
            $report['attendance']['posts']++;
            echo "[apply] schedule #{$schedule_id}: attendance_data updated\n";
        } elseif ($changed) {
            $report['attendance']['posts']++;
        }
    }
}

echo "\n--- summary ---\n";
echo wp_json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
echo $dry_run ? "\n適用するには: php scripts/backfill-legacy-meta.php --apply\n" : "\n完了\n";
