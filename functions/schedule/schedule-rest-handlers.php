<?php
/**
 * スケジュール REST ハンドラ（更新・削除・依存参照）
 *
 * 正ルート: register-schedule-v2 / update-schedule-v2 / delete-schedule-v2（schedule-registration.php + persist）
 * 旧ルート register-schedules / update-schedule / delete-schedule は削除済み（2026-06-02）
 *
 * @package AidUnite
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('rest_api_init', function () {
    register_rest_route('aidunite/v1', '/schedule-dependencies/(?P<schedule_id>\\d+)', [
        'methods' => 'GET',
        'callback' => 'aidunite_rest_get_schedule_dependencies',
        'permission_callback' => function () {
            require_once get_template_directory() . '/functions/common/auth-middleware.php';
            $auth_result = AidUniteAuthMiddleware::require_auth(false);

            return $auth_result->is_valid();
        },
    ]);
});

function aidunite_update_schedule($request) {
    $nonce = $request->get_header('X-WP-Nonce');
    if (!$nonce) {
        $params = $request->get_json_params();
        $nonce = isset($params['nonce']) ? $params['nonce'] : '';
    }
    if (!$nonce || !wp_verify_nonce($nonce, 'wp_rest')) {
        return new WP_Error('invalid_nonce', 'セキュリティトークンが無効です', ['status' => 403]);
    }

    $current_user_id = get_current_user_id();
    if (!$current_user_id) {
        return new WP_Error('unauthorized', 'ログインが必要です', ['status' => 401]);
    }

    $params = $request->get_json_params();
    if (!is_array($params)) {
        return new WP_Error('invalid_params', '無効なパラメータ形式です', ['status' => 400]);
    }

    if (!isset($params['post_id'])) {
        return new WP_Error('invalid_params', '必須フィールド post_id が不足しています', ['status' => 400]);
    }

    $post_id = (int) $params['post_id'];
    if ($post_id <= 0 || get_post_type($post_id) !== 'schedule') {
        return new WP_Error('invalid_id', '対象のスケジュールが見つかりません', ['status' => 400]);
    }

    require_once get_template_directory() . '/functions/common/auth-middleware.php';
    if (!function_exists('aidunite_schedule_read_team_id')) {
        require_once get_stylesheet_directory() . '/functions/schedule/schedule-persist-read.php';
    }
    $schedule_team_id = (int) aidunite_schedule_read_team_id($post_id);
    $auth_result = AidUniteAuthMiddleware::require_team_leader($schedule_team_id, false);
    if (!$auth_result->is_valid()) {
        $schedule_author_id = (int) get_post_field('post_author', $post_id);
        if ($schedule_author_id !== $current_user_id) {
            return new WP_Error('forbidden', $auth_result->error ?: 'このスケジュールを編集する権限がありません', ['status' => 403]);
        }
    }

    if (function_exists('aidunite_can_update_schedule')) {
        $edit_gate = aidunite_can_update_schedule($post_id, $params);
        if (is_wp_error($edit_gate)) {
            return $edit_gate;
        }
    }

    if (!function_exists('aidunite_schedule_merge_legacy_params_for_persist')) {
        require_once get_stylesheet_directory() . '/functions/schedule/schedule-persist.php';
    }

    $persist_data = aidunite_schedule_merge_legacy_params_for_persist($params, $post_id);
    $update_result = aidunite_schedule_update_published_post($post_id, $persist_data);
    if (is_wp_error($update_result)) {
        return $update_result;
    }

    $payload = function_exists('aidunite_schedule_get_rest_calendar_payload')
        ? aidunite_schedule_get_rest_calendar_payload($post_id, $current_user_id)
        : [];

    return rest_ensure_response([
        'success' => true,
        'schedule' => $payload,
    ]);
}

function aidunite_delete_schedule($request) {
    $current_user_id = get_current_user_id();
    if (!$current_user_id) {
        return new WP_Error('unauthorized', 'ログインが必要です', ['status' => 401]);
    }

    $header_nonce = $request->get_header('X-WP-Nonce');
    $param_nonce = (string) $request->get_param('nonce');
    $nonce_ok = ($header_nonce && wp_verify_nonce($header_nonce, 'wp_rest'))
        || ($param_nonce && wp_verify_nonce($param_nonce, 'wp_rest'))
        || ($param_nonce && wp_verify_nonce($param_nonce, 'aidunite_schedule_nonce'));
    if (!$nonce_ok) {
        return new WP_Error('invalid_nonce', 'セキュリティトークンが無効です', ['status' => 403]);
    }

    $params = $request->get_json_params();
    if (!is_array($params) || $params === []) {
        $params = $request->get_params();
    }
    if (!is_array($params)) {
        $params = [];
    }

    $post_id = 0;
    if (!empty($params['post_id'])) {
        $post_id = (int) $params['post_id'];
    } elseif (!empty($params['schedule_id'])) {
        $post_id = (int) $params['schedule_id'];
    } else {
        $post_id = (int) $request->get_param('post_id');
        if ($post_id <= 0) {
            $post_id = (int) $request->get_param('schedule_id');
        }
    }

    if ($post_id <= 0 || get_post_type($post_id) !== 'schedule') {
        return new WP_Error('invalid_id', '対象のスケジュールが見つかりません', ['status' => 400]);
    }

    if (!user_can($current_user_id, 'delete_post', $post_id)) {
        $aidunite_role = get_user_meta($current_user_id, 'aidunite_role', true);
        $is_leader_or_admin = in_array($aidunite_role, ['team_leader', 'administrator'], true) || current_user_can('administrator');
        if (!$is_leader_or_admin) {
            return new WP_Error('forbidden', 'その操作を実行する権限がありません。', ['status' => 403]);
        }
        $schedule_author_id = (int) get_post_field('post_author', $post_id);
        if (!function_exists('aidunite_schedule_read_team_id')) {
            require_once get_stylesheet_directory() . '/functions/schedule/schedule-persist-read.php';
        }
        $schedule_team_id = (int) aidunite_schedule_read_team_id($post_id);
        $same_team = $schedule_team_id > 0
            && function_exists('aidunite_user_has_managed_team_access')
            && aidunite_user_has_managed_team_access($current_user_id, $schedule_team_id);
        if (!$same_team && $schedule_team_id > 0 && !function_exists('aidunite_user_has_managed_team_access')) {
            $user_team_id = (int) get_user_meta($current_user_id, 'team_id', true);
            $same_team = $user_team_id > 0 && $user_team_id === $schedule_team_id;
        }
        if ($schedule_author_id !== $current_user_id && !$same_team) {
            return new WP_Error('forbidden', 'このスケジュールを削除する権限がありません', ['status' => 403]);
        }
    }

    if (!function_exists('aidunite_can_delete_schedule') || !function_exists('aidunite_perform_safe_schedule_deletion')) {
        return new WP_Error('not_available', 'スケジュール依存ガードが読み込めません。', ['status' => 500]);
    }

    $block = aidunite_can_delete_schedule($post_id);
    if (is_wp_error($block)) {
        return $block;
    }

    $result = aidunite_perform_safe_schedule_deletion($post_id);
    if (is_wp_error($result)) {
        return $result;
    }

    return rest_ensure_response([
        'success' => true,
        'data' => is_array($result) ? $result : [],
        'message' => 'スケジュールを削除しました。',
    ]);
}

/**
 * 依存状況の参照用 GET（確認モーダル・事後拡張用。書き込みなし）
 */
function aidunite_rest_get_schedule_dependencies($request) {
    $schedule_id = (int) $request->get_param('schedule_id');
    if ($schedule_id <= 0 || get_post_type($schedule_id) !== 'schedule') {
        return new WP_Error('invalid_id', '対象のスケジュールが見つかりません', ['status' => 400]);
    }
    $uid = get_current_user_id();
    if (!$uid) {
        return new WP_Error('unauthorized', 'ログインが必要です', ['status' => 401]);
    }
    if (!user_can($uid, 'delete_post', $schedule_id) && !current_user_can('administrator') && !current_user_can('manage_options')) {
        $schedule_author_id = (int) get_post_field('post_author', $schedule_id);
        if (!function_exists('aidunite_schedule_read_team_id')) {
            require_once get_stylesheet_directory() . '/functions/schedule/schedule-persist-read.php';
        }
        $schedule_team_id = (int) aidunite_schedule_read_team_id($schedule_id);
        $same_team = $schedule_team_id > 0
            && function_exists('aidunite_user_has_managed_team_access')
            && aidunite_user_has_managed_team_access($uid, $schedule_team_id);
        if (!$same_team && $schedule_team_id > 0 && !function_exists('aidunite_user_has_managed_team_access')) {
            $user_team_id = (int) get_user_meta($uid, 'team_id', true);
            $same_team = $user_team_id > 0 && $user_team_id === $schedule_team_id;
        }
        if ($schedule_author_id !== $uid && !$same_team) {
            return new WP_Error('forbidden', '閲覧権限がありません。', ['status' => 403]);
        }
    }
    if (!function_exists('aidunite_get_schedule_dependencies')) {
        return new WP_Error('not_available', '依存関数が未読み込みです。', ['status' => 500]);
    }
    $dep = aidunite_get_schedule_dependencies($schedule_id);
    $gate = function_exists('aidunite_get_schedule_delete_gate_array')
        ? aidunite_get_schedule_delete_gate_array($schedule_id)
        : ['allowed' => true];

    return rest_ensure_response(['success' => true, 'delete_gate' => $gate, 'dependencies' => $dep]);
}
