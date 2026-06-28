<?php
$wp_load = dirname(__DIR__, 4) . '/wp-load.php';
require_once $wp_load;
require_once dirname(__DIR__) . '/functions/match/match-board-page-helpers.php';

$viewer = 5680;
$my_sid = 6979;
$other_sid = 6889;
$mr_id = 7347;

header('Content-Type: text/plain; charset=utf-8');
$mr = get_post($mr_id);
echo "status meta=" . get_post_meta($mr_id, 'status', true) . "\n";
if (function_exists('aidunite_match_request_get_canonical_meta')) {
    echo "canonical=" . wp_json_encode(aidunite_match_request_get_canonical_meta($mr_id)) . "\n";
}
$latest = null;
$mr_pick = $mr;
$pair_ok = true;
echo "pair_ok=yes\n";
$st = get_post_meta($mr_id, 'status', true);
$norm = function_exists('aidunite_normalize_match_request_status')
    ? aidunite_normalize_match_request_status($st, (string) $mr->post_status)
    : $st;
echo "norm_status={$norm}\n";
$app = function_exists('aidunite_get_application_status')
    ? aidunite_get_application_status($viewer, $my_sid, (int) get_post_meta($other_sid, 'team_id', true), $other_sid)
    : [];
echo "board_status=" . wp_json_encode($app) . "\n";
