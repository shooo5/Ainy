<?php
/**
 * 固定ページの Webアプリ判定（トンマナ一体型UIの適用範囲）
 *
 * @package AidUnite
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 一体型UI・チームテーマを付けない公開／登録系スラッグ
 *
 * @return string[]
 */
function aidunite_get_public_fixed_page_slugs() {
    return [
        'login',
        'about',
        'faq',
        'terms-of-service',
        'privacy-policy',
        'cookie-policy',
        'disclaimer',
        'press',
        'service',
        'guide',
        'sample-preview',
        'design-reference',
        'team-registration',
        'team-registration-complete',
        'team-registration-preview',
        'team-apply',
        'guardian-signup',
        'guardian-registration-pending',
        'member-register',
        'member-registration',
        'approve-registration',
        'registration-preview',
        'confirm-withdrawal',
        'member-withdrawal',
        'payment-required',
        'system-maintenance',
        'competition-event',
    ];
}

/**
 * ログイン済みユーザー向け Webアプリ固定ページか
 */
function aidunite_is_web_app_page() {
    if (!is_user_logged_in()) {
        return false;
    }

    // general マイページは原則グローバルヘッダー。承認待ち（Joy シェル）のみ一体型 UI
    if (function_exists('aidunite_is_general_mypage_screen') && aidunite_is_general_mypage_screen()) {
        if (function_exists('aidunite_mypage_general_state')
            && aidunite_mypage_general_state() === 'pending') {
            return true;
        }

        return false;
    }

    if (is_singular('team')) {
        return true;
    }

    if (!is_page()) {
        return false;
    }

    $post = get_queried_object();
    if (!$post instanceof WP_Post) {
        return false;
    }

    $slug = (string) $post->post_name;
    if ($slug !== '' && in_array($slug, aidunite_get_public_fixed_page_slugs(), true)) {
        return false;
    }

    $template_slug = function_exists('get_page_template_slug') ? (string) get_page_template_slug($post->ID) : '';
    $template_base = $template_slug !== '' ? basename(str_replace('\\', '/', $template_slug)) : '';

    if ($template_base !== '' && preg_match('/^page-[a-z0-9\-]+\.php$/i', $template_base)) {
        $path = get_stylesheet_directory() . '/' . $template_base;
        if (is_readable($path)) {
            return true;
        }
    }

    $legacy = [
        'mypage',
        'notifications',
        'schedule-management',
        'schedule-edit',
        'schedule-create',
        'match-list',
        'match-create',
        'match-board-own',
        'member-management',
        'communication-main',
        'team-chat',
        'chat',
        'player-add',
        'player-edit',
        'edit-player',
        'attendance-report',
        'team-settings',
        'team-members',
        'attendance-management',
        'match-analytics',
        'match-requests',
        'match-history',
        'match-detail',
        'notification-settings',
        'payment-setup',
        'payment-checkout',
        'parent-payment',
        'plan-info',
        'team-payment-management',
        'team-tuition-collections',
        'invite-guardian',
        'mypage-favorite-teams',
        'profile-edit',
        'team-management',
        'system-management',
        'ainy-dashboard',
        'match-feedback',
        'team-approval',
        'feedback',
        'contact',
        'faq',
        'regulation',
        'communication',
        'communication-main',
        'notification-settings',
        'mypage-favorite-teams',
        'invite-guardian',
        'team-members',
        'team-payment-management',
        'team-tuition-collections',
        'profile-edit',
        'player-add',
        'edit-player',
        'team-public',
    ];

    return $slug !== '' && in_array($slug, $legacy, true);
}

/**
 * デフォルト一体型ヒーロー引数（page-template-dashboard 等）
 *
 * @param array<string, mixed> $args title, subtitle, size, back, back_url, active_nav, actions
 * @return array<string, mixed>
 */
function aidunite_build_default_web_app_hero_args(array $args = []) {
    $args = wp_parse_args($args, [
        'title' => '',
        'subtitle' => '',
        'size' => 'md',
        'back' => true,
        'back_url' => '',
        'active_nav' => 'none',
        'actions' => [],
    ]);

    return [
        'size' => in_array($args['size'], ['sm', 'md', 'lg'], true) ? $args['size'] : 'md',
        'title' => (string) $args['title'],
        'subtitle' => (string) $args['subtitle'],
        'back' => !empty($args['back']),
        'back_url' => (string) $args['back_url'],
        'active_nav' => (string) $args['active_nav'],
        'actions' => is_array($args['actions']) ? $args['actions'] : [],
        'hero_body' => isset($args['hero_body']) ? (string) $args['hero_body'] : '',
    ];
}
