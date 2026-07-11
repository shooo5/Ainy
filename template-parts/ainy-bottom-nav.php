<?php
/**
 * 統一ボトムナビ（ロール別・ダークシェル幅）
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
if ($nav_items === []) {
    return;
}

$nav_count   = count($nav_items);
$inner_class = 'ainy-bottom-nav-inner ainy-bottom-nav-inner--count-' . max(1, min(5, $nav_count));
?>

<nav class="ainy-bottom-nav" aria-label="メインナビゲーション">
    <div class="<?php echo esc_attr($inner_class); ?>">
        <?php foreach ($nav_items as $item) : ?>
            <?php
            $is_active    = !empty($item['is_active']);
            $active_class = $is_active ? ' active' : '';
            $aria_current = $is_active ? ' aria-current="page"' : '';
            $item_id      = isset($item['id']) ? (string) $item['id'] : '';
            $item_attrs   = isset($item['attrs']) && is_array($item['attrs']) ? $item['attrs'] : [];
            ?>
            <a href="<?php echo esc_url($item['url']); ?>"
                class="ainy-bottom-nav-link<?php echo esc_attr($active_class); ?>"
                data-nav-id="<?php echo esc_attr($item_id); ?>"
                title="<?php echo esc_attr($item['label']); ?>"<?php echo $aria_current; ?>
                <?php foreach ($item_attrs as $attr_name => $attr_value) : ?>
                    <?php echo esc_attr((string) $attr_name); ?>="<?php echo esc_attr((string) $attr_value); ?>"
                <?php endforeach; ?>>
                <?php
                if ($item_id !== '' && function_exists('aidunite_render_bottom_nav_icon')) {
                    aidunite_render_bottom_nav_icon($item_id);
                }
                ?>
                <span><?php echo esc_html($item['label']); ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</nav>
