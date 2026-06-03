<?php
/**
 * ハンバーガーサイドメニュー（設定・管理置き場）
 *
 * @package AidUnite
 */

if (!defined('ABSPATH')) {
    exit;
}

$sections = function_exists('aidunite_get_hamburger_menu_sections')
    ? aidunite_get_hamburger_menu_sections()
    : [];

$side_notification_count = is_user_logged_in() && function_exists('aidunite_get_notification_count')
    ? (int) aidunite_get_notification_count()
    : 0;
?>

<div class="ainy-side-menu-overlay"></div>

<div class="ainy-side-menu" id="sideMenu">
    <div class="ainy-side-menu-content">
        <div class="ainy-side-menu-header">
            <div class="ainy-side-menu-title">メニュー</div>
            <button type="button" class="ainy-side-menu-close" aria-label="メニューを閉じる"><?php echo aidunite_render_theme_icon('close', ['width' => '24', 'height' => '24']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
        </div>

        <?php foreach ($sections as $section) : ?>
            <?php
            $section_title = isset($section['title']) ? (string) $section['title'] : '';
            $section_items = isset($section['items']) && is_array($section['items']) ? $section['items'] : [];
            if (empty($section_items)) {
                continue;
            }
            ?>
            <div class="ainy-side-menu-section">
                <?php if ($section_title !== '') : ?>
                    <h3 class="ainy-side-menu-section-title"><?php echo esc_html($section_title); ?></h3>
                <?php endif; ?>
                <nav class="ainy-side-nav" aria-label="<?php echo esc_attr($section_title !== '' ? $section_title : 'メニュー'); ?>">
                    <ul class="ainy-side-nav-list">
                        <?php foreach ($section_items as $item) : ?>
                            <?php
                            $item_title = isset($item['title']) ? (string) $item['title'] : '';
                            $item_url   = isset($item['url']) ? (string) $item['url'] : '';
                            $emphasis   = isset($item['emphasis']) ? (string) $item['emphasis'] : 'default';
                            if ($item_title === '' || $item_url === '') {
                                continue;
                            }
                            $link_class = 'ainy-side-nav-link';
                            if ($emphasis === 'strong') {
                                $link_class .= ' ainy-side-nav-link--strong';
                            } elseif ($emphasis === 'muted') {
                                $link_class .= ' ainy-side-nav-link--muted';
                            }
                            $is_notifications = ($item_title === 'お知らせ');
                            ?>
                            <li class="ainy-side-nav-item">
                                <a href="<?php echo esc_url($item_url); ?>" class="<?php echo esc_attr($link_class); ?><?php echo $is_notifications ? ' ainy-nav-link-with-badge' : ''; ?>">
                                    <?php echo esc_html($item_title); ?>
                                    <?php if ($is_notifications && $side_notification_count > 0) : ?>
                                        <span class="count-badge count-badge--overlay" aria-label="未読<?php echo (int) $side_notification_count; ?>件"><?php echo (int) $side_notification_count; ?></span>
                                    <?php endif; ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </nav>
            </div>
        <?php endforeach; ?>

        <?php if (is_user_logged_in() && function_exists('aidunite_is_web_app_page') && aidunite_is_web_app_page()) : ?>
        <div class="ainy-side-menu-legal">
            <h3 class="ainy-side-menu-section-title">法的情報</h3>
            <nav class="ainy-side-nav" aria-label="法的情報">
                <ul class="ainy-side-nav-list">
                    <li class="ainy-side-nav-item">
                        <a href="<?php echo esc_url(home_url('/terms-of-service')); ?>" class="ainy-side-nav-link ainy-side-nav-link--muted">利用規約</a>
                    </li>
                    <li class="ainy-side-nav-item">
                        <a href="<?php echo esc_url(home_url('/privacy-policy')); ?>" class="ainy-side-nav-link ainy-side-nav-link--muted">プライバシーポリシー</a>
                    </li>
                    <li class="ainy-side-nav-item">
                        <a href="<?php echo esc_url(home_url('/commercial-transaction-law')); ?>" class="ainy-side-nav-link ainy-side-nav-link--muted">特定商取引法に基づく表記</a>
                    </li>
                    <li class="ainy-side-nav-item">
                        <a href="<?php echo esc_url(home_url('/about')); ?>" class="ainy-side-nav-link ainy-side-nav-link--muted">会社概要</a>
                    </li>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>
