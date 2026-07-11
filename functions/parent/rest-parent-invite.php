<?php
/**
 * 保護者招待 REST API（submit/read の薄いラッパー）
 *
 * 画面は page テンプレートの POST を正本とする。
 * 以下 REST は将来の SPA / 外部連携用に温存。現行フロント JS からは未接続（2026-06）。
 *
 * @package AidUnite
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('rest_api_init', function () {
    // 未接続・温存: page-invite-guardian POST が正本
    register_rest_route('aidunite/v1', '/guardian-invite/send', [
        'methods' => 'POST',
        'callback' => 'aidunite_rest_parent_invite_send',
        'permission_callback' => 'aidunite_rest_parent_invite_send_permission',
    ]);

    // 未接続・温存: page-guardian-signup が正本
    register_rest_route('aidunite/v1', '/guardian-invite/signup-context', [
        'methods' => 'GET',
        'callback' => 'aidunite_rest_parent_invite_signup_context',
        'permission_callback' => '__return_true',
    ]);

    // 未接続・温存: page-guardian-signup POST が正本
    register_rest_route('aidunite/v1', '/guardian-invite/signup', [
        'methods' => 'POST',
        'callback' => 'aidunite_rest_parent_invite_signup',
        'permission_callback' => '__return_true',
    ]);

    // 未接続・温存: page-team-members が正本
    register_rest_route('aidunite/v1', '/guardian-invite/pending', [
        'methods' => 'GET',
        'callback' => 'aidunite_rest_parent_invite_pending',
        'permission_callback' => function () {
            return is_user_logged_in();
        },
    ]);

    // 未接続・温存: page-team-members POST が正本
    register_rest_route('aidunite/v1', '/guardian-invite/approve', [
        'methods' => 'POST',
        'callback' => 'aidunite_rest_parent_invite_approve',
        'permission_callback' => function () {
            return is_user_logged_in();
        },
    ]);

    // 未接続・温存: page-team-members POST が正本
    register_rest_route('aidunite/v1', '/guardian-invite/reject', [
        'methods' => 'POST',
        'callback' => 'aidunite_rest_parent_invite_reject',
        'permission_callback' => function () {
            return is_user_logged_in();
        },
    ]);
});

/**
 * 保護者招待送信 REST: チーム代表者のみ
 *
 * @return bool
 */
function aidunite_rest_parent_invite_send_permission() {
    if (!is_user_logged_in()) {
        return false;
    }
    if (!class_exists('AidUniteAuthMiddleware')) {
        require_once get_template_directory() . '/functions/common/auth-middleware.php';
    }
    $result = AidUniteAuthMiddleware::require_team_leader(null, false);

    return $result->is_valid();
}

/**
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function aidunite_rest_parent_invite_send(WP_REST_Request $request) {
    if (function_exists('aidunite_guardian_invite_check_rate_limit')) {
        $rate = aidunite_guardian_invite_check_rate_limit('send');
        if (is_wp_error($rate)) {
            return $rate;
        }
    }

    $params = $request->get_json_params();
    if (!is_array($params)) {
        $params = $request->get_params();
    }

    $result = aidunite_parent_submit_invite(
        is_array($params) ? $params : [],
        get_current_user_id()
    );

    return new WP_REST_Response([
        'ok' => !empty($result['ok']),
        'redirect' => (string) ($result['redirect'] ?? ''),
        'errors' => is_array($result['errors'] ?? null) ? $result['errors'] : [],
        'flash' => is_array($result['flash'] ?? null) ? $result['flash'] : [],
        'form_defaults' => is_array($result['form_defaults'] ?? null) ? $result['form_defaults'] : [],
    ], !empty($result['ok']) ? 200 : 400);
}

/**
 * @param WP_REST_Request $request
 * @return WP_REST_Response
 */
function aidunite_rest_parent_invite_signup_context(WP_REST_Request $request) {
    if (function_exists('aidunite_guardian_invite_check_rate_limit')) {
        $rate = aidunite_guardian_invite_check_rate_limit('signup_context');
        if (is_wp_error($rate)) {
            return $rate;
        }
    }

    $token = sanitize_text_field((string) $request->get_param('token'));
    $team_id = (int) $request->get_param('team_id');

    $context = aidunite_parent_get_signup_context($token, $team_id);

    return new WP_REST_Response($context, !empty($context['ok']) ? 200 : 400);
}

/**
 * @param WP_REST_Request $request
 * @return WP_REST_Response
 */
function aidunite_rest_parent_invite_signup(WP_REST_Request $request) {
    if (function_exists('aidunite_guardian_invite_check_rate_limit')) {
        $rate = aidunite_guardian_invite_check_rate_limit('signup');
        if (is_wp_error($rate)) {
            return $rate;
        }
    }

    $params = $request->get_json_params();
    if (!is_array($params)) {
        $params = $request->get_params();
    }

    $result = aidunite_parent_submit_guardian_signup(is_array($params) ? $params : []);

    return new WP_REST_Response([
        'ok' => !empty($result['ok']),
        'redirect' => (string) ($result['redirect'] ?? ''),
        'errors' => is_array($result['errors'] ?? null) ? $result['errors'] : [],
        'flash' => is_array($result['flash'] ?? null) ? $result['flash'] : [],
        'next_action' => (string) ($result['next_action'] ?? ''),
        'membership_status' => (string) ($result['membership_status'] ?? ''),
    ], !empty($result['ok']) ? 200 : 400);
}

/**
 * @param WP_REST_Request $request
 * @return WP_REST_Response
 */
function aidunite_rest_parent_invite_pending(WP_REST_Request $request) {
    $team_id = (int) $request->get_param('team_id');
    $context = aidunite_parent_get_pending_approval_context($team_id, get_current_user_id());

    return new WP_REST_Response($context, !empty($context['ok']) ? 200 : 403);
}

/**
 * @param WP_REST_Request $request
 * @return WP_REST_Response
 */
function aidunite_rest_parent_invite_approve(WP_REST_Request $request) {
    $params = $request->get_json_params();
    if (!is_array($params)) {
        $params = $request->get_params();
    }

    $result = aidunite_parent_submit_approve_parent(
        is_array($params) ? $params : [],
        get_current_user_id()
    );

    return new WP_REST_Response([
        'ok' => !empty($result['ok']),
        'redirect' => (string) ($result['redirect'] ?? ''),
        'errors' => is_array($result['errors'] ?? null) ? $result['errors'] : [],
        'flash' => is_array($result['flash'] ?? null) ? $result['flash'] : [],
    ], !empty($result['ok']) ? 200 : 400);
}

/**
 * @param WP_REST_Request $request
 * @return WP_REST_Response
 */
function aidunite_rest_parent_invite_reject(WP_REST_Request $request) {
    $params = $request->get_json_params();
    if (!is_array($params)) {
        $params = $request->get_params();
    }

    $result = aidunite_parent_submit_reject_parent(
        is_array($params) ? $params : [],
        get_current_user_id()
    );

    return new WP_REST_Response([
        'ok' => !empty($result['ok']),
        'redirect' => (string) ($result['redirect'] ?? ''),
        'errors' => is_array($result['errors'] ?? null) ? $result['errors'] : [],
        'flash' => is_array($result['flash'] ?? null) ? $result['flash'] : [],
    ], !empty($result['ok']) ? 200 : 400);
}
