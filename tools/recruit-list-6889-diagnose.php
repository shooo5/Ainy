<?php
$wp_load = dirname(__DIR__, 4) . '/wp-load.php';
require_once $wp_load;
require_once dirname(__DIR__) . '/functions/match/match-board-page-helpers.php';
if (function_exists('aidunite_match_board_ensure_dependencies')) {
    aidunite_match_board_ensure_dependencies();
}

$viewer = 5680;
$range = aidunite_market_board_default_date_range();
$all = aidunite_market_get_all_recruitments($range['from'], $range['to'], $viewer, ['gender' => '']);
header('Content-Type: text/plain; charset=utf-8');
echo "range {$range['from']} .. {$range['to']}\n";
foreach ($all as $p) {
    $id = (int) $p->ID;
    $team = (int) get_post_meta($id, 'team_id', true);
    $date = function_exists('aidunite_schedule_read_normalized_date')
        ? aidunite_schedule_read_normalized_date($id)
        : get_post_meta($id, 'schedule_date', true);
    $hide = aidunite_market_viewer_hides_established_recruit_row($viewer, $id);
    $ctx = aidunite_get_established_context_for_recruit_row($viewer, $id);
    $ctx_rid = is_array($ctx) ? (int) $ctx['request_id'] : 0;
    if ($date === '2026-07-11' || $id === 6889 || $team === 5667) {
        echo "sid={$id} team={$team} date={$date} hide=" . ($hide ? 'Y' : 'N') . " ctx_mr={$ctx_rid}\n";
    }
}
echo "--- rows that would render (hide=N) for 7/11 team 5667 ---\n";
foreach ($all as $p) {
    $id = (int) $p->ID;
    $team = (int) get_post_meta($id, 'team_id', true);
    $date = function_exists('aidunite_schedule_read_normalized_date')
        ? aidunite_schedule_read_normalized_date($id)
        : get_post_meta($id, 'schedule_date', true);
    if ($date !== '2026-07-11' || $team !== 5667) {
        continue;
    }
    if (!aidunite_market_viewer_hides_established_recruit_row($viewer, $id)) {
        echo "VISIBLE sid={$id}\n";
    }
}
