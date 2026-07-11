<?php
/**
 * マッチ分析 REST API
 * マッチ成立率の分析機能
 */

if (!defined('ABSPATH')) {
    exit;
}

// REST APIエンドポイント登録
add_action('rest_api_init', function() {
    register_rest_route('aidunite/v1', '/match-analytics', [
        'methods' => 'GET',
        'callback' => 'aidunite_get_match_analytics',
        'permission_callback' => function($request) {
            $result = AidUniteAuthMiddleware::rest_require($request, []);
            return !is_wp_error($result);
        }
    ]);
});

/**
 * マッチ分析データを取得
 */
function aidunite_get_match_analytics($request) {
    try {
        $current_user_id = get_current_user_id();
        $team_scope = aidunite_match_resolve_user_team_scope($current_user_id);

        if (empty($team_scope)) {
            error_log('[MATCH_ANALYTICS_API] Error: Team ID not found for user ' . $current_user_id);
            return new WP_Error('no_team', 'チームIDが設定されていません', ['status' => 400]);
        }

        $scope_val = count($team_scope) === 1 ? (int) $team_scope[0] : array_map('intval', $team_scope);
        $scope_cmp = count($team_scope) === 1 ? '=' : 'IN';

        error_log('[MATCH_ANALYTICS_API] Request: team_scope=' . wp_json_encode($team_scope));

        // 申請したマッチリクエストを取得
        $sent_requests = get_posts([
            'post_type' => 'match_request',
            'post_status' => 'any',
            'posts_per_page' => -1,
            'author' => $current_user_id,
            'meta_query' => [
                'relation' => 'AND',
                [
                    'relation' => 'OR',
                    [
                        'key' => 'from_team_id',
                        'value' => $scope_val,
                        'compare' => $scope_cmp,
                    ],
                    [
                        'key' => 'request_team_id',
                        'value' => $scope_val,
                        'compare' => $scope_cmp,
                    ]
                ],
                [
                    'key' => 'is_auto_match',
                    'compare' => 'NOT EXISTS'
                ]
            ],
            'orderby' => 'date',
            'order' => 'DESC'
        ]);

        $total_requests = count($sent_requests);
        $established_count = 0;
        $accepted_count = 0;
        $rejected_count = 0;
        $pending_count = 0;
        $cancelled_count = 0;

        $total_days_to_establish = 0;
        $established_requests = 0;

        $monthly_data = [];
        $match_score_data = [
            'best' => ['total' => 0, 'established' => 0],
            'green' => ['total' => 0, 'established' => 0],
            'yellow' => ['total' => 0, 'established' => 0],
            'no_preference' => ['total' => 0, 'established' => 0],
        ];

        $time_slot_data = [];
        $day_of_week_data = [];

        foreach ($sent_requests as $request) {
            $canonical = aidunite_match_request_get_canonical_meta((int) $request->ID);
            $status = (string) ($canonical['status'] ?? '申請中');
            if ($status === '') {
                $status = '申請中';
            }

            // ステータス別カウント
            if ($status === 'established' || $status === '試合確定') {
                $established_count++;
                $accepted_count++;
            } elseif ($status === 'accepted' || $status === '承認済み') {
                $accepted_count++;
            } elseif ($status === 'rejected' || $status === '拒否済み') {
                $rejected_count++;
            } elseif ($status === 'cancelled' || $status === 'キャンセル済み') {
                $cancelled_count++;
            } else {
                $pending_count++;
            }

            // 成立までの日数を計算
            if ($status === 'established' || $status === '試合確定' || $status === 'accepted' || $status === '承認済み') {
                $created_date = strtotime($request->post_date);
                $accepted_date = (string) ($canonical['accepted_at'] ?? '');
                if ($accepted_date) {
                    $accepted_timestamp = strtotime($accepted_date);
                    $days = ($accepted_timestamp - $created_date) / (24 * 60 * 60);
                    $total_days_to_establish += $days;
                    $established_requests++;
                }
            }

            // 月別データ
            $month = date('Y-m', strtotime($request->post_date));
            if (!isset($monthly_data[$month])) {
                $monthly_data[$month] = ['total' => 0, 'established' => 0];
            }
            $monthly_data[$month]['total']++;
            if ($status === 'established' || $status === '試合確定') {
                $monthly_data[$month]['established']++;
            }

            // マッチ度別データ（スケジュールIDから計算）
            $my_schedule_id = (int) ($canonical['my_schedule_id'] ?? 0);
            $other_schedule_id = (int) ($canonical['to_schedule_id'] ?? 0);
            if ($my_schedule_id <= 0) {
                $my_schedule_id = (int) ($canonical['from_schedule_id'] ?? 0);
            }

            if ($my_schedule_id && $other_schedule_id && function_exists('aidunite_evaluate_match_apply_context')) {
                $viewer_team = count($team_scope) === 1 ? (int) $team_scope[0] : (int) ($team_scope[0] ?? 0);
                $ev = aidunite_evaluate_match_apply_context(
                    (int) $other_schedule_id,
                    (int) $my_schedule_id,
                    ['mode' => 'preview']
                );
                $tier = is_array($ev) ? (string) ($ev['board_tier'] ?? 'yellow') : 'yellow';
                if ($tier === 'hidden') {
                    $tier = '';
                }
                if ($tier !== '' && isset($match_score_data[$tier])) {
                    $match_score_data[$tier]['total']++;
                    if ($status === 'established' || $status === '試合確定') {
                        $match_score_data[$tier]['established']++;
                    }
                }
            }

            // 時間帯別データ
            if ($other_schedule_id) {
                $other_api = function_exists('aidunite_schedule_get_api_display_fields')
                    ? aidunite_schedule_get_api_display_fields((int) $other_schedule_id)
                    : [];
                $start_time = (string) ($other_api['start_time'] ?? '');
                if ($start_time) {
                    $hour = intval(substr($start_time, 0, 2));
                    $time_slot = '';
                    if ($hour >= 16 && $hour < 18) {
                        $time_slot = '16:00-18:00';
                    } elseif ($hour >= 18 && $hour < 20) {
                        $time_slot = '18:00-20:00';
                    } elseif ($hour >= 20 && $hour < 22) {
                        $time_slot = '20:00-22:00';
                    }

                    if ($time_slot) {
                        if (!isset($time_slot_data[$time_slot])) {
                            $time_slot_data[$time_slot] = ['total' => 0, 'established' => 0];
                        }
                        $time_slot_data[$time_slot]['total']++;
                        if ($status === 'established' || $status === '試合確定') {
                            $time_slot_data[$time_slot]['established']++;
                        }
                    }
                }

                // 曜日別データ
                $schedule_date = (string) ($other_api['date'] ?? '');
                if ($schedule_date === '' && function_exists('aidunite_schedule_read_normalized_date')) {
                    $schedule_date = aidunite_schedule_read_normalized_date((int) $other_schedule_id);
                }
                if ($schedule_date) {
                    $day_of_week = date('w', strtotime($schedule_date));
                    $day_names = ['日', '月', '火', '水', '木', '金', '土'];
                    $day_name = $day_names[$day_of_week];

                    if (!isset($day_of_week_data[$day_name])) {
                        $day_of_week_data[$day_name] = ['total' => 0, 'established' => 0];
                    }
                    $day_of_week_data[$day_name]['total']++;
                    if ($status === 'established' || $status === '試合確定') {
                        $day_of_week_data[$day_name]['established']++;
                    }
                }
            }
        }

        // 成立率を計算
        $establishment_rate = $total_requests > 0 ? ($established_count / $total_requests) * 100 : 0;
        $average_days_to_establish = $established_requests > 0 ? $total_days_to_establish / $established_requests : 0;

        // 月別データをソート
        ksort($monthly_data);

        // マッチ度別の成立率を計算
        $match_score_rates = [];
        foreach ($match_score_data as $label => $data) {
            $rate = $data['total'] > 0 ? ($data['established'] / $data['total']) * 100 : 0;
            $match_score_rates[$label] = [
                'total' => $data['total'],
                'established' => $data['established'],
                'rate' => round($rate, 1)
            ];
        }

        // 時間帯別の成立率を計算
        $time_slot_rates = [];
        foreach ($time_slot_data as $slot => $data) {
            $rate = $data['total'] > 0 ? ($data['established'] / $data['total']) * 100 : 0;
            $time_slot_rates[$slot] = [
                'total' => $data['total'],
                'established' => $data['established'],
                'rate' => round($rate, 1)
            ];
        }

        // 曜日別の成立率を計算
        $day_of_week_rates = [];
        foreach ($day_of_week_data as $day => $data) {
            $rate = $data['total'] > 0 ? ($data['established'] / $data['total']) * 100 : 0;
            $day_of_week_rates[$day] = [
                'total' => $data['total'],
                'established' => $data['established'],
                'rate' => round($rate, 1)
            ];
        }

        error_log('[MATCH_ANALYTICS_API] Success: total_requests=' . $total_requests . ', established=' . $established_count);

        return new WP_REST_Response([
            'success' => true,
            'data' => [
                'basic_stats' => [
                    'total_requests' => $total_requests,
                    'established_count' => $established_count,
                    'accepted_count' => $accepted_count,
                    'rejected_count' => $rejected_count,
                    'pending_count' => $pending_count,
                    'cancelled_count' => $cancelled_count,
                    'establishment_rate' => round($establishment_rate, 1),
                    'average_days_to_establish' => round($average_days_to_establish, 1)
                ],
                'monthly_data' => $monthly_data,
                'match_score_rates' => $match_score_rates,
                'time_slot_rates' => $time_slot_rates,
                'day_of_week_rates' => $day_of_week_rates
            ]
        ], 200);

    } catch (Exception $e) {
        error_log('[MATCH_ANALYTICS_API] Exception: ' . $e->getMessage());
        return new WP_Error('server_error', 'サーバーエラーが発生しました', ['status' => 500]);
    }
}

