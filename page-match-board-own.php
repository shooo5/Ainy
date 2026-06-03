<?php
/**
 * Template Name: 試合掲示板・自動マッチ候補
 */

// 統一認証・権限チェック
try {
    require_once get_stylesheet_directory() . '/functions/common/auth-middleware.php';
    $auth_result = AidUniteAuthMiddleware::require_auth(true);
    if (!$auth_result->is_valid()) {
        return;
    }
} catch (Exception $e) {
    error_log('試合掲示板認証エラー: ' . $e->getMessage());
    wp_redirect(home_url('/login'));
    exit;
} catch (Error $e) {
    error_log('試合掲示板認証Fatal Error: ' . $e->getMessage());
    wp_redirect(home_url('/login'));
    exit;
}

// 掲示板依存（functions.php でも読込済み。子テーマパスで統一）
if (function_exists('aidunite_match_board_ensure_dependencies')) {
    aidunite_match_board_ensure_dependencies();
}
require_once get_stylesheet_directory() . '/functions/match/match-request-functions.php';
require_once get_stylesheet_directory() . '/functions/match/match-board-page-helpers.php';

// 削除済みスケジュールの完全削除処理
if (isset($_POST['cleanup_schedules']) && $_POST['cleanup_schedules'] === '1') {
    $current_user = wp_get_current_user();
    $deleted_count = 0;

    // 削除済みスケジュールを取得
    $trashed_schedules = get_posts([
        'post_type' => 'schedule',
        'post_status' => 'trash',
        'posts_per_page' => -1,
        'author' => $current_user->ID
    ]);

    foreach ($trashed_schedules as $schedule) {
        if (wp_delete_post($schedule->ID, true)) {
            $deleted_count++;
        }
    }

    echo '<div class="alert alert-success">' . aidunite_render_theme_icon('check_circle', ['width' => '18', 'height' => '18'], 'aidunite-icon--inline') . ' 削除済みスケジュール ' . $deleted_count . ' 件を完全削除しました</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

get_header();

if (function_exists('aidunite_empty_state_styles')) {
    aidunite_empty_state_styles();
}

?>
<!-- マイページと同様：html には触れず body 以下だけでスクロール（二重スクロール防止） -->
<style id="match-board-scroll-critical">
body.page-match-board-own #page,
body.page-match-board-own #page-wrapper,
body.page-match-board-own #main.site-main,
body.page-match-board-own .full-container,
body.page-template-page-match-board-own-php #page,
body.page-template-page-match-board-own-php #page-wrapper,
body.page-template-page-match-board-own-php #main.site-main,
body.page-template-page-match-board-own-php .full-container { overflow: visible; height: auto; min-height: auto; }
body.page-match-board-own .team-dashboard-container.page-match-board-own,
body.page-template-page-match-board-own-php .team-dashboard-container.page-match-board-own { min-height: 0; overflow: visible; }
@media (max-width: 767px) {
  body.page-match-board-own .team-dashboard-container.page-match-board-own,
  body.page-template-page-match-board-own-php .team-dashboard-container.page-match-board-own { min-height: 0; overflow: visible; }
}
</style>
<?php
$au_match_board_rest_url = esc_url(get_rest_url(null, 'aidunite/v1'));
$au_match_board_rest_nonce = wp_create_nonce('wp_rest');
$initial_market_tab = (isset($_GET['market_tab']) && sanitize_text_field(wp_unslash($_GET['market_tab'])) === 'my')
    ? 'my'
    : 'recruit';
$hero_title = '試合一覧';
$hero_subtitle = ($initial_market_tab === 'my')
    ? '自分が出した募集と申請の状況を確認できます。'
    : '募集中の試合を確認して、条件に合う相手へ申請できます。';
?>
<script>
window.aiduniteMatchBoardConfig = window.aiduniteMatchBoardConfig || {};
window.aiduniteMatchBoardConfig.restUrl = <?php echo json_encode($au_match_board_rest_url); ?>;
window.aiduniteMatchBoardConfig.restNonce = <?php echo json_encode($au_match_board_rest_nonce); ?>;
window.aiduniteMatchBoardConfig.initialTab = <?php echo json_encode($initial_market_tab); ?>;
</script>
<div class="team-dashboard-container page-match-board-own ainy-webapp-page" data-au-match-nonce="<?php echo esc_attr(wp_create_nonce('au_match_nonce')); ?>" data-rest-url="<?php echo esc_attr($au_match_board_rest_url); ?>" data-wp-rest-nonce="<?php echo esc_attr($au_match_board_rest_nonce); ?>">
    <?php
    if (function_exists('aidunite_render_web_app_page_hero')) {
        aidunite_render_web_app_page_hero([
            'size'       => 'md',
            'title'      => $hero_title,
            'subtitle'   => $hero_subtitle,
            'back'       => true,
            'active_nav' => 'none',
        ]);
        aidunite_render_web_app_content_open();
    } else {
        ?>
    <div class="dashboard-header">
        <h1><?php echo esc_html($hero_title); ?></h1>
        <p><?php echo esc_html($hero_subtitle); ?></p>
    </div>
        <?php
    }
    ?>

    <div class="main-content-area match-candidates-container match-board-market-axis" id="match-candidates-container">
            <?php
            $current_user = wp_get_current_user();
            $current_user_team_id = function_exists('aidunite_match_board_resolve_viewer_team_id')
                ? aidunite_match_board_resolve_viewer_team_id((int) $current_user->ID)
                : 0;

            if (!$current_user_team_id) {
                echo '<div class="alert alert-warning">' . aidunite_render_theme_icon('brightness_alert', ['width' => '18', 'height' => '18'], 'aidunite-icon--inline') . ' チームIDが設定されていません。チーム登録を完了してください。</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            } else {
                $au_market_debug = isset($_GET['au_market_debug']) && $_GET['au_market_debug'] === '1' && is_user_logged_in();
                if ($au_market_debug) {
                    $GLOBALS['aidunite_market_board_debug_enabled'] = true;
                }
                $today = date('Y-m-d');
                $default_range = function_exists('aidunite_market_board_default_date_range')
                    ? aidunite_market_board_default_date_range()
                    : ['from' => date('Y-m-d', strtotime('+1 day')), 'to' => date('Y-m-d', strtotime('+120 days'))];
                $date_from_default = $default_range['from'];
                $date_to_default = $default_range['to'];
                $date_from = isset($_GET['date_from']) ? sanitize_text_field(wp_unslash($_GET['date_from'])) : $date_from_default;
                $date_to   = isset($_GET['date_to']) ? sanitize_text_field(wp_unslash($_GET['date_to'])) : $date_to_default;
                if (function_exists('aidunite_market_normalize_filter_date')) {
                    $date_from = aidunite_market_normalize_filter_date($date_from);
                    $date_to   = aidunite_market_normalize_filter_date($date_to);
                }
                $filter_gender = isset($_GET['filter_gender']) ? sanitize_text_field($_GET['filter_gender']) : '';
                if (!in_array($filter_gender, ['male', 'female'], true)) {
                    $filter_gender = '';
                }
                $filter_area   = isset($_GET['filter_area']) ? sanitize_text_field($_GET['filter_area']) : '';
                if ($date_from === '') {
                    $date_from = $date_from_default;
                }
                if ($date_to === '') $date_to = $date_to_default;

                $my_matching_schedules = aidunite_market_get_my_matching_schedules($current_user_team_id);
                $my_board_schedules = function_exists('aidunite_market_get_board_my_schedules')
                    ? aidunite_market_get_board_my_schedules($current_user_team_id)
                    : $my_matching_schedules;
                $show_my_tab_nudge = function_exists('aidunite_market_board_should_show_my_tab_nudge')
                    && aidunite_market_board_should_show_my_tab_nudge((int) $current_user_team_id, $my_board_schedules);
                $show_my_tab_bot_approve_nudge = function_exists('aidunite_market_board_should_show_my_tab_bot_approve_nudge')
                    && aidunite_market_board_should_show_my_tab_bot_approve_nudge((int) $current_user_team_id);
                if (function_exists('aidunite_enqueue_onboarding_bot_chat_modal_assets')) {
                    $board_bot_chat_url = '';
                    if (defined('AIDUNITE_TEAM_META_ONBOARDING_BOT_MR')) {
                        $board_bot_mr = (int) get_post_meta((int) $current_user_team_id, AIDUNITE_TEAM_META_ONBOARDING_BOT_MR, true);
                        if ($board_bot_mr > 0 && function_exists('aidunite_onboarding_bot_get_chat_url_for_request')) {
                            $board_bot_chat_url = aidunite_onboarding_bot_get_chat_url_for_request($board_bot_mr);
                        }
                    }
                    $board_show_bot_modal = function_exists('aidunite_onboarding_bot_should_show_chat_modal_on_mypage')
                        && aidunite_onboarding_bot_should_show_chat_modal_on_mypage((int) $current_user_team_id);
                    aidunite_enqueue_onboarding_bot_chat_modal_assets([
                        'show_modal' => $board_show_bot_modal,
                        'chat_url'   => $board_bot_chat_url,
                    ]);
                }
                $schedule_edit_url = function_exists('aidunite_get_activation_recruit_edit_url')
                    ? aidunite_get_activation_recruit_edit_url()
                    : home_url('/schedule-edit/');
                $all_recruitments = aidunite_market_get_all_recruitments($date_from, $date_to, $current_user_team_id, [
                    'gender' => $filter_gender,
                    'team_area' => $filter_area,
                ]);

                $rows = [];
                $row_debug = [];
                foreach ($all_recruitments as $other_post) {
                    if (function_exists('aidunite_market_viewer_hides_established_recruit_row')
                        && aidunite_market_viewer_hides_established_recruit_row((int) $current_user_team_id, (int) $other_post->ID)) {
                        continue;
                    }
                    $row_state = aidunite_market_get_row_state($other_post, $my_matching_schedules, $current_user_team_id);
                    if ($au_market_debug) {
                        $other_dn_dbg = function_exists('_aidunite_match_apply_normalize_schedule_date')
                            ? _aidunite_match_apply_normalize_schedule_date(get_post_meta((int) $other_post->ID, 'schedule_date', true))
                            : (string) get_post_meta((int) $other_post->ID, 'schedule_date', true);
                        $ev_dbg = $row_state['best_apply_eval'] ?? null;
                        $row_debug[] = [
                            'id'              => (int) $other_post->ID,
                            'state'           => $row_state['state'],
                            'best_my'         => (int) ($row_state['best_my_schedule_id'] ?? 0),
                            'eval_lr'         => is_array($ev_dbg) ? (string) ($ev_dbg['list_row'] ?? '') : '',
                            'eval_rc'         => is_array($ev_dbg) ? (string) ($ev_dbg['reason_code'] ?? '') : '',
                            'team_has_guest'  => function_exists('aidunite_team_has_guest_committed_schedule_on_date')
                                ? (aidunite_team_has_guest_committed_schedule_on_date((int) $current_user_team_id, $other_dn_dbg) ? 1 : 0)
                                : null,
                            'my_matching_cnt' => count($my_matching_schedules),
                        ];
                    }
                    if ($row_state['state'] === 'mismatch') {
                        continue;
                    }
                    $rows[] = [
                        'other' => $other_post,
                        'state' => $row_state['state'],
                        'best_my_schedule_id' => $row_state['best_my_schedule_id'],
                    ];
                }

                $state_order = ['best' => 0, 'green' => 1, 'yellow' => 2, 'no_preference' => 3];
                usort($rows, function ($a, $b) use ($state_order) {
                    $oa = $a['other'];
                    $ob = $b['other'];
                    $da = get_post_meta($oa->ID, 'schedule_date', true);
                    $db = get_post_meta($ob->ID, 'schedule_date', true);
                    $sa = get_post_meta($oa->ID, 'schedule_start_time', true);
                    $sb = get_post_meta($ob->ID, 'schedule_start_time', true);
                    $order_a = $state_order[$a['state']] ?? 5;
                    $order_b = $state_order[$b['state']] ?? 5;
                    if ($order_a !== $order_b) return $order_a - $order_b;
                    if ($da !== $db) return strcmp($da, $db);
                    return strcmp($sa, $sb);
                });
                ?>

                <!-- ピルタブ: 募集中の試合 | 申請状況 -->
                <nav class="market-axis-pill-tabs" role="tablist" aria-label="募集中の試合・申請状況">
                    <button type="button" class="market-axis-pill-tab active" role="tab" aria-selected="true" aria-controls="market-tab-recruit" id="market-tab-recruit-btn">募集中の試合</button>
                    <button type="button" class="market-axis-pill-tab" role="tab" aria-selected="false" aria-controls="market-tab-my" id="market-tab-my-btn">申請状況</button>
                </nav>
                <p class="market-axis-tab-hint" id="market-axis-tab-hint" role="status" aria-live="polite">
                    申請状況：自分の募集と申請を確認 / 募集中の試合：相手募集を探して申請
                </p>

                <!-- タブパネル: 募集中の試合 -->
                <div id="market-tab-recruit" class="market-axis-tab-panel" role="tabpanel" aria-labelledby="market-tab-recruit-btn">
                <!-- フィルター（折り畳み式） -->
                <details class="market-axis-filters-wrap" id="market-axis-filters-details" open>
                    <summary class="market-axis-filters-summary">フィルター</summary>
                    <form method="get" class="market-axis-filters" id="market-axis-filters">
                        <input type="hidden" name="p" value="<?php echo esc_attr(get_query_var('paged') ?: 1); ?>">
                        <div class="market-axis-filter-row">
                            <label>日付 <input type="date" name="date_from" value="<?php echo esc_attr($date_from); ?>"> ～ <input type="date" name="date_to" value="<?php echo esc_attr($date_to); ?>"></label>
                            <label>性別
                                <select name="filter_gender">
                                    <option value="">すべて</option>
                                    <option value="male" <?php selected($filter_gender, 'male'); ?>>男子</option>
                                    <option value="female" <?php selected($filter_gender, 'female'); ?>>女子</option>
                                </select>
                            </label>
                            <button type="submit" class="match-btn match-btn--secondary">絞り込む</button>
                        </div>
                    </form>
                    <div class="market-axis-quick-filters" role="group" aria-label="表示フィルター">
                        <button type="button" class="match-btn market-axis-quick-btn active" data-quick="all">すべて</button>
                        <button type="button" class="match-btn market-axis-quick-btn" data-quick="best"><?php echo aidunite_render_theme_icon('star', ['width' => '16', 'height' => '16'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> ベストマッチ</button>
                        <button type="button" class="match-btn market-axis-quick-btn" data-quick="green">成立可能</button>
                        <button type="button" class="match-btn market-axis-quick-btn" data-quick="yellow">条件調整</button>
                        <button type="button" class="match-btn market-axis-quick-btn" data-quick="no_preference">希望未登録</button>
                    </div>
                </details>

                <?php
                if ($au_market_debug && function_exists('aidunite_market_board_render_debug_panel')) {
                    aidunite_market_board_render_debug_panel([
                        'viewer_team_id'        => (int) $current_user_team_id,
                        'user_id'               => (int) $current_user->ID,
                        'date_from'             => $date_from,
                        'date_to'               => $date_to,
                        'filter_gender'         => $filter_gender,
                        'all_recruitments'      => $all_recruitments,
                        'rows'                  => $rows,
                        'row_debug'             => $row_debug ?? [],
                        'my_matching_schedules' => $my_matching_schedules,
                    ]);
                }
                ?>

                <!-- 募集一覧 -->
                <ul class="market-axis-list" id="market-axis-list" aria-label="募集中の試合一覧">
                    <?php
                    foreach ($rows as $row) {
                        $other = $row['other'];
                        $state = $row['state'];
                        $best_my_id = $row['best_my_schedule_id'];
                        $other_id = $other->ID;
                        $other_team_id = get_post_meta($other_id, 'team_id', true);
                        $other_team_name = $other_team_id && function_exists('aidunite_get_team_name') ? aidunite_get_team_name($other_team_id) : ($other_team_id ? get_the_title($other_team_id) : '');
                        $other_team_name = $other_team_name ?: 'チーム名未設定';
                        $other_date = get_post_meta($other_id, 'schedule_date', true);
                        $other_start = get_post_meta($other_id, 'schedule_start_time', true);
                        $other_end = get_post_meta($other_id, 'schedule_end_time', true);
                        $other_place = get_post_meta($other_id, 'schedule_place', true) ?: get_post_meta($other_id, 'schedule_place_option', true);
                        $other_gender = get_post_meta($other_id, 'schedule_gender', true) ?: get_post_meta($other_id, 'matching_gender_condition', true);
                        $date_disp = $other_date ? date('n/j', strtotime($other_date)) . '(' . ['日','月','火','水','木','金','土'][date('w', strtotime($other_date))] . ')' : '';
                        $time_disp = trim(($other_start ?: '') . '–' . ($other_end ?: ''));
                        $place_disp = function_exists('aidunite_jp_place') ? aidunite_jp_place($other_place) : ($other_place ?: '-');
                        $gender_disp = function_exists('aidunite_jp_gender') ? aidunite_jp_gender($other_gender) : ($other_gender ?: '-');
                        $no_preference_apply_link = home_url('/match-detail/') . '?schedule_id=' . $other_id . '#apply';
                        $status_data = $current_user_team_id ? aidunite_get_application_status($current_user_team_id, $best_my_id ?: 0, $other_team_id, $other_id) : [
                            'text' => function_exists('aidunite_get_match_status_label') ? aidunite_get_match_status_label('not_applied', 'text') : '未申請',
                            'class' => '',
                            'code' => 'not_applied',
                            'request_id' => 0,
                            'is_requester' => false,
                        ];
                        $detail_link = home_url('/match-detail/') . '?my_schedule_id=' . $best_my_id . '&schedule_id=' . $other_id;
                        if (!empty($status_data['request_id'])) {
                            $detail_link .= '&match_request_id=' . (int) $status_data['request_id'];
                        }
                        $status_chat_link = !empty($status_data['request_id'])
                            ? home_url('/chat?match_id=' . (int) $status_data['request_id'])
                            : '';
                        $status_code = (string) ($status_data['code'] ?? '');
                        $is_not_applied = ($status_code === 'not_applied' || ($status_data['text'] ?? '') === '未申請');
                        $is_terminal = in_array($status_code, ['rejected', 'canceled', 'canceled_opponent', 'reconfirm_required', 'proposal_pending_accept', 'slots_full', 'gender_slots_full', 'gender_conflict', 'invalid_closed', 'venue_conflict', 'proposal_possible'], true)
                            || in_array(($status_data['text'] ?? ''), ['拒否済み', 'キャンセル済み', '相手キャンセル'], true);
                        $outcome_blocks_reapply = in_array($status_code, ['slots_full', 'gender_slots_full'], true);
                        $is_established = ($status_code === 'established' || ($status_data['text'] ?? '') === '試合確定');
                        $market_reapply_label = '再申請';
                        $market_reapply_hide = false;
                        if (!empty($best_my_id) && !empty($status_data['request_id']) && function_exists('aidunite_match_detail_reapply_cta')) {
                            $mcta = aidunite_match_detail_reapply_cta((int) $other_id, (int) $status_data['request_id'], (int) $best_my_id);
                            if (($mcta['variant'] ?? '') === 'ineligible') {
                                $market_reapply_hide = true;
                            } else {
                                $market_reapply_label = $mcta['label'] ?: '再申請';
                            }
                        }

                        $tier_labels = function_exists('aidunite_match_board_tier_labels') ? aidunite_match_board_tier_labels() : [];
                        if (isset($tier_labels[$state])) {
                            $badge = (string) $tier_labels[$state]['label'];
                            $state_class = (string) $tier_labels[$state]['class'];
                        } elseif ($state === 'green') {
                            $badge = '成立可能';
                            $state_class = 'green';
                        } elseif ($state === 'yellow') {
                            $badge = '条件調整';
                            $state_class = 'yellow';
                        } else {
                            $badge = '希望未登録';
                            $state_class = 'no_preference';
                        }
                        $initial_apply_label = ($state_class === 'yellow') ? '調整して申請' : '申請';
                        $team_name_short = (function_exists('mb_strlen') && function_exists('mb_substr') && mb_strlen($other_team_name) > 13) ? mb_substr($other_team_name, 0, 13) . '...' : $other_team_name;
                        ?>
                        <li class="market-axis-row" data-state="<?php echo esc_attr($state_class); ?>">
                            <div class="market-axis-row-line1 market-axis-row-head">
                                <span class="market-axis-date-time"><?php echo esc_html($date_disp . '｜' . $time_disp); ?></span>
                                <span class="market-axis-badge market-axis-badge--<?php echo esc_attr($state_class); ?>"><?php if ($state_class === 'best') { echo aidunite_render_theme_icon('star', ['width' => '14', 'height' => '14'], 'aidunite-icon--inline'); } // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo esc_html($badge); ?></span>
                            </div>
                            <div class="market-axis-row-line2">
                                <a href="<?php echo esc_url($state_class === 'no_preference' ? $no_preference_apply_link : $detail_link); ?>" class="market-axis-team-link" title="<?php echo esc_attr($other_team_name); ?>"><?php echo esc_html($team_name_short); ?></a>
                                <span class="market-axis-sep-inline" aria-hidden="true">｜</span>
                                <span class="market-axis-meta"><?php echo esc_html($place_disp . '｜' . $gender_disp); ?></span>
                            </div>
                            <div class="market-axis-row-line3">
                                <?php if ($state_class === 'no_preference') : ?>
                                    <p class="market-axis-no-preference-message">この日はスケジュールが空いています。この試合募集に申請しますか？<br><span class="market-axis-no-preference-note">※申請後、仮の日程でスケジュール登録されます。</span></p>
                                    <div class="market-axis-row-cta">
                                        <a href="<?php echo esc_url($no_preference_apply_link); ?>" class="match-btn match-btn--primary" data-analytics-target="match_apply" data-analytics-event="click">この日程で申請する</a>
                                    </div>
                                <?php else : ?>
                                    <div class="market-axis-row-cta">
                                        <?php if (in_array($state_class, ['best', 'yellow', 'green'], true)) : ?>
                                            <?php if ($is_not_applied && $best_my_id) : ?>
                                                <a href="<?php echo esc_url($detail_link . '#apply'); ?>" class="match-btn match-btn--primary"><?php echo esc_html($initial_apply_label); ?></a>
                                            <?php elseif ($is_terminal) : ?>
                                                <?php if ($outcome_blocks_reapply && !empty($status_data['request_id'])) : ?>
                                                <a href="<?php echo esc_url($detail_link); ?>" class="match-btn match-btn--secondary">詳細</a>
                                                <?php if (!empty($status_data['is_requester'])) : ?>
                                                <span class="market-axis-established-sep" aria-hidden="true">｜</span>
                                                <button type="button" class="match-btn match-btn--secondary au-open-cancel-modal market-axis-cancel-apply" data-request-id="<?php echo (int) $status_data['request_id']; ?>" data-team-name="<?php echo esc_attr($other_team_name); ?>" data-cancel-type="apply">申請をキャンセル</button>
                                                <?php endif; ?>
                                                <?php elseif (!$market_reapply_hide) : ?>
                                                <a href="<?php echo esc_url($detail_link . '#apply'); ?>" class="match-btn match-btn--primary"><?php echo esc_html($market_reapply_label); ?></a>
                                                <?php else : ?>
                                                <a href="<?php echo esc_url($detail_link); ?>" class="match-btn match-btn--secondary">詳細</a>
                                                <?php endif; ?>
                                            <?php else : ?>
                                                <?php if ($is_established) : ?>
                                                    <div class="market-axis-status-with-cta">
                                                        <span class="match-candidate-status-badge <?php echo esc_attr($status_data['class'] ?: 'badge-status'); ?>"><?php echo esc_html($status_data['text']); ?></span>
                                                        <?php if ($status_chat_link !== ''): ?>
                                                        <a href="<?php echo esc_url($status_chat_link); ?>" class="match-btn match-btn--secondary">チャット</a>
                                                        <?php endif; ?>
                                                        <a href="<?php echo esc_url($detail_link); ?>" class="match-btn match-btn--secondary">詳細</a>
                                                    </div>
                                                <?php else : ?>
                                                    <a href="<?php echo esc_url($detail_link); ?>" class="match-btn match-btn--secondary">申請内容を確認</a>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php } ?>
                </ul>
                <!-- 該当0件時の空状態（カード表示）。初期は $rows が空のときのみ表示し、フィルターで0件になったときはJSで表示 -->
                <div class="market-axis-empty-card" id="market-axis-empty-state" role="status" aria-live="polite"<?php echo empty($rows) ? '' : ' style="display:none;" aria-hidden="true"'; ?>>
                    <p class="market-axis-empty-card__text">該当する対戦募集はありません。</p>
                    <p class="market-axis-empty-card__sub" id="market-axis-empty-sub">日付や条件を変更してください。</p>
                </div>
                </div><!-- /#market-tab-recruit -->

                <!-- タブパネル: 申請状況（自分の予定・招待URL・成立一覧） -->
                <div id="market-tab-my" class="market-axis-tab-panel market-axis-tab-panel--hidden" role="tabpanel" aria-labelledby="market-tab-my-btn" hidden>
                <section class="market-axis-my-panel">
                    <?php if (!empty($show_my_tab_nudge)) : ?>
                    <div class="market-axis-my-nudge" role="region" aria-labelledby="market-axis-my-nudge-title">
                        <div class="market-axis-my-nudge__card">
                            <span class="market-axis-my-nudge__icon" aria-hidden="true">
                                <?php echo aidunite_render_theme_icon('info', ['width' => '20', 'height' => '20']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            </span>
                            <div class="market-axis-my-nudge__body">
                                <p class="market-axis-my-nudge__title" id="market-axis-my-nudge-title">いまは「申請待ち」の状態です</p>
                                <p class="market-axis-my-nudge__text">公開した募集は問題なく掲載されています。他チームから申請が届くと、下の日程の枠に表示されます。</p>
                                <ul class="market-axis-my-nudge__list">
                                    <li><strong>待つ</strong> — この画面を開いたまま、またはあとから申請状況を確認</li>
                                    <li><strong>知らせる</strong> — 各日程の「招待コード」で知り合いのチームに共有</li>
                                    <li><strong>探す</strong> — 「募集中の試合」で他チームの募集にこちらから申請</li>
                                </ul>
                                <div class="market-axis-my-nudge__actions">
                                    <button type="button" class="match-btn match-btn--primary" id="market-axis-my-nudge-recruit-tab">募集中の試合を見る</button>
                                    <a href="<?php echo esc_url($schedule_edit_url); ?>" class="match-btn match-btn--secondary">日程を追加する</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($show_my_tab_bot_approve_nudge)) : ?>
                    <div class="market-axis-my-nudge market-axis-my-nudge--bot-approve" role="region" aria-labelledby="market-axis-my-bot-nudge-title">
                        <div class="market-axis-my-nudge__card">
                            <span class="market-axis-my-nudge__icon" aria-hidden="true">
                                <?php echo aidunite_render_theme_icon('info', ['width' => '20', 'height' => '20']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            </span>
                            <div class="market-axis-my-nudge__body">
                                <p class="market-axis-my-nudge__title" id="market-axis-my-bot-nudge-title">練習相手チームから申請が届きました</p>
                                <p class="market-axis-my-nudge__text">下の日程に「練習相手チーム」の申請が表示されています。<strong>詳細を確認</strong>から承認すると、初めての試合が成立します（オンボーディングの練習です）。</p>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php
                    if (!empty($my_board_schedules)) :
                        foreach ($my_board_schedules as $my_post) :
                            $sid = $my_post->ID;
                            $m_date = get_post_meta($sid, 'schedule_date', true);
                            $m_start = get_post_meta($sid, 'schedule_start_time', true);
                            $m_end = get_post_meta($sid, 'schedule_end_time', true);
                            $m_place = get_post_meta($sid, 'schedule_place', true) ?: get_post_meta($sid, 'schedule_place_option', true);
                            $m_gender = get_post_meta($sid, 'schedule_gender', true) ?: get_post_meta($sid, 'matching_gender_condition', true);
                            $venue_name = get_post_meta($sid, 'venue_name', true);
                            $matching_on = function_exists('aidunite_schedule_matching_meta_on')
                                ? aidunite_schedule_matching_meta_on($sid)
                                : (get_post_meta($sid, 'matching', true) === '1'
                                    || get_post_meta($sid, 'is_match_requested', true) === '1');
                            $can_invite = $matching_on && (in_array($m_place, ['home', 'ホーム'], true) ? !empty(trim((string) $venue_name)) : true);
                            $date_short = $m_date ? date('n/j', strtotime($m_date)) . '(' . ['日','月','火','水','木','金','土'][date('w', strtotime($m_date))] . ')' : '';
                            $time_short = trim(($m_start ?: '') . '–' . ($m_end ?: ''));
                            $place_disp = function_exists('aidunite_jp_place') ? aidunite_jp_place($m_place) : $m_place;
                            $gender_disp = function_exists('aidunite_jp_gender') ? aidunite_jp_gender($m_gender) : $m_gender;

                            $m_place_lc = strtolower(trim((string) $m_place));
                            $is_schedule_away = ($m_place_lc === 'away' || $m_place === 'アウェイ');

                            $established_list = function_exists('aidunite_get_established_requests_for_schedule')
                                ? aidunite_get_established_requests_for_schedule($sid, $current_user_team_id, ['exclude_terminal' => true])
                                : [];
                            $male_est = [];
                            $female_est = [];
                            $male_pending = [];
                            $female_pending = [];
                            $tail_est = [];
                            foreach ($established_list as $e) {
                                if (in_array($e['status'], ['rejected', '拒否済み', 'canceled', 'キャンセル済み', '相手キャンセル'], true)) {
                                    $tail_est[] = $e;
                                    continue;
                                }
                                $st_row = function_exists('aidunite_normalize_match_request_status')
                                    ? aidunite_normalize_match_request_status((string) ($e['status'] ?? ''), '')
                                    : strtolower((string) ($e['status'] ?? ''));
                                $is_pending_row = ($st_row === 'pending')
                                    || in_array($e['status'] ?? '', ['publish', 'pending', '申請中'], true)
                                    || (($e['cta_type'] ?? '') === 'approve_reject')
                                    || (($e['cta_type'] ?? '') === 'cancel_apply');
                                $gs = isset($e['gender_slot']) ? $e['gender_slot'] : '';
                                $is_male_bucket = ($gs === 'male' || ((string) ($e['gender_display'] ?? '') === '男子' && $gs !== 'female'));
                                if ($is_pending_row) {
                                    if ($is_male_bucket) {
                                        $male_pending[] = $e;
                                    } else {
                                        $female_pending[] = $e;
                                    }
                                    continue;
                                }
                                if ($is_male_bucket) {
                                    $male_est[] = $e;
                                } else {
                                    $female_est[] = $e;
                                }
                            }
                            // 募集が男子のみ／女子のみのとき、誤バケツに入った成立が「行ゼロ」で消えるのを防ぐ
                            $mg_raw = strtolower(trim((string) $m_gender));
                            $recruit_male_only = ($mg_raw === 'male' || $m_gender === '男子');
                            $recruit_female_only = ($mg_raw === 'female' || $m_gender === '女子');
                            if ($recruit_male_only && !empty($female_est)) {
                                $male_est = array_merge($male_est, $female_est);
                                $female_est = [];
                            }
                            if ($recruit_male_only && !empty($female_pending)) {
                                $male_pending = array_merge($male_pending, $female_pending);
                                $female_pending = [];
                            }
                            if ($recruit_female_only && !empty($male_est)) {
                                $female_est = array_merge($female_est, $male_est);
                                $male_est = [];
                            }
                            if ($recruit_female_only && !empty($male_pending)) {
                                $female_pending = array_merge($female_pending, $male_pending);
                                $male_pending = [];
                            }
                            $male_rem_meta = function_exists('aidunite_get_schedule_male_slots') ? (int) aidunite_get_schedule_male_slots($sid) : 0;
                            $female_rem_meta = function_exists('aidunite_get_schedule_female_slots') ? (int) aidunite_get_schedule_female_slots($sid) : 0;
                            $match_game_id = 0;
                            foreach ($established_list as $e_game) {
                                if (empty($e_game['request_id'])) {
                                    continue;
                                }
                                if (function_exists('aidunite_resolve_match_game_id_for_match_request')) {
                                    $gid_try = (int) aidunite_resolve_match_game_id_for_match_request((int) $e_game['request_id']);
                                    if ($gid_try > 0) {
                                        $match_game_id = $gid_try;
                                        break;
                                    }
                                }
                            }
                            if ($match_game_id <= 0 && !$is_schedule_away && $matching_on) {
                                $match_game_id = (int) $sid;
                            }
                            $anchor_team_for_block = ($match_game_id > 0 && function_exists('aidunite_get_anchor_team_id_for_match_game'))
                                ? (int) aidunite_get_anchor_team_id_for_match_game($match_game_id)
                                : 0;
                            $is_anchor_host_block = ($anchor_team_for_block > 0 && (int) $current_user_team_id === $anchor_team_for_block);
                            $male_empty_slots = 0;
                            $female_empty_slots = 0;
                            if ($is_anchor_host_block && function_exists('aidunite_market_board_anchor_slot_row_plan')) {
                                $male_plan = aidunite_market_board_anchor_slot_row_plan($sid, 'male', count($male_est), count($male_pending));
                                $female_plan = aidunite_market_board_anchor_slot_row_plan($sid, 'female', count($female_est), count($female_pending));
                                $male_rem_meta = (int) $male_plan['remaining'];
                                $female_rem_meta = (int) $female_plan['remaining'];
                                $male_empty_slots = (int) $male_plan['empty_slots'];
                                $female_empty_slots = (int) $female_plan['empty_slots'];
                            }
                            // ヘッダー「残」と枠行数は anchor では上記 plan で揃える。アウェイは残枠列なし。
                            $slots_disp = '';
                            if (!$is_schedule_away) {
                                if ($m_gender === 'male') {
                                    $slots_disp = '男子 残 ' . $male_rem_meta;
                                } elseif ($m_gender === 'female') {
                                    $slots_disp = '女子 残 ' . $female_rem_meta;
                                } else {
                                    $slots_disp = '男 残 ' . $male_rem_meta . ' / 女 残 ' . $female_rem_meta;
                                }
                            }
                            $is_full = ($male_rem_meta <= 0 && $female_rem_meta <= 0);
                            $show_invite_btn = $can_invite && !$is_full;
                            $slot_rows = [];
                            if ($is_anchor_host_block) {
                                if ($recruit_female_only) {
                                    foreach ($female_est as $e_row) {
                                        $slot_rows[] = ['label' => '', 'est' => $e_row];
                                    }
                                    foreach ($female_pending as $e_row) {
                                        $slot_rows[] = ['label' => '', 'est' => $e_row];
                                    }
                                    for ($ei = 0; $ei < $female_empty_slots; $ei++) {
                                        $slot_rows[] = ['label' => '', 'est' => null];
                                    }
                                } else {
                                    foreach ($male_est as $e_row) {
                                        $slot_rows[] = ['label' => '', 'est' => $e_row];
                                    }
                                    foreach ($male_pending as $e_row) {
                                        $slot_rows[] = ['label' => '', 'est' => $e_row];
                                    }
                                    for ($ei = 0; $ei < $male_empty_slots; $ei++) {
                                        $slot_rows[] = ['label' => '', 'est' => null];
                                    }
                                    if (!$recruit_male_only) {
                                        foreach ($female_est as $e_row) {
                                            $slot_rows[] = ['label' => '', 'est' => $e_row];
                                        }
                                        foreach ($female_pending as $e_row) {
                                            $slot_rows[] = ['label' => '', 'est' => $e_row];
                                        }
                                        for ($ei = 0; $ei < $female_empty_slots; $ei++) {
                                            $slot_rows[] = ['label' => '', 'est' => null];
                                        }
                                    }
                                }
                            } else {
                                $male_slot_entries = array_merge($male_est, $male_pending);
                                $female_slot_entries = array_merge($female_est, $female_pending);
                                foreach ($male_slot_entries as $e_row) {
                                    $slot_rows[] = ['label' => '', 'est' => $e_row];
                                }
                                if (!$recruit_male_only && !$recruit_female_only) {
                                    foreach ($female_slot_entries as $e_row) {
                                        $slot_rows[] = ['label' => '', 'est' => $e_row];
                                    }
                                } elseif ($recruit_female_only) {
                                    $slot_rows = [];
                                    foreach ($female_slot_entries as $e_row) {
                                        $slot_rows[] = ['label' => '', 'est' => $e_row];
                                    }
                                }
                            }
                            if (!function_exists('get_match_status_label')) {
                                require_once get_template_directory() . '/functions/match/match-common-functions.php';
                            }
                            $host_dissolve_mr_id = 0;
                            foreach ($established_list as $e_dissolve) {
                                if (empty($e_dissolve['request_id'])) {
                                    continue;
                                }
                                if (in_array($e_dissolve['status'] ?? '', ['rejected', '拒否済み', 'canceled', 'キャンセル済み', '相手キャンセル'], true)) {
                                    continue;
                                }
                                $st_dissolve = function_exists('aidunite_normalize_match_request_status')
                                    ? aidunite_normalize_match_request_status((string) ($e_dissolve['status'] ?? ''), '')
                                    : (string) ($e_dissolve['status'] ?? '');
                                if ($st_dissolve === 'established') {
                                    $host_dissolve_mr_id = (int) $e_dissolve['request_id'];
                                    break;
                                }
                            }
                            ?>
                            <div class="market-axis-my-block" data-schedule-id="<?php echo (int) $sid; ?>">
                                <div class="market-axis-my-schedule-line">
                                    <span class="market-axis-my-date"><?php echo esc_html($date_short); ?></span>
                                    <span class="market-axis-my-sep-inline" aria-hidden="true">｜</span>
                                    <span class="market-axis-my-time"><?php echo esc_html($time_short); ?></span>
                                    <span class="market-axis-my-sep-inline" aria-hidden="true">｜</span>
                                    <span class="market-axis-my-venue"><?php echo esc_html($place_disp); ?></span>
                                    <?php if ($slots_disp !== '') : ?>
                                    <span class="market-axis-my-sep-inline" aria-hidden="true">｜</span>
                                    <span class="market-axis-my-slots"><?php echo esc_html($slots_disp); ?></span>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($slot_rows) || !empty($tail_est)) : ?>
                                <div class="market-axis-my-sep" aria-hidden="true"></div>
                                <div class="market-axis-slot-list" aria-label="この日程の枠別申請状況">
                                    <?php
                                    $detail_base = home_url('/match-detail/') . '?my_schedule_id=' . (int) $sid . '&schedule_id=';
                                    foreach ($slot_rows as $slot) :
                                        $est = $slot['est'];
                                        $slot_label = $slot['label'];
                                    ?>
                                        <div class="market-axis-slot-row">
                                            <?php if ($est) :
                                                $established_detail_link = $detail_base . (int) ($est['other_schedule_id'] ?? 0);
                                                if (!empty($est['request_id'])) {
                                                    $established_detail_link .= '&match_request_id=' . (int) $est['request_id'];
                                                }
                                                $team_name_short = (function_exists('mb_strlen') && function_exists('mb_substr') && mb_strlen($est['team_name']) > 13) ? mb_substr($est['team_name'], 0, 13) . '...' : $est['team_name'];
                                                $badge_label = $est['badge_label'] ?? (function_exists('get_match_status_label') ? get_match_status_label($est['status'], 'text') : $est['status']);
                                            ?>
                                                <div class="market-axis-slot-row-line1 market-axis-row-head">
                                                    <span class="market-axis-date-time"><?php echo esc_html($date_short . '｜' . ($est['time_display'] ?? '—')); ?></span>
                                                    <span class="market-axis-badge market-axis-badge--status"><?php echo esc_html($badge_label); ?></span>
                                                </div>
                                                <div class="market-axis-slot-row-line2">
                                                    <a href="<?php echo esc_url($established_detail_link); ?>" class="market-axis-team-link" title="<?php echo esc_attr($est['team_name']); ?>"><?php echo esc_html($team_name_short); ?></a>
                                                    <span class="market-axis-sep-inline" aria-hidden="true">｜</span>
                                                    <span class="market-axis-meta"><?php echo esc_html(($est['place_display'] ?? '—') . '｜' . ($est['gender_display'] ?? '—')); ?></span>
                                                </div>
                                                <?php
                                                $post_match = is_array($est['post_match_survey'] ?? null) ? $est['post_match_survey'] : [];
                                                $post_match_state = (string) ($post_match['state'] ?? '');
                                                if ($post_match_state === 'pending') :
                                                ?>
                                                <div class="market-axis-post-match-card" role="region" aria-label="試合後アンケート">
                                                    <p class="market-axis-post-match-card__text">試合お疲れさまでした。アンケートへのご協力をお願いします。</p>
                                                </div>
                                                <?php endif; ?>
                                                <div class="market-axis-slot-row-line3">
                                                    <div class="market-axis-row-cta">
                                                        <?php if ($est['cta_type'] === 'cancel_apply') : ?>
                                                            <a href="<?php echo esc_url($established_detail_link); ?>" class="match-btn match-btn--secondary">詳細</a>
                                                            <span class="market-axis-established-sep" aria-hidden="true">｜</span>
                                                            <button type="button" class="match-btn match-btn--secondary au-open-cancel-modal market-axis-cancel-apply" data-request-id="<?php echo (int) $est['request_id']; ?>" data-team-name="<?php echo esc_attr($est['team_name']); ?>" data-cancel-type="apply">申請をキャンセル</button>
                                                        <?php elseif ($est['cta_type'] === 'approve_reject') : ?>
                                                            <a href="<?php echo esc_url($established_detail_link); ?>" class="match-btn match-btn--secondary">詳細を確認</a>
                                                        <?php elseif ($est['cta_type'] === 'cancel') : ?>
                                                            <a href="<?php echo esc_url($established_detail_link); ?>" class="match-btn match-btn--secondary">詳細</a>
                                                            <span class="market-axis-established-sep" aria-hidden="true">｜</span>
                                                            <?php
                                                            $chat_room = null;
                                                            if (!empty($est['request_id']) && function_exists('aidunite_get_game_chat_room_for_match_request')) {
                                                                $chat_room = aidunite_get_game_chat_room_for_match_request((int) $est['request_id']);
                                                            }
                                                            if (!$chat_room) {
                                                                $chat_room_id_meta = !empty($est['request_id']) ? (int) get_post_meta((int) $est['request_id'], 'chat_room_id', true) : 0;
                                                                if ($chat_room_id_meta > 0 && function_exists('aidunite_resolve_canonical_chat_room_id')) {
                                                                    $chat_room_id_meta = (int) aidunite_resolve_canonical_chat_room_id($chat_room_id_meta);
                                                                }
                                                                if ($chat_room_id_meta > 0 && function_exists('aidunite_get_chat_room')) {
                                                                    $chat_room = aidunite_get_chat_room($chat_room_id_meta);
                                                                }
                                                            }
                                                            if (!$chat_room && function_exists('aidunite_get_match_chat_room')) {
                                                                $chat_room = aidunite_get_match_chat_room($est['request_id']);
                                                            }
                                                            if (!$chat_room && function_exists('aidunite_get_schedule_chat_room')) {
                                                                $chat_room = aidunite_get_schedule_chat_room($sid);
                                                            }
                                                            $chat_url = $chat_room ? home_url('/chat?room_id=' . (int) $chat_room->id) : home_url('/match-chat?match_id=' . (int) $est['request_id']);
                                                            $suppress_bot_chat = !empty($est['request_id'])
                                                                && function_exists('aidunite_onboarding_bot_should_suppress_chat_cta')
                                                                && aidunite_onboarding_bot_should_suppress_chat_cta((int) $est['request_id']);
                                                            if (!$suppress_bot_chat) :
                                                            ?>
                                                            <a href="<?php echo esc_url($chat_url); ?>" class="match-btn match-btn--secondary">チャット</a>
                                                            <?php endif; ?>
                                                            <?php if ($post_match_state === 'pending' && !empty($post_match['url'])) : ?>
                                                            <span class="market-axis-established-sep" aria-hidden="true">｜</span>
                                                            <a href="<?php echo esc_url($post_match['url']); ?>" class="match-btn match-btn--primary" data-testid="post-match-survey-cta"><?php echo esc_html($post_match['label'] ?? '試合後アンケート'); ?></a>
                                                            <?php elseif ($post_match_state === 'answered') : ?>
                                                            <span class="market-axis-established-sep" aria-hidden="true">｜</span>
                                                            <span class="market-axis-post-match-answered"><?php echo esc_html($post_match['label'] ?? 'アンケート回答済み'); ?></span>
                                                            <?php endif; ?>
                                                            <span class="market-axis-established-sep" aria-hidden="true">｜</span>
                                                            <?php
                                                            $is_host_pair_cancel_btn = $is_anchor_host_block && empty($est['is_requester']);
                                                            $est_cancel_label = $is_host_pair_cancel_btn ? 'キャンセル' : '確定をキャンセル';
                                                            ?>
                                                            <button type="button" class="match-btn match-btn--secondary au-open-cancel-modal market-axis-established-cancel" data-request-id="<?php echo (int) $est['request_id']; ?>" data-team-name="<?php echo esc_attr($est['team_name']); ?>" data-cancel-type="established" data-cancel-scope="pair"<?php echo $is_host_pair_cancel_btn ? ' data-host-pair-cancel="1"' : ''; ?>><?php echo esc_html($est_cancel_label); ?></button>
                                                        <?php elseif ($est['cta_type'] === 'reapply') :
                                                            $slot_reapply_label = '再申請する';
                                                            $slot_reapply_hide = false;
                                                            if (!empty($est['request_id']) && !empty($est['other_schedule_id']) && function_exists('aidunite_match_detail_reapply_cta')) {
                                                                $srcta = aidunite_match_detail_reapply_cta((int) $est['other_schedule_id'], (int) $est['request_id'], (int) $sid);
                                                                if (($srcta['variant'] ?? '') === 'ineligible') {
                                                                    $slot_reapply_hide = true;
                                                                } else {
                                                                    $slot_reapply_label = $srcta['label'] ?: '再申請する';
                                                                }
                                                            }
                                                            ?>
                                                            <?php if (!$slot_reapply_hide) : ?>
                                                            <a href="<?php echo esc_url($established_detail_link . '#apply'); ?>" class="match-btn match-btn--secondary market-axis-established-reapply"><?php echo esc_html($slot_reapply_label); ?></a>
                                                            <?php endif; ?>
                                                        <?php else : ?>
                                                            <a href="<?php echo esc_url($established_detail_link); ?>" class="match-btn match-btn--secondary">詳細</a>
                                                            <span class="market-axis-established-status-text"><?php echo esc_html($badge_label); ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php else : ?>
                                                <div class="market-axis-slot-row-line2">
                                                    <span class="market-axis-slot-label"><?php echo esc_html($slot_label); ?></span>
                                                    <span class="market-axis-slot-empty"><?php echo !empty($show_my_tab_nudge) ? 'まだ申請はございません。届くとここに表示されます。' : 'まだ申請はございません。'; ?></span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                    <?php foreach ($tail_est as $est) :
                                        $established_detail_link = $detail_base . (int) ($est['other_schedule_id'] ?? 0);
                                        if (!empty($est['request_id'])) {
                                            $established_detail_link .= '&match_request_id=' . (int) $est['request_id'];
                                        }
                                        $team_name_short = (function_exists('mb_strlen') && function_exists('mb_substr') && mb_strlen($est['team_name']) > 13) ? mb_substr($est['team_name'], 0, 13) . '...' : $est['team_name'];
                                        $badge_label = $est['badge_label'] ?? (function_exists('get_match_status_label') ? get_match_status_label($est['status'], 'text') : $est['status']);
                                    ?>
                                        <div class="market-axis-slot-row market-axis-slot-row--tail">
                                            <div class="market-axis-slot-row-line1 market-axis-row-head">
                                                <span class="market-axis-date-time"><?php echo esc_html($date_short . '｜' . ($est['time_display'] ?? '—')); ?></span>
                                                <span class="market-axis-badge market-axis-badge--status"><?php echo esc_html($badge_label); ?></span>
                                            </div>
                                            <div class="market-axis-slot-row-line2">
                                                <a href="<?php echo esc_url($established_detail_link); ?>" class="market-axis-team-link" title="<?php echo esc_attr($est['team_name']); ?>"><?php echo esc_html($team_name_short); ?></a>
                                                <span class="market-axis-sep-inline" aria-hidden="true">｜</span>
                                                <span class="market-axis-meta"><?php echo esc_html(($est['place_display'] ?? '—') . '｜' . ($est['gender_display'] ?? '—')); ?></span>
                                            </div>
                                            <div class="market-axis-slot-row-line3">
                                                <div class="market-axis-row-cta">
                                                    <?php if ($est['cta_type'] === 'reapply') :
                                                        $tail_reapply_label = '再申請する';
                                                        $tail_reapply_hide = false;
                                                        if (!empty($est['request_id']) && !empty($est['other_schedule_id']) && function_exists('aidunite_match_detail_reapply_cta')) {
                                                            $trcta = aidunite_match_detail_reapply_cta((int) $est['other_schedule_id'], (int) $est['request_id'], (int) $sid);
                                                            if (($trcta['variant'] ?? '') === 'ineligible') {
                                                                $tail_reapply_hide = true;
                                                            } else {
                                                                $tail_reapply_label = $trcta['label'] ?: '再申請する';
                                                            }
                                                        }
                                                        ?>
                                                        <?php if (!$tail_reapply_hide) : ?>
                                                        <a href="<?php echo esc_url($established_detail_link . '#apply'); ?>" class="match-btn match-btn--secondary market-axis-established-reapply"><?php echo esc_html($tail_reapply_label); ?></a>
                                                        <?php endif; ?>
                                                    <?php else : ?>
                                                        <span class="market-axis-established-status-text"><?php echo esc_html($badge_label); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php else : ?>
                                <div class="market-axis-my-sep" aria-hidden="true"></div>
                                <p class="market-axis-slot-empty market-axis-my-no-applications-yet" role="status">まだ申請は一件もありません。</p>
                                <?php endif; ?>
                                <?php if ($is_anchor_host_block && $host_dissolve_mr_id > 0) : ?>
                                <div class="market-axis-my-line3" aria-label="試合を解散">
                                    <button type="button" class="match-btn match-btn--schedule-meta market-axis-block-dissolve-btn au-open-cancel-modal" data-request-id="<?php echo (int) $host_dissolve_mr_id; ?>" data-team-name="" data-cancel-type="established" data-cancel-scope="dissolve">試合を解散</button>
                                </div>
                                <?php elseif ($host_dissolve_mr_id <= 0) : ?>
                                <div class="market-axis-my-line3" aria-label="招待・編集">
                                    <?php if ($show_invite_btn) : ?>
                                        <button type="button" class="match-btn match-btn--schedule-meta market-axis-invite-btn" data-schedule-id="<?php echo (int) $sid; ?>">招待コード</button>
                                    <?php elseif ($can_invite && $is_full) : ?>
                                        <button type="button" class="match-btn match-btn--schedule-meta market-axis-invite-btn" disabled title="満員">招待コード（満員）</button>
                                    <?php elseif (!$matching_on) : ?>
                                        <button type="button" class="match-btn match-btn--schedule-meta" disabled title="対戦募集をONにすると発行できます">招待コード</button>
                                    <?php elseif (in_array($m_place, ['home', 'ホーム'], true) && empty(trim((string) $venue_name))) : ?>
                                        <button type="button" class="match-btn match-btn--schedule-meta" disabled title="会場名が未入力です（編集して入力してください）">招待コード</button>
                                    <?php endif; ?>
                                    <a href="<?php echo esc_url(home_url('/schedule-edit/?schedule_id=' . $sid)); ?>" class="match-btn match-btn--schedule-meta">編集</a>
                                </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <p class="market-axis-slot-empty market-axis-my-panel-empty" role="status">対戦マッチング用の予定がまだありません。スケジュール登録で「対戦マッチング希望」をオンにすると、ここに表示されます。</p>
                    <?php endif; ?>
                </section>
                </div><!-- /#market-tab-my -->

            <?php } ?>
        </div>

    <?php
    if (function_exists('aidunite_render_web_app_content_close')) {
        aidunite_render_web_app_content_close();
    }
    ?>

    <?php get_template_part('template-parts/onboarding-bot-chat-modal'); ?>

    <!-- 掲示板用・招待URLモーダル（別IDで衝突回避） -->
    <div class="match-modal" id="matchBoardInviteModal" aria-hidden="true">
        <div class="match-modal__backdrop" data-close-board-invite="1"></div>
        <div class="match-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="matchBoardInviteTitle">
            <div class="match-modal__header">
                <h3 class="match-modal__title" id="matchBoardInviteTitle">招待リンクを共有</h3>
                <button class="match-iconbtn" type="button" aria-label="閉じる" data-close-board-invite="1"><?php echo aidunite_render_theme_icon('close', ['width' => '20', 'height' => '20']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
            </div>
            <div class="match-modal__body">
                <p class="market-axis-invite-summary" id="market-axis-invite-summary">—</p>
                <label class="market-axis-invite-url-label">招待URL</label>
                <input type="text" id="market-axis-invite-url-input" readonly class="market-axis-invite-url-input" value="">
                <p class="market-axis-invite-expires" id="market-axis-invite-expires"></p>
                <button type="button" class="match-btn match-btn--primary" id="market-axis-invite-copy">コピー</button>
            </div>
            <div class="match-modal__footer">
                <button type="button" class="match-btn match-btn--ghost" data-close-board-invite="1">閉じる</button>
            </div>
        </div>
    </div>
<script>
(function(){
  var cfg = window.aiduniteMatchBoardConfig || {};
  var tabRecruitBtn = document.getElementById('market-tab-recruit-btn');
  var tabMyBtn = document.getElementById('market-tab-my-btn');
  var panelRecruit = document.getElementById('market-tab-recruit');
  var panelMy = document.getElementById('market-tab-my');
  var tabHint = document.getElementById('market-axis-tab-hint');
  function updateTabHint(tab) {
    if (!tabHint) return;
    tabHint.textContent = tab === 'my'
      ? '申請状況：自分の募集と申請を確認できます。相手募集を探すときは「募集中の試合」へ。'
      : '募集中の試合：相手募集を探して申請できます。自分の募集確認は「申請状況」へ。';
  }
  function showRecruitTab() {
    if (panelMy) panelMy.hidden = true;
    if (panelRecruit) panelRecruit.hidden = false;
    if (tabMyBtn) { tabMyBtn.classList.remove('active'); tabMyBtn.setAttribute('aria-selected', 'false'); }
    if (tabRecruitBtn) { tabRecruitBtn.classList.add('active'); tabRecruitBtn.setAttribute('aria-selected', 'true'); }
    updateTabHint('recruit');
  }
  function showMyTab() {
    if (panelRecruit) panelRecruit.hidden = true;
    if (panelMy) panelMy.hidden = false;
    if (tabRecruitBtn) { tabRecruitBtn.classList.remove('active'); tabRecruitBtn.setAttribute('aria-selected', 'false'); }
    if (tabMyBtn) { tabMyBtn.classList.add('active'); tabMyBtn.setAttribute('aria-selected', 'true'); }
    updateTabHint('my');
  }
  if (tabRecruitBtn) tabRecruitBtn.addEventListener('click', showRecruitTab);
  if (tabMyBtn) tabMyBtn.addEventListener('click', showMyTab);

  var nudgeRecruitBtn = document.getElementById('market-axis-my-nudge-recruit-tab');
  if (nudgeRecruitBtn) {
    nudgeRecruitBtn.addEventListener('click', function() {
      showRecruitTab();
      if (panelRecruit && typeof panelRecruit.scrollIntoView === 'function') {
        panelRecruit.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    });
  }

  (function initMarketTabFromQuery() {
    try {
      var params = new URLSearchParams(window.location.search);
      if (params.get('market_tab') === 'my') {
        showMyTab();
      } else if (cfg.initialTab === 'my') {
        showMyTab();
      } else {
        showRecruitTab();
      }
      var hlRaw = params.get('highlight_schedule');
      if (hlRaw) {
        var hl = String(hlRaw).replace(/[^0-9]/g, '');
        if (hl) {
          window.setTimeout(function() {
            var el = document.querySelector('.market-axis-my-block[data-schedule-id="' + hl + '"]');
            if (el) {
              el.scrollIntoView({ behavior: 'smooth', block: 'start' });
              el.classList.add('market-axis-debug-highlight');
              window.setTimeout(function() {
                el.classList.remove('market-axis-debug-highlight');
              }, 4000);
            }
          }, 100);
        }
      }
    } catch (e) {}
  })();

  var list = document.getElementById('market-axis-list');
  var emptyState = document.getElementById('market-axis-empty-state');
  var quickBtns = document.querySelectorAll('.market-axis-quick-btn');
  function updateEmptyStateVisibility() {
    if (!list || !emptyState) return;
    var rows = list.querySelectorAll('.market-axis-row');
    var visibleCount = 0;
    rows.forEach(function(row) { if (row.style.display !== 'none') visibleCount++; });
    var sub = document.getElementById('market-axis-empty-sub');
    var activeQuick = document.querySelector('.market-axis-quick-btn.active');
    var q = activeQuick ? activeQuick.getAttribute('data-quick') : 'all';
    if (visibleCount === 0) {
      emptyState.style.display = '';
      emptyState.setAttribute('aria-hidden', 'false');
      if (sub && rows.length > 0 && q !== 'all') {
        sub.textContent = '表示フィルターが「すべて」以外です。上部の「すべて」を選ぶと、隠れている行も含めて一覧が表示されます。';
      } else if (sub) {
        sub.textContent = '日付や条件を変更してください。';
      }
    } else {
      emptyState.style.display = 'none';
      emptyState.setAttribute('aria-hidden', 'true');
      if (sub) {
        sub.textContent = '日付や条件を変更してください。';
      }
    }
  }
  if (list && quickBtns.length) {
    quickBtns.forEach(function(btn) {
      btn.addEventListener('click', function() {
        quickBtns.forEach(function(b) { b.classList.remove('active'); });
        btn.classList.add('active');
        var q = btn.getAttribute('data-quick');
        var rows = list.querySelectorAll('.market-axis-row');
        rows.forEach(function(row) {
          var state = row.getAttribute('data-state');
          var show = (q === 'all') || (q === state);
          row.style.display = show ? '' : 'none';
        });
        updateEmptyStateVisibility();
      });
    });
  }
  var inviteModal = document.getElementById('matchBoardInviteModal');
  var inviteInput = document.getElementById('market-axis-invite-url-input');
  var inviteSummary = document.getElementById('market-axis-invite-summary');
  var inviteExpires = document.getElementById('market-axis-invite-expires');
  var inviteCopyBtn = document.getElementById('market-axis-invite-copy');
  var container = document.querySelector('.page-match-board-own');
  var restUrl = container ? (container.getAttribute('data-rest-url') || '').replace(/\/$/, '') : '';
  var restNonce = container ? container.getAttribute('data-wp-rest-nonce') : '';
  function openBoardInviteModal() {
    if (inviteModal) {
      inviteModal.setAttribute('aria-hidden', 'false');
      document.body.style.overflow = 'hidden';
    }
  }
  function closeBoardInviteModal() {
    if (inviteModal) {
      inviteModal.setAttribute('aria-hidden', 'true');
      document.body.style.overflow = '';
      document.documentElement.style.overflow = '';
    }
  }
  document.addEventListener('click', function(e) {
    var inviteBtn = e.target.closest('.market-axis-invite-btn:not([disabled])');
    if (inviteBtn && restUrl && restNonce) {
      var scheduleId = inviteBtn.getAttribute('data-schedule-id');
      if (!scheduleId) return;
      inviteBtn.disabled = true;
      inviteSummary.textContent = '取得中...';
      if (inviteInput) inviteInput.value = '';
      if (inviteExpires) inviteExpires.textContent = '';
      fetch(restUrl + '/generate-invite-url', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': restNonce },
        body: JSON.stringify({ schedule_id: parseInt(scheduleId, 10) }),
        credentials: 'same-origin'
      }).then(function(r) { return r.json(); }).then(function(data) {
        inviteBtn.disabled = false;
        if (data && data.success && data.invite_url) {
          if (inviteSummary) inviteSummary.textContent = (data.schedule_date || '') + ' ' + (data.schedule_start || '') + '–' + (data.schedule_end || '') + ' 会場: ' + (data.venue_opponent_label || '');
          if (inviteInput) inviteInput.value = data.invite_url;
          if (inviteExpires) inviteExpires.textContent = '有効期限: ' + (data.expires_at || '72時間');
          openBoardInviteModal();
          if (inviteCopyBtn) inviteCopyBtn.onclick = function() {
            inviteInput.select();
            document.execCommand('copy');
            inviteCopyBtn.textContent = 'コピーしました';
            setTimeout(function() { inviteCopyBtn.textContent = 'コピー'; }, 2000);
          };
        } else {
          inviteSummary.textContent = data && data.message ? data.message : '招待URLの生成に失敗しました';
        }
      }).catch(function() {
        inviteBtn.disabled = false;
        if (inviteSummary) inviteSummary.textContent = '通信エラーです';
      });
      return;
    }
    if (e.target.closest('[data-close-board-invite="1"]')) { closeBoardInviteModal(); return; }
  });
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && inviteModal && inviteModal.getAttribute('aria-hidden') === 'false') closeBoardInviteModal();
  });
})();
</script>

    <!-- キャンセル確認モーダル（自分が申請した申請を取り下げる） -->
    <div class="match-modal" id="matchCancelModal" aria-hidden="true">
        <div class="match-modal__backdrop" data-close-cancel-modal="1"></div>
        <div class="match-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="matchCancelTitle">
            <div class="match-modal__header">
                <h3 class="match-modal__title" id="matchCancelTitle">申請をキャンセルしますか？</h3>
                <button class="match-iconbtn" type="button" aria-label="閉じる" data-close-cancel-modal="1"><?php echo aidunite_render_theme_icon('close', ['width' => '20', 'height' => '20']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
            </div>
            <div class="match-modal__body">
                <p class="match-modal__message"><span data-cancel-team>—</span> への申請を取り下げます。相手に通知が送られます。</p>
            </div>
            <div class="match-modal__footer">
                <button class="match-btn match-btn--ghost" type="button" data-close-cancel-modal="1">閉じる</button>
                <button class="match-btn match-btn--danger" type="button" id="matchCancelSubmit">キャンセルする</button>
            </div>
        </div>
    </div>

<script>
(function(){
  var container = document.querySelector('.page-match-board-own');
  var nonce = <?php echo json_encode(wp_create_nonce('au_match_nonce')); ?>;
  var ajaxUrl = <?php echo json_encode(admin_url('admin-ajax.php')); ?>;

  function resolveAjaxNonce() {
    // 1) サーバー埋め込み値（最優先）
    if (nonce && String(nonce).trim() !== '') return String(nonce).trim();

    // 2) ルート要素の data 属性
    if (container) {
      var attr = container.getAttribute('data-au-match-nonce');
      if (attr && String(attr).trim() !== '') return String(attr).trim();
    }

    // 3) hidden input（互換）
    var hidden = document.getElementById('au_match_nonce');
    if (hidden && hidden.value && String(hidden.value).trim() !== '') return String(hidden.value).trim();

    return '';
  }

  function openCancelModal(payload) {
    var m = document.getElementById('matchCancelModal');
    if (!m) return;
    var teamEl = m.querySelector('[data-cancel-team]');
    if (teamEl) teamEl.textContent = payload.teamName || '—';
    var titleEl = m.querySelector('#matchCancelTitle');
    var msgEl = m.querySelector('.match-modal__message');
    var submitBtn = m.querySelector('#matchCancelSubmit');
    var isEst = payload.cancelType === 'established';
    var scope = payload.cancelScope || 'pair';
    var isDissolve = isEst && scope === 'dissolve';
    var isHostPair = isEst && scope === 'pair' && payload.isHostPairCancel;
    if (titleEl) {
      titleEl.textContent = isDissolve ? 'ゲームを解散しますか？' : (isEst ? (isHostPair ? 'キャンセルしますか？' : '確定をキャンセルしますか？') : '申請をキャンセルしますか？');
    }
    if (msgEl) {
      if (isDissolve) {
        msgEl.textContent = 'この日程の試合をすべてキャンセルし、募集を初期状態に戻します。参加していた全チームに通知が送られます。';
      } else if (isEst) {
        msgEl.textContent = (payload.teamName || '—') + (isHostPair ? ' との試合確定のみを取り消します。他の確定チームはそのまま残ります。' : ' との試合確定を取り消します。相手に通知が送られます。');
      } else {
        msgEl.textContent = (payload.teamName || '—') + ' への申請を取り下げます。相手に通知が送られます。';
      }
    }
    if (submitBtn) submitBtn.textContent = isDissolve ? 'ゲームを解散する' : 'キャンセルする';
    m.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
    m._payload = { requestId: payload.requestId, cancelScope: scope };
  }

  function closeCancelModal() {
    var m = document.getElementById('matchCancelModal');
    if (m) { m.setAttribute('aria-hidden', 'true'); m._payload = null; }
    document.body.style.overflow = '';
    document.documentElement.style.overflow = '';
  }

  function sendStatusUpdate(requestId, status, submitBtn, doneLabel, cancelScope) {
    var requestKey = String(requestId) + ':' + String(status) + ':' + String(cancelScope || 'pair');
    window.__aiduniteBoardStatusInFlight = window.__aiduniteBoardStatusInFlight || {};
    if (window.__aiduniteBoardStatusInFlight[requestKey]) {
      return;
    }
    window.__aiduniteBoardStatusInFlight[requestKey] = true;
    if (!requestId) {
      window.__aiduniteBoardStatusInFlight[requestKey] = false;
      alert('対象の申請IDを取得できませんでした。ページを再読み込みしてください。');
      return;
    }
    var currentNonce = resolveAjaxNonce();
    if (!currentNonce) {
      window.__aiduniteBoardStatusInFlight[requestKey] = false;
      alert('認証情報の読み込みに失敗しました。ページを再読み込みしてください。');
      return;
    }
    var fd = new FormData();
    fd.append('action', 'au_update_match_request_status');
    fd.append('security', currentNonce);
    fd.append('request_id', String(requestId));
    fd.append('status', status);
    if (status === 'canceled') {
      fd.append('cancel_scope', cancelScope || 'pair');
    }
    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.textContent = '送信中...';
    }
    fetch(ajaxUrl, { method: 'POST', body: fd })
      .then(function(r) {
        return r.text().then(function(txt) {
          var json = null;
          try { json = JSON.parse(txt); } catch (e) {}
          return { ok: r.ok, status: r.status, statusText: r.statusText, json: json, text: txt };
        });
      })
      .then(function(res) {
        var json = res && res.json ? res.json : null;
        if (json && json.success) {
          if (submitBtn) submitBtn.textContent = doneLabel || '完了';
          var data = json.data || {};
          if (status === 'accepted' && data.show_onboarding_bot_chat_modal
              && typeof window.aiduniteShowOnboardingBotChatModal === 'function') {
            window.aiduniteShowOnboardingBotChatModal(data.chat_url || '');
            return;
          }
          setTimeout(function() { location.reload(); }, 400);
          return;
        }
        window.__aiduniteBoardStatusInFlight[requestKey] = false;
        if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = submitBtn.getAttribute('data-original-text') || '送信'; }
        var errMsg = '';
        if (json && json.data) {
          errMsg = (typeof json.data === 'string') ? json.data : (json.data.message || '');
        }
        if (!errMsg && json && json.message) {
          errMsg = String(json.message);
        }
        if (!errMsg && json && json.data && json.data.code) {
          errMsg = 'エラーコード: ' + String(json.data.code);
        }
        if (!errMsg && res && res.text) {
          errMsg = String(res.text).replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
        }
        if (!errMsg) {
          var httpLabel = (res && res.status) ? ('HTTP ' + String(res.status) + (res.statusText ? (' ' + res.statusText) : '')) : '';
          errMsg = httpLabel ? ('処理に失敗しました（' + httpLabel + '）') : '処理に失敗しました';
        }
        alert(errMsg);
      })
      .catch(function() {
        window.__aiduniteBoardStatusInFlight[requestKey] = false;
        if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = submitBtn.getAttribute('data-original-text') || '送信'; }
        alert('通信エラーが発生しました');
      });
  }

  document.addEventListener('click', function(e) {
    var openCancel = e.target.closest('.au-open-cancel-modal');
    if (openCancel) {
      var cancelScope = openCancel.getAttribute('data-cancel-scope') || 'pair';
      openCancelModal({
        requestId: openCancel.getAttribute('data-request-id'),
        teamName: openCancel.getAttribute('data-team-name'),
        cancelType: openCancel.getAttribute('data-cancel-type') || 'apply',
        cancelScope: cancelScope,
        isHostPairCancel: openCancel.getAttribute('data-host-pair-cancel') === '1'
      });
      return;
    }
    if (e.target.closest('[data-close-cancel-modal="1"]')) { closeCancelModal(); return; }
    var cancelSubmit = e.target.closest('#matchCancelSubmit');
    if (cancelSubmit) {
      var m = document.getElementById('matchCancelModal');
      if (m && m._payload && m._payload.requestId) {
        var orig = cancelSubmit.getAttribute('data-original-text');
        if (!orig) { cancelSubmit.setAttribute('data-original-text', cancelSubmit.textContent); }
        sendStatusUpdate(m._payload.requestId, 'canceled', cancelSubmit, 'キャンセルしました', m._payload.cancelScope || 'pair');
      } else {
        alert('キャンセル対象を取得できませんでした。ページを再読み込みしてお試しください。');
      }
      return;
    }
  });

  document.addEventListener('keydown', function(e) {
    if (e.key !== 'Escape') return;
    var cancelM = document.getElementById('matchCancelModal');
    if (cancelM && cancelM.getAttribute('aria-hidden') === 'false') { closeCancelModal(); }
  });
})();
</script>

</div>
<?php get_footer();
