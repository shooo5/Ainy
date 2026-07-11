<?php
/**
 * マイページ Joy UI（試合決定・安心感中心）
 *
 * @package AidUnite
 */

if (!defined('ABSPATH')) {
    exit;
}

$effective_role          = isset($args['effective_role']) ? (string) $args['effective_role'] : 'general';
$current_user_id         = isset($args['current_user_id']) ? (int) $args['current_user_id'] : get_current_user_id();
$user                    = isset($args['user']) && $args['user'] instanceof WP_User ? $args['user'] : wp_get_current_user();
$mypage_schedule_url     = isset($args['mypage_schedule_url']) ? (string) $args['mypage_schedule_url'] : home_url('/schedule-management');
$user_info               = isset($args['user_info']) && is_array($args['user_info']) ? $args['user_info'] : [];
$match_board_url         = isset($args['match_board_url']) ? (string) $args['match_board_url'] : home_url('/match-board-own');
$match_board_recruit_url = $match_board_url . (strpos($match_board_url, '?') === false ? '?' : '&') . 'market_tab=recruit';
$match_board_my_url      = $match_board_url . (strpos($match_board_url, '?') === false ? '?' : '&') . 'market_tab=my';
$communication_url       = isset($args['communication_url']) ? (string) $args['communication_url'] : home_url('/communication');
$pending_sibling_teams   = isset($args['pending_sibling_teams']) && is_array($args['pending_sibling_teams'])
    ? $args['pending_sibling_teams']
    : [];
$activation_mission_ui   = !empty($args['activation_mission_ui']);
$activation_stage        = isset($args['activation_stage']) ? (string) $args['activation_stage'] : '';
$recruit_edit_url        = isset($args['recruit_edit_url']) ? (string) $args['recruit_edit_url'] : home_url('/mypage/?open_recruit=1');
$mission_schedule_url    = $activation_stage === 'recruit_pending'
    ? $recruit_edit_url
    : $mypage_schedule_url;

$team_name = '';
$team_logo = '';
$team_id   = isset($user_info['team_id']) ? (int) $user_info['team_id'] : 0;
if ($team_id > 0) {
    if (function_exists('aidunite_get_team_name')) {
        $team_name = (string) aidunite_get_team_name($team_id);
    }
    $team_bundle = function_exists('aidunite_team_get_display_bundle')
        ? aidunite_team_get_display_bundle($team_id)
        : [];
    $team_logo = (string) ($team_bundle['team_logo'] ?? '');
    if ($team_name === '' && $team_bundle !== []) {
        $team_name = (string) ($team_bundle['team_name'] ?? '');
    }
}

$notification_count = function_exists('aidunite_get_notification_count')
    ? (int) aidunite_get_notification_count($current_user_id)
    : 0;

$joy_show_leader_ui = !empty($args['joy_show_leader_ui']);
$show_leader_quick  = ($effective_role === 'team_leader') || $joy_show_leader_ui;
$is_member_role     = !empty($args['is_member_role']) || in_array($effective_role, ['parent', 'player'], true);
$show_member_quick  = $is_member_role && !$show_leader_quick;
$attendance_url     = isset($args['attendance_url']) ? (string) $args['attendance_url'] : home_url('/attendance-report');
$team_logo_valid    = function_exists('aidunite_team_logo_is_displayable')
    ? aidunite_team_logo_is_displayable($team_logo)
    : ($team_logo !== '' && filter_var($team_logo, FILTER_VALIDATE_URL));
$joy_root_class     = 'mypage-joy' . ($is_member_role ? ' mypage-joy--member' : '');
?>

<div class="<?php echo esc_attr($joy_root_class); ?>" id="mypage-joy-root" data-mypage-ui="joy" data-effective-role="<?php echo esc_attr($effective_role); ?>">

    <header class="ainy-webapp-hero ainy-webapp-hero--lg mypage-joy-hero" aria-label="マイページヘッダー">
        <?php
        if (function_exists('aidunite_render_web_app_integrated_header_bar')) {
            aidunite_render_web_app_integrated_header_bar([
                'active_nav'         => 'mypage',
                'notification_count' => $notification_count,
            ]);
        }
        ?>

        <div class="mypage-joy-profile">
            <div class="mypage-joy-profile__logo-wrap">
                <?php if ($team_logo_valid) : ?>
                    <img class="mypage-joy-profile__logo" src="<?php echo esc_url($team_logo); ?>" alt="<?php echo esc_attr($team_name !== '' ? $team_name . 'のロゴ' : 'チームロゴ'); ?>" width="88" height="88" loading="lazy" />
                <?php else : ?>
                    <div class="mypage-joy-profile__logo mypage-joy-profile__logo--placeholder" aria-hidden="true">
                        <?php
                        if (function_exists('aidunite_get_theme_icon_svg')) {
                            echo aidunite_get_theme_icon_svg('stadium', ['width' => '40', 'height' => '40']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        }
                        ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="mypage-joy-profile__text">
                <?php if ($team_name !== '') : ?>
                    <h1 class="mypage-joy-profile__team"><?php echo esc_html($team_name); ?></h1>
                <?php endif; ?>
                <p class="mypage-joy-profile__login"><?php echo esc_html($user->display_name); ?></p>
            </div>
        </div>
    </header>

    <main class="ainy-webapp-content mypage-joy-content">
        <div class="mypage-joy-stack">

            <?php if ($pending_sibling_teams !== []) : ?>
            <section class="mypage-joy-pending-sibling" role="status" aria-labelledby="mypage-joy-pending-sibling-heading">
                <h2 id="mypage-joy-pending-sibling-heading" class="mypage-joy-pending-sibling__title">承認待ちのチームがあります</h2>
                <ul class="mypage-joy-pending-sibling__list">
                    <?php foreach ($pending_sibling_teams as $pending_team) : ?>
                        <?php if (!is_array($pending_team)) {
                            continue;
                        } ?>
                        <li class="mypage-joy-pending-sibling__item">
                            <span class="mypage-joy-pending-sibling__badge">運営チームが確認中</span>
                            <span class="mypage-joy-pending-sibling__name"><?php echo esc_html((string) ($pending_team['team_name'] ?? 'チーム')); ?></span>
                            <span class="mypage-joy-pending-sibling__note">通常 <strong>1〜2営業日以内</strong> にご連絡します</span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
            <?php endif; ?>

            <div id="mypage-joy-hero" class="mypage-joy-hero-card mypage-joy-hero-card--pending" aria-live="polite"></div>

            <?php if ($show_leader_quick && $activation_mission_ui && $activation_stage === 'recruit_pending') : ?>
            <nav id="mypage-joy-quick-nav" class="mypage-joy-quick mypage-joy-quick--one mypage-joy-quick--mission" role="navigation" aria-label="クイックアクション">
                <button type="button" class="mypage-joy-quick__item" id="mypage-joy-quick-recruit-mission" data-aidunite-recruit-modal="1">
                    <span class="mypage-joy-quick__icon mypage-joy-quick__icon--recruit" aria-hidden="true">
                        <?php echo aidunite_get_theme_icon_svg('campaign', ['width' => '26', 'height' => '26']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </span>
                    <span class="mypage-joy-quick__label">試合を募集</span>
                </button>
            </nav>
            <?php elseif ($show_leader_quick && $activation_mission_ui && in_array($activation_stage, ['recruit_published', 'first_application'], true)) : ?>
            <nav id="mypage-joy-quick-nav" class="mypage-joy-quick mypage-joy-quick--two mypage-joy-quick--mission" role="navigation" aria-label="クイックアクション">
                <button type="button" class="mypage-joy-quick__item" id="mypage-joy-quick-recruit" data-aidunite-recruit-modal="1">
                    <span class="mypage-joy-quick__icon mypage-joy-quick__icon--recruit" aria-hidden="true">
                        <?php echo aidunite_get_theme_icon_svg('campaign', ['width' => '26', 'height' => '26']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </span>
                    <span class="mypage-joy-quick__label">試合を募集</span>
                </button>
                <a href="<?php echo esc_url($match_board_my_url); ?>" class="mypage-joy-quick__item" id="mypage-joy-quick-confirm">
                    <span class="mypage-joy-quick__icon mypage-joy-quick__icon--confirm" aria-hidden="true">
                        <?php echo aidunite_get_theme_icon_svg('check_circle', ['width' => '26', 'height' => '26']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </span>
                    <span class="mypage-joy-quick__label">申請状況の確認</span>
                </a>
            </nav>
            <?php elseif ($show_leader_quick) : ?>
            <?php $leader_quick_cols = !empty($activation_chat_unlocked) ? 'three' : 'two'; ?>
            <nav id="mypage-joy-quick-nav" class="mypage-joy-quick mypage-joy-quick--<?php echo esc_attr($leader_quick_cols); ?>" role="navigation" aria-label="クイックアクション">
                <button type="button" class="mypage-joy-quick__item" id="mypage-joy-quick-recruit" data-aidunite-recruit-modal="1">
                    <span class="mypage-joy-quick__icon mypage-joy-quick__icon--recruit" aria-hidden="true">
                        <?php echo aidunite_get_theme_icon_svg('campaign', ['width' => '26', 'height' => '26']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </span>
                    <span class="mypage-joy-quick__label">試合を募集</span>
                </button>
                <a href="<?php echo esc_url($match_board_my_url); ?>" class="mypage-joy-quick__item" id="mypage-joy-quick-confirm">
                    <span class="mypage-joy-quick__icon mypage-joy-quick__icon--confirm" aria-hidden="true">
                        <?php echo aidunite_get_theme_icon_svg('check_circle', ['width' => '26', 'height' => '26']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </span>
                    <span class="mypage-joy-quick__label">申請状況の確認</span>
                </a>
                <?php if (!empty($activation_chat_unlocked)) : ?>
                <a href="<?php echo esc_url($communication_url); ?>" class="mypage-joy-quick__item" id="mypage-joy-quick-chat">
                    <span class="mypage-joy-quick__icon mypage-joy-quick__icon--chat" aria-hidden="true">
                        <?php echo aidunite_get_theme_icon_svg('chat', ['width' => '26', 'height' => '26']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </span>
                    <span class="mypage-joy-quick__label">チャット</span>
                </a>
                <?php endif; ?>
            </nav>
            <?php elseif ($show_member_quick) : ?>
            <nav class="mypage-joy-quick mypage-joy-quick--three mypage-joy-quick--member" role="navigation" aria-label="クイックアクション">
                <a href="<?php echo esc_url($attendance_url); ?>" class="mypage-joy-quick__item" id="mypage-joy-quick-attendance">
                    <span class="mypage-joy-quick__icon mypage-joy-quick__icon--attendance" aria-hidden="true">
                        <?php echo aidunite_get_theme_icon_svg('check_circle', ['width' => '26', 'height' => '26']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </span>
                    <span class="mypage-joy-quick__label">出欠連絡</span>
                </a>
                <a href="<?php echo esc_url($mypage_schedule_url); ?>" class="mypage-joy-quick__item" id="mypage-joy-quick-schedule">
                    <span class="mypage-joy-quick__icon mypage-joy-quick__icon--schedule" aria-hidden="true">
                        <?php echo aidunite_get_theme_icon_svg('calendar_month', ['width' => '26', 'height' => '26']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </span>
                    <span class="mypage-joy-quick__label">スケジュール</span>
                </a>
                <a href="<?php echo esc_url($communication_url); ?>" class="mypage-joy-quick__item" id="mypage-joy-quick-chat">
                    <span class="mypage-joy-quick__icon mypage-joy-quick__icon--chat" aria-hidden="true">
                        <?php echo aidunite_get_theme_icon_svg('chat', ['width' => '26', 'height' => '26']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </span>
                    <span class="mypage-joy-quick__label">チャット</span>
                </a>
            </nav>
            <?php endif; ?>

            <?php if ($show_leader_quick) : ?>
            <section class="mypage-joy-block" id="mypage-joy-happy-section" aria-labelledby="mypage-joy-happy-heading">
                <?php
                if (function_exists('aidunite_render_mypage_section_bar')) {
                    aidunite_render_mypage_section_bar([
                        'section'     => 'happy-feed',
                        'title'       => '最近のチームの様子',
                        'heading_id'  => 'mypage-joy-happy-heading',
                        'link_url'    => $match_board_my_url,
                        'link_label'  => 'すべて確認',
                        'link_id'     => 'mypage-joy-happy-all',
                        'link_hidden' => true,
                        'variant'     => 'joy',
                    ]);
                }
                ?>
                <div class="mypage-joy-block__body mypage-joy-happy-feed">
                    <div id="mypage-joy-happy-feed"></div>
                </div>
            </section>
            <?php endif; ?>

            <section class="mypage-joy-block mypage-joy-block--secondary<?php echo $is_member_role ? ' mypage-joy-block--member-todo' : ''; ?>" id="mypage-joy-secondary" aria-labelledby="mypage-joy-secondary-heading"<?php echo $is_member_role ? '' : ' hidden'; ?>>
                <?php
                if (function_exists('aidunite_render_mypage_section_bar')) {
                    aidunite_render_mypage_section_bar([
                        'section'    => 'secondary-todo',
                        'title'      => $is_member_role ? 'やること' : 'チームのやること',
                        'subtitle'   => $is_member_role ? '（要対応）' : '',
                        'heading_id' => 'mypage-joy-secondary-heading',
                        'priority'   => true,
                        'variant'    => 'joy',
                    ]);
                }
                ?>
                <div class="mypage-joy-block__body">
                    <div id="mypage-joy-secondary-list" class="mypage-joy-secondary__list"></div>
                </div>
            </section>

            <section class="mypage-joy-block" id="mypage-joy-week-section" aria-labelledby="mypage-joy-week-heading">
                <?php
                if (function_exists('aidunite_render_mypage_section_bar')) {
                    aidunite_render_mypage_section_bar([
                        'section'    => 'week-schedule',
                        'title'      => '今週の予定',
                        'heading_id' => 'mypage-joy-week-heading',
                        'link_url'   => $mypage_schedule_url,
                        'variant'    => 'joy',
                    ]);
                }
                ?>
                <div class="mypage-joy-block__body mypage-joy-week-list" id="mypage-joy-week-list"></div>
            </section>

        </div>
    </main>

    <?php get_template_part('template-parts/onboarding-bot-chat-modal'); ?>

</div>
