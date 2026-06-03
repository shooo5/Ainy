<?php
/**
 * 選手機能管理
 * AidUnite統一仕様対応版
 */

/**
 * 選手ダッシュボード用データ取得（統一命名）
 */
function aidunite_get_player_dashboard_data($player_id) {
    if (!$player_id) {
        return [];
    }

    $dashboard_data = [
        'player_info' => [],
        'team_info' => null,
        'upcoming_schedules' => [],
        'recent_attendance' => []
    ];

    // 選手情報を取得
    $player_info = aidunite_get_player_info($player_id);
    if ($player_info) {
        $dashboard_data['player_info'] = $player_info;
    }

    // チーム情報を取得
    $team_id = (function_exists('aidunite_get_current_team_id') ? aidunite_get_current_team_id((int) $player_id) : get_user_meta($player_id, 'team_id', true));
    if ($team_id) {
        $dashboard_data['team_info'] = aidunite_get_team_info($team_id);
    }

    // 今月のスケジュールを取得
    $current_month = date('Y-m');
    $date_range = [
        'start' => $current_month . '-01',
        'end' => $current_month . '-31'
    ];

    $player_schedules = aidunite_get_player_schedules($player_id, $date_range);
    $dashboard_data['upcoming_schedules'] = $player_schedules;

    // 最近の出欠回答を取得
    $recent_attendance = get_posts([
        'post_type' => 'attendance',
        'post_status' => 'publish',
        'posts_per_page' => 5,
        'author' => $player_id,
        'orderby' => 'date',
        'order' => 'DESC'
    ]);

    foreach ($recent_attendance as $attendance) {
        $schedule_id = get_post_meta($attendance->ID, 'schedule_id', true);
        $schedule = get_post($schedule_id);

        $dashboard_data['recent_attendance'][] = [
            'attendance_id' => $attendance->ID,
            'schedule_title' => $schedule ? $schedule->post_title : '',
            'schedule_date' => get_post_meta($schedule_id, 'schedule_date', true),
            'status' => get_post_meta($attendance->ID, 'attendance_status', true),
            'submitted_date' => get_post_meta($attendance->ID, 'attendance_date', true)
        ];
    }

    return $dashboard_data;
}

/**
 * 後方互換性のためのラッパー関数（段階的削除予定）
 * @deprecated 代わりに aidunite_get_player_dashboard_data() を使用してください
 */
function tunageru_get_player_dashboard_data($player_id) {
    return aidunite_get_player_dashboard_data($player_id);
}

/**
 * 選手情報取得（統一命名）
 */
function aidunite_get_player_info($player_id) {
    if (!$player_id) {
        return null;
    }

    $user = get_userdata($player_id);
    if (!$user) {
        return null;
    }

    // 選手投稿から情報を取得
    $player_posts = get_posts([
        'post_type' => 'player',
        'meta_key' => 'player_user_id',
        'meta_value' => $player_id,
        'posts_per_page' => 1
    ]);

    $player_info = [
        'name' => $user->display_name,
        'age' => '',
        'height' => '',
        'position' => ''
    ];

    if (!empty($player_posts)) {
        $player_post = $player_posts[0];
        $birth_date = get_post_meta($player_post->ID, 'player_birth_date', true);
        $height = get_post_meta($player_post->ID, 'player_height', true);
        $position = get_post_meta($player_post->ID, 'player_position', true);

        if ($birth_date) {
            $player_info['age'] = calculate_age($birth_date);
        }
        if ($height) {
            $player_info['height'] = $height;
        }
        if ($position) {
            $player_info['position'] = $position;
        }
    }

    return $player_info;
}

/**
 * 選手のスケジュール取得（統一命名）
 */
function aidunite_get_player_schedules($player_id, $date_range = []) {
    if (!$player_id) {
        return [];
    }

    $team_id = (function_exists('aidunite_get_current_team_id') ? aidunite_get_current_team_id((int) $player_id) : get_user_meta($player_id, 'team_id', true));
    if (!$team_id) {
        return [];
    }

    $args = [
        'post_type' => 'schedule',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'meta_query' => [
            [
                'key' => 'team_id',
                'value' => $team_id
            ]
        ]
    ];

    // 日付範囲が指定されている場合
    if (!empty($date_range['start']) && !empty($date_range['end'])) {
        $args['meta_query'][] = [
            'key' => 'schedule_date',
            'value' => [$date_range['start'], $date_range['end']],
            'compare' => 'BETWEEN',
            'type' => 'DATE'
        ];
    }

    $schedules = get_posts($args);
    $schedule_data = [];

    foreach ($schedules as $schedule) {
        $schedule_data[] = [
            'id' => $schedule->ID,
            'title' => $schedule->post_title,
            'date' => get_post_meta($schedule->ID, 'schedule_date', true),
            'time' => get_post_meta($schedule->ID, 'schedule_start_time', true) . ' - ' . get_post_meta($schedule->ID, 'schedule_end_time', true),
            'place' => get_post_meta($schedule->ID, 'schedule_place', true),
            'type' => get_post_meta($schedule->ID, 'schedule_type', true)
        ];
    }

    // 日付順にソート
    usort($schedule_data, function($a, $b) {
        return strtotime($a['date']) - strtotime($b['date']);
    });

    return $schedule_data;
}

/**
 * 選手の総活動数を取得（統一命名）
 */
function aidunite_get_player_total_events($player_id) {
    if (!$player_id) return 0;

    $team_id = (function_exists('aidunite_get_current_team_id') ? aidunite_get_current_team_id((int) $player_id) : get_user_meta($player_id, 'team_id', true));
    if (!$team_id) return 0;

    // スケジュール（練習・試合）の総数を取得
    $total_schedules = get_posts([
        'post_type' => 'schedule',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'meta_query' => [
            [
                'key' => 'team_id',
                'value' => $team_id,
                'compare' => '='
            ]
        ]
    ]);

    return count($total_schedules);
}

/**
 * 後方互換性のためのラッパー関数（段階的削除予定）
 * @deprecated 代わりに aidunite_get_player_total_events() を使用してください
 */
function tunageru_get_player_total_events($player_id) {
    return aidunite_get_player_total_events($player_id);
}

/**
 * 選手の試合数を取得（統一命名）
 */
function aidunite_get_player_match_count($player_id) {
    if (!$player_id) return 0;

    $team_id = (function_exists('aidunite_get_current_team_id') ? aidunite_get_current_team_id((int) $player_id) : get_user_meta($player_id, 'team_id', true));
    if (!$team_id) return 0;

    // 試合のみのスケジュール数を取得
    $matches = get_posts([
        'post_type' => 'schedule',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'meta_query' => [
            [
                'key' => 'team_id',
                'value' => $team_id,
                'compare' => '='
            ],
            [
                'key' => 'schedule_type',
                'value' => 'match',
                'compare' => '='
            ]
        ]
    ]);

    return count($matches);
}

/**
 * 後方互換性のためのラッパー関数（段階的削除予定）
 * @deprecated 代わりに aidunite_get_player_match_count() を使用してください
 */
function tunageru_get_player_match_count($player_id) {
    return aidunite_get_player_match_count($player_id);
}

/**
 * 選手の出席率を取得（統一命名）
 */
function aidunite_get_player_attendance_rate($player_id) {
    if (!$player_id) return 0;

    $team_id = (function_exists('aidunite_get_current_team_id') ? aidunite_get_current_team_id((int) $player_id) : get_user_meta($player_id, 'team_id', true));
    if (!$team_id) return 0;

    // 過去のスケジュール数を取得
    $past_schedules = get_posts([
        'post_type' => 'schedule',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'meta_query' => [
            [
                'key' => 'team_id',
                'value' => $team_id,
                'compare' => '='
            ],
            [
                'key' => 'schedule_date',
                'value' => date('Y-m-d'),
                'compare' => '<',
                'type' => 'DATE'
            ]
        ]
    ]);

    if (empty($past_schedules)) return 100;

    // 出席記録を取得
    $attendance_count = 0;
    foreach ($past_schedules as $schedule) {
        $attendance = get_posts([
            'post_type' => 'attendance',
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'author' => $player_id,
            'meta_query' => [
                [
                    'key' => 'schedule_id',
                    'value' => $schedule->ID,
                    'compare' => '='
                ],
                [
                    'key' => 'attendance_status',
                    'value' => 'attended',
                    'compare' => '='
                ]
            ]
        ]);

        if (!empty($attendance)) {
            $attendance_count++;
        }
    }

    return round(($attendance_count / count($past_schedules)) * 100);
}

/**
 * 後方互換性のためのラッパー関数（段階的削除予定）
 * @deprecated 代わりに aidunite_get_player_attendance_rate() を使用してください
 */
function tunageru_get_player_attendance_rate($player_id) {
    return aidunite_get_player_attendance_rate($player_id);
}

?>
