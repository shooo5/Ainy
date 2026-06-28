<?php
/**
 * E2E用テストデータ生成API
 * 開発・テスト環境でのみ有効。本番ではエンドポイントを登録しない。
 *
 * POST /wp-json/aidunite/v1/test-data/setup
 * POST /wp-json/aidunite/v1/test-data/cleanup
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * E2E API を有効にするか
 * 本番では常に false。ローカル含むそれ以外もデフォルト false（誤ってテストデータを残さないため）。
 * 有効にする場合は wp-config.php で define('AIDUNITE_E2E_API_ENABLED', true); と E2E_TEST_KEY を設定。
 */
if (!function_exists('aidunite_e2e_api_enabled')) {
    function aidunite_e2e_api_enabled() {
        if (defined('WP_ENVIRONMENT_TYPE') && WP_ENVIRONMENT_TYPE === 'production') {
            return false;
        }
        if (defined('AIDUNITE_E2E_API_ENABLED')) {
            return (bool) AIDUNITE_E2E_API_ENABLED;
        }
        return false;
    }
}

/**
 * E2E専用シークレット（wp-config.php で AIDUNITE_E2E_TEST_KEY を定義するか、環境変数 E2E_TEST_KEY）
 */
function aidunite_e2e_get_test_key() {
    if (defined('AIDUNITE_E2E_TEST_KEY') && AIDUNITE_E2E_TEST_KEY !== '') {
        return (string) AIDUNITE_E2E_TEST_KEY;
    }
    $env = getenv('E2E_TEST_KEY');
    return $env !== false ? (string) $env : '';
}

/**
 * E2E API を X-E2E-Test-Key ヘッダで許可するか（ローカル/テスト環境のみ）
 */
function aidunite_e2e_api_can_use_by_header($request) {
    if (!aidunite_e2e_api_enabled()) {
        return false;
    }
    $expected = aidunite_e2e_get_test_key();
    if ($expected === '') {
        return false;
    }
    $header = $request->get_header('X-E2E-Test-Key');
    return $header !== null && $header !== '' && hash_equals($expected, $header);
}

add_action('rest_api_init', function () {
    if (!aidunite_e2e_api_enabled()) {
        return;
    }

    $permission = function ($request) {
        if (!aidunite_e2e_api_can_use_by_header($request)) {
            return new WP_Error('forbidden', 'E2EテストデータAPIの利用権限がありません', ['status' => 403]);
        }
        return true;
    };

    register_rest_route('aidunite/v1', '/test-data/setup', [
        'methods' => 'POST',
        'callback' => 'aidunite_e2e_test_data_setup',
        'permission_callback' => $permission,
    ]);

    register_rest_route('aidunite/v1', '/test-data/cleanup', [
        'methods' => 'POST',
        'callback' => 'aidunite_e2e_test_data_cleanup',
        'permission_callback' => $permission,
    ]);

    register_rest_route('aidunite/v1', '/test-data/match-request/(?P<id>\d+)/chat-rooms', [
        'methods' => 'GET',
        'callback' => 'aidunite_e2e_get_match_request_chat_rooms',
        'permission_callback' => $permission,
        'args' => [
            'id' => [
                'required' => true,
                'type' => 'integer',
                'sanitize_callback' => 'absint',
            ],
        ],
    ]);
});

/**
 * E2E: 当該 MR に紐づくチャットルーム一覧（キャンセル→再申請→再承認の room 検証用）
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response
 */
function aidunite_e2e_get_match_request_chat_rooms($request) {
    global $wpdb;

    $match_request_id = (int) $request->get_param('id');
    if ($match_request_id <= 0) {
        return new WP_REST_Response(['success' => false, 'message' => 'invalid match_request_id'], 400);
    }

    $mr = get_post($match_request_id);
    if (!$mr || $mr->post_type !== 'match_request') {
        return new WP_REST_Response(['success' => false, 'message' => 'match_request not found'], 404);
    }

    $rooms_table = $wpdb->prefix . 'chat_rooms';
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT id, status, match_id, room_type, schedule_id
         FROM {$rooms_table}
         WHERE room_type IN ('match', 'group')
           AND match_id = %d
         ORDER BY id ASC",
        $match_request_id
    ), ARRAY_A);

    $rooms = [];
    foreach ((array) $rows as $row) {
        $rooms[] = [
            'id' => (int) ($row['id'] ?? 0),
            'status' => (string) ($row['status'] ?? ''),
            'match_id' => (int) ($row['match_id'] ?? 0),
            'room_type' => (string) ($row['room_type'] ?? ''),
            'schedule_id' => (int) ($row['schedule_id'] ?? 0),
        ];
    }

    $bound_room_id = (int) get_post_meta($match_request_id, 'chat_room_id', true);
    $active_room_id = 0;
    if (function_exists('aidunite_get_active_match_chat_room')) {
        $active = aidunite_get_active_match_chat_room($match_request_id);
        if ($active && !empty($active->id)) {
            $active_room_id = (int) $active->id;
        }
    }

    $completed_ids = [];
    $active_ids = [];
    foreach ($rooms as $room) {
        if (($room['status'] ?? '') === 'completed') {
            $completed_ids[] = (int) $room['id'];
        } elseif (($room['status'] ?? '') === 'active') {
            $active_ids[] = (int) $room['id'];
        }
    }

    return new WP_REST_Response([
        'success' => true,
        'match_request_id' => $match_request_id,
        'bound_room_id' => $bound_room_id,
        'active_room_id' => $active_room_id,
        'completed_room_ids' => $completed_ids,
        'active_room_ids' => $active_ids,
        'rooms' => $rooms,
    ], 200);
}

/**
 * メールからユーザーとチームIDを取得
 */
function aidunite_e2e_get_user_and_team($email) {
    $user = get_user_by('email', $email);
    if (!$user) {
        return null;
    }
    $team_id = get_user_meta($user->ID, 'team_id', true);
    return [
        'user_id' => $user->ID,
        'team_id' => $team_id ? (int) $team_id : null,
    ];
}

/**
 * 募集用スケジュールを1件作成（teamA = 募集側）
 */
function aidunite_e2e_create_recruit_schedule($team_id, $user_id, $params, $is_past = false) {
    $date = $params['match_date'] ?? date('Y-m-d', strtotime('+7 days'));
    $start = $params['start_time'] ?? '14:00';
    $end = $params['end_time'] ?? '16:00';
    $gender = $params['gender'] ?? 'male';
    $place = $params['place_type'] ?? 'home';

    if ($is_past) {
        $date = date('Y-m-d', strtotime('-3 days'));
        $start = '10:00';
        $end = '12:00';
    }

    $post_id = wp_insert_post([
        'post_type' => 'schedule',
        'post_status' => 'publish',
        'post_title' => '[E2E] recruit ' . $date,
        'post_author' => $user_id,
    ]);
    if (is_wp_error($post_id)) {
        return $post_id;
    }

    update_post_meta($post_id, 'team_id', $team_id);
    update_post_meta($post_id, 'schedule_date', $date);
    update_post_meta($post_id, 'schedule_start_time', $start);
    update_post_meta($post_id, 'schedule_end_time', $end);
    update_post_meta($post_id, 'schedule_type', 'practice_match');
    update_post_meta($post_id, 'intent', 'recruit');
    update_post_meta($post_id, 'schedule_gender', $gender);
    update_post_meta($post_id, 'schedule_place', $place);
    aidunite_e2e_apply_recruit_matching_meta($post_id, $gender);
    update_post_meta($post_id, 'is_test_schedule', '1');
    update_post_meta($post_id, 'e2e_test_data', '1');

    return $post_id;
}

/**
 * 掲示板募集用: matching=1 と性別枠
 */
function aidunite_e2e_apply_recruit_matching_meta($post_id, $gender) {
    $post_id = (int) $post_id;
    $gender = strtolower((string) $gender);
    update_post_meta($post_id, 'matching', '1');
    if ($gender === 'female') {
        update_post_meta($post_id, 'male_slots', 0);
        update_post_meta($post_id, 'female_slots', 1);
    } else {
        update_post_meta($post_id, 'male_slots', 1);
        update_post_meta($post_id, 'female_slots', 0);
    }
}

/**
 * 5+2 チーム掲示板テスト用: 各 team に同日 recruit schedule を1件ずつ作成
 */
function aidunite_e2e_setup_five_plus_two_market(array $params) {
    $default_team_ids = [5661, 5663, 5665, 5667, 5680, 5803, 5805];
    $team_ids = isset($params['team_ids']) && is_array($params['team_ids'])
        ? array_map('intval', $params['team_ids'])
        : $default_team_ids;
    $team_ids = array_values(array_filter($team_ids, static function ($id) {
        return $id > 0;
    }));
    if ($team_ids === []) {
        return new WP_REST_Response(['success' => false, 'message' => 'team_ids required'], 400);
    }

    $base = [
        'match_date' => $params['match_date'] ?? date('Y-m-d', strtotime('+7 days')),
        'start_time' => $params['start_time'] ?? '14:00',
        'end_time' => $params['end_time'] ?? '16:00',
        'place_type' => $params['place_type'] ?? 'home',
    ];

    $schedule_ids = [];
    $teams_meta = [];

    $team_date_offsets = [];
    if (!empty($params['team_date_offsets']) && is_array($params['team_date_offsets'])) {
        foreach ($params['team_date_offsets'] as $k => $v) {
            $team_date_offsets[(string) (int) $k] = (int) $v;
        }
    }

    foreach ($team_ids as $team_id) {
        $leader_id = (int) get_post_meta($team_id, 'team_leader_id', true);
        if ($leader_id <= 0) {
            $leader_id = (int) get_post_field('post_author', $team_id);
        }
        if ($leader_id <= 0) {
            continue;
        }
        $gender = (string) get_post_meta($team_id, 'team_gender_option', true);
        if ($gender === '') {
            $gender = 'male';
        }
        $offset_days = $team_date_offsets[(string) (int) $team_id] ?? 0;
        $row_date = $base['match_date'];
        if ($offset_days !== 0) {
            $row_date = date('Y-m-d', strtotime($row_date . ' ' . ($offset_days > 0 ? '+' : '') . $offset_days . ' days'));
        }
        $row_params = array_merge($base, ['gender' => $gender, 'match_date' => $row_date]);
        $schedule_id = aidunite_e2e_create_recruit_schedule($team_id, $leader_id, $row_params, false);
        if (is_wp_error($schedule_id)) {
            foreach ($schedule_ids as $sid) {
                wp_delete_post((int) $sid, true);
            }
            return new WP_REST_Response(['success' => false, 'message' => $schedule_id->get_error_message()], 500);
        }
        $schedule_ids[] = (int) $schedule_id;
        $teams_meta[] = [
            'team_id' => $team_id,
            'leader_id' => $leader_id,
            'gender' => $gender,
            'schedule_id' => (int) $schedule_id,
        ];
    }

    $operating = $params['operating_team_by_user'] ?? [
        302 => 5661,
        303 => 5680,
        304 => 5667,
        306 => 5803,
        307 => 5805,
    ];
    if (is_array($operating) && function_exists('aidunite_set_current_operating_team_id')) {
        foreach ($operating as $uid => $tid) {
            aidunite_set_current_operating_team_id((int) $uid, (int) $tid);
        }
    }

    return new WP_REST_Response([
        'success' => true,
        'scenario' => 'five_plus_two_market',
        'schedule_ids' => $schedule_ids,
        'teams' => $teams_meta,
        'match_date' => $base['match_date'],
    ], 200);
}

/**
 * team_id 配列に紐づく E2E schedule / MR を削除
 */
function aidunite_e2e_cleanup_by_team_ids(array $team_ids) {
    foreach ($team_ids as $tid) {
        $tid = (int) $tid;
        if ($tid <= 0) {
            continue;
        }
        $schedules = get_posts([
            'post_type' => 'schedule',
            'posts_per_page' => -1,
            'meta_query' => [
                ['key' => 'team_id', 'value' => (string) $tid, 'compare' => '='],
                ['key' => 'e2e_test_data', 'value' => '1', 'compare' => '='],
            ],
        ]);
        foreach ($schedules as $s) {
            wp_delete_post($s->ID, true);
        }
        $requests = get_posts([
            'post_type' => 'match_request',
            'posts_per_page' => -1,
            'meta_query' => [
                'relation' => 'OR',
                ['key' => 'from_team_id', 'value' => (string) $tid, 'compare' => '='],
                ['key' => 'to_team_id', 'value' => (string) $tid, 'compare' => '='],
            ],
        ]);
        foreach ($requests as $r) {
            if (get_post_meta($r->ID, 'e2e_test_data', true) === '1') {
                wp_delete_post($r->ID, true);
            }
        }
    }
}

/**
 * 申請側用スケジュールを1件作成（teamB = 申請側で使う）
 */
function aidunite_e2e_create_applicant_schedule($team_id, $user_id, $params, $is_past = false) {
    $date = $params['match_date'] ?? date('Y-m-d', strtotime('+7 days'));
    $start = $params['start_time'] ?? '14:00';
    $end = $params['end_time'] ?? '16:00';
    if ($is_past) {
        $date = date('Y-m-d', strtotime('-3 days'));
        $start = '10:00';
        $end = '12:00';
    }

    $post_id = wp_insert_post([
        'post_type' => 'schedule',
        'post_status' => 'publish',
        'post_title' => '[E2E] applicant ' . $date,
        'post_author' => $user_id,
    ]);
    if (is_wp_error($post_id)) {
        return $post_id;
    }

    update_post_meta($post_id, 'team_id', $team_id);
    update_post_meta($post_id, 'schedule_date', $date);
    update_post_meta($post_id, 'schedule_start_time', $start);
    update_post_meta($post_id, 'schedule_end_time', $end);
    update_post_meta($post_id, 'schedule_type', 'practice_match');
    update_post_meta($post_id, 'intent', 'recruit');
    update_post_meta($post_id, 'schedule_gender', $params['gender'] ?? 'male');
    update_post_meta($post_id, 'schedule_place', $params['place_type'] ?? 'away');
    update_post_meta($post_id, 'male_slots', 1);
    update_post_meta($post_id, 'female_slots', 0);
    update_post_meta($post_id, 'is_test_schedule', '1');
    update_post_meta($post_id, 'e2e_test_data', '1');

    return $post_id;
}

/**
 * page-match-feedback.php を割り当てた固定ページの ID（なければ E2E 有効時のみ 1 ページ自動作成）
 */
function aidunite_e2e_get_or_create_match_feedback_page_id() {
    $pages = get_posts([
        'post_type'              => 'page',
        'post_status'            => 'publish',
        'posts_per_page'         => 1,
        'meta_key'               => '_wp_page_template',
        'meta_value'             => 'page-match-feedback.php',
        'orderby'                => 'ID',
        'order'                  => 'ASC',
        'suppress_filters'       => true,
        'update_post_meta_cache' => false,
    ]);
    if (!empty($pages)) {
        return (int) $pages[0]->ID;
    }

    $by_slug = get_page_by_path('match-feedback', OBJECT, 'page');
    if ($by_slug instanceof WP_Post) {
        return (int) $by_slug->ID;
    }

    if (!aidunite_e2e_api_enabled()) {
        return 0;
    }

    $post_id = wp_insert_post([
        'post_title'  => '[E2E] マッチアンケート',
        'post_name'   => 'match-feedback',
        'post_status' => 'publish',
        'post_type'   => 'page',
    ], true);
    if (is_wp_error($post_id) || !$post_id) {
        return 0;
    }
    $post_id = (int) $post_id;
    update_post_meta($post_id, '_wp_page_template', 'page-match-feedback.php');
    update_post_meta($post_id, 'e2e_test_data', '1');

    return $post_id;
}

/**
 * マッチアンケート画面の URL（固定ページ＋テンプレート解決。未作成環境では E2E 用ページを作成）
 */
function aidunite_e2e_match_feedback_survey_url($match_request_id) {
    $mid = (int) $match_request_id;
    if ($mid <= 0) {
        return '';
    }

    $page_id = aidunite_e2e_get_or_create_match_feedback_page_id();
    if ($page_id > 0) {
        return add_query_arg('match_id', $mid, get_permalink($page_id));
    }

    return home_url('/match-feedback?match_id=' . $mid);
}

/**
 * setup API メイン
 */
function aidunite_e2e_test_data_setup($request) {
    $params = $request->get_json_params() ?: $request->get_body_params();
    $scenario = $params['scenario'] ?? '';

    if ($scenario === 'five_plus_two_market') {
        return aidunite_e2e_setup_five_plus_two_market($params);
    }

    $team_a_email = $params['teamA_email'] ?? 'teamA@example.com';
    $team_b_email = $params['teamB_email'] ?? 'teamBpleyer@example.com';
    $force_role_setup = !empty($params['force_role_setup']);
    $create_chat = !isset($params['create_chat']) || !empty($params['create_chat']);
    $create_survey_state = !empty($params['create_survey_state']);

    $team_a = aidunite_e2e_get_user_and_team($team_a_email);
    $team_b = aidunite_e2e_get_user_and_team($team_b_email);
    if (!$team_a || !$team_a['team_id']) {
        return new WP_REST_Response(['success' => false, 'message' => 'teamA user or team not found'], 400);
    }
    if (!$team_b || !$team_b['team_id']) {
        return new WP_REST_Response(['success' => false, 'message' => 'teamB user or team not found'], 400);
    }

    // マルチチームユーザー向け: 操作中チームと揃えるため team_id を明示上書き可能
    if (!empty($params['teamA_team_id'])) {
        $team_a['team_id'] = (int) $params['teamA_team_id'];
    }
    if (!empty($params['teamB_team_id'])) {
        $team_b['team_id'] = (int) $params['teamB_team_id'];
    }

    // 既定ではユーザーロールを上書きしない（teamBリーダー誤上書き防止）。
    // 旧E2E互換が必要な場合のみ force_role_setup=true で明示的に上書きする。
    if ($force_role_setup) {
        update_user_meta($team_a['user_id'], 'aidunite_role', 'team_leader');
        update_user_meta($team_b['user_id'], 'aidunite_role', 'player');
    }

    $gender_default = $params['gender'] ?? null;
    if ($gender_default === null || $gender_default === '') {
        $g_from_team = (string) get_post_meta((int) $team_a['team_id'], 'team_gender_option', true);
        $gender_default = $g_from_team !== '' ? $g_from_team : 'male';
    }

    $base = [
        'match_date' => $params['match_date'] ?? date('Y-m-d', strtotime('+7 days')),
        'start_time' => $params['start_time'] ?? '14:00',
        'end_time' => $params['end_time'] ?? '16:00',
        'gender' => $gender_default,
        'place_type' => $params['place_type'] ?? 'home',
    ];

    $schedule_ids = [];
    $to_schedule_id = null;
    $from_schedule_id = null;
    $match_request_id = null;
    $chat_room_id = null;
    $survey_response_id = null;

    $is_past = in_array($scenario, ['finished_match', 'finished_match_with_survey'], true);

    // teamA = 募集側 (to_schedule), teamB = 申請側 (from_schedule)
    $to_schedule_id = aidunite_e2e_create_recruit_schedule(
        $team_a['team_id'],
        $team_a['user_id'],
        $base,
        $is_past
    );
    if (is_wp_error($to_schedule_id)) {
        return new WP_REST_Response(['success' => false, 'message' => $to_schedule_id->get_error_message()], 500);
    }
    $schedule_ids[] = $to_schedule_id;

    if (!in_array($scenario, ['recruit_schedule_only'], true)) {
        $from_schedule_id = aidunite_e2e_create_applicant_schedule(
            $team_b['team_id'],
            $team_b['user_id'],
            $base,
            $is_past
        );
        if (is_wp_error($from_schedule_id)) {
            wp_delete_post($to_schedule_id, true);
            return new WP_REST_Response(['success' => false, 'message' => $from_schedule_id->get_error_message()], 500);
        }
        $schedule_ids[] = $from_schedule_id;

        $match_request_id = wp_insert_post([
            'post_type' => 'match_request',
            'post_status' => 'publish',
            'post_title' => '[E2E] ' . $scenario . ' ' . current_time('mysql'),
            'post_author' => $team_b['user_id'],
        ]);
        if (is_wp_error($match_request_id)) {
            wp_delete_post($from_schedule_id, true);
            wp_delete_post($to_schedule_id, true);
            return new WP_REST_Response(['success' => false, 'message' => $match_request_id->get_error_message()], 500);
        }

        update_post_meta($match_request_id, 'from_team_id', $team_b['team_id']);
        update_post_meta($match_request_id, 'to_team_id', $team_a['team_id']);
        update_post_meta($match_request_id, 'from_schedule_id', $from_schedule_id);
        update_post_meta($match_request_id, 'to_schedule_id', $to_schedule_id);
        update_post_meta($match_request_id, 'my_schedule_id', $from_schedule_id);
        update_post_meta($match_request_id, 'e2e_test_data', '1');

        $status = 'publish';
        if ($scenario === 'rejected_match') {
            $status = 'rejected';
        } elseif ($scenario === 'cancelled_match') {
            $status = 'canceled';
        } elseif (in_array($scenario, ['accepted_match', 'finished_match', 'finished_match_with_survey'], true)) {
            $status = 'established';
        } elseif ($scenario === 'reapply_ready_match') {
            $status = 'rejected';
        }

        if (function_exists('aidunite_update_match_request_status_meta')) {
            aidunite_update_match_request_status_meta($match_request_id, $status);
        } else {
            update_post_meta($match_request_id, 'status', $status);
        }
        if ($status === 'established') {
            update_post_meta($match_request_id, 'accepted_at', current_time('mysql'));
            update_post_meta($match_request_id, 'established_at', current_time('mysql'));
            update_post_meta($to_schedule_id, 'intent', 'confirmed');
            update_post_meta($from_schedule_id, 'intent', 'confirmed');

            if ($create_chat && function_exists('aidunite_create_or_extend_match_chat')) {
                $match_date = function_exists('aidunite_schedule_read_normalized_date')
                    ? aidunite_schedule_read_normalized_date((int) $to_schedule_id)
                    : '';
                $chat_room_id = aidunite_create_or_extend_match_chat(
                    $match_request_id,
                    (int) $team_b['team_id'],
                    (int) $team_a['team_id'],
                    (int) $to_schedule_id,
                    $match_date
                );
                if (is_wp_error($chat_room_id)) {
                    $chat_room_id = null;
                }
            }

            if ($scenario === 'finished_match_with_survey' && $create_survey_state) {
                $survey_response_id = aidunite_e2e_create_match_feedback($match_request_id, $team_a['team_id'], $team_a['user_id']);
            }

            if (in_array($scenario, ['finished_match', 'finished_match_with_survey'], true)
                && function_exists('aidunite_trigger_post_match_survey_flow')) {
                aidunite_trigger_post_match_survey_flow((int) $match_request_id, ['force' => true]);
            }
        }
    }

    if (function_exists('aidunite_set_current_operating_team_id')) {
        aidunite_set_current_operating_team_id((int) $team_a['user_id'], (int) $team_a['team_id']);
        aidunite_set_current_operating_team_id((int) $team_b['user_id'], (int) $team_b['team_id']);
    }

    $base_url = home_url('');
    // マッチ詳細は ?id=request_id で開く（schedule_id ではない）
    $match_detail_url = $match_request_id
        ? $base_url . '/match-detail/?id=' . $match_request_id
        : $base_url . '/match-detail/?id=' . $to_schedule_id;
    $team_chat_url = $chat_room_id ? $base_url . '/team-chat?room_id=' . $chat_room_id : '';
    $survey_url = $match_request_id ? aidunite_e2e_match_feedback_survey_url((int) $match_request_id) : '';

    return new WP_REST_Response([
        'success' => true,
        'scenario' => $scenario,
        'current_status' => $status ?? ($scenario === 'recruit_schedule_only' ? 'recruit_only' : ''),
        'team_a_team_id' => (int) $team_a['team_id'],
        'team_b_team_id' => (int) $team_b['team_id'],
        'schedule_ids' => $schedule_ids,
        'match_request_id' => $match_request_id,
        'chat_room_id' => $chat_room_id,
        'survey_response_id' => $survey_response_id,
        'match_detail_url' => $match_detail_url,
        'team_chat_url' => $team_chat_url,
        'survey_url' => $survey_url,
    ], 200);
}

/**
 * match_feedback を1件作成（survey_response_id = post ID）
 */
function aidunite_e2e_create_match_feedback($match_request_id, $team_id, $user_id) {
    $post_id = wp_insert_post([
        'post_type' => 'match_feedback',
        'post_status' => 'publish',
        'post_title' => '[E2E] マッチアンケート match=' . $match_request_id . ' team=' . $team_id,
        'post_author' => $user_id,
    ]);
    if (is_wp_error($post_id)) {
        return null;
    }
    update_post_meta($post_id, 'match_id', $match_request_id);
    update_post_meta($post_id, 'team_id', $team_id);
    update_post_meta($post_id, 'user_id', $user_id);
    update_post_meta($post_id, 'satisfaction', 5);
    update_post_meta($post_id, 'opponent_rating', 5);
    update_post_meta($post_id, 'venue_rating', 5);
    update_post_meta($post_id, 'comment', 'E2E test');
    update_post_meta($post_id, 'created_at', current_time('mysql'));
    update_post_meta($post_id, 'e2e_test_data', '1');
    return $post_id;
}

/**
 * cleanup API
 * 指定された ID で削除。または teamA_email / teamB_email のみ指定で、該当チームに紐づく E2E テストデータ残骸を削除。
 */
function aidunite_e2e_test_data_cleanup($request) {
    $params = $request->get_json_params() ?: $request->get_body_params();
    $schedule_ids = isset($params['schedule_ids']) ? (array) $params['schedule_ids'] : [];
    $match_request_id = isset($params['match_request_id']) ? (int) $params['match_request_id'] : 0;
    $chat_room_id = isset($params['chat_room_id']) ? (int) $params['chat_room_id'] : 0;
    $survey_response_id = isset($params['survey_response_id']) ? (int) $params['survey_response_id'] : 0;
    $team_a_email = $params['teamA_email'] ?? '';
    $team_b_email = $params['teamB_email'] ?? '';
    $cleanup_team_ids = isset($params['team_ids']) && is_array($params['team_ids'])
        ? array_map('intval', $params['team_ids'])
        : [];

    global $wpdb;

    if ($cleanup_team_ids !== [] && empty($schedule_ids) && !$match_request_id && !$chat_room_id && !$survey_response_id) {
        aidunite_e2e_cleanup_by_team_ids($cleanup_team_ids);
        return new WP_REST_Response(['success' => true, 'message' => 'cleanup by team_ids done'], 200);
    }

    $cleanup_by_team = ($team_a_email || $team_b_email) && empty($schedule_ids) && !$match_request_id && !$chat_room_id && !$survey_response_id;
    if ($cleanup_by_team) {
        $team_ids = [];
        if ($team_a_email) {
            $a = aidunite_e2e_get_user_and_team($team_a_email);
            if ($a && $a['team_id']) {
                $team_ids[] = $a['team_id'];
            }
        }
        if ($team_b_email) {
            $b = aidunite_e2e_get_user_and_team($team_b_email);
            if ($b && $b['team_id']) {
                $team_ids[] = $b['team_id'];
            }
        }
        foreach ($team_ids as $tid) {
            $schedules = get_posts([
                'post_type' => 'schedule',
                'posts_per_page' => -1,
                'meta_query' => [
                    ['key' => 'team_id', 'value' => $tid, 'compare' => '='],
                    ['key' => 'e2e_test_data', 'value' => '1', 'compare' => '='],
                ],
            ]);
            foreach ($schedules as $s) {
                wp_delete_post($s->ID, true);
            }
            $requests = get_posts([
                'post_type' => 'match_request',
                'posts_per_page' => -1,
                'meta_query' => [
                    'relation' => 'OR',
                    ['key' => 'from_team_id', 'value' => $tid, 'compare' => '='],
                    ['key' => 'to_team_id', 'value' => $tid, 'compare' => '='],
                ],
            ]);
            foreach ($requests as $r) {
                if (get_post_meta($r->ID, 'e2e_test_data', true) === '1') {
                    $room = function_exists('aidunite_get_match_chat_room') ? aidunite_get_match_chat_room($r->ID) : null;
                    if ($room) {
                        $msg_table = $wpdb->prefix . 'chat_messages';
                        $wpdb->delete($msg_table, ['room_id' => $room->id], ['%d']);
                        $part_table = $wpdb->prefix . 'chat_participants';
                        $wpdb->delete($part_table, ['room_id' => $room->id], ['%d']);
                        $room_table = $wpdb->prefix . 'chat_rooms';
                        $wpdb->delete($room_table, ['id' => $room->id], ['%d']);
                    }
                    wp_delete_post($r->ID, true);
                }
            }
            $feedbacks = get_posts([
                'post_type' => 'match_feedback',
                'posts_per_page' => -1,
                'meta_query' => [
                    ['key' => 'team_id', 'value' => $tid, 'compare' => '='],
                    ['key' => 'e2e_test_data', 'value' => '1', 'compare' => '='],
                ],
            ]);
            foreach ($feedbacks as $f) {
                wp_delete_post($f->ID, true);
            }
        }
        return new WP_REST_Response(['success' => true, 'message' => 'cleanup by team done'], 200);
    }

    if ($chat_room_id) {
        $msg_table = $wpdb->prefix . 'chat_messages';
        $wpdb->delete($msg_table, ['room_id' => $chat_room_id], ['%d']);
        $part_table = $wpdb->prefix . 'chat_participants';
        $wpdb->delete($part_table, ['room_id' => $chat_room_id], ['%d']);
        $room_table = $wpdb->prefix . 'chat_rooms';
        $wpdb->delete($room_table, ['id' => $chat_room_id], ['%d']);
    }

    if ($survey_response_id) {
        wp_delete_post($survey_response_id, true);
    }

    if ($match_request_id) {
        wp_delete_post($match_request_id, true);
    }

    foreach ($schedule_ids as $sid) {
        $sid = (int) $sid;
        if ($sid) {
            wp_delete_post($sid, true);
        }
    }

    return new WP_REST_Response(['success' => true, 'message' => 'cleanup done'], 200);
}
