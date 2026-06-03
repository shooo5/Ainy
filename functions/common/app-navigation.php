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
 * 統一ボトムナビ（ダーク・シェル幅）を使うか
 */
function aidunite_should_use_app_bottom_nav() {
    if (!is_user_logged_in()) {
        return false;
    }
    if (function_exists('aidunite_is_web_app_page') && aidunite_is_web_app_page()) {
        return true;
    }

    return is_front_page();
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
 * トップ等：web-app-integrated-ui なしで統一ボトムナビ用 body クラス
 *
 * @param string[] $classes
 * @return string[]
 */
function aidunite_bottom_nav_unified_body_class($classes) {
    if (!aidunite_should_use_app_bottom_nav() || !aidunite_should_show_bottom_nav()) {
        return $classes;
    }
    if (in_array('web-app-integrated-ui', $classes, true)) {
        return $classes;
    }
    $classes[] = 'ainy-bottom-nav-unified';

    return $classes;
}
add_filter('body_class', 'aidunite_bottom_nav_unified_body_class', 26);

/**
 * ボトムナビ用アイコン（chat/ ベース名 → インライン SVG + CSS color）
 *
 * @return array<string, string>
 */
function aidunite_get_bottom_nav_icon_map() {
    return [
        'home'     => 'home',
        'schedule' => 'calendar_month',
        'match'    => 'trophy',
        'chat'     => 'chat',
        'menu'     => 'menu',
    ];
}

/**
 * ボトムナビアイコン HTML を出力
 *
 * @param string $item_id home|schedule|match|chat|menu
 */
function aidunite_render_bottom_nav_icon($item_id) {
    $icons = aidunite_get_bottom_nav_icon_map();
    if (!isset($icons[$item_id])) {
        return;
    }

    echo '<span class="ainy-bottom-nav-icon ainy-bottom-nav-icon--inline" aria-hidden="true">';
    echo aidunite_get_chat_icon_svg(
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
 * ボトムナビ項目を返す（ログイン済み Web アプリ向け）
 *
 * @return array<int, array<string, mixed>>
 */
function aidunite_get_bottom_nav_items() {
    if (!is_user_logged_in()) {
        return [];
    }

    $role = 'general';
    if (function_exists('aidunite_get_effective_user_role')) {
        list($role,) = aidunite_get_effective_user_role();
    }

    $schedule_url = ($role === 'team_leader')
        ? home_url('/schedule-management')
        : home_url('/schedule-list');

    $menu_url = '';
    if ($role === 'team_leader' && function_exists('aidunite_get_team_settings_page_url')) {
        $menu_url = aidunite_get_team_settings_page_url();
    }

    $items = [
        [
            'id'       => 'schedule',
            'label'    => 'スケジュール',
            'url'      => $schedule_url,
            'type'     => 'link',
            'is_active'=> aidunite_bottom_nav_is_active('schedule'),
        ],
        [
            'id'       => 'match',
            'label'    => '試合一覧',
            'url'      => home_url('/match-board-own'),
            'type'     => 'link',
            'is_active'=> aidunite_bottom_nav_is_active('match'),
        ],
        [
            'id'       => 'home',
            'label'    => 'ホーム',
            'url'      => home_url('/mypage'),
            'type'     => 'link',
            'is_active'=> aidunite_bottom_nav_is_active('home'),
        ],
        [
            'id'       => 'chat',
            'label'    => 'チャット',
            'url'      => home_url('/communication'),
            'type'     => 'link',
            'is_active'=> aidunite_bottom_nav_is_active('chat'),
        ],
        [
            'id'       => 'menu',
            'label'    => 'メニュー',
            'url'      => $menu_url,
            'type'     => $menu_url !== '' ? 'link' : 'menu-trigger',
            'is_active'=> aidunite_bottom_nav_is_active('menu'),
        ],
    ];

    return $items;
}

/**
 * ボトムナビ項目のアクティブ判定
 *
 * @param string $item_id home|schedule|match|chat|menu
 */
function aidunite_bottom_nav_is_active($item_id) {
    switch ($item_id) {
        case 'home':
            return is_page('mypage');
        case 'schedule':
            return is_page(['schedule-list', 'schedule-management', 'schedule', 'schedule-edit', 'schedule-create']);
        case 'match':
            return is_page(['match-board-own', 'match-board', 'match-detail', 'match-history', 'match-requests', 'match-feedback']);
        case 'chat':
            return is_page(['chat', 'communication', 'communication-main', 'team-chat']);
        case 'menu':
            if (function_exists('aidunite_is_team_settings_screen') && aidunite_is_team_settings_screen()) {
                return true;
            }
            return is_page(['team-settings', 'profile-edit', 'notification-settings']);
        default:
            return false;
    }
}

/**
 * ハンバーガーメニューのセクション構成（設定・管理置き場）
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
                    ['title' => '利用ガイド', 'url' => home_url('/guide')],
                    ['title' => 'お問い合わせ', 'url' => home_url('/contact')],
                ],
            ],
        ];
    }

    $role = 'general';
    if (function_exists('aidunite_get_effective_user_role')) {
        list($role,) = aidunite_get_effective_user_role();
    }

    $sections = [];

    switch ($role) {
        case 'team_leader':
            $sections[] = [
                'title' => 'チーム運営',
                'items' => [
                    ['title' => 'チーム管理', 'url' => function_exists('aidunite_get_team_settings_page_url') ? aidunite_get_team_settings_page_url() : home_url('/team-settings'), 'emphasis' => 'strong'],
                    ['title' => 'メンバー一覧', 'url' => home_url('/team-members'), 'emphasis' => 'strong'],
                    ['title' => '保護者招待', 'url' => home_url('/invite-guardian'), 'emphasis' => 'strong'],
                    ['title' => '出欠一覧', 'url' => home_url('/attendance-management'), 'emphasis' => 'strong'],
                ],
            ];
            $sections[] = [
                'title' => '試合・活動',
                'items' => [
                    ['title' => 'マッチ統計', 'url' => home_url('/match-analytics')],
                    ['title' => '試合履歴', 'url' => home_url('/match-history')],
                    ['title' => 'お気に入りチーム', 'url' => home_url('/mypage-favorite-teams')],
                ],
            ];
            $sections[] = [
                'title' => 'アカウント',
                'items' => [
                    ['title' => 'プロフィール編集', 'url' => home_url('/profile-edit')],
                    ['title' => '通知設定', 'url' => home_url('/notification-settings')],
                ],
            ];
            break;

        case 'parent':
            $sections[] = [
                'title' => 'チーム運営',
                'items' => [
                    ['title' => '子供の活動管理', 'url' => home_url('/player-add'), 'emphasis' => 'strong'],
                    ['title' => '出欠連絡', 'url' => home_url('/attendance-report'), 'emphasis' => 'strong'],
                ],
            ];
            $sections[] = [
                'title' => 'アカウント',
                'items' => [
                    ['title' => 'プロフィール編集', 'url' => home_url('/profile-edit')],
                    ['title' => '通知設定', 'url' => home_url('/notification-settings')],
                ],
            ];
            break;

        case 'player':
            $sections[] = [
                'title' => '活動',
                'items' => [
                    ['title' => '出欠連絡', 'url' => home_url('/attendance-report'), 'emphasis' => 'strong'],
                    ['title' => '個人記録', 'url' => home_url('/player-stats')],
                ],
            ];
            $sections[] = [
                'title' => 'アカウント',
                'items' => [
                    ['title' => 'プロフィール編集', 'url' => home_url('/profile-edit')],
                    ['title' => '通知設定', 'url' => home_url('/notification-settings')],
                ],
            ];
            break;

        case 'administrator':
            $sections[] = [
                'title' => 'システム管理',
                'items' => [
                    ['title' => 'Ainyダッシュボード', 'url' => home_url('/ainy-dashboard'), 'emphasis' => 'strong'],
                    ['title' => 'システム管理', 'url' => home_url('/system-management')],
                    ['title' => 'ユーザー管理', 'url' => home_url('/user-management')],
                    ['title' => 'チーム管理', 'url' => home_url('/team-management')],
                    ['title' => 'システムログ', 'url' => home_url('/system-logs')],
                ],
            ];
            $sections[] = [
                'title' => 'アカウント',
                'items' => [
                    ['title' => 'プロフィール編集', 'url' => home_url('/profile-edit')],
                    ['title' => '通知設定', 'url' => home_url('/notification-settings')],
                ],
            ];
            break;

        default:
            $sections[] = [
                'title' => 'アカウント',
                'items' => [
                    ['title' => 'プロフィール編集', 'url' => home_url('/profile-edit')],
                    ['title' => '通知設定', 'url' => home_url('/notification-settings')],
                ],
            ];
            break;
    }

    $sections[] = [
        'title' => 'その他',
        'items' => [
            ['title' => 'お知らせ', 'url' => home_url('/notifications')],
            ['title' => '利用ガイド', 'url' => home_url('/guide')],
            ['title' => 'お問い合わせ', 'url' => home_url('/contact')],
            ['title' => 'ログアウト', 'url' => wp_logout_url(home_url('/')), 'emphasis' => 'muted'],
        ],
    ];

    return $sections;
}
