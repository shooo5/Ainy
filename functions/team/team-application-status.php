<?php
/**
 * チーム作成申請：承認・修正依頼・通知・マイページ状態
 */
if (!defined('ABSPATH')) {
    exit;
}

/** @var string user_meta: 修正依頼中の team 投稿 ID */
if (!defined('AIDUNITE_USER_META_NEEDS_REVISION_TEAM_ID')) {
    define('AIDUNITE_USER_META_NEEDS_REVISION_TEAM_ID', 'needs_revision_team_id');
}

/** @var string team post meta: 管理者からの修正依頼メッセージ */
if (!defined('AIDUNITE_TEAM_META_REVISION_MESSAGE')) {
    define('AIDUNITE_TEAM_META_REVISION_MESSAGE', 'application_revision_message');
}

/** @var string team post meta: 修正依頼日時 */
if (!defined('AIDUNITE_TEAM_META_REVISION_REQUESTED_AT')) {
    define('AIDUNITE_TEAM_META_REVISION_REQUESTED_AT', 'application_revision_requested_at');
}

/**
 * 修正依頼中 team ID を取得
 *
 * @param int $user_id
 * @return int
 */
function aidunite_get_user_needs_revision_team_id($user_id = null) {
    $user_id = $user_id === null ? get_current_user_id() : (int) $user_id;
    if ($user_id <= 0) {
        return 0;
    }

    $team_id = (int) get_user_meta($user_id, AIDUNITE_USER_META_NEEDS_REVISION_TEAM_ID, true);
    if ($team_id <= 0) {
        return 0;
    }

    $post = get_post($team_id);
    if (!$post || $post->post_type !== 'team') {
        delete_user_meta($user_id, AIDUNITE_USER_META_NEEDS_REVISION_TEAM_ID);
        return 0;
    }

    $team_status = (string) get_post_meta($team_id, 'team_status', true);
    if ($team_status !== 'needs_revision') {
        return 0;
    }

    return $team_id;
}

/**
 * 修正依頼状態をクリア
 *
 * @param int $user_id
 * @param int $team_id 0 なら user meta のみ削除
 */
function aidunite_clear_user_needs_revision_state($user_id, $team_id = 0) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return;
    }

    $stored = (int) get_user_meta($user_id, AIDUNITE_USER_META_NEEDS_REVISION_TEAM_ID, true);
    if ($team_id > 0 && $stored > 0 && $stored !== $team_id) {
        return;
    }

    delete_user_meta($user_id, AIDUNITE_USER_META_NEEDS_REVISION_TEAM_ID);
}

/**
 * 修正依頼マイページ用コンテキスト
 *
 * @param int|null $user_id
 * @return array<string, mixed>
 */
function aidunite_mypage_general_needs_revision_context($user_id = null) {
    $user_id = $user_id === null ? get_current_user_id() : (int) $user_id;
    $team_id = aidunite_get_user_needs_revision_team_id($user_id);
    if ($team_id <= 0) {
        return [];
    }

    if (!function_exists('aidunite_get_team_application_review_context')) {
        require_once get_stylesheet_directory() . '/functions/team/team-registration-complete.php';
    }

    $context = aidunite_get_team_application_review_context($team_id);
    if ($context === []) {
        return [];
    }

    $context['revision_message'] = (string) get_post_meta($team_id, AIDUNITE_TEAM_META_REVISION_MESSAGE, true);
    $context['revision_requested_at'] = (string) get_post_meta($team_id, AIDUNITE_TEAM_META_REVISION_REQUESTED_AT, true);

    return $context;
}

/**
 * チーム申請承認通知（CPT + メール）
 *
 * @param int $user_id
 * @param int $team_id
 */
function aidunite_send_team_application_approved_notification($user_id, $team_id) {
    $user_id = (int) $user_id;
    $team_id = (int) $team_id;
    if ($user_id <= 0 || $team_id <= 0 || !function_exists('aidunite_notification_send')) {
        return;
    }

    $team_name = function_exists('aidunite_get_team_name')
        ? (string) aidunite_get_team_name($team_id)
        : (string) get_the_title($team_id);

    if ($team_name === '') {
        $team_name = 'あなたのチーム';
    }

    aidunite_notification_send($user_id, 'team_application_approved', [
        'title'      => 'チーム申請が承認されました',
        'message'    => "チーム「{$team_name}」の申請が承認されました。\n最初の試合募集を公開しましょう。",
        'related_id' => $team_id,
        'link_url'   => function_exists('aidunite_get_activation_recruit_edit_url') ? aidunite_get_activation_recruit_edit_url() : home_url('/mypage/?open_recruit=1'),
        'idempotency_key' => 'team_application_approved:' . $team_id . ':' . $user_id,
    ]);
}

/**
 * チーム申請修正依頼通知（CPT + メール）
 *
 * @param int    $user_id
 * @param int    $team_id
 * @param string $revision_message
 */
function aidunite_send_team_application_revision_notification($user_id, $team_id, $revision_message) {
    $user_id = (int) $user_id;
    $team_id = (int) $team_id;
    $revision_message = sanitize_textarea_field((string) $revision_message);

    if ($user_id <= 0 || $team_id <= 0 || !function_exists('aidunite_notification_send')) {
        return;
    }

    $team_name = function_exists('aidunite_get_team_name')
        ? (string) aidunite_get_team_name($team_id)
        : (string) get_the_title($team_id);

    if ($team_name === '') {
        $team_name = 'あなたのチーム';
    }

    $body = "チーム「{$team_name}」の申請内容に確認事項があります。\n内容をご確認のうえ、再度申請してください。";
    if ($revision_message !== '') {
        $body .= "\n\n【確認事項】\n" . $revision_message;
    }

    aidunite_notification_send($user_id, 'team_application_rejected', [
        'title'      => 'チーム申請を確認してください',
        'message'    => $body,
        'related_id' => $team_id,
        'link_url'   => home_url('/mypage/'),
        'idempotency_key' => 'team_application_rejected:' . $team_id . ':' . $user_id . ':' . md5($revision_message),
    ]);
}

/**
 * 管理者：修正依頼を送信（post_status draft・needs_revision 状態を保持）
 *
 * @param int    $team_id
 * @param string $revision_message
 * @return bool
 */
function aidunite_request_team_application_revision($team_id, $revision_message) {
    $team_id = (int) $team_id;
    $revision_message = sanitize_textarea_field(trim((string) $revision_message));

    if ($team_id <= 0 || $revision_message === '') {
        return false;
    }

    $post = get_post($team_id);
    if (!$post || $post->post_type !== 'team' || $post->post_status !== 'pending') {
        return false;
    }

    $user_id = function_exists('aidunite_team_resolve_leader_user_id')
        ? aidunite_team_resolve_leader_user_id($team_id)
        : (int) $post->post_author;

    if ($user_id <= 0) {
        return false;
    }

    update_post_meta($team_id, AIDUNITE_TEAM_META_REVISION_MESSAGE, $revision_message);
    update_post_meta($team_id, AIDUNITE_TEAM_META_REVISION_REQUESTED_AT, current_time('mysql'));
    if (function_exists('aidunite_team_write_status_meta')) {
        aidunite_team_write_status_meta($team_id, 'needs_revision');
    } else {
        update_post_meta($team_id, 'team_status', 'needs_revision');
    }

    remove_action('transition_post_status', 'aidunite_handle_team_application_status_change', 10);
    wp_update_post([
        'ID'          => $team_id,
        'post_status' => 'draft',
    ]);
    add_action('transition_post_status', 'aidunite_handle_team_application_status_change', 10, 3);

    delete_user_meta($user_id, 'pending_team_id');
    update_user_meta($user_id, AIDUNITE_USER_META_NEEDS_REVISION_TEAM_ID, $team_id);

    aidunite_send_team_application_revision_notification($user_id, $team_id, $revision_message);

    return true;
}

/**
 * 管理者 POST: 修正依頼
 */
function aidunite_admin_request_team_revision() {
    if (!current_user_can('administrator')) {
        wp_die('権限がありません。');
    }

    $team_id = isset($_POST['team_id']) ? (int) $_POST['team_id'] : 0;
    if ($team_id <= 0) {
        wp_die('不正なリクエストです。');
    }

    check_admin_referer('request_team_revision_' . $team_id);

    $revision_message = isset($_POST['revision_message']) ? wp_unslash($_POST['revision_message']) : '';
    if (trim((string) $revision_message) === '') {
        wp_die('確認・修正依頼内容を入力してください。');
    }

    if (!aidunite_request_team_application_revision($team_id, $revision_message)) {
        wp_die('修正依頼の送信に失敗しました。');
    }

    wp_safe_redirect(home_url('/team-approval?revision_requested=1'));
    exit;
}

add_action('admin_post_request_team_revision', 'aidunite_admin_request_team_revision');
