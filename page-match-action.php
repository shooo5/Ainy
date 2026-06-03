<?php
/**
 * Template Name: Match Action Page
 */

get_header();

if (!is_user_logged_in()) {
  wp_redirect(home_url('/login'));
  exit;
}

$post_id  = intval($_GET['id'] ?? 0);
$action   = sanitize_text_field($_GET['action'] ?? '');
$user_id  = get_current_user_id();
$my_team  = get_user_meta($user_id, 'team_id', true);
$post_team = get_post_meta($post_id, 'team_id', true);

// 権限チェック
if (!$post_id || !$action || $my_team != $post_team) {
  wp_die('⚠️ このマッチ申請は操作できません。');
}

// 実行処理
switch ($action) {
  case 'approve':
    update_field('match_status', '承認', $post_id);
    echo '<p>✅ 申請を承認しました。</p>';
    break;
  case 'reject':
    update_field('match_status', '拒否', $post_id);
    echo '<p>❌ 申請を拒否しました。</p>';
    break;
  case 'delete':
    wp_delete_post($post_id, true);
    echo '<p>🗑️ 申請を削除しました。</p>';
    break;
  default:
    wp_die('⚠️ 不正な操作です。');
}

echo '<a href="' . home_url('/match-requests') . '">⏪ 一覧に戻る</a>';

get_footer();
