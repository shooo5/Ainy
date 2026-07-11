<?php
/**
 * Webアプリ共通ナビゲーション（ボトムナビ・ハンバーガーメニュー）
 *
 * @package AidUnite
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 統一ボトムナビ（ダーク・シェル幅）を使うか（表示時は常に true）
 */
function aidunite_should_use_app_bottom_nav() {
    return function_exists('aidunite_should_show_bottom_nav') && aidunite_should_show_bottom_nav();
}

/**
 * ボトムナビを表示するか
 */
function aidunite_should_show_bottom_nav() {
    if (!is_user_logged_in()) {
        return false;
    }
    if (!empty($GLOBALS['aidunite_team_chat_page'])) {
        return false;
    }
    if (function_exists('aidunite_is_team_registration_complete_request')
        && aidunite_is_team_registration_complete_request()) {
        return false;
    }
    $is_web_app = function_exists('aidunite_is_web_app_page') && aidunite_is_web_app_page();

    return wp_is_mobile() || $is_web_app || is_front_page();
}

/**
 * ボトムナビ表示時の body クラス（下端余白・ナビ見た目の単一フック）
 *
 * @param string[] $classes
 * @return string[]
 */
function aidunite_bottom_nav_body_class($classes) {
    if (!aidunite_should_show_bottom_nav()) {
        return $classes;
    }
    $classes[] = 'ainy-has-bottom-nav';

    return $classes;
}
add_filter('body_class', 'aidunite_bottom_nav_body_class', 26);

/**
 * ボトムナビ用アイコン（basename → インライン SVG + CSS color）
 *
 * @return array<string, string>
 */
function aidunite_get_bottom_nav_icon_map() {
    return [
        'home'          => 'home',
        'schedule'      => 'calendar_month',
        'recruit'       => 'campaign',
        'match'         => 'trophy',
        'chat'          => 'chat',
        'attendance'    => 'check_circle',
        'stats'         => 'bar_chart_4_bars',
        'notifications' => 'notification_add',
        'guide'         => 'question_mark',
        'team-create'   => 'basketball',
        'profile'       => 'person_man',
    ];
}

/**
 * ボトムナビ構築用コンテキスト
 *
 * @return array{role:string, general_state:string, parent_pending_only:bool}
 */
function aidunite_get_bottom_nav_context() {
    $role = 'general';
    if (function_exists('aidunite_get_effective_user_role')) {
        list($role,) = aidunite_get_effective_user_role();
    }

    $general_state = 'before_apply';
    if ($role === 'general' && function_exists('aidunite_mypage_general_state')) {
        $general_state = aidunite_mypage_general_state(get_current_user_id());
    }

    $parent_pending_only = false;
    if ($role === 'parent' && function_exists('aidunite_parent_is_pending_only_mypage')) {
        $parent_pending_only = aidunite_parent_is_pending_only_mypage(get_current_user_id());
    }

    $activation_stage      = '';
    $activation_mission_ui = false;
    if (in_array($role, ['team_leader', 'administrator'], true) && function_exists('aidunite_get_current_team_id')) {
        $team_id = (int) aidunite_get_current_team_id();
        if ($team_id > 0 && function_exists('aidunite_get_team_activation_stage')) {
            $activation_stage = (string) aidunite_get_team_activation_stage($team_id);
            $activation_mission_ui = function_exists('aidunite_activation_is_mission_ui')
                && aidunite_activation_is_mission_ui($team_id);
        }
    }

    return [
        'role'                  => (string) $role,
        'general_state'         => (string) $general_state,
        'parent_pending_only'   => (bool) $parent_pending_only,
        'activation_stage'      => $activation_stage,
        'activation_mission_ui' => (bool) $activation_mission_ui,
    ];
}

/**
 * ボトムナビ1項目を組み立てる
 *
 * @param string               $id
 * @param string               $label
 * @param string               $url
 * @param array<string, mixed> $attrs
 * @return array<string, mixed>
 */
function aidunite_bottom_nav_make_item($id, $label, $url, array $attrs = []) {
    return [
        'id'        => $id,
        'label'     => $label,
        'url'       => $url,
        'type'      => 'link',
        'is_active' => aidunite_bottom_nav_is_active($id),
        'attrs'     => $attrs,
    ];
}

/**
 * ボトムナビアイコン HTML を出力
 *
 * @param string $item_id home|schedule|recruit|match|chat
 */
function aidunite_render_bottom_nav_icon($item_id) {
    $icons = aidunite_get_bottom_nav_icon_map();
    if (!isset($icons[$item_id])) {
        return;
    }

    echo '<span class="ainy-bottom-nav-icon ainy-bottom-nav-icon--inline" aria-hidden="true">';
    echo aidunite_get_theme_icon_svg(
        $icons[$item_id],
        [
            'width'  => '22',
            'height' => '22',
            'class'  => 'ainy-bottom-nav-icon__svg',
        ]
    );
    echo '</span>';
}

/**
 * ボトムナビ項目を返す（ログイン済み・ロール別）
 *
 * @return array<int, array<string, mixed>>
 */
function aidunite_get_bottom_nav_items() {
    if (!is_user_logged_in()) {
        return [];
    }

    $ctx          = aidunite_get_bottom_nav_context();
    $role         = $ctx['role'];
    $general_state = $ctx['general_state'];
    $parent_pending_only = $ctx['parent_pending_only'];

    $schedule_url = home_url('/schedule-management');

    $items = [];

    if ($parent_pending_only) {
        $items[] = aidunite_bottom_nav_make_item('home', 'ホーム', home_url('/mypage'));
        $items[] = aidunite_bottom_nav_make_item('notifications', 'お知らせ', home_url('/notifications'));
        $items[] = aidunite_bottom_nav_make_item('profile', 'プロフィール', home_url('/profile-edit'));

        return apply_filters('aidunite_bottom_nav_items', $items, $ctx);
    }

    if ($role === 'general') {
        $items[] = aidunite_bottom_nav_make_item('home', 'ホーム', home_url('/mypage'));
        if ($general_state === 'before_apply') {
            $items[] = aidunite_bottom_nav_make_item('team-create', 'チーム作成', home_url('/team-registration'));
            $items[] = aidunite_bottom_nav_make_item('guide', 'ガイド', home_url('/guide'));
        } else {
            $items[] = aidunite_bottom_nav_make_item('notifications', 'お知らせ', home_url('/notifications'));
        }

        return apply_filters('aidunite_bottom_nav_items', $items, $ctx);
    }

    if ($role === 'parent') {
        $items[] = aidunite_bottom_nav_make_item('attendance', '出欠連絡', home_url('/attendance-report'));
        $items[] = aidunite_bottom_nav_make_item('schedule', 'スケジュール', $schedule_url);
        $items[] = aidunite_bottom_nav_make_item('home', 'ホーム', home_url('/mypage'));
        $items[] = aidunite_bottom_nav_make_item('chat', 'チャット', home_url('/communication'));
        $items[] = aidunite_bottom_nav_make_item('notifications', 'お知らせ', home_url('/notifications'));

        return apply_filters('aidunite_bottom_nav_items', $items, $ctx);
    }

    if ($role === 'player') {
        $items[] = aidunite_bottom_nav_make_item('attendance', '出欠連絡', home_url('/attendance-report'));
        $items[] = aidunite_bottom_nav_make_item('schedule', 'スケジュール', $schedule_url);
        $items[] = aidunite_bottom_nav_make_item('home', 'ホーム', home_url('/mypage'));
        $items[] = aidunite_bottom_nav_make_item('chat', 'チャット', home_url('/communication'));

        return apply_filters('aidunite_bottom_nav_items', $items, $ctx);
    }

    $match_board_url    = home_url('/match-board-own');
    $match_my_url       = home_url('/match-board-own?market_tab=my');
    $activation_stage   = (string) ($ctx['activation_stage'] ?? '');
    $activation_mission = !empty($ctx['activation_mission_ui']);

    // オンボーディング中の代表者: 段階に応じてボトムナビを絞る
    if ($activation_mission && ($role === 'team_leader' || $role === 'administrator')) {
        if ($activation_stage === 'recruit_pending') {
            $items[] = aidunite_bottom_nav_make_item('recruit', '試合の募集', '#', [
                'data-aidunite-recruit-modal' => '1',
            ]);
            $items[] = aidunite_bottom_nav_make_item('home', 'ホーム', home_url('/mypage'));

            return apply_filters('aidunite_bottom_nav_items', $items, $ctx);
        }

        if (in_array($activation_stage, ['recruit_published', 'first_application'], true)) {
            $items[] = aidunite_bottom_nav_make_item('recruit', '試合の募集', '#', [
                'data-aidunite-recruit-modal' => '1',
            ]);
            $items[] = aidunite_bottom_nav_make_item('match', '申請状況', $match_my_url);
            $items[] = aidunite_bottom_nav_make_item('home', 'ホーム', home_url('/mypage'));

            return apply_filters('aidunite_bottom_nav_items', $items, $ctx);
        }
    }

    // team_leader / administrator / その他チーム所属
    if ($role === 'team_leader' || $role === 'administrator') {
        $items[] = aidunite_bottom_nav_make_item('recruit', '試合の募集', '#', [
            'data-aidunite-recruit-modal' => '1',
        ]);
    }

    $items[] = aidunite_bottom_nav_make_item('match', '試合一覧', $match_board_url);
    $items[] = aidunite_bottom_nav_make_item('home', 'ホーム', home_url('/mypage'));
    $items[] = aidunite_bottom_nav_make_item('schedule', 'スケジュール', $schedule_url);

    $team_id = function_exists('aidunite_get_current_team_id') ? (int) aidunite_get_current_team_id() : 0;
    $chat_unlocked = $team_id <= 0
        || !function_exists('aidunite_activation_is_chat_unlocked')
        || aidunite_activation_is_chat_unlocked($team_id);
    if ($chat_unlocked) {
        $items[] = aidunite_bottom_nav_make_item('chat', 'チャット', home_url('/communication'));
    }

    return apply_filters('aidunite_bottom_nav_items', $items, $ctx);
}

/**
 * ボトムナビ項目のアクティブ判定
 *
 * @param string $item_id
 */
function aidunite_bottom_nav_is_active($item_id) {
    switch ($item_id) {
        case 'home':
            return is_page('mypage');
        case 'schedule':
            return is_page(['schedule-management', 'schedule', 'schedule-edit', 'schedule-create']);
        case 'recruit':
            return false;
        case 'match':
            return is_page(['match-board-own', 'match-board', 'match-detail', 'match-history', 'match-requests', 'match-feedback']);
        case 'chat':
            return is_page(['chat', 'communication', 'communication-main', 'team-chat']);
        case 'attendance':
            return is_page(['attendance-report', 'attendance-management']);
        case 'stats':
            return is_page(['player-stats']);
        case 'notifications':
            return is_page(['notifications', 'notification-settings']);
        case 'guide':
            return is_page(['guide', 'faq']);
        case 'team-create':
            return is_page(['team-registration', 'team-registration-complete']);
        case 'profile':
            return is_page(['profile-edit']);
        default:
            return false;
    }
}

/**
 * ハンバーガーから除外するメニュー（ボトムナビと重複する項目）
 *
 * @param string               $role
 * @param array<string, mixed> $ctx aidunite_get_bottom_nav_context()
 * @return string[]
 */
function aidunite_get_hamburger_excluded_menu_titles($role, array $ctx) {
    $exclude = ['プロフィール編集', '通知設定'];

    if (!empty($ctx['parent_pending_only'])) {
        return array_merge($exclude, [
            'コミュニケーション', 'スケジュール', '出欠', '子供', 'チャット',
            '試合', 'メンバー', 'チーム管理', 'マッチ', '保護者', 'お気に入り',
            '個人記録', 'Ainy', 'WordPress', 'ユーザー管理', 'システムログ',
            'チーム作成', '利用ガイド', 'よくある', 'お知らせ',
        ]);
    }

    switch ($role) {
        case 'team_leader':
            // /team-settings 内の管理メニューと重複する項目はハンバーガーから除外（チーム管理のみ残す）
            $exclude = array_merge($exclude, [
                'コミュニケーション', 'スケジュール登録', '試合一覧',
                'お気に入り', 'マッチ統計', '出欠一覧', 'メンバー一覧', '保護者招待',
            ]);
            break;
        case 'administrator':
            $exclude = array_merge($exclude, ['コミュニケーション', 'スケジュール登録', '試合一覧']);
            break;
        case 'parent':
            $exclude = array_merge($exclude, ['コミュニケーション', 'スケジュール確認', '出欠連絡', 'お知らせ']);
            break;
        case 'player':
            $exclude = array_merge($exclude, ['コミュニケーション', 'スケジュール確認', '出欠連絡']);
            break;
        case 'general':
            if (($ctx['general_state'] ?? '') === 'before_apply') {
                $exclude = array_merge($exclude, ['チーム作成申請']);
            }
            $exclude = array_merge($exclude, ['利用ガイド', 'よくある質問']);
            if (in_array($ctx['general_state'] ?? '', ['pending', 'needs_revision'], true)) {
                $exclude = array_merge($exclude, ['お知らせ']);
            }
            break;
    }

    return $exclude;
}

/**
 * タイトルが除外パターンに一致するか
 *
 * @param string   $title
 * @param string[] $patterns
 */
function aidunite_hamburger_title_is_excluded($title, array $patterns) {
    $title = (string) $title;
    foreach ($patterns as $pattern) {
        if ($pattern !== '' && mb_strpos($title, (string) $pattern) !== false) {
            return true;
        }
    }

    return false;
}

/**
 * mypage_menu_v2 項目をハンバーガー用に分類
 *
 * @param string               $role
 * @param array<string, mixed> $ctx
 * @return array{team:array<int,array{title:string,url:string,emphasis?:string}>,account:array<int,array{title:string,url:string,emphasis?:string}>,system:array<int,array{title:string,url:string,emphasis?:string}>,support:array<int,array{title:string,url:string,emphasis?:string}>}
 */
function aidunite_hamburger_classify_mypage_menu($role, array $ctx) {
    $menu = function_exists('aidunite_get_mypage_menu_v2')
        ? aidunite_get_mypage_menu_v2($role)
        : [];

    $exclude   = aidunite_get_hamburger_excluded_menu_titles($role, $ctx);
    $team      = [];
    $account   = [];
    $system    = [];
    $support   = [];

    foreach ($menu as $item) {
        if (!is_array($item)) {
            continue;
        }
        $title = (string) ($item['title'] ?? '');
        $url   = (string) ($item['url'] ?? '');
        if ($title === '' || $url === '') {
            continue;
        }

        $entry = ['title' => $title, 'url' => $url];

        if ($title === 'プロフィール編集' || $title === '通知設定') {
            $account[] = $entry;
            continue;
        }

        if ($title === 'お知らせ' || $title === '利用ガイド' || $title === 'よくある質問') {
            if (!aidunite_hamburger_title_is_excluded($title, $exclude)) {
                $support[] = $entry;
            }
            continue;
        }

        if (aidunite_hamburger_title_is_excluded($title, $exclude)) {
            continue;
        }

        if (in_array($role, ['administrator'], true)
            && (
                mb_strpos($title, 'Ainy') !== false
                || mb_strpos($title, 'WordPress') !== false
                || mb_strpos($title, 'ユーザー管理') !== false
                || mb_strpos($title, 'システムログ') !== false
                || ($title === 'チーム管理' && mb_strpos($url, 'team-management') !== false)
            )) {
            $entry['emphasis'] = (mb_strpos($title, 'Ainy') !== false) ? 'strong' : 'default';
            $system[] = $entry;
            continue;
        }

        if (in_array($role, ['team_leader', 'parent'], true)) {
            $entry['emphasis'] = 'strong';
        }
        $team[] = $entry;
    }

    return [
        'team'    => $team,
        'account' => $account,
        'system'  => $system,
        'support' => $support,
    ];
}

/**
 * ハンバーガーメニューのセクション構成（mypage_menu_v2 統合）
 *
 * @return array<int, array{title:string, items:array<int, array{title:string, url:string, emphasis?:string}>}>
 */
function aidunite_get_hamburger_menu_sections() {
    if (!is_user_logged_in()) {
        return [
            [
                'title' => '',
                'items' => [
                    ['title' => 'ホーム', 'url' => home_url('/')],
                    ['title' => 'ログイン', 'url' => home_url('/login'), 'emphasis' => 'strong'],
                    ['title' => '利用ガイド', 'url' => home_url('/guide')],
                    ['title' => 'お問い合わせ', 'url' => home_url('/contact')],
                ],
            ],
        ];
    }

    $ctx  = aidunite_get_bottom_nav_context();
    $role = $ctx['role'];
    $classified = aidunite_hamburger_classify_mypage_menu($role, $ctx);

    $sections = [];

    if ($classified['system'] !== []) {
        $sections[] = [
            'title' => 'システム管理',
            'items' => $classified['system'],
        ];
    }

    if ($classified['team'] !== []) {
        $section_title = 'チーム運営';
        if ($role === 'player') {
            $section_title = '活動';
        } elseif ($role === 'general') {
            $section_title = 'はじめる';
        }
        $sections[] = [
            'title' => $section_title,
            'items' => $classified['team'],
        ];
    }

    if ($classified['account'] !== []) {
        $sections[] = [
            'title' => 'アカウント',
            'items' => $classified['account'],
        ];
    }

    $other_items = $classified['support'];
    $other_titles = array_column($other_items, 'title');

    if (!in_array('お知らせ', $other_titles, true)
        && !aidunite_hamburger_title_is_excluded('お知らせ', aidunite_get_hamburger_excluded_menu_titles($role, $ctx))) {
        $other_items[] = ['title' => 'お知らせ', 'url' => home_url('/notifications')];
    }
    if (!in_array('利用ガイド', $other_titles, true)) {
        $other_items[] = ['title' => '利用ガイド', 'url' => home_url('/guide')];
    }
    $other_items[] = ['title' => 'お問い合わせ', 'url' => home_url('/contact')];
    $other_items[] = ['title' => 'ログアウト', 'url' => wp_logout_url(home_url('/')), 'emphasis' => 'muted'];

    $sections[] = [
        'title' => 'その他',
        'items' => $other_items,
    ];

    return apply_filters('aidunite_hamburger_menu_sections', $sections, $role, $ctx);
}

/**
 * ボトムナビ「試合の募集」用クイックモーダルを読み込むか（代表者のみ）
 */
function aidunite_should_enqueue_recruit_quick_modal() {
    if (apply_filters('aidunite_mypage_joy_force_recruit_modal', false)) {
        return is_user_logged_in();
    }

    if (!is_user_logged_in()) {
        return false;
    }

    $role = 'general';
    if (function_exists('aidunite_get_effective_user_role')) {
        list($role,) = aidunite_get_effective_user_role();
    }
    if ($role !== 'team_leader') {
        return false;
    }

    if (is_page('mypage') || is_page_template('page-mypage.php')) {
        return true;
    }

    if (!function_exists('aidunite_should_use_app_bottom_nav') || !aidunite_should_use_app_bottom_nav()) {
        return false;
    }

    return true;
}

/**
 * クイックモーダル用スタイル（ページ固有 CSS とは分離）
 */
function aidunite_enqueue_schedule_quick_modal_styles() {
    $schedule_css = get_stylesheet_directory() . '/assets/css/pages/schedule.css';
    if (!wp_style_is('schedule-style', 'enqueued')) {
        wp_enqueue_style(
            'schedule-style',
            get_stylesheet_directory_uri() . '/assets/css/pages/schedule.css',
            array('aidunite-style'),
            is_readable($schedule_css) ? (string) filemtime($schedule_css) : '1.2.0',
            'all'
        );
    }

    $quick_modal_css = get_stylesheet_directory() . '/assets/css/components/schedule-quick-modal.css';
    if (is_readable($quick_modal_css) && !wp_style_is('aidunite-schedule-quick-modal', 'enqueued')) {
        wp_enqueue_style(
            'aidunite-schedule-quick-modal',
            get_stylesheet_directory_uri() . '/assets/css/components/schedule-quick-modal.css',
            array('schedule-style', 'button-style', 'form-style', 'aidunite-theme-icons'),
            (string) filemtime($quick_modal_css)
        );
    }
}

/**
 * 試合募集クイックモーダル用スクリプト・スタイルを enqueue
 */
function aidunite_enqueue_recruit_quick_modal_assets() {
    if (!aidunite_should_enqueue_recruit_quick_modal()) {
        return;
    }

    aidunite_enqueue_schedule_quick_modal_styles();

    if (!wp_script_is('aidunite-date-utils', 'enqueued')) {
        wp_enqueue_script('aidunite-date-utils', get_stylesheet_directory_uri() . '/assets/js/common/date-utils.js', array('aidunite-dom-utils'), '1.0.0', true);
    }
    if (!wp_script_is('aidunite-schedule-utils', 'enqueued')) {
        wp_enqueue_script('aidunite-schedule-utils', get_stylesheet_directory_uri() . '/assets/js/common/schedule-utils.js', array('aidunite-date-utils', 'aidunite-theme-icons'), '1.0.1', true);
    }
    if (!wp_script_is('aidunite-ajax-utils', 'enqueued')) {
        wp_enqueue_script('aidunite-ajax-utils', get_stylesheet_directory_uri() . '/assets/js/common/ajax-utils.js', array('aidunite-dom-utils', 'loading-spinner-utils', 'aidunite-toast-notification'), '1.0.0', true);
        wp_localize_script('aidunite-ajax-utils', 'aiduniteScheduleRest', [
            'root'  => rest_url(),
            'nonce' => wp_create_nonce('wp_rest'),
        ]);
    }
    if (!wp_script_is('aidunite-schedule-modal', 'enqueued')) {
        wp_enqueue_script('aidunite-schedule-modal', get_stylesheet_directory_uri() . '/assets/js/common/schedule-modal.js', array('aidunite-schedule-utils', 'aidunite-ajax-utils', 'aidunite-toast-notification', 'aidunite-confirm-modal'), '1.0.2', true);
    }

    $quick_modal_js = get_stylesheet_directory() . '/assets/js/common/schedule-quick-modal.js';
    if (is_readable($quick_modal_js) && !wp_script_is('aidunite-schedule-quick-modal', 'enqueued')) {
        wp_enqueue_script(
            'aidunite-schedule-quick-modal',
            get_stylesheet_directory_uri() . '/assets/js/common/schedule-quick-modal.js',
            array('aidunite-schedule-modal', 'aidunite-date-utils', 'aidunite-confirm-modal'),
            (string) filemtime($quick_modal_js),
            true
        );
    }

    if (wp_script_is('aidunite-schedule-quick-modal', 'enqueued') || wp_script_is('aidunite-schedule-quick-modal', 'registered')) {
        $team_gender           = '';
        $activation_stage      = '';
        $activation_mission_ui = false;
        if (function_exists('aidunite_get_current_team_id')) {
            $team_id = (int) aidunite_get_current_team_id(get_current_user_id());
            if ($team_id > 0 && function_exists('aidunite_team_get_canonical_meta')) {
                $team_canonical = aidunite_team_get_canonical_meta($team_id);
                $team_gender = (string) ($team_canonical['team_gender_option'] ?? '');
            }
            if ($team_id > 0 && function_exists('aidunite_get_team_activation_stage')) {
                $activation_stage = (string) aidunite_get_team_activation_stage($team_id);
                $activation_mission_ui = function_exists('aidunite_activation_is_mission_ui')
                    && aidunite_activation_is_mission_ui($team_id);
            }
        }
        wp_localize_script('aidunite-schedule-quick-modal', 'AIDUNITE_SCHEDULE_QUICK', [
            'canEdit' => true,
            'scheduleEditUrl' => function_exists('aidunite_get_schedule_edit_url')
                ? aidunite_get_schedule_edit_url()
                : home_url('/schedule-management/'),
            'registerUrl' => rest_url('aidunite/v1/register-schedule-v2'),
            'deleteUrl' => rest_url('aidunite/v1/delete-schedule-v2'),
            'updateUrl' => rest_url('aidunite/v1/update-schedule-v2'),
            'nonce' => wp_create_nonce('wp_rest'),
            'teamGender' => $team_gender,
            'defaultStartTime' => '13:00',
            'defaultEndTime' => '14:00',
            'defaultRecruitTeams' => 2,
            'activationStage' => $activation_stage,
            'activationMissionUi' => $activation_mission_ui ? '1' : '0',
            'mypageUrl' => home_url('/mypage/'),
            'matchBoardMyUrl' => home_url('/match-board-own?market_tab=my'),
        ]);
    }
}
