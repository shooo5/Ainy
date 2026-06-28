<?php
/**
 * Template Name: システム管理（開発者専用）
 *
 * システム情報・決済管理は Ainy ダッシュボード（/ainy-dashboard）に統合済み。
 * 固定ページ slug system-management からのブックマーク互換用リダイレクトのみ。
 */

require_once get_template_directory() . '/functions/common/auth-middleware.php';

$auth_result = AidUniteAuthMiddleware::require_admin(true);
if (!$auth_result->is_valid()) {
    get_header();
    echo '<div class="page-container">';
    echo '<div class="error-message">このページは開発者（administrator）のみアクセス可能です。</div>';
    echo '<a href="' . esc_url(home_url('/mypage')) . '" class="btn btn-primary">マイページに戻る</a>';
    echo '</div>';
    get_footer();
    return;
}

wp_safe_redirect(home_url('/ainy-dashboard'));
exit;
