<?php
/**
 * Phase 9: インライン抽出済みページテンプレートの CSS/JS を一括 enqueue
 */

if (!defined('ABSPATH')) {
  exit;
}

/**
 * @return array<int, array{slug: string, css: bool, js: bool, deps?: string[]}>
 */
function aidunite_get_page_asset_manifest() {
  static $manifest = null;
  if ($manifest !== null) {
    return $manifest;
  }

  $manifest = [
    ['slug' => 'about', 'css' => true, 'js' => false],
    ['slug' => 'disclaimer', 'css' => true, 'js' => false],
    ['slug' => 'cookie-policy', 'css' => true, 'js' => false],
    ['slug' => 'privacy-policy', 'css' => true, 'js' => false],
    ['slug' => 'terms-of-service', 'css' => true, 'js' => false],
    ['slug' => 'plan-info', 'css' => true, 'js' => false],
    ['slug' => 'service', 'css' => true, 'js' => false],
    ['slug' => 'payment-required', 'css' => true, 'js' => false],
    ['slug' => 'team-approval', 'css' => true, 'js' => false],
    ['slug' => 'player-stats', 'css' => true, 'js' => false],
    ['slug' => 'match-log-view', 'css' => true, 'js' => false],
    ['slug' => 'edit-player', 'css' => true, 'js' => false],
    ['slug' => 'contact', 'css' => true, 'js' => true, 'deps' => ['aidunite-feedback-utils', 'aidunite-ui-state-helpers', 'aidunite-ui-tab-controller']],
    ['slug' => 'faq', 'css' => true, 'js' => true, 'deps' => ['aidunite-ui-tab-controller']],
    ['slug' => 'feedback', 'css' => true, 'js' => true, 'deps' => ['aidunite-feedback-utils', 'aidunite-ui-state-helpers']],
    ['slug' => 'guide', 'css' => true, 'js' => true, 'deps' => ['aidunite-ui-tab-controller']],
    ['slug' => 'press', 'css' => true, 'js' => true],
    ['slug' => 'regulation', 'css' => true, 'js' => true, 'deps' => ['aidunite-ui-tab-controller']],
    ['slug' => 'system-maintenance', 'css' => false, 'js' => true],
    ['slug' => 'template-dashboard', 'css' => false, 'js' => true, 'deps' => ['aidunite-ui-tab-controller']],
    ['slug' => 'admin-schedule-list', 'css' => true, 'js' => true],
    ['slug' => 'admin-match-feedback-list', 'css' => true, 'js' => true],
    ['slug' => 'admin-user-list', 'css' => true, 'js' => true],
    ['slug' => 'match-analytics', 'css' => true, 'js' => true, 'deps' => ['jquery']],
    ['slug' => 'parent-payment', 'css' => true, 'js' => true, 'deps' => ['jquery']],
    ['slug' => 'team-payment-management', 'css' => true, 'js' => true, 'deps' => ['jquery']],
    ['slug' => 'team-tuition-collections', 'css' => true, 'js' => true, 'deps' => []],
    ['slug' => 'registration-preview', 'css' => true, 'js' => true],
    ['slug' => 'team-registration-preview', 'css' => true, 'js' => true],
    ['slug' => 'sample-preview', 'css' => true, 'js' => true, 'deps' => ['aidunite-ui-tab-controller']],
  ];

  return $manifest;
}

/**
 * @param string $slug
 */
function aidunite_page_asset_matches($slug) {
  $template = 'page-' . $slug . '.php';
  return is_page($slug) || is_page_template($template);
}

/**
 * @param string $slug
 */
function aidunite_page_asset_handle($slug) {
  return 'aidunite-page-' . $slug;
}

/**
 * @param string $slug
 * @param array<string, mixed> $data
 */
function aidunite_page_asset_localize($slug, array $data) {
  $handle = aidunite_page_asset_handle($slug);
  $object_name = 'aidunitePage_' . str_replace('-', '_', $slug);
  wp_localize_script($handle, $object_name, $data);
}

function aidunite_enqueue_manifest_page_assets() {
  $theme_dir = get_stylesheet_directory();
  $theme_uri = get_stylesheet_directory_uri();

  foreach (aidunite_get_page_asset_manifest() as $entry) {
    $slug = $entry['slug'];
    if (!aidunite_page_asset_matches($slug)) {
      continue;
    }

    $handle = aidunite_page_asset_handle($slug);
    $style_deps = ['aidunite-style'];

    if (!empty($entry['css'])) {
      if (in_array($slug, ['parent-payment', 'team-payment-management', 'team-tuition-collections', 'plan-info'], true)
        && function_exists('aidunite_enqueue_payment_setup_shared_styles')) {
        aidunite_enqueue_payment_setup_shared_styles();
        $style_deps[] = 'aidunite-payment-setup';
      }
      $css_path = $theme_dir . '/assets/css/pages/' . $slug . '.css';
      if (is_readable($css_path)) {
        // admin-schedule-list は従来 handle で既に読み込み済みの場合がある
        $legacy_css_handle = $slug === 'admin-schedule-list' ? 'aidunite-admin-schedule-list' : '';
        if ($legacy_css_handle !== '' && wp_style_is($legacy_css_handle, 'enqueued')) {
          // 従来ブロックと同一ファイルのため重複 enqueue を避ける
        } else {
          wp_enqueue_style(
            $handle,
            $theme_uri . '/assets/css/pages/' . $slug . '.css',
            $style_deps,
            (string) filemtime($css_path)
          );
        }
      }
    }

    if (!empty($entry['js'])) {
      $js_path = $theme_dir . '/assets/js/pages/' . $slug . '.js';
      if (is_readable($js_path)) {
        $script_deps = $entry['deps'] ?? [];
        wp_enqueue_script(
          $handle,
          $theme_uri . '/assets/js/pages/' . $slug . '.js',
          $script_deps,
          (string) filemtime($js_path),
          true
        );
      }
    }
  }
}
