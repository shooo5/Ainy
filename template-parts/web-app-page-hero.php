<?php
/**
 * Webアプリ：一体型ページヒーロー（ヘッダーバー + タイトル行 + 任意ボディ）
 *
 * @package AidUnite
 */

if (!defined('ABSPATH')) {
    exit;
}

$defaults = [
    'size'         => 'md',
    'title'        => '',
    'subtitle'     => '',
    'back'         => true,
    'back_url'     => '',
    'aria_label'   => '',
    'active_nav'   => 'none',
    'actions'      => [],
    'hero_body'    => '',
];
$args = wp_parse_args($args ?? [], $defaults);

$size = in_array($args['size'], ['sm', 'md', 'lg'], true) ? $args['size'] : 'md';
$aria = $args['aria_label'] !== '' ? $args['aria_label'] : ($args['title'] !== '' ? $args['title'] : 'ページヘッダー');
?>

<header class="ainy-webapp-hero ainy-webapp-hero--<?php echo esc_attr($size); ?> mypage-v2-hero" aria-label="<?php echo esc_attr($aria); ?>">
    <?php
    aidunite_render_web_app_integrated_header_bar([
        'active_nav'           => $args['active_nav'],
        'notification_count'   => isset($args['notification_count']) ? (int) $args['notification_count'] : 0,
    ]);
    ?>

    <div class="ainy-webapp-hero-main">
        <?php if ($args['back'] || $args['title'] !== '' || !empty($args['actions'])) : ?>
        <div class="ainy-webapp-hero-title-row">
            <div class="ainy-webapp-hero-title-start">
                <?php if ($args['back']) : ?>
                    <?php if ($args['back_url'] !== '') : ?>
                        <a href="<?php echo esc_url($args['back_url']); ?>" class="ainy-webapp-hero-back" aria-label="戻る"><?php echo aidunite_render_theme_icon('chevron_left', ['width' => '22', 'height' => '22']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
                    <?php else : ?>
                        <button type="button" class="ainy-webapp-hero-back" onclick="history.back();" aria-label="戻る"><?php echo aidunite_render_theme_icon('chevron_left', ['width' => '22', 'height' => '22']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
                    <?php endif; ?>
                <?php endif; ?>
                <?php if ($args['title'] !== '') : ?>
                    <h1 class="ainy-webapp-hero-title"><?php echo esc_html($args['title']); ?></h1>
                <?php endif; ?>
            </div>
            <?php if (!empty($args['actions'])) : ?>
            <div class="ainy-webapp-hero-title-actions" role="group" aria-label="ページアクション">
                <?php foreach ($args['actions'] as $action) : ?>
                    <?php
                    if (!is_array($action)) {
                        continue;
                    }
                    $type  = isset($action['type']) ? (string) $action['type'] : 'link';
                    $icon_svg  = isset($action['icon_svg']) ? (string) $action['icon_svg'] : '';
                    $icon      = isset($action['icon']) ? (string) $action['icon'] : '';
                    $label = isset($action['label']) ? (string) $action['label'] : '';
                    $aria_action = isset($action['aria']) ? (string) $action['aria'] : $label;
                    $class = 'ainy-webapp-hero-action';
                    if (!empty($action['class'])) {
                        $class .= ' ' . sanitize_html_class($action['class']);
                    }
                    $icon_markup = '';
                    if ($icon_svg !== '' && function_exists('aidunite_render_theme_icon')) {
                        $icon_markup = aidunite_render_theme_icon($icon_svg, ['width' => '22', 'height' => '22']);
                    } elseif ($icon !== '' && preg_match('/^[a-z0-9_-]+$/i', $icon) && function_exists('aidunite_render_theme_icon')) {
                        $icon_markup = aidunite_render_theme_icon($icon, ['width' => '22', 'height' => '22']);
                    }
                    if ($type === 'button') {
                        $onclick = isset($action['onclick']) ? (string) $action['onclick'] : '';
                        ?>
                        <button type="button" class="<?php echo esc_attr($class); ?>" aria-label="<?php echo esc_attr($aria_action); ?>"<?php echo $onclick !== '' ? ' onclick="' . esc_attr($onclick) . '"' : ''; ?>>
                            <?php echo $icon_markup !== '' ? $icon_markup : esc_html($label); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        </button>
                        <?php
                    } else {
                        $href = isset($action['href']) ? (string) $action['href'] : '#';
                        ?>
                        <a href="<?php echo esc_url($href); ?>" class="<?php echo esc_attr($class); ?>" aria-label="<?php echo esc_attr($aria_action); ?>">
                            <?php echo $icon_markup !== '' ? $icon_markup : esc_html($label); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        </a>
                        <?php
                    }
                    ?>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($args['subtitle'] !== '') : ?>
            <p class="ainy-webapp-hero-subtitle"><?php echo esc_html($args['subtitle']); ?></p>
        <?php endif; ?>

        <?php
        if ($args['hero_body'] !== '') {
            echo '<div class="ainy-webapp-hero-body">' . wp_kses_post($args['hero_body']) . '</div>';
        }
        ?>
    </div>
</header>
