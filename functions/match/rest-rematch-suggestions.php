<?php
/**
 * 再マッチング提案 REST API
 * 過去に高評価をいただいたチームとの再試合を提案
 */

if (!defined('ABSPATH')) {
    exit;
}

// REST APIエンドポイント登録
add_action('rest_api_init', function() {
    register_rest_route('aidunite/v1', '/rematch-suggestions', [
        'methods' => 'GET',
        'callback' => 'aidunite_get_rematch_suggestions',
        'permission_callback' => function($request) {
            $result = AidUniteAuthMiddleware::rest_require($request, []);
            return !is_wp_error($result);
        },
        'args' => [
            'limit' => [
                'required' => false,
                'type' => 'integer',
                'default' => 10,
                'sanitize_callback' => 'absint'
            ]
        ]
    ]);
});

/**
 * 再マッチング提案を取得
 */
function aidunite_get_rematch_suggestions($request) {
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
            error_log('[REMATCH_SUGGESTIONS_API] Error: Team ID not found for user ' . $current_user_id);
            return new WP_Error('no_team', 'チームIDが設定されていません', ['status' => 400]);
        }

        $scope_val = count($team_scope) === 1 ? (int) $team_scope[0] : array_map('intval', $team_scope);
        $scope_cmp = count($team_scope) === 1 ? '=' : 'IN';

        $params = $request->get_params();
        $limit = isset($params['limit']) ? intval($params['limit']) : 10;

        error_log('[REMATCH_SUGGESTIONS_API] Request: team_scope=' . wp_json_encode($team_scope) . ', limit=' . $limit);

        // 過去6ヶ月以内の試合で、4⭐以上（5段階評価）のフィードバックを取得
        $six_months_ago = date('Y-m-d', strtotime('-6 months'));

        $feedbacks = get_posts([
            'post_type' => 'match_feedback',
            'posts_per_page' => -1,
            'meta_query' => [
                'relation' => 'AND',
                [
                    'key' => 'team_id',
                    'value' => $scope_val,
                    'compare' => $scope_cmp,
                ],
                [
                    'key' => 'satisfaction',
                    'value' => 4,
                    'compare' => '>='
                ],
                [
                    'key' => 'rematch_interest',
                    'value' => 'yes',
                    'compare' => '='
                ]
            ],
            'orderby' => 'date',
            'order' => 'DESC'
        ]);

        $suggestions = [];

        foreach ($feedbacks as $feedback) {
            $match_id = get_post_meta($feedback->ID, 'match_id', true);
            if (!$match_id) {
                continue;
            }

            // マッチ情報を取得
            $match_request = get_post($match_id);
            if (!$match_request || $match_request->post_type !== 'match_request') {
                continue;
            }

            // 相手チームIDを取得（managed 内のどちらが自チームかで判定）
            $from_team_id = (int) get_post_meta($match_id, 'from_team_id', true);
            $to_team_id = (int) get_post_meta($match_id, 'to_team_id', true);

            $my_tid = 0;
            if (in_array($from_team_id, $team_scope, true)) {
                $my_tid = $from_team_id;
            } elseif (in_array($to_team_id, $team_scope, true)) {
                $my_tid = $to_team_id;
            }
            if (!$my_tid) {
                continue;
            }

            $opponent_team_id = ($my_tid === $from_team_id) ? $to_team_id : $from_team_id;
            if (!$opponent_team_id) {
                continue;
            }

            // 相手チームの評価も確認（双方が高評価の場合のみ提案）
            $opponent_feedback = get_posts([
                'post_type' => 'match_feedback',
                'posts_per_page' => 1,
                'meta_query' => [
                    'relation' => 'AND',
                    [
                        'key' => 'match_id',
                        'value' => $match_id,
                        'compare' => '='
                    ],
                    [
                        'key' => 'team_id',
                        'value' => $opponent_team_id,
                        'compare' => '='
                    ],
                    [
                        'key' => 'satisfaction',
                        'value' => 4,
                        'compare' => '>='
                    ],
                    [
                        'key' => 'rematch_interest',
                        'value' => 'yes',
                        'compare' => '='
                    ]
                ]
            ]);

            // 相手も再試合を希望している場合のみ提案
            if (empty($opponent_feedback)) {
                continue;
            }

            // スケジュール情報を取得
            $schedule_id = get_post_meta($match_id, 'to_schedule_id', true);
            if (!$schedule_id) {
                $schedule_id = get_post_meta($match_id, 'from_schedule_id', true);
            }

            $schedule_date = $schedule_id ? get_post_meta($schedule_id, 'schedule_date', true) : '';

            // 過去6ヶ月以内かチェック
            if ($schedule_date && $schedule_date < $six_months_ago) {
                continue;
            }

            $opponent_team_name = get_the_title($opponent_team_id);
            $satisfaction = get_post_meta($feedback->ID, 'satisfaction', true);
            $opponent_rating = get_post_meta($feedback->ID, 'opponent_rating', true);
            $opponent_satisfaction = get_post_meta($opponent_feedback[0]->ID, 'satisfaction', true);

            $suggestions[] = [
                'match_id' => $match_id,
                'opponent_team_id' => $opponent_team_id,
                'opponent_team_name' => $opponent_team_name,
                'last_match_date' => $schedule_date,
                'my_rating' => $satisfaction,
                'opponent_rating' => $opponent_rating,
                'opponent_satisfaction' => $opponent_satisfaction,
                'score' => ($satisfaction + $opponent_rating + $opponent_satisfaction) / 3 // 平均評価
            ];
        }

        // 評価が高い順、最近の試合順でソート
        usort($suggestions, function($a, $b) {
            if ($a['score'] != $b['score']) {
                return $b['score'] - $a['score'];
            }
            return strtotime($b['last_match_date']) - strtotime($a['last_match_date']);
        });

        // 制限数まで取得
        $suggestions = array_slice($suggestions, 0, $limit);

        error_log('[REMATCH_SUGGESTIONS_API] Success: Found ' . count($suggestions) . ' suggestions');

        return new WP_REST_Response([
            'success' => true,
            'data' => [
                'suggestions' => $suggestions,
                'count' => count($suggestions)
            ]
        ], 200);

    } catch (Exception $e) {
        error_log('[REMATCH_SUGGESTIONS_API] Exception: ' . $e->getMessage());
        return new WP_Error('server_error', 'サーバーエラーが発生しました', ['status' => 500]);
    }
}
