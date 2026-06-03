<?php
/**
 * スケジュール persist スモーク用メタ診断（Local 開発向け）。
 * 例: /wp-content/themes/aidunite-original/tools/schedule-persist-diagnose.php?schedule_id=6987
 * 本番では削除またはアクセス制限すること。
 */

$wp_load = dirname(__DIR__, 4) . '/wp-load.php';
if (!is_readable($wp_load)) {
    header('Content-Type: text/plain; charset=utf-8');
    echo "wp-load.php not found: {$wp_load}\n";
    exit(1);
}

require_once $wp_load;

if (!is_user_logged_in() && php_sapi_name() !== 'cli') {
    wp_die('ログインが必要です。');
}

$schedule_id = isset($_GET['schedule_id']) ? (int) $_GET['schedule_id'] : 0;
if ($schedule_id <= 0) {
    wp_die('schedule_id クエリを指定してください。例: ?schedule_id=6987');
}

$lines = [];
$lines[] = '=== schedule persist diagnose ===';
$lines[] = 'schedule_id=' . $schedule_id;
$lines[] = 'post_status=' . get_post_status($schedule_id);
$lines[] = 'post_type=' . get_post_type($schedule_id);

$keys = [
    'team_id',
    'schedule_date',
    'schedule_start_time',
    'schedule_end_time',
    'schedule_type',
    'intent',
    'certainty',
    'matching',
    'is_match_requested',
    'schedule_gender',
    'matching_gender_condition',
    'schedule_place',
    'schedule_place_option',
    'venue_name',
    'male_slots',
    'female_slots',
    'male_teams',
    'female_teams',
    'capacity',
    'attendance_required',
    'is_personal',
    'schedule_quick_memo',
];

foreach ($keys as $key) {
    $lines[] = $key . '=' . var_export(get_post_meta($schedule_id, $key, true), true);
}

$intent = (string) get_post_meta($schedule_id, 'intent', true);
$matching = get_post_meta($schedule_id, 'matching', true);
$is_mr = get_post_meta($schedule_id, 'is_match_requested', true);
$gender = (string) (get_post_meta($schedule_id, 'schedule_gender', true)
    ?: get_post_meta($schedule_id, 'matching_gender_condition', true));
$gender_canon = function_exists('aidunite_normalize_gender_canonical')
    ? aidunite_normalize_gender_canonical($gender)
    : $gender;

$lines[] = '--- checks (recruit) ---';

if ($intent === 'recruit') {
    $lines[] = 'check_matching_on=' . (in_array($matching, [1, '1', true], true) ? 'OK' : 'NG');
    $lines[] = 'check_is_match_requested=' . (in_array($is_mr, [1, '1', true], true) ? 'OK' : 'NG');
    $lines[] = 'check_gender_not_both=' . ($gender_canon !== 'both' ? 'OK' : 'NG');
    $male = (int) get_post_meta($schedule_id, 'male_slots', true);
    $female = (int) get_post_meta($schedule_id, 'female_slots', true);
    if ($gender_canon === 'male') {
        $lines[] = 'check_male_slots=' . ($male >= 1 ? 'OK' : 'NG') . " (male_slots={$male})";
    } elseif ($gender_canon === 'female') {
        $lines[] = 'check_female_slots=' . ($female >= 1 ? 'OK' : 'NG') . " (female_slots={$female})";
    }
    if (function_exists('aidunite_recruit_open_for_market_board')) {
        $lines[] = 'recruit_open_for_board=' . (aidunite_recruit_open_for_market_board($schedule_id) ? 'yes' : 'no');
    }
    if (function_exists('aidunite_recruit_open_for_market_board_reason')) {
        $r = aidunite_recruit_open_for_market_board_reason($schedule_id);
        $lines[] = 'recruit_open_reason=' . (string) ($r['reason'] ?? '');
    }
} else {
    $lines[] = 'intent is not recruit — recruit checks skipped';
}

$lines[] = '--- persist path ---';
$lines[] = 'schedule-persist.php=' . (function_exists('aidunite_schedule_write_post_meta') ? 'loaded' : 'MISSING');
$lines[] = 'normalize_form_input=' . (function_exists('aidunite_schedule_normalize_form_input') ? 'loaded' : 'MISSING');
$lines[] = 'persist-read=' . (function_exists('aidunite_schedule_get_canonical_meta') ? 'loaded' : 'MISSING');

if (function_exists('aidunite_schedule_get_canonical_meta')) {
    $canonical = aidunite_schedule_get_canonical_meta($schedule_id);
    $lines[] = '--- canonical read ---';
    $lines[] = 'canonical_intent=' . ($canonical['intent'] ?? '');
    $lines[] = 'canonical_gender=' . ($canonical['gender_condition'] ?? '');
    $lines[] = 'canonical_place=' . ($canonical['schedule_place'] ?? '');
    $lines[] = 'match_board_id=' . (int) ($canonical['match_board_id'] ?? 0);
    $lines[] = 'match_board_status=' . ($canonical['match_board_status'] ?? '');
}

if (function_exists('aidunite_schedule_get_linked_match_request_ids')) {
    $mr_ids = aidunite_schedule_get_linked_match_request_ids($schedule_id);
    $lines[] = 'linked_match_request_ids=' . (empty($mr_ids) ? '(none)' : implode(',', $mr_ids));
}

$lines[] = '--- Phase 4 legacy ---';
$lines[] = 'legacy_meta_writes_enabled=' . (function_exists('aidunite_schedule_legacy_meta_writes_enabled')
    && aidunite_schedule_legacy_meta_writes_enabled() ? 'yes' : 'no');
if (function_exists('aidunite_schedule_get_redundant_legacy_meta_keys')) {
    $redundant = aidunite_schedule_get_redundant_legacy_meta_keys($schedule_id);
    $lines[] = 'redundant_legacy_keys=' . (empty($redundant) ? '(none)' : implode(',', $redundant));
}

header('Content-Type: text/plain; charset=utf-8');
echo implode("\n", $lines) . "\n";
