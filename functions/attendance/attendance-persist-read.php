<?php
/**
 * 出欠 attendance_data 読取正本
 *
 * attendance_required は schedule-persist-read が正本。
 *
 * @see docs/spec/schedule.md（出欠・attendance_data）
 * @package AidUnite
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 出欠確認が必要なスケジュールか
 *
 * @param int $schedule_id
 * @return bool
 */
function aidunite_attendance_read_is_required($schedule_id) {
    $schedule_id = (int) $schedule_id;
    if ($schedule_id <= 0) {
        return false;
    }

    if (function_exists('aidunite_schedule_get_canonical_meta')) {
        $meta = aidunite_schedule_get_canonical_meta($schedule_id);

        return ($meta['attendance_required'] ?? '0') === '1';
    }

    return get_post_meta($schedule_id, 'attendance_required', true) === '1';
}

/**
 * @param int $schedule_id
 * @return array<string, mixed>
 */
function aidunite_attendance_get_canonical_data($schedule_id) {
    $schedule_id = (int) $schedule_id;
    if ($schedule_id <= 0 || get_post_type($schedule_id) !== 'schedule') {
        return [];
    }

    $raw = get_post_meta($schedule_id, 'attendance_data', true);
    if (!is_array($raw)) {
        $raw = [];
    }

    $attendance_required = '0';
    if (function_exists('aidunite_schedule_get_canonical_meta')) {
        $sch = aidunite_schedule_get_canonical_meta($schedule_id);
        $attendance_required = (string) ($sch['attendance_required'] ?? '0');
    }

    return [
        'schedule_id' => $schedule_id,
        'attendance_required' => $attendance_required,
        'attendance_data' => aidunite_attendance_normalize_data_array($raw),
        'attendance_data_raw' => $raw,
        'respondent_count' => count($raw),
    ];
}

/**
 * page 表示用 attendance_data 配列（生データ）
 *
 * @param int $schedule_id
 * @return array<int|string, mixed>
 */
function aidunite_attendance_read_data_map($schedule_id) {
    $canonical = aidunite_attendance_get_canonical_data((int) $schedule_id);
    $raw = $canonical['attendance_data_raw'] ?? [];

    return is_array($raw) ? $raw : [];
}

/**
 * 1ユーザーの出欠行（未回答は null）
 *
 * @param int $schedule_id
 * @param int $user_id
 * @return array<string, mixed>|null
 */
function aidunite_attendance_read_user_row($schedule_id, $user_id) {
    $schedule_id = (int) $schedule_id;
    $user_id = (int) $user_id;
    if ($schedule_id <= 0 || $user_id <= 0) {
        return null;
    }

    $data = aidunite_attendance_read_data_map($schedule_id);
    $row = $data[$user_id] ?? null;

    return is_array($row) ? $row : null;
}

/**
 * 出欠確認対象スケジュールを WP_Query 経由で取得（attendance_required=1 固定）
 *
 * @param array<string, mixed> $args {
 *   @type int         $team_id         0 なら全チーム
 *   @type string|null $date_from       Y-m-d（>=）
 *   @type string|null $date_to         Y-m-d（<=）
 *   @type string      $date_eq         Y-m-d（=）
 *   @type string      $date_before     Y-m-d（<）
 *   @type array       $date_between    [from, to] BETWEEN
 *   @type int         $posts_per_page  デフォルト -1
 *   @type string      $fields          'all'|'ids'
 *   @type string      $order           ASC|DESC
 * }
 * @return WP_Post[]|int[]
 */
function aidunite_attendance_read_schedules(array $args = []) {
    $team_id = isset($args['team_id']) ? (int) $args['team_id'] : 0;
    $date_from = array_key_exists('date_from', $args) ? $args['date_from'] : null;
    $date_to = array_key_exists('date_to', $args) ? $args['date_to'] : null;
    $date_eq = isset($args['date_eq']) ? trim((string) $args['date_eq']) : '';
    $date_before = isset($args['date_before']) ? trim((string) $args['date_before']) : '';
    $date_between = isset($args['date_between']) && is_array($args['date_between']) ? $args['date_between'] : null;
    $posts_per_page = isset($args['posts_per_page']) ? (int) $args['posts_per_page'] : -1;
    $fields = isset($args['fields']) ? (string) $args['fields'] : 'all';
    $order = isset($args['order']) ? strtoupper((string) $args['order']) : 'ASC';
    if (!in_array($order, ['ASC', 'DESC'], true)) {
        $order = 'ASC';
    }

    $meta_query = [
        'relation' => 'AND',
        [
            'key' => 'attendance_required',
            'value' => '1',
            'compare' => '=',
        ],
    ];

    if ($team_id > 0) {
        $meta_query[] = [
            'key' => 'team_id',
            'value' => (string) $team_id,
            'compare' => '=',
        ];
    }

    if ($date_eq !== '') {
        $meta_query[] = [
            'key' => 'schedule_date',
            'value' => $date_eq,
            'compare' => '=',
            'type' => 'DATE',
        ];
    } elseif (is_array($date_between) && count($date_between) >= 2) {
        $meta_query[] = [
            'key' => 'schedule_date',
            'value' => [ (string) $date_between[0], (string) $date_between[1] ],
            'compare' => 'BETWEEN',
            'type' => 'DATE',
        ];
    } else {
        if ($date_before !== '') {
            $meta_query[] = [
                'key' => 'schedule_date',
                'value' => $date_before,
                'compare' => '<',
                'type' => 'DATE',
            ];
        } else {
            if ($date_from !== null && $date_from !== '') {
                $meta_query[] = [
                    'key' => 'schedule_date',
                    'value' => (string) $date_from,
                    'compare' => '>=',
                    'type' => 'DATE',
                ];
            }
            if ($date_to !== null && $date_to !== '') {
                $meta_query[] = [
                    'key' => 'schedule_date',
                    'value' => (string) $date_to,
                    'compare' => '<=',
                    'type' => 'DATE',
                ];
            }
        }
    }

    $query_args = [
        'post_type' => 'schedule',
        'post_status' => 'publish',
        'posts_per_page' => $posts_per_page > 0 ? $posts_per_page : -1,
        'meta_query' => $meta_query,
        'orderby' => 'meta_value',
        'meta_key' => 'schedule_date',
        'order' => $order,
    ];

    if ($fields === 'ids') {
        $query_args['fields'] = 'ids';
    }

    return get_posts($query_args);
}

/**
 * 出欠 UI 用スケジュール行（display bundle 経由）
 *
 * @param WP_Post|int $schedule
 * @return array<string, mixed>
 */
function aidunite_attendance_read_schedule_list_row($schedule) {
    $schedule_id = (int) (is_object($schedule) ? $schedule->ID : $schedule);
    $title = is_object($schedule) ? (string) $schedule->post_title : (string) get_the_title($schedule_id);

    $sch_disp = function_exists('aidunite_schedule_get_display_bundle')
        ? aidunite_schedule_get_display_bundle($schedule_id)
        : [];

    return [
        'id' => $schedule_id,
        'title' => $title,
        'date' => (string) ($sch_disp['date'] ?? (function_exists('aidunite_schedule_read_normalized_date')
            ? aidunite_schedule_read_normalized_date($schedule_id)
            : '')),
        'start_time' => (string) ($sch_disp['start_time'] ?? ''),
        'end_time' => (string) ($sch_disp['end_time'] ?? ''),
        'place' => (string) ($sch_disp['place'] ?? (function_exists('aidunite_schedule_read_place_raw')
            ? aidunite_schedule_read_place_raw($schedule_id)
            : '')),
        'type' => (string) ($sch_disp['schedule_type'] ?? ''),
        'team_id' => (int) ($sch_disp['team_id'] ?? (function_exists('aidunite_schedule_read_team_id')
            ? aidunite_schedule_read_team_id($schedule_id)
            : 0)),
    ];
}
