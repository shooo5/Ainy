<?php
/**
 * アイコン用ヘルパー（docs/icon-usage-guide.md 準拠）
 * インラインSVGを返し、fill="currentColor" で親の文字色に連動させる。
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * アイコン名からインラインSVGマークアップを取得する
 *
 * assets/images/icons/{$icon_name}.svg を読み、fill を currentColor に置換して返す。
 * ナビ・メニューなどで利用（icon-usage-guide.md 手順A）。
 *
 * @param string $icon_name アイコン名（拡張子なし）。例: guardian-invite, settings
 * @param array  $attrs     追加属性。例: ['width' => '20', 'height' => '20']
 * @return string インラインSVGのHTML。ファイルがない場合は空文字。
 */
function aidunite_get_inline_icon_svg($icon_name, $attrs = []) {
    if (empty($icon_name)) {
        return '';
    }

    $path = get_stylesheet_directory() . '/assets/images/icons/' . sanitize_file_name($icon_name) . '.svg';
    if (!is_readable($path)) {
        return '';
    }

    $svg = file_get_contents($path);
    if ($svg === false) {
        return '';
    }

    // ガイド準拠: 色は親の文字色に連動（currentColor）
    if (preg_match('/\sfill="/', $svg)) {
        $svg = preg_replace('/\s*fill="[^"]*"/', ' fill="currentColor"', $svg, 1);
    } else {
        $svg = preg_replace('/<svg/', '<svg fill="currentColor"', $svg, 1);
    }

    // 既定属性
    if (!preg_match('/\baria-hidden\b/', $svg)) {
        $svg = preg_replace('/<svg/', '<svg aria-hidden="true"', $svg, 1);
    }

    // 追加属性（width/height など）
    foreach ($attrs as $key => $value) {
        $key = sanitize_key($key);
        $value = esc_attr($value);
        if (preg_match('/\s' . preg_quote($key, '/') . '="[^"]*"/', $svg)) {
            $svg = preg_replace('/\s' . preg_quote($key, '/') . '="[^"]*"/', ' ' . $key . '="' . $value . '"', $svg, 1);
        } else {
            $svg = preg_replace('/<svg/', '<svg ' . $key . '="' . $value . '"', $svg, 1);
        }
    }

    return trim($svg);
}

/**
 * テーマ SVG アイコンの URL ベース（末尾スラッシュ付き）
 *
 * @return string
 */
function aidunite_get_theme_icons_uri() {
    return get_stylesheet_directory_uri() . '/assets/images/icons/';
}

/**
 * テーマ SVG アイコンのディレクトリパス（末尾スラッシュ付き）
 *
 * @return string
 */
function aidunite_get_theme_icons_path() {
    return get_stylesheet_directory() . '/assets/images/icons/';
}

/**
 * UI 用 SVG をインラインで取得（currentColor 対応）
 *
 * `aidunite_get_chat_icon_svg` の後継。ファイルは assets/images/icons/{name}.svg。
 *
 * @param string $basename  拡張子なし（例: home）
 * @param array  $attrs
 * @param bool   $use_current_color
 * @return string
 */
function aidunite_get_theme_icon_svg($basename, $attrs = [], $use_current_color = true) {
    if ($basename === '') {
        return '';
    }

    $filename = sanitize_file_name($basename) . '.svg';

    if (!$use_current_color) {
        return aidunite_get_inline_icon_svg_from_relative($filename, $attrs, false);
    }

    return aidunite_get_inline_icon_svg($basename, $attrs);
}

/**
 * @deprecated 3.0 use aidunite_get_theme_icon_svg()
 */
function aidunite_get_chat_icon_svg($basename, $attrs = [], $use_current_color = true) {
    return aidunite_get_theme_icon_svg($basename, $attrs, $use_current_color);
}

/**
 * テーマ内 SVG アイコンの相対パスからインライン SVG を取得
 *
 * @param string $relative_path assets/images/icons/ からの相対（例: home.svg）
 * @param array  $attrs
 * @param bool   $use_current_color fill を currentColor に置換するか
 * @return string
 */
function aidunite_get_inline_icon_svg_from_relative($relative_path, $attrs = [], $use_current_color = true) {
    if (empty($relative_path)) {
        return '';
    }

    $relative_path = ltrim(str_replace('\\', '/', $relative_path), '/');
    if (strpos($relative_path, '..') !== false) {
        return '';
    }

    $path = get_stylesheet_directory() . '/assets/images/icons/' . $relative_path;
    if (!is_readable($path) || !preg_match('/\.svg$/i', $relative_path)) {
        return '';
    }

    $svg = file_get_contents($path);
    if ($svg === false) {
        return '';
    }

    if ($use_current_color) {
        if (preg_match('/\sfill="/', $svg)) {
            $svg = preg_replace('/\s*fill="[^"]*"/', ' fill="currentColor"', $svg, 1);
        } else {
            $svg = preg_replace('/<svg/', '<svg fill="currentColor"', $svg, 1);
        }
    }

    if (!preg_match('/\baria-hidden\b/', $svg)) {
        $svg = preg_replace('/<svg/', '<svg aria-hidden="true"', $svg, 1);
    }

    foreach ($attrs as $key => $value) {
        $key = sanitize_key($key);
        $value = esc_attr($value);
        if (preg_match('/\s' . preg_quote($key, '/') . '="[^"]*"/', $svg)) {
            $svg = preg_replace('/\s' . preg_quote($key, '/') . '="[^"]*"/', ' ' . $key . '="' . $value . '"', $svg, 1);
        } else {
            $svg = preg_replace('/<svg/', '<svg ' . $key . '="' . $value . '"', $svg, 1);
        }
    }

    return trim($svg);
}

/**
 * ファイル名からオリジナル色を保持すべき SVG か判定（chat 用カラーバリアント等）
 *
 * @param string $filename
 * @return bool
 */
function aidunite_svg_icon_preserves_original_colors($filename) {
    $base = pathinfo($filename, PATHINFO_FILENAME);
    if (preg_match('/_(FFFFFF|999999|[0-9A-Fa-f]{6}|[0-9A-Fa-f]{8})(_|$)/', $base)) {
        return true;
    }
    if (preg_match('/_24dp_.*_FILL/i', $base)) {
        return true;
    }
    return false;
}

/**
 * ファイル名に FFFFFF（白）バリアントが含まれるか
 *
 * @param string $filename
 * @return bool
 */
function aidunite_svg_icon_is_white_variant($filename) {
    $base = pathinfo($filename, PATHINFO_FILENAME);
    return (bool) preg_match('/_FFFFFF(_|$)/i', $base);
}

/**
 * デザインリファレンス用 — assets/images/icons/ 配下の SVG を自動収集
 *
 * 新規 SVG をフォルダに追加するだけで一覧に反映される。
 *
 * @return array<int, array{slug: string, label: string, description: string, items: array<int, array>}>
 */
function aidunite_get_theme_svg_icon_inventory() {
    $icons_base = aidunite_get_theme_icons_path();

    $groups = [];

    $svg_files = glob($icons_base . '*.svg') ?: [];
    sort($svg_files, SORT_NATURAL | SORT_FLAG_CASE);
    $file_items = [];
    foreach ($svg_files as $file) {
        $filename = basename($file);
        $name = pathinfo($filename, PATHINFO_FILENAME);
        $preserve = aidunite_svg_icon_preserves_original_colors($filename);
        $file_items[] = [
            'id'              => 'svg/' . $name,
            'name'            => $name,
            'relative'        => $filename,
            'preserve_colors' => $preserve,
            'white_on_dark'   => aidunite_svg_icon_is_white_variant($filename),
            'helper'          => $preserve ? 'img' : 'aidunite_get_inline_icon_svg',
        ];
    }
    if ($file_items) {
        $groups[] = [
            'slug'        => 'files',
            'label'       => 'SVG ファイル（assets/images/icons/）',
            'description' => 'ボトムナビ・会員登録・マイページ・マッチ詳細・チャット等。1ファイル + <code>currentColor</code> + CSS。<code>sliders</code> / <code>heart_check</code> 等の固有色はファイル内 fill または <code>use_current_color=false</code>。',
            'items'       => $file_items,
        ];
    }

    $groups[] = [
        'slug'        => 'inline-misc',
        'label'       => 'その他（PHP 内蔵）',
        'description' => '登録完了画面の装飾等。',
        'items'       => [
            [
                'id'              => 'inline-misc/sparkle',
                'name'            => 'sparkle',
                'relative'        => '',
                'preserve_colors' => false,
                'helper'          => 'aidunite_registration_complete_sparkle_icon',
            ],
        ],
    ];

    /**
     * デザインリファレンス SVG 一覧にグループを追加するフィルター
     *
     * @param array $groups aidunite_get_theme_svg_icon_inventory() の groups 配列
     */
    return apply_filters('aidunite_svg_icon_inventory_groups', $groups);
}

/**
 * デザインリファレンス用 — 1 アイコンのプレビュー HTML
 *
 * @param array $item inventory item
 * @return string
 */
function aidunite_render_design_ref_icon_preview($item) {
    $attrs = ['width' => '32', 'height' => '32', 'class' => 'ref-icon-preview__svg'];

    // 一覧表は形状カタログのため常に currentColor（CSS で黒に統一）。原色 img は使わない。
    if (!empty($item['relative'])) {
        $svg = aidunite_get_inline_icon_svg_from_relative($item['relative'], $attrs, true);
        if ($svg !== '') {
            return $svg;
        }
    }

    $helper = $item['helper'] ?? '';
    if ($helper === 'aidunite_get_inline_icon_svg' && !empty($item['name'])) {
        return aidunite_get_inline_icon_svg($item['name'], $attrs);
    }
    if ($helper === 'aidunite_registration_complete_sparkle_icon' && function_exists('aidunite_registration_complete_sparkle_icon')) {
        return aidunite_registration_complete_sparkle_icon();
    }

    return '';
}
