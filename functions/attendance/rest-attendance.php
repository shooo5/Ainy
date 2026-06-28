<?php
/**
 * 出欠 REST API
 *
 * @package AidUnite
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * REST: 出欠回答保存（マイページ TODO インライン回答）
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function aidunite_rest_save_attendance($request) {
    require_once get_template_directory() . '/functions/common/auth-middleware.php';
    $auth_result = AidUniteAuthMiddleware::rest_require($request, [
        'roles' => ['parent', 'player'],
    ]);
    if (is_wp_error($auth_result)) {
        return $auth_result;
    }

    $params = $request->get_json_params();
    if (!is_array($params)) {
        return new WP_Error('invalid_params', '無効なリクエストです', ['status' => 400]);
    }

    $schedule_id = (int) ($params['schedule_id'] ?? $params['id'] ?? 0);
    $status = sanitize_text_field((string) ($params['status'] ?? ''));
    $note = isset($params['note']) ? sanitize_textarea_field((string) $params['note']) : '';

    if ($schedule_id <= 0 || $status === '') {
        return new WP_Error('invalid_params', 'schedule_id と status が必要です', ['status' => 400]);
    }

    $user_id = (int) $auth_result->user_id;
    $team_id = 0;
    if (function_exists('aidunite_schedule_read_team_id')) {
        $team_id = (int) aidunite_schedule_read_team_id($schedule_id);
    }
    $respondent_id = function_exists('aidunite_attendance_resolve_respondent_user_id')
        ? aidunite_attendance_resolve_respondent_user_id($user_id, $team_id)
        : $user_id;
    if ($respondent_id <= 0) {
        return new WP_Error('invalid_respondent', '出欠回答対象の選手が見つかりません', ['status' => 400]);
    }

    $sch_att = function_exists('aidunite_schedule_get_canonical_meta')
        ? aidunite_schedule_get_canonical_meta($schedule_id)
        : [];
    $schedule_date = (string) ($sch_att['schedule_date'] ?? $sch_att['date'] ?? '');
    if ($schedule_date === '' && function_exists('aidunite_schedule_read_normalized_date')) {
        $schedule_date = (string) aidunite_schedule_read_normalized_date($schedule_id);
    }
    $deadline_at = function_exists('aidunite_attendance_policy_get_response_deadline')
        ? aidunite_attendance_policy_get_response_deadline($schedule_date)
        : '';
    if (function_exists('aidunite_attendance_is_response_open')
        && !aidunite_attendance_is_response_open($schedule_date, $deadline_at)) {
        $msg = function_exists('aidunite_attendance_response_closed_message')
            ? aidunite_attendance_response_closed_message(
                aidunite_attendance_response_closed_reason($schedule_date, $deadline_at)
            )
            : '回答期限を過ぎています';
        return new WP_Error('response_closed', $msg, ['status' => 403]);
    }

    $saved = aidunite_save_attendance($schedule_id, $respondent_id, $status, $note);
    if (!$saved) {
        return new WP_Error('save_failed', '出欠回答の保存に失敗しました', ['status' => 400]);
    }

    return rest_ensure_response([
        'success' => true,
        'schedule_id' => $schedule_id,
    ]);
}

add_action('rest_api_init', function () {
    register_rest_route('aidunite/v1', '/attendance', [
        'methods' => 'POST',
        'callback' => 'aidunite_rest_save_attendance',
        'permission_callback' => function () {
            return is_user_logged_in();
        },
    ]);
});
