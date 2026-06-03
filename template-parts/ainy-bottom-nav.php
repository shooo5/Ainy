<?php
/**
 * 統一ボトムナビ（スケジュール / 試合 / ホーム / チャット / メニュー）
 *
 * @package AidUnite
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('aidunite_get_bottom_nav_items')) {
    return;
}

$nav_items = aidunite_get_bottom_nav_items();
if (empty($nav_items)) {
    return;
}

$use_app_style = function_exists('aidunite_should_use_app_bottom_nav') && aidunite_should_use_app_bottom_nav();
$nav_class     = 'ainy-bottom-nav' . ($use_app_style ? ' ainy-bottom-nav--app' : '');
?>

<nav class="<?php echo esc_attr($nav_class); ?>" aria-label="メインナビゲーション">
    <div class="ainy-bottom-nav-inner">
        <?php foreach ($nav_items as $item) : ?>
            <?php
            $is_active = !empty($item['is_active']);
            $active_class = $is_active ? ' active' : '';
            $aria_current = $is_active ? ' aria-current="page"' : '';
            $item_type = isset($item['type']) ? (string) $item['type'] : 'link';
            $item_id = isset($item['id']) ? (string) $item['id'] : '';
            ?>
            <?php if ($item_type === 'menu-trigger') : ?>
                <button type="button"
                    class="ainy-bottom-nav-link ainy-bottom-nav-menu-trigger<?php echo esc_attr($active_class); ?>"
                    title="<?php echo esc_attr($item['label']); ?>"
                    aria-label="<?php echo esc_attr($item['label'] . 'を開く'); ?>">
                    <?php
                    if ($item_id !== '' && function_exists('aidunite_render_bottom_nav_icon')) {
                        aidunite_render_bottom_nav_icon($item_id);
                    }
                    ?>
                    <span><?php echo esc_html($item['label']); ?></span>
                </button>
            <?php else : ?>
                <a href="<?php echo esc_url($item['url']); ?>"
                    class="ainy-bottom-nav-link<?php echo esc_attr($active_class); ?>"
                    title="<?php echo esc_attr($item['label']); ?>"<?php echo $aria_current; ?>>
                    <?php
                    if ($item_id !== '' && function_exists('aidunite_render_bottom_nav_icon')) {
                        aidunite_render_bottom_nav_icon($item_id);
                    }
                    ?>
                    <span><?php echo esc_html($item['label']); ?></span>
                </a>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
</nav>
