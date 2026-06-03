<?php
/**
 * Template Name: チーム詳細ページ
 */

// 共通テンプレート関数を読み込み
require_once get_stylesheet_directory() . '/functions/team/team-display-template.php';

get_header();

// スタイルとスクリプトを出力
aidunite_team_display_styles();
aidunite_team_support_script();

$team_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$team_id) {
  echo '<p>⚠️ チームIDが指定されていません。</p>';
  get_footer();
  exit;
}

$team_post = get_post($team_id);
if (!$team_post || $team_post->post_type !== 'team') {
  echo '<p>⚠️ 有効なチーム情報が見つかりません。</p>';
  get_footer();
  exit;
}

// 支援ボタンを表示するかどうかを判定（一般ユーザー向け）
$current_user_id = get_current_user_id();
$show_support_button = !empty($current_user_id) && !current_user_can('administrator');

$additional_data = [
    'show_support_button' => $show_support_button
];

?>

<div class="wrap">
  <h2><?php echo esc_html(get_the_title($team_id)); ?>（チーム紹介）</h2>

  <?php echo aidunite_display_team_info($team_id, 'supporter_view', $additional_data); ?>
</div>

<?php get_footer(); ?>
