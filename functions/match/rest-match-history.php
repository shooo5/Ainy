<?php
/**
 * マッチ申請履歴管理 REST API
 * フィルタリング、検索、ページネーション機能を提供
 */

if (!defined('ABSPATH')) {
    exit;
}

// REST APIエンドポイント登録
add_action('rest_api_init', function() {
    register_rest_route('aidunite/v1', '/match-history', [
        'methods' => 'GET',
        'callback' => 'aidunite_get_match_history',
        'permission_callback' => function($request) {
            $result = AidUniteAuthMiddleware::rest_require($request, []);
            return !is_wp_error($result);
        },
        'args' => [
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
            'status' => [
                'required' => false,
                'type' => 'string',
                'enum' => ['all', '申請中', '承認済み', '拒否済み', 'キャンセル済み', '試合確定'],
                'default' => 'all'
            ],
            'date_from' => [
                'required' => false,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field'
            ],
            'date_to' => [
                'required' => false,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field'
            ],
            'team_name' => [
                'required' => false,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field'
            ],
            'search' => [
                'required' => false,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field'
            ],
            'type' => [
                'required' => false,
                'type' => 'string',
                'enum' => ['sent', 'received', 'all'],
                'default' => 'all'
            ]
        ]
    ]);
});

/**
 * マッチ申請履歴を取得
 */
function aidunite_get_match_history($request) {
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
        return new WP_Error('no_team', 'チームIDが設定されていません', ['status' => 400]);
    }

    $scope_val = count($team_scope) === 1 ? (int) $team_scope[0] : array_map('intval', $team_scope);
    $scope_cmp = count($team_scope) === 1 ? '=' : 'IN';
    $schedule_team_clause = count($team_scope) === 1
        ? [
            'key' => 'team_id',
            'value' => (int) $team_scope[0],
        ]
        : [
            'key' => 'team_id',
            'value' => array_map('intval', $team_scope),
            'compare' => 'IN',
        ];

    $context_team_id = function_exists('aidunite_get_current_team_id')
        ? (int) aidunite_get_current_team_id($current_user_id)
        : (int) $team_scope[0];

    $params = $request->get_params();
    $page = isset($params['page']) ? intval($params['page']) : 1;
    $per_page = isset($params['per_page']) ? intval($params['per_page']) : 20;
    $status_filter = isset($params['status']) ? $params['status'] : 'all';
    $date_from = isset($params['date_from']) ? $params['date_from'] : null;
    $date_to = isset($params['date_to']) ? $params['date_to'] : null;
    $team_name = isset($params['team_name']) ? trim($params['team_name']) : '';
    $search = isset($params['search']) ? trim($params['search']) : '';
    $type = isset($params['type']) ? $params['type'] : 'all';

    // デフォルトの日付範囲（過去3ヶ月）
    if (!$date_from) {
        $date_from = date('Y-m-d', strtotime('-3 months'));
    }
    if (!$date_to) {
        $date_to = date('Y-m-d');
    }

    $results = [];

    // 申請した側のデータを取得
    if ($type === 'sent' || $type === 'all') {
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

        foreach ($sent_requests as $request) {
            $item = aidunite_format_match_history_item($request, 'sent', $context_team_id);
            if ($item) {
                $results[] = $item;
            }
        }
    }

    // 申請を受けた側のデータを取得
    if ($type === 'received' || $type === 'all') {
        $my_schedules = get_posts([
            'post_type' => 'schedule',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => [
                $schedule_team_clause,
            ]
        ]);

        if (!empty($my_schedules)) {
            $my_schedule_ids = array_map(function($schedule) {
                return $schedule->ID;
            }, $my_schedules);

            $received_requests = get_posts([
                'post_type' => 'match_request',
                'post_status' => 'any',
                'posts_per_page' => -1,
                'meta_query' => [
                    'relation' => 'AND',
                    [
                        'relation' => 'OR',
                        [
                            'key' => 'to_schedule_id',
                            'value' => $my_schedule_ids,
                            'compare' => 'IN'
                        ],
                        [
                            'key' => 'target_schedule_id',
                            'value' => $my_schedule_ids,
                            'compare' => 'IN'
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

            // 自分自身からの申請を除外（自チームが申請元の行）
            $received_requests = array_filter($received_requests, function($request) use ($team_scope) {
                $from_team_id = (int) get_post_meta($request->ID, 'from_team_id', true);
                return !in_array($from_team_id, $team_scope, true);
            });

            foreach ($received_requests as $request) {
                $item = aidunite_format_match_history_item($request, 'received', $context_team_id);
                if ($item) {
                    $results[] = $item;
                }
            }
        }
    }

    // フィルタリング
    $filtered_results = aidunite_filter_match_history($results, [
        'status' => $status_filter,
        'date_from' => $date_from,
        'date_to' => $date_to,
        'team_name' => $team_name,
        'search' => $search
    ]);

    // 日付でソート（新しい順）
    usort($filtered_results, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });

    // ページネーション
    $total = count($filtered_results);
    $total_pages = ceil($total / $per_page);
    $offset = ($page - 1) * $per_page;
    $paginated_results = array_slice($filtered_results, $offset, $per_page);

    return new WP_REST_Response([
        'success' => true,
        'data' => [
            'items' => $paginated_results,
            'pagination' => [
                'page' => $page,
                'per_page' => $per_page,
                'total' => $total,
                'total_pages' => $total_pages
            ]
        ]
    ], 200);
}

/**
 * マッチ申請履歴アイテムをフォーマット
 */
function aidunite_format_match_history_item($request, $type, $current_user_team_id) {
    $request_id = $request->ID;
    $status = get_post_meta($request_id, 'status', true) ?: '申請中';

    // スケジュールIDを取得
    $other_schedule_id = get_post_meta($request_id, 'to_schedule_id', true);
    $my_schedule_id = get_post_meta($request_id, 'my_schedule_id', true);
    if (!$my_schedule_id) {
        $my_schedule_id = get_post_meta($request_id, 'from_schedule_id', true);
    }

    // チームIDを取得
    $from_team_id = get_post_meta($request_id, 'from_team_id', true);
    $to_team_id = get_post_meta($request_id, 'to_team_id', true);

    // 相手チーム名を取得
    $opponent_team_id = ($type === 'sent') ? $to_team_id : $from_team_id;
    $opponent_team_name = $opponent_team_id ? get_the_title($opponent_team_id) : '（不明）';

    // スケジュール情報を取得
    $schedule_id = ($type === 'sent') ? $other_schedule_id : $my_schedule_id;
    $schedule_date = $schedule_id ? get_post_meta($schedule_id, 'schedule_date', true) : null;
    $schedule_start = $schedule_id ? get_post_meta($schedule_id, 'schedule_start_time', true) : '';
    $schedule_end = $schedule_id ? get_post_meta($schedule_id, 'schedule_end_time', true) : '';

    // 会場情報を取得
    $venue = $schedule_id ? get_post_meta($schedule_id, 'schedule_place', true) : '';
    if (!$venue) {
        $venue = $schedule_id ? get_post_meta($schedule_id, 'schedule_place_option', true) : '';
    }

    // 申請メッセージを取得
    $request_message = get_post_meta($request_id, 'request_message', true) ?: '';

    // 作成日時
    $created_at = get_the_date('Y-m-d H:i:s', $request_id);

    return [
        'id' => $request_id,
        'type' => $type,
        'status' => $status,
        'opponent_team_id' => $opponent_team_id,
        'opponent_team_name' => $opponent_team_name,
        'schedule_date' => $schedule_date,
        'schedule_start' => $schedule_start,
        'schedule_end' => $schedule_end,
        'venue' => $venue,
        'request_message' => $request_message,
        'created_at' => $created_at,
        'my_schedule_id' => $my_schedule_id,
        'other_schedule_id' => $other_schedule_id
    ];
}

/**
 * マッチ申請履歴をフィルタリング
 */
function aidunite_filter_match_history($items, $filters) {
    $filtered = $items;

    // ステータスフィルター
    if (isset($filters['status']) && $filters['status'] !== 'all') {
        $filtered = array_filter($filtered, function($item) use ($filters) {
            return $item['status'] === $filters['status'];
        });
    }

    // 日付範囲フィルター
    if (isset($filters['date_from']) && $filters['date_from']) {
        $filtered = array_filter($filtered, function($item) use ($filters) {
            return $item['schedule_date'] && $item['schedule_date'] >= $filters['date_from'];
        });
    }

    if (isset($filters['date_to']) && $filters['date_to']) {
        $filtered = array_filter($filtered, function($item) use ($filters) {
            return $item['schedule_date'] && $item['schedule_date'] <= $filters['date_to'];
        });
    }

    // チーム名フィルター
    if (isset($filters['team_name']) && $filters['team_name']) {
        $team_name = strtolower($filters['team_name']);
        $filtered = array_filter($filtered, function($item) use ($team_name) {
            return stripos(strtolower($item['opponent_team_name']), $team_name) !== false;
        });
    }

    // 検索フィルター
    if (isset($filters['search']) && $filters['search']) {
        $search_terms = explode(' ', trim($filters['search']));
        $filtered = array_filter($filtered, function($item) use ($search_terms) {
            $searchable_text = strtolower(
                $item['opponent_team_name'] . ' ' .
                $item['schedule_date'] . ' ' .
                $item['venue'] . ' ' .
                $item['request_message']
            );

            foreach ($search_terms as $term) {
                if (stripos($searchable_text, strtolower($term)) === false) {
                    return false;
                }
            }
            return true;
        });
    }

    return array_values($filtered);
}
