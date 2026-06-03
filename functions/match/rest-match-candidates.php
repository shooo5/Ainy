<?php
/**
 * マッチ候補取得 REST API
 * ページネーション機能付き
 */

if (!defined('ABSPATH')) {
    exit;
}

// REST APIエンドポイント登録
add_action('rest_api_init', function() {
    register_rest_route('aidunite/v1', '/match-candidates', [
        'methods' => 'GET',
        'callback' => 'aidunite_get_match_candidates',
        'permission_callback' => function($request) {
            $result = AidUniteAuthMiddleware::rest_require($request, []);
            return !is_wp_error($result);
        },
        'args' => [
            'schedule_id' => [
                'required' => false,
                'type' => 'integer',
                'sanitize_callback' => 'absint'
            ],
            'page' => [
                'required' => false,
                'type' => 'integer',
                'default' => 1,
                'sanitize_callback' => 'absint'
            ],
            'per_page' => [
                'required' => false,
                'type' => 'integer',
                'default' => 20,
                'sanitize_callback' => 'absint'
            ],
            'filter' => [
                'required' => false,
                'type' => 'string',
                'enum' => ['all', 'future_only'],
                'default' => 'future_only'
            ]
        ]
    ]);
});

/**
 * マッチ候補を取得
 */
function aidunite_get_match_candidates($request) {
    try {
        $current_user_id = get_current_user_id();
        $team_scope = function_exists('aidunite_get_managed_team_ids')
            ? aidunite_get_managed_team_ids($current_user_id)
            : [];
        if (empty($team_scope)) {
            $legacy = (int) get_user_meta($current_user_id, 'team_id', true);
            if ($legacy > 0) {
                $team_scope = [$legacy];
            }
        }

        if (empty($team_scope)) {
            error_log('[MATCH_CANDIDATES_API] Error: Team ID not found for user ' . $current_user_id);
            return new WP_Error('no_team', 'チームIDが設定されていません', ['status' => 400]);
        }

        $team_meta_clause = count($team_scope) === 1
            ? [
                'key' => 'team_id',
                'value' => (int) $team_scope[0],
                'compare' => '=',
            ]
            : [
                'key' => 'team_id',
                'value' => array_map('intval', $team_scope),
                'compare' => 'IN',
            ];

        $params = $request->get_params();
        $schedule_id = isset($params['schedule_id']) ? intval($params['schedule_id']) : null;
        $page = isset($params['page']) ? intval($params['page']) : 1;
        $per_page = isset($params['per_page']) ? intval($params['per_page']) : 20;
        $filter = isset($params['filter']) ? $params['filter'] : 'future_only';

        error_log('[MATCH_CANDIDATES_API] Request: schedule_id=' . $schedule_id . ', page=' . $page . ', per_page=' . $per_page . ', filter=' . $filter);

        // 自チームのスケジュールを取得
        $my_schedules = get_posts([
            'post_type' => 'schedule',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => [
                'relation' => 'OR',
                $team_meta_clause,
            ]
        ]);

        // 投稿者でも検索
        $author_schedules = get_posts([
            'post_type' => 'schedule',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'author' => $current_user_id
        ]);

        // 重複を除いてマージ
        $all_my_schedules = [];
        $schedule_ids = [];
        foreach (array_merge($my_schedules, $author_schedules) as $schedule) {
            if (!in_array($schedule->ID, $schedule_ids)) {
                $all_my_schedules[] = $schedule;
                $schedule_ids[] = $schedule->ID;
            }
        }

        if (empty($all_my_schedules)) {
            error_log('[MATCH_CANDIDATES_API] No schedules found for user ' . $current_user_id);
            return new WP_REST_Response([
                'success' => true,
                'data' => [
                    'items' => [],
                    'pagination' => [
                        'page' => 1,
                        'per_page' => $per_page,
                        'total' => 0,
                        'total_pages' => 0
                    ]
                ]
            ], 200);
        }

        // 特定のスケジュールIDが指定されている場合
        if ($schedule_id) {
            $all_my_schedules = array_filter($all_my_schedules, function($schedule) use ($schedule_id) {
                return $schedule->ID == $schedule_id;
            });
        }

        $today = date('Y-m-d');
        $all_candidates = [];
        $viewer_team_id = count($team_scope) === 1 ? (int) $team_scope[0] : (int) ($team_scope[0] ?? 0);

        foreach ($all_my_schedules as $my_schedule) {
            $schedule_date = get_post_meta($my_schedule->ID, 'schedule_date', true);

            // 過去の日付を除外
            if ($filter === 'future_only' && $schedule_date && $schedule_date < $today) {
                continue;
            }

            // マッチ候補を取得
            $candidates = aidunite_get_auto_match_candidates($my_schedule->ID);

            foreach ($candidates as $candidate) {
                $candidate_team_id = (int) get_post_meta($candidate->ID, 'team_id', true);
                if ($candidate_team_id <= 0 && function_exists('aidunite_resolve_schedule_owner_team_id')) {
                    $candidate_team_id = (int) aidunite_resolve_schedule_owner_team_id((int) $candidate->ID);
                }
                if ($candidate_team_id <= 0) {
                    $author_id = (int) get_post_field('post_author', $candidate->ID);
                    $candidate_team_id = (int) get_user_meta($author_id, 'team_id', true);
                }

                $candidate_team_name = $candidate_team_id ? get_the_title($candidate_team_id) : '（不明）';
                $candidate_date = get_post_meta($candidate->ID, 'schedule_date', true);
                $candidate_start = get_post_meta($candidate->ID, 'schedule_start_time', true);
                $candidate_end = get_post_meta($candidate->ID, 'schedule_end_time', true);
                $candidate_place = get_post_meta($candidate->ID, 'schedule_place', true);
                if (!$candidate_place) {
                    $candidate_place = get_post_meta($candidate->ID, 'schedule_place_option', true);
                }

                $board_tier = 'hidden';
                $scores = ['T' => 0, 'V' => 0, 'A' => 0];
                $match_label = '条件不一致';
                if ($viewer_team_id > 0 && function_exists('aidunite_evaluate_match_apply_context')) {
                    $ev = aidunite_evaluate_match_apply_context(
                        (int) $candidate->ID,
                        (int) $my_schedule->ID,
                        ['mode' => 'preview']
                    );
                    if (is_array($ev)) {
                        $board_tier = (string) ($ev['board_tier'] ?? 'yellow');
                        if (is_array($ev['scores'] ?? null)) {
                            $scores = $ev['scores'];
                        }
                        if (function_exists('aidunite_match_board_tier_label_from_evaluation')) {
                            $match_label = aidunite_match_board_tier_label_from_evaluation($ev);
                        }
                    }
                }
                if ($board_tier === 'hidden') {
                    continue;
                }

                $all_candidates[] = [
                    'my_schedule_id' => $my_schedule->ID,
                    'candidate_schedule_id' => $candidate->ID,
                    'candidate_team_id' => $candidate_team_id,
                    'candidate_team_name' => $candidate_team_name,
                    'schedule_date' => $candidate_date,
                    'schedule_start' => $candidate_start,
                    'schedule_end' => $candidate_end,
                    'venue' => $candidate_place,
                    'board_tier' => $board_tier,
                    'scores' => $scores,
                    'match_label' => $match_label,
                ];
            }
        }

        usort($all_candidates, static function ($a, $b) {
            $pa = function_exists('aidunite_match_board_tier_sort_priority')
                ? aidunite_match_board_tier_sort_priority($a['board_tier'] ?? '')
                : 5;
            $pb = function_exists('aidunite_match_board_tier_sort_priority')
                ? aidunite_match_board_tier_sort_priority($b['board_tier'] ?? '')
                : 5;
            if ($pa !== $pb) {
                return $pa <=> $pb;
            }

            return ((int) ($b['scores']['T'] ?? 0)) <=> ((int) ($a['scores']['T'] ?? 0));
        });

        // ページネーション
        $total = count($all_candidates);
        $total_pages = ceil($total / $per_page);
        $offset = ($page - 1) * $per_page;
        $paginated_candidates = array_slice($all_candidates, $offset, $per_page);

        error_log('[MATCH_CANDIDATES_API] Success: total=' . $total . ', returned=' . count($paginated_candidates));

        return new WP_REST_Response([
            'success' => true,
            'data' => [
                'items' => $paginated_candidates,
                'pagination' => [
                    'page' => $page,
                    'per_page' => $per_page,
                    'total' => $total,
                    'total_pages' => $total_pages
                ]
            ]
        ], 200);

    } catch (Exception $e) {
        error_log('[MATCH_CANDIDATES_API] Exception: ' . $e->getMessage());
        return new WP_Error('server_error', 'サーバーエラーが発生しました', ['status' => 500]);
    }
}

