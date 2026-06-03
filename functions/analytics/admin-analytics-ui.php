<?php
/**
 * 経営分析画面共通 UI（ナビ・レイアウト）
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * @return array<int, array{slug:string, label:string, path:string}>
 */
function aidunite_analytics_get_admin_nav_items() {
    return [
        ['slug' => 'funnel', 'label' => '成立ファネル', 'path' => '/admin-team-funnel'],
        ['slug' => 'active', 'label' => 'アクティブ', 'path' => '/admin-analytics-active'],
        ['slug' => 'pv', 'label' => 'PVデータ', 'path' => '/admin-analytics-pv'],
        ['slug' => 'churn', 'label' => '離脱分析', 'path' => '/admin-analytics-churn'],
        ['slug' => 'ai', 'label' => 'AI月次レポート', 'path' => '/admin-analytics-ai-report'],
    ];
}

/**
 * @param string $current_slug funnel|active|pv|churn|ai
 */
function aidunite_analytics_render_admin_nav($current_slug) {
    $current_slug = sanitize_key($current_slug);
    echo '<nav class="admin-analytics-nav" aria-label="経営分析メニュー">';
    echo '<ul class="admin-analytics-nav__list">';
    foreach (aidunite_analytics_get_admin_nav_items() as $item) {
        $active = $item['slug'] === $current_slug ? ' is-active' : '';
        $url = home_url($item['path']);
        echo '<li class="admin-analytics-nav__item">';
        echo '<a class="admin-analytics-nav__link' . esc_attr($active) . '" href="' . esc_url($url) . '"';
        if ($active !== '') {
            echo ' aria-current="page"';
        }
        echo '>';
        echo esc_html($item['label']);
        echo '</a></li>';
    }
    echo '</ul>';
    echo '<a class="admin-analytics-nav__back" href="' . esc_url(home_url('/ainy-dashboard')) . '">← ダッシュボード</a>';
    echo '</nav>';
}

/**
 * @param string $title
 * @param string $description
 */
function aidunite_analytics_render_page_header($title, $description = '') {
    echo '<header class="admin-analytics-header">';
    echo '<h1>' . esc_html($title) . '</h1>';
    if ($description !== '') {
        echo '<p class="admin-analytics-header__lead">' . esc_html($description) . '</p>';
    }
    echo '</header>';
}

/**
 * @param string $label
 * @param string|int $value
 * @param string $meta
 */
function aidunite_analytics_render_kpi_card($label, $value, $meta = '') {
    echo '<div class="admin-analytics-kpi-card">';
    echo '<span class="admin-analytics-kpi-card__label">' . esc_html($label) . '</span>';
    echo '<span class="admin-analytics-kpi-card__value">' . esc_html((string) $value) . '</span>';
    if ($meta !== '') {
        echo '<span class="admin-analytics-kpi-card__meta">' . esc_html($meta) . '</span>';
    }
    echo '</div>';
}
