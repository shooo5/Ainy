<?php
/**
 * Template Name: 管理用試合後モジュール
 *
 * 使い方: 固定ページスラッグ「admin-post-match-modules」、本テンプレートを選択。
 * Ainy 管理者ダッシュボードのショートカットから遷移。
 */

require_once get_template_directory() . '/functions/common/auth-middleware.php';
$auth_result = AidUniteAuthMiddleware::require_admin(true);
if (!$auth_result->is_valid()) {
    return;
}

require_once get_template_directory() . '/functions/match/admin-post-match-module.php';

aidunite_admin_post_match_module_handle_post();

get_header();
aidunite_admin_post_match_module_render_page();
get_footer();
