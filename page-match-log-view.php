<?php
/**
 * Template Name: マッチ投稿ログ表示（開発者用）
 * 固定ページのスラッグを「match-log-view」にするとこのテンプレートが使われます。
 * 管理者（または aidunite_role=administrator）のみ表示。
 */

require_once get_template_directory() . '/functions/common/auth-middleware.php';
$auth_result = AidUniteAuthMiddleware::require_admin(true);
if (!$auth_result->is_valid()) {
    return;
}

get_header();

$post_types = ['match_request', 'match_board'];
?>

<!-- コンテンツが確実に表示されるよう .page-match-log-view でスコープ -->
<div class="wrap page-match-log-view" style="padding: var(--spacing-lg); max-width: 1400px; margin: 0 auto;">
<style>
.page-match-log-view { display: block; }
.page-match-log-view .log-table { width: 100%; border-collapse: collapse; }
.page-match-log-view .log-table th,
.page-match-log-view .log-table td { border: 1px solid var(--border-color); padding: var(--spacing-sm) var(--spacing-base); text-align: left; }
.page-match-log-view .post-type-header { margin: var(--spacing-xl) 0 var(--spacing-base); font-weight: bold; font-size: var(--font-size-lg); }
</style>
  <h2>📊 マッチ投稿ログ表示（開発者用）</h2>
  <p>match_request と match_board の投稿データ一覧を表示します。</p>

  <?php foreach ($post_types as $pt): ?>
    <div class="post-type-header">
      投稿タイプ: <?php echo esc_html($pt); ?>
    </div>

    <?php
    $posts = get_posts([
      'post_type' => $pt,
      'post_status' => 'any',
      'posts_per_page' => 50,
      'orderby' => 'date',
      'order' => 'DESC',
    ]);
    ?>

    <table class="log-table">
      <thead>
        <tr>
          <th>No</th>
          <th>ID</th>
          <th>タイトル</th>
          <th>ステータス</th>
          <th>作成日時</th>
          <th>from_schedule_id</th>
          <th>to_schedule_id</th>
          <th>その他メタフィールド</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($posts)) : ?>
          <tr>
            <td colspan="8">該当する投稿がありません。</td>
          </tr>
        <?php else :
          foreach ($posts as $i => $p):
            $row_no = $i + 1;
            $from_schedule_id = get_post_meta($p->ID, 'from_schedule_id', true);
            $to_schedule_id = get_post_meta($p->ID, 'to_schedule_id', true);

            $other_meta = [];
            if ($pt === 'match_request') {
              $other_meta['from_team_id'] = get_post_meta($p->ID, 'from_team_id', true);
              $other_meta['to_team_id'] = get_post_meta($p->ID, 'to_team_id', true);
              $other_meta['status'] = get_post_meta($p->ID, 'status', true);
              $other_meta['type'] = get_post_meta($p->ID, 'type', true);
            } elseif ($pt === 'match_board') {
              $other_meta['schedule_id'] = get_post_meta($p->ID, 'schedule_id', true);
              $other_meta['match_board_status'] = get_post_meta($p->ID, 'match_board_status', true);
              $other_meta['team_id'] = get_post_meta($p->ID, 'team_id', true);
            }
        ?>
          <tr>
            <td><?php echo (int) $row_no; ?></td>
            <td><?php echo esc_html($p->ID); ?></td>
            <td><?php echo esc_html($p->post_title); ?></td>
            <td><?php echo esc_html($p->post_status); ?></td>
            <td><?php echo esc_html($p->post_date); ?></td>
            <td><?php echo esc_html($from_schedule_id); ?></td>
            <td><?php echo esc_html($to_schedule_id); ?></td>
            <td>
              <?php foreach ($other_meta as $key => $value): ?>
                <div class="meta-field">
                  <strong><?php echo esc_html($key); ?>:</strong> <?php echo esc_html($value); ?>
                </div>
              <?php endforeach; ?>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  <?php endforeach; ?>

  <hr style="margin: 30px 0;">
  <h3>📈 統計情報</h3>
  <?php
  foreach ($post_types as $pt) {
    $total_posts = wp_count_posts($pt);
    echo "<p><strong>{$pt}:</strong> ";
    foreach ($total_posts as $status => $count) {
      if ($count > 0) {
        echo "{$status}: {$count}件, ";
      }
    }
    echo "</p>";
  }
  ?>
</div>

<?php get_footer(); ?>
