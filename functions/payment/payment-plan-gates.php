<?php
/**
 * Match / Club プラン機能ゲート
 *
 * @see docs/spec/payment.md §5
 * @package AidUnite
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 新規チームのデフォルト商品プランは Match
 */
add_action('aidunite_team_registered', function ($team_id) {
    $team_id = (int) $team_id;
    if ($team_id <= 0 || !function_exists('aidunite_payment_read_canonical_team_meta')) {
        return;
    }
    $meta = aidunite_payment_read_canonical_team_meta($team_id);
    if (($meta['product_plan_raw'] ?? '') === '' && function_exists('aidunite_team_write_product_plan_meta')) {
        aidunite_team_write_product_plan_meta($team_id, 'match');
    }
}, 5);

/**
 * Club プラン必須の固定ページ slug
 *
 * @return string[]
 */
function aidunite_payment_club_required_page_slugs() {
    return [
        'attendance-management',
        'attendance-report',
        'team-members',
        'player-add',
        'edit-player',
        'invite-guardian',
        'team-payment-management',
        'team-tuition-collections',
    ];
}

/**
 * Club プラン必須の REST route 接頭辞（代表者操作）
 *
 * @return string[]
 */
function aidunite_payment_club_required_rest_route_prefixes() {
    return [
        '/aidunite/v1/attendance',
        '/aidunite/v1/guardian-invite/send',
        '/aidunite/v1/guardian-invite/pending',
        '/aidunite/v1/guardian-invite/approve',
        '/aidunite/v1/guardian-invite/reject',
    ];
}

/**
 * @param int $team_id
 * @return bool
 */
function aidunite_payment_team_has_club_plan($team_id) {
    return function_exists('aidunite_payment_read_team_has_club_plan')
        && aidunite_payment_read_team_has_club_plan((int) $team_id);
}

/**
 * @param int $team_id
 * @return true|WP_Error
 */
function aidunite_payment_require_club_plan($team_id, $user_id = null) {
    $team_id = (int) $team_id;
    if ($team_id <= 0) {
        return new WP_Error('team_required', 'チームが特定できません。', ['status' => 400]);
    }
    $user_id = $user_id === null ? get_current_user_id() : (int) $user_id;
    if ($user_id > 0 && aidunite_payment_plan_gate_user_is_exempt($user_id)) {
        return true;
    }
    if (aidunite_payment_team_has_club_plan($team_id)) {
        return true;
    }

    return new WP_Error(
        'club_plan_required',
        'この機能は Clubプランでご利用いただけます。',
        [
            'status' => 403,
            'upgrade_url' => add_query_arg(['upgrade' => 'club'], home_url('/payment-setup')),
        ]
    );
}

/**
 * 管理者はゲートをスキップ
 *
 * @param int $user_id
 * @return bool
 */
function aidunite_payment_plan_gate_user_is_exempt($user_id) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return false;
    }
    if (function_exists('aidunite_user_is_privileged_admin') && aidunite_user_is_privileged_admin($user_id)) {
        return true;
    }

    return false;
}

/**
 * Club 専用ページへのアクセスを制限
 */
function aidunite_payment_template_redirect_club_plan_gate() {
    if (!is_user_logged_in() || is_admin()) {
        return;
    }

    $page = get_queried_object();
    $slug = ($page instanceof WP_Post) ? (string) $page->post_name : '';
    if ($slug === '' || !in_array($slug, aidunite_payment_club_required_page_slugs(), true)) {
        return;
    }

    $user_id = get_current_user_id();
    if (aidunite_payment_plan_gate_user_is_exempt($user_id)) {
        return;
    }

    $team_id = function_exists('aidunite_get_current_team_id')
        ? (int) aidunite_get_current_team_id($user_id)
        : 0;
    if ($team_id <= 0) {
        return;
    }

    if (aidunite_payment_team_has_club_plan($team_id)) {
        return;
    }

    wp_safe_redirect(add_query_arg(
        ['upgrade' => 'club', 'reason' => 'club_plan_required'],
        home_url('/payment-setup')
    ));
    exit;
}
add_action('template_redirect', 'aidunite_payment_template_redirect_club_plan_gate', 5);

/**
 * @param mixed          $result
 * @param WP_REST_Server $server
 * @param WP_REST_Request $request
 * @return mixed
 */
function aidunite_payment_rest_club_plan_gate($result, $server, $request) {
    if (!is_user_logged_in()) {
        return $result;
    }

    $route = (string) $request->get_route();
    $blocked = false;
    foreach (aidunite_payment_club_required_rest_route_prefixes() as $prefix) {
        if (strpos($route, $prefix) === 0) {
            $blocked = true;
            break;
        }
    }
    if (!$blocked) {
        return $result;
    }

    $user_id = get_current_user_id();
    if (aidunite_payment_plan_gate_user_is_exempt($user_id)) {
        return $result;
    }

    $team_id = function_exists('aidunite_get_current_team_id')
        ? (int) aidunite_get_current_team_id($user_id)
        : 0;
    if ($team_id <= 0) {
        return $result;
    }

    $check = aidunite_payment_require_club_plan($team_id);
    if (is_wp_error($check)) {
        return $check;
    }

    return $result;
}
add_filter('rest_pre_dispatch', 'aidunite_payment_rest_club_plan_gate', 12, 3);

/**
 * チーム内チャット（room_type=team）作成ゲート
 *
 * @param int $team_id
 * @return int|WP_Error
 */
function aidunite_payment_gate_team_chat_creation($team_id) {
    $user_id = get_current_user_id();
    if ($user_id > 0 && aidunite_payment_plan_gate_user_is_exempt($user_id)) {
        return (int) $team_id;
    }

    $check = aidunite_payment_require_club_plan($team_id, $user_id);
    if (is_wp_error($check)) {
        return $check;
    }

    return (int) $team_id;
}

/**
 * マイページメニュー項目と product_plan feature の対応
 *
 * @return array<string, string> url path fragment => feature key
 */
function aidunite_payment_mypage_menu_feature_map() {
    return [
        '/attendance-management' => 'attendance',
        '/attendance-report' => 'attendance',
        '/team-members' => 'member_management',
        '/player-add' => 'member_management',
        '/edit-player' => 'member_management',
        '/invite-guardian' => 'guardian_comms',
        '/team-payment-management' => 'tuition_connect',
        '/team-tuition-collections' => 'tuition_connect',
        '/parent-payment' => 'tuition_connect',
    ];
}

/**
 * Match プラン時に Club 専用メニューを除外（管理者は除外しない）
 *
 * @param array<int, array<string, mixed>> $menu
 * @param int                             $user_id
 * @param int                             $team_id
 * @return array<int, array<string, mixed>>
 */
function aidunite_payment_filter_mypage_menu_items(array $menu, $user_id = 0, $team_id = 0) {
    $user_id = (int) $user_id;
    $team_id = (int) $team_id;
    if ($user_id > 0 && aidunite_payment_plan_gate_user_is_exempt($user_id)) {
        return $menu;
    }
    if ($team_id <= 0 && $user_id > 0 && function_exists('aidunite_get_current_team_id')) {
        $team_id = (int) aidunite_get_current_team_id($user_id);
    }
    if ($team_id <= 0 || !function_exists('aidunite_payment_read_feature_flags_payload')) {
        return $menu;
    }

    $features = [];
    if (function_exists('aidunite_payment_read_team_payload')) {
        $payload = aidunite_payment_read_team_payload($team_id, $user_id);
        $features = is_array($payload['features'] ?? null) ? $payload['features'] : [];
    }
    if ($features === [] && function_exists('aidunite_payment_read_feature_flags_payload')) {
        $features = aidunite_payment_read_feature_flags_payload('match');
    }
    $map = aidunite_payment_mypage_menu_feature_map();

    return array_values(array_filter($menu, static function ($item) use ($features, $map) {
        if (!is_array($item)) {
            return true;
        }
        $feature_key = (string) ($item['plan_feature'] ?? '');
        if ($feature_key === '' && !empty($item['url'])) {
            $url = (string) $item['url'];
            foreach ($map as $fragment => $key) {
                if (strpos($url, $fragment) !== false) {
                    $feature_key = $key;
                    break;
                }
            }
        }
        if ($feature_key === '') {
            return true;
        }

        return !empty($features[$feature_key]);
    }));
}
