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
<?php
$au_match_board_rest_url = esc_url(get_rest_url(null, 'aidunite/v1'));
$au_match_board_rest_nonce = wp_create_nonce('wp_rest');
$initial_market_tab = 'recruit';
if (isset($_GET['market_tab'])) {
    $tab_raw = sanitize_text_field(wp_unslash($_GET['market_tab']));
    if (in_array($tab_raw, ['my', 'recruit', 'completed'], true)) {
        $initial_market_tab = $tab_raw;
    }
}
$hero_title = '試合一覧';
$hero_subtitle = '募集中の試合を確認して、条件に合う相手へ申請できます。';
if ($initial_market_tab === 'my') {
    $hero_subtitle = '自分が出した募集と申請の状況を確認できます。';
} elseif ($initial_market_tab === 'completed') {
    $hero_subtitle = '終了した試合と試合後アンケートを確認できます。';
}
?>
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
        aidunite_render_web_app_content_open('ainy-webapp-content--match-board');
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
                $my_tab_schedules_all = function_exists('aidunite_match_board_get_my_tab_schedule_posts')
                    ? aidunite_match_board_get_my_tab_schedule_posts((int) $current_user_team_id)
                    : $my_board_schedules;
                $my_quick_filter = 'all';
                if (isset($_GET['my_quick'])) {
                    $my_quick_raw = sanitize_text_field(wp_unslash($_GET['my_quick']));
                    if (in_array($my_quick_raw, ['all', 'action', 'has_mr'], true)) {
                        $my_quick_filter = $my_quick_raw;
                    }
                }
                $my_tab_schedules = function_exists('aidunite_match_board_filter_my_tab_schedule_posts')
                    ? aidunite_match_board_filter_my_tab_schedule_posts(
                        is_array($my_tab_schedules_all) ? $my_tab_schedules_all : [],
                        (int) $current_user_team_id,
                        $my_quick_filter
                    )
                    : $my_tab_schedules_all;
                $is_onboarding_mission_ui = function_exists('aidunite_board_onboarding_mission_active')
                    && aidunite_board_onboarding_mission_active((int) $current_user_team_id);
                if ($is_onboarding_mission_ui && function_exists('aidunite_onboarding_bot_ensure_for_team')) {
                    aidunite_onboarding_bot_ensure_for_team((int) $current_user_team_id);
                }
                $board_tab_badges = function_exists('aidunite_match_board_read_tab_badges_payload')
                    ? aidunite_match_board_read_tab_badges_payload((int) $current_user_team_id, (int) $current_user->ID)
                    : ['completed_survey_pending' => 0];
                $completed_survey_pending = (int) ($board_tab_badges['completed_survey_pending'] ?? 0);
                $completed_blocks = function_exists('aidunite_match_board_get_completed_blocks_payload')
                    ? aidunite_match_board_get_completed_blocks_payload((int) $current_user_team_id, (int) $current_user->ID)
                    : [];
                $board_card_seen_map = function_exists('aidunite_user_read_market_board_card_seen')
                    ? aidunite_user_read_market_board_card_seen((int) $current_user->ID)
                    : [];
                if (function_exists('aidunite_enqueue_onboarding_bot_chat_modal_assets')) {
                    $board_bot_chat_url = '';
                    $board_team_display = function_exists('aidunite_team_get_display_bundle')
                        ? aidunite_team_get_display_bundle((int) $current_user_team_id)
                        : [];
                    $board_bot_mr = (int) ($board_team_display['onboarding_bot_mr_id'] ?? 0);
                    if ($board_bot_mr > 0 && function_exists('aidunite_onboarding_bot_get_chat_url_for_request')) {
                        $board_bot_chat_url = aidunite_onboarding_bot_get_chat_url_for_request($board_bot_mr);
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
                    : home_url('/mypage/?open_recruit=1');
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
                        $other_dn_dbg = function_exists('aidunite_schedule_read_normalized_date')
                            ? aidunite_schedule_read_normalized_date((int) $other_post->ID)
                            : '';
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
                    $disp_a = function_exists('aidunite_schedule_get_market_list_display')
                        ? aidunite_schedule_get_market_list_display((int) $oa->ID)
                        : [];
                    $disp_b = function_exists('aidunite_schedule_get_market_list_display')
                        ? aidunite_schedule_get_market_list_display((int) $ob->ID)
                        : [];
                    $da = (string) ($disp_a['date'] ?? (function_exists('aidunite_schedule_read_normalized_date')
                        ? aidunite_schedule_read_normalized_date((int) $oa->ID)
                        : ''));
                    $db = (string) ($disp_b['date'] ?? (function_exists('aidunite_schedule_read_normalized_date')
                        ? aidunite_schedule_read_normalized_date((int) $ob->ID)
                        : ''));
                    $sa = (string) ($disp_a['start_time'] ?? '');
                    $sb = (string) ($disp_b['start_time'] ?? '');
                    $order_a = $state_order[$a['state']] ?? 5;
                    $order_b = $state_order[$b['state']] ?? 5;
                    if ($order_a !== $order_b) return $order_a - $order_b;
                    if ($da !== $db) return strcmp($da, $db);
                    return strcmp($sa, $sb);
                });

                $market_list_page_var = 'mb_paged';
                $market_per_page = 10;
                $quick_filter = isset($_GET['quick']) ? sanitize_text_field(wp_unslash($_GET['quick'])) : 'all';
                $allowed_quick = ['all', 'best', 'green', 'yellow', 'no_preference'];
                if (!in_array($quick_filter, $allowed_quick, true)) {
                    $quick_filter = 'all';
                }

                $filtered_rows = $rows;
                if ($quick_filter !== 'all') {
                    $filtered_rows = array_values(array_filter($rows, function ($row) use ($quick_filter) {
                        return ($row['state'] ?? '') === $quick_filter;
                    }));
                }

                $total_recruit_rows = count($filtered_rows);
                $total_recruit_pages = $total_recruit_rows > 0 ? (int) ceil($total_recruit_rows / $market_per_page) : 1;
                $requested_recruit_page = 0;
                if (isset($_GET[$market_list_page_var])) {
                    $requested_recruit_page = (int) $_GET[$market_list_page_var];
                } elseif (isset($_GET['paged'])) {
                    $requested_recruit_page = (int) $_GET['paged'];
                }
                $current_recruit_page = $requested_recruit_page > 0 ? max(1, min($requested_recruit_page, $total_recruit_pages)) : 1;
                $recruit_offset = ($current_recruit_page - 1) * $market_per_page;
                $rows_page = array_slice($filtered_rows, $recruit_offset, $market_per_page);

                $market_pagination_args = array_filter([
                    'date_from' => $date_from,
                    'date_to' => $date_to,
                    'filter_gender' => $filter_gender !== '' ? $filter_gender : null,
                    'quick' => $quick_filter !== 'all' ? $quick_filter : null,
                ]);
                $market_pagination_base = add_query_arg($market_pagination_args, get_permalink());

                if ($total_recruit_rows > 0 && $rows_page === [] && $current_recruit_page > 1) {
                    wp_safe_redirect(add_query_arg(
                        array_merge($market_pagination_args, [$market_list_page_var => 1]),
                        get_permalink()
                    ));
                    exit;
                }
                ?>

                <!-- ピルタブ: 募集中の試合 | 申請状況 | 試合完了 -->
                <nav class="market-axis-pill-tabs" role="tablist" aria-label="募集中の試合・申請状況・試合完了">
                    <button type="button" class="market-axis-pill-tab active" role="tab" aria-selected="true" aria-controls="market-tab-recruit" id="market-tab-recruit-btn">募集中の試合</button>
                    <button type="button" class="market-axis-pill-tab" role="tab" aria-selected="false" aria-controls="market-tab-my" id="market-tab-my-btn">申請状況</button>
                    <button type="button" class="market-axis-pill-tab" role="tab" aria-selected="false" aria-controls="market-tab-completed" id="market-tab-completed-btn">
                        <span class="market-axis-pill-tab-label">試合完了</span>
                        <?php if ($completed_survey_pending > 0) : ?>
                        <span class="market-axis-pill-tab-badge" id="market-tab-completed-badge" aria-label="未回答のアンケート <?php echo (int) $completed_survey_pending; ?> 件"><?php echo (int) $completed_survey_pending; ?></span>
                        <?php else : ?>
                        <span class="market-axis-pill-tab-badge market-axis-pill-tab-badge--hidden" id="market-tab-completed-badge" aria-hidden="true"></span>
                        <?php endif; ?>
                    </button>
                </nav>

                <!-- タブパネル: 募集中の試合 -->
                <div id="market-tab-recruit" class="market-axis-tab-panel" role="tabpanel" aria-labelledby="market-tab-recruit-btn">
                <!-- フィルター（折り畳み式） -->
                <details class="market-axis-filters-wrap" id="market-axis-filters-details">
                    <summary class="market-axis-filters-summary">フィルター</summary>
                    <form method="get" class="market-axis-filters" id="market-axis-filters">
                        <?php if ($quick_filter !== 'all') : ?>
                        <input type="hidden" name="quick" value="<?php echo esc_attr($quick_filter); ?>">
                        <?php endif; ?>
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
                        <button type="button" class="match-btn market-axis-quick-btn<?php echo $quick_filter === 'all' ? ' active' : ''; ?>" data-quick="all">すべて</button>
                        <button type="button" class="match-btn market-axis-quick-btn<?php echo $quick_filter === 'best' ? ' active' : ''; ?>" data-quick="best"><?php echo aidunite_render_theme_icon('star', ['width' => '16', 'height' => '16'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> ベストマッチ</button>
                        <button type="button" class="match-btn market-axis-quick-btn<?php echo $quick_filter === 'green' ? ' active' : ''; ?>" data-quick="green">成立可能</button>
                        <button type="button" class="match-btn market-axis-quick-btn<?php echo $quick_filter === 'yellow' ? ' active' : ''; ?>" data-quick="yellow">条件調整</button>
                        <button type="button" class="match-btn market-axis-quick-btn<?php echo $quick_filter === 'no_preference' ? ' active' : ''; ?>" data-quick="no_preference">希望未登録</button>
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
                <ul class="market-axis-list market-axis-list--recruit-grid" id="market-axis-list" aria-label="募集中の試合一覧">
                    <?php
                    foreach ($rows_page as $row) {
                        if (function_exists('aidunite_match_board_enrich_recruit_row')) {
                            $other_post = $row['other'] ?? null;
                            $other_id_dbg = ($other_post instanceof WP_Post) ? (int) $other_post->ID : 0;
                            $req_id_dbg = 0;
                            if ($other_id_dbg > 0 && function_exists('aidunite_get_application_status')) {
                                $other_team_dbg = function_exists('aidunite_schedule_read_team_id')
                                    ? (int) aidunite_schedule_read_team_id($other_id_dbg)
                                    : 0;
                                $best_my_dbg = (int) ($row['best_my_schedule_id'] ?? 0);
                                if ($other_team_dbg > 0) {
                                    $st_dbg = aidunite_get_application_status((int) $current_user_team_id, $best_my_dbg, $other_team_dbg, $other_id_dbg);
                                    $req_id_dbg = (int) ($st_dbg['request_id'] ?? 0);
                                }
                            }
                            $row = aidunite_match_board_enrich_recruit_row(
                                $row,
                                (int) $current_user_team_id,
                                (int) $current_user->ID,
                                $req_id_dbg,
                                $board_card_seen_map
                            );
                        }
                        get_template_part(
                            'template-parts/match/market-axis-recruit-row',
                            null,
                            [
                                'row' => $row,
                                'viewer_team_id' => (int) $current_user_team_id,
                            ]
                        );
                    }
                    ?>
                </ul>
                <?php if ($total_recruit_rows > 0) : ?>
                <p class="market-axis-pagination-info" role="status">
                    全 <?php echo (int) $total_recruit_rows; ?> 件中
                    <?php echo (int) ($recruit_offset + 1); ?>–<?php echo (int) min($recruit_offset + $market_per_page, $total_recruit_rows); ?> 件を表示
                </p>
                <?php endif; ?>
                <?php if ($total_recruit_pages > 1) : ?>
                <nav class="market-axis-pagination" aria-label="ページ送り">
                    <?php
                    echo paginate_links([
                        'base' => $market_pagination_base . '%_%',
                        'format' => '?' . $market_list_page_var . '=%#%',
                        'current' => $current_recruit_page,
                        'total' => $total_recruit_pages,
                        'prev_text' => '&laquo; 前へ',
                        'next_text' => '次へ &raquo;',
                        'mid_size' => 1,
                        'end_size' => 1,
                    ]);
                    ?>
                </nav>
                <?php endif; ?>
                <!-- 該当0件時の空状態（カード表示） -->
                <div class="market-axis-empty-card" id="market-axis-empty-state" role="status" aria-live="polite"<?php echo empty($filtered_rows) ? '' : ' style="display:none;" aria-hidden="true"'; ?>>
                    <p class="market-axis-empty-card__text">該当する対戦募集はありません。</p>
                    <p class="market-axis-empty-card__sub" id="market-axis-empty-sub"><?php echo $quick_filter !== 'all' ? '表示フィルターや日付・条件を変更してください。' : '日付や条件を変更してください。'; ?></p>
                </div>
                </div><!-- /#market-tab-recruit -->

                <!-- タブパネル: 申請状況（自分の予定・招待URL・成立一覧） -->
                <div id="market-tab-my" class="market-axis-tab-panel market-axis-tab-panel--hidden" role="tabpanel" aria-labelledby="market-tab-my-btn" hidden>
                <section class="market-axis-my-panel">
                    <div class="market-axis-quick-filters market-axis-my-quick-filters" role="group" aria-label="申請状況の表示フィルター">
                        <button type="button" class="match-btn market-axis-my-quick-btn<?php echo $my_quick_filter === 'all' ? ' active' : ''; ?>" data-my-quick="all">すべて</button>
                        <button type="button" class="match-btn market-axis-my-quick-btn<?php echo $my_quick_filter === 'action' ? ' active' : ''; ?>" data-my-quick="action">要対応</button>
                        <button type="button" class="match-btn market-axis-my-quick-btn<?php echo $my_quick_filter === 'has_mr' ? ' active' : ''; ?>" data-my-quick="has_mr">申請あり</button>
                    </div>
                    <?php
                    if (function_exists('aidunite_market_board_render_my_tab_nudge_html')) {
                        $my_tab_nudge_html = aidunite_market_board_render_my_tab_nudge_html(
                            (int) $current_user_team_id,
                            is_array($my_tab_schedules_all) ? $my_tab_schedules_all : []
                        );
                        if ($my_tab_nudge_html !== '') {
                            echo $my_tab_nudge_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        }
                    }
                    if (empty($my_tab_schedules) && $my_quick_filter !== 'all') :
                        ?>
                    <p class="market-axis-my-filter-empty" role="status">該当する日程はありません。フィルターを変更してください。</p>
                        <?php
                    endif;
                    if (!empty($my_tab_schedules)) :
                        foreach ($my_tab_schedules as $my_post) :
                            $sid = $my_post->ID;
                            $m_disp = function_exists('aidunite_schedule_get_market_list_display')
                                ? aidunite_schedule_get_market_list_display((int) $sid)
                                : [];
                            $m_date = (string) ($m_disp['date'] ?? '');
                            $m_start = (string) ($m_disp['start_time'] ?? '');
                            $m_end = (string) ($m_disp['end_time'] ?? '');
                            $m_place = (string) ($m_disp['place'] ?? '');
                            $m_gender = (string) ($m_disp['gender'] ?? '');
                            $venue_name = (string) ($m_disp['venue_name'] ?? '');
                            $matching_on = function_exists('aidunite_schedule_matching_meta_on')
                                ? aidunite_schedule_matching_meta_on($sid)
                                : (($m_disp['matching'] ?? '') === '1');
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
                            $schedule_edit_url = function_exists('aidunite_get_schedule_edit_url')
                                ? aidunite_get_schedule_edit_url((int) $sid)
                                : home_url('/schedule-management/?edit_schedule=' . (int) $sid);
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
                                <?php
                                get_template_part('template-parts/match/market-axis-my-schedule-header', null, [
                                    'date_short' => $date_short,
                                    'time_short' => $time_short,
                                    'place_disp' => $place_disp,
                                    'slots_disp' => $slots_disp,
                                    'is_full' => $is_full,
                                    'is_schedule_away' => $is_schedule_away,
                                    'schedule_id' => (int) $sid,
                                    'edit_url' => $schedule_edit_url,
                                    'show_invite_btn' => $show_invite_btn,
                                    'can_invite' => $can_invite,
                                    'matching_on' => $matching_on,
                                    'm_place' => $m_place,
                                    'venue_name' => $venue_name,
                                    'host_dissolve_mr_id' => $host_dissolve_mr_id,
                                    'is_anchor_host_block' => $is_anchor_host_block,
                                ]);
                                ?>
                                <?php if (!empty($slot_rows) || !empty($tail_est)) : ?>
                                <ul class="market-axis-list market-axis-list--recruit-grid market-axis-my-slot-list" aria-label="この日程の枠別申請状況">
                                    <?php
                                    $detail_base = home_url('/match-detail/') . '?my_schedule_id=' . (int) $sid . '&schedule_id=';
                                    $slot_row_args_base = [
                                        'my_schedule_id' => (int) $sid,
                                        'viewer_team_id' => (int) $current_user_team_id,
                                        'detail_base' => $detail_base,
                                        'is_anchor_host_block' => $is_anchor_host_block,
                                        'show_my_tab_nudge' => !empty($is_onboarding_mission_ui),
                                        'board_card_seen_map' => $board_card_seen_map,
                                        'tab_context' => 'my',
                                        'parent_date_short' => $date_short,
                                        'parent_time_short' => $time_short,
                                    ];
                                    foreach ($slot_rows as $slot) {
                                        get_template_part('template-parts/match/market-axis-my-slot-row', null, array_merge($slot_row_args_base, [
                                            'est' => $slot['est'],
                                            'slot_label' => (string) ($slot['label'] ?? ''),
                                        ]));
                                    }
                                    foreach ($tail_est as $est) {
                                        get_template_part('template-parts/match/market-axis-my-slot-row', null, array_merge($slot_row_args_base, [
                                            'est' => $est,
                                            'is_tail' => true,
                                        ]));
                                    }
                                    ?>
                                </ul>
                                <?php else : ?>
                                <p class="market-axis-slot-empty market-axis-my-no-applications-yet" role="status">まだ申請はありません。</p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <p class="market-axis-slot-empty market-axis-my-panel-empty" role="status">対戦マッチング用の予定がまだありません。スケジュール登録で「対戦マッチング希望」をオンにすると、ここに表示されます。</p>
                    <?php endif; ?>
                </section>
                </div><!-- /#market-tab-my -->

                <!-- タブパネル: 試合完了 -->
                <div id="market-tab-completed" class="market-axis-tab-panel market-axis-tab-panel--hidden" role="tabpanel" aria-labelledby="market-tab-completed-btn" hidden>
                <section class="market-axis-my-panel market-axis-completed-panel">
                    <?php if (!empty($completed_blocks)) : ?>
                        <?php foreach ($completed_blocks as $completed_block) :
                            $cb_sid = (int) ($completed_block['schedule_id'] ?? 0);
                            $cb_slot_rows = is_array($completed_block['slot_rows'] ?? null) ? $completed_block['slot_rows'] : [];
                            $cb_detail_base = home_url('/match-detail/') . '?my_schedule_id=' . $cb_sid . '&schedule_id=';
                            ?>
                            <div class="market-axis-my-block market-axis-my-block--completed" data-schedule-id="<?php echo (int) $cb_sid; ?>">
                                <?php
                                get_template_part('template-parts/match/market-axis-my-schedule-header', null, [
                                    'date_short' => (string) ($completed_block['date_short'] ?? ''),
                                    'time_short' => (string) ($completed_block['time_short'] ?? ''),
                                    'place_disp' => (string) ($completed_block['place_disp'] ?? ''),
                                    'slots_disp' => '',
                                    'is_full' => false,
                                    'is_schedule_away' => !empty($completed_block['is_schedule_away']),
                                ]);
                                ?>
                                <div class="market-axis-my-slot-list" aria-label="この日程の完了した試合">
                                    <?php
                                    foreach ($cb_slot_rows as $slot) {
                                        get_template_part('template-parts/match/market-axis-my-slot-row', null, [
                                            'my_schedule_id' => $cb_sid,
                                            'viewer_team_id' => (int) $current_user_team_id,
                                            'detail_base' => $cb_detail_base,
                                            'is_anchor_host_block' => false,
                                            'show_my_tab_nudge' => false,
                                            'board_card_seen_map' => $board_card_seen_map,
                                            'tab_context' => 'completed',
                                            'est' => $slot['est'] ?? null,
                                            'slot_label' => (string) ($slot['label'] ?? ''),
                                            'is_tail' => !empty($slot['is_tail']),
                                            'is_muted' => !empty($slot['is_muted']),
                                        ]);
                                    }
                                    ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <p class="market-axis-slot-empty market-axis-my-panel-empty" role="status">終了した試合はまだありません。試合が終了すると、ここに表示されます。</p>
                    <?php endif; ?>
                </section>
                </div><!-- /#market-tab-completed -->

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

</div>
<?php
$match_board_page_js = get_stylesheet_directory() . '/assets/js/pages/match-board-page.js';
if (is_readable($match_board_page_js)) {
    $board_onboarding_payload = ($current_user_team_id && function_exists('aidunite_board_onboarding_modals_payload'))
        ? aidunite_board_onboarding_modals_payload((int) $current_user_team_id)
        : ['active' => false];
    if (!empty($board_onboarding_payload['active']) && function_exists('aidunite_enqueue_board_onboarding_modal_assets')) {
        aidunite_enqueue_board_onboarding_modal_assets();
    }
    wp_localize_script('aidunite-match-board-page', 'aiduniteMatchBoardConfig', [
        'restUrl' => $au_match_board_rest_url,
        'restNonce' => $au_match_board_rest_nonce,
        'initialTab' => $initial_market_tab,
        'onboarding' => $board_onboarding_payload,
    ]);
    wp_localize_script('aidunite-match-board-page', 'aiduniteMatchBoardPage', [
        'matchNonce' => wp_create_nonce('au_match_nonce'),
        'ajaxUrl' => admin_url('admin-ajax.php'),
    ]);
}
get_footer();
