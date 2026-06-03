<?php
/**
 * 複数チームマッチ試合自動生成システム
 */

/*--------------------------------------------------------------
  試合形式の定義
--------------------------------------------------------------*/
function aidunite_get_match_formats() {
    return [
        'round_robin' => [
            'name' => '総当たり戦',
            'description' => '全チームが1回ずつ対戦',
            'min_teams' => 3,
            'max_teams' => 8,
            'requires_official' => true
        ],
        'single_elimination' => [
            'name' => 'トーナメント戦（勝ち抜き）',
            'description' => '負けたら終了のトーナメント',
            'min_teams' => 4,
            'max_teams' => 16,
            'requires_official' => true
        ],
        'double_elimination' => [
            'name' => 'トーナメント戦（敗者復活）',
            'description' => '敗者復活戦ありのトーナメント',
            'min_teams' => 4,
            'max_teams' => 16,
            'requires_official' => true
        ],
        'sequential_matches' => [
            'name' => '順番対戦',
            'description' => '参加チームが順番に試合',
            'min_teams' => 3,
            'max_teams' => 12,
            'requires_official' => true
        ],
        'group_stage' => [
            'name' => 'グループリーグ',
            'description' => 'グループ分けしてリーグ戦',
            'min_teams' => 6,
            'max_teams' => 16,
            'requires_official' => true
        ]
    ];
}

/*--------------------------------------------------------------
  参加チーム情報取得（性別情報付き）
--------------------------------------------------------------*/
function aidunite_get_mm_participants_with_gender($multi_match_id) {
    $participants = get_posts([
        'post_type' => 'mm_participant',
        'meta_query' => [
            ['key' => 'multi_match_id', 'value' => $multi_match_id],
            ['key' => 'participant_status', 'value' => 'approved']
        ],
        'post_status' => 'publish',
        'numberposts' => -1
    ]);

    $teams = [];
    foreach ($participants as $participant) {
        $team_id = get_post_meta($participant->ID, 'team_id', true);
        if ($team_id) {
            $team_gender = get_post_meta($team_id, 'team_gender_option', true);
            if (function_exists('aidunite_normalize_team_gender_option')) {
                $team_gender = aidunite_normalize_team_gender_option((string) $team_gender);
            }
            $teams[] = [
                'id' => $team_id,
                'name' => get_the_title($team_id),
                'participant_id' => $participant->ID,
                'gender' => $team_gender !== '' ? $team_gender : 'both',
                'region' => get_post_meta($team_id, 'region', true),
                'team_category' => get_post_meta($team_id, 'team_category', true)
            ];
        }
    }

    return $teams;
}

/*--------------------------------------------------------------
  性別別チーム分け
--------------------------------------------------------------*/
function aidunite_separate_teams_by_gender($teams) {
    $male_teams = [];
    $female_teams = [];
    $mixed_teams = [];

    foreach ($teams as $team) {
        $g = function_exists('aidunite_normalize_team_gender_option')
            ? aidunite_normalize_team_gender_option((string) ($team['gender'] ?? ''))
            : (string) ($team['gender'] ?? '');
        switch ($g) {
            case 'male':
            case '男子':
                $male_teams[] = $team;
                break;
            case 'female':
            case '女子':
                $female_teams[] = $team;
                break;
            case 'both':
            case '男女':
            case 'mixed':
            default:
                $mixed_teams[] = $team;
                break;
        }
    }

    return [
        'male' => $male_teams,
        'female' => $female_teams,
        'mixed' => $mixed_teams
    ];
}

/*--------------------------------------------------------------
  試合自動生成メイン関数
--------------------------------------------------------------*/
function aidunite_generate_multi_match_tournament($multi_match_id) {
    $match_format = get_post_meta($multi_match_id, 'match_format', true);
            $teams = aidunite_get_mm_participants_with_gender($multi_match_id);

    if (empty($teams)) {
        return ['success' => false, 'message' => '参加チームがありません'];
    }

    // 性別別にチームを分ける
    $separated_teams = aidunite_separate_teams_by_gender($teams);

    // 試合形式に応じて生成
    switch ($match_format) {
        case 'round_robin':
            return aidunite_generate_round_robin($multi_match_id, $separated_teams);
        case 'single_elimination':
            return aidunite_generate_single_elimination($multi_match_id, $separated_teams);
        case 'double_elimination':
            return aidunite_generate_double_elimination($multi_match_id, $separated_teams);
        case 'sequential_matches':
            return aidunite_generate_sequential_matches($multi_match_id, $separated_teams);
        case 'group_stage':
            return aidunite_generate_group_stage($multi_match_id, $separated_teams);
        default:
            return aidunite_generate_sequential_matches($multi_match_id, $separated_teams);
    }
}

/*--------------------------------------------------------------
  順番対戦形式の生成
--------------------------------------------------------------*/
function aidunite_generate_sequential_matches($multi_match_id, $separated_teams) {
    $all_teams = array_merge($separated_teams['male'], $separated_teams['female'], $separated_teams['mixed']);
    $total_teams = count($all_teams);

    if ($total_teams < 3) {
        return ['success' => false, 'message' => '順番対戦には最低3チームが必要です'];
    }

    $matches = [];
    $match_number = 1;

    // チームをシャッフル
    shuffle($all_teams);

    // 連続試合を避けるための設定
    $avoid_consecutive = $total_teams > 6;
    $team_match_count = array_fill_keys(array_column($all_teams, 'id'), 0);

    for ($i = 0; $i < $total_teams - 1; $i++) {
        $team_a = $all_teams[$i];

        // 次のチームを選択（連続試合を避ける）
        $next_team_index = $i + 1;
        if ($avoid_consecutive) {
            // 試合回数が少ないチームを優先
            $next_team_index = aidunite_find_next_team_for_sequential($all_teams, $i, $team_match_count);
        }

        $team_b = $all_teams[$next_team_index];

        // オフィシャルチームを選択
        $official_team = aidunite_select_official_team($all_teams, [$team_a['id'], $team_b['id']]);

        $matches[] = [
            'match_number' => $match_number++,
            'team_a_id' => $team_a['id'],
            'team_a_name' => $team_a['name'],
            'team_b_id' => $team_b['id'],
            'team_b_name' => $team_b['name'],
            'official_team_id' => $official_team['id'],
            'official_team_name' => $official_team['name'],
            'match_type' => 'sequential',
            'round' => 1,
            'estimated_duration' => 90, // 分
            'status' => 'scheduled'
        ];

        // 試合回数を更新
        $team_match_count[$team_a['id']]++;
        $team_match_count[$team_b['id']]++;
    }

    // データベースに保存
    $result = aidunite_save_generated_matches($multi_match_id, $matches);

    return [
        'success' => true,
        'message' => "順番対戦を生成しました（{$total_teams}チーム、{$match_number}試合）",
        'matches' => $matches,
        'total_matches' => count($matches)
    ];
}

/*--------------------------------------------------------------
  連続試合を避けるための次のチーム選択
--------------------------------------------------------------*/
function aidunite_find_next_team_for_sequential($teams, $current_index, $team_match_count) {
    $current_team_id = $teams[$current_index]['id'];
    $min_matches = PHP_INT_MAX;
    $best_candidate = $current_index + 1;

    // 試合回数が最も少ないチームを探す
    for ($i = $current_index + 1; $i < count($teams); $i++) {
        $candidate_team_id = $teams[$i]['id'];
        $match_count = $team_match_count[$candidate_team_id];

        if ($match_count < $min_matches) {
            $min_matches = $match_count;
            $best_candidate = $i;
        }
    }

    return $best_candidate;
}

/*--------------------------------------------------------------
  オフィシャルチーム選択
--------------------------------------------------------------*/
function aidunite_select_official_team($all_teams, $exclude_team_ids) {
    $available_teams = array_filter($all_teams, function($team) use ($exclude_team_ids) {
        return !in_array($team['id'], $exclude_team_ids);
    });

    if (empty($available_teams)) {
        // 除外チームがない場合は、最初のチームを除外して選択
        return $all_teams[0];
    }

    // ランダムに選択
    return $available_teams[array_rand($available_teams)];
}

/*--------------------------------------------------------------
  総当たり戦形式の生成
--------------------------------------------------------------*/
function aidunite_generate_round_robin($multi_match_id, $separated_teams) {
    $all_teams = array_merge($separated_teams['male'], $separated_teams['female'], $separated_teams['mixed']);
    $total_teams = count($all_teams);

    if ($total_teams < 3) {
        return ['success' => false, 'message' => '総当たり戦には最低3チームが必要です'];
    }

    $matches = [];
    $match_number = 1;

    // 総当たり戦の組み合わせを生成
    for ($i = 0; $i < $total_teams; $i++) {
        for ($j = $i + 1; $j < $total_teams; $j++) {
            $team_a = $all_teams[$i];
            $team_b = $all_teams[$j];

            // オフィシャルチームを選択
            $official_team = aidunite_select_official_team($all_teams, [$team_a['id'], $team_b['id']]);

            $matches[] = [
                'match_number' => $match_number++,
                'team_a_id' => $team_a['id'],
                'team_a_name' => $team_a['name'],
                'team_b_id' => $team_b['id'],
                'team_b_name' => $team_b['name'],
                'official_team_id' => $official_team['id'],
                'official_team_name' => $official_team['name'],
                'match_type' => 'round_robin',
                'round' => 1,
                'estimated_duration' => 90,
                'status' => 'scheduled'
            ];
        }
    }

    // データベースに保存
    $result = aidunite_save_generated_matches($multi_match_id, $matches);

    return [
        'success' => true,
        'message' => "総当たり戦を生成しました（{$total_teams}チーム、{$match_number}試合）",
        'matches' => $matches,
        'total_matches' => count($matches)
    ];
}

/*--------------------------------------------------------------
  トーナメント戦（勝ち抜き）の生成
--------------------------------------------------------------*/
function aidunite_generate_single_elimination($multi_match_id, $separated_teams) {
    $all_teams = array_merge($separated_teams['male'], $separated_teams['female'], $separated_teams['mixed']);
    $total_teams = count($all_teams);

    if ($total_teams < 4) {
        return ['success' => false, 'message' => 'トーナメント戦には最低4チームが必要です'];
    }

    // 2の累乗に調整
    $power_of_two = aidunite_get_next_power_of_two($total_teams);
    $byes_needed = $power_of_two - $total_teams;

    // チームをシャッフル
    shuffle($all_teams);

    // バイ（不戦勝）を追加
    $tournament_teams = $all_teams;
    for ($i = 0; $i < $byes_needed; $i++) {
        $tournament_teams[] = ['id' => 'bye_' . $i, 'name' => '不戦勝', 'is_bye' => true];
    }

    $matches = [];
    $match_number = 1;
    $round = 1;

    // 1回戦を生成
    for ($i = 0; $i < count($tournament_teams); $i += 2) {
        $team_a = $tournament_teams[$i];
        $team_b = $tournament_teams[$i + 1];

        if (isset($team_a['is_bye']) || isset($team_b['is_bye'])) {
            // バイの場合は試合をスキップ
            continue;
        }

        // オフィシャルチームを選択
        $official_team = aidunite_select_official_team($all_teams, [$team_a['id'], $team_b['id']]);

        $matches[] = [
            'match_number' => $match_number++,
            'team_a_id' => $team_a['id'],
            'team_a_name' => $team_a['name'],
            'team_b_id' => $team_b['id'],
            'team_b_name' => $team_b['name'],
            'official_team_id' => $official_team['id'],
            'official_team_name' => $official_team['name'],
            'match_type' => 'single_elimination',
            'round' => $round,
            'estimated_duration' => 90,
            'status' => 'scheduled'
        ];
    }

    // データベースに保存
    $result = aidunite_save_generated_matches($multi_match_id, $matches);

    return [
        'success' => true,
        'message' => "トーナメント戦（勝ち抜き）を生成しました（{$total_teams}チーム、{$match_number}試合）",
        'matches' => $matches,
        'total_matches' => count($matches)
    ];
}

/*--------------------------------------------------------------
  2の累乗を取得
--------------------------------------------------------------*/
function aidunite_get_next_power_of_two($number) {
    $power = 1;
    while ($power < $number) {
        $power *= 2;
    }
    return $power;
}

/*--------------------------------------------------------------
  グループリーグ形式の生成
--------------------------------------------------------------*/
function aidunite_generate_group_stage($multi_match_id, $separated_teams) {
    $all_teams = array_merge($separated_teams['male'], $separated_teams['female'], $separated_teams['mixed']);
    $total_teams = count($all_teams);

    if ($total_teams < 6) {
        return ['success' => false, 'message' => 'グループリーグには最低6チームが必要です'];
    }

    // グループ数を決定（4-6チームずつ）
    $group_count = ceil($total_teams / 4);
    $teams_per_group = ceil($total_teams / $group_count);

    // チームをシャッフル
    shuffle($all_teams);

    // グループに分ける
    $groups = [];
    for ($i = 0; $i < $group_count; $i++) {
        $groups[$i] = array_slice($all_teams, $i * $teams_per_group, $teams_per_group);
    }

    $matches = [];
    $match_number = 1;

    // 各グループ内で総当たり戦
    foreach ($groups as $group_index => $group_teams) {
        for ($i = 0; $i < count($group_teams); $i++) {
            for ($j = $i + 1; $j < count($group_teams); $j++) {
                $team_a = $group_teams[$i];
                $team_b = $group_teams[$j];

                // オフィシャルチームを選択（同じグループ外から）
                $other_teams = array_merge(...array_filter($groups, function($key) use ($group_index) {
                    return $key != $group_index;
                }, ARRAY_FILTER_USE_KEY));

                $official_team = aidunite_select_official_team($other_teams, [$team_a['id'], $team_b['id']]);

                $matches[] = [
                    'match_number' => $match_number++,
                    'team_a_id' => $team_a['id'],
                    'team_a_name' => $team_a['name'],
                    'team_b_id' => $team_b['id'],
                    'team_b_name' => $team_b['name'],
                    'official_team_id' => $official_team['id'],
                    'official_team_name' => $official_team['name'],
                    'match_type' => 'group_stage',
                    'round' => 1,
                    'group' => $group_index + 1,
                    'estimated_duration' => 90,
                    'status' => 'scheduled'
                ];
            }
        }
    }

    // データベースに保存
    $result = aidunite_save_generated_matches($multi_match_id, $matches);

    return [
        'success' => true,
        'message' => "グループリーグを生成しました（{$total_teams}チーム、{$group_count}グループ、{$match_number}試合）",
        'matches' => $matches,
        'total_matches' => count($matches),
        'groups' => $groups
    ];
}

/*--------------------------------------------------------------
  生成された試合をデータベースに保存
--------------------------------------------------------------*/
function aidunite_save_generated_matches($multi_match_id, $matches) {
    $saved_matches = [];

    foreach ($matches as $match_data) {
        $match_id = wp_insert_post([
            'post_type' => 'multi_match_result',
            'post_title' => "試合 #{$match_data['match_number']}: {$match_data['team_a_name']} vs {$match_data['team_b_name']}",
            'post_status' => 'publish',
            'post_author' => get_current_user_id()
        ]);

        if ($match_id) {
            // メタデータを保存
            update_post_meta($match_id, 'multi_match_id', $multi_match_id);
            update_post_meta($match_id, 'match_number', $match_data['match_number']);
            update_post_meta($match_id, 'team1_id', $match_data['team_a_id']);
            update_post_meta($match_id, 'team2_id', $match_data['team_b_id']);
            update_post_meta($match_id, 'official_team_id', $match_data['official_team_id']);
            update_post_meta($match_id, 'match_type', $match_data['match_type']);
            update_post_meta($match_id, 'match_round', $match_data['round']);
            update_post_meta($match_id, 'estimated_duration', $match_data['estimated_duration']);
            update_post_meta($match_id, 'result_status', $match_data['status']);

            if (isset($match_data['group'])) {
                update_post_meta($match_id, 'match_group', $match_data['group']);
            }

            $saved_matches[] = $match_id;
        }
    }

    return $saved_matches;
}

/*--------------------------------------------------------------
  試合スケジュール最適化（連続試合を避ける）
--------------------------------------------------------------*/
function aidunite_optimize_match_schedule($multi_match_id) {
    $matches = get_posts([
        'post_type' => 'multi_match_result',
        'meta_query' => [
            ['key' => 'multi_match_id', 'value' => $multi_match_id],
            ['key' => 'result_status', 'value' => 'scheduled']
        ],
        'post_status' => 'publish',
        'numberposts' => -1,
        'orderby' => 'meta_value_num',
        'meta_key' => 'match_number'
    ]);

    if (empty($matches)) {
        return ['success' => false, 'message' => '最適化する試合がありません'];
    }

    $team_schedule = [];
    $optimized_schedule = [];

    // 各チームの試合回数をカウント
    foreach ($matches as $match) {
        $team1_id = get_post_meta($match->ID, 'team1_id', true);
        $team2_id = get_post_meta($match->ID, 'team2_id', true);

        if (!isset($team_schedule[$team1_id])) {
            $team_schedule[$team1_id] = [];
        }
        if (!isset($team_schedule[$team2_id])) {
            $team_schedule[$team2_id] = [];
        }

        $team_schedule[$team1_id][] = $match->ID;
        $team_schedule[$team2_id][] = $match->ID;
    }

    // 試合を並び替えて連続試合を避ける
    $used_matches = [];
    $current_teams = [];

    while (count($used_matches) < count($matches)) {
        $best_match = null;
        $best_score = -1;

        foreach ($matches as $match) {
            if (in_array($match->ID, $used_matches)) {
                continue;
            }

            $team1_id = get_post_meta($match->ID, 'team1_id', true);
            $team2_id = get_post_meta($match->ID, 'team2_id', true);

            // 連続試合を避けるスコアを計算
            $score = 0;
            if (!in_array($team1_id, $current_teams)) $score += 2;
            if (!in_array($team2_id, $current_teams)) $score += 2;

            if ($score > $best_score) {
                $best_score = $score;
                $best_match = $match;
            }
        }

        if ($best_match) {
            $optimized_schedule[] = $best_match->ID;
            $used_matches[] = $best_match->ID;

            $team1_id = get_post_meta($best_match->ID, 'team1_id', true);
            $team2_id = get_post_meta($best_match->ID, 'team2_id', true);

            $current_teams = [$team1_id, $team2_id];
        }
    }

    // 最適化された順序で試合番号を更新
    foreach ($optimized_schedule as $index => $match_id) {
        update_post_meta($match_id, 'match_number', $index + 1);
    }

    return [
        'success' => true,
        'message' => '試合スケジュールを最適化しました',
        'optimized_matches' => $optimized_schedule
    ];
}

/*--------------------------------------------------------------
  REST API: 試合自動生成
--------------------------------------------------------------*/
add_action('rest_api_init', function() {
    register_rest_route('aidunite/v1', '/multi-match/(?P<match_id>\d+)/generate-tournament', [
        'methods' => 'POST',
        'callback' => 'aidunite_api_generate_tournament',
        'permission_callback' => function($request) {
            return is_user_logged_in();
        }
    ]);

    register_rest_route('aidunite/v1', '/multi-match/(?P<match_id>\d+)/optimize-schedule', [
        'methods' => 'POST',
        'callback' => 'aidunite_api_optimize_schedule',
        'permission_callback' => function($request) {
            return is_user_logged_in();
        }
    ]);
});

function aidunite_api_generate_tournament($request) {
    $match_id = intval($request->get_param('match_id'));
    $params = $request->get_json_params();

    // 試合形式を指定（オプション）
    if (isset($params['match_format'])) {
        update_post_meta($match_id, 'match_format', sanitize_text_field($params['match_format']));
    }

    $result = aidunite_generate_multi_match_tournament($match_id);

    if ($result['success']) {
        return new WP_REST_Response($result, 200);
    } else {
        return new WP_Error('generation_failed', $result['message'], ['status' => 400]);
    }
}

function aidunite_api_optimize_schedule($request) {
    $match_id = intval($request->get_param('match_id'));

    $result = aidunite_optimize_match_schedule($match_id);

    if ($result['success']) {
        return new WP_REST_Response($result, 200);
    } else {
        return new WP_Error('optimization_failed', $result['message'], ['status' => 400]);
    }
}
