<?php
/**
 * Match chat routing helper
 * 旧 page-match-chat.php の機能をフックで再現
 */

add_action('template_redirect', function() {
    if (!is_page('match-chat')) {
        return;
    }

    // 統一認証・権限チェック
    $auth_result = AidUniteAuthMiddleware::require_auth(true);
    if (!$auth_result->is_valid()) {
        // リダイレクトは自動で実行される
        return;
    }

    $match_id = isset($_GET['match_id']) ? intval($_GET['match_id']) : 0;

    if (!$match_id) {
        wp_die('マッチIDが指定されていません。');
    }

    if (!function_exists('aidunite_get_match_chat_room')) {
        wp_die('チャット機能が無効化されています。');
    }

    $chat_room = function_exists('aidunite_get_game_chat_room_for_match_request')
        ? aidunite_get_game_chat_room_for_match_request($match_id)
        : aidunite_get_match_chat_room($match_id);

    if (!$chat_room && function_exists('aidunite_create_or_extend_match_chat')) {
        $from_team_id = (int) get_post_meta($match_id, 'from_team_id', true);
        $to_team_id = (int) get_post_meta($match_id, 'to_team_id', true);
        $to_schedule_id = (int) get_post_meta($match_id, 'to_schedule_id', true);
        $my_schedule_id = (int) get_post_meta($match_id, 'my_schedule_id', true);
        $target_schedule_id = $to_schedule_id ?: $my_schedule_id;
        $match_date = null;
        if ($target_schedule_id) {
            $match_date = get_post_meta($target_schedule_id, 'schedule_date', true);
        }
        if (!$match_date && $my_schedule_id) {
            $match_date = get_post_meta($my_schedule_id, 'schedule_date', true);
        }
        if ($from_team_id && $to_team_id) {
            $room_id = aidunite_create_or_extend_match_chat($match_id, $from_team_id, $to_team_id, $target_schedule_id, $match_date);
            if (!is_wp_error($room_id)) {
                $chat_room = aidunite_get_chat_room($room_id);
            }
        }
    }

    if (!$chat_room) {
        wp_die('対戦チャットルームが見つかりません。');
    }

    // 完了済みルームは履歴として開く（完了済みタブの「チャット」から新規ルームを生成しない）

    // 統合チャット画面用のパラメータを設定して再利用
    $_GET['room_id'] = intval($chat_room->id);

    load_template(get_template_directory() . '/page-chat.php', true);
    exit;
});
