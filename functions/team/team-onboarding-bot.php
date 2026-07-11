<?php
/**
 * オンボーディング用「練習相手チーム」ボット（初回 recruit 公開後に MR を自動生成）
 *
 * @package AidUnite
 * @see docs/spec/account-onboarding.md §5A.8
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('AIDUNITE_ONBOARDING_BOT_META')) {
    define('AIDUNITE_ONBOARDING_BOT_META', 'aidunite_onboarding_bot');
}

if (!defined('AIDUNITE_TEAM_META_ONBOARDING_BOT_MR')) {
    define('AIDUNITE_TEAM_META_ONBOARDING_BOT_MR', 'aidunite_onboarding_bot_mr_id');
}

if (!defined('AIDUNITE_TEAM_META_ONBOARDING_BOT_SCHEDULE')) {
    define('AIDUNITE_TEAM_META_ONBOARDING_BOT_SCHEDULE', 'aidunite_onboarding_bot_schedule_id');
}

if (!defined('AIDUNITE_TEAM_META_ONBOARDING_BOT_CHAT_MODAL_PENDING')) {
    define('AIDUNITE_TEAM_META_ONBOARDING_BOT_CHAT_MODAL_PENDING', 'aidunite_onboarding_bot_chat_modal_pending');
}

if (!defined('AIDUNITE_TEAM_META_ONBOARDING_BOT_CHAT_MODAL_SHOWN')) {
    define('AIDUNITE_TEAM_META_ONBOARDING_BOT_CHAT_MODAL_SHOWN', 'aidunite_onboarding_bot_chat_modal_shown');
}

if (!defined('AIDUNITE_OPTION_ONBOARDING_BOT_TEAM_ID')) {
    define('AIDUNITE_OPTION_ONBOARDING_BOT_TEAM_ID', 'aidunite_onboarding_bot_team_id');
}

if (!defined('AIDUNITE_OPTION_ONBOARDING_BOT_USER_ID')) {
    define('AIDUNITE_OPTION_ONBOARDING_BOT_USER_ID', 'aidunite_onboarding_bot_user_id');
}

/**
 * ボット機能が有効か
 *
 * @return bool
 */
function aidunite_onboarding_bot_enabled() {
    return (bool) apply_filters('aidunite_onboarding_bot_enabled', true);
}

/**
 * @param int $team_id
 * @return bool
 */
function aidunite_is_onboarding_bot_team($team_id) {
    $team_id = (int) $team_id;
    if ($team_id <= 0) {
        return false;
    }

    return get_post_meta($team_id, AIDUNITE_ONBOARDING_BOT_META, true) === '1';
}

/**
 * @param int $schedule_id
 * @return bool
 */
function aidunite_is_onboarding_bot_schedule($schedule_id) {
    $schedule_id = (int) $schedule_id;
    if ($schedule_id <= 0) {
        return false;
    }

    if (get_post_meta($schedule_id, AIDUNITE_ONBOARDING_BOT_META, true) === '1') {
        return true;
    }

    $team_id = (int) get_post_meta($schedule_id, 'team_id', true);

    return aidunite_is_onboarding_bot_team($team_id);
}

/**
 * @param int $request_id
 * @return bool
 */
function aidunite_match_request_is_onboarding_bot($request_id) {
    $request_id = (int) $request_id;
    if ($request_id <= 0) {
        return false;
    }

    if (get_post_meta($request_id, AIDUNITE_ONBOARDING_BOT_META, true) === '1') {
        return true;
    }

    $from_team = (int) get_post_meta($request_id, 'from_team_id', true);

    return aidunite_is_onboarding_bot_team($from_team);
}

/**
 * 通知・メール等を抑止中か（ボット MR 生成時）
 *
 * @return bool
 */
function aidunite_onboarding_bot_suppress_notifications() {
    return !empty($GLOBALS['aidunite_onboarding_bot_suppress_notifications']);
}

/**
 * @param bool $suppress
 */
function aidunite_onboarding_bot_set_suppress_notifications($suppress) {
    $GLOBALS['aidunite_onboarding_bot_suppress_notifications'] = (bool) $suppress;
}

/**
 * 固定ボット team を取得または作成
 *
 * @return int team post ID
 */
function aidunite_onboarding_bot_get_or_create_team() {
    $team_id = (int) get_option(AIDUNITE_OPTION_ONBOARDING_BOT_TEAM_ID, 0);
    if ($team_id > 0) {
        $post = get_post($team_id);
        if ($post && $post->post_type === 'team' && $post->post_status === 'publish') {
            return $team_id;
        }
    }

    $user_id = aidunite_onboarding_bot_get_or_create_user();
    if ($user_id <= 0) {
        return 0;
    }

    $team_id = wp_insert_post([
        'post_type'   => 'team',
        'post_status' => 'publish',
        'post_title'  => '練習相手チーム',
        'post_author' => $user_id,
    ], true);

    if (is_wp_error($team_id) || $team_id <= 0) {
        return 0;
    }

    $team_id = (int) $team_id;
    update_post_meta($team_id, 'team_name', '練習相手チーム');
    if (function_exists('aidunite_team_write_status_meta')) {
        aidunite_team_write_status_meta($team_id, 'active');
    } else {
        update_post_meta($team_id, 'team_status', 'active');
    }
    update_post_meta($team_id, AIDUNITE_ONBOARDING_BOT_META, '1');
    update_post_meta($team_id, 'team_gender_option', 'male');
    update_post_meta($team_id, 'team_leader_id', $user_id);
    update_post_meta($team_id, AIDUNITE_TEAM_META_ACTIVATION_STAGE, 'first_established');

    if (function_exists('aidunite_mark_post_as_test_fixture')) {
        aidunite_mark_post_as_test_fixture($team_id, 'onboarding_bot');
    }

    update_option(AIDUNITE_OPTION_ONBOARDING_BOT_TEAM_ID, $team_id);

    if (function_exists('aidunite_user_attach_approved_team_membership')) {
        aidunite_user_attach_approved_team_membership($user_id, $team_id);
    } else {
        update_user_meta($user_id, 'team_id', $team_id);
        if (function_exists('aidunite_user_write_role_meta')) {
            aidunite_user_write_role_meta($user_id, 'team_leader');
        } else {
            update_user_meta($user_id, 'aidunite_role', 'team_leader');
            update_user_meta($user_id, 'user_type', 'team_leader');
        }
    }

    return $team_id;
}

/**
 * ボット操作用ユーザー
 *
 * @return int
 */
function aidunite_onboarding_bot_get_or_create_user() {
    $user_id = (int) get_option(AIDUNITE_OPTION_ONBOARDING_BOT_USER_ID, 0);
    if ($user_id > 0 && get_userdata($user_id)) {
        return $user_id;
    }

    $login = 'ainy_onboarding_bot';
    $existing = get_user_by('login', $login);
    if ($existing) {
        $user_id = (int) $existing->ID;
        update_option(AIDUNITE_OPTION_ONBOARDING_BOT_USER_ID, $user_id);

        return $user_id;
    }

    $email = apply_filters('aidunite_onboarding_bot_user_email', 'onboarding-bot@ainy.local');
    $user_id = wp_insert_user([
        'user_login'   => $login,
        'user_email'   => $email,
        'user_pass'    => wp_generate_password(32, true, true),
        'display_name' => '練習相手チーム',
        'role'         => 'subscriber',
    ]);

    if (is_wp_error($user_id) || $user_id <= 0) {
        return 0;
    }

    if (function_exists('aidunite_user_write_role_meta')) {
        aidunite_user_write_role_meta($user_id, 'team_leader');
    } else {
        update_user_meta($user_id, 'aidunite_role', 'team_leader');
        update_user_meta($user_id, 'user_type', 'team_leader');
    }
    update_option(AIDUNITE_OPTION_ONBOARDING_BOT_USER_ID, (int) $user_id);

    return (int) $user_id;
}

/**
 * 初回 recruit 公開後にボット MR を生成するか
 *
 * @param int $user_team_id
 * @param int $recruit_schedule_id
 * @return bool
 */
function aidunite_onboarding_bot_should_spawn($user_team_id, $recruit_schedule_id) {
    $user_team_id = (int) $user_team_id;
    $recruit_schedule_id = (int) $recruit_schedule_id;

    if (!aidunite_onboarding_bot_enabled()) {
        return false;
    }
    if ($user_team_id <= 0 || $recruit_schedule_id <= 0) {
        return false;
    }
    if (aidunite_is_onboarding_bot_team($user_team_id)) {
        return false;
    }
    if (!function_exists('aidunite_activation_is_mission_ui') || !aidunite_activation_is_mission_ui($user_team_id)) {
        return false;
    }
    $recruit_bundle = function_exists('aidunite_schedule_get_display_bundle')
        ? aidunite_schedule_get_display_bundle((int) $recruit_schedule_id)
        : [];
    $recruit_intent = function_exists('aidunite_schedule_read_intent')
        ? aidunite_schedule_read_intent((int) $recruit_schedule_id)
        : (string) get_post_meta($recruit_schedule_id, 'intent', true);
    if ($recruit_intent !== 'recruit') {
        return false;
    }
    $schedule_team = (int) ($recruit_bundle['team_id'] ?? (function_exists('aidunite_schedule_read_team_id')
        ? aidunite_schedule_read_team_id((int) $recruit_schedule_id)
        : 0));
    if ($schedule_team !== $user_team_id) {
        return false;
    }

    $existing_mr = (int) get_post_meta($user_team_id, AIDUNITE_TEAM_META_ONBOARDING_BOT_MR, true);
    if ($existing_mr > 0 && get_post($existing_mr)) {
        return false;
    }

    if (function_exists('aidunite_team_has_established_match') && aidunite_team_has_established_match($user_team_id)) {
        return false;
    }

    $first_recruit = defined('AIDUNITE_TEAM_META_FIRST_RECRUIT_SCHEDULE_ID')
        ? (int) get_post_meta($user_team_id, AIDUNITE_TEAM_META_FIRST_RECRUIT_SCHEDULE_ID, true)
        : 0;
    if ($first_recruit > 0 && $first_recruit !== $recruit_schedule_id) {
        return false;
    }

    return true;
}

/**
 * 申請者側の会場（相手募集に対する補完）
 *
 * @param string $host_place
 * @return string
 */
function aidunite_onboarding_bot_complement_place($host_place) {
    $host_place = is_string($host_place) ? strtolower(trim($host_place)) : '';
    if (in_array($host_place, ['home', 'ホーム'], true)) {
        return 'away';
    }
    if (in_array($host_place, ['away', 'アウェイ'], true)) {
        return 'home';
    }

    return 'away';
}

/**
 * ボット側 schedule を作成
 *
 * @param int $bot_team_id
 * @param int $user_recruit_schedule_id
 * @return int schedule ID
 */
function aidunite_onboarding_bot_create_schedule($bot_team_id, $user_recruit_schedule_id) {
    $bot_team_id = (int) $bot_team_id;
    $user_recruit_schedule_id = (int) $user_recruit_schedule_id;
    $bot_user_id = aidunite_onboarding_bot_get_or_create_user();

    $host_bundle = function_exists('aidunite_schedule_get_display_bundle')
        ? aidunite_schedule_get_display_bundle($user_recruit_schedule_id)
        : [];
    $date = (string) ($host_bundle['date'] ?? (function_exists('aidunite_schedule_read_normalized_date')
        ? aidunite_schedule_read_normalized_date($user_recruit_schedule_id)
        : ''));
    $start = (string) ($host_bundle['start_time'] ?? '');
    $end = (string) ($host_bundle['end_time'] ?? '');
    $gender = (string) ($host_bundle['gender'] ?? '');
    if ($gender === '') {
        $gender = function_exists('aidunite_schedule_read_gender_raw')
            ? aidunite_schedule_read_gender_raw($user_recruit_schedule_id)
            : '';
    }
    if (function_exists('aidunite_normalize_gender_canonical')) {
        $gender = aidunite_normalize_gender_canonical($gender);
    }
    if (!in_array($gender, ['male', 'female'], true)) {
        $gender = function_exists('aidunite_activation_recruit_gender_for_team')
            ? aidunite_activation_recruit_gender_for_team((int) ($host_bundle['team_id'] ?? (function_exists('aidunite_schedule_read_team_id')
                ? aidunite_schedule_read_team_id($user_recruit_schedule_id)
                : 0)))
            : 'male';
    }

    $host_place = function_exists('aidunite_schedule_read_place_raw')
        ? aidunite_schedule_read_place_raw($user_recruit_schedule_id)
        : '';
    $bot_place = aidunite_onboarding_bot_complement_place((string) $host_place);

    $male_slots = $gender === 'female' ? 0 : 1;
    $female_slots = $gender === 'female' ? 1 : 0;

    if (!function_exists('aidunite_schedule_create_published_post')) {
        require_once get_stylesheet_directory() . '/functions/schedule/schedule-persist.php';
    }

    $persist = aidunite_schedule_normalize_form_input([
        'date' => $date,
        'end_date' => $date,
        'start_time' => $start,
        'end_time' => $end,
        'venue_condition' => $bot_place,
        'gender_condition' => $gender,
        'intent' => 'recruit',
        'certainty' => 'firm',
        'schedule_type' => 'practice_match',
        'team_id' => $bot_team_id,
        'user_id' => $bot_user_id,
        'male_slots' => $male_slots,
        'female_slots' => $female_slots,
    ]);

    $schedule_id = aidunite_schedule_create_published_post($persist);
    if (is_wp_error($schedule_id) || $schedule_id <= 0) {
        return 0;
    }

    $schedule_id = (int) $schedule_id;
    wp_update_post([
        'ID' => $schedule_id,
        'post_title' => $date . ' 練習試合（練習相手）',
    ]);
    update_post_meta($schedule_id, AIDUNITE_ONBOARDING_BOT_META, '1');

    if (function_exists('aidunite_mark_post_as_test_fixture')) {
        aidunite_mark_post_as_test_fixture($schedule_id, 'onboarding_bot');
    }

    return $schedule_id;
}

/**
 * ボットからユーザー recruit へ MR を作成
 *
 * @param int $user_team_id
 * @param int $user_recruit_schedule_id
 * @param int $bot_schedule_id
 * @return int match_request ID
 */
function aidunite_onboarding_bot_create_match_request($user_team_id, $user_recruit_schedule_id, $bot_schedule_id) {
    if (!function_exists('aidunite_save_match_application_core')) {
        return 0;
    }

    $bot_user_id = aidunite_onboarding_bot_get_or_create_user();
    $host_mr_bundle = function_exists('aidunite_schedule_get_display_bundle')
        ? aidunite_schedule_get_display_bundle((int) $user_recruit_schedule_id)
        : [];
    $bot_mr_bundle = function_exists('aidunite_schedule_get_display_bundle')
        ? aidunite_schedule_get_display_bundle((int) $bot_schedule_id)
        : [];
    $start = (string) ($host_mr_bundle['start_time'] ?? '');
    $end = (string) ($host_mr_bundle['end_time'] ?? '');
    $gender = (string) ($host_mr_bundle['gender'] ?? (function_exists('aidunite_schedule_read_gender_raw')
        ? aidunite_schedule_read_gender_raw($user_recruit_schedule_id)
        : ''));
    $place = (string) ($bot_mr_bundle['place'] ?? (function_exists('aidunite_schedule_read_place_raw')
        ? aidunite_schedule_read_place_raw($bot_schedule_id)
        : ''));

    aidunite_onboarding_bot_set_suppress_notifications(true);
    $result = aidunite_save_match_application_core([
        'my_schedule_id'       => $bot_schedule_id,
        'other_schedule_id'    => $user_recruit_schedule_id,
        'selected_start_time'  => $start,
        'selected_end_time'    => $end,
        'selected_place'       => $place,
        'selected_gender'      => $gender,
    ], $bot_user_id);
    aidunite_onboarding_bot_set_suppress_notifications(false);

    if (empty($result['success']) || empty($result['request_id'])) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[aidunite onboarding bot] MR create failed: ' . wp_json_encode($result));
        }

        return 0;
    }

    $request_id = (int) $result['request_id'];
    update_post_meta($request_id, AIDUNITE_ONBOARDING_BOT_META, '1');
    if (function_exists('aidunite_mark_post_as_test_fixture')) {
        aidunite_mark_post_as_test_fixture($request_id, 'onboarding_bot');
    }

    update_post_meta($user_team_id, AIDUNITE_TEAM_META_ONBOARDING_BOT_MR, $request_id);
    update_post_meta($user_team_id, AIDUNITE_TEAM_META_ONBOARDING_BOT_SCHEDULE, $bot_schedule_id);

    if (function_exists('aidunite_team_activation_sync')) {
        aidunite_team_activation_sync($user_team_id);
    }

    return $request_id;
}

/**
 * チームの初回 recruit schedule ID（meta 未設定時は最古の recruit を採用）
 *
 * @param int $user_team_id
 * @return int
 */
function aidunite_onboarding_bot_resolve_first_recruit_schedule_id($user_team_id) {
    $user_team_id = (int) $user_team_id;
    if ($user_team_id <= 0) {
        return 0;
    }

    if (defined('AIDUNITE_TEAM_META_FIRST_RECRUIT_SCHEDULE_ID')) {
        $first = (int) get_post_meta($user_team_id, AIDUNITE_TEAM_META_FIRST_RECRUIT_SCHEDULE_ID, true);
        if ($first > 0 && get_post($first)) {
            return $first;
        }
    }

    $posts = get_posts([
        'post_type'      => 'schedule',
        'post_status'    => 'publish',
        'posts_per_page' => 1,
        'orderby'        => 'date',
        'order'          => 'ASC',
        'fields'         => 'ids',
        'meta_query'     => [
            [
                'key'   => 'team_id',
                'value' => (string) $user_team_id,
            ],
            [
                'key'   => 'intent',
                'value' => 'recruit',
            ],
        ],
    ]);

    return !empty($posts) ? (int) $posts[0] : 0;
}

/**
 * オンボーディングボットのデバッグログ
 *
 * @param string              $event
 * @param array<string,mixed> $context
 */
function aidunite_onboarding_bot_log($event, array $context = []) {
    $event = is_string($event) ? trim($event) : 'unknown';
    if ($event === '') {
        $event = 'unknown';
    }
    if (function_exists('aidunite_match_flow_debug_log')) {
        aidunite_match_flow_debug_log('onboarding_bot_' . $event, $context);
    }
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[aidunite onboarding bot] ' . $event . ': ' . wp_json_encode($context));
    }
}

/**
 * チームに紐づく既存ボット MR を返す（有効な投稿のみ）
 *
 * @param int $user_team_id
 * @return int
 */
function aidunite_onboarding_bot_resolve_existing_mr_id($user_team_id) {
    $user_team_id = (int) $user_team_id;
    if ($user_team_id <= 0 || !defined('AIDUNITE_TEAM_META_ONBOARDING_BOT_MR')) {
        return 0;
    }
    $mr_id = (int) get_post_meta($user_team_id, AIDUNITE_TEAM_META_ONBOARDING_BOT_MR, true);
    if ($mr_id > 0 && get_post($mr_id)) {
        return $mr_id;
    }

    return 0;
}

/**
 * 既に公開済みの初回 recruit に対してボット MR が無ければ生成（ページ再表示時の補完）
 *
 * @param int $user_team_id
 * @return int match_request ID
 */
function aidunite_onboarding_bot_ensure_for_team($user_team_id) {
    $user_team_id = (int) $user_team_id;
    if ($user_team_id <= 0 || !aidunite_onboarding_bot_enabled()) {
        return 0;
    }

    $existing_mr = aidunite_onboarding_bot_resolve_existing_mr_id($user_team_id);
    if ($existing_mr > 0) {
        return $existing_mr;
    }

    $schedule_id = aidunite_onboarding_bot_resolve_first_recruit_schedule_id($user_team_id);
    if ($schedule_id <= 0) {
        aidunite_onboarding_bot_log('ensure_no_recruit', ['team_id' => $user_team_id]);

        return 0;
    }

    $mr_id = aidunite_onboarding_bot_try_spawn_for_first_recruit($schedule_id, $user_team_id);
    if ($mr_id <= 0) {
        aidunite_onboarding_bot_log('ensure_spawn_retry', [
            'team_id' => $user_team_id,
            'recruit_schedule_id' => $schedule_id,
        ]);
        $mr_id = aidunite_onboarding_bot_try_spawn_for_first_recruit($schedule_id, $user_team_id);
        if ($mr_id <= 0) {
            aidunite_onboarding_bot_log('ensure_spawn_failed', [
                'team_id' => $user_team_id,
                'recruit_schedule_id' => $schedule_id,
            ]);
        }
    }

    return $mr_id;
}

/**
 * 初回 recruit 公開後のボット生成
 *
 * @param int $recruit_schedule_id
 * @param int $user_team_id
 * @return int MR ID
 */
function aidunite_onboarding_bot_try_spawn_for_first_recruit($recruit_schedule_id, $user_team_id) {
    $recruit_schedule_id = (int) $recruit_schedule_id;
    $user_team_id = (int) $user_team_id;

    $existing_mr = aidunite_onboarding_bot_resolve_existing_mr_id($user_team_id);
    if ($existing_mr > 0) {
        return $existing_mr;
    }

    if (!aidunite_onboarding_bot_should_spawn($user_team_id, $recruit_schedule_id)) {
        aidunite_onboarding_bot_log('spawn_skipped', [
            'team_id' => $user_team_id,
            'recruit_schedule_id' => $recruit_schedule_id,
        ]);

        return 0;
    }

    $bot_team_id = aidunite_onboarding_bot_get_or_create_team();
    if ($bot_team_id <= 0) {
        aidunite_onboarding_bot_log('spawn_fail_bot_team', [
            'team_id' => $user_team_id,
            'recruit_schedule_id' => $recruit_schedule_id,
        ]);

        return 0;
    }

    $bot_schedule_id = aidunite_onboarding_bot_create_schedule($bot_team_id, $recruit_schedule_id);
    if ($bot_schedule_id <= 0) {
        aidunite_onboarding_bot_log('spawn_fail_bot_schedule', [
            'team_id' => $user_team_id,
            'recruit_schedule_id' => $recruit_schedule_id,
            'bot_team_id' => $bot_team_id,
        ]);

        return 0;
    }

    $mr_id = aidunite_onboarding_bot_create_match_request($user_team_id, $recruit_schedule_id, $bot_schedule_id);
    if ($mr_id <= 0) {
        aidunite_onboarding_bot_log('spawn_fail_mr', [
            'team_id' => $user_team_id,
            'recruit_schedule_id' => $recruit_schedule_id,
            'bot_schedule_id' => $bot_schedule_id,
        ]);

        return 0;
    }

    aidunite_onboarding_bot_log('spawn_ok', [
        'team_id' => $user_team_id,
        'recruit_schedule_id' => $recruit_schedule_id,
        'match_request_id' => $mr_id,
    ]);

    return $mr_id;
}

/**
 * @param int $schedule_id
 * @param array $data
 */
function aidunite_onboarding_bot_on_schedule_registered($schedule_id, $data) {
    if (!is_array($data) || ($data['intent'] ?? '') !== 'recruit') {
        return;
    }

    $team_id = (int) ($data['team_id'] ?? 0);
    if ($team_id <= 0 && $schedule_id > 0) {
        $team_id = (int) get_post_meta((int) $schedule_id, 'team_id', true);
    }

    if ($team_id <= 0) {
        return;
    }

    aidunite_onboarding_bot_try_spawn_for_first_recruit((int) $schedule_id, $team_id);
}

add_action('aidunite_schedule_registered', 'aidunite_onboarding_bot_on_schedule_registered', 25, 2);

/**
 * 募集中一覧からボット募集を除外
 *
 * @param WP_Post[] $posts
 * @return WP_Post[]
 */
function aidunite_onboarding_bot_filter_market_recruitments($posts) {
    if (!is_array($posts) || $posts === []) {
        return $posts;
    }

    return array_values(array_filter($posts, static function ($p) {
        $sid = $p instanceof WP_Post ? (int) $p->ID : (int) $p;

        return !aidunite_is_onboarding_bot_schedule($sid);
    }));
}

add_filter('aidunite_market_get_all_recruitments_posts', 'aidunite_onboarding_bot_filter_market_recruitments');

/**
 * mother set 判定でも除外
 *
 * @param bool $in_set
 * @param int  $schedule_id
 * @return bool
 */
function aidunite_onboarding_bot_filter_mother_set($in_set, $schedule_id) {
    if (!$in_set) {
        return false;
    }
    if (aidunite_is_onboarding_bot_schedule((int) $schedule_id)) {
        return false;
    }

    return true;
}

add_filter('aidunite_market_schedule_in_recruitment_mother_set', 'aidunite_onboarding_bot_filter_mother_set', 10, 2);

/**
 * @param array $post_data
 * @param int   $user_id
 * @return array|null null = 続行
 */
function aidunite_onboarding_bot_guard_apply($post_data, $user_id) {
    $other_schedule_id = (int) ($post_data['other_schedule_id'] ?? 0);
    if ($other_schedule_id <= 0) {
        return null;
    }

    $owner_team = function_exists('aidunite_resolve_schedule_owner_team_id')
        ? (int) aidunite_resolve_schedule_owner_team_id($other_schedule_id)
        : (int) get_post_meta($other_schedule_id, 'team_id', true);

    if (!aidunite_is_onboarding_bot_team($owner_team)) {
        return null;
    }

    $applicant_team = function_exists('aidunite_resolve_user_team_id_for_schedule_ops')
        ? (int) aidunite_resolve_user_team_id_for_schedule_ops((int) $user_id)
        : 0;

    if (aidunite_is_onboarding_bot_team($applicant_team)) {
        return null;
    }

    return [
        'success' => false,
        'message' => 'この募集には申請できません。',
        'code'    => 'onboarding_bot_recruit',
    ];
}

/**
 * 通知抑止
 */
function aidunite_onboarding_bot_maybe_suppress_notification($request_id) {
    if (aidunite_onboarding_bot_suppress_notifications()) {
        return true;
    }

    return aidunite_match_request_is_onboarding_bot((int) $request_id);
}

/**
 * 成立後: モーダル pending + 分析イベント
 *
 * @param int $request_id
 */
function aidunite_onboarding_bot_on_match_established($request_id) {
    $request_id = (int) $request_id;
    if ($request_id <= 0 || !aidunite_match_request_is_onboarding_bot($request_id)) {
        return;
    }

    $mr_est = function_exists('aidunite_match_request_get_canonical_meta')
        ? aidunite_match_request_get_canonical_meta($request_id)
        : [];
    $to_schedule_id = (int) ($mr_est['to_schedule_id'] ?? get_post_meta($request_id, 'to_schedule_id', true));
    $user_team_id = $to_schedule_id > 0 && function_exists('aidunite_resolve_schedule_owner_team_id')
        ? (int) aidunite_resolve_schedule_owner_team_id($to_schedule_id)
        : (int) ($mr_est['to_team_id'] ?? get_post_meta($request_id, 'to_team_id', true));

    if ($user_team_id <= 0 || aidunite_is_onboarding_bot_team($user_team_id)) {
        return;
    }

    update_post_meta($user_team_id, AIDUNITE_TEAM_META_ONBOARDING_BOT_CHAT_MODAL_PENDING, '1');

    if (function_exists('aidunite_analytics_insert_page_event')) {
        aidunite_analytics_insert_page_event([
            'event_type'  => 'onboarding_bot_established',
            'team_id'     => $user_team_id,
            'user_id'     => get_current_user_id(),
            'page_key'    => 'onboarding',
            'target_key'  => 'bot_match_' . $request_id,
        ]);
    }
}

add_action('aidunite_after_match_established', 'aidunite_onboarding_bot_on_match_established', 15, 1);

/**
 * マイページでチャット説明モーダルを出すか
 *
 * @param int $user_team_id
 * @return bool
 */
function aidunite_onboarding_bot_should_show_chat_modal_on_mypage($user_team_id) {
    $user_team_id = (int) $user_team_id;
    if ($user_team_id <= 0) {
        return false;
    }
    if (get_post_meta($user_team_id, AIDUNITE_TEAM_META_ONBOARDING_BOT_CHAT_MODAL_SHOWN, true) === '1') {
        return false;
    }

    return get_post_meta($user_team_id, AIDUNITE_TEAM_META_ONBOARDING_BOT_CHAT_MODAL_PENDING, true) === '1';
}

/**
 * 承認レスポンス用
 *
 * @param int $request_id
 * @param int $actor_user_id
 * @return bool
 */
function aidunite_onboarding_bot_should_show_chat_modal_after_approve($request_id, $actor_user_id) {
    unset($actor_user_id);

    return aidunite_match_request_is_onboarding_bot((int) $request_id);
}

/**
 * 申請状況一覧からボット行を除外するか
 *
 * @param int $user_team_id
 * @return bool
 */
function aidunite_onboarding_bot_should_hide_board_rows_for_team($user_team_id) {
    $user_team_id = (int) $user_team_id;
    if ($user_team_id <= 0) {
        return false;
    }

    if (get_post_meta($user_team_id, AIDUNITE_TEAM_META_ONBOARDING_BOT_CHAT_MODAL_SHOWN, true) === '1') {
        return true;
    }

    if (function_exists('aidunite_board_onboarding_mission_active')
        && aidunite_board_onboarding_mission_active($user_team_id)
        && function_exists('aidunite_board_onboarding_modal_step_shown')
        && !aidunite_board_onboarding_modal_step_shown($user_team_id, 'bot_arrived')) {
        return true;
    }

    return false;
}

/**
 * 成立直後〜モーダル閉じるまでチャット CTA を出さない
 *
 * @param int $request_id
 * @return bool
 */
function aidunite_onboarding_bot_should_suppress_chat_cta($request_id) {
    $request_id = (int) $request_id;
    if ($request_id <= 0 || !aidunite_match_request_is_onboarding_bot($request_id)) {
        return false;
    }

    $mr_cta = function_exists('aidunite_match_request_get_canonical_meta')
        ? aidunite_match_request_get_canonical_meta($request_id)
        : [];
    $to_schedule_id = (int) ($mr_cta['to_schedule_id'] ?? get_post_meta($request_id, 'to_schedule_id', true));
    $user_team_id = $to_schedule_id > 0 && function_exists('aidunite_resolve_schedule_owner_team_id')
        ? (int) aidunite_resolve_schedule_owner_team_id($to_schedule_id)
        : (int) ($mr_cta['to_team_id'] ?? get_post_meta($request_id, 'to_team_id', true));

    if ($user_team_id <= 0) {
        return false;
    }

    return get_post_meta($user_team_id, AIDUNITE_TEAM_META_ONBOARDING_BOT_CHAT_MODAL_SHOWN, true) !== '1';
}

/**
 * ボット MR のチャット URL（モーダル「チャット→」用）
 *
 * @param int $request_id
 * @return string
 */
function aidunite_onboarding_bot_get_chat_url_for_request($request_id) {
    $request_id = (int) $request_id;
    if ($request_id <= 0) {
        return '';
    }

    $room_id = 0;
    if (function_exists('aidunite_get_game_chat_room_for_match_request')) {
        $room = aidunite_get_game_chat_room_for_match_request($request_id);
        if ($room && !empty($room->id)) {
            $room_id = (int) $room->id;
        }
    }
    if ($room_id <= 0) {
        $room_id = (int) get_post_meta($request_id, 'chat_room_id', true);
    }
    if ($room_id > 0) {
        return home_url('/chat?room_id=' . $room_id);
    }

    return home_url('/match-chat?match_id=' . $request_id);
}

/**
 * Ajax / REST 承認成功レスポンスにモーダル用フラグを付与
 *
 * @param int   $request_id
 * @param array $payload
 * @return array
 */
function aidunite_onboarding_bot_extend_accept_response($request_id, array $payload) {
    $request_id = (int) $request_id;
    if ($request_id <= 0
        || !function_exists('aidunite_onboarding_bot_should_show_chat_modal_after_approve')
        || !aidunite_onboarding_bot_should_show_chat_modal_after_approve($request_id, get_current_user_id())) {
        return $payload;
    }

    $payload['redirect_url'] = '';
    $payload['show_onboarding_bot_chat_modal'] = true;
    $chat_url = aidunite_onboarding_bot_get_chat_url_for_request($request_id);
    if ($chat_url !== '') {
        $payload['chat_url'] = $chat_url;
    }

    return $payload;
}

/**
 * 申請状況一覧からボット行を除外（モーダル閉じた後）
 *
 * @param array $rows
 * @param int   $schedule_id
 * @param int   $team_id
 * @return array
 */
function aidunite_onboarding_bot_filter_established_rows($rows, $schedule_id, $team_id) {
    unset($schedule_id);
    if (!is_array($rows) || $rows === [] || !aidunite_onboarding_bot_should_hide_board_rows_for_team((int) $team_id)) {
        return $rows;
    }

    return array_values(array_filter($rows, static function ($row) {
        if (!is_array($row) || empty($row['request_id'])) {
            return true;
        }

        return !aidunite_match_request_is_onboarding_bot((int) $row['request_id']);
    }));
}

add_filter('aidunite_established_requests_for_schedule', 'aidunite_onboarding_bot_filter_established_rows', 10, 3);

/**
 * オンボーディング用ボット schedule を物理削除（MR 削除後に呼ぶ）
 *
 * @param int $schedule_id
 * @return bool
 */
function aidunite_onboarding_bot_delete_schedule_post($schedule_id) {
    $schedule_id = (int) $schedule_id;
    if ($schedule_id <= 0) {
        return true;
    }

    if (!aidunite_is_onboarding_bot_schedule($schedule_id)) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[aidunite onboarding bot] skip schedule delete (not bot schedule): ' . $schedule_id);
        }

        return false;
    }

    if (function_exists('aidunite_perform_safe_schedule_deletion')) {
        $result = aidunite_perform_safe_schedule_deletion($schedule_id);
        if (is_wp_error($result)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[aidunite onboarding bot] schedule delete failed: ' . $schedule_id . ' ' . $result->get_error_message());
            }

            return false;
        }

        return true;
    }

    if (function_exists('aidunite_can_delete_schedule')) {
        $may = aidunite_can_delete_schedule($schedule_id);
        if (is_wp_error($may)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[aidunite onboarding bot] schedule delete blocked: ' . $schedule_id . ' ' . $may->get_error_message());
            }

            return false;
        }
    }

    return (bool) wp_delete_post($schedule_id, true);
}

/**
 * 成立お祝いモーダル閉じた後: ボット MR とボット schedule を削除（ユーザー recruit は残す）
 *
 * @param int $user_team_id 代表者チーム
 * @return array{success:bool,deleted:bool,schedule_deleted?:bool,request_id?:int,bot_schedule_id?:int,reason?:string}
 */
function aidunite_onboarding_bot_remove_match_on_modal_close($user_team_id) {
    $user_team_id = (int) $user_team_id;
    if ($user_team_id <= 0 || !defined('AIDUNITE_TEAM_META_ONBOARDING_BOT_MR')) {
        return ['success' => false, 'deleted' => false, 'reason' => 'invalid_team'];
    }

    $request_id = (int) get_post_meta($user_team_id, AIDUNITE_TEAM_META_ONBOARDING_BOT_MR, true);
    $bot_schedule_id = defined('AIDUNITE_TEAM_META_ONBOARDING_BOT_SCHEDULE')
        ? (int) get_post_meta($user_team_id, AIDUNITE_TEAM_META_ONBOARDING_BOT_SCHEDULE, true)
        : 0;

    if ($request_id <= 0) {
        if ($bot_schedule_id > 0) {
            $schedule_deleted = aidunite_onboarding_bot_delete_schedule_post($bot_schedule_id);
            delete_post_meta($user_team_id, AIDUNITE_TEAM_META_ONBOARDING_BOT_SCHEDULE);

            return [
                'success'          => $schedule_deleted,
                'deleted'          => false,
                'schedule_deleted' => $schedule_deleted,
                'bot_schedule_id'  => $bot_schedule_id,
                'reason'           => $schedule_deleted ? 'no_mr' : 'schedule_delete_failed',
            ];
        }

        return ['success' => true, 'deleted' => false, 'reason' => 'no_mr'];
    }

    if (!aidunite_match_request_is_onboarding_bot($request_id)) {
        return ['success' => false, 'deleted' => false, 'reason' => 'not_bot_mr'];
    }

    $mr = function_exists('aidunite_match_request_get_canonical_meta')
        ? aidunite_match_request_get_canonical_meta($request_id)
        : [];
    $to_team_id = (int) ($mr['to_team_id'] ?? get_post_meta($request_id, 'to_team_id', true));
    $from_team_id = (int) ($mr['from_team_id'] ?? get_post_meta($request_id, 'from_team_id', true));
    if ($to_team_id !== $user_team_id && $from_team_id !== $user_team_id) {
        return ['success' => false, 'deleted' => false, 'reason' => 'forbidden'];
    }

    $to_schedule_id = (int) ($mr['to_schedule_id'] ?? get_post_meta($request_id, 'to_schedule_id', true));
    if ($bot_schedule_id <= 0) {
        $bot_schedule_id = (int) ($mr['from_schedule_id'] ?? get_post_meta($request_id, 'from_schedule_id', true));
        if ($bot_schedule_id <= 0) {
            $bot_schedule_id = (int) ($mr['my_schedule_id'] ?? get_post_meta($request_id, 'my_schedule_id', true));
        }
    }
    if ($bot_schedule_id > 0 && $bot_schedule_id === $to_schedule_id) {
        $bot_schedule_id = 0;
    }

    $was_established = (string) ($mr['established_at'] ?? get_post_meta($request_id, 'established_at', true)) !== '';

    aidunite_onboarding_bot_set_suppress_notifications(true);

    if ($was_established && function_exists('aidunite_rollback_established_from_schedules')) {
        aidunite_rollback_established_from_schedules($request_id);
    }

    if (function_exists('aidunite_update_match_request_status_meta')) {
        aidunite_update_match_request_status_meta($request_id, 'canceled', 'trash');
    }

    if (function_exists('aidunite_mark_match_chat_completed')) {
        aidunite_mark_match_chat_completed($request_id, 'onboarding_bot_modal_closed');
    }

    $deleted = function_exists('aidunite_match_request_persist_admin_delete')
        ? aidunite_match_request_persist_admin_delete($request_id)
        : (bool) wp_delete_post($request_id, true);

    delete_post_meta($user_team_id, AIDUNITE_TEAM_META_ONBOARDING_BOT_MR);
    if (defined('AIDUNITE_TEAM_META_ONBOARDING_BOT_SCHEDULE')) {
        delete_post_meta($user_team_id, AIDUNITE_TEAM_META_ONBOARDING_BOT_SCHEDULE);
    }

    $schedule_deleted = true;
    if ($deleted && $bot_schedule_id > 0) {
        $schedule_deleted = aidunite_onboarding_bot_delete_schedule_post($bot_schedule_id);
    }

    if ($to_schedule_id > 0 && function_exists('aidunite_sync_match_board_status_from_game')) {
        aidunite_sync_match_board_status_from_game($to_schedule_id);
    }

    aidunite_onboarding_bot_set_suppress_notifications(false);

    $reason = '';
    if (!$deleted) {
        $reason = 'mr_delete_failed';
    } elseif (!$schedule_deleted) {
        $reason = 'schedule_delete_failed';
    }

    return [
        'success'          => (bool) $deleted && $schedule_deleted,
        'deleted'          => (bool) $deleted,
        'schedule_deleted' => $schedule_deleted,
        'request_id'       => $request_id,
        'bot_schedule_id'  => $bot_schedule_id,
        'reason'           => $reason,
    ];
}

/**
 * REST: モーダル表示済み
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response
 */
function aidunite_rest_onboarding_bot_chat_modal_shown(WP_REST_Request $request) {
    $user_id = get_current_user_id();
    if ($user_id <= 0) {
        return new WP_REST_Response(['success' => false], 401);
    }

    $team_id = function_exists('aidunite_get_current_team_id')
        ? (int) aidunite_get_current_team_id($user_id)
        : (int) get_user_meta($user_id, 'team_id', true);

    if ($team_id <= 0) {
        return new WP_REST_Response(['success' => false, 'message' => 'team_not_found'], 400);
    }

    $cleanup = aidunite_onboarding_bot_remove_match_on_modal_close($team_id);
    if (($cleanup['reason'] ?? '') === 'forbidden') {
        return new WP_REST_Response(['success' => false, 'message' => 'forbidden'], 403);
    }
    if (($cleanup['reason'] ?? '') === 'not_bot_mr') {
        return new WP_REST_Response(['success' => false, 'message' => 'not_bot_mr'], 400);
    }
    if (empty($cleanup['success']) && ($cleanup['reason'] ?? '') !== 'no_mr') {
        return new WP_REST_Response(['success' => false, 'message' => 'cleanup_failed'], 500);
    }

    update_post_meta($team_id, AIDUNITE_TEAM_META_ONBOARDING_BOT_CHAT_MODAL_SHOWN, '1');
    delete_post_meta($team_id, AIDUNITE_TEAM_META_ONBOARDING_BOT_CHAT_MODAL_PENDING);

    return new WP_REST_Response([
        'success'              => true,
        'bot_mr_deleted'       => !empty($cleanup['deleted']),
        'bot_schedule_deleted' => !empty($cleanup['schedule_deleted']),
        'request_id'           => (int) ($cleanup['request_id'] ?? 0),
        'bot_schedule_id'      => (int) ($cleanup['bot_schedule_id'] ?? 0),
    ], 200);
}

add_action('rest_api_init', static function () {
    register_rest_route('aidunite/v1', '/onboarding-bot/chat-modal-shown', [
        'methods'             => 'POST',
        'callback'            => 'aidunite_rest_onboarding_bot_chat_modal_shown',
        'permission_callback' => static function () {
            return is_user_logged_in();
        },
    ]);
});

/**
 * 成立後モーダル用 CSS / JS を読み込む
 *
 * @param array $args show_modal (bool), chat_url (string)
 */
function aidunite_enqueue_onboarding_bot_chat_modal_assets(array $args = []) {
    $bot_modal_css = get_stylesheet_directory() . '/assets/css/components/onboarding-bot-chat-modal.css';
    if (is_readable($bot_modal_css)) {
        wp_enqueue_style(
            'aidunite-onboarding-bot-chat-modal',
            get_stylesheet_directory_uri() . '/assets/css/components/onboarding-bot-chat-modal.css',
            array('aidunite-style', 'card-style', 'button-style', 'aidunite-theme-icons'),
            (string) filemtime($bot_modal_css)
        );
    }

    $modal_js = get_stylesheet_directory() . '/assets/js/common/onboarding-bot-chat-modal.js';
    wp_enqueue_script(
        'aidunite-onboarding-bot-chat-modal',
        get_stylesheet_directory_uri() . '/assets/js/common/onboarding-bot-chat-modal.js',
        array(),
        is_readable($modal_js) ? (string) filemtime($modal_js) : '1.0.0',
        true
    );

    $recruit_url = function_exists('aidunite_get_activation_recruit_edit_url')
        ? aidunite_get_activation_recruit_edit_url()
        : home_url('/mypage/?open_recruit=1');

    wp_localize_script('aidunite-onboarding-bot-chat-modal', 'aiduniteOnboardingBotModal', [
        'restNonce'                  => wp_create_nonce('wp_rest'),
        'mypageUrl'                  => esc_url(home_url('/mypage/')),
        'recruitUrl'                 => esc_url($recruit_url),
        'showOnboardingBotChatModal' => !empty($args['show_modal']) ? '1' : '0',
        'chatUrl'                    => isset($args['chat_url']) ? esc_url((string) $args['chat_url']) : '',
    ]);
}

/**
 * 分析ファネル established からボット MR を除外する SQL 断片
 *
 * @return string
 */
function aidunite_analytics_sql_exclude_onboarding_bot_mr() {
    global $wpdb;

    return " AND NOT EXISTS (
        SELECT 1 FROM {$wpdb->postmeta} pm_ob
        WHERE pm_ob.post_id = p.ID
        AND pm_ob.meta_key = '" . esc_sql(AIDUNITE_ONBOARDING_BOT_META) . "'
        AND pm_ob.meta_value = '1'
    )";
}
