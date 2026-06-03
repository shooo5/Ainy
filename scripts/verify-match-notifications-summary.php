<?php
/**
 * 直近マッチ申請に対するお知らせ（notification CPT）の有無をサマリ表示（CLI）
 *
 * 用法（Local Open site shell・テーマルート）:
 *   php scripts/verify-match-notifications-summary.php
 *   php scripts/verify-match-notifications-summary.php --limit=10
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

if (!defined('ABSPATH')) {
    fwrite(STDERR, "WordPress の読み込みに失敗しました。\n");
    exit(1);
}

$limit = 5;
foreach ($argv ?? [] as $arg) {
    if (preg_match('/^--limit=(\d+)$/', (string) $arg, $m)) {
        $limit = max(1, min(50, (int) $m[1]));
    }
}

$requests = get_posts([
    'post_type'      => 'match_request',
    'post_status'    => 'any',
    'posts_per_page' => $limit,
    'orderby'        => 'ID',
    'order'          => 'DESC',
]);

if ($requests === []) {
    fwrite(STDERR, "match_request が1件もありません。\n");
    exit(0);
}

$match_types = [
    'match_request',
    'match_request_received',
    'match_established',
    'match_accepted', // legacy
    'match_rejected',
    'match_cancelled',
    'match_canceled',
    'match_updated',
    'match_feedback_survey',
    'match_participant_withdrawn',
];

fwrite(STDERR, "\n=== 直近マッチ申請 × お知らせサマリ（最新 {$limit} 件）===\n\n");

foreach ($requests as $req) {
    $rid = (int) $req->ID;
    $status = (string) get_post_meta($rid, 'status', true);
    $from_team = (int) get_post_meta($rid, 'from_team_id', true);
    $to_team = (int) get_post_meta($rid, 'to_team_id', true);

    $leader_ids = function_exists('get_team_leaders') ? get_team_leaders($from_team) : [];
    $to_leaders = function_exists('get_team_leaders') ? get_team_leaders($to_team) : [];
    $all_leaders = array_values(array_unique(array_merge($leader_ids, $to_leaders)));

    $notif_count = 0;
    $types_found = [];

    if ($all_leaders !== []) {
        $q = new WP_Query([
            'post_type'      => 'notification',
            'post_status'    => 'publish',
            'posts_per_page' => 50,
            'author__in'     => $all_leaders,
            'meta_query'     => [
                [
                    'key'     => 'related_id',
                    'value'   => $rid,
                    'compare' => '=',
                ],
            ],
            'fields'         => 'ids',
        ]);
        $notif_count = (int) $q->found_posts;
        foreach ($q->posts as $nid) {
            $t = (string) get_post_meta((int) $nid, 'type', true);
            if ($t !== '') {
                $types_found[$t] = true;
            }
        }
    }

    $leaders_note = $all_leaders === [] ? '代表者0人（通知送れない）' : '代表者' . count($all_leaders) . '人';

    fwrite(STDERR, "MR #{$rid} status={$status} {$leaders_note}\n");
    $line = "  お知らせ(related_id={$rid}): {$notif_count} 件";
    if ($types_found !== []) {
        $line .= ' [' . implode(', ', array_keys($types_found)) . ']';
    }
    fwrite(STDERR, $line . "\n");

    if ($all_leaders === []) {
        fwrite(STDERR, "  ⚠ チーム {$from_team} / {$to_team} に代表者が解決できません（team_leader_id / affiliated）\n");
    } elseif ($notif_count === 0 && in_array($status, ['pending', 'accepted', 'established'], true)) {
        fwrite(STDERR, "  ⚠ 代表者はいるがお知らせ0件（申請・承認時に未送信の可能性）\n");
    }
    fwrite(STDERR, "\n");
}

fwrite(STDERR, "一覧ページ: " . home_url('/notifications/') . "\n");
fwrite(STDERR, "管理者テスト: POST /wp-json/aidunite/v1/notification-test（manage_options）\n");
fwrite(STDERR, "---\n");

exit(0);
