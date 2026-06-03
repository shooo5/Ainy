<?php
/**
 * /team-settings チーム管理ダッシュボード（正規メタ保存・権限文脈）
 *
 * @package AidUnite
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * /team-settings デバッグログを出すか（WP_DEBUG / WP_DEBUG_LOG / フィルター）。
 */
function aidunite_team_settings_debug_enabled() {
    if (
        isset($_GET['ts_debug'])
        && (string) wp_unslash($_GET['ts_debug']) === '1'
        && is_user_logged_in()
    ) {
        return true;
    }
    $forced = apply_filters('aidunite_team_settings_debug', null);
    if ($forced !== null) {
        return (bool) $forced;
    }
    return (defined('WP_DEBUG') && WP_DEBUG) || (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG);
}

/**
 * @param string               $event
 * @param array<string, mixed> $context
 */
function aidunite_team_settings_debug_log($event, array $context = []) {
    if (!aidunite_team_settings_debug_enabled()) {
        return;
    }
    $context['event'] = $event;
    $context['ts'] = gmdate('c');
    $json = wp_json_encode($context, JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        $json = '{"event":"encode_failed"}';
    }
    error_log('[aidunite_team_settings] ' . $json);
}

/**
 * 権限デバッグ用スナップショット（error_log 用。個人情報はメール等をマスクしないが本番ではフィルター off 推奨）。
 *
 * @param int $user_id
 * @return array<string, mixed>
 */
function aidunite_team_settings_collect_debug_snapshot($user_id) {
    $user_id = (int) $user_id;
    $legacy = (int) get_user_meta($user_id, 'team_id', true);
    $pending = (int) get_user_meta($user_id, 'pending_team_id', true);
    $primary = (int) get_user_meta($user_id, 'primary_team_id', true);
    $current_meta = (int) get_user_meta($user_id, AIDUNITE_USER_META_CURRENT_OPERATING_TEAM_ID, true);
    $managed_raw = get_user_meta($user_id, AIDUNITE_USER_META_MANAGED_TEAM_IDS, true);
    $managed = function_exists('aidunite_get_managed_team_ids') ? aidunite_get_managed_team_ids($user_id) : [];
    $resolved = aidunite_team_settings_resolve_operating_team_id($user_id);
    $role = function_exists('aidunite_get_user_role') ? aidunite_get_user_role($user_id) : '';

    $team_debug = [];
    $seen = array_unique(array_filter(array_merge(
        [$resolved, $legacy, $pending, $primary, $current_meta],
        $managed
    )));
    foreach ($seen as $tid) {
        $tid = (int) $tid;
        if ($tid <= 0) {
            continue;
        }
        $post = get_post($tid);
        $team_debug[$tid] = [
            'post_status' => $post ? $post->post_status : null,
            'post_type' => $post ? $post->post_type : null,
            'post_author' => $post ? (int) $post->post_author : null,
            'team_leader_id' => (int) get_post_meta($tid, 'team_leader_id', true),
            'team_status' => (string) get_post_meta($tid, 'team_status', true),
            'is_leader_meta' => aidunite_team_settings_user_is_leader_of_team($user_id, $tid),
            'has_leader_access' => aidunite_team_settings_user_has_team_leader_access($user_id, $tid),
            'can_access' => aidunite_team_settings_user_can_access_team($user_id, $tid),
            'publish_ctx' => function_exists('aidunite_team_cpt_is_publish_for_operation_context')
                ? aidunite_team_cpt_is_publish_for_operation_context($tid)
                : null,
        ];
    }

    return [
        'user_id' => $user_id,
        'aidunite_role_meta' => (string) get_user_meta($user_id, 'aidunite_role', true),
        'aidunite_get_user_role' => $role,
        'user_type' => (string) get_user_meta($user_id, 'user_type', true),
        'team_id_meta' => $legacy,
        'pending_team_id' => $pending,
        'primary_team_id' => $primary,
        'current_operating_team_id' => $current_meta,
        'managed_team_ids_raw' => $managed_raw,
        'managed_team_ids_filtered' => $managed,
        'aidunite_get_current_team_id' => function_exists('aidunite_get_current_team_id')
            ? (int) aidunite_get_current_team_id($user_id)
            : null,
        'resolved_operating_team_id' => $resolved,
        'teams' => $team_debug,
        'request_uri' => isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '',
    ];
}

/**
 * 認証・文脈エラー時のログ＋マイページへリダイレクト（?ts_err= 付与）。
 *
 * @param string                    $stage auth|context
 * @param AidUniteAuthResult|WP_Error $error
 * @param int                       $user_id
 */
function aidunite_team_settings_redirect_denied($stage, $error, $user_id = 0) {
    $code = 'unknown';
    $message = '';

    if ($error instanceof WP_Error) {
        $code = $error->get_error_code();
        $message = $error->get_error_message();
    } elseif ($error instanceof AidUniteAuthResult) {
        $code = $error->error_code !== '' ? $error->error_code : 'auth_invalid';
        $message = (string) $error->error;
    }

    $log_ctx = [
        'stage' => $stage,
        'code' => $code,
        'message' => $message,
        'user_id' => (int) $user_id,
    ];
    if ($user_id > 0) {
        $log_ctx['snapshot'] = aidunite_team_settings_collect_debug_snapshot($user_id);
    }
    aidunite_team_settings_debug_log('access_denied', $log_ctx);

    $url = add_query_arg(
        [
            'ts_err' => sanitize_key($code),
        ],
        home_url('/mypage')
    );
    wp_safe_redirect($url);
    exit;
}

/**
 * /team-settings 用の操作中 team ID（代表者がリーダーのチームを優先）。
 * AuthMiddleware の legacy フォールバックと整合させ、current=0 でも代表チームへ到達できるようにする。
 *
 * @param int $user_id
 * @return int
 */
function aidunite_team_settings_resolve_operating_team_id($user_id) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return 0;
    }

    $candidates = [];
    $current = function_exists('aidunite_get_current_team_id')
        ? (int) aidunite_get_current_team_id($user_id)
        : 0;
    if ($current > 0) {
        $candidates[] = $current;
    }

    $legacy = (int) get_user_meta($user_id, 'team_id', true);
    if ($legacy > 0) {
        $candidates[] = $legacy;
    }

    $pending = (int) get_user_meta($user_id, 'pending_team_id', true);
    if ($pending > 0) {
        $candidates[] = $pending;
    }

    $primary = (int) get_user_meta($user_id, 'primary_team_id', true);
    if ($primary > 0) {
        $candidates[] = $primary;
    }

    $managed = function_exists('aidunite_get_managed_team_ids') ? aidunite_get_managed_team_ids($user_id) : [];
    foreach ($managed as $mid) {
        $mid = (int) $mid;
        if ($mid > 0) {
            $candidates[] = $mid;
        }
    }

    $candidates = array_values(array_unique(array_filter($candidates)));

    foreach ($candidates as $candidate) {
        if (aidunite_team_settings_user_has_team_leader_access($user_id, (int) $candidate)) {
            return (int) $candidate;
        }
    }

    if ($current > 0) {
        return $current;
    }
    if ($legacy > 0) {
        return $legacy;
    }
    if (!empty($managed)) {
        return (int) $managed[0];
    }

    return 0;
}

/**
 * チーム設定画面へのアクセス可否（managed・レガシー team_id・代表者）。
 *
 * @param int $user_id
 * @param int $team_id
 */
function aidunite_team_settings_user_can_access_team($user_id, $team_id) {
    $user_id = (int) $user_id;
    $team_id = (int) $team_id;
    if ($team_id <= 0) {
        return false;
    }
    if (current_user_can('administrator')) {
        return true;
    }
    if (function_exists('aidunite_user_has_managed_team_access')
        && aidunite_user_has_managed_team_access($user_id, $team_id)) {
        return true;
    }
    $legacy = (int) get_user_meta($user_id, 'team_id', true);
    $pending = (int) get_user_meta($user_id, 'pending_team_id', true);
    if ($legacy === $team_id && aidunite_team_settings_user_has_team_leader_access($user_id, $team_id)) {
        return true;
    }
    if ($pending === $team_id && aidunite_team_settings_user_has_team_leader_access($user_id, $team_id)) {
        return true;
    }
    return false;
}

/**
 * 代表者としての編集権（team_leader_id / post_author / aidunite_role=team_leader＋所属 team）。
 *
 * @param int $user_id
 * @param int $team_id
 */
function aidunite_team_settings_user_has_team_leader_access($user_id, $team_id) {
    if (aidunite_team_settings_user_is_leader_of_team($user_id, $team_id)) {
        return true;
    }
    $role = function_exists('aidunite_get_user_role') ? aidunite_get_user_role($user_id) : '';
    if ($role !== 'team_leader') {
        return false;
    }
    $legacy = (int) get_user_meta($user_id, 'team_id', true);
    if ($legacy === $team_id && $team_id > 0) {
        return true;
    }
    $pending = (int) get_user_meta($user_id, 'pending_team_id', true);
    if ($pending === $team_id && $team_id > 0) {
        return true;
    }
    $managed = function_exists('aidunite_get_managed_team_ids') ? aidunite_get_managed_team_ids($user_id) : [];
    return in_array($team_id, $managed, true);
}

/**
 * 編集対象チームの文脈を解決する（操作中 team・managed・publish・team_status・代表者）。
 *
 * @param int $user_id
 * @return array{team_id:int,post:\WP_Post}|\WP_Error
 */
/**
 * リクエストで指定された編集対象チーム ID（管理者の ?team_id= / POST team_id）。
 */
function aidunite_team_settings_resolve_requested_team_id() {
    if (!isset($_REQUEST['team_id'])) {
        return 0;
    }
    $id = (int) $_REQUEST['team_id'];
    return $id > 0 ? $id : 0;
}

function aidunite_team_settings_resolve_context($user_id) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return new WP_Error('auth', 'ログインが必要です。');
    }

    $requested_team_id = aidunite_team_settings_resolve_requested_team_id();
    $is_admin = current_user_can('administrator');
    if ($is_admin && $requested_team_id > 0) {
        $team_id = $requested_team_id;
    } else {
        // ヘッダー切替と同じ current_operating_team_id を優先（セレクトと表示のズレ防止）
        $team_id = function_exists('aidunite_get_current_team_id')
            ? (int) aidunite_get_current_team_id($user_id)
            : 0;
        if (
            $team_id <= 0
            || !aidunite_team_settings_user_has_team_leader_access($user_id, $team_id)
        ) {
            $team_id = aidunite_team_settings_resolve_operating_team_id($user_id);
        }
    }

    if ($team_id <= 0) {
        aidunite_team_settings_debug_log('resolve_fail', [
            'code' => 'no_current_team',
            'user_id' => $user_id,
            'snapshot' => aidunite_team_settings_collect_debug_snapshot($user_id),
        ]);
        return new WP_Error('no_current_team', '操作中のチームがありません。マイページ等からチームを選択してください。');
    }

    $has_leader_access = aidunite_team_settings_user_has_team_leader_access($user_id, $team_id);

    if (function_exists('aidunite_team_cpt_is_publish_for_operation_context')
        && !aidunite_team_cpt_is_publish_for_operation_context($team_id)
        && !$has_leader_access
        && !current_user_can('administrator')) {
        return new WP_Error('not_publish', 'このチームはまだ公開されていないため、ここからは編集できません。');
    }

    if (!aidunite_team_settings_user_can_access_team($user_id, $team_id)) {
        aidunite_team_settings_debug_log('resolve_fail', [
            'code' => 'not_managed',
            'user_id' => $user_id,
            'team_id' => $team_id,
            'snapshot' => aidunite_team_settings_collect_debug_snapshot($user_id),
        ]);
        return new WP_Error('not_managed', 'このチームを管理する権限がありません。');
    }

    $post = get_post($team_id);
    if (!$post || $post->post_type !== 'team') {
        return new WP_Error('invalid_team', 'チーム情報を読み込めませんでした。');
    }

    $team_status = (string) get_post_meta($team_id, 'team_status', true);
    if ($team_status === 'pending' && !$is_admin) {
        aidunite_team_settings_debug_log('resolve_fail', [
            'code' => 'team_inactive_pending',
            'user_id' => $user_id,
            'team_id' => $team_id,
            'team_status' => $team_status,
        ]);
        return new WP_Error('team_inactive', 'チームが有効化されていません。承認完了後に再度お試しください。');
    }
    if ($team_status !== '' && $team_status !== 'active' && !current_user_can('administrator')) {
        aidunite_team_settings_debug_log('resolve_fail', [
            'code' => 'team_inactive',
            'user_id' => $user_id,
            'team_id' => $team_id,
            'team_status' => $team_status,
        ]);
        return new WP_Error('team_inactive', 'チーム状態が有効ではないため、編集できません。');
    }

    if (!current_user_can('administrator') && !$has_leader_access) {
        aidunite_team_settings_debug_log('resolve_fail', [
            'code' => 'not_leader',
            'user_id' => $user_id,
            'team_id' => $team_id,
            'snapshot' => aidunite_team_settings_collect_debug_snapshot($user_id),
        ]);
        return new WP_Error('not_leader', 'チーム代表者のみがこの画面で保存できます。');
    }

    aidunite_team_settings_debug_log('resolve_ok', [
        'user_id' => $user_id,
        'team_id' => $team_id,
        'post_status' => $post->post_status,
        'team_status' => $team_status,
    ]);

    if (
        function_exists('aidunite_set_current_operating_team_id')
        && function_exists('aidunite_user_has_managed_team_access')
        && aidunite_user_has_managed_team_access($user_id, $team_id)
    ) {
        $cur = (int) get_user_meta($user_id, AIDUNITE_USER_META_CURRENT_OPERATING_TEAM_ID, true);
        if ($cur !== $team_id) {
            aidunite_set_current_operating_team_id($user_id, $team_id);
        }
    }

    return [
        'team_id' => $team_id,
        'post' => $post,
    ];
}

/**
 * @param int $user_id
 * @param int $team_id
 */
function aidunite_team_settings_user_is_leader_of_team($user_id, $team_id) {
    $user_id = (int) $user_id;
    $team_id = (int) $team_id;
    if ($user_id <= 0 || $team_id <= 0) {
        return false;
    }
    if (function_exists('aidunite_team_resolve_leader_user_id')) {
        $resolved = aidunite_team_resolve_leader_user_id($team_id);
        return $resolved > 0 && $resolved === $user_id;
    }
    $leader = (int) get_post_meta($team_id, 'team_leader_id', true);
    if ($leader > 0) {
        return $leader === $user_id;
    }
    $post = get_post($team_id);
    return $post && (int) $post->post_author === $user_id;
}

/**
 * POST から正規メタのみ保存（team_location / team_contact は書かず削除）。
 *
 * @param int $team_id
 * @param int $user_id
 * @return true|\WP_Error
 */
function aidunite_team_settings_save_from_post($team_id, $user_id) {
    $team_id = (int) $team_id;
    $user_id = (int) $user_id;
    if ($team_id <= 0 || $user_id <= 0) {
        return new WP_Error('invalid', '保存に必要な情報が不足しています。');
    }

    $ctx = aidunite_team_settings_resolve_context($user_id);
    if (is_wp_error($ctx) || (int) $ctx['team_id'] !== $team_id) {
        return is_wp_error($ctx) ? $ctx : new WP_Error('context', 'チーム文脈が一致しません。');
    }

    $team_name = sanitize_text_field(wp_unslash($_POST['team_name'] ?? ''));
    if ($team_name === '') {
        return new WP_Error('required', 'チーム名は必須です。');
    }

    $team_name_kana = sanitize_text_field(wp_unslash($_POST['team_name_kana'] ?? ''));

    $team_description = sanitize_textarea_field(wp_unslash($_POST['team_description'] ?? ''));
    $sport_type = sanitize_text_field(wp_unslash($_POST['sport_type'] ?? ''));
    $team_category = sanitize_text_field(wp_unslash($_POST['team_category'] ?? ''));
    $team_type = sanitize_text_field(wp_unslash($_POST['team_type'] ?? ''));
    $team_place = sanitize_text_field(wp_unslash($_POST['team_place'] ?? ''));
    $team_logo = esc_url_raw(wp_unslash($_POST['team_logo'] ?? ''));
    $registrant_name = sanitize_text_field(wp_unslash($_POST['registrant_name'] ?? ''));
    $contact_mail = function_exists('aidunite_normalize_email')
        ? aidunite_normalize_email(wp_unslash($_POST['contact_mail'] ?? ''))
        : sanitize_email(wp_unslash($_POST['contact_mail'] ?? ''));
    $contact_phone = sanitize_text_field(wp_unslash($_POST['contact_phone'] ?? ''));

    $gender_raw = wp_unslash($_POST['team_gender_option'] ?? '');
    $incoming_gender = function_exists('aidunite_normalize_team_gender_option')
        ? aidunite_normalize_team_gender_option((string) $gender_raw)
        : strtolower(trim((string) $gender_raw));

    $existing_gender = function_exists('aidunite_normalize_team_gender_option')
        ? aidunite_normalize_team_gender_option((string) get_post_meta($team_id, 'team_gender_option', true))
        : strtolower(trim((string) get_post_meta($team_id, 'team_gender_option', true)));

    $ban_both = apply_filters('aidunite_mvp_ban_new_team_gender_both', true);

    if ($ban_both) {
        if (in_array($incoming_gender, ['both', 'mixed'], true)) {
            return new WP_Error('gender_both_forbidden', '「男子・女子可」は選択できません。男子または女子を選んでください。');
        }
        if ($existing_gender === 'both' || $existing_gender === 'mixed') {
            if ($incoming_gender === '' || !in_array($incoming_gender, ['male', 'female'], true)) {
                return new WP_Error(
                    'gender_both_fix_required',
                    '現在のデータが「男子・女子可」です。保存するには、男子または女子のいずれかを必ず選択してください。'
                );
            }
        } elseif (!in_array($incoming_gender, ['male', 'female'], true)) {
            return new WP_Error('gender_required', '性別（男子／女子）を選択してください。');
        }
    } else {
        if ($incoming_gender === '' || $incoming_gender === 'both') {
            $incoming_gender = $existing_gender !== '' ? $existing_gender : 'male';
        }
    }

    $activity_area_post = wp_unslash($_POST['activity_area'] ?? '');
    $activity_area_ward_post = wp_unslash($_POST['activity_area_ward'] ?? '');
    if ($activity_area_ward_post === '' && $activity_area_post !== '') {
        $activity_area_ward_post = $activity_area_post;
    }
    $activity_input = [
        'activity_prefecture' => wp_unslash($_POST['activity_prefecture'] ?? ''),
        'activity_area_type'  => wp_unslash($_POST['activity_area_type'] ?? ''),
        'activity_area_ward'  => $activity_area_ward_post,
        'activity_area_city'  => wp_unslash($_POST['activity_area_city'] ?? ''),
        'activity_area_sync'  => wp_unslash($_POST['activity_area_sync'] ?? ''),
        'activity_area'       => $activity_area_post,
    ];
    if (function_exists('aidunite_team_activity_save_meta')) {
        aidunite_team_activity_save_meta($team_id, $activity_input);
    }

    $post_status = get_post_status($team_id) ?: 'publish';
    $updated = wp_update_post(
        [
            'ID' => $team_id,
            'post_title' => $team_name,
            'post_content' => $team_description,
            'post_status' => $post_status,
        ],
        true
    );
    if (is_wp_error($updated)) {
        return $updated;
    }

    update_post_meta($team_id, 'team_name', $team_name);
    update_post_meta($team_id, 'team_name_kana', $team_name_kana);
    update_post_meta($team_id, 'team_description', $team_description);
    update_post_meta($team_id, 'sport_type', $sport_type);
    update_post_meta($team_id, 'team_category', $team_category);
    aidunite_update_team_type_meta($team_id, $team_type);
    update_post_meta($team_id, 'team_gender_option', $incoming_gender);
    update_post_meta($team_id, 'team_place', $team_place);
    update_post_meta($team_id, 'team_logo', $team_logo);
    if (function_exists('aidunite_save_team_logo_crop_meta')) {
        aidunite_save_team_logo_crop_meta(
            $team_id,
            wp_unslash($_POST['team_logo_offset_x'] ?? 0),
            wp_unslash($_POST['team_logo_offset_y'] ?? 0),
            wp_unslash($_POST['team_logo_zoom'] ?? 100)
        );
    }
    update_post_meta($team_id, 'registrant_name', $registrant_name);
    update_post_meta($team_id, 'contact_mail', $contact_mail);
    update_post_meta($team_id, 'contact_phone', $contact_phone);

    delete_post_meta($team_id, 'team_location');
    delete_post_meta($team_id, 'team_contact');

    return true;
}

/**
 * ダッシュボード用メニュー行（URL・文言・バッジ）
 *
 * @param int $team_id
 * @param int $user_id
 * @return array<int, array{title:string,url:string,icon:string,description:string,badge:string}>
 */
function aidunite_team_settings_dashboard_menu_items($team_id, $user_id) {
    $team_id = (int) $team_id;
    $user_id = (int) $user_id;
    $members = function_exists('aidunite_get_team_member_count') ? (int) aidunite_get_team_member_count($team_id) : 0;
    $pending_att = function_exists('aidunite_get_pending_attendance_count_team')
        ? (int) aidunite_get_pending_attendance_count_team($team_id)
        : 0;
    $matches = function_exists('aidunite_get_total_matches') ? (int) aidunite_get_total_matches($team_id) : 0;
    $unread = function_exists('aidunite_get_notification_count') ? (int) aidunite_get_notification_count($user_id) : 0;

    $att_badge = $pending_att > 0 ? sprintf('未回答 %d件', $pending_att) : '今月の出欠';

    return [
        [
            'title' => 'メンバー一覧',
            'url' => home_url('/team-members'),
            'icon' => 'group',
            'description' => 'チームメンバーの確認・管理を行います',
            'badge' => sprintf('%d名', $members),
        ],
        [
            'title' => '出欠一覧',
            'url' => home_url('/attendance-management'),
            'icon' => 'check_circle',
            'description' => '練習・試合の出欠確認と集計を行います',
            'badge' => $att_badge,
            'attention' => $pending_att > 0,
        ],
        [
            'title' => 'マッチ統計',
            'url' => home_url('/match-analytics'),
            'icon' => 'bar_chart_4_bars',
            'description' => 'マッチ成立率や試合傾向を確認します',
            'badge' => sprintf('累計 %d 試合', $matches),
        ],
        [
            'title' => '通知設定',
            'url' => home_url('/notification-settings'),
            'icon' => 'notification_add',
            'description' => '活動通知やメール配信の設定を行います',
            'badge' => $unread > 0 ? sprintf('未読 %d件', $unread) : '設定',
        ],
        [
            'title' => '公開プロフィール',
            'url' => function_exists('aidunite_get_team_public_profile_url')
                ? aidunite_get_team_public_profile_url($team_id)
                : (get_permalink($team_id) ?: home_url('/')),
            'icon' => 'home',
            'description' => '一般向けの簡易紹介ページ（チーム名・地域・紹介文など）',
            'badge' => '公開',
        ],
        [
            'title' => 'お気に入りチーム',
            'url' => home_url('/mypage-favorite-teams'),
            'icon' => 'star',
            'description' => 'お気に入り登録したチームを管理します',
            'badge' => '一覧',
        ],
        [
            'title' => '保護者招待・追加',
            'url' => home_url('/invite-guardian'),
            'icon' => 'forward_to_inbox',
            'description' => '保護者を招待し、チームに参加してもらいます',
            'badge' => '招待',
        ],
    ];
}

/**
 * 一体型ヒーロー内: 現在のチームカード・切り替え・クイック操作。
 *
 * @param array<string, mixed> $args
 */
function aidunite_render_team_settings_hero_body(array $args) {
    $team_display_name = (string) ($args['team_display_name'] ?? '');
    $team_logo = (string) ($args['team_logo'] ?? '');
    $team_gender_label = (string) ($args['team_gender_label'] ?? '');
    $team_category_display = (string) ($args['team_category_display'] ?? '—');
    $region = (string) ($args['region'] ?? '');
    $public_status_label = (string) ($args['public_status_label'] ?? '');
    $team_status_display = (string) ($args['team_status_display'] ?? '');
    $team_status_raw = (string) ($args['team_status_raw'] ?? 'active');
    $user_id = (int) ($args['user_id'] ?? 0);
    $team_public_profile_url = (string) ($args['team_public_profile_url'] ?? '');
    $meta_parts = [];
    if ($team_gender_label !== '' && $team_gender_label !== '—') {
        $meta_parts[] = $team_gender_label;
    }
    if ($team_category_display !== '' && $team_category_display !== '—') {
        $meta_parts[] = $team_category_display;
    }
    if ($region !== '' && $region !== '—' && $region !== '地域未設定') {
        $meta_parts[] = $region;
    }
    $meta_line = implode(' · ', $meta_parts);
    ?>
    <div class="ainy-webapp-hero-team-card">
        <?php if ($team_logo !== '' && filter_var($team_logo, FILTER_VALIDATE_URL)) : ?>
            <img class="ainy-webapp-hero-team-card__logo" src="<?php echo esc_url($team_logo); ?>" alt="" width="64" height="64" loading="lazy" />
        <?php else : ?>
            <div class="ainy-webapp-hero-team-card__logo ainy-webapp-hero-team-card__logo--placeholder" aria-hidden="true">🏟️</div>
        <?php endif; ?>
        <div class="ainy-webapp-hero-team-card__body">
            <p class="ainy-webapp-hero-team-card__meta">現在のチーム</p>
            <h2 class="ainy-webapp-hero-team-card__name"><?php echo esc_html($team_display_name); ?></h2>
            <?php if ($meta_line !== '') : ?>
                <p class="ainy-webapp-hero-team-card__meta"><?php echo esc_html($meta_line); ?></p>
            <?php endif; ?>
            <div class="ainy-webapp-hero-team-card__badges" role="list" aria-label="チーム状態">
                <?php if ($public_status_label !== '') : ?>
                    <span class="ainy-webapp-hero-team-card__badge" role="listitem"><?php echo esc_html($public_status_label); ?></span>
                <?php endif; ?>
                <?php if ($team_status_raw !== '' && $team_status_raw !== 'active' && $team_status_display !== '') : ?>
                    <span class="ainy-webapp-hero-team-card__badge ainy-webapp-hero-team-card__badge--status" role="listitem"><?php echo esc_html($team_status_display); ?></span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php if (function_exists('aidunite_render_team_settings_summary_actions') && $team_public_profile_url !== '') : ?>
        <div class="ainy-webapp-hero-team-actions">
            <?php aidunite_render_team_settings_summary_actions($user_id, $team_public_profile_url); ?>
        </div>
    <?php endif; ?>
    <?php
}

/**
 * /team-settings: チーム切替はヘッダー「操作中」のみ。複数チーム時の案内文。
 *
 * @param int $user_id
 */
function aidunite_render_team_settings_operating_team_hint($user_id) {
    $user_id = (int) $user_id;
    $switcher_cfg = function_exists('aidunite_get_operating_team_header_switcher_config')
        ? aidunite_get_operating_team_header_switcher_config($user_id)
        : ['enabled' => false];
    if (empty($switcher_cfg['enabled'])) {
        return;
    }
    ?>
    <p class="team-settings-dash__operating-hint" role="note">
      別のチームを編集する場合は、画面上部の<strong>操作中</strong>からチームを選び直してください。
    </p>
    <?php
}

/**
 * /team-settings サマリー下部: プロフィールプレビューとチーム追加。
 *
 * @param int    $user_id
 * @param string $team_public_profile_url
 */
function aidunite_render_team_settings_summary_actions($user_id, $team_public_profile_url) {
    $user_id = (int) $user_id;
    $has_pending_application = function_exists('aidunite_user_has_pending_team_application')
        ? aidunite_user_has_pending_team_application($user_id)
        : ((int) get_user_meta($user_id, 'pending_team_id', true) > 0);
    $registration_url = home_url('/team-registration');
    ?>
    <div class="team-settings-dash__summary-actions" id="team-summary-actions">
      <a
        class="team-settings-dash__btn team-settings-dash__btn--primary"
        href="<?php echo esc_url($team_public_profile_url); ?>"
        target="_blank"
        rel="noopener noreferrer"
      >プロフィール プレビュー</a>
      <span class="team-settings-dash__summary-actions-divider" aria-hidden="true">｜</span>
      <?php if ($has_pending_application) : ?>
        <button
          type="button"
          class="team-settings-dash__btn team-settings-dash__btn--secondary"
          disabled
          title="新規チームの申請を承認待ちです"
        >＋ チームを追加</button>
      <?php else : ?>
        <a
          class="team-settings-dash__btn team-settings-dash__btn--secondary"
          href="<?php echo esc_url($registration_url); ?>"
        >＋ チームを追加</a>
      <?php endif; ?>
    </div>
    <?php if ($has_pending_application) : ?>
      <p class="team-settings-dash__summary-actions-note" role="status">
        新規チームの申請を承認待ちです。承認後に追加できます。
      </p>
    <?php endif; ?>
    <?php
}

/**
 * チーム管理ダッシュボード固定ページの正規 URL（テンプレート page-team-settings.php を優先）。
 *
 * @return string
 */
function aidunite_get_team_settings_page_url() {
    $page = get_page_by_path('team-settings', OBJECT, 'page');
    if ($page instanceof WP_Post && $page->post_status === 'publish') {
        $tpl = function_exists('get_page_template_slug') ? (string) get_page_template_slug($page->ID) : '';
        $tpl_base = $tpl !== '' ? basename(str_replace('\\', '/', $tpl)) : '';
        if ($tpl_base === 'page-team-settings.php') {
            return (string) get_permalink($page->ID);
        }
    }

    $pages = get_posts([
        'post_type' => 'page',
        'post_status' => 'publish',
        'posts_per_page' => 1,
        'meta_key' => '_wp_page_template',
        'meta_value' => 'page-team-settings.php',
    ]);
    if (!empty($pages[0]) && $pages[0] instanceof WP_Post) {
        return (string) get_permalink($pages[0]->ID);
    }

    return home_url('/team-settings');
}

/**
 * 指定チームのチーム設定（編集）画面 URL。管理者は team-management の「編集」から遷移。
 *
 * @param int $team_id
 * @return string
 */
function aidunite_get_team_settings_edit_url($team_id) {
    $team_id = (int) $team_id;
    $base = aidunite_get_team_settings_page_url();
    if ($team_id <= 0) {
        return $base;
    }
    return add_query_arg('team_id', $team_id, $base);
}

/**
 * ヘッダー右端「ダッシュボード」リンク（お知らせ → マイページ → ダッシュボード の3番目）。
 *
 * @param int|null $user_id 省略時はログイン中ユーザー
 * @return array{url:string,label:string,class:string,is_admin:bool}|null 表示不要時は null
 */
function aidunite_get_header_dashboard_link($user_id = null) {
    $user_id = $user_id !== null ? (int) $user_id : (int) get_current_user_id();
    if ($user_id <= 0) {
        return null;
    }

    $is_admin = current_user_can('administrator')
        || (get_user_meta($user_id, 'aidunite_role', true) === 'administrator');

    if ($is_admin) {
        return [
            'url' => home_url('/ainy-dashboard'),
            'label' => 'ダッシュボード',
            'class' => 'ainy-header-icon-link ainy-header-dashboard-link',
            'is_admin' => true,
        ];
    }

    if (!class_exists('AidUniteAuthMiddleware')) {
        return null;
    }

    $tl = AidUniteAuthMiddleware::require_team_leader(null, false);
    if (!$tl->is_valid()) {
        return null;
    }

    return [
        'url' => aidunite_get_team_settings_page_url(),
        'label' => 'チーム管理ダッシュボード',
        'class' => 'ainy-header-icon-link ainy-header-dashboard-link ainy-header-dashboard-link--team',
        'is_admin' => false,
    ];
}

/**
 * チーム管理ダッシュボード画面か（スラッグまたは page-team-settings テンプレート）。
 */
function aidunite_is_team_settings_screen() {
    if (is_page('team-settings')) {
        return true;
    }
    $slug = function_exists('get_page_template_slug') ? (string) get_page_template_slug() : '';
    $base = $slug !== '' ? basename(str_replace('\\', '/', $slug)) : '';

    return $base === 'page-team-settings.php';
}

/**
 * /team-settings 固定ページにテンプレート未割当でも page-team-settings.php を使う。
 *
 * @param string $template
 * @return string
 */
function aidunite_team_settings_force_page_template($template) {
    if (!is_page('team-settings')) {
        return $template;
    }
    $forced = get_stylesheet_directory() . '/page-team-settings.php';
    if (is_readable($forced)) {
        return $forced;
    }

    return $template;
}
add_filter('template_include', 'aidunite_team_settings_force_page_template', 99);
