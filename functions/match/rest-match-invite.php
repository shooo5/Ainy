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

    /**
     * @deprecated 現行フローでは未使用。試合成立は POST /match-invite-approve（aidunite_process_match_invite_approval）で established まで完結。
     * 互換・監査用にルートは維持。既に established の場合は idempotent で 200 を返す。
     */
    register_rest_route('aidunite/v1', '/match-invite-confirm', [
        'methods' => 'POST',
        'callback' => 'aidunite_rest_match_invite_confirm',
        'permission_callback' => function ($request) {
            $result = AidUniteAuthMiddleware::rest_require($request, []);
            return !is_wp_error($result);
        }
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
    $matching = get_post_meta($schedule_id, 'matching', true);
    if ($matching !== '1') {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'マッチング希望のスケジュールのみ招待URLを発行できます'
        ], 400);
    }

    // 会場条件を取得（自分の条件がホームのときだけ会場名必須＝相手にはアウェイ@会場名になる）
    $schedule_place = get_post_meta($schedule_id, 'schedule_place', true);
    if (empty($schedule_place)) {
        $schedule_place = get_post_meta($schedule_id, 'schedule_place_option', true);
    }
    $venue_name = get_post_meta($schedule_id, 'venue_name', true);
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
    $schedule_date = get_post_meta($schedule_id, 'schedule_date', true);
    $schedule_start = get_post_meta($schedule_id, 'schedule_start_time', true);
    $schedule_end = get_post_meta($schedule_id, 'schedule_end_time', true);
    $team_id = function_exists('aidunite_resolve_schedule_owner_team_id')
        ? (int) aidunite_resolve_schedule_owner_team_id($schedule_id)
        : 0;
    if (!$team_id) {
        $team_id = (int) get_user_meta($current_user_id, 'team_id', true);
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

/*--------------------------------------------------------------
  招待成立の確定 REST API（非推奨・現行フローでは未使用）

  - 正規フロー: POST /match-invite-approve がゲスト承認と同時に established まで処理する。
  - 本コールバック: 旧クライアント・手動呼び出し向け。status が既に established（正規化後）なら副作用なく成功を返す。
--------------------------------------------------------------*/
function aidunite_rest_match_invite_confirm($request) {
    $params = $request->get_json_params();
    $request_id = isset($params['request_id']) ? absint($params['request_id']) : 0;

    if (!$request_id) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'マッチリクエストIDが指定されていません'
        ], 400);
    }

    $req = get_post($request_id);
    if (!$req || $req->post_type !== 'match_request') {
        return new WP_REST_Response([
            'success' => false,
            'message' => '申請が見つかりません'
        ], 404);
    }

    $approver_type = get_post_meta($request_id, 'approver_type', true);
    $to_schedule_id_meta = (int) get_post_meta($request_id, 'to_schedule_id', true);
    if ($approver_type !== 'guest_invite' && $to_schedule_id_meta !== 9999) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'この申請は招待によるものではありません'
        ], 400);
    }

    $status = get_post_meta($request_id, 'status', true);
    $norm = function_exists('aidunite_normalize_match_request_status')
        ? aidunite_normalize_match_request_status((string) $status, $req->post_status)
        : strtolower((string) $status);
    if ($norm === 'established') {
        return new WP_REST_Response([
            'success'       => true,
            'message'       => '既に確定済みです',
            'request_id'    => $request_id,
            'already_established' => true,
        ], 200);
    }
    if ($norm !== 'accepted') {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'ゲスト承認済み（accepted）の招待のみ確定できます',
        ], 400);
    }

    $from_team_id = (int) get_post_meta($request_id, 'from_team_id', true);
    $current_user_id = get_current_user_id();
    $current_team_id = function_exists('aidunite_get_current_team_id')
        ? (int) aidunite_get_current_team_id($current_user_id)
        : (int) get_user_meta($current_user_id, 'team_id', true);
    $can_from = function_exists('aidunite_user_has_managed_team_access')
        ? aidunite_user_has_managed_team_access($current_user_id, $from_team_id)
        : ($current_team_id === $from_team_id);
    if (!$can_from) {
        return new WP_REST_Response([
            'success' => false,
            'message' => '招待したチームの代表者のみ確定できます'
        ], 403);
    }

    $my_schedule_id = (int) get_post_meta($request_id, 'my_schedule_id', true);
    $to_team_id = (int) get_post_meta($request_id, 'to_team_id', true);
    if (!$my_schedule_id || !$to_team_id) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'スケジュールまたは相手チーム情報がありません'
        ], 500);
    }

    $schedule = get_post($my_schedule_id);
    if (!$schedule || $schedule->post_type !== 'schedule') {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'スケジュールが見つかりません'
        ], 404);
    }

    // 自スケジュールの participants に相手（招待チーム）を追加
    $participants = get_post_meta($my_schedule_id, 'participants', true);
    $participant_ids = $participants ? array_filter(array_map('trim', explode(',', $participants))) : [];
    if (!in_array((string) $to_team_id, $participant_ids)) {
        $participant_ids[] = (string) $to_team_id;
        update_post_meta($my_schedule_id, 'participants', implode(',', $participant_ids));
    }

    // 自スケジュールの intent を「確定」に
    update_post_meta($my_schedule_id, 'intent', 'confirmed');

    if (!function_exists('aidunite_apply_established_to_schedules')) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'サーバー設定エラーです',
        ], 500);
    }
    aidunite_apply_established_to_schedules($request_id);
    if (function_exists('aidunite_after_match_established')) {
        aidunite_after_match_established($request_id, [
            'guest_invite'               => true,
            'send_approval_notification' => false,
            'increment_match_counts'     => false,
        ]);
    }

    return new WP_REST_Response([
        'success' => true,
        'message' => '試合を確定しました'
    ], 200);
}
