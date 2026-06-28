<?php
/**
 * アクティベーション: 旧 /first-match リダイレクト・ページゲート
 *
 * @package AidUnite
 * @see docs/spec/account-onboarding.md §5A
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 廃止した /first-match へのリクエストか
 *
 * @return bool
 */
function aidunite_is_legacy_first_match_request() {
    if (is_page('first-match')) {
        return true;
    }

    $pagename = get_query_var('pagename');
    if ($pagename === 'first-match') {
        return true;
    }

    if (function_exists('aidunite_get_request_path_slug')) {
        return aidunite_get_request_path_slug() === 'first-match';
    }

    $path = trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');

    return $path === 'first-match' || str_ends_with($path, '/first-match');
}

/**
 * 旧 /first-match → マイページ（試合募集クイックモーダル）へ恒久リダイレクト
 */
function aidunite_activation_legacy_first_match_redirect() {
    if (!aidunite_is_legacy_first_match_request()) {
        return;
    }

    wp_safe_redirect(aidunite_get_activation_recruit_edit_url());
    exit;
}

add_action('template_redirect', 'aidunite_activation_legacy_first_match_redirect', 5);

/**
 * 廃止した /schedule-edit → スケジュール管理 or マイページ（クイックモーダル）
 */
function aidunite_redirect_legacy_schedule_edit_page() {
    if (!is_user_logged_in() || !is_page('schedule-edit')) {
        return;
    }

    $schedule_id = 0;
    if (!empty($_GET['post_id'])) {
        $schedule_id = absint(wp_unslash((string) $_GET['post_id']));
    } elseif (!empty($_GET['id'])) {
        $schedule_id = absint(wp_unslash((string) $_GET['id']));
    } elseif (!empty($_GET['schedule_id'])) {
        $schedule_id = absint(wp_unslash((string) $_GET['schedule_id']));
    }

    if ($schedule_id > 0 && function_exists('aidunite_get_schedule_edit_url')) {
        wp_safe_redirect(aidunite_get_schedule_edit_url($schedule_id));
        exit;
    }

    $date = '';
    if (!empty($_GET['date'])) {
        $date = sanitize_text_field(wp_unslash((string) $_GET['date']));
    }

    if ($date !== '' && function_exists('aidunite_schedule_edit_is_trial_simplified')
        && aidunite_schedule_edit_is_trial_simplified()) {
        wp_safe_redirect(aidunite_get_activation_recruit_edit_url());
        exit;
    }

    if ($date !== '' && function_exists('aidunite_get_schedule_edit_url')) {
        wp_safe_redirect(aidunite_get_schedule_edit_url(0, $date));
        exit;
    }

    if (function_exists('aidunite_schedule_edit_is_trial_simplified')
        && aidunite_schedule_edit_is_trial_simplified()) {
        wp_safe_redirect(aidunite_get_activation_recruit_edit_url());
        exit;
    }

    wp_safe_redirect(function_exists('aidunite_get_schedule_edit_url')
        ? aidunite_get_schedule_edit_url()
        : home_url('/schedule-management/'));
    exit;
}

add_action('template_redirect', 'aidunite_redirect_legacy_schedule_edit_page', 6);

/**
 * ロックページへのアクセス制御
 */
function aidunite_activation_page_restrictions() {
    if (!is_user_logged_in() || !is_page()) {
        return;
    }

    if (!function_exists('aidunite_get_effective_user_role')) {
        return;
    }

    list($role,) = aidunite_get_effective_user_role();
    if ($role !== 'team_leader') {
        return;
    }

    $post = get_queried_object();
    if (!$post instanceof WP_Post) {
        return;
    }

    $slug = (string) $post->post_name;
    $team_id = function_exists('aidunite_get_current_team_id')
        ? (int) aidunite_get_current_team_id()
        : 0;

    if ($slug === 'communication' && !aidunite_activation_is_chat_unlocked($team_id)) {
        wp_safe_redirect(add_query_arg('activation_locked', 'chat', home_url('/mypage/')));
        exit;
    }

    if (!aidunite_activation_is_page_locked($slug, $team_id)) {
        return;
    }

    wp_safe_redirect(add_query_arg('activation_locked', $slug, home_url('/mypage/')));
    exit;
}

add_action('template_redirect', 'aidunite_activation_page_restrictions', 12);
