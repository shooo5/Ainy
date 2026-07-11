<?php
/**
 * 保護者 pending 中のページアクセスガード
 *
 * @package AidUnite
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * pending 保護者が利用できないページ slug
 *
 * @return string[]
 */
function aidunite_parent_pending_blocked_page_slugs() {
    return [
        'schedule-management',
        'communication',
        'chat',
        'attendance-report',
        'attendance-management',
        'match-board-own',
        'player-add',
        'team-members',
        'edit-player',
        'parent-payment',
    ];
}

/**
 * @param int $user_id
 * @param int $team_id
 * @return bool
 */
function aidunite_parent_user_is_pending_for_team($user_id, $team_id) {
    $user_id = (int) $user_id;
    $team_id = (int) $team_id;
    if ($user_id <= 0 || $team_id <= 0 || !function_exists('aidunite_parent_read_user_membership_status')) {
        return false;
    }

    return aidunite_parent_read_user_membership_status($user_id, $team_id) === 'pending';
}

/**
 * pending 保護者をマイページへ誘導
 */
function aidunite_parent_template_redirect_pending_guard() {
    if (!is_user_logged_in() || is_admin()) {
        return;
    }

    $user_id = get_current_user_id();
    $user_info = function_exists('aidunite_get_user_info') ? aidunite_get_user_info($user_id) : [];
    if (($user_info['user_type'] ?? '') !== 'parent') {
        return;
    }

    if (function_exists('aidunite_user_is_privileged_admin') && aidunite_user_is_privileged_admin($user_id)) {
        return;
    }

    $page = get_queried_object();
    $slug = ($page instanceof WP_Post) ? (string) $page->post_name : '';
    if ($slug === '' || !in_array($slug, aidunite_parent_pending_blocked_page_slugs(), true)) {
        return;
    }

    $team_id = function_exists('aidunite_get_current_team_id')
        ? (int) aidunite_get_current_team_id($user_id)
        : (int) get_user_meta($user_id, 'team_id', true);

    if ($team_id <= 0 && function_exists('aidunite_parent_get_pending_mypage_context')) {
        $pending_ctx = aidunite_parent_get_pending_mypage_context($user_id);
        $pending_teams = is_array($pending_ctx['teams'] ?? null) ? $pending_ctx['teams'] : [];
        if (!empty($pending_teams[0]['team_id'])) {
            $team_id = (int) $pending_teams[0]['team_id'];
        }
    }

    if ($team_id <= 0 || !aidunite_parent_user_is_pending_for_team($user_id, $team_id)) {
        return;
    }

    wp_safe_redirect(add_query_arg('pending_guard', '1', home_url('/mypage')));
    exit;
}

add_action('template_redirect', 'aidunite_parent_template_redirect_pending_guard', 8);

/** @var int */
const AIDUNITE_GUARDIAN_INVITE_RATE_MAX = 15;

/** @var int seconds */
const AIDUNITE_GUARDIAN_INVITE_RATE_WINDOW = 300;

/**
 * 公開 guardian-invite REST の IP レート制限
 *
 * @param string $action signup|signup_context|send
 * @return true|WP_Error
 */
function aidunite_guardian_invite_check_rate_limit($action = 'signup') {
    $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : 'unknown';
    $key = 'aidunite_gi_rate_' . sanitize_key((string) $action) . '_' . md5($ip);
    $count = (int) get_transient($key);
    if ($count >= AIDUNITE_GUARDIAN_INVITE_RATE_MAX) {
        return new WP_Error(
            'guardian_invite_rate_limited',
            'リクエストが多すぎます。しばらく経ってから再度お試しください。',
            ['status' => 429]
        );
    }
    set_transient($key, $count + 1, AIDUNITE_GUARDIAN_INVITE_RATE_WINDOW);

    return true;
}

/**
 * pending 保護者が REST で利用できる route 接頭辞
 *
 * @return string[]
 */
function aidunite_parent_pending_rest_allowed_route_prefixes() {
    return [
        '/aidunite/v1/notifications/',
        '/aidunite/v1/push/',
        '/aidunite/v1/team-context',
        '/aidunite/v1/current-operating-team',
    ];
}

/**
 * @param string $route
 * @return bool
 */
function aidunite_parent_pending_rest_route_is_allowed($route) {
    $route = (string) $route;
    foreach (aidunite_parent_pending_rest_allowed_route_prefixes() as $prefix) {
        if (strpos($route, $prefix) === 0) {
            return true;
        }
    }

    return false;
}

/**
 * @param int $user_id
 * @return bool
 */
function aidunite_parent_rest_should_block_operations($user_id) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return false;
    }

    $user_info = function_exists('aidunite_get_user_info') ? aidunite_get_user_info($user_id) : [];
    if (($user_info['user_type'] ?? '') !== 'parent') {
        return false;
    }

    if (function_exists('aidunite_user_is_privileged_admin') && aidunite_user_is_privileged_admin($user_id)) {
        return false;
    }

    if (function_exists('aidunite_parent_is_pending_only_mypage')
        && aidunite_parent_is_pending_only_mypage($user_id)) {
        return true;
    }

    $team_id = function_exists('aidunite_get_current_team_id')
        ? (int) aidunite_get_current_team_id($user_id)
        : (int) get_user_meta($user_id, 'team_id', true);

    if ($team_id <= 0) {
        return false;
    }

    return aidunite_parent_user_is_pending_for_team($user_id, $team_id);
}

/**
 * pending 保護者のチーム操作系 REST を拒否
 *
 * @param mixed            $result
 * @param WP_REST_Server   $server
 * @param WP_REST_Request  $request
 * @return mixed
 */
function aidunite_parent_rest_pending_guard($result, $server, $request) {
    if (!is_user_logged_in() || !($request instanceof WP_REST_Request)) {
        return $result;
    }

    $route = (string) $request->get_route();
    if ($route === '' || strpos($route, '/aidunite/v1') !== 0) {
        return $result;
    }

    $user_id = get_current_user_id();
    if (!aidunite_parent_rest_should_block_operations($user_id)) {
        return $result;
    }

    if (aidunite_parent_pending_rest_route_is_allowed($route)) {
        return $result;
    }

    return new WP_Error(
        'parent_pending_restricted',
        'チーム代表者の承認後にご利用いただけます。',
        ['status' => 403]
    );
}

add_filter('rest_pre_dispatch', 'aidunite_parent_rest_pending_guard', 10, 3);

/**
 * 廃止した schedule-list を schedule-management へリダイレクト
 */
function aidunite_redirect_legacy_schedule_list_page() {
    if (!is_page('schedule-list')) {
        return;
    }

    wp_safe_redirect(home_url('/schedule-management'), 301);
    exit;
}

add_action('template_redirect', 'aidunite_redirect_legacy_schedule_list_page', 5);
