<?php
/**
 * 複数コート対応スケジューリング機能
 */

// 直接実行を防ぐ
if (!defined('ABSPATH')) {
    exit;
}

/**
 * コート情報を取得
 */
function aidunite_get_courts($multi_match_id) {
    $courts = get_post_meta($multi_match_id, 'available_courts', true);
    if (!$courts) {
        // デフォルトで1コート
        return [
            [
                'id' => 1,
                'name' => 'コート1',
                'status' => 'available',
                'capacity' => 1
            ]
        ];
    }
    return $courts;
}

/**
 * コート情報を保存
 */
function aidunite_save_courts($multi_match_id, $courts) {
    update_post_meta($multi_match_id, 'available_courts', $courts);
}

/**
 * 複数コート対応の試合スケジュール生成
 */
function aidunite_generate_multi_court_schedule($multi_match_id, $match_format = 'sequential_matches') {
    $participants = aidunite_get_multi_match_participants($multi_match_id);
    $courts = aidunite_get_courts($multi_match_id);
    $available_courts = array_filter($courts, function($court) {
        return $court['status'] === 'available';
    });

    if (empty($available_courts)) {
        return new WP_Error('no_courts', '利用可能なコートがありません。');
    }

    $court_count = count($available_courts);
    $team_count = count($participants);

    // 最小チーム数チェック
    $min_teams = get_post_meta($multi_match_id, 'min_teams', true);
    if ($team_count < $min_teams) {
        return new WP_Error('insufficient_teams', "参加チーム数が不足しています。必要: {$min_teams}チーム、現在: {$team_count}チーム");
    }

    // 既存の試合を削除
    $existing_matches = get_posts([
        'post_type' => 'multi_match_result',
        'meta_query' => [
            ['key' => 'multi_match_id', 'value' => $multi_match_id]
        ],
        'post_status' => 'publish',
        'numberposts' => -1
    ]);

    foreach ($existing_matches as $match) {
        wp_delete_post($match->ID, true);
    }

    $matches = [];
    $court_schedules = [];

    // 各コートのスケジュールを初期化
    foreach ($available_courts as $court) {
        $court_schedules[$court['id']] = [
            'court_id' => $court['id'],
            'court_name' => $court['name'],
            'matches' => [],
            'current_time' => 0
        ];
    }

    switch ($match_format) {
        case 'sequential_matches':
            $matches = aidunite_generate_sequential_matches_multi_court($participants, $court_schedules, $multi_match_id);
            break;
        case 'round_robin':
            $matches = aidunite_generate_round_robin_multi_court($participants, $court_schedules, $multi_match_id);
            break;
        case 'tournament':
            $matches = aidunite_generate_tournament_multi_court($participants, $court_schedules, $multi_match_id);
            break;
        default:
            $matches = aidunite_generate_sequential_matches_multi_court($participants, $court_schedules, $multi_match_id);
    }

    // 試合をデータベースに保存
    foreach ($matches as $match) {
        $match_id = wp_insert_post([
            'post_type' => 'multi_match_result',
            'post_title' => "試合 #{$match['match_number']} - {$match['team1_name']} vs {$match['team2_name']}",
            'post_status' => 'publish',
            'post_author' => get_current_user_id()
        ]);

        if ($match_id) {
            update_post_meta($match_id, 'multi_match_id', $multi_match_id);
            update_post_meta($match_id, 'match_number', $match['match_number']);
            update_post_meta($match_id, 'team1_id', $match['team1_id']);
            update_post_meta($match_id, 'team2_id', $match['team2_id']);
            update_post_meta($match_id, 'official_team_id', $match['official_team_id']);
            update_post_meta($match_id, 'court_id', $match['court_id']);
            update_post_meta($match_id, 'court_name', $match['court_name']);
            update_post_meta($match_id, 'match_time', $match['match_time']);
            update_post_meta($match_id, 'match_duration', $match['match_duration']);
            update_post_meta($match_id, 'match_status', 'scheduled');
            update_post_meta($match_id, 'match_format', $match_format);
        }
    }

    return [
        'success' => true,
        'matches_created' => count($matches),
        'courts_used' => count(array_unique(array_column($matches, 'court_id'))),
        'total_duration' => max(array_column($matches, 'end_time')),
        'message' => "複数コート対応の試合スケジュールを生成しました。"
    ];
}

/**
 * 複数コート対応の順番対戦生成
 */
function aidunite_generate_sequential_matches_multi_court($participants, &$court_schedules, $multi_match_id) {
    $matches = [];
    $match_number = 1;
    $match_duration = 60; // 1試合60分
    $break_duration = 15; // 休憩15分

    // チームを性別で分離
    $male_teams = [];
    $female_teams = [];
    $mixed_teams = [];

    foreach ($participants as $participant) {
        $team_gender = get_post_meta($participant['id'], 'team_gender_option', true);
        if (function_exists('aidunite_normalize_team_gender_option')) {
            $team_gender = aidunite_normalize_team_gender_option((string) $team_gender);
        }
        switch ($team_gender) {
            case 'male':
                $male_teams[] = $participant;
                break;
            case 'female':
                $female_teams[] = $participant;
                break;
            case 'both':
            default:
                $mixed_teams[] = $participant;
                break;
        }
    }

    // 各性別グループで試合を生成
    $gender_groups = [
        'male' => $male_teams,
        'female' => $female_teams,
        'mixed' => $mixed_teams
    ];

    foreach ($gender_groups as $gender => $teams) {
        if (count($teams) < 2) continue;

        // チームをシャッフル
        shuffle($teams);

        for ($i = 0; $i < count($teams) - 1; $i++) {
            $team1 = $teams[$i];
            $team2 = $teams[$i + 1];

            // オフィシャルチームを選択（他のチームから）
            $official_team = aidunite_select_official_team_for_match($teams, $team1['id'], $team2['id']);

            // 最適なコートと時間を選択
            $court_assignment = aidunite_assign_court_and_time($court_schedules, $match_duration, $break_duration);

            $matches[] = [
                'match_number' => $match_number++,
                'team1_id' => $team1['id'],
                'team1_name' => $team1['name'],
                'team2_id' => $team2['id'],
                'team2_name' => $team2['name'],
                'official_team_id' => $official_team['id'],
                'official_team_name' => $official_team['name'],
                'court_id' => $court_assignment['court_id'],
                'court_name' => $court_assignment['court_name'],
                'match_time' => $court_assignment['start_time'],
                'match_duration' => $match_duration,
                'end_time' => $court_assignment['start_time'] + $match_duration,
                'gender' => $gender
            ];

            // コートスケジュールを更新
            $court_schedules[$court_assignment['court_id']]['matches'][] = [
                'match_number' => $match_number - 1,
                'start_time' => $court_assignment['start_time'],
                'end_time' => $court_assignment['start_time'] + $match_duration,
                'teams' => [$team1['name'], $team2['name']]
            ];
            $court_schedules[$court_assignment['court_id']]['current_time'] = $court_assignment['start_time'] + $match_duration + $break_duration;
        }
    }

    return $matches;
}

/**
 * 複数コート対応のリーグ戦生成
 */
function aidunite_generate_round_robin_multi_court($participants, &$court_schedules, $multi_match_id) {
    $matches = [];
    $match_number = 1;
    $match_duration = 60;
    $break_duration = 15;

    // 全チームの組み合わせを生成
    $combinations = [];
    for ($i = 0; $i < count($participants); $i++) {
        for ($j = $i + 1; $j < count($participants); $j++) {
            $combinations[] = [$participants[$i], $participants[$j]];
        }
    }

    // 組み合わせをシャッフル
    shuffle($combinations);

    foreach ($combinations as $combination) {
        $team1 = $combination[0];
        $team2 = $combination[1];

        // オフィシャルチームを選択
        $official_team = aidunite_select_official_team_for_match($participants, $team1['id'], $team2['id']);

        // 最適なコートと時間を選択
        $court_assignment = aidunite_assign_court_and_time($court_schedules, $match_duration, $break_duration);

        $matches[] = [
            'match_number' => $match_number++,
            'team1_id' => $team1['id'],
            'team1_name' => $team1['name'],
            'team2_id' => $team2['id'],
            'team2_name' => $team2['name'],
            'official_team_id' => $official_team['id'],
            'official_team_name' => $official_team['name'],
            'court_id' => $court_assignment['court_id'],
            'court_name' => $court_assignment['court_name'],
            'match_time' => $court_assignment['start_time'],
            'match_duration' => $match_duration,
            'end_time' => $court_assignment['start_time'] + $match_duration
        ];

        // コートスケジュールを更新
        $court_schedules[$court_assignment['court_id']]['matches'][] = [
            'match_number' => $match_number - 1,
            'start_time' => $court_assignment['start_time'],
            'end_time' => $court_assignment['start_time'] + $match_duration,
            'teams' => [$team1['name'], $team2['name']]
        ];
        $court_schedules[$court_assignment['court_id']]['current_time'] = $court_assignment['start_time'] + $match_duration + $break_duration;
    }

    return $matches;
}

/**
 * 複数コート対応のトーナメント生成
 */
function aidunite_generate_tournament_multi_court($participants, &$court_schedules, $multi_match_id) {
    $matches = [];
    $match_number = 1;
    $match_duration = 60;
    $break_duration = 15;

    // チーム数を2の累乗に調整
    $team_count = count($participants);
    $target_count = pow(2, ceil(log($team_count, 2)));

    // チームをシャッフル
    shuffle($participants);

    // 1回戦の組み合わせを生成
    $round1_matches = [];
    for ($i = 0; $i < $target_count / 2; $i++) {
        $team1 = isset($participants[$i * 2]) ? $participants[$i * 2] : null;
        $team2 = isset($participants[$i * 2 + 1]) ? $participants[$i * 2 + 1] : null;

        if ($team1 && $team2) {
            $round1_matches[] = [$team1, $team2];
        }
    }

    // 1回戦の試合をスケジュール
    foreach ($round1_matches as $match_teams) {
        $team1 = $match_teams[0];
        $team2 = $match_teams[1];

        // オフィシャルチームを選択
        $official_team = aidunite_select_official_team_for_match($participants, $team1['id'], $team2['id']);

        // 最適なコートと時間を選択
        $court_assignment = aidunite_assign_court_and_time($court_schedules, $match_duration, $break_duration);

        $matches[] = [
            'match_number' => $match_number++,
            'team1_id' => $team1['id'],
            'team1_name' => $team1['name'],
            'team2_id' => $team2['id'],
            'team2_name' => $team2['name'],
            'official_team_id' => $official_team['id'],
            'official_team_name' => $official_team['name'],
            'court_id' => $court_assignment['court_id'],
            'court_name' => $court_assignment['court_name'],
            'match_time' => $court_assignment['start_time'],
            'match_duration' => $match_duration,
            'end_time' => $court_assignment['start_time'] + $match_duration,
            'round' => 1
        ];

        // コートスケジュールを更新
        $court_schedules[$court_assignment['court_id']]['matches'][] = [
            'match_number' => $match_number - 1,
            'start_time' => $court_assignment['start_time'],
            'end_time' => $court_assignment['start_time'] + $match_duration,
            'teams' => [$team1['name'], $team2['name']],
            'round' => 1
        ];
        $court_schedules[$court_assignment['court_id']]['current_time'] = $court_assignment['start_time'] + $match_duration + $break_duration;
    }

    return $matches;
}

/**
 * コートと時間の割り当て
 */
function aidunite_assign_court_and_time(&$court_schedules, $match_duration, $break_duration) {
    $best_court = null;
    $earliest_start = PHP_INT_MAX;

    // 最も早く開始できるコートを選択
    foreach ($court_schedules as $court_id => $schedule) {
        if ($schedule['current_time'] < $earliest_start) {
            $earliest_start = $schedule['current_time'];
            $best_court = $court_id;
        }
    }

    return [
        'court_id' => $best_court,
        'court_name' => $court_schedules[$best_court]['court_name'],
        'start_time' => $earliest_start
    ];
}

/**
 * オフィシャルチーム選択（既存の関数を拡張）
 */
function aidunite_select_official_team_for_match($participants, $team1_id, $team2_id) {
    $available_teams = array_filter($participants, function($team) use ($team1_id, $team2_id) {
        return $team['id'] != $team1_id && $team['id'] != $team2_id;
    });

    if (empty($available_teams)) {
        // 利用可能なチームがない場合は、参加チームからランダムに選択
        return $participants[array_rand($participants)];
    }

    // ランダムに選択
    return $available_teams[array_rand($available_teams)];
}

/**
 * コート別スケジュール取得
 */
function aidunite_get_court_schedule($multi_match_id) {
    $matches = get_posts([
        'post_type' => 'multi_match_result',
        'meta_query' => [
            ['key' => 'multi_match_id', 'value' => $multi_match_id]
        ],
        'post_status' => 'publish',
        'numberposts' => -1,
        'orderby' => 'meta_value_num',
        'meta_key' => 'match_time'
    ]);

    $court_schedules = [];

    foreach ($matches as $match) {
        $court_id = get_post_meta($match->ID, 'court_id', true);
        $court_name = get_post_meta($match->ID, 'court_name', true);
        $match_time = get_post_meta($match->ID, 'match_time', true);
        $match_duration = get_post_meta($match->ID, 'match_duration', true);
        $team1_name = get_the_title(get_post_meta($match->ID, 'team1_id', true));
        $team2_name = get_the_title(get_post_meta($match->ID, 'team2_id', true));
        $official_name = get_the_title(get_post_meta($match->ID, 'official_team_id', true));

        if (!isset($court_schedules[$court_id])) {
            $court_schedules[$court_id] = [
                'court_id' => $court_id,
                'court_name' => $court_name,
                'matches' => []
            ];
        }

        $court_schedules[$court_id]['matches'][] = [
            'match_id' => $match->ID,
            'match_number' => get_post_meta($match->ID, 'match_number', true),
            'start_time' => $match_time,
            'end_time' => $match_time + $match_duration,
            'team1' => $team1_name,
            'team2' => $team2_name,
            'official' => $official_name,
            'status' => get_post_meta($match->ID, 'match_status', true)
        ];
    }

    return $court_schedules;
}

/**
 * コート利用効率の分析
 */
function aidunite_analyze_court_efficiency($multi_match_id) {
    $court_schedules = aidunite_get_court_schedule($multi_match_id);
    $analysis = [];

    foreach ($court_schedules as $court_id => $schedule) {
        $total_matches = count($schedule['matches']);
        $total_time = 0;
        $idle_time = 0;

        if ($total_matches > 0) {
            $first_match = $schedule['matches'][0];
            $last_match = end($schedule['matches']);
            $total_time = $last_match['end_time'] - $first_match['start_time'];

            // 試合間の空き時間を計算
            for ($i = 0; $i < count($schedule['matches']) - 1; $i++) {
                $current_end = $schedule['matches'][$i]['end_time'];
                $next_start = $schedule['matches'][$i + 1]['start_time'];
                $idle_time += $next_start - $current_end;
            }
        }

        $analysis[$court_id] = [
            'court_name' => $schedule['court_name'],
            'total_matches' => $total_matches,
            'total_time' => $total_time,
            'idle_time' => $idle_time,
            'utilization_rate' => $total_time > 0 ? (($total_time - $idle_time) / $total_time) * 100 : 0
        ];
    }

    return $analysis;
}
?>
