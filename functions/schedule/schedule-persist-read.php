<?php
/**
 * スケジュールメタの読取正規化（表示・REST・管理画面用）
 *
 * 書き込み正本は schedule-persist.php（Phase 4: 旧キーは読取フォールバックのみ）。
 *
 * @package AidUnite
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * schedule 投稿の表示用メタを1本化して返す（旧キーはフォールバックのみ）
 *
 * @param int $schedule_id
 * @return array<string, mixed>
 */
function aidunite_schedule_get_canonical_meta($schedule_id) {
    $schedule_id = (int) $schedule_id;
    if ($schedule_id < 1 || get_post_type($schedule_id) !== 'schedule') {
        return [];
    }

    $intent = (string) get_post_meta($schedule_id, 'intent', true);
    if ($intent === '' && function_exists('aidunite_normalize_schedule_intent')) {
        $matching = get_post_meta($schedule_id, 'matching', true);
        $certainty = (string) get_post_meta($schedule_id, 'certainty', true);
        $intent = aidunite_normalize_schedule_intent(
            in_array($matching, [1, '1', true], true) ? 'recruit' : '',
            $certainty
        );
    }

    $place = (string) (get_post_meta($schedule_id, 'schedule_place', true)
        ?: get_post_meta($schedule_id, 'schedule_place_option', true));
    $gender = (string) (get_post_meta($schedule_id, 'schedule_gender', true)
        ?: get_post_meta($schedule_id, 'matching_gender_condition', true)
        ?: get_post_meta($schedule_id, 'gender_condition', true));
    if ($gender !== '' && function_exists('aidunite_normalize_gender_canonical')) {
        $canonical = aidunite_normalize_gender_canonical($gender);
        if ($canonical !== '') {
            $gender = $canonical;
        }
    }

    $memo = (string) (get_post_meta($schedule_id, 'schedule_quick_memo', true)
        ?: get_post_meta($schedule_id, 'schedule_note', true));

    $male_slots = (int) get_post_meta($schedule_id, 'male_slots', true);
    if ($male_slots < 1) {
        $male_slots = (int) (get_post_meta($schedule_id, 'male_teams', true)
            ?: get_post_meta($schedule_id, 'male_capacity', true));
    }
    $female_slots = (int) get_post_meta($schedule_id, 'female_slots', true);
    if ($female_slots < 1) {
        $female_slots = (int) (get_post_meta($schedule_id, 'female_teams', true)
            ?: get_post_meta($schedule_id, 'female_capacity', true));
    }

    $board_id = function_exists('aidunite_schedule_find_match_board_id_for_schedule')
        ? aidunite_schedule_find_match_board_id_for_schedule($schedule_id)
        : 0;

    return [
        'schedule_id' => $schedule_id,
        'team_id' => (int) get_post_meta($schedule_id, 'team_id', true),
        'date' => (string) get_post_meta($schedule_id, 'schedule_date', true),
        'start_time' => (string) get_post_meta($schedule_id, 'schedule_start_time', true),
        'end_time' => (string) get_post_meta($schedule_id, 'schedule_end_time', true),
        'schedule_type' => (string) get_post_meta($schedule_id, 'schedule_type', true),
        'intent' => $intent,
        'certainty' => (string) get_post_meta($schedule_id, 'certainty', true),
        'venue_condition' => $place,
        'schedule_place' => $place,
        'venue_name' => (string) get_post_meta($schedule_id, 'venue_name', true),
        'gender_condition' => $gender,
        'schedule_gender' => $gender,
        'male_slots' => $male_slots,
        'female_slots' => $female_slots,
        'matching' => in_array(get_post_meta($schedule_id, 'matching', true), [1, '1', true], true) ? '1' : '0',
        'is_match_requested' => in_array(get_post_meta($schedule_id, 'is_match_requested', true), [1, '1', true], true) ? '1' : '0',
        'schedule_quick_memo' => $memo,
        'match_board_id' => $board_id,
        'match_board_status' => $board_id > 0 && function_exists('aidunite_match_board_get_canonical_meta')
            ? (string) (aidunite_match_board_get_canonical_meta($board_id)['board_status'] ?? '')
            : ($board_id > 0 ? (string) get_post_meta($board_id, 'match_board_status', true) : ''),
    ];
}

/**
 * 正本メタと併存している旧キー一覧（移行・診断用）
 *
 * @return string[]
 */
function aidunite_schedule_get_redundant_legacy_meta_keys($schedule_id) {
    $schedule_id = (int) $schedule_id;
    if ($schedule_id < 1) {
        return [];
    }

    $redundant = [];
    if ((string) get_post_meta($schedule_id, 'schedule_gender', true) !== ''
        && (string) get_post_meta($schedule_id, 'matching_gender_condition', true) !== '') {
        $redundant[] = 'matching_gender_condition';
    }
    if ((string) get_post_meta($schedule_id, 'schedule_place', true) !== ''
        && (string) get_post_meta($schedule_id, 'schedule_place_option', true) !== '') {
        $redundant[] = 'schedule_place_option';
    }
    if ((string) get_post_meta($schedule_id, 'male_slots', true) !== ''
        && (string) get_post_meta($schedule_id, 'male_teams', true) !== '') {
        $redundant[] = 'male_teams';
    }
    if ((string) get_post_meta($schedule_id, 'female_slots', true) !== ''
        && (string) get_post_meta($schedule_id, 'female_teams', true) !== '') {
        $redundant[] = 'female_teams';
    }
    if ((string) get_post_meta($schedule_id, 'schedule_quick_memo', true) !== ''
        && (string) get_post_meta($schedule_id, 'schedule_note', true) !== '') {
        $redundant[] = 'schedule_note';
    }

    return $redundant;
}

/**
 * 募集 schedule に紐づく match_request ID 一覧（to_schedule_id 基準）
 *
 * @return int[]
 */
function aidunite_schedule_get_linked_match_request_ids($schedule_id) {
    $schedule_id = (int) $schedule_id;
    if ($schedule_id < 1) {
        return [];
    }

    $ids = get_posts([
        'post_type'      => 'match_request',
        'post_status'    => 'any',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'meta_query'     => [
            [
                'key'     => 'to_schedule_id',
                'value'   => (string) $schedule_id,
                'compare' => '=',
            ],
        ],
    ]);

    return array_map('intval', is_array($ids) ? $ids : []);
}
