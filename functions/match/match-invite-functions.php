<?php
/**
 * 試合招待URL機能
 * 登録ユーザーがスケジュールから招待URLを発行し、未登録ユーザーが承認できる機能
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once get_template_directory() . '/functions/common/error-handler.php';
require_once get_stylesheet_directory() . '/functions/match/match-state-sync.php';

/*--------------------------------------------------------------
  試合招待トークン生成
--------------------------------------------------------------*/
function aidunite_generate_match_invite_token($schedule_id, $hours_valid = 72) {
    if (!$schedule_id) {
        return false;
    }

    // スケジュールの存在確認
    $schedule = get_post($schedule_id);
    if (!$schedule || $schedule->post_type !== 'schedule') {
        error_log("❌ 試合招待トークン生成失敗：無効なスケジュールID {$schedule_id}");
        return false;
    }

    // マッチング希望チェック
    $matching = get_post_meta($schedule_id, 'matching', true);
    if ($matching !== '1') {
        error_log("❌ 試合招待トークン生成失敗：マッチング希望ではないスケジュール {$schedule_id}");
        return false;
    }

    // 会場条件を取得（自分の条件がホームのときだけ会場名必須＝相手にはアウェイ@会場名になる）
    $schedule_place = function_exists('aidunite_schedule_read_place_raw')
        ? aidunite_schedule_read_place_raw((int) $schedule_id)
        : '';
    $venue_name = get_post_meta($schedule_id, 'venue_name', true);
    if (in_array($schedule_place, ['home', 'ホーム'], true) && empty(trim((string) $venue_name))) {
        error_log("❌ 試合招待トークン生成失敗：ホームの場合は会場名必須 schedule_id={$schedule_id}");
        return false;
    }

    // トークンを生成（64文字のランダム文字列）
    $token = bin2hex(random_bytes(32));
    $expires_at = time() + ($hours_valid * 3600);
    $user_id = get_current_user_id();

    // トークンデータを保存
    $token_data = [
        'token' => $token,
        'schedule_id' => intval($schedule_id),
        'user_id' => intval($user_id),
        'expires_at' => $expires_at,
        'created_at' => time(),
        'used' => false
    ];

    // WordPress Optionsに保存
    $option_key = 'aidunite_match_invite_token_' . md5($token);
    update_option($option_key, $token_data, false);

    error_log("✅ 試合招待トークン生成成功：schedule_id={$schedule_id} token=" . substr($token, 0, 8) . "...");

    return $token;
}

/*--------------------------------------------------------------
  招待時の「相手側の会場表示」を返す
  自分の会場条件の逆：ホーム→相手はアウェイ@会場名、アウェイ→相手はホーム、どちらでも→どちらでも
--------------------------------------------------------------*/
function aidunite_match_invite_opponent_venue_label($schedule_place, $venue_name = '') {
    $place = strtolower(trim((string) $schedule_place));
    $venue = trim((string) $venue_name);
    if ($place === 'home' || $place === 'ホーム') {
        return $venue ? 'アウェイ @' . $venue_name : 'アウェイ';
    }
    if ($place === 'away' || $place === 'アウェイ') {
        return 'ホーム';
    }
    if ($place === 'both' || $place === 'either' || $place === 'どちらでも') {
        return $venue ? 'どちらでも @' . $venue_name : 'どちらでも';
    }
    return $venue ? $venue_name : '未設定';
}

/*--------------------------------------------------------------
  試合招待トークン検証
--------------------------------------------------------------*/
function aidunite_verify_match_invite_token($token) {
    if (empty($token)) {
        return false;
    }

    // トークンデータを取得
    $option_key = 'aidunite_match_invite_token_' . md5($token);
    $token_data = get_option($option_key);

    if (!$token_data || !is_array($token_data)) {
        error_log("❌ 試合招待トークン検証失敗：トークンが見つかりません");
        return false;
    }

    // 有効期限チェック
    if (isset($token_data['expires_at']) && $token_data['expires_at'] < time()) {
        delete_option($option_key);
        error_log("❌ 試合招待トークン検証失敗：期限切れ");
        return false;
    }

    // 使用済みチェック
    if (isset($token_data['used']) && $token_data['used'] === true) {
        error_log("❌ 試合招待トークン検証失敗：既に使用済み");
        return false;
    }

    // トークン一致チェック
    if ($token_data['token'] !== $token) {
        error_log("❌ 試合招待トークン検証失敗：トークン不一致");
        return false;
    }

    // スケジュールの存在確認
    $schedule = get_post($token_data['schedule_id']);
    if (!$schedule || $schedule->post_type !== 'schedule') {
        error_log("❌ 試合招待トークン検証失敗：スケジュールが存在しません");
        return false;
    }

    return $token_data;
}

/*--------------------------------------------------------------
  試合招待トークンを無効化
--------------------------------------------------------------*/
function aidunite_invalidate_match_invite_token($token) {
    if (empty($token)) {
        return false;
    }

    $option_key = 'aidunite_match_invite_token_' . md5($token);
    $token_data = get_option($option_key);

    if (!$token_data || !is_array($token_data)) {
        return false;
    }

    // 使用済みフラグを立てる
    $token_data['used'] = true;
    $token_data['used_at'] = time();

    update_option($option_key, $token_data, false);

    error_log("✅ 試合招待トークン無効化：token=" . substr($token, 0, 8) . "...");

    return true;
}

/*--------------------------------------------------------------
  「招待」チームの取得/作成
--------------------------------------------------------------*/
function aidunite_get_or_create_guest_team() {
    // 既存の「招待」チームを検索
    $query = new WP_Query([
        'post_type' => 'team',
        'post_status' => 'publish',
        'posts_per_page' => 1,
        'meta_query' => [
            [
                'key' => 'team_name',
                'value' => '招待（ゲスト承認）',
                'compare' => '='
            ]
        ]
    ]);

    if ($query->have_posts()) {
        $team = $query->posts[0];
        error_log("✅ 「招待」チーム取得：team_id={$team->ID}");
        return $team->ID;
    }

    // 存在しない場合は作成
    $team_id = wp_insert_post([
        'post_type' => 'team',
        'post_title' => '招待（ゲスト承認）',
        'post_status' => 'publish',
        'post_author' => 1 // 管理者ユーザー
    ]);

    if (is_wp_error($team_id)) {
        error_log("❌ 「招待」チーム作成失敗：" . $team_id->get_error_message());
        return false;
    }

    // チームメタデータを設定
    update_post_meta($team_id, 'team_name', '招待（ゲスト承認）');
    update_post_meta($team_id, 'team_description', 'ゲスト承認による試合招待用の仮チームです。後で正しいチーム情報に更新してください。');
    update_post_meta($team_id, 'is_guest_team', true);

    error_log("✅ 「招待」チーム作成成功：team_id={$team_id}");

    return $team_id;
}

/*--------------------------------------------------------------
  試合招待承認処理
--------------------------------------------------------------*/
function aidunite_process_match_invite_approval($token, $school_name, $approver_name) {
    // トークン検証
    $token_data = aidunite_verify_match_invite_token($token);
    if (!$token_data) {
        return [
            'success' => false,
            'message' => '無効な招待リンクです'
        ];
    }

    $schedule_id = $token_data['schedule_id'];
    $user_id = $token_data['user_id'];

    // スケジュール情報を取得
    $schedule = get_post($schedule_id);
    if (!$schedule) {
        return [
            'success' => false,
            'message' => 'スケジュールが見つかりません'
        ];
    }

    // 発行者のチームIDを取得（募集 schedule の team_id 優先）
    $from_team_id = function_exists('aidunite_resolve_schedule_owner_team_id')
        ? (int) aidunite_resolve_schedule_owner_team_id((int) $schedule_id)
        : 0;
    if (!$from_team_id) {
        $from_team_id = (int) get_user_meta($user_id, 'team_id', true);
    }
    if (!$from_team_id) {
        return [
            'success' => false,
            'message' => '発行者のチーム情報が見つかりません'
        ];
    }

    // 「招待」チームを取得/作成
    $to_team_id = aidunite_get_or_create_guest_team();
    if (!$to_team_id) {
        return [
            'success' => false,
            'message' => 'チーム情報の取得に失敗しました'
        ];
    }

    if (!function_exists('aidunite_match_request_create_guest_invite_post')) {
        return [
            'success' => false,
            'message' => 'サーバー設定エラーです',
        ];
    }

    $request_id = aidunite_match_request_create_guest_invite_post([
        'post_author' => $user_id,
        'from_team_id' => $from_team_id,
        'to_team_id' => $to_team_id,
        'my_schedule_id' => $schedule_id,
        'to_schedule_id' => 9999,
        'approver_school_name' => $school_name,
        'approver_name' => $approver_name,
    ]);

    if (is_wp_error($request_id)) {
        error_log('❌ match_request作成失敗：' . $request_id->get_error_message());
        return [
            'success' => false,
            'message' => '試合申請の作成に失敗しました',
        ];
    }

    // トークンを無効化
    aidunite_invalidate_match_invite_token($token);

    // 承認時点で確定：自スケジュールの participants と intent を更新（チャットは作成しない）
    $participants = get_post_meta($schedule_id, 'participants', true);
    $participant_ids = $participants ? array_filter(array_map('trim', explode(',', $participants))) : [];
    if (!in_array((string) $to_team_id, $participant_ids)) {
        $participant_ids[] = (string) $to_team_id;
        update_post_meta($schedule_id, 'participants', implode(',', $participant_ids));
    }
    if (function_exists('aidunite_schedule_persist_intent_flags')) {
        aidunite_schedule_persist_intent_flags((int) $schedule_id, 'confirmed', false);
    } else {
        update_post_meta($schedule_id, 'intent', 'confirmed');
    }

    if (!function_exists('aidunite_apply_established_to_schedules')) {
        error_log('❌ match-invite: aidunite_apply_established_to_schedules が未読込です');
        return [
            'success' => false,
            'message' => 'サーバー設定エラーです',
        ];
    }
    aidunite_apply_established_to_schedules($request_id);
    if (function_exists('aidunite_after_match_established')) {
        aidunite_after_match_established($request_id, [
            'guest_invite'               => true,
            'send_approval_notification' => false,
            'increment_match_counts'     => false,
        ]);
    }

    // 通知を送信
    aidunite_send_match_invite_approval_notification($request_id, $user_id, $school_name, $approver_name, $schedule_id);

    error_log("✅ 試合招待承認処理完了（確定済み）：request_id={$request_id}");

    return [
        'success' => true,
        'message' => '承認が完了しました',
        'request_id' => $request_id
    ];
}

/*--------------------------------------------------------------
  試合招待拒否処理
--------------------------------------------------------------*/
function aidunite_process_match_invite_rejection($token) {
    // トークン検証
    $token_data = aidunite_verify_match_invite_token($token);
    if (!$token_data) {
        return [
            'success' => false,
            'message' => '無効な招待リンクです'
        ];
    }

    // トークンを無効化
    aidunite_invalidate_match_invite_token($token);

    error_log("✅ 試合招待拒否処理完了：token=" . substr($token, 0, 8) . "...");

    return [
        'success' => true,
        'message' => '拒否が完了しました'
    ];
}

/*--------------------------------------------------------------
  試合招待承認通知送信
--------------------------------------------------------------*/
function aidunite_send_match_invite_approval_notification($request_id, $user_id, $school_name, $approver_name, $schedule_id) {
    // スケジュール情報を取得（自分の会場条件の逆が相手の見え方）
    $schedule_place = function_exists('aidunite_schedule_read_place_raw')
        ? aidunite_schedule_read_place_raw((int) $schedule_id)
        : '';
    $venue_name = get_post_meta($schedule_id, 'venue_name', true);
    $venue_display = aidunite_match_invite_opponent_venue_label($schedule_place, $venue_name);

    // 通知メッセージを作成
    $message = <<<EOM
【AidUnite】試合招待が承認されました

相手: {$school_name} {$approver_name} 様
会場: {$venue_display}

試合が確定しました。
詳細はマイページで確認できます。
EOM;

    // 通知を送信
    if (function_exists('aidunite_notify_user')) {
        aidunite_notify_user(
            $user_id,
            '【AidUnite】試合招待が承認されました',
            $message,
            'match_invite_approved',
            $request_id
        );
    }

    error_log("✅ 試合招待承認通知送信：user_id={$user_id} request_id={$request_id}");
}
