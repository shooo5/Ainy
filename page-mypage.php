<?php
/*
Template Name: マイページ
*/

// 統一認証・権限チェック
try {
    require_once get_template_directory() . '/functions/common/auth-middleware.php';
    $auth_result = AidUniteAuthMiddleware::require_auth(true);
    if (!$auth_result->is_valid()) {
        return;
    }
} catch (Exception $e) {
    error_log('マイページ認証エラー: ' . $e->getMessage());
    wp_redirect(home_url('/login'));
    exit;
} catch (Error $e) {
    error_log('マイページ認証Fatal Error: ' . $e->getMessage());
    wp_redirect(home_url('/login'));
    exit;
}

try {
    list($effective_role, $preview_mode) = aidunite_get_effective_user_role();
    $current_user_id = get_current_user_id();
    $user_info = aidunite_get_user_info($current_user_id);
    $user = wp_get_current_user();
} catch (Exception $e) {
    error_log('マイページユーザー情報取得エラー: ' . $e->getMessage());
    $effective_role = 'general';
    $preview_mode = false;
    $current_user_id = get_current_user_id();
    $user_info = [];
    $user = wp_get_current_user();
}

$is_developer = current_user_can('manage_options');
$menu_items = aidunite_get_mypage_menu_v2($effective_role);

$quick_icons = [];
foreach ($menu_items as $item) {
    $title = $item['title'] ?? '';
    if (strpos($title, 'スケジュール') !== false || strpos($title, 'コミュニケーション') !== false || strpos($title, '試合一覧') !== false) {
        if (strpos($title, 'スケジュール') !== false) {
            $quick_icons['schedule'] = $item['url'];
        } elseif (strpos($title, 'コミュニケーション') !== false) {
            $quick_icons['chat'] = $item['url'];
        } elseif (strpos($title, '試合一覧') !== false) {
            $quick_icons['match'] = $item['url'];
        }
    }
}

$mypage_schedule_url_php = ($effective_role === 'team_leader') ? home_url('/schedule-management') : home_url('/schedule-list');
$quick_icons['schedule'] = $mypage_schedule_url_php;
if (empty($quick_icons['chat'])) {
    $quick_icons['chat'] = home_url('/communication');
}
if (!empty($quick_icons['match'])) {
    $quick_icons['match'] = home_url('/match-board-own');
}

$is_general_mypage = ($effective_role === 'general');
$team_registration_url = home_url('/team-registration');
$general_mypage_state = 'before_apply';
$general_pending_context = [];
$general_needs_revision_context = [];

if ($is_general_mypage && function_exists('aidunite_mypage_general_state')) {
    $general_mypage_state = aidunite_mypage_general_state($current_user_id);
    if ($general_mypage_state === 'pending' && function_exists('aidunite_mypage_general_pending_context')) {
        $general_pending_context = aidunite_mypage_general_pending_context($current_user_id);
    }
    if ($general_mypage_state === 'needs_revision' && function_exists('aidunite_mypage_general_needs_revision_context')) {
        $general_needs_revision_context = aidunite_mypage_general_needs_revision_context($current_user_id);
    }
}

$tournament_participations = [];
$pending_sibling_teams     = [];
if ($effective_role === 'team_leader' && function_exists('aidunite_get_pending_sibling_teams_for_leader')) {
    $pending_sibling_teams = aidunite_get_pending_sibling_teams_for_leader((int) $current_user_id);
}
$activation_stage          = '';
$activation_mission_ui     = false;
$activation_chat_unlocked  = true;
$recruit_edit_url          = function_exists('aidunite_get_activation_recruit_edit_url') ? aidunite_get_activation_recruit_edit_url() : home_url('/schedule-edit/');
$act_team_id               = 0;

if (!$is_general_mypage && $effective_role === 'team_leader') {
    $act_team_id = isset($user_info['team_id']) ? (int) $user_info['team_id'] : 0;
    if ($act_team_id <= 0 && function_exists('aidunite_get_current_team_id')) {
        $act_team_id = (int) aidunite_get_current_team_id($current_user_id);
    }
    if ($act_team_id > 0 && function_exists('aidunite_get_team_activation_stage')) {
        $activation_stage         = aidunite_get_team_activation_stage($act_team_id);
        $activation_mission_ui    = function_exists('aidunite_activation_is_mission_ui')
            ? aidunite_activation_is_mission_ui($act_team_id)
            : false;
        $activation_chat_unlocked = function_exists('aidunite_activation_is_chat_unlocked')
            ? aidunite_activation_is_chat_unlocked($act_team_id)
            : true;
    }
}

$show_onboarding_bot_chat_modal = false;
if (!$is_general_mypage && $effective_role === 'team_leader' && !empty($act_team_id)
    && function_exists('aidunite_onboarding_bot_should_show_chat_modal_on_mypage')) {
    $show_onboarding_bot_chat_modal = aidunite_onboarding_bot_should_show_chat_modal_on_mypage((int) $act_team_id);
}

if (!$is_general_mypage && function_exists('aidunite_get_managed_team_ids') && function_exists('aidunite_get_team_tournament_participations')) {
    $seen_urls = [];
    foreach (aidunite_get_managed_team_ids((int) $current_user_id) as $tid) {
        $tid = (int) $tid;
        if ($tid <= 0) {
            continue;
        }
        $parts = aidunite_get_team_tournament_participations($tid, 10);
        foreach ((array) $parts as $part) {
            $u = isset($part['url']) ? (string) $part['url'] : '';
            if ($u !== '' && isset($seen_urls[$u])) {
                continue;
            }
            if ($u !== '') {
                $seen_urls[$u] = true;
            }
            $tournament_participations[] = $part;
        }
    }
    $tournament_participations = array_slice($tournament_participations, 0, 20);
}

if (!$is_general_mypage) {
    add_filter('body_class', function ($classes) use ($activation_mission_ui, $activation_stage) {
        $classes[] = 'mypage-v2-dashboard';
        if ($activation_mission_ui) {
            $classes[] = 'mypage-v2--activation-mission';
        } elseif ($activation_stage === 'first_established') {
            $classes[] = 'mypage-v2--activation-established';
        }
        return $classes;
    }, 20);

    $mypage_redesign_css = get_stylesheet_directory() . '/assets/css/pages/mypage-redesign.css';
    wp_enqueue_style(
        'mypage-redesign',
        get_stylesheet_directory_uri() . '/assets/css/pages/mypage-redesign.css',
        array('aidunite-style', 'aidunite-team-theme', 'aidunite-web-app-integrated-ui', 'card-style', 'button-style'),
        is_readable($mypage_redesign_css) ? (string) filemtime($mypage_redesign_css) : '1.3.9'
    );
    wp_enqueue_script('aidunite-date-utils', get_stylesheet_directory_uri() . '/assets/js/common/date-utils.js', array('aidunite-dom-utils'), '1.0.0', true);
    wp_enqueue_script('aidunite-schedule-utils', get_stylesheet_directory_uri() . '/assets/js/common/schedule-utils.js', array('aidunite-date-utils'), '1.0.0', true);
    wp_enqueue_script('aidunite-ajax-utils', get_stylesheet_directory_uri() . '/assets/js/common/ajax-utils.js', array('aidunite-dom-utils', 'loading-spinner-utils', 'aidunite-toast-notification'), '1.0.0', true);
    wp_localize_script('aidunite-ajax-utils', 'aiduniteScheduleRest', [
        'root'  => rest_url(),
        'nonce' => wp_create_nonce('wp_rest'),
    ]);
    wp_enqueue_script('aidunite-schedule-modal', get_stylesheet_directory_uri() . '/assets/js/common/schedule-modal.js', array('aidunite-schedule-utils', 'aidunite-ajax-utils'), '1.0.1', true);
    $mypage_dashboard_js = get_stylesheet_directory() . '/assets/js/pages/mypage-dashboard.js';
    $mypage_bot_modal_deps = array('aidunite-date-utils', 'aidunite-schedule-utils', 'aidunite-schedule-modal', 'aidunite-toast-notification', 'aidunite-theme-icons');
    if (function_exists('aidunite_enqueue_onboarding_bot_chat_modal_assets')) {
        $bot_chat_url = '';
        if (!empty($act_team_id) && defined('AIDUNITE_TEAM_META_ONBOARDING_BOT_MR')) {
            $bot_mr = (int) get_post_meta((int) $act_team_id, AIDUNITE_TEAM_META_ONBOARDING_BOT_MR, true);
            if ($bot_mr > 0 && function_exists('aidunite_onboarding_bot_get_chat_url_for_request')) {
                $bot_chat_url = aidunite_onboarding_bot_get_chat_url_for_request($bot_mr);
            }
        }
        aidunite_enqueue_onboarding_bot_chat_modal_assets([
            'show_modal' => !empty($show_onboarding_bot_chat_modal),
            'chat_url'   => $bot_chat_url,
        ]);
        $mypage_bot_modal_deps[] = 'aidunite-onboarding-bot-chat-modal';
    }

    wp_enqueue_script(
        'aidunite-mypage-dashboard',
        get_stylesheet_directory_uri() . '/assets/js/pages/mypage-dashboard.js',
        $mypage_bot_modal_deps,
        is_readable($mypage_dashboard_js) ? (string) filemtime($mypage_dashboard_js) : '1.2.1',
        true
    );
    wp_localize_script('aidunite-mypage-dashboard', 'aiduniteMypageDashboard', [
        'restNonce'           => wp_create_nonce('wp_rest'),
        'scheduleUrl'         => esc_url($mypage_schedule_url_php),
        'matchBoardUrl'       => esc_url(home_url('/match-board-own')),
        'communicationUrl'    => esc_url($quick_icons['chat'] ?? home_url('/communication')),
        'tournamentsUrl'      => esc_url(get_permalink(get_page_by_path('tournaments')) ?: home_url('/tournaments/')),
        'activationStage'     => esc_attr($activation_stage),
        'activationMissionUi' => $activation_mission_ui ? '1' : '0',
        'activationChatUnlocked' => $activation_chat_unlocked ? '1' : '0',
        'recruitEditUrl'      => esc_url($recruit_edit_url),
        'activationLockMessage' => function_exists('aidunite_activation_lock_message') && !empty($user_info['team_id'])
            ? esc_attr(aidunite_activation_lock_message((int) $user_info['team_id']))
            : '',
        'showOnboardingBotChatModal' => !empty($show_onboarding_bot_chat_modal) ? '1' : '0',
    ]);
}

get_header();
?>

<div class="team-dashboard-container page-mypage<?php
echo $is_general_mypage ? ' page-mypage--general' : '';
echo ($is_general_mypage && $general_mypage_state === 'pending') ? ' page-mypage--general-pending' : '';
echo ($is_general_mypage && $general_mypage_state === 'needs_revision') ? ' page-mypage--general-needs-revision' : '';
echo ($is_general_mypage && $general_mypage_state === 'before_apply') ? ' page-mypage--general-before' : '';
?>">

    <?php if ($is_general_mypage && $general_mypage_state === 'pending') : ?>
        <?php
        get_template_part(
            'template-parts/mypage',
            'general-pending',
            [
                'context' => $general_pending_context,
            ]
        );
        ?>
    <?php elseif ($is_general_mypage && $general_mypage_state === 'needs_revision') : ?>
        <?php
        get_template_part(
            'template-parts/mypage',
            'general-needs-revision',
            [
                'context' => $general_needs_revision_context,
            ]
        );
        ?>
    <?php elseif ($is_general_mypage) : ?>
        <?php
        get_template_part(
            'template-parts/mypage',
            'general-landing',
            [
                'team_registration_url' => $team_registration_url,
            ]
        );
        ?>
    <?php else : ?>
        <?php
        get_template_part(
            'template-parts/mypage',
            'dashboard',
            [
                'effective_role'            => $effective_role,
                'current_user_id'           => $current_user_id,
                'user'                      => $user,
                'user_info'                 => $user_info,
                'quick_icons'               => $quick_icons,
                'tournament_participations' => $tournament_participations,
                'mypage_schedule_url'       => $mypage_schedule_url_php,
                'is_developer'              => $is_developer,
                'activation_stage'          => $activation_stage,
                'activation_mission_ui'     => $activation_mission_ui,
                'activation_chat_unlocked'  => $activation_chat_unlocked,
                'recruit_edit_url'          => $recruit_edit_url,
                'match_board_url'           => home_url('/match-board-own'),
                'pending_sibling_teams'     => $pending_sibling_teams,
            ]
        );
        ?>
    <?php endif; ?>

</div>

<?php get_footer(); ?>
