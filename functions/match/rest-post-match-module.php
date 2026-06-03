<?php
/**
 * 試合後モジュール REST API
 */

if (!defined('ABSPATH')) {
    exit;
}

// プレビュー HTML 生成は管理 UI と同じ関数を使う（REST 単体リクエストでも必須）
require_once dirname(__FILE__) . '/admin-post-match-module.php';

/**
 * 管理者プレビュー用
 */
function aidunite_rest_post_match_module_admin_can() {
    if (!is_user_logged_in()) {
        return false;
    }
    if (!class_exists('AidUniteAuthMiddleware')) {
        return current_user_can('administrator');
    }
    $result = AidUniteAuthMiddleware::require_admin(false);
    return $result->is_valid();
}

add_action('rest_api_init', static function () {
    register_rest_route('aidunite/v1', '/post-match-modules', [
        'methods'             => 'GET',
        'callback'            => 'aidunite_rest_get_post_match_modules',
        'permission_callback' => static function () {
            return is_user_logged_in();
        },
        'args'                => [
            'slot_key' => [
                'required' => true,
                'type'     => 'string',
            ],
            'match_id' => [
                'required' => false,
                'type'     => 'integer',
            ],
            'team_id'  => [
                'required' => false,
                'type'     => 'integer',
            ],
        ],
    ]);

    register_rest_route('aidunite/v1', '/post-match-modules/preview', [
        'methods'             => 'POST',
        'callback'            => 'aidunite_rest_preview_post_match_module',
        'permission_callback' => 'aidunite_rest_post_match_module_admin_can',
    ]);

    register_rest_route('aidunite/v1', '/post-match-modules/(?P<id>\d+)/preview', [
        'methods'             => 'GET',
        'callback'            => 'aidunite_rest_preview_post_match_module_by_id',
        'permission_callback' => 'aidunite_rest_post_match_module_admin_can',
        'args'                => [
            'id' => [
                'required' => true,
                'type'     => 'integer',
            ],
        ],
    ]);

    register_rest_route('aidunite/v1', '/post-match-modules/(?P<id>\d+)/interactions', [
        'methods'             => 'POST',
        'callback'            => 'aidunite_rest_post_match_module_interaction',
        'permission_callback' => static function () {
            return is_user_logged_in();
        },
        'args'                => [
            'id' => [
                'required' => true,
                'type'     => 'integer',
            ],
        ],
    ]);
});

/**
 * フォーム入力からプレビュー HTML
 *
 * @param WP_REST_Request $request
 */
function aidunite_rest_preview_post_match_module(WP_REST_Request $request) {
    try {
        $body = $request->get_json_params();
        if (!is_array($body)) {
            $body = [];
        }
        $args = aidunite_build_match_feedback_flow_preview_args($body);
        $html = aidunite_render_match_feedback_flow_preview($args);

        return new WP_REST_Response([
            'success' => true,
            'data'    => ['html' => $html],
        ], 200);
    } catch (Throwable $e) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[PMM_PREVIEW] ' . $e->getMessage());
        }
        return new WP_REST_Response([
            'success' => false,
            'message' => 'プレビュー生成に失敗しました: ' . $e->getMessage(),
        ], 500);
    }
}

/**
 * 保存済みモジュールのプレビュー HTML
 *
 * @param WP_REST_Request $request
 */
function aidunite_rest_preview_post_match_module_by_id(WP_REST_Request $request) {
    try {
        $module_id = (int) $request->get_param('id');
        $post = get_post($module_id);
        if (!$post || $post->post_type !== 'post_match_module') {
            return new WP_REST_Response(['success' => false, 'message' => 'module not found'], 404);
        }

        $args = aidunite_build_match_feedback_flow_preview_args_from_id($module_id);
        if ($args === null) {
            return new WP_REST_Response(['success' => false, 'message' => 'module not found'], 404);
        }

        $html = aidunite_render_match_feedback_flow_preview($args);

        return new WP_REST_Response([
            'success' => true,
            'data'    => ['html' => $html],
        ], 200);
    } catch (Throwable $e) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[PMM_PREVIEW] ' . $e->getMessage());
        }
        return new WP_REST_Response([
            'success' => false,
            'message' => 'プレビュー生成に失敗しました: ' . $e->getMessage(),
        ], 500);
    }
}

/**
 * @param WP_REST_Request $request
 */
function aidunite_rest_get_post_match_modules(WP_REST_Request $request) {
    $user_id = get_current_user_id();
    $team_id = (int) $request->get_param('team_id');
    $match_id = (int) $request->get_param('match_id');
    $slot_key = sanitize_key($request->get_param('slot_key'));

    if ($team_id <= 0 && function_exists('aidunite_get_current_team_id')) {
        $team_id = (int) aidunite_get_current_team_id($user_id);
    }

    $context = aidunite_build_post_match_module_context($user_id, $team_id, $match_id);
    $modules = aidunite_get_post_match_modules_for_slot($slot_key, $context);

    return new WP_REST_Response([
        'success' => true,
        'data'    => [
            'modules' => $modules,
            'count'   => count($modules),
        ],
    ], 200);
}

/**
 * @param WP_REST_Request $request
 */
function aidunite_rest_post_match_module_interaction(WP_REST_Request $request) {
    $module_id = (int) $request->get_param('id');
    $post = get_post($module_id);
    if (!$post || $post->post_type !== 'post_match_module' || $post->post_status !== 'publish') {
        return new WP_REST_Response(['success' => false, 'message' => 'module not found'], 404);
    }

    $body = $request->get_json_params();
    if (!is_array($body)) {
        $body = [];
    }
    $action = isset($body['action']) ? sanitize_key($body['action']) : '';
    $team_id = isset($body['team_id']) ? (int) $body['team_id'] : 0;
    $match_id = isset($body['match_id']) ? (int) $body['match_id'] : 0;

    $user_id = get_current_user_id();
    if ($team_id <= 0 && function_exists('aidunite_get_current_team_id')) {
        $team_id = (int) aidunite_get_current_team_id($user_id);
    }

    $log_id = aidunite_post_match_module_log_insert($module_id, $action, $user_id, $team_id, $match_id);
    if (!$log_id) {
        return new WP_REST_Response(['success' => false, 'message' => 'invalid action or params'], 400);
    }

    return new WP_REST_Response([
        'success' => true,
        'data'    => ['log_id' => $log_id],
    ], 201);
}
