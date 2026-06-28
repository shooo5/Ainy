<?php
/**
 * 解約・出口ゲート REST API（§12B）
 *
 * @package AidUnite
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('rest_api_init', static function () {
    register_rest_route('aidunite/v1', '/payment-exit/evaluate', [
        'methods' => 'GET',
        'callback' => 'aidunite_rest_payment_exit_evaluate',
        'permission_callback' => static function ($request) {
            $result = AidUniteAuthMiddleware::rest_require($request, ['team_id' => null]);
            return !is_wp_error($result);
        },
        'args' => [
            'team_id' => [
                'required' => true,
                'type' => 'integer',
                'sanitize_callback' => 'absint',
            ],
        ],
    ]);

    register_rest_route('aidunite/v1', '/payment-exit/cancel', [
        'methods' => 'POST',
        'callback' => 'aidunite_rest_payment_exit_cancel',
        'permission_callback' => static function ($request) {
            $result = AidUniteAuthMiddleware::rest_require($request, ['team_id' => null]);
            return !is_wp_error($result);
        },
    ]);

    register_rest_route('aidunite/v1', '/payment-exit/first-match-prompt', [
        'methods' => 'GET',
        'callback' => 'aidunite_rest_payment_exit_first_match_prompt',
        'permission_callback' => static function () {
            return is_user_logged_in();
        },
        'args' => [
            'team_id' => [
                'required' => true,
                'type' => 'integer',
                'sanitize_callback' => 'absint',
            ],
        ],
    ]);

    register_rest_route('aidunite/v1', '/payment-exit/first-match-prompt', [
        'methods' => 'POST',
        'callback' => 'aidunite_rest_payment_exit_dismiss_first_match_prompt',
        'permission_callback' => static function () {
            return is_user_logged_in();
        },
    ]);
});

/**
 * @param int $user_id
 * @param int $team_id
 * @return true|WP_Error
 */
function aidunite_payment_exit_assert_team_manager($user_id, $team_id) {
    $user_id = (int) $user_id;
    $team_id = (int) $team_id;
    if ($team_id <= 0) {
        return new WP_Error('team_required', 'チームが指定されていません。', ['status' => 400]);
    }
    $leader = function_exists('aidunite_payment_exit_resolve_leader_user_id')
        ? aidunite_payment_exit_resolve_leader_user_id($team_id)
        : 0;
    if ($leader === $user_id) {
        return true;
    }
    $team = get_post($team_id);
    if ($team && (int) $team->post_author === $user_id) {
        return true;
    }
    if (function_exists('aidunite_user_has_managed_team_access') && aidunite_user_has_managed_team_access($user_id, $team_id)) {
        return true;
    }

    return new WP_Error('forbidden', 'このチームの解約手続きは代表者のみ可能です。', ['status' => 403]);
}

/**
 * @param WP_REST_Request $request
 */
function aidunite_rest_payment_exit_evaluate($request) {
    $user_id = get_current_user_id();
    $team_id = (int) $request->get_param('team_id');
    $auth = aidunite_payment_exit_assert_team_manager($user_id, $team_id);
    if (is_wp_error($auth)) {
        return $auth;
    }
    if (!function_exists('aidunite_payment_exit_evaluate_gates')) {
        return new WP_Error('not_configured', '出口ゲートが利用できません。', ['status' => 500]);
    }
    $gates = aidunite_payment_exit_evaluate_gates($team_id);
    $complete_at = aidunite_payment_exit_compute_completion_date($team_id, 'cancel');
    $pending = function_exists('aidunite_payment_exit_read_pending')
        ? aidunite_payment_exit_read_pending($team_id)
        : null;
    $subscription_id = function_exists('aidunite_get_team_stripe_subscription_id')
        ? aidunite_get_team_stripe_subscription_id($team_id, $user_id)
        : '';

    return new WP_REST_Response([
        'success' => true,
        'team_id' => $team_id,
        'gates' => $gates,
        'complete_at' => $complete_at,
        'pending' => $pending,
        'has_subscription' => $subscription_id !== '',
        'can_show_cancel_ui' => $subscription_id !== '' && $pending === null,
    ], 200);
}

/**
 * @param WP_REST_Request $request
 */
function aidunite_rest_payment_exit_cancel($request) {
    $user_id = get_current_user_id();
    $params = $request->get_json_params();
    if (!is_array($params)) {
        $params = [];
    }
    $team_id = (int) ($params['team_id'] ?? $request->get_param('team_id') ?? 0);
    $auth = aidunite_payment_exit_assert_team_manager($user_id, $team_id);
    if (is_wp_error($auth)) {
        return $auth;
    }
    if (!function_exists('aidunite_payment_exit_start_team_cancellation')) {
        return new WP_Error('not_configured', '解約処理が利用できません。', ['status' => 500]);
    }
    $result = aidunite_payment_exit_start_team_cancellation($team_id, $user_id);
    if (is_wp_error($result)) {
        return $result;
    }

    return new WP_REST_Response([
        'success' => true,
        'message' => '解約手続きを受け付けました。完了日まではご利用いただけます。',
        'data' => $result,
    ], 200);
}

/**
 * @param int $user_id
 * @param int $team_id
 * @return true|WP_Error
 */
function aidunite_payment_exit_assert_first_match_prompt_leader($user_id, $team_id) {
    $user_id = (int) $user_id;
    $team_id = (int) $team_id;
    if ($team_id <= 0) {
        return new WP_Error('team_required', 'チームが指定されていません。', ['status' => 400]);
    }
    if ($user_id <= 0) {
        return new WP_Error('unauthorized', 'ログインが必要です。', ['status' => 401]);
    }

    $leader = function_exists('aidunite_payment_exit_resolve_leader_user_id')
        ? aidunite_payment_exit_resolve_leader_user_id($team_id)
        : 0;
    if ($leader > 0 && $leader === $user_id) {
        return true;
    }

    if (function_exists('aidunite_get_effective_user_role')) {
        list($role,) = aidunite_get_effective_user_role($user_id);
        if ($role === 'team_leader' && function_exists('aidunite_get_current_team_id')
            && (int) aidunite_get_current_team_id($user_id) === $team_id) {
            return true;
        }
    }

    return new WP_Error('forbidden', 'この案内はチーム代表者のみ表示できます。', ['status' => 403]);
}

/**
 * @param WP_REST_Request $request
 */
function aidunite_rest_payment_exit_first_match_prompt($request) {
    $user_id = get_current_user_id();
    $team_id = (int) $request->get_param('team_id');
    $auth = aidunite_payment_exit_assert_first_match_prompt_leader($user_id, $team_id);
    if (is_wp_error($auth)) {
        return $auth;
    }
    if (!function_exists('aidunite_payment_exit_first_match_billing_prompt')) {
        return new WP_REST_Response(['success' => true, 'prompt' => null], 200);
    }
    $prompt = aidunite_payment_exit_first_match_billing_prompt($team_id);

    return new WP_REST_Response([
        'success' => true,
        'prompt' => $prompt,
    ], 200);
}

/**
 * @param WP_REST_Request $request
 */
function aidunite_rest_payment_exit_dismiss_first_match_prompt($request) {
    $user_id = get_current_user_id();
    $params = $request->get_json_params();
    if (!is_array($params)) {
        $params = [];
    }
    $team_id = (int) ($params['team_id'] ?? 0);
    $auth = aidunite_payment_exit_assert_first_match_prompt_leader($user_id, $team_id);
    if (is_wp_error($auth)) {
        return $auth;
    }

    $action = sanitize_key((string) ($params['action'] ?? 'later'));
    if ($action === 'payment_setup_snooze') {
        if (function_exists('aidunite_payment_exit_snooze_first_match_prompt_payment_setup')) {
            aidunite_payment_exit_snooze_first_match_prompt_payment_setup($team_id);
        }
    } elseif ($action === 'impression') {
        if (function_exists('aidunite_payment_exit_mark_first_match_prompt_impression')) {
            aidunite_payment_exit_mark_first_match_prompt_impression($team_id);
        }
    } else {
        if (function_exists('aidunite_payment_exit_mark_first_match_prompt_shown')) {
            aidunite_payment_exit_mark_first_match_prompt_shown($team_id);
        }
    }

    return new WP_REST_Response(['success' => true], 200);
}
