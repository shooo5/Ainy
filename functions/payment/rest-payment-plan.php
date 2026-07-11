<?php
/**
 * 決済プラン REST（read payload / upgrade）
 *
 * @see docs/spec/payment.md
 * @package AidUnite
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('rest_api_init', 'aidunite_register_rest_payment_plan_routes');

function aidunite_register_rest_payment_plan_routes() {
    register_rest_route('aidunite/v1', '/teams/(?P<team_id>\d+)/payment-plan', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'aidunite_rest_get_team_payment_plan',
        'permission_callback' => 'aidunite_rest_payment_plan_read_permission',
        'args' => [
            'team_id' => [
                'type' => 'integer',
                'required' => true,
            ],
        ],
    ]);

    register_rest_route('aidunite/v1', '/teams/(?P<team_id>\d+)/payment-plan/upgrade-club', [
        'methods' => WP_REST_Server::CREATABLE,
        'callback' => 'aidunite_rest_upgrade_team_payment_plan_club',
        'permission_callback' => 'aidunite_rest_payment_plan_manage_permission',
        'args' => [
            'team_id' => [
                'type' => 'integer',
                'required' => true,
            ],
        ],
    ]);

    register_rest_route('aidunite/v1', '/teams/(?P<team_id>\d+)/payment-plan/founding', [
        'methods' => WP_REST_Server::CREATABLE,
        'callback' => 'aidunite_rest_assign_founding_team',
        'permission_callback' => 'aidunite_rest_payment_plan_admin_permission',
        'args' => [
            'team_id' => [
                'type' => 'integer',
                'required' => true,
            ],
        ],
    ]);
}

/**
 * @param WP_REST_Request $request
 * @return bool|WP_Error
 */
function aidunite_rest_payment_plan_read_permission($request) {
    if (!is_user_logged_in()) {
        return new WP_Error('rest_forbidden', 'ログインが必要です。', ['status' => 401]);
    }

    $team_id = (int) $request->get_param('team_id');
    if ($team_id <= 0) {
        return new WP_Error('invalid_team', 'チームIDが不正です。', ['status' => 400]);
    }

    $user_id = get_current_user_id();
    if (function_exists('aidunite_user_is_privileged_admin') && aidunite_user_is_privileged_admin($user_id)) {
        return true;
    }

    $managed = function_exists('aidunite_get_managed_team_ids')
        ? aidunite_get_managed_team_ids($user_id)
        : [];
    if (in_array($team_id, array_map('intval', (array) $managed), true)) {
        return true;
    }

    return new WP_Error('rest_forbidden', '権限がありません。', ['status' => 403]);
}

/**
 * @param WP_REST_Request $request
 * @return bool|WP_Error
 */
function aidunite_rest_payment_plan_manage_permission($request) {
    if (!is_user_logged_in()) {
        return new WP_Error('rest_forbidden', 'ログインが必要です。', ['status' => 401]);
    }

    $team_id = (int) $request->get_param('team_id');
    $user_id = get_current_user_id();
    if (function_exists('aidunite_user_is_privileged_admin') && aidunite_user_is_privileged_admin($user_id)) {
        return true;
    }

    $managed = function_exists('aidunite_get_managed_team_ids')
        ? aidunite_get_managed_team_ids($user_id)
        : [];
    if (in_array($team_id, array_map('intval', (array) $managed), true)) {
        return true;
    }

    return new WP_Error('rest_forbidden', '権限がありません。', ['status' => 403]);
}

/**
 * @return bool|WP_Error
 */
function aidunite_rest_payment_plan_admin_permission() {
    if (!is_user_logged_in()) {
        return new WP_Error('rest_forbidden', 'ログインが必要です。', ['status' => 401]);
    }
    if (function_exists('aidunite_user_is_privileged_admin') && aidunite_user_is_privileged_admin(get_current_user_id())) {
        return true;
    }

    return new WP_Error('rest_forbidden', '管理者権限が必要です。', ['status' => 403]);
}

/**
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function aidunite_rest_get_team_payment_plan($request) {
    $team_id = (int) $request->get_param('team_id');
    if (!function_exists('aidunite_payment_read_team_payload')) {
        return new WP_Error('not_ready', '決済 read 層が読み込まれていません。', ['status' => 500]);
    }

    $payload = aidunite_payment_read_team_payload($team_id, get_current_user_id());
    if ($payload === []) {
        return new WP_Error('team_not_found', 'チームが見つかりません。', ['status' => 404]);
    }

    return rest_ensure_response(['success' => true, 'payload' => $payload]);
}

/**
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function aidunite_rest_upgrade_team_payment_plan_club($request) {
    $team_id = (int) $request->get_param('team_id');
    if (!function_exists('aidunite_payment_persist_upgrade_to_club')) {
        return new WP_Error('not_ready', '決済 persist 層が読み込まれていません。', ['status' => 500]);
    }

    if (!aidunite_payment_persist_upgrade_to_club($team_id)) {
        return new WP_Error('upgrade_failed', 'Clubプランへのアップグレードに失敗しました。', ['status' => 500]);
    }

    $payload = aidunite_payment_read_team_payload($team_id, get_current_user_id());

    return rest_ensure_response([
        'success' => true,
        'message' => 'Clubプランにアップグレードしました。',
        'payload' => $payload,
    ]);
}

/**
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function aidunite_rest_assign_founding_team($request) {
    $team_id = (int) $request->get_param('team_id');
    if (!function_exists('aidunite_payment_persist_assign_founding_team')) {
        return new WP_Error('not_ready', '決済 persist 層が読み込まれていません。', ['status' => 500]);
    }

    if (!aidunite_payment_persist_assign_founding_team($team_id)) {
        return new WP_Error(
            'founding_unavailable',
            'Founding Team の枠がいっぱいか、割り当てに失敗しました。',
            ['status' => 409]
        );
    }

    $payload = aidunite_payment_read_team_payload($team_id, get_current_user_id());

    return rest_ensure_response([
        'success' => true,
        'message' => 'Founding Team に割り当てました。',
        'payload' => $payload,
    ]);
}
