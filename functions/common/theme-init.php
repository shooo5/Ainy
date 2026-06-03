<?php
/**
 * テーマ初期化（ロゴディレクトリ作成等）
 * functions.php から移設
 */

if (!defined('ABSPATH')) {
  exit;
}

/**
 * ロゴ画像用ディレクトリの作成
 */
function create_logo_directory() {
  if (defined('AIDUNITE_LOGO_DIR_CREATED')) {
    return;
  }
  $logo_dir = get_stylesheet_directory() . '/assets/images';
  if (!file_exists($logo_dir)) {
    wp_mkdir_p($logo_dir);
  }
  define('AIDUNITE_LOGO_DIR_CREATED', true);
}
add_action('after_setup_theme', 'create_logo_directory');
