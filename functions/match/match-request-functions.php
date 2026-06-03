<?php
/**
 * マッチ申請関連の関数
 * AidUnite Theme - Match Request Functions
 */

if (!defined('ABSPATH')) {
    exit;
}

if (function_exists('aidunite_match_board_ensure_dependencies')) {
    aidunite_match_board_ensure_dependencies();
} else {
    require_once get_stylesheet_directory() . '/functions/common/error-handler.php';
    require_once get_stylesheet_directory() . '/functions/match/match-gender-venue-helpers.php';
    require_once get_stylesheet_directory() . '/functions/match/match-apply-evaluation.php';
}

if (!function_exists('aidunite_reset_match_request_lifecycle_meta_for_reapply')) {
    /**
     * 再申請時に、前回成立/キャンセルの痕跡メタをクリアする。
     * status を pending に戻しただけでは旧 established_at が残り、後続キャンセル判定が誤るため。
     *
     * @param int $request_id
     * @return void
     */
    function aidunite_reset_match_request_lifecycle_meta_for_reapply($request_id) {
        $request_id = (int) $request_id;
        if ($request_id <= 0) {
            return;
        }
        $keys_to_delete = [
            'accepted_at',
            'established_at',
            'rejected_at',
            'canceled_at',
            'canceled_by_team_id',
            'established_gender_slot',
            'requires_reconfirm',
            'reconfirm_reason',
            'reconfirm_detected_at',
            'superseded_by_request_id',
            'reconfirm_before_schedule_place',
            'reconfirm_before_schedule_gender',
            'reconfirm_before_male_slots',
            'reconfirm_before_female_slots',
            'reconfirm_before_place_lock',
            'proposal_pending_accept',
            'proposal_by_team_id',
            'proposal_created_at',
            'proposal_accepted_at',
            'last_proposed_by_team_id',
            'last_proposed_at',
            'last_proposal_source',
            'mr_outcome_code',
            'mr_outcome_updated_at',
        ];
        foreach ($keys_to_delete as $key) {
            delete_post_meta($request_id, $key);
        }

        if (function_exists('aidunite_clear_match_chat_bindings_for_reapply')) {
            aidunite_clear_match_chat_bindings_for_reapply($request_id);
        }
    }
}

if (!function_exists('aidunite_clear_match_chat_bindings_for_reapply')) {
    /**
     * 再申請時に前回成立・キャンセルで残ったチャットポインタを外す。
     * completed ルームを pending 表示や承認前の遷移に再利用しないため。
     *
     * @param int $request_id
     * @return void
     */
    function aidunite_clear_match_chat_bindings_for_reapply($request_id) {
        $request_id = (int) $request_id;
        if ($request_id <= 0) {
            return;
        }

        if (function_exists('aidunite_complete_active_match_request_chat_rooms')) {
            aidunite_complete_active_match_request_chat_rooms($request_id);
        }

        delete_post_meta($request_id, 'chat_room_id');

        $match_game_id = function_exists('aidunite_resolve_match_game_id_for_match_request')
            ? (int) aidunite_resolve_match_game_id_for_match_request($request_id)
            : (int) get_post_meta($request_id, 'to_schedule_id', true);
        if ($match_game_id <= 0 || $match_game_id === 9999) {
            return;
        }

        if (
            function_exists('aidunite_count_game_established_match_requests')
            && aidunite_count_game_established_match_requests($match_game_id, 0) > 0
        ) {
            return;
        }

        delete_post_meta($match_game_id, 'active_match_chat_room_id');
    }
}

if (!function_exists('aidunite_find_team_schedule_on_recruit_date')) {
    /**
     * 募集 schedule と同日の、指定チームの schedule を1件探す（?id= 通知導線の my_schedule 補正用）
     *
     * @param int $team_id
     * @param int $other_schedule_id
     * @return int schedule ID or 0
     */
    function aidunite_find_team_schedule_on_recruit_date($team_id, $other_schedule_id) {
        $team_id = (int) $team_id;
        $other_schedule_id = (int) $other_schedule_id;
        if ($team_id <= 0 || $other_schedule_id <= 0) {
            return 0;
        }
        $schedule_date = (string) get_post_meta($other_schedule_id, 'schedule_date', true);
        if ($schedule_date === '') {
            return 0;
        }
        $posts = get_posts([
            'post_type'      => 'schedule',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'orderby'        => 'ID',
            'order'          => 'DESC',
            'meta_query'     => [
                'relation' => 'AND',
                [
                    'key'     => 'team_id',
                    'value'   => $team_id,
                    'compare' => '=',
                ],
                [
                    'key'     => 'schedule_date',
                    'value'   => $schedule_date,
                    'compare' => '=',
                ],
            ],
            'fields' => 'ids',
        ]);

        return !empty($posts[0]) ? (int) $posts[0] : 0;
    }
}

if (!function_exists('aidunite_resolve_my_schedule_id_for_match_application')) {
    /**
     * 申請 POST の my_schedule_id が他チーム所有のとき、操作中チームの schedule に補正する。
     * 通知リンク ?id=match_request から再申請した場合の schedule_actor_team_mismatch 防止。
     *
     * @param int $actor_team_id
     * @param int $posted_my_schedule_id
     * @param int $other_schedule_id
     * @param int $match_request_id
     * @return int
     */
    function aidunite_resolve_my_schedule_id_for_match_application($actor_team_id, $posted_my_schedule_id, $other_schedule_id, $match_request_id = 0) {
        $actor_team_id = (int) $actor_team_id;
        $posted_my_schedule_id = (int) $posted_my_schedule_id;
        $other_schedule_id = (int) $other_schedule_id;
        $match_request_id = (int) $match_request_id;

        if ($posted_my_schedule_id > 0) {
            $sch_team = (int) get_post_meta($posted_my_schedule_id, 'team_id', true);
            if ($sch_team <= 0 || $sch_team === $actor_team_id) {
                return $posted_my_schedule_id;
            }
        }

        if ($match_request_id > 0) {
            $from_team = (int) get_post_meta($match_request_id, 'from_team_id', true);
            $mr_my = (int) get_post_meta($match_request_id, 'my_schedule_id', true);
            if ($from_team === $actor_team_id && $mr_my > 0) {
                $owner = (int) get_post_meta($mr_my, 'team_id', true);
                if ($owner <= 0 || $owner === $actor_team_id) {
                    return $mr_my;
                }
            }
        }

        $found = aidunite_find_team_schedule_on_recruit_date($actor_team_id, $other_schedule_id);

        return $found > 0 ? $found : $posted_my_schedule_id;
    }
}

if (!function_exists('aidunite_touch_match_request_post_modified')) {
    /**
     * match_request の post_modified のみ進める。update_post_meta では投稿本体の更新時刻が進まないため、
     * get_latest_match_request_bidirectional の重複畳み込み／タイブレークと掲示板表示の整合に使う。
     *
     * @param int $request_id
     */
    function aidunite_touch_match_request_post_modified($request_id) {
        $request_id = (int) $request_id;
        if ($request_id <= 0) {
            return;
        }
        wp_update_post([
            'ID'                => $request_id,
            'post_modified'     => current_time('mysql'),
            'post_modified_gmt' => current_time('mysql', true),
        ]);
    }
}

// チェックリスト保存用Ajaxハンドラ
add_action('wp_ajax_aidunite_save_checklist', 'aidunite_save_match_checklist');
function aidunite_save_match_checklist() {
    // 統一認証・権限チェック
    $auth_result = AidUniteAuthMiddleware::require_auth();
    if (!$auth_result->is_valid()) {
        // 認証エラーはcritical
        AidUniteApiResponse::send_critical_error(
            $auth_result->error ?: 'ログインが必要です',
            'authentication_required'
        );
        return;
    }

    // CSRF対策
    $nonce_result = AidUniteAuthMiddleware::verify_nonce('security', 'aidunite_checklist_nonce');
    if (is_wp_error($nonce_result)) {
        // CSRFエラーはcritical
        AidUniteApiResponse::send_critical_error(
            'セキュリティチェックに失敗しました。ページを再読み込みして再度お試しください。',
            'csrf_verification_failed'
        );
        return;
    }

    $user_id = $auth_result->user_id;
    $checklist_key = AidUniteAuthMiddleware::sanitize($_POST['checklist_key'] ?? '', 'text');
    $item_id = AidUniteAuthMiddleware::sanitize($_POST['item_id'] ?? '', 'text');
    $checked = isset($_POST['checked']) && $_POST['checked'] === '1';

    // チェックリストデータを取得
    $checklist_data = get_user_meta($user_id, $checklist_key, true);
    if (!is_array($checklist_data)) {
        $checklist_data = [];
    }

    // 項目の状態を更新
    $checklist_data[$item_id] = $checked;

    // 保存
    update_user_meta($user_id, $checklist_key, $checklist_data);

    wp_send_json_success(['message' => 'チェックリストを保存しました']);
}

/**
 * 会場側（anchor team）によるゲーム解散：同一 match_game_id の活動中 MR をまとめてキャンセル
 *
 * @param int $primary_request_id 操作の起点となった match_request ID
 * @param int $actor_team_id      実行ユーザーの team_id（主催と一致すること）
 * @return bool 対象があって処理したら true
 */
function aidunite_host_dissolve_game_match_requests($primary_request_id, $actor_team_id) {
    $primary_request_id = (int) $primary_request_id;
    $actor_team_id      = (int) $actor_team_id;
    if ($primary_request_id <= 0 || $actor_team_id <= 0) {
        return false;
    }
    $match_game_id = function_exists('aidunite_resolve_match_game_id_for_match_request')
        ? (int) aidunite_resolve_match_game_id_for_match_request($primary_request_id)
        : 0;
    if ($match_game_id <= 0 || $match_game_id === 9999) {
        return false;
    }

    $host_team = function_exists('aidunite_get_anchor_team_id_for_match_game')
        ? (int) aidunite_get_anchor_team_id_for_match_game($match_game_id)
        : 0;
    if ($host_team !== $actor_team_id) {
        return false;
    }

    if (!function_exists('aidunite_get_game_match_requests')) {
        return false;
    }

    if (function_exists('aidunite_match_flow_debug_log')) {
        aidunite_match_flow_debug_log('host_dissolve_start', [
            'primary_request_id' => $primary_request_id,
            'actor_team_id'      => $actor_team_id,
            'match_game_id' => $match_game_id,
        ]);
    }

    $established_for_restore = [];
    $to_cancel               = [];
    foreach (aidunite_get_game_match_requests($match_game_id) as $p) {
        $meta = (string) get_post_meta($p->ID, 'status', true);
        $st   = aidunite_normalize_match_request_status($meta, isset($p->post_status) ? (string) $p->post_status : '');
        if (!in_array($st, ['pending', 'accepted', 'established'], true)) {
            continue;
        }
        $to_cancel[] = $p;
        if (get_post_meta($p->ID, 'established_at', true) !== '') {
            $established_for_restore[] = $p;
        }
    }
    if ($to_cancel === []) {
        return false;
    }

    foreach ($to_cancel as $p) {
        $rid = (int) $p->ID;
        aidunite_update_match_request_status_meta($rid, 'canceled');
        update_post_meta($rid, 'canceled_at', current_time('mysql'));
        update_post_meta($rid, 'canceled_by_team_id', $actor_team_id);
        if (function_exists('aidunite_mark_match_chat_completed')) {
            aidunite_mark_match_chat_completed($rid, 'canceled');
        }
    }

    if (function_exists('send_match_game_dissolved_notification')) {
        send_match_game_dissolved_notification($match_game_id, $to_cancel, $actor_team_id);
    }

    if ($established_for_restore !== [] && function_exists('aidunite_rollback_match_game_on_host_dissolve')) {
        aidunite_rollback_match_game_on_host_dissolve($match_game_id, $established_for_restore);
    }

    if (function_exists('aidunite_rebuild_recruit_schedule_participants_from_game_match_requests')) {
        aidunite_rebuild_recruit_schedule_participants_from_game_match_requests($match_game_id);
    }
    if (function_exists('aidunite_sync_match_board_status_from_game')) {
        aidunite_sync_match_board_status_from_game($match_game_id);
    }

    if (function_exists('aidunite_match_flow_debug_log')) {
        aidunite_match_flow_debug_log('host_dissolve_done', [
            'primary_request_id'   => $primary_request_id,
            'match_game_id'        => $match_game_id,
            'canceled_request_ids' => array_map(static function ($p) {
                return (int) $p->ID;
            }, $to_cancel),
        ]);
    }

    return true;
}

/**
 * 申請のキャンセル処理
 */
function au_cancel_match_application() {
    // 統一認証・権限チェック
    $auth_result = AidUniteAuthMiddleware::require_team_membership();
    if (!$auth_result->is_valid()) {
        // 認証エラーはcritical
        AidUniteApiResponse::send_critical_error(
            $auth_result->error ?: '認証が必要です',
            'authentication_required'
        );
        return;
    }

    // CSRF対策
    $nonce_result = AidUniteAuthMiddleware::verify_nonce('security', 'au_match_nonce');
    if (is_wp_error($nonce_result)) {
        // CSRFエラーはcritical
        AidUniteApiResponse::send_critical_error(
            'セキュリティチェックに失敗しました。ページを再読み込みして再度お試しください。',
            'csrf_verification_failed'
        );
        return;
    }

    $my_schedule_id = AidUniteAuthMiddleware::sanitize($_POST['my_schedule_id'] ?? 0, 'int');
    $other_schedule_id = AidUniteAuthMiddleware::sanitize($_POST['other_schedule_id'] ?? 0, 'int');
    if (empty($my_schedule_id) || empty($other_schedule_id)) {
        AidUniteApiResponse::send_validation_error(
            ['my_schedule_id' => '必要なパラメータが不足しています'],
            '入力内容を確認してください'
        );
        return;
    }

    $current_user_id = get_current_user_id();
    $current_user_team_id = function_exists('aidunite_resolve_user_team_id_for_schedule_ops')
        ? (int) aidunite_resolve_user_team_id_for_schedule_ops($current_user_id)
        : (int) get_user_meta($current_user_id, 'team_id', true);
    if ($current_user_team_id <= 0) {
        AidUniteApiResponse::send_error('チーム情報が見つかりません', null, 'normal', 'team_not_found');
        return;
    }

    $other_team_id = (int) get_post_meta($other_schedule_id, 'team_id', true);
    if ($other_team_id <= 0) {
        AidUniteApiResponse::send_error('相手チームの情報が見つかりません', null, 'normal', 'other_team_not_found');
        return;
    }

    $latest_request = get_latest_match_request_bidirectional($current_user_team_id, $my_schedule_id, $other_team_id, $other_schedule_id);
    if (!$latest_request) {
        AidUniteApiResponse::send_error('申請が見つかりません', null, 'normal', 'request_not_found');
        return;
    }

    // 旧APIは独自キャンセル処理を持たず、統一ステータス更新APIへ委譲する（挙動差分を防止）
    $_POST['request_id'] = (string) ((int) $latest_request->ID);
    $_POST['status'] = 'canceled';
    au_update_match_request_status();
}

// AJAXハンドラを登録
add_action('wp_ajax_au_cancel_match_application', 'au_cancel_match_application');
add_action('wp_ajax_au_reapply_match_application', 'au_reapply_match_application');
add_action('wp_ajax_au_propose_reconfirm_conditions', 'au_propose_reconfirm_conditions');
add_action('wp_ajax_au_accept_reconfirm_proposal', 'au_accept_reconfirm_proposal');

/**
 * 再申請処理
 */
function au_reapply_match_application() {
    // 統一認証・権限チェック
    $auth_result = AidUniteAuthMiddleware::require_team_membership();
    if (!$auth_result->is_valid()) {
        // 認証エラーはcritical
        AidUniteApiResponse::send_critical_error(
            $auth_result->error ?: '認証が必要です',
            'authentication_required'
        );
        return;
    }

    // CSRF対策
    $nonce_result = AidUniteAuthMiddleware::verify_nonce('security', 'au_match_nonce');
    if (is_wp_error($nonce_result)) {
        // CSRFエラーはcritical
        AidUniteApiResponse::send_critical_error(
            'セキュリティチェックに失敗しました。ページを再読み込みして再度お試しください。',
            'csrf_verification_failed'
        );
        return;
    }

    $request_id = AidUniteAuthMiddleware::sanitize($_POST['request_id'] ?? 0, 'int');

    if (empty($request_id)) {
        // バリデーションエラー（フォームエラーとして扱う）
        AidUniteApiResponse::send_validation_error(
            ['request_id' => '必要なパラメータが不足しています'],
            '入力内容を確認してください'
        );
        return;
    }

    // 申請情報を取得
    $from_team_id = get_post_meta($request_id, 'from_team_id', true);
    $to_schedule_id = get_post_meta($request_id, 'to_schedule_id', true);
    $to_team_id = get_post_meta($to_schedule_id, 'team_id', true);
    $current_status = get_post_meta($request_id, 'status', true);

    // 現在のユーザーのチームIDを取得（操作中 team）
    $current_user_id = get_current_user_id();
    $current_user_team_id = function_exists('aidunite_resolve_user_team_id_for_schedule_ops')
        ? (int) aidunite_resolve_user_team_id_for_schedule_ops($current_user_id)
        : (int) get_user_meta($current_user_id, 'team_id', true);

    // 権限チェック：申請者または受信者のみが再申請可能
    if ($current_user_team_id != $from_team_id && $current_user_team_id != $to_team_id) {
        // 権限エラーはcritical
        AidUniteApiResponse::send_critical_error(
            '再申請する権限がありません',
            'permission_denied'
        );
        return;
    }

    // ステータスが拒否済みでない場合は再申請不可
    if ($current_status != 'rejected') {
        AidUniteApiResponse::send_error(
            '拒否された申請のみ再申請できます',
            null,
            'normal',
            'invalid_status'
        );
        return;
    }

    // ステータスを申請中に更新（保存値は英語 canonical）
    $updated = aidunite_update_match_request_status_meta($request_id, 'pending') !== '';

    if ($updated) {
        if (function_exists('aidunite_reset_match_request_lifecycle_meta_for_reapply')) {
            aidunite_reset_match_request_lifecycle_meta_for_reapply($request_id);
        }
        if (function_exists('aidunite_match_flow_debug_log')) {
            aidunite_match_flow_debug_log('ajax_reapply', [
                'request_id'    => (int) $request_id,
                'prior_status'  => (string) $current_status,
                'actor_team_id' => (int) $current_user_team_id,
            ]);
        }
        // from_team_idとto_team_idを正しく更新
        $my_schedule_id = get_post_meta($request_id, 'my_schedule_id', true);
        $my_schedule_team_id = get_post_meta($my_schedule_id, 'team_id', true);
        $other_schedule_team_id = get_post_meta($to_schedule_id, 'team_id', true);

        // 正しいfrom_team_idとto_team_idを設定
        if ($current_user_team_id == $my_schedule_team_id) {
            // 現在のユーザーが申請者
            update_post_meta($request_id, 'from_team_id', $current_user_team_id);
            update_post_meta($request_id, 'other_team_id', $other_schedule_team_id);
            update_post_meta($request_id, 'to_team_id', $other_schedule_team_id);
        } else {
            // 現在のユーザーが受信者
            update_post_meta($request_id, 'from_team_id', $other_schedule_team_id);
            update_post_meta($request_id, 'other_team_id', $current_user_team_id);
            update_post_meta($request_id, 'to_team_id', $current_user_team_id);
        }

        aidunite_touch_match_request_post_modified((int) $request_id);

        // デバッグログ
        // 掲示板ステータスも更新
        update_board_status_on_apply($to_schedule_id);

        // 再申請通知を送信
        if ($current_user_team_id == $from_team_id) {
            // 申請者が再申請 → 受信者に通知
            send_match_request_received_notification($request_id, $to_team_id, $from_team_id);
        } else {
            // 受信者が再申請 → 申請者に通知
            send_match_request_received_notification($request_id, $from_team_id, $to_team_id);
        }

        AidUniteApiResponse::send_success(
            ['message' => '再申請しました'],
            '再申請しました'
        );
    } else {
        AidUniteApiResponse::send_error(
            '再申請に失敗しました。しばらく時間をおいて再度お試しください。',
            null,
            'normal',
            'reapply_failed'
        );
    }
}

/**
 * マッチ申請の共通処理（REST POST /match-request とレガシー Ajax の共有本体）。
 * match_request の status メタは英語 canonical のみ保存（pending / accepted / …）。
 *
 * @param array $post_data POST 相当（selected_* または preferred_*）
 * @param int   $user_id
 * @return array{success:bool, request_id?:int, message?:string, code?:string}
 */
function aidunite_save_match_application_core(array $post_data, $user_id) {
    $user_id = (int) $user_id;

    if (function_exists('aidunite_normalize_match_request_payload')) {
        $post_data = aidunite_normalize_match_request_payload($post_data, true);
    }

    if (function_exists('aidunite_onboarding_bot_guard_apply')) {
        $blocked = aidunite_onboarding_bot_guard_apply($post_data, $user_id);
        if (is_array($blocked)) {
            return $blocked;
        }
    }

    $my_team_id = function_exists('aidunite_resolve_user_team_id_for_schedule_ops')
        ? (int) aidunite_resolve_user_team_id_for_schedule_ops($user_id)
        : (int) get_user_meta($user_id, 'team_id', true);
    if (!$my_team_id) {
        return ['success' => false, 'message' => 'team_not_found', 'code' => 'team_not_found'];
    }

    $my_schedule_id = (int) ($post_data['my_schedule_id'] ?? 0);
    $other_schedule_id = (int) ($post_data['other_schedule_id'] ?? 0);
    $match_request_id_hint = (int) ($post_data['request_id'] ?? $post_data['match_request_id'] ?? 0);
    $selected_start_time = sanitize_text_field($post_data['selected_start_time'] ?? $post_data['preferred_start'] ?? '');
    $selected_end_time = sanitize_text_field($post_data['selected_end_time'] ?? $post_data['preferred_end'] ?? '');
    $selected_place = sanitize_text_field($post_data['selected_place'] ?? $post_data['preferred_place'] ?? '');
    $selected_gender = sanitize_text_field($post_data['selected_gender'] ?? $post_data['preferred_gender'] ?? '');
    if (!function_exists('aidunite_normalize_match_request_payload')) {
        if (function_exists('aidunite_normalize_gender_canonical')) {
            $selected_gender = aidunite_normalize_gender_canonical($selected_gender);
        }
        if ($selected_place === 'both') {
            $selected_place = 'either';
        }
    }

    $other_schedule = get_post($other_schedule_id);
    if (!$other_schedule || $other_schedule->post_type !== 'schedule') {
        return ['success' => false, 'message' => 'schedule_not_found', 'code' => 'schedule_not_found'];
    }
    $target_schedule_team_id = function_exists('aidunite_resolve_schedule_owner_team_id')
        ? (int) aidunite_resolve_schedule_owner_team_id($other_schedule_id)
        : (int) get_post_meta($other_schedule_id, 'team_id', true);
    if ($target_schedule_team_id <= 0) {
        $other_author = (int) get_post_field('post_author', $other_schedule_id);
        if ($other_author > 0) {
            $target_schedule_team_id = function_exists('aidunite_get_current_team_id')
                ? (int) aidunite_get_current_team_id($other_author)
                : (int) get_user_meta($other_author, 'team_id', true);
        }
    }

    // 相手のみモード：予定がない場合は申請時に「仮」スケジュールを自動作成
    if ($my_schedule_id === 0) {
        $schedule_date = get_post_meta($other_schedule_id, 'schedule_date', true);
        if (empty($schedule_date) || empty($selected_start_time) || empty($selected_end_time)) {
            return ['success' => false, 'message' => 'time_data_required', 'code' => 'time_data_required'];
        }
        $sched_dn = function_exists('_aidunite_match_apply_normalize_schedule_date')
            ? _aidunite_match_apply_normalize_schedule_date($schedule_date)
            : (string) $schedule_date;
        if ($sched_dn !== '' && function_exists('aidunite_team_has_guest_committed_schedule_on_date')
            && aidunite_team_has_guest_committed_schedule_on_date($my_team_id, $sched_dn)) {
            return [
                'success' => false,
                'message' => 'この対戦希望はすでに試合が確定しているため、新しい申請はできません。',
                'code'    => 'applicant_schedule_committed',
            ];
        }
        $other_place_raw = get_post_meta($other_schedule_id, 'schedule_place', true) ?: get_post_meta($other_schedule_id, 'schedule_place_option', true);
        $other_place = is_string($other_place_raw) ? strtolower(trim($other_place_raw)) : 'either';
        $other_gender_raw = get_post_meta($other_schedule_id, 'schedule_gender', true) ?: get_post_meta($other_schedule_id, 'matching_gender_condition', true);
        $other_gender = function_exists('aidunite_normalize_gender_canonical')
            ? aidunite_normalize_gender_canonical((string) $other_gender_raw)
            : (is_string($other_gender_raw) ? strtolower(trim($other_gender_raw)) : 'both');
        if ($other_gender === '') {
            $other_gender = 'both';
        }
        if ($other_place === 'both') {
            $other_place = 'either';
        }
        if (in_array($other_place, ['home', 'ホーム'], true)) {
            // 相手ホーム募集に申請する自分はアウェイ
            $my_place = 'away';
        } elseif (in_array($other_place, ['away', 'アウェイ'], true)) {
            // 相手アウェイ募集に申請する自分はホーム
            $my_place = 'home';
        } else {
            $my_place = in_array($selected_place, ['home', 'away'], true) ? $selected_place : 'home';
        }
        if (in_array($other_gender, ['male', 'female'], true)) {
            $my_gender = $other_gender;
        } else {
            $my_gender = in_array($selected_gender, ['male', 'female', 'both'], true) ? $selected_gender : 'both';
        }
        $new_schedule_id = wp_insert_post([
            'post_type'   => 'schedule',
            'post_title'  => $schedule_date . ' 練習試合（仮）',
            'post_status' => 'publish',
            'post_author' => $user_id,
        ]);
        if (is_wp_error($new_schedule_id)) {
            return ['success' => false, 'message' => 'schedule_creation_failed', 'code' => 'schedule_creation_failed'];
        }
        update_post_meta($new_schedule_id, 'schedule_date', $schedule_date);
        update_post_meta($new_schedule_id, 'schedule_end_date', $schedule_date);
        update_post_meta($new_schedule_id, 'schedule_start_time', $selected_start_time);
        update_post_meta($new_schedule_id, 'schedule_end_time', $selected_end_time);
        if (function_exists('aidunite_schedule_write_place_meta')) {
            aidunite_schedule_write_place_meta($new_schedule_id, $my_place);
        } else {
            update_post_meta($new_schedule_id, 'schedule_place', $my_place);
        }
        if (function_exists('aidunite_schedule_write_gender_meta')) {
            aidunite_schedule_write_gender_meta($new_schedule_id, $my_gender);
        } else {
            update_post_meta($new_schedule_id, 'schedule_gender', $my_gender);
        }
        update_post_meta($new_schedule_id, 'team_id', $my_team_id);
        update_post_meta($new_schedule_id, 'intent', 'tentative');
        update_post_meta($new_schedule_id, 'schedule_type', '練習試合');
        update_post_meta($new_schedule_id, 'matching', '1');
        update_post_meta($new_schedule_id, 'is_match_requested', '1');
        update_post_meta($new_schedule_id, 'aidunite_schedule_origin', 'match_apply_tentative');
        if ($my_gender === 'female') {
            update_post_meta($new_schedule_id, 'male_slots', 0);
            update_post_meta($new_schedule_id, 'female_slots', 1);
        } elseif ($my_gender === 'male') {
            update_post_meta($new_schedule_id, 'male_slots', 1);
            update_post_meta($new_schedule_id, 'female_slots', 0);
        } else {
            update_post_meta($new_schedule_id, 'male_slots', 1);
            update_post_meta($new_schedule_id, 'female_slots', 1);
        }
        $my_schedule_id = $new_schedule_id;
    }

    if ($other_schedule_id > 0 && function_exists('aidunite_resolve_my_schedule_id_for_match_application')) {
        $resolve_rid = $match_request_id_hint;
        if ($resolve_rid <= 0 && function_exists('get_latest_match_request_bidirectional')) {
            $existing_for_resolve = get_latest_match_request_bidirectional(
                $my_team_id,
                $my_schedule_id,
                $target_schedule_team_id,
                $other_schedule_id,
                ['restrict_to_to_schedule_id' => $other_schedule_id]
            );
            if ($existing_for_resolve) {
                $resolve_rid = (int) $existing_for_resolve->ID;
            }
        }
        $resolved_my_schedule_id = aidunite_resolve_my_schedule_id_for_match_application(
            $my_team_id,
            $my_schedule_id,
            $other_schedule_id,
            $resolve_rid
        );
        if ($resolved_my_schedule_id > 0) {
            $my_schedule_id = $resolved_my_schedule_id;
        }
    }

    $my_schedule = get_post($my_schedule_id);
    if (!$my_schedule || $my_schedule->post_type !== 'schedule') {
        return ['success' => false, 'message' => 'schedule_not_found', 'code' => 'schedule_not_found'];
    }

    $sch_team = (int) get_post_meta($my_schedule_id, 'team_id', true);
    if ($sch_team > 0 && $sch_team !== $my_team_id) {
        return ['success' => false, 'message' => 'schedule_actor_team_mismatch', 'code' => 'schedule_actor_team_mismatch'];
    }
    if ($sch_team <= 0 && (int) $my_schedule->post_author !== $user_id) {
        return ['success' => false, 'message' => 'schedule_actor_team_mismatch', 'code' => 'schedule_actor_team_mismatch'];
    }

    if ($my_team_id == $target_schedule_team_id) {
        return ['success' => false, 'message' => 'cannot_apply_to_own_team', 'code' => 'cannot_apply_to_own_team'];
    }

    if (function_exists('aidunite_compute_schedule_pair_overlap_times')) {
        [$overlap_start, $overlap_end] = aidunite_compute_schedule_pair_overlap_times(
            (int) $my_schedule_id,
            (int) $other_schedule_id
        );
        if ($overlap_start !== '' && $overlap_end !== '') {
            $selected_start_time = $overlap_start;
            $selected_end_time   = $overlap_end;
        }
    }

    if (empty($selected_start_time) || empty($selected_end_time)) {
        if (function_exists('aidunite_match_flow_debug_log')) {
            aidunite_match_flow_debug_log('save_application_fail', [
                'reason' => 'time_data_required',
                'my_schedule_id' => $my_schedule_id,
                'other_schedule_id' => $other_schedule_id,
            ]);
        }
        return ['success' => false, 'message' => 'time_data_required', 'code' => 'time_data_required'];
    }

    // 再確認待ちの同一 MR 更新再申請: クライアントが古い selected_place を送っても、募集＋place_lock と整合する申請者視点へ寄せる
    $existing_for_reconfirm_place = function_exists('get_latest_match_request_bidirectional')
        ? get_latest_match_request_bidirectional($my_team_id, $my_schedule_id, $target_schedule_team_id, $other_schedule_id, [
            'restrict_to_to_schedule_id' => (int) $other_schedule_id,
        ])
        : null;
    if ($existing_for_reconfirm_place) {
        $ex_st = (string) get_post_meta($existing_for_reconfirm_place->ID, 'status', true);
        $ex_norm = function_exists('aidunite_normalize_match_request_status')
            ? aidunite_normalize_match_request_status($ex_st, (string) $existing_for_reconfirm_place->post_status)
            : $ex_st;
        $ex_req = ((int) get_post_meta($existing_for_reconfirm_place->ID, 'requires_reconfirm', true) === 1);
        if (
            $ex_req
            && in_array($ex_norm, ['pending', 'accepted'], true)
            && function_exists('_aidunite_resolve_selected_place_for_established')
        ) {
            $selected_place = (string) _aidunite_resolve_selected_place_for_established('', (int) $other_schedule_id, (int) $my_schedule_id);
            if ($selected_place === 'both') {
                $selected_place = 'either';
            }
        }
    }

    if (function_exists('aidunite_evaluate_match_apply_context')) {
        $eval_args = [
            'mode'                => 'validate',
            'selected_place'      => $selected_place,
            'selected_gender'     => $selected_gender,
            'selected_start_time' => $selected_start_time,
            'selected_end_time'   => $selected_end_time,
        ];
        $ev = aidunite_evaluate_match_apply_context((int) $other_schedule_id, (int) $my_schedule_id, $eval_args);
        if (empty($ev['can_apply'])) {
            if (function_exists('aidunite_match_flow_debug_log')) {
                aidunite_match_flow_debug_log('save_application_fail', [
                    'reason'     => 'apply_evaluator',
                    'reason_code'=> $ev['reason_code'] ?? '',
                    'message'    => $ev['message'] ?? '',
                ]);
            }
            $code = !empty($ev['reason_code']) ? (string) $ev['reason_code'] : 'apply_not_allowed';
            return [
                'success' => false,
                'message' => !empty($ev['message']) ? (string) $ev['message'] : '申請条件を満たしていません。',
                'code'    => $code,
            ];
        }
    }

    $existing_request = get_latest_match_request_bidirectional($my_team_id, $my_schedule_id, $target_schedule_team_id, $other_schedule_id, [
        'restrict_to_to_schedule_id' => (int) $other_schedule_id,
    ]);

    if ($existing_request) {
        $existing_status = get_post_meta($existing_request->ID, 'status', true);
        $norm = function_exists('aidunite_normalize_match_request_status')
            ? aidunite_normalize_match_request_status((string) $existing_status, (string) $existing_request->post_status)
            : (string) $existing_status;
        $requires_reconfirm = ((int) get_post_meta($existing_request->ID, 'requires_reconfirm', true) === 1);

        // 再確認待ち（requires_reconfirm=1）は pending/accepted でも「同一MRの再申請更新」を許可する
        if (in_array($norm, ['pending', 'accepted', 'established'], true) && !($requires_reconfirm && in_array($norm, ['pending', 'accepted'], true))) {
            return ['success' => false, 'message' => 'application_already_exists', 'code' => 'application_already_exists'];
        }
        if (in_array($norm, ['canceled', 'rejected'], true) || $existing_request->post_status === 'draft' || ($requires_reconfirm && in_array($norm, ['pending', 'accepted'], true))) {
            if ($my_schedule_id > 0 && function_exists('aidunite_schedule_guest_remaining') && aidunite_schedule_guest_remaining($my_schedule_id) < 1) {
                return [
                    'success' => false,
                    'message' => 'この対戦希望はすでに試合が確定しているため、新しい申請はできません。',
                    'code'    => 'applicant_schedule_committed',
                ];
            }
            $sched_dn_re = function_exists('_aidunite_match_apply_normalize_schedule_date')
                ? _aidunite_match_apply_normalize_schedule_date(get_post_meta($other_schedule_id, 'schedule_date', true))
                : (string) get_post_meta($other_schedule_id, 'schedule_date', true);
            if ($my_schedule_id <= 0 && $sched_dn_re !== '' && function_exists('aidunite_team_has_guest_committed_schedule_on_date')
                && aidunite_team_has_guest_committed_schedule_on_date($my_team_id, $sched_dn_re)) {
                return [
                    'success' => false,
                    'message' => 'この対戦希望はすでに試合が確定しているため、新しい申請はできません。',
                    'code'    => 'applicant_schedule_committed',
                ];
            }

            $request_id = $existing_request->ID;

            if (function_exists('aidunite_reset_match_request_lifecycle_meta_for_reapply')) {
                aidunite_reset_match_request_lifecycle_meta_for_reapply($request_id);
            }
            if (function_exists('aidunite_match_request_write_application_meta')) {
                aidunite_match_request_write_application_meta((int) $request_id, [
                    'status' => 'pending',
                    'from_team_id' => $my_team_id,
                    'other_team_id' => $target_schedule_team_id,
                    'to_team_id' => $target_schedule_team_id,
                    'request_team_id' => $my_team_id,
                    'to_schedule_id' => $other_schedule_id,
                    'my_schedule_id' => $my_schedule_id,
                    'selected_start_time' => $selected_start_time,
                    'selected_end_time' => $selected_end_time,
                    'selected_place' => $selected_place,
                    'selected_gender' => $selected_gender,
                ]);
            } else {
                aidunite_update_match_request_status_meta($request_id, 'pending');
                update_post_meta($request_id, 'from_team_id', $my_team_id);
                update_post_meta($request_id, 'other_team_id', $target_schedule_team_id);
                update_post_meta($request_id, 'to_team_id', $target_schedule_team_id);
                update_post_meta($request_id, 'selected_start_time', $selected_start_time);
                update_post_meta($request_id, 'selected_end_time', $selected_end_time);
                update_post_meta($request_id, 'selected_place', $selected_place);
                update_post_meta($request_id, 'selected_gender', $selected_gender);
            }

            if (function_exists('aidunite_match_flow_debug_log')) {
                aidunite_match_flow_debug_log('save_application_ok', [
                    'mode'              => 'update_existing',
                    'request_id'        => (int) $request_id,
                    'my_team_id'        => $my_team_id,
                    'other_schedule_id' => $other_schedule_id,
                    'my_schedule_id'    => $my_schedule_id,
                    'status'            => 'pending',
                ]);
            }

            if (function_exists('aidunite_match_request_ensure_link_team_meta')) {
                aidunite_match_request_ensure_link_team_meta((int) $request_id);
            }

            wp_update_post([
                'ID'                => (int) $request_id,
                'post_status'       => 'publish',
                'post_title'        => "マッチ申請: チーム{$my_team_id} → スケジュール{$other_schedule_id}",
                'post_content'      => "申請時間: {$selected_start_time} - {$selected_end_time}",
                'post_modified'     => current_time('mysql'),
                'post_modified_gmt' => current_time('mysql', true),
            ]);

            $notify_terminal_reapply = in_array($norm, ['canceled', 'rejected'], true)
                || $existing_request->post_status === 'draft';
            if ($notify_terminal_reapply && function_exists('aidunite_match_request_after_create_hooks')) {
                aidunite_match_request_after_create_hooks((int) $request_id, [
                    'notify'                => true,
                    'reapply_received_only' => true,
                    'to_schedule_id'        => (int) $other_schedule_id,
                    'from_team_id'          => (int) $my_team_id,
                    'to_team_id'            => (int) $target_schedule_team_id,
                ]);
            }

            return ['success' => true, 'request_id' => (int) $request_id, 'message' => 'application_updated'];
        }
    }

    $create_args = [
        'post_author' => $user_id,
        'post_title' => "マッチ申請: チーム{$my_team_id} → スケジュール{$other_schedule_id}",
        'post_content' => "申請時間: {$selected_start_time} - {$selected_end_time}",
        'from_team_id' => $my_team_id,
        'other_team_id' => $target_schedule_team_id,
        'to_team_id' => $target_schedule_team_id,
        'request_team_id' => $my_team_id,
        'to_schedule_id' => $other_schedule_id,
        'my_schedule_id' => $my_schedule_id,
        'status' => 'pending',
        'selected_start_time' => $selected_start_time,
        'selected_end_time' => $selected_end_time,
        'selected_place' => $selected_place,
        'selected_gender' => $selected_gender,
    ];

    if (!function_exists('aidunite_match_request_create_application_post')) {
        return ['success' => false, 'message' => 'creation_failed', 'code' => 'persist_unavailable'];
    }

    $request_id = aidunite_match_request_create_application_post($create_args);

    if (is_wp_error($request_id)) {
        if (function_exists('aidunite_match_flow_debug_log')) {
            aidunite_match_flow_debug_log('save_application_fail', [
                'reason' => 'creation_failed',
                'wp_error' => $request_id->get_error_message(),
            ]);
        }
        return ['success' => false, 'message' => 'creation_failed', 'code' => 'creation_failed'];
    }

    if (function_exists('aidunite_match_flow_debug_log')) {
        aidunite_match_flow_debug_log('save_application_ok', [
            'mode'              => 'create',
            'request_id'        => (int) $request_id,
            'my_team_id'        => $my_team_id,
            'other_schedule_id' => $other_schedule_id,
            'my_schedule_id'    => $my_schedule_id,
            'status'            => 'pending',
        ]);
    }

    if (function_exists('aidunite_match_request_after_create_hooks')) {
        aidunite_match_request_after_create_hooks((int) $request_id, [
            'notify' => true,
            'to_schedule_id' => (int) $other_schedule_id,
            'from_team_id' => (int) $my_team_id,
            'to_team_id' => (int) $target_schedule_team_id,
        ]);
    }

    return ['success' => true, 'request_id' => (int) $request_id, 'message' => 'application_created'];
}

/**
 * 申請の承認・拒否処理（match-notifications.phpに統合済み）
 */

/**
 * 申請受信通知を送信（match-notifications.phpに統合済み）
 */

/**
 * チームが受けた申請を取得
 */
function get_received_match_requests_for_team($team_id) {
    if (!$team_id) {
        return [];
    }

    // チームのスケジュールIDを取得（複数方法で検索）
    $team_schedules = get_posts([
        'post_type' => 'schedule',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'meta_query' => [
            [
                'key' => 'team_id',
                'value' => $team_id,
            ]
        ]
    ]);

    // 投稿者でも検索（ユーザーIDが異なる場合の対策）
    $current_user = wp_get_current_user();
    $author_schedules = get_posts([
        'post_type' => 'schedule',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'author' => $current_user->ID
    ]);

    // 重複を除いてマージ
    $team_schedules = array_unique(array_merge($team_schedules, $author_schedules), SORT_REGULAR);

    if (empty($team_schedules)) {
        return [];
    }

    $schedule_ids = array_map(function($schedule) {
        return $schedule->ID;
    }, $team_schedules);

    // 申請を取得（より包括的な検索）
    $requests = get_posts([
        'post_type' => 'match_request',
        'post_status' => ['publish', 'draft'],
        'posts_per_page' => -1,
        'meta_query' => [
            'relation' => 'OR',
            [
                'key' => 'to_schedule_id',
                'value' => $schedule_ids,
                'compare' => 'IN'
            ],
            [
                'key' => 'target_schedule_id',
                'value' => $schedule_ids,
                'compare' => 'IN'
            ]
        ],
        'orderby' => 'date',
        'order' => 'DESC'
    ]);


    // 自分自身への申請を除外
    $filtered_requests = [];
    foreach ($requests as $request) {
        $from_team_id = get_post_meta($request->ID, 'from_team_id', true);
        $to_schedule_id = get_post_meta($request->ID, 'to_schedule_id', true);
        if ($from_team_id != $team_id) {
            $filtered_requests[] = $request;
        }
    }

    return $filtered_requests;
}

/**
 * 再確認待ちMRに対して、募集側が最新条件を再提示する。
 */
function au_propose_reconfirm_conditions() {
    $auth_result = AidUniteAuthMiddleware::require_team_membership();
    if (!$auth_result->is_valid()) {
        wp_send_json_error(['message' => $auth_result->error ?: '認証が必要です', 'code' => 'authentication_required'], 403);
        return;
    }
    $nonce_result = AidUniteAuthMiddleware::verify_nonce('security', 'au_match_nonce');
    if (is_wp_error($nonce_result)) {
        wp_send_json_error(['message' => 'セキュリティチェックに失敗しました。', 'code' => 'csrf_verification_failed'], 403);
        return;
    }
    $request_id = AidUniteAuthMiddleware::sanitize($_POST['request_id'] ?? 0, 'int');
    if ($request_id <= 0) {
        wp_send_json_error(['message' => '申請IDが不正です。', 'code' => 'invalid_request_id'], 400);
        return;
    }
    $to_schedule_id = (int) get_post_meta($request_id, 'to_schedule_id', true);
    $to_team_id = function_exists('aidunite_resolve_schedule_owner_team_id')
        ? (int) aidunite_resolve_schedule_owner_team_id($to_schedule_id)
        : (int) get_post_meta($to_schedule_id, 'team_id', true);
    $actor_team_id = function_exists('aidunite_resolve_user_team_id_for_schedule_ops')
        ? (int) aidunite_resolve_user_team_id_for_schedule_ops(get_current_user_id())
        : (int) get_user_meta(get_current_user_id(), 'team_id', true);
    if ($to_team_id <= 0 || $actor_team_id !== $to_team_id) {
        wp_send_json_error(['message' => '再提示する権限がありません。', 'code' => 'permission_denied'], 403);
        return;
    }
    if ((int) get_post_meta($request_id, 'requires_reconfirm', true) !== 1) {
        wp_send_json_error(['message' => 'この申請は再確認待ちではありません。', 'code' => 'not_reconfirm_required'], 409);
        return;
    }

    $current_place = (string) (get_post_meta($to_schedule_id, 'schedule_place', true) ?: get_post_meta($to_schedule_id, 'schedule_place_option', true));
    $current_gender = (string) (get_post_meta($to_schedule_id, 'schedule_gender', true) ?: get_post_meta($to_schedule_id, 'matching_gender_condition', true));
    $current_start = (string) get_post_meta($to_schedule_id, 'schedule_start_time', true);
    $current_end = (string) get_post_meta($to_schedule_id, 'schedule_end_time', true);

    // MR の selected_place は「申請者視点」。募集側 schedule の home/away をそのままコピーすると
    // 主催ホーム＝相手アウェイ申請、の整合が崩れる。承認確定時と同じ解決関数へ寄せる。
    $my_schedule_id_for_place = (int) get_post_meta($request_id, 'my_schedule_id', true);
    if ($my_schedule_id_for_place <= 0) {
        $my_schedule_id_for_place = (int) get_post_meta($request_id, 'from_schedule_id', true);
    }
    $applicant_place = '';
    if ($to_schedule_id > 0 && $my_schedule_id_for_place > 0 && function_exists('_aidunite_resolve_selected_place_for_established')) {
        $applicant_place = (string) _aidunite_resolve_selected_place_for_established('', $to_schedule_id, $my_schedule_id_for_place);
    }
    $selection_args = [];
    if ($applicant_place !== '' && in_array($applicant_place, ['home', 'away', 'either'], true)) {
        $selection_args['selected_place'] = $applicant_place;
    } elseif ($current_place !== '') {
        $selection_args['selected_place'] = $current_place;
    }
    if ($current_gender !== '') {
        $selection_args['selected_gender'] = $current_gender;
    }
    if ($current_start !== '' && $current_end !== '') {
        $selection_args['selected_start_time'] = $current_start;
        $selection_args['selected_end_time'] = $current_end;
    }
    if (!empty($selection_args) && function_exists('aidunite_match_request_write_application_meta')) {
        aidunite_match_request_write_application_meta((int) $request_id, $selection_args);
    } elseif (!empty($selection_args)) {
        foreach ($selection_args as $k => $v) {
            update_post_meta($request_id, $k, $v);
        }
    }

    update_post_meta($request_id, 'proposal_pending_accept', 1);
    update_post_meta($request_id, 'proposal_by_team_id', $actor_team_id);
    update_post_meta($request_id, 'proposal_created_at', current_time('mysql'));
    update_post_meta($request_id, 'last_proposed_by_team_id', $actor_team_id);
    update_post_meta($request_id, 'last_proposed_at', current_time('mysql'));
    update_post_meta($request_id, 'last_proposal_source', 'receiver_proposal');

    if (function_exists('send_match_request_reconfirm_proposed_notification')) {
        send_match_request_reconfirm_proposed_notification($request_id, $actor_team_id);
    }

    wp_send_json_success([
        'message' => '最新条件を提案しました。相手チームの承諾を待っています。',
        'code' => 'proposal_pending_accept',
        'request_id' => $request_id,
    ]);
}

/**
 * 申請側が募集側の再提示を承諾する。
 */
function au_accept_reconfirm_proposal() {
    $auth_result = AidUniteAuthMiddleware::require_team_membership();
    if (!$auth_result->is_valid()) {
        wp_send_json_error(['message' => $auth_result->error ?: '認証が必要です', 'code' => 'authentication_required'], 403);
        return;
    }
    $nonce_result = AidUniteAuthMiddleware::verify_nonce('security', 'au_match_nonce');
    if (is_wp_error($nonce_result)) {
        wp_send_json_error(['message' => 'セキュリティチェックに失敗しました。', 'code' => 'csrf_verification_failed'], 403);
        return;
    }
    $request_id = AidUniteAuthMiddleware::sanitize($_POST['request_id'] ?? 0, 'int');
    if ($request_id <= 0) {
        wp_send_json_error(['message' => '申請IDが不正です。', 'code' => 'invalid_request_id'], 400);
        return;
    }
    $from_team_id = (int) get_post_meta($request_id, 'from_team_id', true);
    $actor_team_id = function_exists('aidunite_resolve_user_team_id_for_schedule_ops')
        ? (int) aidunite_resolve_user_team_id_for_schedule_ops(get_current_user_id())
        : (int) get_user_meta(get_current_user_id(), 'team_id', true);
    if ($from_team_id <= 0 || $actor_team_id !== $from_team_id) {
        wp_send_json_error(['message' => '承諾する権限がありません。', 'code' => 'permission_denied'], 403);
        return;
    }
    if ((int) get_post_meta($request_id, 'proposal_pending_accept', true) !== 1) {
        wp_send_json_error(['message' => '承諾待ちの提案がありません。', 'code' => 'proposal_not_pending'], 409);
        return;
    }

    update_post_meta($request_id, 'proposal_pending_accept', 0);
    update_post_meta($request_id, 'proposal_accepted_at', current_time('mysql'));
    if (function_exists('aidunite_clear_match_request_reconfirm_meta')) {
        aidunite_clear_match_request_reconfirm_meta($request_id);
    } else {
        delete_post_meta($request_id, 'requires_reconfirm');
        delete_post_meta($request_id, 'reconfirm_reason');
    }

    aidunite_update_match_request_status_meta($request_id, 'pending');
    update_post_meta($request_id, 'last_proposed_by_team_id', $actor_team_id);
    update_post_meta($request_id, 'last_proposed_at', current_time('mysql'));
    update_post_meta($request_id, 'last_proposal_source', 'proposal_accept');

    aidunite_touch_match_request_post_modified((int) $request_id);

    if (function_exists('send_match_request_reconfirm_accepted_notification')) {
        send_match_request_reconfirm_accepted_notification($request_id, $actor_team_id);
    }

    wp_send_json_success([
        'message' => '提案を承諾しました。募集側が承認可能な状態になりました。',
        'code' => 'proposal_accepted',
        'request_id' => $request_id,
    ]);
}

/**
 * マッチ申請のステータスを更新
 */
add_action('wp_ajax_au_update_match_request_status', 'handle_update_match_request_status');

function handle_update_match_request_status() {
    try {
    // 統一認証・権限チェック
    $auth_result = AidUniteAuthMiddleware::require_team_membership();
    if (!$auth_result->is_valid()) {
        wp_send_json_error([
            'message' => (string) ($auth_result->error ?: '認証が必要です'),
            'code'    => 'authentication_required',
        ], 403);
        return;
    }

    // CSRF対策
    $nonce_result = AidUniteAuthMiddleware::verify_nonce('security', 'au_match_nonce');
    if (is_wp_error($nonce_result)) {
        wp_send_json_error([
            'message' => (string) $nonce_result->get_error_message(),
            'code'    => 'csrf_verification_failed',
        ], 403);
        return;
    }

    $request_id = AidUniteAuthMiddleware::sanitize($_POST['request_id'] ?? $_POST['index'] ?? 0, 'int');
    $status_raw = AidUniteAuthMiddleware::sanitize($_POST['status'] ?? '', 'text');
    // 旧フロント（日本語ラベル）・match-board-button-control.js との互換
    $status_map = [
        'accepted' => 'accepted',
        'rejected' => 'rejected',
        'canceled' => 'canceled',
        '承認待ち' => 'accepted',
        '拒否された' => 'rejected',
        'キャンセル' => 'canceled',
    ];
    $status = isset($status_map[$status_raw]) ? $status_map[$status_raw] : '';

    if (!$request_id || !in_array($status, ['accepted', 'rejected', 'canceled'], true)) {
        wp_send_json_error('無効なパラメータです');
        return;
    }

    // 申請の存在確認
    $request = get_post($request_id);
    if (!$request || $request->post_type !== 'match_request') {
        wp_send_json_error('申請が見つかりません');
        return;
    }

    // 権限チェック（申請者または申請先チームの代表者）
    $from_team_id = get_post_meta($request_id, 'from_team_id', true);
    $to_schedule_id = get_post_meta($request_id, 'to_schedule_id', true);
    $to_sched_int = (int) $to_schedule_id;
    $to_team_id = 0;
    if ($to_sched_int > 0 && function_exists('aidunite_resolve_schedule_owner_team_id')) {
        $to_team_id = (int) aidunite_resolve_schedule_owner_team_id($to_sched_int);
    }
    if ($to_team_id <= 0 && $to_sched_int > 0) {
        $to_team_id = (int) get_post_meta($to_sched_int, 'team_id', true);
    }
    if ($to_team_id <= 0) {
        $to_team_id = (int) get_post_meta($request_id, 'to_team_id', true);
    }
    if ($to_team_id <= 0) {
        $to_team_id = (int) get_post_meta($request_id, 'other_team_id', true);
    }
    $current_user_id = get_current_user_id();
    $current_team_id = function_exists('aidunite_resolve_user_team_id_for_schedule_ops')
        ? (int) aidunite_resolve_user_team_id_for_schedule_ops($current_user_id)
        : (int) get_user_meta($current_user_id, 'team_id', true);
    $prev_status_meta = (string) get_post_meta($request_id, 'status', true);
    $prev_status_norm = function_exists('aidunite_normalize_match_request_status')
        ? (string) aidunite_normalize_match_request_status($prev_status_meta, isset($request->post_status) ? (string) $request->post_status : '')
        : strtolower($prev_status_meta);
    if (function_exists('aidunite_match_flow_debug_log')) {
        aidunite_match_flow_debug_log('ajax_update_status_entry', [
            'request_id'      => (int) $request_id,
            'target_status'   => $status,
            'prev_status'     => $prev_status_meta,
            'prev_status_norm'=> $prev_status_norm,
            'from_team_id'    => $from_team_id,
            'to_team_id'      => $to_team_id,
            'actor_team_id'   => (int) $current_team_id,
            'to_schedule_id'  => (int) $to_schedule_id,
        ]);
    }

    // 冪等化: 既に canceled のMRを再キャンセルしても、枠ロールバックを再実行しない。
    if ($status === 'canceled' && $prev_status_norm === 'canceled') {
        wp_send_json_success([
            'message'      => '既にキャンセル済みです',
            'status'       => 'canceled',
            'chat_room_id' => 0,
            'redirect_url' => '',
        ]);
        return;
    }

    // キャンセルの場合は申請者・申請先両方、承認・拒否の場合は申請先チームのみ
    if ($status === 'canceled') {
        if (!in_array($current_team_id, [$from_team_id, $to_team_id])) {
            wp_send_json_error('権限がありません');
            return;
        }
    } else {
    if ($current_team_id != $to_team_id) {
        wp_send_json_error('権限がありません');
        return;
        }
    }

    $skip_rollback_established = false;
    $was_established_cancel = false;
    $match_game_for_cancel = function_exists('aidunite_resolve_match_game_id_for_match_request')
        ? (int) aidunite_resolve_match_game_id_for_match_request($request_id)
        : (int) get_post_meta($request_id, 'to_schedule_id', true);

    $cancel_scope = ($status === 'canceled' && function_exists('aidunite_parse_cancel_scope_from_request'))
        ? aidunite_parse_cancel_scope_from_request()
        : 'pair';

    $is_anchor_host_actor = (
        $match_game_for_cancel > 0
        && $match_game_for_cancel !== 9999
        && function_exists('aidunite_actor_is_anchor_host_for_match_request')
        && aidunite_actor_is_anchor_host_for_match_request($request_id, (int) $current_team_id)
    );

    if ($status === 'canceled') {
        // 会場側（anchor）：cancel_scope=dissolve のときのみゲーム全体リセット（§17.5）
        if (
            $is_anchor_host_actor
            && $cancel_scope === 'dissolve'
            && function_exists('aidunite_host_dissolve_game_match_requests')
        ) {
            if (aidunite_host_dissolve_game_match_requests($request_id, (int) $current_team_id)) {
                if (function_exists('aidunite_match_flow_debug_log')) {
                    aidunite_match_flow_debug_log('ajax_status_host_dissolve_response', [
                        'request_id'    => (int) $request_id,
                        'cancel_scope'  => $cancel_scope,
                    ]);
                }
                wp_send_json_success([
                    'message' => 'ゲームを解散し、関連するマッチ申請をキャンセルしました。',
                    'status' => 'canceled',
                    'chat_room_id' => 0,
                    'redirect_url' => '',
                ]);
                return;
            }
        }

        $was_established_cancel = (get_post_meta($request_id, 'established_at', true) !== '');
        if ($was_established_cancel && $match_game_for_cancel > 0 && $cancel_scope !== 'dissolve') {
            $other_established_remain = function_exists('aidunite_count_game_established_match_requests')
                ? (int) aidunite_count_game_established_match_requests($match_game_for_cancel, $request_id)
                : 0;
            if ($other_established_remain > 0) {
                // 参加側自己キャンセル、または主催による1ペアのみ除外（§17.6）
                if ((int) $current_team_id === (int) $from_team_id || $is_anchor_host_actor) {
                    $skip_rollback_established = true;
                }
            }
        }
    }

    // 承認時：整合チェック（仕様4.6・9.3.3）— 最新の枠・成立件数を再検証（REST と共通）
    if ($status === 'accepted') {
        if (function_exists('aidunite_match_request_guard_before_recipient_accept')) {
            $guard = aidunite_match_request_guard_before_recipient_accept($request_id);
            if ($guard !== null) {
                wp_send_json_error([
                    'message' => $guard['message'],
                    'code'    => $guard['code'],
                ], (int) $guard['http_status']);
                return;
            }
        }
    }

    // ステータス更新（persist: accepted → established + *_at）
    aidunite_update_match_request_status_meta(
        $request_id,
        $status,
        isset($request->post_status) ? (string) $request->post_status : ''
    );

    aidunite_touch_match_request_post_modified((int) $request_id);

    // キャンセル時：実行者を記録（受信側の「相手キャンセル」表示用）
    if ($status === 'canceled') {
        update_post_meta($request_id, 'canceled_by_team_id', $current_team_id);
    }

    if (function_exists('aidunite_match_flow_debug_log')) {
        aidunite_match_flow_debug_log('ajax_status_meta_updated', [
            'request_id'                 => (int) $request_id,
            'new_status'                 => $status,
            'skip_rollback_established' => $skip_rollback_established,
        ]);
    }

    // 承認時にチャット導線を返すための返却データ
    $chat_room_id = 0;
    $redirect_url = '';

    // 通知送信と掲示板ステータス更新
    if ($status === 'accepted') {
        // スケジュールベースのチャットルームを作成または拡張
        if (function_exists('aidunite_create_or_extend_match_chat')) {
            $my_schedule_id = get_post_meta($request_id, 'my_schedule_id', true);
            $to_schedule_id = get_post_meta($request_id, 'to_schedule_id', true);

            $target_schedule_id = function_exists('aidunite_resolve_match_game_id_for_match_request')
                ? (int) aidunite_resolve_match_game_id_for_match_request((int) $request_id)
                : 0;
            if ($target_schedule_id <= 0) {
                $target_schedule_id = $to_schedule_id ?: $my_schedule_id;
            }

            // スケジュールから日付を取得
            $match_date = null;
            if ($target_schedule_id) {
                $match_date = get_post_meta($target_schedule_id, 'schedule_date', true);
            }
            if (!$match_date && $my_schedule_id) {
                $match_date = get_post_meta($my_schedule_id, 'schedule_date', true);
            }

            // スケジュールベースのチャットルームを作成または拡張
            $chat_room_id = aidunite_create_or_extend_match_chat(
                $request_id,
                $from_team_id,
                $to_team_id,
                $target_schedule_id,
                $match_date
            );

            if (!is_wp_error($chat_room_id)) {
                $chat_room_id = (int) $chat_room_id;
                if (!empty($target_schedule_id)) {
                    update_post_meta((int) $target_schedule_id, 'active_match_chat_room_id', $chat_room_id);
                }
                $redirect_url = home_url('/chat?room_id=' . $chat_room_id);
                // AidUniteErrorHandlerが存在する場合は使用、なければerror_log
                if (class_exists('AidUniteErrorHandler')) {
                    AidUniteErrorHandler::info('マッチチャットルームを作成/拡張', [
                        'room_id' => $chat_room_id,
                        'schedule_id' => $target_schedule_id,
                        'match_request_id' => $request_id
                    ]);
                } else {
                    error_log("✅ マッチチャットルームを作成/拡張しました: Room ID = " . $chat_room_id);
                }
            } else {
                // AidUniteErrorHandlerが存在する場合は使用、なければerror_log
                if (class_exists('AidUniteErrorHandler')) {
                    AidUniteErrorHandler::error('マッチチャットルームの作成/拡張に失敗', [
                        'match_request_id' => $request_id,
                        'schedule_id' => $target_schedule_id,
                        'error' => $chat_room_id->get_error_message()
                    ]);
                } else {
                    error_log("❌ マッチチャットルームの作成/拡張に失敗: " . $chat_room_id->get_error_message());
                }
            }
        } elseif (function_exists('aidunite_create_match_chat')) {
            // 後方互換性のため、既存の関数もサポート
            $my_schedule_id = get_post_meta($request_id, 'my_schedule_id', true);
            $to_schedule_id = get_post_meta($request_id, 'to_schedule_id', true);

            // スケジュールから日付を取得
            $match_date = null;
            if ($to_schedule_id) {
                $match_date = get_post_meta($to_schedule_id, 'schedule_date', true);
            }
            if (!$match_date && $my_schedule_id) {
                $match_date = get_post_meta($my_schedule_id, 'schedule_date', true);
            }

            // スケジュールIDを決定（to_schedule_idを優先）
            $target_schedule_id = $to_schedule_id ?: $my_schedule_id;

            // マッチチャットルームを作成
            $chat_room_id = aidunite_create_match_chat($request_id, $from_team_id, $to_team_id, $match_date, $target_schedule_id);
            if (!is_wp_error($chat_room_id)) {
                $chat_room_id = (int) $chat_room_id;
                update_post_meta((int) $request_id, 'chat_room_id', $chat_room_id);
                if (!empty($target_schedule_id)) {
                    update_post_meta((int) $target_schedule_id, 'active_match_chat_room_id', $chat_room_id);
                }
                $redirect_url = home_url('/chat?room_id=' . $chat_room_id);
                error_log("✅ マッチチャットルームを作成しました: Room ID = " . $chat_room_id);
            } else {
                error_log("❌ マッチチャットルームの作成に失敗: " . $chat_room_id->get_error_message());
            }
        }

        // 承認済み → 試合確定へ遷移（共通サービスに集約）
        if (function_exists('aidunite_apply_established_to_schedules')) {
            aidunite_apply_established_to_schedules($request_id);
        }
        if (function_exists('aidunite_after_match_established')) {
            aidunite_after_match_established($request_id);
        }
        $forked_new_chat = function_exists('aidunite_match_request_should_fork_new_chat_room')
            && aidunite_match_request_should_fork_new_chat_room((int) $request_id);
        if (!$forked_new_chat && function_exists('aidunite_get_game_chat_room_for_match_request')) {
            $canonical_room = aidunite_get_game_chat_room_for_match_request((int) $request_id);
            if ($canonical_room && !empty($canonical_room->id) && (string) ($canonical_room->status ?? '') === 'active') {
                $chat_room_id = (int) $canonical_room->id;
                $redirect_url = home_url('/chat?room_id=' . $chat_room_id);
            }
        } elseif ($chat_room_id > 0) {
            $redirect_url = home_url('/chat?room_id=' . $chat_room_id);
        }
    } elseif ($status === 'rejected') {
        send_match_rejection_notification($request_id);
        $recruit_reject = (int) get_post_meta($request_id, 'to_schedule_id', true);
        if ($recruit_reject > 0) {
            if (function_exists('aidunite_rebuild_recruit_schedule_participants_from_game_match_requests')) {
                aidunite_rebuild_recruit_schedule_participants_from_game_match_requests($recruit_reject);
            }
            if (function_exists('aidunite_sync_match_board_status_from_game')) {
                aidunite_sync_match_board_status_from_game($recruit_reject);
            }
        }
    } elseif ($status === 'canceled') {
        // 成立後キャンセル時のみロールバック対象（非成立キャンセルはロールバックしない）
        if ($was_established_cancel && !$skip_rollback_established && function_exists('aidunite_rollback_established_from_schedules')) {
            aidunite_rollback_established_from_schedules($request_id);
        } elseif ($was_established_cancel && $skip_rollback_established && function_exists('aidunite_restore_established_slot_only')) {
            // 他 established が残るケースは full rollback せず、当該申請分の枠だけ戻す。
            aidunite_restore_established_slot_only($request_id);
        }
        // 試合チャット：同一募集に他 established が残る場合は共有ルームを閉じない（sync で参加者同期）
        if (function_exists('aidunite_handle_chat_on_match_request_canceled')) {
            aidunite_handle_chat_on_match_request_canceled($request_id);
        } elseif (!$skip_rollback_established && function_exists('aidunite_mark_match_chat_completed')) {
            aidunite_mark_match_chat_completed($request_id, 'canceled');
        }
        $game_cancel = function_exists('aidunite_resolve_match_game_id_for_match_request')
            ? (int) aidunite_resolve_match_game_id_for_match_request($request_id)
            : (int) get_post_meta($request_id, 'to_schedule_id', true);
        if ($game_cancel > 0) {
            if (function_exists('aidunite_rebuild_recruit_schedule_participants_from_game_match_requests')) {
                // チャット同期は handle_chat_on_match_request_canceled 内で1回のみ（system 重複防止）
                aidunite_rebuild_recruit_schedule_participants_from_game_match_requests($game_cancel, ['sync_chat' => false]);
            }
            if (function_exists('aidunite_sync_match_board_status_from_game')) {
                aidunite_sync_match_board_status_from_game($game_cancel);
            }
        }
        if (function_exists('aidunite_notify_match_request_canceled')) {
            aidunite_notify_match_request_canceled($request_id, (int) $current_team_id);
        } elseif (function_exists('send_match_cancellation_notification')) {
            send_match_cancellation_notification($request_id, (int) $current_team_id);
        }
    }

    if (function_exists('aidunite_match_flow_debug_log')) {
        aidunite_match_flow_debug_log('ajax_update_status_done', [
            'request_id'     => (int) $request_id,
            'final_status'   => $status,
            'chat_room_id'   => (int) $chat_room_id,
            'has_redirect'   => $redirect_url !== '',
        ]);
    }

    $success_payload = [
        'message'      => 'ステータスを更新しました',
        'status'       => $status,
        'chat_room_id' => $chat_room_id,
        'redirect_url' => $redirect_url,
    ];
    if ($status === 'accepted' && function_exists('aidunite_onboarding_bot_extend_accept_response')) {
        $success_payload = aidunite_onboarding_bot_extend_accept_response((int) $request_id, $success_payload);
    }

    wp_send_json_success($success_payload);
    } catch (Throwable $e) {
        if (function_exists('aidunite_match_flow_debug_log')) {
            aidunite_match_flow_debug_log('ajax_update_status_exception', [
                'message' => (string) $e->getMessage(),
                'file'    => (string) $e->getFile(),
                'line'    => (int) $e->getLine(),
            ]);
        }
        error_log('[au_update_match_request_status] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
        wp_send_json_error([
            'message' => 'キャンセル処理中に例外が発生しました: ' . $e->getMessage(),
            'code'    => 'cancel_exception',
        ], 500);
    }
}

/**
 * チームの試合数をカウント
 */
function increment_team_match_count($team_id) {
    if (!$team_id) return;

    $current_count = get_post_meta($team_id, 'match_count', true) ?: 0;
    update_post_meta($team_id, 'match_count', $current_count + 1);
}

/**
 * マッチ承認通知を送信（match-notifications.phpに統合済み）
 */

/**
 * マッチ拒否通知を送信（match-notifications.phpに統合済み）
 */

/**
 * マッチキャンセル通知を送信（match-notifications.phpに統合済み）
 */

/**
 * チーム代表者を取得（統一版）
 */
function get_team_leaders($team_id) {
    $team_id = (int) $team_id;
    if ($team_id <= 0) {
        return [];
    }

    $leaders = [];

    // 1) 投稿メタ team_leader_id / post_author（マルチチーム・管理承認の正本）
    if (function_exists('aidunite_team_resolve_leader_user_id')) {
        $resolved = aidunite_team_resolve_leader_user_id($team_id);
        if ($resolved > 0) {
            $leaders[] = $resolved;
        }
    }

    // 2) affiliated ユーザーから team_leader / administrator ロール
    if (function_exists('aidunite_get_team_affiliated_user_ids')) {
        foreach (aidunite_get_team_affiliated_user_ids($team_id) as $member_id) {
            $role = (string) get_user_meta($member_id, 'aidunite_role', true);
            if (in_array($role, ['team_leader', 'administrator'], true)) {
                $leaders[] = (int) $member_id;
            }
        }
    } elseif (function_exists('aidunite_get_team_members')) {
        foreach (aidunite_get_team_members($team_id) as $member) {
            $member_id = is_object($member) ? (int) $member->ID : (int) $member;
            $role = (string) get_user_meta($member_id, 'aidunite_role', true);
            if (in_array($role, ['team_leader', 'administrator'], true)) {
                $leaders[] = $member_id;
            }
        }
    }

    // 3) 後方互換: team_members 投稿メタ
    if ($leaders === []) {
        $team_members = get_post_meta($team_id, 'team_members', true);
        if (is_array($team_members)) {
            foreach ($team_members as $member_id) {
                $member_id = (int) $member_id;
                if ($member_id <= 0) {
                    continue;
                }
                $role = (string) get_user_meta($member_id, 'aidunite_role', true);
                if (in_array($role, ['team_leader', 'administrator'], true)) {
                    $leaders[] = $member_id;
                }
            }
        }
    }

    return array_values(array_unique(array_map('intval', $leaders)));
}

/**
 * 掲示板ステータス管理関数群
 */

/**
 * マッチ申請時：掲示板ステータスを募集ゲーム内 MR 集合から同期
 */
function update_board_status_on_apply($target_schedule_id) {
    $sid = (int) $target_schedule_id;
    if ($sid > 0 && function_exists('aidunite_sync_match_board_status_from_game')) {
        aidunite_sync_match_board_status_from_game($sid);
        error_log('✅ 申請時：掲示板をゲーム MR 集合から同期 schedule_id=' . $sid);
    }
}

/**
 * マッチ承認時：掲示板ステータスを募集ゲーム内 MR 集合から同期
 */
function update_board_status_on_approved($target_schedule_id) {
    $sid = (int) $target_schedule_id;
    if ($sid > 0 && function_exists('aidunite_sync_match_board_status_from_game')) {
        aidunite_sync_match_board_status_from_game($sid);
        error_log('✅ 承認時：掲示板をゲーム MR 集合から同期 schedule_id=' . $sid);
    }
}

/**
 * 成立時：掲示板ステータスを募集ゲーム内 MR 集合から同期
 */
function update_board_status_on_established($target_schedule_id) {
    $sid = (int) $target_schedule_id;
    if ($sid > 0 && function_exists('aidunite_sync_match_board_status_from_game')) {
        aidunite_sync_match_board_status_from_game($sid);
        error_log('✅ 成立時：掲示板をゲーム MR 集合から同期 schedule_id=' . $sid);
    }
}

/**
 * 拒否・キャンセル・再申請時：掲示板ステータスを MR 集合から再計算する
 * （旧：無条件 open ＋単一 match_request_id 連動。複数 MR ゲームでは非整合のため廃止）
 *
 * @param int    $target_schedule_id 募集側 schedule ID
 * @param string $match_status     後方互換用・未使用（旧自動マッチ連携向け）
 */
function reset_board_status_to_open($target_schedule_id, $match_status = '') {
    $sid = (int) $target_schedule_id;
    if ($sid > 0 && function_exists('aidunite_sync_match_board_status_from_game')) {
        aidunite_sync_match_board_status_from_game($sid);
        error_log('✅ 掲示板ステータスをゲーム内 MR 集合から同期: schedule_id=' . $sid);
    }
}

/**
 * match_request に連動して掲示板ステータスを同期（募集ゲームの MR 集合から再計算）
 */
function sync_board_status_with_request($match_request_id) {
    $to_schedule_id = (int) get_post_meta($match_request_id, 'to_schedule_id', true);
    if ($to_schedule_id > 0 && function_exists('aidunite_sync_match_board_status_from_game')) {
        aidunite_sync_match_board_status_from_game($to_schedule_id);
        error_log("🔁 掲示板同期（ゲーム集合）: match_request={$match_request_id} recruit_schedule_id={$to_schedule_id}");
    }
}

/**
 * 一度限りの移行：既存の match_request の status='accepted' を 'established' に更新
 * 承認と成立を分離した際の後方互換用。init で1回だけ実行する。
 */
function aidunite_migrate_accepted_to_established() {
    if (get_option('aidunite_migrated_accepted_to_established', false)) {
        return;
    }

    $posts = get_posts([
        'post_type'      => 'match_request',
        'post_status'    => 'any',
        'posts_per_page' => -1,
        'meta_query'     => [
            ['key' => 'status', 'value' => 'accepted', 'compare' => '='],
        ],
    ]);

    foreach ($posts as $post) {
        $accepted_at = get_post_meta($post->ID, 'accepted_at', true);
        aidunite_update_match_request_status_meta((int) $post->ID, 'established');
        update_post_meta($post->ID, 'established_at', $accepted_at ?: current_time('mysql'));
        if (function_exists('sync_board_and_request_status')) {
            sync_board_and_request_status($post->ID);
        }
    }

    update_option('aidunite_migrated_accepted_to_established', true);
    if (!empty($posts)) {
        error_log('aidunite_migrate_accepted_to_established: ' . count($posts) . ' 件を established に移行しました');
    }
}
add_action('init', 'aidunite_migrate_accepted_to_established', 20);

/**
 * 一度限りの移行：established 済みなのに intent が recruit/tentative のまま残っている schedule を confirmed へ補正
 * 過去データの表示崩れ（「練習試合（募）」残り）対策。
 */
function aidunite_migrate_established_schedule_intent_to_confirmed() {
    if (get_option('aidunite_migrated_established_schedule_intent_to_confirmed', false)) {
        return;
    }

    $requests = get_posts([
        'post_type'      => 'match_request',
        'post_status'    => 'any',
        'posts_per_page' => -1,
        'meta_query'     => [
            ['key' => 'status', 'value' => 'established', 'compare' => '='],
        ],
        'fields'         => 'ids',
    ]);

    $updated_count = 0;
    foreach ($requests as $request_id) {
        $to_schedule_id = (int) get_post_meta($request_id, 'to_schedule_id', true);
        $my_schedule_id = (int) get_post_meta($request_id, 'my_schedule_id', true);
        if (!$my_schedule_id) {
            $my_schedule_id = (int) get_post_meta($request_id, 'from_schedule_id', true);
        }

        $schedule_ids = array_filter(array_unique([$to_schedule_id, $my_schedule_id]));
        foreach ($schedule_ids as $schedule_id) {
            $current_intent = (string) get_post_meta($schedule_id, 'intent', true);
            if ($current_intent !== 'confirmed') {
                update_post_meta($schedule_id, 'intent', 'confirmed');
                $updated_count++;
            }
        }
    }

    update_option('aidunite_migrated_established_schedule_intent_to_confirmed', true);
    if ($updated_count > 0) {
        error_log('aidunite_migrate_established_schedule_intent_to_confirmed: ' . $updated_count . ' 件の schedule intent を confirmed に補正しました');
    }
}
add_action('init', 'aidunite_migrate_established_schedule_intent_to_confirmed', 21);
