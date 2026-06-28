<?php
/**
 * 試合招待URL機能 REST API
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once get_template_directory() . '/functions/common/auth-middleware.php';
require_once get_template_directory() . '/functions/match/match-invite-functions.php';

/*--------------------------------------------------------------
  REST APIエンドポイント登録
--------------------------------------------------------------*/
add_action('rest_api_init', function () {
    // 招待URL生成
    register_rest_route('aidunite/v1', '/generate-invite-url', [
        'methods' => 'POST',
        'callback' => 'aidunite_rest_generate_invite_url',
        'permission_callback' => function ($request) {
            $result = AidUniteAuthMiddleware::rest_require($request, []);
            return !is_wp_error($result);
        }
    ]);

    // 試合招待承認
    register_rest_route('aidunite/v1', '/match-invite-approve', [
        'methods' => 'POST',
        'callback' => 'aidunite_rest_match_invite_approve',
        'permission_callback' => '__return_true' // ゲストもアクセス可能
    ]);

    // 試合招待拒否
    register_rest_route('aidunite/v1', '/match-invite-reject', [
        'methods' => 'POST',
        'callback' => 'aidunite_rest_match_invite_reject',
        'permission_callback' => '__return_true' // ゲストもアクセス可能
    ]);
});

/*--------------------------------------------------------------
  招待URL生成 REST API
--------------------------------------------------------------*/
function aidunite_rest_generate_invite_url($request) {
    $params = $request->get_json_params();
    $schedule_id = isset($params['schedule_id']) ? intval($params['schedule_id']) : 0;

    if (!$schedule_id) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'スケジュールIDが指定されていません'
        ], 400);
    }

    // スケジュールの存在確認
    $schedule = get_post($schedule_id);
    if (!$schedule || $schedule->post_type !== 'schedule') {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'スケジュールが見つかりません'
        ], 404);
    }

    // マッチング希望チェック
    $matching_on = function_exists('aidunite_schedule_matching_meta_on')
        ? aidunite_schedule_matching_meta_on((int) $schedule_id)
        : false;
    if (!$matching_on) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'マッチング希望のスケジュールのみ招待URLを発行できます'
        ], 400);
    }

    // 会場条件を取得（自分の条件がホームのときだけ会場名必須＝相手にはアウェイ@会場名になる）
    $sched_api = function_exists('aidunite_schedule_get_api_display_fields')
        ? aidunite_schedule_get_api_display_fields((int) $schedule_id)
        : [];
    $schedule_place = (string) ($sched_api['place'] ?? '');
    if ($schedule_place === '' && function_exists('aidunite_schedule_read_place_raw')) {
        $schedule_place = aidunite_schedule_read_place_raw((int) $schedule_id);
    }
    $venue_name = (string) ($sched_api['venue_name'] ?? '');
    if (in_array($schedule_place, ['home', 'ホーム'], true) && empty(trim((string) $venue_name))) {
        return new WP_REST_Response([
            'success' => false,
            'message' => '会場条件がホームの場合は会場名の入力が必須です。スケジュールを編集して会場名を入力してください。'
        ], 400);
    }

    // 権限チェック：スケジュールの所有者のみ
    $current_user_id = get_current_user_id();
    $schedule_author = intval($schedule->post_author);
    if ($current_user_id !== $schedule_author) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'このスケジュールの招待URLを発行する権限がありません'
        ], 403);
    }

    // トークン生成
    $token = aidunite_generate_match_invite_token($schedule_id, 72);
    if (!$token) {
        return new WP_REST_Response([
            'success' => false,
            'message' => '招待URLの生成に失敗しました'
        ], 500);
    }

    // 招待URLを生成
    $invite_url = home_url('/match-invite?token=' . $token);

    // 有効期限を計算
    $expires_at = date('Y-m-d H:i:s', time() + (72 * 3600));

    // LINE用メッセージ・OGP用にスケジュール情報を取得
    if (empty($sched_api) && function_exists('aidunite_schedule_get_api_display_fields')) {
        $sched_api = aidunite_schedule_get_api_display_fields((int) $schedule_id);
    }
    $schedule_date = (string) ($sched_api['date'] ?? '');
    $schedule_start = (string) ($sched_api['start_time'] ?? '');
    $schedule_end = (string) ($sched_api['end_time'] ?? '');
    $team_id = function_exists('aidunite_resolve_schedule_owner_team_id')
        ? (int) aidunite_resolve_schedule_owner_team_id($schedule_id)
        : 0;
    if ($team_id <= 0 && function_exists('aidunite_user_read_primary_team_id')) {
        $team_id = aidunite_user_read_primary_team_id((int) $current_user_id);
    }
    $team_name = $team_id ? get_the_title($team_id) : '';

    $venue_opponent_label = function_exists('aidunite_match_invite_opponent_venue_label')
        ? aidunite_match_invite_opponent_venue_label($schedule_place, $venue_name)
        : ($venue_name ? 'アウェイ @' . $venue_name : '');
    $schedule_place = $schedule_place ?: '';

    return new WP_REST_Response([
        'success' => true,
        'invite_url' => $invite_url,
        'expires_at' => $expires_at,
        'schedule_date' => $schedule_date,
        'schedule_start' => $schedule_start,
        'schedule_end' => $schedule_end,
        'team_name' => $team_name,
        'venue_name' => $venue_name,
        'schedule_place' => $schedule_place,
        'venue_opponent_label' => $venue_opponent_label,
        'token' => $token
    ], 200);
}

/*--------------------------------------------------------------
  試合招待承認 REST API
--------------------------------------------------------------*/
function aidunite_rest_match_invite_approve($request) {
    $params = $request->get_json_params();
    $token = isset($params['token']) ? sanitize_text_field($params['token']) : '';
    $school_name = isset($params['school_name']) ? sanitize_text_field($params['school_name']) : '';
    $approver_name = isset($params['approver_name']) ? sanitize_text_field($params['approver_name']) : '';

    // 必須項目チェック
    if (empty($token)) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'トークンが指定されていません'
        ], 400);
    }

    if (empty($school_name)) {
        return new WP_REST_Response([
            'success' => false,
            'message' => '学校名またはクラブ名を入力してください'
        ], 400);
    }

    if (empty($approver_name)) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'お名前を入力してください'
        ], 400);
    }

    // 承認処理
    $result = aidunite_process_match_invite_approval($token, $school_name, $approver_name);

    if (!$result['success']) {
        return new WP_REST_Response([
            'success' => false,
            'message' => $result['message']
        ], 400);
    }

    return new WP_REST_Response([
        'success' => true,
        'message' => $result['message'],
        'request_id' => $result['request_id']
    ], 200);
}

/*--------------------------------------------------------------
  試合招待拒否 REST API
--------------------------------------------------------------*/
function aidunite_rest_match_invite_reject($request) {
    $params = $request->get_json_params();
    $token = isset($params['token']) ? sanitize_text_field($params['token']) : '';

    // 必須項目チェック
    if (empty($token)) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'トークンが指定されていません'
        ], 400);
    }

    // 拒否処理
    $result = aidunite_process_match_invite_rejection($token);

    if (!$result['success']) {
        return new WP_REST_Response([
            'success' => false,
            'message' => $result['message']
        ], 400);
    }

    return new WP_REST_Response([
        'success' => true,
        'message' => $result['message']
    ], 200);
}
