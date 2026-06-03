<?php
/**
 * 複数コート対応REST API
 */

// 直接実行を防ぐ
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 複数コート対応のREST APIエンドポイントを登録
 */
add_action('rest_api_init', function() {
    // コート管理API
    register_rest_route('aidunite/v1', '/multi-match/(?P<match_id>\d+)/courts', [
        'methods' => 'GET',
        'callback' => 'aidunite_api_get_courts',
        'permission_callback' => function($request) {
            return aidunite_check_multi_match_permission($request->get_param('match_id'));
        }
    ]);

    register_rest_route('aidunite/v1', '/multi-match/(?P<match_id>\d+)/courts', [
        'methods' => 'POST',
        'callback' => 'aidunite_api_manage_courts',
        'permission_callback' => function($request) {
            return aidunite_check_multi_match_permission($request->get_param('match_id'));
        }
    ]);

    // 複数コート対応試合生成API
    register_rest_route('aidunite/v1', '/multi-match/(?P<match_id>\d+)/generate-multi-court', [
        'methods' => 'POST',
        'callback' => 'aidunite_api_generate_multi_court_schedule',
        'permission_callback' => function($request) {
            return aidunite_check_multi_match_permission($request->get_param('match_id'));
        }
    ]);

    // コート別スケジュール取得API
    register_rest_route('aidunite/v1', '/multi-match/(?P<match_id>\d+)/court-schedule', [
        'methods' => 'GET',
        'callback' => 'aidunite_api_get_court_schedule',
        'permission_callback' => function($request) {
            return aidunite_check_multi_match_permission($request->get_param('match_id'));
        }
    ]);
});

/**
 * 複数チームマッチ権限チェック
 */
function aidunite_check_multi_match_permission($match_id) {
    if (!is_user_logged_in()) {
        return false;
    }

    $user_id = get_current_user_id();
    $organizer_team_id = (int) get_post_meta($match_id, 'organizer_team_id', true);
    if ($organizer_team_id > 0 && function_exists('aidunite_user_has_managed_team_access')
        && aidunite_user_has_managed_team_access($user_id, $organizer_team_id)) {
        return true;
    }

    // 後方互換: 単一 team_id のみの環境
    $team_id = function_exists('aidunite_get_current_team_id')
        ? (int) aidunite_get_current_team_id($user_id)
        : (int) get_user_meta($user_id, 'team_id', true);

    if (!$team_id) {
        return false;
    }

    // 主催者または管理者かチェック
    if ($organizer_team_id == $team_id) {
        return true;
    }

    // 管理者権限チェック
    if (current_user_can('manage_options')) {
        return true;
    }

    return false;
}

/**
 * コート情報取得API
 */
function aidunite_api_get_courts($request) {
    $match_id = intval($request->get_param('match_id'));

    $courts = aidunite_get_courts($match_id);
    $efficiency = aidunite_analyze_court_efficiency($match_id);

    // 各コートの試合数を取得
    foreach ($courts as &$court) {
        $court_matches = get_posts([
            'post_type' => 'multi_match_result',
            'meta_query' => [
                ['key' => 'multi_match_id', 'value' => $match_id],
                ['key' => 'court_id', 'value' => $court['id']]
            ],
            'post_status' => 'publish',
            'numberposts' => -1
        ]);

        $court['match_count'] = count($court_matches);
        $court['utilization_rate'] = isset($efficiency[$court['id']]) ? $efficiency[$court['id']]['utilization_rate'] : 0;
    }

    return new WP_REST_Response([
        'success' => true,
        'courts' => $courts,
        'efficiency' => $efficiency
    ], 200);
}

/**
 * コート管理API
 */
function aidunite_api_manage_courts($request) {
    $match_id = intval($request->get_param('match_id'));
    $params = $request->get_json_params();
    $action = $params['action'] ?? '';

    $courts = aidunite_get_courts($match_id);

    switch ($action) {
        case 'add_court':
            $court_name = sanitize_text_field($params['court_name'] ?? '');
            if (empty($court_name)) {
                return new WP_Error('invalid_court_name', 'コート名を入力してください。', ['status' => 400]);
            }

            // 新しいコートIDを生成
            $new_court_id = 1;
            if (!empty($courts)) {
                $new_court_id = max(array_column($courts, 'id')) + 1;
            }

            $courts[] = [
                'id' => $new_court_id,
                'name' => $court_name,
                'status' => 'available',
                'capacity' => 1
            ];

            aidunite_save_courts($match_id, $courts);

            return new WP_REST_Response([
                'success' => true,
                'message' => 'コートを追加しました。',
                'court_id' => $new_court_id
            ], 200);

        case 'edit_court':
            $court_id = intval($params['court_id'] ?? 0);
            $court_name = sanitize_text_field($params['court_name'] ?? '');

            if (empty($court_name)) {
                return new WP_Error('invalid_court_name', 'コート名を入力してください。', ['status' => 400]);
            }

            $court_found = false;
            foreach ($courts as &$court) {
                if ($court['id'] == $court_id) {
                    $court['name'] = $court_name;
                    $court_found = true;
                    break;
                }
            }

            if (!$court_found) {
                return new WP_Error('court_not_found', 'コートが見つかりません。', ['status' => 404]);
            }

            aidunite_save_courts($match_id, $courts);

            return new WP_REST_Response([
                'success' => true,
                'message' => 'コート名を更新しました。'
            ], 200);

        case 'delete_court':
            $court_id = intval($params['court_id'] ?? 0);

            // コートに試合が割り当てられているかチェック
            $court_matches = get_posts([
                'post_type' => 'multi_match_result',
                'meta_query' => [
                    ['key' => 'multi_match_id', 'value' => $match_id],
                    ['key' => 'court_id', 'value' => $court_id]
                ],
                'post_status' => 'publish',
                'numberposts' => 1
            ]);

            if (!empty($court_matches)) {
                return new WP_Error('court_has_matches', 'このコートには試合が割り当てられているため削除できません。', ['status' => 400]);
            }

            $courts = array_filter($courts, function($court) use ($court_id) {
                return $court['id'] != $court_id;
            });

            aidunite_save_courts($match_id, $courts);

            return new WP_REST_Response([
                'success' => true,
                'message' => 'コートを削除しました。'
            ], 200);

        default:
            return new WP_Error('invalid_action', '無効なアクションです。', ['status' => 400]);
    }
}

/**
 * 複数コート対応試合生成API
 */
function aidunite_api_generate_multi_court_schedule($request) {
    $match_id = intval($request->get_param('match_id'));
    $params = $request->get_json_params();
    $match_format = $params['match_format'] ?? 'sequential_matches';

    $result = aidunite_generate_multi_court_schedule($match_id, $match_format);

    if (is_wp_error($result)) {
        return new WP_Error($result->get_error_code(), $result->get_error_message(), ['status' => 400]);
    }

    return new WP_REST_Response($result, 200);
}

/**
 * コート別スケジュール取得API
 */
function aidunite_api_get_court_schedule($request) {
    $match_id = intval($request->get_param('match_id'));

    $court_schedules = aidunite_get_court_schedule($match_id);
    $efficiency = aidunite_analyze_court_efficiency($match_id);

    return new WP_REST_Response([
        'success' => true,
        'court_schedules' => $court_schedules,
        'efficiency' => $efficiency
    ], 200);
}
?>
