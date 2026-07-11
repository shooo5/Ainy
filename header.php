<?php
/**
 * The Header for our theme (child override).
 *
 * Displays all of the <head> section and everything up till <div id="main">
 *
 * @since vantage-child 1.0
 * @license GPL 2.0
 */
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
	<meta http-equiv="Pragma" content="no-cache">
	<meta http-equiv="Expires" content="0">
	<link rel="profile" href="https://gmpg.org/xfn/11">
	<link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

	<?php wp_head(); ?>
</head>

<?php
$aidunite_body_team_theme = '';
if (
	function_exists('aidunite_should_apply_team_ui_theme')
	&& aidunite_should_apply_team_ui_theme()
	&& function_exists('aidunite_get_team_ui_theme_key')
) {
	$aidunite_body_team_theme = aidunite_get_team_ui_theme_key();
}
?>
<body <?php body_class(); ?><?php echo $aidunite_body_team_theme !== '' ? ' data-team-theme="' . esc_attr($aidunite_body_team_theme) . '"' : ''; ?>>
<?php if ( function_exists( 'wp_body_open' ) ) {
	wp_body_open();
} ?>

<?php
// 管理者プレビューモードバナー表示（全ページで1回のみ。各ページテンプレートでは出さないこと）
if ( function_exists('aidunite_get_effective_user_role') && function_exists('aidunite_preview_mode_banner') ) {
    list($user_role, $preview_mode) = aidunite_get_effective_user_role();
    require_once get_template_directory() . '/functions/common/auth-middleware.php';
    $admin_result = AidUniteAuthMiddleware::require_admin(false);
    if ($admin_result->is_valid()) {
        // プレビュー中はロールキー（文字列）を渡す。渡し間違えると「1」などになる
        aidunite_preview_mode_banner($preview_mode ? $user_role : false);
    } elseif ($preview_mode) {
        echo '<div style="background:#ff9800;color:#fff;padding:8px 0;text-align:center;font-weight:bold;z-index:9999;">【確認モード：' . esc_html($user_role) . '】 <a href="' . esc_url( remove_query_arg('mode') ) . '" style="color:#fff;text-decoration:underline;">解除</a></div>';
    }
}
?>

<div id="page"><!-- 子テーマで追加: flexレイアウト用ラッパー -->

<!-- Ainy ヘッダー（スタイル: assets/css/layout/ainy-header.css → enqueue ainy-header） -->
<header class="ainy-header">
    <div class="ainy-header-container">
        <!-- ロゴ -->
        <a href="<?php echo home_url(); ?>" class="ainy-logo">
            <div class="ainy-logo-icon">A</div>
            Ainy
        </a>

        <!-- 右側のボタン群 -->
        <div class="ainy-header-right">
            <?php if (!is_user_logged_in()): ?>
                <div class="ainy-header-auth-actions">
                    <a href="<?php echo esc_url(home_url('/login')); ?>" class="ainy-auth-button primary">
                        <?php
                        if (function_exists('aidunite_render_theme_icon')) {
                            echo aidunite_render_theme_icon('lock_open', ['width' => '18', 'height' => '18'], 'ainy-auth-button__icon'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        }
                        ?>
                        <span class="ainy-auth-button__label">ログイン</span>
                    </a>
                    <a href="<?php echo esc_url(home_url('/member-registration')); ?>" class="ainy-auth-button secondary">
                        <?php
                        if (function_exists('aidunite_render_theme_icon')) {
                            echo aidunite_render_theme_icon('person_add', ['width' => '18', 'height' => '18'], 'ainy-auth-button__icon'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        }
                        ?>
                        <span class="ainy-auth-button__label">新規登録</span>
                    </a>
                </div>
            <?php else:
                $notification_count = function_exists('aidunite_get_notification_count') ? aidunite_get_notification_count() : 0;
                ?>
                <?php
                if (function_exists('aidunite_render_operating_team_header_control')) {
                    aidunite_render_operating_team_header_control();
                }
                ?>
                <span class="ainy-header-notification-wrap">
                    <a href="<?php echo esc_url(home_url('/notifications')); ?>" class="ainy-header-icon-link ainy-header-notification-link" aria-label="<?php echo $notification_count > 0 ? 'お知らせ（未読' . (int) $notification_count . '件）' : 'お知らせ'; ?>">
                        <svg width="24" height="24" viewBox="0 -960 960 960" fill="#EAC452" aria-hidden="true"><path d="M160-200v-80h80v-280q0-83 50-147.5T420-792v-28q0-25 17.5-42.5T480-880q25 0 42.5 17.5T540-820v28q80 20 130 84.5T720-560v280h80v80H160Zm320-300Zm0 420q-33 0-56.5-23.5T400-160h160q0 33-23.5 56.5T480-80ZM320-280h320v-280q0-66-47-113t-113-47q-66 0-113 47t-47 113v280Z"/></svg>
                        <?php if ($notification_count > 0): ?>
                            <span class="ainy-notification-dot" aria-hidden="true"></span>
                        <?php endif; ?>
                    </a>
                </span>
            <?php endif; ?>

            <!-- ハンバーガーメニュー -->
            <button class="ainy-hamburger" title="メニュー">
                <div class="ainy-hamburger-icon">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
            </button>
        </div>
    </div>
</header>

<!-- サイドメニュー -->
<?php get_template_part('template-parts/ainy', 'side-menu'); ?>

<div id="page-wrapper">

	<a class="skip-link screen-reader-text" href="#content"><?php esc_html_e( 'Skip to content', 'vantage' ); ?></a>

	<?php
// ❌削除済：親テーマのマストヘッド表示（aidunite-originalに移行済のため）
// if ( function_exists('siteorigin_page_setting') && ! siteorigin_page_setting( 'hide_masthead', false ) ) {
// 	get_template_part( 'parts/masthead', apply_filters( 'vantage_masthead_type', siteorigin_setting( 'layout_masthead' ) ) );
// }
?>

	<?php
// ❌削除済：親テーマのスライダー表示（aidunite-originalに移行済のため）
// if (function_exists('vantage_render_slider')) {
//   vantage_render_slider();
// }
?>

	<div id="main" class="site-main">
		<div class="full-container">
			<?php
// header.php で開いた #page-wrapper / #main / .full-container（footer.php で閉じる）
$GLOBALS['aidunite_layout_wrapper_depth'] = 3;
// ❌削除済：親テーマのメイントップアクション（aidunite-originalに移行済のため）
// do_action('vantage_main_top');
?>
