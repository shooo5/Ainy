<?php
/**
 * 選手機能管理
 * AidUnite統一仕様対応版
 */

/**
 * 選手情報取得（統一命名）
 *
 * @param int $player_id
 * @return array<string, string>|null
 */
function aidunite_get_player_info($player_id) {
    $player_id = (int) $player_id;
    if ($player_id <= 0) {
        return null;
    }

    $user = get_userdata($player_id);
    if (!$user) {
        return null;
    }

    $edit = function_exists('aidunite_player_get_edit_display')
        ? aidunite_player_get_edit_display($player_id)
        : [];

    $player_info = [
        'name' => $user->display_name,
        'age' => '',
        'height' => '',
        'position' => '',
    ];

    if ($edit === []) {
        return $player_info;
    }

    $birth = (string) ($edit['player_birth_date'] ?? '');
    if ($birth !== '' && function_exists('calculate_age')) {
        $player_info['age'] = (string) calculate_age($birth);
    }

    $height = (string) ($edit['player_height'] ?? '');
    if ($height !== '') {
        $player_info['height'] = $height;
    }

    $position = (string) ($edit['player_position'] ?? '');
    if ($position !== '') {
        $player_info['position'] = $position;
    }

    return $player_info;
}

/**
 * 選手のスケジュール取得（統一命名）
 *
 * @param int                  $player_id
 * @param array<string, string> $date_range
 * @return array<int, array<string, mixed>>
 */
function aidunite_get_player_schedules($player_id, $date_range = []) {
    $player_id = (int) $player_id;
    if ($player_id <= 0) {
        return [];
    }

    $team_id = (int) aidunite_player_read_team_id($player_id);
    if ($team_id <= 0) {
        return [];
    }

    $args = [
        'post_type' => 'schedule',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'meta_query' => [
            [
                'key' => 'team_id',
                'value' => $team_id,
            ],
        ],
    ];

    if (!empty($date_range['start']) && !empty($date_range['end'])) {
        $args['meta_query'][] = [
            'key' => 'schedule_date',
            'value' => [$date_range['start'], $date_range['end']],
            'compare' => 'BETWEEN',
            'type' => 'DATE',
        ];
    }

    $schedules = get_posts($args);
    $schedule_data = [];

    foreach ($schedules as $schedule) {
        $sch_pl = function_exists('aidunite_schedule_get_display_bundle')
            ? aidunite_schedule_get_display_bundle((int) $schedule->ID)
            : [];
        $start = (string) ($sch_pl['start_time'] ?? '');
        $end = (string) ($sch_pl['end_time'] ?? '');
        $schedule_data[] = [
            'id' => $schedule->ID,
            'title' => $schedule->post_title,
            'date' => (string) ($sch_pl['date'] ?? ''),
            'time' => ($start !== '' || $end !== '') ? trim($start . ' - ' . $end) : (string) ($sch_pl['legacy_time'] ?? ''),
            'place' => (string) ($sch_pl['place'] ?? ''),
            'type' => (string) ($sch_pl['schedule_type'] ?? ''),
        ];
    }

    usort($schedule_data, static function ($a, $b) {
        return strtotime((string) ($a['date'] ?? '')) - strtotime((string) ($b['date'] ?? ''));
    });

    return $schedule_data;
}

/**
 * 選手の総活動数を取得（統一命名）
 *
 * @param int $player_id
 * @return int
 */
function aidunite_get_player_total_events($player_id) {
    $player_id = (int) $player_id;
    if ($player_id <= 0) {
        return 0;
    }

    $team_id = (int) aidunite_player_read_team_id($player_id);
    if ($team_id <= 0) {
        return 0;
    }

    $total_schedules = get_posts([
        'post_type' => 'schedule',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'meta_query' => [
            [
                'key' => 'team_id',
                'value' => $team_id,
                'compare' => '=',
            ],
        ],
    ]);

    return count($total_schedules);
}

/**
 * 選手の試合数を取得（統一命名）
 *
 * @param int $player_id
 * @return int
 */
function aidunite_get_player_match_count($player_id) {
    $player_id = (int) $player_id;
    if ($player_id <= 0) {
        return 0;
    }

    $team_id = (int) aidunite_player_read_team_id($player_id);
    if ($team_id <= 0) {
        return 0;
    }

    $matches = get_posts([
        'post_type' => 'schedule',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'meta_query' => [
            [
                'key' => 'team_id',
                'value' => $team_id,
                'compare' => '=',
            ],
            [
                'key' => 'schedule_type',
                'value' => 'match',
                'compare' => '=',
            ],
        ],
    ]);

    return count($matches);
}

/**
 * 選手の出席率を取得（統一命名）
 *
 * @param int $player_id
 * @return int|float
 */
function aidunite_get_player_attendance_rate($player_id) {
    $player_id = (int) $player_id;
    if ($player_id <= 0) {
        return 0;
    }

    if (function_exists('aidunite_attendance_calculate_presence_rate')) {
        return aidunite_attendance_calculate_presence_rate($player_id);
    }

    return 0;
}
