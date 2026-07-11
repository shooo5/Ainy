<?php
/**
 * 募集中一覧の診断（ブラウザまたは CLI）。
 * 例: /wp-content/themes/aidunite-original/tools/market-board-diagnose.php?team_id=5661&schedule_id=5702
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

$team_id     = isset($_GET['team_id']) ? (int) $_GET['team_id'] : 5661;
$schedule_id = isset($_GET['schedule_id']) ? (int) $_GET['schedule_id'] : 5702;
$default_range = function_exists('aidunite_market_board_default_date_range')
    ? aidunite_market_board_default_date_range()
    : ['from' => date('Y-m-d', strtotime('+1 day')), 'to' => date('Y-m-d', strtotime('+120 days'))];
$date_from   = isset($_GET['date_from']) ? sanitize_text_field(wp_unslash($_GET['date_from'])) : $default_range['from'];
$date_to     = isset($_GET['date_to']) ? sanitize_text_field(wp_unslash($_GET['date_to'])) : $default_range['to'];

if (function_exists('aidunite_match_board_ensure_dependencies')) {
    aidunite_match_board_ensure_dependencies();
}

$lines = [];
$lines[] = 'theme_stylesheet=' . get_stylesheet();
$lines[] = 'theme_template=' . get_template();
$lines[] = 'recruit_accepts_fn=' . (function_exists('aidunite_recruit_accepts_match_applications') ? 'yes' : 'NO');
$lines[] = 'recruit_open_fn=' . (function_exists('aidunite_recruit_open_for_market_board') ? 'yes' : 'NO');
$lines[] = 'board_tier_fn=' . (function_exists('aidunite_match_board_tier_label_from_evaluation') ? 'yes' : 'NO');
$lines[] = 'gender_canon_fn=' . (function_exists('aidunite_market_recruitment_gender_canonical') ? 'yes' : 'NO');
$lines[] = 'viewer_team_id_arg=' . $team_id;
$lines[] = 'date_from=' . $date_from . ' date_to=' . $date_to;

if ($schedule_id > 0) {
    $lines[] = '--- schedule ' . $schedule_id . ' ---';
    $lines[] = 'post_status=' . get_post_status($schedule_id);
    $lines[] = 'team_id_meta=' . get_post_meta($schedule_id, 'team_id', true);
    $lines[] = 'schedule_date=' . (function_exists('aidunite_schedule_read_normalized_date')
        ? aidunite_schedule_read_normalized_date($schedule_id)
        : get_post_meta($schedule_id, 'schedule_date', true));
    $lines[] = 'intent=' . (function_exists('aidunite_schedule_read_intent')
        ? aidunite_schedule_read_intent($schedule_id)
        : get_post_meta($schedule_id, 'intent', true));
    $lines[] = 'matching=' . get_post_meta($schedule_id, 'matching', true);
    $lines[] = 'is_match_requested=' . get_post_meta($schedule_id, 'is_match_requested', true);
    $lines[] = 'schedule_gender=' . (function_exists('aidunite_schedule_read_gender_raw')
        ? aidunite_schedule_read_gender_raw($schedule_id)
        : get_post_meta($schedule_id, 'schedule_gender', true));
    $lines[] = 'male_slots=' . get_post_meta($schedule_id, 'male_slots', true);
    if (function_exists('aidunite_market_recruitment_gender_canonical')) {
        $lines[] = 'gender_canon=' . aidunite_market_recruitment_gender_canonical($schedule_id);
    }
    if (function_exists('aidunite_recruit_accepts_match_applications')) {
        $lines[] = 'recruit_accepts=' . (aidunite_recruit_accepts_match_applications($schedule_id) ? 'true' : 'FALSE');
    }
    if (function_exists('aidunite_recruit_open_for_market_board')) {
        $lines[] = 'recruit_open=' . (aidunite_recruit_open_for_market_board($schedule_id) ? 'true' : 'FALSE');
    }
    if (function_exists('aidunite_market_schedule_in_recruitment_mother_set')) {
        $lines[] = 'in_mother_set=' . (aidunite_market_schedule_in_recruitment_mother_set($schedule_id) ? 'true' : 'FALSE');
    }
    if (function_exists('aidunite_get_remaining_gender_slots')) {
        $rem = aidunite_get_remaining_gender_slots($schedule_id);
        $lines[] = 'remaining_male=' . ($rem['male'] ?? '?') . ' remaining_female=' . ($rem['female'] ?? '?');
    }
    if (function_exists('aidunite_market_recruitment_passes_board_filters')) {
        $lines[] = 'passes_board_filters=' . (aidunite_market_recruitment_passes_board_filters($schedule_id) ? 'true' : 'FALSE');
    }
    $same_team = ((int) get_post_meta($schedule_id, 'team_id', true) === $team_id);
    $lines[] = 'excluded_as_own_team=' . ($same_team ? 'YES' : 'no');
}

if (function_exists('aidunite_market_get_all_recruitments')) {
    $all = aidunite_market_get_all_recruitments($date_from, $date_to, $team_id, ['gender' => '']);
    $ids = array_map(static function ($p) {
        return (int) $p->ID;
    }, $all);
    $lines[] = '--- aidunite_market_get_all_recruitments count=' . count($all) . ' ---';
    $lines[] = 'ids=' . implode(',', $ids);
    $lines[] = 'contains_' . $schedule_id . '=' . (in_array($schedule_id, $ids, true) ? 'YES' : 'NO');
}

if (function_exists('aidunite_market_get_row_state') && $schedule_id > 0) {
    $post = get_post($schedule_id);
    if ($post) {
        $my = function_exists('aidunite_market_get_my_matching_schedules')
            ? aidunite_market_get_my_matching_schedules($team_id)
            : [];
        $st = aidunite_market_get_row_state($post, $my, $team_id);
        $lines[] = '--- row_state ---';
        $lines[] = 'state=' . ($st['state'] ?? '?');
        $lines[] = 'best_my_schedule_id=' . (int) ($st['best_my_schedule_id'] ?? 0);
        $ev = $st['best_apply_eval'] ?? null;
        if (is_array($ev)) {
            $lines[] = 'eval_list_row=' . (string) ($ev['list_row'] ?? '');
            $lines[] = 'eval_reason_code=' . (string) ($ev['reason_code'] ?? '');
            $lines[] = 'eval_variant=' . (string) ($ev['variant'] ?? '');
            $lines[] = 'board_tier=' . (string) ($ev['board_tier'] ?? '');
            if (is_array($ev['scores'] ?? null)) {
                $lines[] = 'scores=' . wp_json_encode($ev['scores'], JSON_UNESCAPED_UNICODE);
            }
        }
        if ($best_my = (int) ($st['best_my_schedule_id'] ?? 0)) {
            if (function_exists('aidunite_evaluate_match_apply_context')) {
                $ev2 = aidunite_evaluate_match_apply_context($schedule_id, $best_my, ['mode' => 'preview']);
                $lines[] = 'eval_direct list_row=' . (string) ($ev2['list_row'] ?? '') . ' reason=' . (string) ($ev2['reason_code'] ?? '');
            }
        }
        if (function_exists('aidunite_get_active_chat_room_for_match_game')) {
            $active_room = aidunite_get_active_chat_room_for_match_game($schedule_id);
            $lines[] = 'match_game_active_room_id=' . ($active_room && !empty($active_room->id) ? (int) $active_room->id : 0);
        }
    }
}

if (function_exists('aidunite_get_current_team_id')) {
    $uid = get_current_user_id();
    $lines[] = '--- current user ' . $uid . ' ---';
    $lines[] = 'aidunite_get_current_team_id=' . aidunite_get_current_team_id($uid);
    if (function_exists('aidunite_get_all_stored_operating_team_ids')) {
        $lines[] = 'stored_operating=' . implode(',', aidunite_get_all_stored_operating_team_ids($uid));
    }
}

header('Content-Type: text/plain; charset=utf-8');
echo implode("\n", $lines) . "\n";
