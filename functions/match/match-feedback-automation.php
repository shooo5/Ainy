<?php
/**
 * マッチアンケート自動化機能
 * 試合終了後の自動アンケート依頼と感謝メッセージ送信
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 試合終了時刻を取得
 * （rest-match-feedback.php 等で定義されている場合はそちらを利用）
 */
if (!function_exists('aidunite_get_match_end_time')) {
function aidunite_get_match_end_time($match_request_id) {
    try {
        // マッチリクエストからスケジュールIDを取得
        $from_schedule_id = get_post_meta($match_request_id, 'from_schedule_id', true);
        $to_schedule_id = get_post_meta($match_request_id, 'to_schedule_id', true);

        // スケジュール情報を取得
        $schedule_id = $from_schedule_id ?: $to_schedule_id;
        if (!$schedule_id) {
            return null;
        }

        $sched_api = function_exists('aidunite_schedule_get_api_display_fields')
            ? aidunite_schedule_get_api_display_fields((int) $schedule_id)
            : [];
        $schedule_date = (string) ($sched_api['date'] ?? '');
        $schedule_end_time = (string) ($sched_api['end_time'] ?? '');

        if (!$schedule_date || !$schedule_end_time) {
            return null;
        }

        // 試合終了時刻 = 日付 + 終了時間
        $end_datetime = $schedule_date . ' ' . $schedule_end_time;
        $timestamp = strtotime($end_datetime);

        if ($timestamp === false) {
            return null;
        }

        return $timestamp;
    } catch (Exception $e) {
        return null;
    }
}
}

/**
 * 試合が終了しているかチェック
 */
function aidunite_is_match_ended($match_request_id) {
    try {
        $end_time = aidunite_get_match_end_time($match_request_id);
        if (!$end_time) {
            return false;
        }

        $current_time = current_time('timestamp');
        $is_ended = $current_time >= $end_time;

        return $is_ended;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * アンケート依頼メッセージを生成
 */
function aidunite_generate_feedback_request_message($match_request_id) {
    try {
        // マッチ情報を取得
        $from_team_id = get_post_meta($match_request_id, 'from_team_id', true);
        $to_team_id = get_post_meta($match_request_id, 'to_team_id', true);

        if (!$from_team_id || !$to_team_id) {
            return null;
        }

        $from_team_name = get_the_title($from_team_id);
        $to_team_name = get_the_title($to_team_id);

        $feedback_url = function_exists('aidunite_get_match_feedback_survey_url')
            ? aidunite_get_match_feedback_survey_url($match_request_id)
            : home_url('/match-feedback/?match_id=' . $match_request_id);

        $message = "試合お疲れさまでした！\n\n";
        $message .= "{$from_team_name} vs {$to_team_name} の試合、いかがでしたか？\n\n";
        $message .= "以下のアンケートにご回答いただけると、今後のマッチング精度向上に役立ちます。\n\n";
        $message .= "【アンケートに回答する】\n";
        $message .= $feedback_url;

        return $message;
    } catch (Exception $e) {
        return null;
    }
}

/**
 * アンケート依頼メッセージを送信
 */
/**
 * 試合後アンケート画面 URL
 */
if (!function_exists('aidunite_get_match_feedback_survey_url')) {
    function aidunite_get_match_feedback_survey_url($match_request_id) {
        $match_request_id = (int) $match_request_id;
        if ($match_request_id <= 0) {
            return '';
        }
        if (function_exists('aidunite_e2e_match_feedback_survey_url')) {
            $e2e_url = aidunite_e2e_match_feedback_survey_url($match_request_id);
            if ($e2e_url !== '') {
                return $e2e_url;
            }
        }

        return home_url('/match-feedback/?match_id=' . $match_request_id);
    }
}

/**
 * 指定チームが当該マッチのアンケートを送信済みか
 */
if (!function_exists('aidunite_team_has_submitted_match_feedback')) {
    function aidunite_team_has_submitted_match_feedback($match_request_id, $team_id) {
        $match_request_id = (int) $match_request_id;
        $team_id = (int) $team_id;
        if ($match_request_id <= 0 || $team_id <= 0) {
            return false;
        }
        $feedback_posts = get_posts([
            'post_type'      => 'match_feedback',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => [
                'relation' => 'AND',
                [
                    'key'     => 'match_id',
                    'value'   => $match_request_id,
                    'compare' => '=',
                ],
                [
                    'key'     => 'team_id',
                    'value'   => $team_id,
                    'compare' => '=',
                ],
            ],
        ]);

        return !empty($feedback_posts);
    }
}

/**
 * 掲示板・詳細用: 試合後アンケート CTA 状態
 *
 * @return array{state:string,url?:string,label?:string}
 */
if (!function_exists('aidunite_resolve_post_match_survey_cta')) {
    function aidunite_resolve_post_match_survey_cta($match_request_id, $viewer_team_id) {
        $match_request_id = (int) $match_request_id;
        $viewer_team_id = (int) $viewer_team_id;
        if ($match_request_id <= 0 || $viewer_team_id <= 0) {
            return ['state' => 'hidden'];
        }
        $status = (string) get_post_meta($match_request_id, 'status', true);
        if (!in_array($status, ['established', '試合確定'], true)) {
            return ['state' => 'hidden'];
        }
        if (!aidunite_is_match_ended($match_request_id)) {
            return ['state' => 'not_ended'];
        }
        if (aidunite_team_has_submitted_match_feedback($match_request_id, $viewer_team_id)) {
            return ['state' => 'answered', 'label' => 'アンケート回答済み'];
        }

        return [
            'state' => 'pending',
            'url'   => aidunite_get_match_feedback_survey_url($match_request_id),
            'label' => '試合後アンケート',
        ];
    }
}

/**
 * 試合終了後の導線を一括送信（通知 + チャット）
 *
 * @param int   $match_request_id
 * @param array $args force => true で終了判定をスキップ（E2E 等）
 * @return array{ok:bool,chat:bool,notify:bool,reason?:string}
 */
if (!function_exists('aidunite_trigger_post_match_survey_flow')) {
    function aidunite_trigger_post_match_survey_flow($match_request_id, $args = []) {
        $match_request_id = (int) $match_request_id;
        $force = !empty($args['force']);
        if ($match_request_id <= 0) {
            return ['ok' => false, 'chat' => false, 'notify' => false, 'reason' => 'invalid_id'];
        }
        if (!$force && !aidunite_is_match_ended($match_request_id)) {
            return ['ok' => false, 'chat' => false, 'notify' => false, 'reason' => 'not_ended'];
        }

        $notify = false;
        if (function_exists('send_match_feedback_survey_notifications')) {
            $notify = (bool) send_match_feedback_survey_notifications($match_request_id);
        }
        $chat = (bool) aidunite_send_feedback_request($match_request_id);

        return [
            'ok'     => $notify || $chat,
            'chat'   => $chat,
            'notify' => $notify,
        ];
    }
}

/**
 * ログイン中チーム向け: 終了済みで未送信の試合後導線を最大件数まで送信
 */
if (!function_exists('aidunite_process_pending_post_match_survey_flows_for_team')) {
    function aidunite_process_pending_post_match_survey_flows_for_team($team_id, $limit = 15) {
        $team_id = (int) $team_id;
        $limit = max(1, min(30, (int) $limit));
        if ($team_id <= 0) {
            return 0;
        }
        static $ran_for = [];
        if (!empty($ran_for[$team_id])) {
            return 0;
        }
        $ran_for[$team_id] = true;

        $request_ids = get_posts([
            'post_type'      => 'match_request',
            'post_status'    => 'publish',
            'posts_per_page' => $limit,
            'fields'         => 'ids',
            'orderby'        => 'ID',
            'order'          => 'DESC',
            'meta_query'     => [
                'relation' => 'AND',
                [
                    'key'     => 'status',
                    'value'   => ['established', '試合確定'],
                    'compare' => 'IN',
                ],
                [
                    'relation' => 'OR',
                    ['key' => 'from_team_id', 'value' => (string) $team_id, 'compare' => '='],
                    ['key' => 'to_team_id', 'value' => (string) $team_id, 'compare' => '='],
                ],
            ],
        ]);

        $processed = 0;
        foreach ($request_ids as $match_request_id) {
            $match_request_id = (int) $match_request_id;
            $chat_sent = get_post_meta($match_request_id, 'feedback_request_sent', true);
            $notify_sent = get_post_meta($match_request_id, 'feedback_survey_notification_sent', true);
            if ($chat_sent && $notify_sent) {
                continue;
            }
            if (!aidunite_is_match_ended($match_request_id)) {
                continue;
            }
            $result = aidunite_trigger_post_match_survey_flow($match_request_id);
            if (!empty($result['ok'])) {
                $processed++;
            }
        }

        return $processed;
    }
}

function aidunite_send_feedback_request($match_request_id) {
    try {
        // 既に送信済みかチェック
        if (get_post_meta($match_request_id, 'feedback_request_sent', true)) {
            return false;
        }

        $room_obj = null;
        if (function_exists('aidunite_get_game_chat_room_for_match_request')) {
            $room_obj = aidunite_get_game_chat_room_for_match_request($match_request_id);
        }
        if (!$room_obj && function_exists('aidunite_get_match_chat_room')) {
            $room_obj = aidunite_get_match_chat_room($match_request_id);
        }

        $chat_room_id = is_object($room_obj) && isset($room_obj->id) ? (int) $room_obj->id : 0;

        if (!$chat_room_id) {
            $from_team_id = get_post_meta($match_request_id, 'from_team_id', true);
            $to_team_id = get_post_meta($match_request_id, 'to_team_id', true);
            $schedule_id = get_post_meta($match_request_id, 'to_schedule_id', true);
            if (!$schedule_id) {
                $schedule_id = get_post_meta($match_request_id, 'from_schedule_id', true);
            }
            $match_date = ($schedule_id && function_exists('aidunite_schedule_read_normalized_date'))
                ? aidunite_schedule_read_normalized_date((int) $schedule_id)
                : '';

            if (function_exists('aidunite_create_match_chat')) {
                $created = aidunite_create_match_chat($match_request_id, $from_team_id, $to_team_id, $match_date);
                if (!is_wp_error($created)) {
                    $chat_room_id = (int) $created;
                }
            }
        }

        if (!$chat_room_id) {
            return false;
        }

        $message_content = aidunite_generate_feedback_request_message($match_request_id);
        if (!$message_content) {
            return false;
        }

        if (function_exists('aidunite_save_chat_message')) {
            $message_id = aidunite_save_chat_message([
                'room_id'      => $chat_room_id,
                'sender_id'    => 0,
                'content'      => $message_content,
                'message_type' => 'evaluation_request',
            ]);

            if ($message_id) {
                update_post_meta($match_request_id, 'feedback_request_sent', true);
                update_post_meta($match_request_id, 'feedback_request_sent_at', current_time('mysql'));
                do_action('aidunite_feedback_request_sent', $match_request_id, $chat_room_id);

                return $message_id;
            }
        }

        return false;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * アンケート依頼の定期チェックをスケジュール
 */
function aidunite_schedule_feedback_request_check() {
    if (!wp_next_scheduled('aidunite_check_feedback_requests')) {
        // 1時間ごとに実行
        wp_schedule_event(time(), 'hourly', 'aidunite_check_feedback_requests');
    }
}
add_action('wp', 'aidunite_schedule_feedback_request_check');

/**
 * アンケート依頼をチェックして送信
 */
function aidunite_check_and_send_feedback_requests() {
    try {
        $args = [
            'post_type'      => 'match_request',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => [
                [
                    'key'     => 'status',
                    'value'   => ['accepted', 'established', '承認済み', '試合確定'],
                    'compare' => 'IN',
                ],
            ],
        ];

        foreach (get_posts($args) as $match_request_id) {
            $match_request_id = (int) $match_request_id;
            $chat_sent = get_post_meta($match_request_id, 'feedback_request_sent', true);
            $notify_sent = get_post_meta($match_request_id, 'feedback_survey_notification_sent', true);
            if ($chat_sent && $notify_sent) {
                continue;
            }
            if (!aidunite_is_match_ended($match_request_id)) {
                continue;
            }
            aidunite_trigger_post_match_survey_flow($match_request_id);
        }
    } catch (Exception $e) {
        // 例外時は静かに終了
    }
}
add_action('aidunite_check_feedback_requests', 'aidunite_check_and_send_feedback_requests');

/**
 * 主要画面表示時に、当該チームの未送信試合後導線を補完する
 */
function aidunite_maybe_process_post_match_survey_on_front_view() {
    if (!is_user_logged_in()) {
        return;
    }
    $slugs = ['match-board-own', 'notifications', 'team-chat', 'chat', 'communication-main'];
    $hit = false;
    foreach ($slugs as $slug) {
        if (is_page($slug)) {
            $hit = true;
            break;
        }
    }
    if (!$hit) {
        return;
    }
    $user_id = get_current_user_id();
    $team_id = function_exists('aidunite_match_board_resolve_viewer_team_id')
        ? (int) aidunite_match_board_resolve_viewer_team_id($user_id)
        : (int) get_user_meta($user_id, 'team_id', true);
    if ($team_id <= 0 || !function_exists('aidunite_process_pending_post_match_survey_flows_for_team')) {
        return;
    }
    aidunite_process_pending_post_match_survey_flows_for_team($team_id);
}
add_action('template_redirect', 'aidunite_maybe_process_post_match_survey_on_front_view', 25);

/**
 * 感謝メッセージを生成
 */
function aidunite_generate_thank_you_message($match_request_id, $team_id) {
    try {
        $team_name = get_the_title($team_id);

        $message = "{$team_name}様、\n\n";
        $message .= "貴重な経験をありがとうございました！\n\n";
        $message .= "いただいたフィードバックは、今後のマッチング精度向上に活用させていただきます。\n\n";
        $message .= "またの機会もよろしくお願いいたします！";

        return $message;
    } catch (Exception $e) {
        return null;
    }
}

/**
 * 評価保存時に感謝メッセージを送信
 */
function aidunite_send_thank_you_on_evaluation_saved($evaluation_id, $match_id, $rater_team_id) {
    try {
        // マッチチャットルームを取得
        if (!function_exists('aidunite_get_match_chat_room')) {
            return;
        }

        $room_obj = aidunite_get_match_chat_room($match_id);
        $chat_room_id = is_object($room_obj) && isset($room_obj->id) ? (int) $room_obj->id : 0;
        if (!$chat_room_id) {
            return;
        }

        // 既に感謝メッセージを送信済みかチェック
        $thank_you_sent = get_post_meta($match_id, 'thank_you_sent_' . $rater_team_id, true);
        if ($thank_you_sent) {
            return;
        }

        // メッセージを生成
        $message_content = aidunite_generate_thank_you_message($match_id, $rater_team_id);

        if (!$message_content) {
            return;
        }

        // システムメッセージとして送信（アンケートご協力のお礼）
        if (function_exists('aidunite_save_chat_message')) {
            $message_id = aidunite_save_chat_message([
                'room_id' => $chat_room_id,
                'sender_id' => 0,
                'content' => $message_content,
                'message_type' => 'system',
            ]);

            if ($message_id) {
                // 送信済みフラグを保存
                update_post_meta($match_id, 'thank_you_sent_' . $rater_team_id, true);
                update_post_meta($match_id, 'thank_you_sent_at_' . $rater_team_id, current_time('mysql'));

                // イベントを発火
                do_action('aidunite_thank_you_sent', $match_id, $rater_team_id, $chat_room_id);

                return $message_id;
            }
        }
    } catch (Exception $e) {
        // 例外時は静かに終了
    }
}

// 評価保存時のフック
add_action('aidunite_evaluation_saved', 'aidunite_send_thank_you_on_evaluation_saved', 10, 3);

/**
 * 両チームのアンケート完了を判定し、チャットを完了済みへ移行
 */
function aidunite_complete_match_chat_on_feedback_finished($evaluation_id, $match_id, $rater_team_id) {
    $match_id_int = (int) $match_id;
    if ($match_id_int <= 0) {
        return;
    }

    $from_team_id = (int) get_post_meta($match_id_int, 'from_team_id', true);
    $to_team_id = (int) get_post_meta($match_id_int, 'to_team_id', true);
    if ($to_team_id <= 0) {
        $to_schedule_id = (int) get_post_meta($match_id_int, 'to_schedule_id', true);
        if ($to_schedule_id > 0) {
            $to_team_id = (int) get_post_meta($to_schedule_id, 'team_id', true);
        }
    }
    if ($from_team_id <= 0 || $to_team_id <= 0) {
        return;
    }

    $feedback_posts = get_posts([
        'post_type'      => 'match_feedback',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'meta_query'     => [
            [
                'key'     => 'match_id',
                'value'   => $match_id_int,
                'compare' => '='
            ]
        ]
    ]);
    if (empty($feedback_posts)) {
        return;
    }

    $answered_team_ids = [];
    foreach ($feedback_posts as $feedback_id) {
        $answered_team_ids[] = (int) get_post_meta($feedback_id, 'team_id', true);
    }
    $answered_team_ids = array_unique(array_filter($answered_team_ids));
    $both_answered = in_array($from_team_id, $answered_team_ids, true) && in_array($to_team_id, $answered_team_ids, true);
    if (!$both_answered) {
        return;
    }

    update_post_meta($match_id_int, 'feedback_completed_at', current_time('mysql'));
    if (function_exists('aidunite_mark_match_chat_completed')) {
        aidunite_mark_match_chat_completed($match_id_int, 'feedback_completed');
    }
}
add_action('aidunite_evaluation_saved', 'aidunite_complete_match_chat_on_feedback_finished', 20, 3);
