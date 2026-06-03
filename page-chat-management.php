<?php
/*
Template Name: チャット管理
*/

require_once get_template_directory() . '/functions/common/auth-middleware.php';
$auth_result = AidUniteAuthMiddleware::require_admin(true);
if (!$auth_result->is_valid()) {
    return;
}

get_header();
?>

<div class="team-dashboard-container" style="max-width:1200px; margin:0 auto; padding:24px 16px;">
    <?php
    if (function_exists('aidunite_render_chat_management_page')) {
        aidunite_render_chat_management_page();
    } else {
        echo '<div class="notice notice-error"><p>チャット管理機能が見つかりません。</p></div>';
    }
    ?>
</div>

<?php get_footer(); ?>
