<?php
/**
 * 募集中行の非表示判定診断（CLI / 管理者 GET）
 * 例: ?viewer_team_id=&recruit_schedule_id=6889&request_id=7347&my_schedule_id=6979
 */

$wp_load = dirname(__DIR__, 4) . '/wp-load.php';
if (!is_readable($wp_load)) {
    echo "wp-load not found\n";
    exit(1);
}
require_once $wp_load;

if (php_sapi_name() !== 'cli' && !current_user_can('manage_options')) {
    wp_die('manage_options required');
}

$rid = isset($_GET['request_id']) ? (int) $_GET['request_id'] : 7347;
$recruit_sid = isset($_GET['recruit_schedule_id']) ? (int) $_GET['recruit_schedule_id'] : (isset($_GET['schedule_id']) ? (int) $_GET['schedule_id'] : 6889);
$my_sid = isset($_GET['my_schedule_id']) ? (int) $_GET['my_schedule_id'] : 6979;
$viewer_team = isset($_GET['viewer_team_id']) ? (int) $_GET['viewer_team_id'] : 0;
if ($viewer_team <= 0 && $my_sid > 0) {
    $viewer_team = (int) get_post_meta($my_sid, 'team_id', true);
}

header('Content-Type: text/plain; charset=utf-8');

$out = static function ($label, $value) {
    echo $label . '=' . (is_bool($value) ? ($value ? 'true' : 'false') : (string) $value) . "\n";
};

$out('viewer_team_id', $viewer_team);
$out('recruit_schedule_id', $recruit_sid);
$out('my_schedule_id', $my_sid);
$out('match_request_id', $rid);

foreach ([$recruit_sid, $my_sid] as $sid) {
    if ($sid <= 0) {
        continue;
    }
    echo "\n--- schedule {$sid} ---\n";
    $out('post_status', get_post_status($sid));
    $out('team_id', get_post_meta($sid, 'team_id', true));
    $out('schedule_date', function_exists('aidunite_schedule_read_normalized_date')
        ? aidunite_schedule_read_normalized_date($sid)
        : get_post_meta($sid, 'schedule_date', true));
    $out('intent', get_post_meta($sid, 'intent', true));
    $out('matching', get_post_meta($sid, 'matching', true));
    if (function_exists('aidunite_schedule_read_normalized_date')) {
        $out('read_normalized_date', aidunite_schedule_read_normalized_date($sid));
    }
    if (function_exists('aidunite_recruit_accepts_match_applications')) {
        $out('recruit_accepts', aidunite_recruit_accepts_match_applications($sid));
    }
}

if ($rid > 0) {
    echo "\n--- match_request {$rid} ---\n";
    $out('status', get_post_meta($rid, 'status', true));
    $out('established_at', get_post_meta($rid, 'established_at', true));
    $out('from_team_id', get_post_meta($rid, 'from_team_id', true));
    $out('to_team_id', get_post_meta($rid, 'to_team_id', true));
    $out('my_schedule_id', get_post_meta($rid, 'my_schedule_id', true));
    $out('to_schedule_id', get_post_meta($rid, 'to_schedule_id', true));
    $out('from_schedule_id', get_post_meta($rid, 'from_schedule_id', true));
    $out('match_game_id', get_post_meta($rid, 'match_game_id', true));
    if (function_exists('aidunite_resolve_match_game_id_for_match_request')) {
        $out('resolved_game_id', aidunite_resolve_match_game_id_for_match_request($rid));
    }
    if (function_exists('aidunite_match_request_get_link_schedule_teams')) {
        $link = aidunite_match_request_get_link_schedule_teams($rid);
        $out('link_my_sid', $link[0] ?? 0);
        $out('link_to_sid', $link[1] ?? 0);
        $out('link_my_team', $link[2] ?? 0);
        $out('link_to_team', $link[3] ?? 0);
    }
    $recruit_team = (int) get_post_meta($recruit_sid, 'team_id', true);
    if (function_exists('aidunite_match_request_involves_team_pair')) {
        $out('involves_viewer_recruit_pair', aidunite_match_request_involves_team_pair(get_post($rid), $viewer_team, $recruit_team));
    }
    if (function_exists('aidunite_match_request_is_viewer_recruit_establishment')) {
        $out('is_establishment', aidunite_match_request_is_viewer_recruit_establishment($rid, $viewer_team, $recruit_team));
    }
}

echo "\n--- hide helpers ---\n";
if (function_exists('aidunite_get_established_context_for_recruit_row')) {
    $ctx = aidunite_get_established_context_for_recruit_row($viewer_team, $recruit_sid);
    $out('established_ctx', $ctx ? wp_json_encode($ctx) : 'null');
}
if (function_exists('aidunite_market_viewer_hides_established_recruit_row')) {
    $out('viewer_hides_row', aidunite_market_viewer_hides_established_recruit_row($viewer_team, $recruit_sid));
}
$dn = function_exists('aidunite_schedule_read_normalized_date')
    ? aidunite_schedule_read_normalized_date($recruit_sid)
    : '';
if (function_exists('aidunite_team_has_guest_committed_schedule_on_date')) {
    $out('viewer_guest_on_date', aidunite_team_has_guest_committed_schedule_on_date($viewer_team, $dn));
    $rt = (int) get_post_meta($recruit_sid, 'team_id', true);
    $out('recruit_team_guest_on_date', aidunite_team_has_guest_committed_schedule_on_date($rt, $dn));
}

if (function_exists('aidunite_count_established_for_schedule')) {
    $out('established_count_on_recruit', aidunite_count_established_for_schedule($recruit_sid));
}
if (function_exists('aidunite_get_remaining_gender_slots')) {
    $rem = aidunite_get_remaining_gender_slots($recruit_sid);
    $out('remaining_slots', wp_json_encode($rem));
}
if (function_exists('aidunite_market_recruitment_passes_board_filters')) {
    $out('passes_board_filters', aidunite_market_recruitment_passes_board_filters($recruit_sid));
}

if (function_exists('aidunite_market_get_all_recruitments') && $viewer_team > 0) {
    $range = function_exists('aidunite_market_board_default_date_range')
        ? aidunite_market_board_default_date_range()
        : ['from' => date('Y-m-d', strtotime('+1 day')), 'to' => date('Y-m-d', strtotime('+120 days'))];
    $all = aidunite_market_get_all_recruitments($range['from'], $range['to'], $viewer_team, ['gender' => '']);
    $ids = array_map(static fn ($p) => (int) $p->ID, $all);
    $out('in_recruit_list', in_array($recruit_sid, $ids, true));
    $out('list_count', count($ids));
}
