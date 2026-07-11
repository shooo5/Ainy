<?php
/**
 * 大会・イベント REST API（persist submit/read の薄いラッパー）
 *
 * @package AidUnite
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * @param array<string, mixed> $data
 * @param int                  $status
 * @return WP_REST_Response
 */
function aidunite_rest_competition_success(array $data, $status = 200) {
    return new WP_REST_Response([
        'success' => true,
        'data' => $data,
    ], $status);
}

/**
 * @param string $code
 * @param string $message
 * @return WP_Error
 */
function aidunite_rest_competition_error($code, $message) {
    $http_map = [
        'forbidden' => 403,
        'not_found' => 404,
        'invalid_params' => 400,
        'recruitment_closed' => 409,
        'duplicate_entry' => 409,
        'invalid_transition' => 409,
        'generator_not_supported' => 501,
        'generator_constraint_failed' => 409,
        'save_failed' => 500,
    ];

    return new WP_Error($code, $message, [
        'status' => $http_map[$code] ?? 400,
    ]);
}

/**
 * @param array{ok?: bool, code?: string, error?: string} $result
 * @return WP_REST_Response|WP_Error
 */
function aidunite_rest_competition_from_submit_result(array $result) {
    if (!empty($result['ok'])) {
        unset($result['ok']);
        return aidunite_rest_competition_success($result);
    }

    return aidunite_rest_competition_error(
        (string) ($result['code'] ?? 'invalid_params'),
        (string) ($result['error'] ?? '処理に失敗しました')
    );
}

add_action('rest_api_init', function () {
    register_rest_route('aidunite/v1', '/competition/events', [
        [
            'methods' => 'GET',
            'callback' => 'aidunite_rest_competition_list_events',
            'permission_callback' => 'aidunite_rest_competition_operate_permission',
        ],
        [
            'methods' => 'POST',
            'callback' => 'aidunite_rest_competition_create_event',
            'permission_callback' => 'aidunite_rest_competition_operate_permission',
        ],
    ]);

    register_rest_route('aidunite/v1', '/competition/events/(?P<id>\d+)', [
        [
            'methods' => 'GET',
            'callback' => 'aidunite_rest_competition_get_event',
            'permission_callback' => 'aidunite_rest_competition_view_event_permission',
        ],
        [
            'methods' => 'PATCH',
            'callback' => 'aidunite_rest_competition_update_event',
            'permission_callback' => 'aidunite_rest_competition_operate_permission',
        ],
    ]);

    register_rest_route('aidunite/v1', '/competition/events/(?P<id>\d+)/publish', [
        'methods' => 'POST',
        'callback' => 'aidunite_rest_competition_publish_event',
        'permission_callback' => 'aidunite_rest_competition_operate_permission',
    ]);

    register_rest_route('aidunite/v1', '/competition/events/(?P<id>\d+)/invite', [
        'methods' => 'POST',
        'callback' => 'aidunite_rest_competition_invite_teams',
        'permission_callback' => 'aidunite_rest_competition_operate_permission',
    ]);

    register_rest_route('aidunite/v1', '/competition/events/(?P<id>\d+)/entries', [
        'methods' => 'GET',
        'callback' => 'aidunite_rest_competition_list_entries',
        'permission_callback' => 'aidunite_rest_competition_operate_permission',
    ]);

    register_rest_route('aidunite/v1', '/competition/events/(?P<id>\d+)/blocks', [
        [
            'methods' => 'GET',
            'callback' => 'aidunite_rest_competition_list_blocks',
            'permission_callback' => 'aidunite_rest_competition_operate_permission',
        ],
        [
            'methods' => 'POST',
            'callback' => 'aidunite_rest_competition_create_block',
            'permission_callback' => 'aidunite_rest_competition_operate_permission',
        ],
    ]);

    register_rest_route('aidunite/v1', '/competition/events/(?P<id>\d+)/awards', [
        'methods' => 'GET',
        'callback' => 'aidunite_rest_competition_get_awards',
        'permission_callback' => 'aidunite_rest_competition_view_event_permission',
    ]);

    register_rest_route('aidunite/v1', '/competition/entries/mine', [
        'methods' => 'GET',
        'callback' => 'aidunite_rest_competition_entries_mine',
        'permission_callback' => function () {
            return is_user_logged_in();
        },
    ]);

    register_rest_route('aidunite/v1', '/competition/entries/(?P<id>\d+)', [
        'methods' => 'GET',
        'callback' => 'aidunite_rest_competition_get_entry',
        'permission_callback' => 'aidunite_rest_competition_view_entry_permission',
    ]);

    register_rest_route('aidunite/v1', '/competition/entries/(?P<id>\d+)/respond', [
        'methods' => 'POST',
        'callback' => 'aidunite_rest_competition_respond_entry',
        'permission_callback' => function () {
            return is_user_logged_in();
        },
    ]);

    register_rest_route('aidunite/v1', '/competition/entries/(?P<id>\d+)/payment', [
        'methods' => 'PATCH',
        'callback' => 'aidunite_rest_competition_update_payment',
        'permission_callback' => 'aidunite_rest_competition_operate_permission',
    ]);

    register_rest_route('aidunite/v1', '/competition/entries/(?P<id>\d+)/approve', [
        'methods' => 'POST',
        'callback' => 'aidunite_rest_competition_approve_entry',
        'permission_callback' => 'aidunite_rest_competition_operate_permission',
    ]);

    register_rest_route('aidunite/v1', '/competition/entries/(?P<id>\d+)/checkout', [
        'methods' => 'POST',
        'callback' => 'aidunite_rest_competition_entry_checkout',
        'permission_callback' => function () {
            return is_user_logged_in();
        },
    ]);

    register_rest_route('aidunite/v1', '/competition/generator/templates', [
        'methods' => 'GET',
        'callback' => 'aidunite_rest_competition_generator_templates',
        'permission_callback' => 'aidunite_rest_competition_operate_permission',
    ]);

    register_rest_route('aidunite/v1', '/competition/ops/summary', [
        'methods' => 'GET',
        'callback' => 'aidunite_rest_competition_ops_summary',
        'permission_callback' => 'aidunite_rest_competition_operate_permission',
    ]);

    register_rest_route('aidunite/v1', '/competition/ops/alerts', [
        'methods' => 'GET',
        'callback' => 'aidunite_rest_competition_ops_alerts',
        'permission_callback' => 'aidunite_rest_competition_operate_permission',
    ]);

    register_rest_route('aidunite/v1', '/competition/events/(?P<id>\d+)/fixtures/generate', [
        'methods' => 'POST',
        'callback' => 'aidunite_rest_competition_generate_fixtures',
        'permission_callback' => 'aidunite_rest_competition_operate_permission',
    ]);

    register_rest_route('aidunite/v1', '/competition/events/(?P<id>\d+)/fixtures', [
        'methods' => 'GET',
        'callback' => 'aidunite_rest_competition_list_fixtures',
        'permission_callback' => 'aidunite_rest_competition_view_event_permission',
    ]);

    register_rest_route('aidunite/v1', '/competition/events/(?P<id>\d+)/standings', [
        'methods' => 'GET',
        'callback' => 'aidunite_rest_competition_standings',
        'permission_callback' => 'aidunite_rest_competition_view_event_permission',
    ]);

    register_rest_route('aidunite/v1', '/competition/events/(?P<id>\d+)/bracket/public', [
        'methods' => 'GET',
        'callback' => 'aidunite_rest_competition_public_bracket',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('aidunite/v1', '/competition/fixtures/(?P<id>\d+)', [
        'methods' => 'PATCH',
        'callback' => 'aidunite_rest_competition_update_fixture',
        'permission_callback' => 'aidunite_rest_competition_operate_permission',
    ]);

    register_rest_route('aidunite/v1', '/competition/fixtures/(?P<id>\d+)/result', [
        'methods' => 'POST',
        'callback' => 'aidunite_rest_competition_fixture_result',
        'permission_callback' => 'aidunite_rest_competition_operate_permission',
    ]);

    register_rest_route('aidunite/v1', '/competition/public/events/(?P<slug>[a-z0-9\-]+)', [
        'methods' => 'GET',
        'callback' => 'aidunite_rest_competition_public_event_by_slug',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('aidunite/v1', '/competition/events/(?P<id>\d+)/refund-policy', [
        'methods' => 'GET',
        'callback' => 'aidunite_rest_competition_refund_policy',
        'permission_callback' => 'aidunite_rest_competition_refund_policy_permission',
    ]);

    register_rest_route('aidunite/v1', '/competition/entries/(?P<id>\d+)/refund', [
        'methods' => 'POST',
        'callback' => 'aidunite_rest_competition_entry_refund',
        'permission_callback' => 'aidunite_rest_competition_operate_permission',
    ]);
});

/**
 * @return bool
 */
function aidunite_rest_competition_operate_permission() {
    return is_user_logged_in() && aidunite_competition_user_can_operate(get_current_user_id());
}

/**
 * @param WP_REST_Request $request
 * @return bool
 */
function aidunite_rest_competition_view_event_permission(WP_REST_Request $request) {
    if (!is_user_logged_in()) {
        return false;
    }

    return aidunite_competition_user_can_view_event(get_current_user_id(), (int) $request['id']);
}

/**
 * @param WP_REST_Request $request
 * @return bool
 */
function aidunite_rest_competition_view_entry_permission(WP_REST_Request $request) {
    if (!is_user_logged_in()) {
        return false;
    }

    return aidunite_competition_user_can_manage_entry(get_current_user_id(), (int) $request['id']);
}

/**
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function aidunite_rest_competition_list_events(WP_REST_Request $request) {
    return aidunite_rest_competition_success([
        'items' => aidunite_competition_read_events_list([
            'viewer_user_id' => get_current_user_id(),
        ]),
    ]);
}

/**
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function aidunite_rest_competition_create_event(WP_REST_Request $request) {
    $params = $request->get_json_params();
    if (!is_array($params)) {
        $params = $request->get_params();
    }

    return aidunite_rest_competition_from_submit_result(
        aidunite_competition_submit_create_event(is_array($params) ? $params : [])
    );
}

/**
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function aidunite_rest_competition_get_event(WP_REST_Request $request) {
    $event_id = (int) $request['id'];
    $payload = aidunite_competition_read_event_payload($event_id);
    if (empty($payload)) {
        return aidunite_rest_competition_error('not_found', 'イベントが見つかりません');
    }

    return aidunite_rest_competition_success(['event' => $payload]);
}

/**
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function aidunite_rest_competition_update_event(WP_REST_Request $request) {
    $params = $request->get_json_params();
    if (!is_array($params)) {
        $params = $request->get_params();
    }

    return aidunite_rest_competition_from_submit_result(
        aidunite_competition_submit_update_event((int) $request['id'], is_array($params) ? $params : [])
    );
}

/**
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function aidunite_rest_competition_publish_event(WP_REST_Request $request) {
    return aidunite_rest_competition_from_submit_result(
        aidunite_competition_submit_publish_event((int) $request['id'])
    );
}

/**
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function aidunite_rest_competition_invite_teams(WP_REST_Request $request) {
    $params = $request->get_json_params();
    if (!is_array($params)) {
        $params = $request->get_params();
    }
    $team_ids = $params['team_ids'] ?? [];
    if (!is_array($team_ids)) {
        $team_ids = [];
    }

    return aidunite_rest_competition_from_submit_result(
        aidunite_competition_submit_invite_teams((int) $request['id'], $team_ids)
    );
}

/**
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function aidunite_rest_competition_list_entries(WP_REST_Request $request) {
    $event_id = (int) $request['id'];
    if (get_post_type($event_id) !== 'competition_event') {
        return aidunite_rest_competition_error('not_found', 'イベントが見つかりません');
    }

    return aidunite_rest_competition_success([
        'items' => aidunite_competition_read_entries_for_event($event_id),
    ]);
}

/**
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function aidunite_rest_competition_entries_mine(WP_REST_Request $request) {
    return aidunite_rest_competition_success([
        'items' => aidunite_competition_read_team_entries_for_user(get_current_user_id()),
    ]);
}

/**
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function aidunite_rest_competition_get_entry(WP_REST_Request $request) {
    $payload = aidunite_competition_read_entry_payload((int) $request['id']);
    if (empty($payload)) {
        return aidunite_rest_competition_error('not_found', '参加エントリが見つかりません');
    }

    return aidunite_rest_competition_success(['entry' => $payload]);
}

/**
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function aidunite_rest_competition_respond_entry(WP_REST_Request $request) {
    $params = $request->get_json_params();
    if (!is_array($params)) {
        $params = $request->get_params();
    }
    $response = (string) ($params['response'] ?? '');

    $result = aidunite_competition_submit_respond_entry((int) $request['id'], $response);
    if (!empty($result['ok'])) {
        return aidunite_rest_competition_success(['entry' => $result['payload'] ?? []]);
    }

    return aidunite_rest_competition_from_submit_result($result);
}

/**
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function aidunite_rest_competition_update_payment(WP_REST_Request $request) {
    $params = $request->get_json_params();
    if (!is_array($params)) {
        $params = $request->get_params();
    }
    $payment_status = (string) ($params['payment_status'] ?? '');

    $result = aidunite_competition_submit_update_payment((int) $request['id'], $payment_status);
    if (!empty($result['ok'])) {
        return aidunite_rest_competition_success(['entry' => $result['payload'] ?? []]);
    }

    return aidunite_rest_competition_from_submit_result($result);
}

/**
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function aidunite_rest_competition_approve_entry(WP_REST_Request $request) {
    $result = aidunite_competition_submit_approve_entry((int) $request['id']);
    if (!empty($result['ok'])) {
        return aidunite_rest_competition_success(['entry' => $result['payload'] ?? []]);
    }

    return aidunite_rest_competition_from_submit_result($result);
}

/**
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function aidunite_rest_competition_list_blocks(WP_REST_Request $request) {
    $event_id = (int) $request['id'];
    if (get_post_type($event_id) !== 'competition_event') {
        return aidunite_rest_competition_error('not_found', 'イベントが見つかりません');
    }

    return aidunite_rest_competition_success([
        'items' => aidunite_competition_read_blocks_for_event($event_id),
    ]);
}

/**
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function aidunite_rest_competition_create_block(WP_REST_Request $request) {
    $params = $request->get_json_params();
    if (!is_array($params)) {
        $params = $request->get_params();
    }

    $result = aidunite_competition_submit_create_block((int) $request['id'], is_array($params) ? $params : []);
    if (!empty($result['ok'])) {
        return aidunite_rest_competition_success(['block' => $result['payload'] ?? []], 201);
    }

    return aidunite_rest_competition_from_submit_result($result);
}

/**
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function aidunite_rest_competition_get_awards(WP_REST_Request $request) {
    $event_id = (int) $request['id'];
    if (get_post_type($event_id) !== 'competition_event') {
        return aidunite_rest_competition_error('not_found', 'イベントが見つかりません');
    }

    return aidunite_rest_competition_success(aidunite_competition_read_awards_payload($event_id));
}

/**
 * @return WP_REST_Response
 */
function aidunite_rest_competition_generator_templates() {
    return aidunite_rest_competition_success([
        'items' => aidunite_competition_get_generator_templates(),
    ]);
}

/**
 * @return WP_REST_Response
 */
function aidunite_rest_competition_ops_summary() {
    return aidunite_rest_competition_success(aidunite_competition_read_ops_summary());
}

/**
 * @param WP_REST_Request $request
 * @return WP_REST_Response
 */
function aidunite_rest_competition_ops_alerts(WP_REST_Request $request) {
    $event_id = (int) $request->get_param('event_id');

    return aidunite_rest_competition_success([
        'alerts' => aidunite_competition_read_ops_alerts([
            'event_id' => $event_id,
        ]),
    ]);
}

/**
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function aidunite_rest_competition_entry_checkout(WP_REST_Request $request) {
    $result = aidunite_competition_submit_entry_checkout((int) $request['id']);
    if (!empty($result['ok'])) {
        return aidunite_rest_competition_success([
            'checkout_url' => (string) ($result['checkout_url'] ?? ''),
            'session_id' => (string) ($result['session_id'] ?? ''),
        ]);
    }

    return aidunite_rest_competition_from_submit_result($result);
}

/**
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function aidunite_rest_competition_generate_fixtures(WP_REST_Request $request) {
    $params = $request->get_json_params();
    if (!is_array($params)) {
        $params = $request->get_params();
    }

    $result = aidunite_competition_submit_generate_fixtures((int) $request['id'], is_array($params) ? $params : []);
    if (!empty($result['ok'])) {
        return aidunite_rest_competition_success([
            'fixtures' => $result['fixtures'] ?? [],
            'warnings' => $result['warnings'] ?? [],
            'preview' => !empty($result['preview']),
        ]);
    }

    return aidunite_rest_competition_from_submit_result($result);
}

/**
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function aidunite_rest_competition_list_fixtures(WP_REST_Request $request) {
    $event_id = (int) $request['id'];
    if (get_post_type($event_id) !== 'competition_event') {
        return aidunite_rest_competition_error('not_found', 'イベントが見つかりません');
    }

    return aidunite_rest_competition_success([
        'items' => aidunite_competition_read_fixtures_for_event($event_id),
    ]);
}

/**
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function aidunite_rest_competition_standings(WP_REST_Request $request) {
    $event_id = (int) $request['id'];
    if (get_post_type($event_id) !== 'competition_event') {
        return aidunite_rest_competition_error('not_found', 'イベントが見つかりません');
    }

    return aidunite_rest_competition_success(aidunite_competition_read_standings_payload($event_id));
}

/**
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function aidunite_rest_competition_public_bracket(WP_REST_Request $request) {
    $payload = aidunite_competition_read_public_bracket_payload((int) $request['id']);
    if (isset($payload['error']) && $payload['error'] === 'forbidden') {
        return aidunite_rest_competition_error('forbidden', '公開されていないイベントです');
    }
    if ($payload === []) {
        return aidunite_rest_competition_error('not_found', 'イベントが見つかりません');
    }

    return aidunite_rest_competition_success(['bracket' => $payload]);
}

/**
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function aidunite_rest_competition_update_fixture(WP_REST_Request $request) {
    $params = $request->get_json_params();
    if (!is_array($params)) {
        $params = $request->get_params();
    }

    $result = aidunite_competition_submit_update_fixture((int) $request['id'], is_array($params) ? $params : []);
    if (!empty($result['ok'])) {
        return aidunite_rest_competition_success(['fixture' => $result['payload'] ?? []]);
    }

    return aidunite_rest_competition_from_submit_result($result);
}

/**
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function aidunite_rest_competition_fixture_result(WP_REST_Request $request) {
    $params = $request->get_json_params();
    if (!is_array($params)) {
        $params = $request->get_params();
    }

    $result = aidunite_competition_submit_fixture_result((int) $request['id'], is_array($params) ? $params : []);
    if (!empty($result['ok'])) {
        return aidunite_rest_competition_success(['fixture' => $result['payload'] ?? []]);
    }

    return aidunite_rest_competition_from_submit_result($result);
}

/**
 * @param WP_REST_Request $request
 * @return bool
 */
function aidunite_rest_competition_refund_policy_permission(WP_REST_Request $request) {
    $event_id = (int) $request['id'];
    if ($event_id < 1 || get_post_type($event_id) !== 'competition_event') {
        return false;
    }

    $raw = aidunite_competition_read_event_meta_raw($event_id);
    if (aidunite_competition_normalize_visibility($raw['visibility'] ?? 'admin_only') === 'public') {
        return true;
    }

    return aidunite_rest_competition_operate_permission();
}

/**
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function aidunite_rest_competition_public_event_by_slug(WP_REST_Request $request) {
    $slug = sanitize_title((string) $request['slug']);
    if ($slug === '') {
        return aidunite_rest_competition_error('invalid_params', 'slug が必要です');
    }

    $event_id = aidunite_competition_persist_find_event_id_by_slug($slug);
    if ($event_id < 1) {
        return aidunite_rest_competition_error('not_found', 'イベントが見つかりません');
    }

    $payload = aidunite_competition_read_public_lp_payload($event_id);
    if (isset($payload['error']) && $payload['error'] === 'forbidden') {
        return aidunite_rest_competition_error('forbidden', '公開されていないイベントです');
    }
    if (isset($payload['error']) && $payload['error'] === 'not_available') {
        return aidunite_rest_competition_error('not_found', '現在公開されていません');
    }
    if ($payload === []) {
        return aidunite_rest_competition_error('not_found', 'イベントが見つかりません');
    }

    return aidunite_rest_competition_success($payload);
}

/**
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function aidunite_rest_competition_refund_policy(WP_REST_Request $request) {
    $event_id = (int) $request['id'];
    if (get_post_type($event_id) !== 'competition_event') {
        return aidunite_rest_competition_error('not_found', 'イベントが見つかりません');
    }

    return aidunite_rest_competition_success([
        'refund_policy' => aidunite_competition_read_refund_policy_payload($event_id),
    ]);
}

/**
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function aidunite_rest_competition_entry_refund(WP_REST_Request $request) {
    $params = $request->get_json_params();
    if (!is_array($params)) {
        $params = $request->get_params();
    }

    $options = [
        'force' => !empty($params['force']),
        'reason' => (string) ($params['reason'] ?? ''),
    ];
    if (isset($params['amount'])) {
        $options['amount'] = (int) $params['amount'];
    }

    $result = aidunite_competition_submit_refund_entry((int) $request['id'], get_current_user_id(), $options);
    if (!empty($result['ok'])) {
        return aidunite_rest_competition_success([
            'entry' => $result['payload'] ?? [],
            'refund' => $result['refund'] ?? [],
            'warnings' => $result['warnings'] ?? [],
        ]);
    }

    return aidunite_rest_competition_from_submit_result($result);
}
