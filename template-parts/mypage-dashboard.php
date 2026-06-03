<?php
/**
 * マイページ v2（チーム所属ユーザー向けダッシュボード）
 *
 * @package AidUnite
 */

if (!defined('ABSPATH')) {
    exit;
}

$effective_role          = isset($args['effective_role']) ? (string) $args['effective_role'] : 'general';
$current_user_id         = isset($args['current_user_id']) ? (int) $args['current_user_id'] : get_current_user_id();
$user                    = isset($args['user']) && $args['user'] instanceof WP_User ? $args['user'] : wp_get_current_user();
$quick_icons             = isset($args['quick_icons']) && is_array($args['quick_icons']) ? $args['quick_icons'] : [];
$tournament_participations = isset($args['tournament_participations']) && is_array($args['tournament_participations'])
    ? $args['tournament_participations']
    : [];
$mypage_schedule_url     = isset($args['mypage_schedule_url']) ? (string) $args['mypage_schedule_url'] : home_url('/schedule-list');
$is_developer            = !empty($args['is_developer']);
$user_info               = isset($args['user_info']) && is_array($args['user_info']) ? $args['user_info'] : [];
$activation_stage        = isset($args['activation_stage']) ? (string) $args['activation_stage'] : '';
$activation_mission_ui   = !empty($args['activation_mission_ui']);
$activation_chat_unlocked = !isset($args['activation_chat_unlocked']) || !empty($args['activation_chat_unlocked']);
$recruit_edit_url        = isset($args['recruit_edit_url']) ? (string) $args['recruit_edit_url'] : home_url('/schedule-edit/');
$match_board_url         = isset($args['match_board_url']) ? (string) $args['match_board_url'] : home_url('/match-board-own');
$match_board_my_tab_url  = $match_board_url . (strpos($match_board_url, '?') === false ? '?market_tab=my' : '&market_tab=my');

$team_name = '';
$team_id   = isset($user_info['team_id']) ? (int) $user_info['team_id'] : 0;
if ($team_id > 0 && function_exists('aidunite_get_team_name')) {
    $team_name = (string) aidunite_get_team_name($team_id);
}

$notification_count = function_exists('aidunite_get_notification_count')
    ? (int) aidunite_get_notification_count($current_user_id)
    : 0;
$communication_url  = !empty($quick_icons['chat']) ? (string) $quick_icons['chat'] : home_url('/communication');
$tournaments_url    = get_permalink(get_page_by_path('tournaments')) ?: home_url('/tournaments/');

$avatar_html = get_avatar($current_user_id, 72, '', '', ['class' => 'mypage-v2-avatar-img']);

$mypage_icon_base = aidunite_get_mypage_icon_base_url();

$applied_tournaments  = [];
$accepted_tournaments = [];
foreach ($tournament_participations as $part) {
    $status = isset($part['status']) ? (string) $part['status'] : '';
    if ($status === 'accepted') {
        $accepted_tournaments[] = $part;
    } else {
        $applied_tournaments[] = $part;
    }
}

$has_tournament_cards = !empty($applied_tournaments) || !empty($accepted_tournaments);
$tournament_entries     = [];
if (!empty($applied_tournaments)) {
    $tournament_entries[] = [
        'modifier' => 'applied',
        'label'    => '応募中' . (count($applied_tournaments) > 1 ? '（' . count($applied_tournaments) . '件）' : ''),
        'part'     => $applied_tournaments[0],
    ];
}
if (!empty($accepted_tournaments)) {
    $tournament_entries[] = [
        'modifier' => 'accepted',
        'label'    => '参加確定' . (count($accepted_tournaments) > 1 ? '（' . count($accepted_tournaments) . '件）' : ''),
        'part'     => $accepted_tournaments[0],
    ];
}
$tournament_single_entry = count($tournament_entries) === 1;
$pending_sibling_teams   = isset($args['pending_sibling_teams']) && is_array($args['pending_sibling_teams'])
    ? $args['pending_sibling_teams']
    : [];
?>

<div class="mypage-v2<?php echo $activation_mission_ui ? ' mypage-v2--activation-mission' : ''; ?>" id="mypage-v2-root" data-activation-stage="<?php echo esc_attr($activation_stage); ?>">

    <?php if ($pending_sibling_teams !== []) : ?>
    <div class="mypage-v2-pending-sibling-banner" role="status">
      <p class="mypage-v2-pending-sibling-banner__title">承認待ちのチームがあります</p>
      <ul class="mypage-v2-pending-sibling-banner__list">
        <?php foreach ($pending_sibling_teams as $pending_team) : ?>
          <?php if (!is_array($pending_team)) {
              continue;
          } ?>
          <li><?php echo esc_html((string) ($pending_team['team_name'] ?? 'チーム')); ?>（通常1〜2営業日以内にご連絡します）</li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php endif; ?>

    <header class="ainy-webapp-hero ainy-webapp-hero--lg mypage-v2-hero" aria-label="マイページヘッダー">
        <?php
        if (function_exists('aidunite_render_web_app_integrated_header_bar')) {
            aidunite_render_web_app_integrated_header_bar([
                'active_nav'           => 'mypage',
                'notification_count'   => $notification_count,
            ]);
        }
        ?>

        <div class="mypage-v2-profile">
            <div class="mypage-v2-avatar"><?php echo $avatar_html; ?></div>
            <h1><?php echo esc_html($user->display_name); ?>さん</h1>
            <?php if ($team_name !== '') : ?>
                <p><?php echo esc_html($team_name); ?></p>
            <?php endif; ?>
        </div>

        <div class="mypage-v2-quick" role="navigation" aria-label="クイックアクション">
            <?php if ($activation_mission_ui) : ?>
            <div class="mypage-v2-quick-item">
                <a href="<?php echo esc_url($activation_stage === 'recruit_pending' ? $recruit_edit_url : $mypage_schedule_url); ?>" class="mypage-v2-quick-link" id="mypage-v2-quick-schedule">
                    <div class="mypage-v2-quick-icon mypage-v2-quick-icon--schedule" aria-hidden="true">
                        <?php echo aidunite_get_chat_icon_svg(aidunite_get_mypage_quick_icon_name('schedule'), ['width' => '28', 'height' => '28']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </div>
                    <span>試合の日時</span>
                </a>
            </div>
            <?php if (in_array($activation_stage, ['recruit_published', 'first_application'], true)) : ?>
            <div class="mypage-v2-quick-item">
                <a href="<?php echo esc_url($match_board_my_tab_url); ?>" class="mypage-v2-quick-link" id="mypage-v2-quick-match">
                    <div class="mypage-v2-quick-icon mypage-v2-quick-icon--match" aria-hidden="true">
                        <?php echo aidunite_get_chat_icon_svg(aidunite_get_mypage_quick_icon_name('match'), ['width' => '32', 'height' => '32', 'class' => 'mypage-v2-quick-icon__svg mypage-v2-quick-icon__svg--match']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </div>
                    <span>試合一覧</span>
                </a>
            </div>
            <?php endif; ?>
            <?php else : ?>
            <div class="mypage-v2-quick-item">
                <a href="<?php echo esc_url($quick_icons['schedule'] ?? $mypage_schedule_url); ?>" class="mypage-v2-quick-link" id="mypage-v2-quick-schedule">
                    <div class="mypage-v2-quick-icon mypage-v2-quick-icon--schedule" aria-hidden="true">
                        <?php echo aidunite_get_chat_icon_svg(aidunite_get_mypage_quick_icon_name('schedule'), ['width' => '28', 'height' => '28']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </div>
                    <span>スケジュール</span>
                </a>
            </div>
            <div class="mypage-v2-quick-item">
                <a href="<?php echo esc_url($communication_url); ?>" class="mypage-v2-quick-link" id="mypage-v2-quick-chat">
                    <div class="mypage-v2-quick-icon mypage-v2-quick-icon--chat" aria-hidden="true">
                        <?php echo aidunite_get_chat_icon_svg(aidunite_get_mypage_quick_icon_name('chat'), ['width' => '28', 'height' => '28']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </div>
                    <span>チャット</span>
                </a>
            </div>
            <?php if (!empty($quick_icons['match'])) : ?>
            <div class="mypage-v2-quick-item">
                <a href="<?php echo esc_url($quick_icons['match']); ?>" class="mypage-v2-quick-link" id="mypage-v2-quick-match">
                    <div class="mypage-v2-quick-icon mypage-v2-quick-icon--match" aria-hidden="true">
                        <?php echo aidunite_get_chat_icon_svg(aidunite_get_mypage_quick_icon_name('match'), ['width' => '32', 'height' => '32', 'class' => 'mypage-v2-quick-icon__svg mypage-v2-quick-icon__svg--match']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </div>
                    <span>試合一覧</span>
                    <?php if ($effective_role === 'team_leader') : ?>
                        <span class="mypage-v2-quick-hint">代表者のみ</span>
                    <?php endif; ?>
                </a>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </header>

    <main class="ainy-webapp-content mypage-v2-content">

        <?php if (!$activation_mission_ui) : ?>
        <?php
        if (function_exists('aidunite_render_mypage_tabs')) {
            aidunite_render_mypage_tabs($mypage_icon_base);
        }
        ?>

        <!-- 今日のフォーカス（カルーセル） -->
        <section class="mypage-v2-block mypage-v2-block--today" id="mypage-section-today" aria-label="今日">
            <div class="mypage-v2-focus" aria-label="今日のフォーカス">
                <button type="button" class="mypage-v2-focus-arrow" id="mypage-focus-prev" aria-label="前のカード"><?php echo aidunite_render_theme_icon('chevron_left', ['width' => '22', 'height' => '22']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
                <div class="mypage-v2-focus-track">
                    <div class="mypage-v2-focus-card" id="mypage-focus-card">
                        <small class="mypage-v2-focus-label">TODAY</small>
                        <h2>読み込み中...</h2>
                        <p>本日の予定を確認しています</p>
                    </div>
                </div>
                <button type="button" class="mypage-v2-focus-arrow" id="mypage-focus-next" aria-label="次のカード"><?php echo aidunite_render_theme_icon('chevron_right', ['width' => '22', 'height' => '22']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
            </div>
            <div class="mypage-v2-dots" id="mypage-focus-dots" aria-hidden="true"></div>
        </section>
        <?php endif; ?>

        <div class="mypage-v2-feed">

            <!-- ① やること -->
            <section class="mypage-v2-block mypage-v2-block--todo" id="mypage-section-todo" aria-labelledby="mypage-heading-todo">
                <?php
                if (function_exists('aidunite_render_mypage_section_bar')) {
                    aidunite_render_mypage_section_bar([
                        'section'    => 'todo',
                        'title'      => 'やること',
                        'subtitle'   => '（要対応）',
                        'heading_id' => 'mypage-heading-todo',
                        'priority'   => true,
                    ]);
                }
                ?>
                <div class="mypage-v2-todo mypage-v2-panel mypage-v2-panel--strong<?php echo $activation_mission_ui ? ' mypage-v2-mission-todo' : ''; ?>" id="mypage-todo-list"<?php echo $activation_mission_ui ? ' data-mission-ui="1"' : ''; ?>>
                    <p class="mypage-v2-loading">読み込み中...</p>
                </div>
            </section>

            <?php if (!$activation_mission_ui) : ?>
            <!-- ② 本日の予定（フォーカス補完・一覧） -->
            <section class="mypage-v2-block mypage-v2-block--today-schedules" id="mypage-section-today-schedules" aria-labelledby="mypage-heading-today-schedules">
                <?php
                if (function_exists('aidunite_render_mypage_section_bar')) {
                    aidunite_render_mypage_section_bar([
                        'section'    => 'today-schedules',
                        'title'      => '本日の予定',
                        'heading_id' => 'mypage-heading-today-schedules',
                        'link_url'   => $mypage_schedule_url,
                    ]);
                }
                ?>
                <div class="mypage-v2-today-schedules mypage-v2-panel" id="mypage-today-schedules">
                    <p class="mypage-v2-loading">読み込み中...</p>
                </div>
            </section>

            <!-- ③ 大会の応募・参加 -->
            <section class="mypage-v2-block mypage-v2-block--event" id="mypage-section-event" aria-labelledby="mypage-heading-event">
                <?php
                if (function_exists('aidunite_render_mypage_section_bar')) {
                    aidunite_render_mypage_section_bar([
                        'section'    => 'event',
                        'title'      => '大会',
                        'heading_id' => 'mypage-heading-event',
                        'link_url'   => $tournaments_url,
                    ]);
                }
                ?>
                <div class="mypage-v2-event-grid mypage-v2-panel" id="mypage-event-grid" data-has-cards="<?php echo $has_tournament_cards ? '1' : '0'; ?>" data-single-entry="<?php echo $tournament_single_entry ? '1' : '0'; ?>">
                    <?php if ($has_tournament_cards) : ?>
                        <?php if ($tournament_single_entry) :
                            $entry = $tournament_entries[0];
                            $part  = $entry['part']; ?>
                            <a href="<?php echo esc_url($part['url']); ?>" class="mypage-v2-tournament-block mypage-v2-tournament-block--<?php echo esc_attr($entry['modifier']); ?>">
                                <small><?php echo esc_html($entry['label']); ?></small>
                                <strong><?php echo esc_html($part['title']); ?></strong>
                                <?php if (!empty($part['status_label'])) : ?>
                                    <p><?php echo esc_html($part['status_label']); ?></p>
                                <?php endif; ?>
                            </a>
                        <?php else : ?>
                            <?php foreach ($tournament_entries as $entry) :
                                $part = $entry['part']; ?>
                                <a href="<?php echo esc_url($part['url']); ?>" class="mypage-v2-mini-card mypage-v2-mini-card--<?php echo esc_attr($entry['modifier']); ?>">
                                    <small><?php echo esc_html($entry['label']); ?></small>
                                    <strong><?php echo esc_html($part['title']); ?></strong>
                                    <?php if (!empty($part['status_label'])) : ?>
                                        <p><?php echo esc_html($part['status_label']); ?></p>
                                    <?php endif; ?>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    <?php else : ?>
                        <p class="mypage-v2-loading" id="mypage-event-empty">読み込み中...</p>
                    <?php endif; ?>
                </div>
            </section>

            <!-- ④ 直近の予定 -->
            <section class="mypage-v2-block mypage-v2-block--upcoming" id="mypage-section-upcoming" aria-labelledby="mypage-heading-upcoming">
                <?php
                if (function_exists('aidunite_render_mypage_section_bar')) {
                    aidunite_render_mypage_section_bar([
                        'section'    => 'upcoming',
                        'title'      => '直近',
                        'heading_id' => 'mypage-heading-upcoming',
                        'link_url'   => $mypage_schedule_url,
                    ]);
                }
                ?>
                <div class="mypage-v2-upcoming-grid mypage-v2-panel" id="mypage-upcoming-grid">
                    <p class="mypage-v2-loading">読み込み中...</p>
                </div>
            </section>
            <?php endif; ?>

        </div>

    </main>

    <?php get_template_part('template-parts/onboarding-bot-chat-modal'); ?>

    <?php if ($is_developer) : ?>
    <div class="mypage-preview-switcher" aria-label="開発者プレビュー">
        <div class="mypage-preview-switcher-title">プレビュー</div>
        <button type="button" class="mypage-preview-btn" data-preview="schedule">予定あり</button>
        <button type="button" class="mypage-preview-btn" data-preview="actions">要対応あり</button>
        <button type="button" class="mypage-preview-btn" data-preview="both">予定+要対応</button>
        <button type="button" class="mypage-preview-btn" data-preview="empty">何もない日</button>
        <button type="button" class="mypage-preview-btn" data-preview="reset">リセット</button>
    </div>
    <?php endif; ?>

</div>
