<?php
/**
 * マッチアンケート REST API
 * アンケートの保存と取得
 */

if (!defined('ABSPATH')) {
    exit;
}

// REST APIエンドポイント登録
add_action('rest_api_init', function() {
    register_rest_route('aidunite/v1', '/match-feedback', [
        'methods' => 'POST',
        'callback' => 'aidunite_save_match_feedback',
        'permission_callback' => function($request) {
            $result = AidUniteAuthMiddleware::rest_require($request, []);
            return !is_wp_error($result);
        }
    ]);

    register_rest_route('aidunite/v1', '/match-feedback', [
        'methods' => 'GET',
        'callback' => 'aidunite_get_match_feedback',
        'permission_callback' => function($request) {
            $result = AidUniteAuthMiddleware::rest_require($request, []);
            return !is_wp_error($result);
        }
    ]);
});

/**
 * 試合終了時刻を取得（match-feedback-automation.php と二重定義されないよう function_exists でガード）
 * 本番で rest-match-feedback が先に読み込まれるため、ここでも定義可能にしておく。
 */
if (!function_exists('aidunite_get_match_end_time')) {
function aidunite_get_match_end_time($match_request_id) {
    try {
        $canonical = function_exists('aidunite_match_request_get_canonical_meta')
            ? aidunite_match_request_get_canonical_meta((int) $match_request_id)
            : [];
        $from_schedule_id = (int) ($canonical['from_schedule_id'] ?? 0);
        $to_schedule_id = (int) ($canonical['to_schedule_id'] ?? 0);
        $schedule_id = $from_schedule_id > 0 ? $from_schedule_id : $to_schedule_id;
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
        $end_datetime = $schedule_date . ' ' . $schedule_end_time;
        $timestamp = strtotime($end_datetime);
        return ($timestamp !== false) ? $timestamp : null;
    } catch (Exception $e) {
        return null;
    }
}
}

/**
 * マッチアンケートを保存
 */
function aidunite_save_match_feedback($request) {
    try {
        $current_user_id = get_current_user_id();
        $team_scope = aidunite_match_resolve_user_team_scope($current_user_id);

        if (empty($team_scope)) {
            error_log('[MATCH_FEEDBACK_API] Error: Team ID not found for user ' . $current_user_id);
            return new WP_Error('no_team', 'チームIDが設定されていません', ['status' => 400]);
        }

        $params = $request->get_params();
        $match_id = isset($params['match_id']) ? intval($params['match_id']) : 0;
        $team_id = isset($params['team_id']) ? intval($params['team_id']) : 0;

        if (!$match_id || !$team_id) {
            error_log('[MATCH_FEEDBACK_API] Error: Missing required parameters');
            return new WP_Error('missing_params', '必須パラメータが不足しています', ['status' => 400]);
        }

        // チームIDの検証（managed / レガシー単一 team_id）
        $team_ok = function_exists('aidunite_user_has_managed_team_access')
            ? aidunite_user_has_managed_team_access($current_user_id, $team_id)
            : in_array($team_id, $team_scope, true);
        if (!$team_ok) {
            error_log('[MATCH_FEEDBACK_API] Error: Team ID mismatch');
            return new WP_Error('invalid_team', 'チームIDが一致しません', ['status' => 403]);
        }

        // マッチ情報の検証
        $match_request = get_post($match_id);
        if (!$match_request || $match_request->post_type !== 'match_request') {
            error_log('[MATCH_FEEDBACK_API] Error: Invalid match_id ' . $match_id);
            return new WP_Error('invalid_match', 'マッチ情報が見つかりません', ['status' => 404]);
        }

        // 既に回答済みかチェック
        if (function_exists('aidunite_match_feedback_read_exists')
            && aidunite_match_feedback_read_exists($match_id, $team_id)) {
            error_log('[MATCH_FEEDBACK_API] Error: Feedback already exists for match_id=' . $match_id . ', team_id=' . $team_id);
            return new WP_Error('already_answered', '既に回答済みです', ['status' => 400]);
        }

        // アンケートデータを保存
        $feedback_data = [
            'match_id' => $match_id,
            'team_id' => $team_id,
            'user_id' => $current_user_id,
            'satisfaction' => isset($params['satisfaction']) ? intval($params['satisfaction']) : 0,
            'satisfaction_reasons' => isset($params['satisfaction_reasons']) ? $params['satisfaction_reasons'] : [],
            'opponent_rating' => isset($params['opponent_rating']) ? intval($params['opponent_rating']) : 0,
            'opponent_reasons' => isset($params['opponent_reasons']) ? $params['opponent_reasons'] : [],
            'venue_rating' => isset($params['venue_rating']) ? intval($params['venue_rating']) : 0,
            'venue_improvement' => isset($params['venue_improvement']) ? sanitize_textarea_field($params['venue_improvement']) : '',
            'rematch_interest' => isset($params['rematch_interest']) ? sanitize_text_field($params['rematch_interest']) : '',
            'rematch_reason' => isset($params['rematch_reason']) ? sanitize_textarea_field($params['rematch_reason']) : '',
            'comment' => isset($params['comment']) ? sanitize_textarea_field($params['comment']) : '',
        ];

        if (!function_exists('aidunite_match_feedback_persist_save')) {
            return new WP_Error('save_failed', 'アンケートの保存に失敗しました', ['status' => 500]);
        }

        $save_result = aidunite_match_feedback_persist_save($feedback_data, $current_user_id);
        if (empty($save_result['success'])) {
            error_log('[MATCH_FEEDBACK_API] Error: Failed to create feedback post');
            return new WP_Error('save_failed', 'アンケートの保存に失敗しました', ['status' => 500]);
        }

        $feedback_post_id = (int) ($save_result['feedback_id'] ?? 0);

        error_log('[MATCH_FEEDBACK_API] Success: Feedback saved with ID ' . $feedback_post_id);

        // 感謝メッセージを送信するフックを発火
        do_action('aidunite_evaluation_saved', $feedback_post_id, $match_id, $team_id);

        error_log('[MATCH_FEEDBACK_API] Hook fired: aidunite_evaluation_saved with evaluation_id=' . $feedback_post_id . ', match_id=' . $match_id . ', team_id=' . $team_id);

        return new WP_REST_Response([
            'success' => true,
            'message' => 'アンケートを保存しました',
            'data' => [
                'feedback_id' => $feedback_post_id
            ]
        ], 200);

    } catch (Exception $e) {
        error_log('[MATCH_FEEDBACK_API] Exception: ' . $e->getMessage());
        return new WP_Error('server_error', 'サーバーエラーが発生しました', ['status' => 500]);
    }
}

/**
 * マッチアンケートを取得
 * 相手に回答を見せないため、ログインユーザーの所属チームのフィードバックのみ返す。
 */
function aidunite_get_match_feedback($request) {
    try {
        $current_user_id = get_current_user_id();
        $team_scope = aidunite_match_resolve_user_team_scope($current_user_id);
        if (empty($team_scope)) {
            return new WP_REST_Response([
                'success' => true,
                'data' => []
            ], 200);
        }

        $params = $request->get_params();
        $match_id = isset($params['match_id']) ? intval($params['match_id']) : 0;

        $feedbacks = function_exists('aidunite_match_feedback_read_posts_for_teams')
            ? aidunite_match_feedback_read_posts_for_teams($team_scope, $match_id)
            : [];

        $results = [];
        foreach ($feedbacks as $feedback) {
            $row = function_exists('aidunite_match_feedback_format_rest_row')
                ? aidunite_match_feedback_format_rest_row((int) $feedback->ID)
                : [];
            if ($row !== []) {
                $results[] = $row;
            }
        }

        return new WP_REST_Response([
            'success' => true,
            'data' => $results
        ], 200);

    } catch (Exception $e) {
        error_log('[MATCH_FEEDBACK_API] Exception: ' . $e->getMessage());
        return new WP_Error('server_error', 'サーバーエラーが発生しました', ['status' => 500]);
    }
}
