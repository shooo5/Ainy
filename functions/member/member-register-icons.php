<?php
/**
 * 登録完了画面用アセット SVG（forward_to_inbox / check_circle など）
 *
 * @param string $filename 拡張子なしファイル名
 * @return string
 */
function aidunite_registration_complete_asset_icon($filename) {
    $relative = 'assets/images/icons/' . $filename . '.svg';
    $path = get_theme_file_path($relative);

    if (!is_readable($path)) {
        return '';
    }

    $svg = file_get_contents($path);
    if ($svg === false || $svg === '') {
        return '';
    }

    $svg = preg_replace('/\s*fill="[^"]*"/', '', $svg);
    $svg = preg_replace(
        '/<svg\b/',
        '<svg class="registration-complete-icon__svg" width="48" height="48" fill="currentColor" aria-hidden="true"',
        $svg,
        1
    );

    return $svg;
}

/**
 * 登録完了画面の小さな sparkle 装飾
 *
 * @return string
 */
function aidunite_registration_complete_sparkle_icon() {
    return '<svg class="registration-complete-sparkle__svg" width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">'
        . '<path d="M12 2l1.4 4.3L18 8l-4.3 1.4L12 14l-1.4-4.3L6 8l4.6-1.7L12 2z"/>'
        . '</svg>';
}
