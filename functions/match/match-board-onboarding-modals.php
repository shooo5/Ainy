<?php
/**
 * 試合掲示板オンボーディングモーダル（申請状況 / 募集中タブ）
 *
 * @package AidUnite
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('AIDUNITE_TEAM_META_BOARD_MY_INTRO_SHOWN')) {
    define('AIDUNITE_TEAM_META_BOARD_MY_INTRO_SHOWN', 'aidunite_onboarding_board_my_intro_shown');
}

if (!defined('AIDUNITE_TEAM_META_BOARD_RECRUIT_INTRO_SHOWN')) {
    define('AIDUNITE_TEAM_META_BOARD_RECRUIT_INTRO_SHOWN', 'aidunite_onboarding_board_recruit_intro_shown');
}

if (!defined('AIDUNITE_TEAM_META_BOARD_BOT_ARRIVED_SHOWN')) {
    define('AIDUNITE_TEAM_META_BOARD_BOT_ARRIVED_SHOWN', 'aidunite_onboarding_board_bot_arrived_shown');
}

if (!defined('AIDUNITE_TEAM_META_BOARD_BOT_APPROVE_INTRO_SHOWN')) {
    define('AIDUNITE_TEAM_META_BOARD_BOT_APPROVE_INTRO_SHOWN', 'aidunite_onboarding_board_bot_approve_intro_shown');
}

/**
 * @param int $team_id
 * @return bool
 */
function aidunite_board_onboarding_mission_active($team_id) {
    $team_id = (int) $team_id;
    if ($team_id <= 0) {
        return false;
    }
    if (!function_exists('aidunite_activation_is_mission_ui') || !aidunite_activation_is_mission_ui($team_id)) {
        return false;
    }
    $stage = function_exists('aidunite_get_team_activation_stage')
        ? (string) aidunite_get_team_activation_stage($team_id)
        : '';

    return in_array($stage, ['recruit_published', 'first_application'], true);
}

/**
 * @param int $team_id
 * @return bool
 */
function aidunite_board_onboarding_bot_mr_pending($team_id) {
    $team_id = (int) $team_id;
    if ($team_id <= 0 || !defined('AIDUNITE_TEAM_META_ONBOARDING_BOT_MR')) {
        return false;
    }
    $mr_id = (int) get_post_meta($team_id, AIDUNITE_TEAM_META_ONBOARDING_BOT_MR, true);
    if ($mr_id <= 0 || !function_exists('aidunite_match_request_is_onboarding_bot')
        || !aidunite_match_request_is_onboarding_bot($mr_id)) {
        return false;
    }
    $st = function_exists('aidunite_normalize_match_request_status')
        ? aidunite_normalize_match_request_status((string) get_post_meta($mr_id, 'status', true), '')
        : strtolower((string) get_post_meta($mr_id, 'status', true));

    return $st === 'pending';
}

/**
 * @param int    $team_id
 * @param string $step my_intro|recruit_intro|bot_arrived|bot_approve
 * @return bool
 */
function aidunite_board_onboarding_modal_step_shown($team_id, $step) {
    $team_id = (int) $team_id;
    $map     = [
        'my_intro'      => AIDUNITE_TEAM_META_BOARD_MY_INTRO_SHOWN,
        'recruit_intro' => AIDUNITE_TEAM_META_BOARD_RECRUIT_INTRO_SHOWN,
        'bot_arrived'   => AIDUNITE_TEAM_META_BOARD_BOT_ARRIVED_SHOWN,
        'bot_approve'   => AIDUNITE_TEAM_META_BOARD_BOT_APPROVE_INTRO_SHOWN,
    ];
    if (!isset($map[$step])) {
        return true;
    }

    return get_post_meta($team_id, $map[$step], true) === '1';
}

/**
 * @param int    $team_id
 * @param string $step
 */
function aidunite_board_onboarding_mark_modal_step_shown($team_id, $step) {
    $team_id = (int) $team_id;
    $map     = [
        'my_intro'      => AIDUNITE_TEAM_META_BOARD_MY_INTRO_SHOWN,
        'recruit_intro' => AIDUNITE_TEAM_META_BOARD_RECRUIT_INTRO_SHOWN,
        'bot_arrived'   => AIDUNITE_TEAM_META_BOARD_BOT_ARRIVED_SHOWN,
        'bot_approve'   => AIDUNITE_TEAM_META_BOARD_BOT_APPROVE_INTRO_SHOWN,
    ];
    if ($team_id <= 0 || !isset($map[$step])) {
        return;
    }
    update_post_meta($team_id, $map[$step], '1');
}

/**
 * 掲示板 JS 向けオンボーディング状態
 *
 * @param int $team_id
 * @return array<string, mixed>
 */
function aidunite_board_onboarding_modals_payload($team_id) {
    $team_id = (int) $team_id;
    $active  = aidunite_board_onboarding_mission_active($team_id);

    if (!$active) {
        return [
            'active' => false,
        ];
    }

    if (function_exists('aidunite_onboarding_bot_ensure_for_team')) {
        aidunite_onboarding_bot_ensure_for_team($team_id);
    }

    $bot_mr_id       = defined('AIDUNITE_TEAM_META_ONBOARDING_BOT_MR')
        ? (int) get_post_meta($team_id, AIDUNITE_TEAM_META_ONBOARDING_BOT_MR, true)
        : 0;
    $bot_schedule_id = defined('AIDUNITE_TEAM_META_ONBOARDING_BOT_SCHEDULE')
        ? (int) get_post_meta($team_id, AIDUNITE_TEAM_META_ONBOARDING_BOT_SCHEDULE, true)
        : 0;
    $first_recruit   = defined('AIDUNITE_TEAM_META_FIRST_RECRUIT_SCHEDULE_ID')
        ? (int) get_post_meta($team_id, AIDUNITE_TEAM_META_FIRST_RECRUIT_SCHEDULE_ID, true)
        : 0;
    $recruit_intro_shown = aidunite_board_onboarding_modal_step_shown($team_id, 'recruit_intro');

    $gender_theme = '';
    if (function_exists('aidunite_normalize_team_gender_option')) {
        $gender_raw = aidunite_normalize_team_gender_option((string) get_post_meta($team_id, 'team_gender_option', true));
        if (in_array($gender_raw, ['male', 'female'], true)) {
            $gender_theme = $gender_raw;
        }
    }

    return [
        'active'              => true,
        'genderTheme'         => $gender_theme,
        'stage'               => function_exists('aidunite_get_team_activation_stage')
            ? (string) aidunite_get_team_activation_stage($team_id)
            : '',
        'showMyIntro'         => !aidunite_board_onboarding_modal_step_shown($team_id, 'my_intro'),
        'showRecruitIntro'    => !$recruit_intro_shown,
        'showBotApproveIntro' => aidunite_board_onboarding_modal_step_shown($team_id, 'bot_arrived')
            && !aidunite_board_onboarding_modal_step_shown($team_id, 'bot_approve'),
        'botMrId'             => $bot_mr_id,
        'botScheduleId'       => $bot_schedule_id,
        'firstRecruitScheduleId' => $first_recruit,
    ];
}

/**
 * @param WP_REST_Request $request
 * @return WP_REST_Response
 */
function aidunite_rest_board_onboarding_modal_shown(WP_REST_Request $request) {
    $user_id = get_current_user_id();
    if ($user_id <= 0) {
        return new WP_REST_Response(['success' => false], 401);
    }

    $team_id = function_exists('aidunite_match_board_resolve_viewer_team_id')
        ? (int) aidunite_match_board_resolve_viewer_team_id($user_id)
        : (int) get_user_meta($user_id, 'team_id', true);
    if ($team_id <= 0) {
        return new WP_REST_Response(['success' => false, 'message' => 'team_not_found'], 400);
    }

    $step = sanitize_key((string) $request->get_param('step'));
    $allowed = ['my_intro', 'recruit_intro', 'bot_arrived', 'bot_approve'];
    if (!in_array($step, $allowed, true)) {
        return new WP_REST_Response(['success' => false, 'message' => 'invalid_step'], 400);
    }

    aidunite_board_onboarding_mark_modal_step_shown($team_id, $step);

    if ($step === 'recruit_intro' && function_exists('aidunite_onboarding_bot_ensure_for_team')) {
        aidunite_onboarding_bot_ensure_for_team($team_id);
    }

    return new WP_REST_Response([
        'success' => true,
        'payload' => aidunite_board_onboarding_modals_payload($team_id),
    ], 200);
}

add_action('rest_api_init', static function () {
    register_rest_route('aidunite/v1', '/onboarding-board/modal-shown', [
        'methods'             => 'POST',
        'callback'            => 'aidunite_rest_board_onboarding_modal_shown',
        'permission_callback' => static function () {
            return is_user_logged_in();
        },
        'args'                => [
            'step' => [
                'required'          => true,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_key',
            ],
        ],
    ]);
});

/**
 * オンボーディングモーダル CSS / JS
 */
function aidunite_enqueue_board_onboarding_modal_assets() {
    if (!function_exists('aidunite_enqueue_onboarding_bot_chat_modal_assets')) {
        return;
    }
    aidunite_enqueue_onboarding_bot_chat_modal_assets(['show_modal' => false]);

    $js = get_stylesheet_directory() . '/assets/js/pages/match-board-onboarding-modals.js';
    if (!is_readable($js)) {
        return;
    }

    wp_enqueue_script(
        'aidunite-match-board-onboarding-modals',
        get_stylesheet_directory_uri() . '/assets/js/pages/match-board-onboarding-modals.js',
        array('aidunite-match-board-page', 'aidunite-onboarding-bot-chat-modal', 'aidunite-theme-icons'),
        (string) filemtime($js),
        true
    );
}
