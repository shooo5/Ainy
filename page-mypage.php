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

if (isset($_GET['payment']) && sanitize_key((string) wp_unslash($_GET['payment'])) === 'success') {
    $redirect_args = ['payment' => 'success'];
    if (!empty($_GET['session_id'])) {
        $redirect_args['session_id'] = sanitize_text_field((string) wp_unslash($_GET['session_id']));
    }
    wp_safe_redirect(add_query_arg($redirect_args, home_url('/payment-setup')));
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
$mypage_team_id = 0;
if ($effective_role !== 'general' && function_exists('aidunite_get_current_team_id')) {
    $mypage_team_id = (int) aidunite_get_current_team_id($current_user_id);
}
$menu_items = aidunite_get_mypage_menu_v2($effective_role, $current_user_id, $mypage_team_id);

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
    if (strpos($title, '出欠') !== false) {
        $quick_icons['attendance'] = $item['url'];
    }
    if ($effective_role === 'parent' && strpos($title, '子供') !== false) {
        $quick_icons['children'] = $item['url'];
    }
    if ($effective_role === 'player' && strpos($title, '個人記録') !== false) {
        $quick_icons['stats'] = $item['url'];
    }
}

$mypage_schedule_url_php = home_url('/schedule-management');
$quick_icons['schedule'] = $mypage_schedule_url_php;
if (empty($quick_icons['chat'])) {
    $quick_icons['chat'] = home_url('/communication');
}
if (in_array($effective_role, ['team_leader', 'administrator'], true)) {
    $quick_icons['match'] = home_url('/match-board-own');
} else {
    unset($quick_icons['match']);
}
if (in_array($effective_role, ['parent', 'player'], true)) {
    $quick_icons['attendance'] = home_url('/attendance-report');
}
if ($effective_role === 'parent') {
    $quick_icons['children'] = home_url('/team-members');
}
if ($effective_role === 'player') {
    $quick_icons['stats'] = home_url('/player-stats');
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

$pending_sibling_teams     = [];
if ($effective_role === 'team_leader' && function_exists('aidunite_get_pending_sibling_teams_for_leader')) {
    $pending_sibling_teams = aidunite_get_pending_sibling_teams_for_leader((int) $current_user_id);
}

$parent_pending_context = [];
if ($effective_role === 'parent' && function_exists('aidunite_parent_get_pending_mypage_context')) {
    $parent_pending_context = aidunite_parent_get_pending_mypage_context((int) $current_user_id);
}
$show_parent_registered_banner = isset($_GET['registered']) && (string) $_GET['registered'] === '1';
$parent_pending_only_mypage = !empty($parent_pending_context['pending_only_mypage']);
$show_parent_pending_banner = (
    !$parent_pending_only_mypage
    && (
        !empty($parent_pending_context['has_pending'])
        || (isset($_GET['pending']) && (string) $_GET['pending'] === '1')
    )
);
$show_parent_pending_guard_notice = isset($_GET['pending_guard']) && (string) $_GET['pending_guard'] === '1';
$activation_stage          = '';
$activation_mission_ui     = false;
$activation_chat_unlocked  = true;
$recruit_edit_url          = function_exists('aidunite_get_activation_recruit_edit_url') ? aidunite_get_activation_recruit_edit_url() : home_url('/mypage/?open_recruit=1');
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

$use_mypage_joy = (
    !$is_general_mypage
    && !$parent_pending_only_mypage
    && function_exists('aidunite_mypage_is_joy_eligible_role')
    && aidunite_mypage_is_joy_eligible_role($effective_role)
);
$joy_show_leader_ui = ($effective_role === 'team_leader');
$is_mypage_member_role = in_array($effective_role, ['parent', 'player'], true);

if (!$is_general_mypage) {
    add_filter('body_class', function ($classes) use ($activation_mission_ui, $is_mypage_member_role, $use_mypage_joy) {
        if (!$use_mypage_joy) {
            return $classes;
        }
        $classes[] = 'mypage-joy-dashboard';
        if ($is_mypage_member_role) {
            $classes[] = 'mypage-joy-dashboard--member';
        }
        if ($activation_mission_ui) {
            $classes[] = 'mypage-joy-dashboard--activation-mission';
        }

        return $classes;
    }, 20);

    wp_enqueue_script('aidunite-date-utils', get_stylesheet_directory_uri() . '/assets/js/common/date-utils.js', array('aidunite-dom-utils'), '1.0.0', true);
    wp_enqueue_script('aidunite-schedule-utils', get_stylesheet_directory_uri() . '/assets/js/common/schedule-utils.js', array('aidunite-date-utils'), '1.0.0', true);
    wp_enqueue_script('aidunite-ajax-utils', get_stylesheet_directory_uri() . '/assets/js/common/ajax-utils.js', array('aidunite-dom-utils', 'loading-spinner-utils', 'aidunite-toast-notification'), '1.0.0', true);
    wp_localize_script('aidunite-ajax-utils', 'aiduniteScheduleRest', [
        'root'  => rest_url(),
        'nonce' => wp_create_nonce('wp_rest'),
    ]);
    wp_enqueue_script('aidunite-schedule-modal', get_stylesheet_directory_uri() . '/assets/js/common/schedule-modal.js', array('aidunite-schedule-utils', 'aidunite-ajax-utils'), '1.0.1', true);

    $mypage_bot_modal_deps = array('aidunite-date-utils', 'aidunite-schedule-utils', 'aidunite-schedule-modal', 'aidunite-toast-notification', 'aidunite-theme-icons');
    if (function_exists('aidunite_enqueue_onboarding_bot_chat_modal_assets')) {
        $bot_chat_url = '';
        if (!empty($act_team_id)) {
            $team_bundle = function_exists('aidunite_team_get_display_bundle')
                ? aidunite_team_get_display_bundle((int) $act_team_id)
                : [];
            $bot_mr = (int) ($team_bundle['onboarding_bot_mr_id'] ?? 0);
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

    if ($use_mypage_joy) {
        $mypage_joy_css = get_stylesheet_directory() . '/assets/css/pages/mypage-joy.css';
        wp_enqueue_style(
            'mypage-joy',
            get_stylesheet_directory_uri() . '/assets/css/pages/mypage-joy.css',
            array('aidunite-style', 'aidunite-team-theme', 'aidunite-web-app-integrated-ui', 'button-style'),
            is_readable($mypage_joy_css) ? (string) filemtime($mypage_joy_css) : '1.0.0'
        );
        if (function_exists('aidunite_enqueue_recruit_quick_modal_assets')) {
            aidunite_enqueue_recruit_quick_modal_assets();
        }
        // recruit クイックモーダルは team_leader のみ enqueue されるため、未登録時は依存に含めない
        $mypage_joy_script_deps = array_merge($mypage_bot_modal_deps, ['aidunite-schedule-utils']);
        if (wp_script_is('aidunite-schedule-quick-modal', 'registered')) {
            $mypage_joy_script_deps[] = 'aidunite-schedule-quick-modal';
        }
        $mypage_joy_js = get_stylesheet_directory() . '/assets/js/pages/mypage-dashboard-joy.js';
        wp_enqueue_script(
            'aidunite-mypage-dashboard-joy',
            get_stylesheet_directory_uri() . '/assets/js/pages/mypage-dashboard-joy.js',
            $mypage_joy_script_deps,
            is_readable($mypage_joy_js) ? (string) filemtime($mypage_joy_js) : '1.0.0',
            true
        );
        wp_localize_script('aidunite-mypage-dashboard-joy', 'aiduniteMypageJoy', [
            'restNonce'              => wp_create_nonce('wp_rest'),
            'scheduleUrl'            => esc_url($mypage_schedule_url_php),
            'matchBoardUrl'          => esc_url(home_url('/match-board-own')),
            'matchBoardMyUrl'        => esc_url(home_url('/match-board-own?market_tab=my')),
            'communicationUrl'       => esc_url($quick_icons['chat'] ?? home_url('/communication')),
            'attendanceUrl'          => esc_url($quick_icons['attendance'] ?? home_url('/attendance-report')),
            'effectiveRole'          => esc_attr($effective_role),
            'isMemberRole'           => $is_mypage_member_role ? '1' : '0',
            'activationStage'        => esc_attr($activation_stage),
            'activationMissionUi'    => $activation_mission_ui ? '1' : '0',
            'activationChatUnlocked' => $activation_chat_unlocked ? '1' : '0',
            'recruitEditUrl'         => esc_url($recruit_edit_url),
            'showOnboardingBotChatModal' => !empty($show_onboarding_bot_chat_modal) ? '1' : '0',
        ]);
    }

    if ($mypage_team_id > 0 && $effective_role === 'team_leader') {
        $first_match_css = get_stylesheet_directory() . '/assets/css/components/payment-first-match-prompt.css';
        wp_enqueue_style(
            'aidunite-payment-first-match-prompt',
            get_stylesheet_directory_uri() . '/assets/css/components/payment-first-match-prompt.css',
            ['aidunite-style'],
            is_readable($first_match_css) ? (string) filemtime($first_match_css) : '1.0.0'
        );
        $first_match_js = get_stylesheet_directory() . '/assets/js/payment/payment-first-match-prompt.js';
        wp_enqueue_script(
            'aidunite-payment-first-match-prompt',
            get_stylesheet_directory_uri() . '/assets/js/payment/payment-first-match-prompt.js',
            [],
            is_readable($first_match_js) ? (string) filemtime($first_match_js) : '1.0.0',
            true
        );
        wp_localize_script('aidunite-payment-first-match-prompt', 'aidunitePaymentFirstMatch', [
            'teamId' => $mypage_team_id,
            'restNonce' => wp_create_nonce('wp_rest'),
            'heroImageUrl' => function_exists('aidunite_payment_exit_get_first_match_hero_image_url')
                ? aidunite_payment_exit_get_first_match_hero_image_url()
                : '',
        ]);
    }
}

if (
    (
        $show_parent_registered_banner
        || $show_parent_pending_banner
        || $parent_pending_only_mypage
        || $show_parent_pending_guard_notice
    )
    && function_exists('wp_enqueue_style')
) {
    wp_enqueue_style(
        'guardian-parent-ui',
        get_stylesheet_directory_uri() . '/assets/css/pages/guardian-parent-ui.css',
        array('aidunite-style'),
        '1.0.0'
    );
}

if ($parent_pending_only_mypage && function_exists('wp_enqueue_style')) {
    $general_pending_css = get_stylesheet_directory() . '/assets/css/pages/mypage-general-landing.css';
    wp_enqueue_style(
        'mypage-general-pending',
        get_stylesheet_directory_uri() . '/assets/css/pages/mypage-general-landing.css',
        array('aidunite-style', 'guardian-parent-ui'),
        is_readable($general_pending_css) ? (string) filemtime($general_pending_css) : '1.0.0'
    );
}

if ($is_general_mypage && $general_mypage_state === 'pending') {
    add_filter('body_class', static function ($classes) {
        $classes[] = 'mypage-joy-dashboard';
        $classes[] = 'mypage-joy-dashboard--general-pending';

        return $classes;
    }, 25);
}

get_header();
?>

<div class="team-dashboard-container page-mypage<?php
echo $is_general_mypage ? ' page-mypage--general' : '';
echo ($is_general_mypage && $general_mypage_state === 'pending') ? ' page-mypage--general-pending' : '';
echo ($is_general_mypage && $general_mypage_state === 'needs_revision') ? ' page-mypage--general-needs-revision' : '';
echo ($is_general_mypage && $general_mypage_state === 'before_apply') ? ' page-mypage--general-before' : '';
echo ($effective_role === 'parent' && $parent_pending_only_mypage) ? ' page-mypage--parent-pending' : '';
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
    <?php elseif ($effective_role === 'parent' && $parent_pending_only_mypage) : ?>
        <?php
        get_template_part(
            'template-parts/mypage',
            'parent-pending',
            [
                'context' => $parent_pending_context,
                'show_registered_notice' => $show_parent_registered_banner,
                'show_guard_notice' => $show_parent_pending_guard_notice,
            ]
        );
        ?>
    <?php else : ?>
        <?php if ($show_parent_registered_banner && !$show_parent_pending_banner) : ?>
        <div class="guardian-flow-alert guardian-flow-alert--success" role="status" style="margin-bottom: 1rem;">
            保護者登録が完了しました。チームの機能をご利用いただけます。
        </div>
        <?php elseif ($show_parent_pending_banner) : ?>
        <div class="guardian-flow-alert guardian-flow-alert--pending" role="status" style="margin-bottom: 1rem;">
            <strong>保護者登録ありがとうございます</strong><br>
            現在、チーム代表者の承認待ちです。承認が完了するとご利用いただけます。
            <?php if (!empty($parent_pending_context['teams'])) : ?>
            <ul style="margin: 0.5rem 0 0 1.1rem;">
                <?php foreach ($parent_pending_context['teams'] as $pending_team) : ?>
                <li><?php echo esc_html($pending_team['team']['team_name'] ?? 'チーム'); ?></li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>
        <?php elseif ($show_parent_pending_guard_notice) : ?>
        <div class="guardian-flow-alert guardian-flow-alert--pending" role="status" style="margin-bottom: 1rem;">
            承認が完了するまで、スケジュール・連絡・出欠などの機能はご利用いただけません。
        </div>
        <?php endif; ?>
        <?php
        $dashboard_args = [
            'effective_role'           => $effective_role,
            'current_user_id'          => $current_user_id,
            'user'                     => $user,
            'user_info'                => $user_info,
            'quick_icons'              => $quick_icons,
            'mypage_schedule_url'      => $mypage_schedule_url_php,
            'is_developer'             => $is_developer,
            'activation_stage'         => $activation_stage,
            'activation_mission_ui'    => $activation_mission_ui,
            'activation_chat_unlocked' => $activation_chat_unlocked,
            'recruit_edit_url'         => $recruit_edit_url,
            'match_board_url'          => home_url('/match-board-own'),
            'pending_sibling_teams'    => $pending_sibling_teams,
            'communication_url'        => $quick_icons['chat'] ?? home_url('/communication'),
            'attendance_url'           => $quick_icons['attendance'] ?? home_url('/attendance-report'),
            'joy_show_leader_ui'       => $joy_show_leader_ui,
            'is_member_role'           => $is_mypage_member_role,
        ];
        if ($use_mypage_joy) {
            get_template_part('template-parts/mypage', 'dashboard-joy', $dashboard_args);
        }
        ?>
    <?php endif; ?>

</div>

<?php get_footer(); ?>
