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
            $from_schedule_id = '';
            $to_schedule_id = '';
            $other_meta = [];
            if ($pt === 'match_request') {
              $mr_log = function_exists('aidunite_match_request_get_canonical_meta')
                ? aidunite_match_request_get_canonical_meta((int) $p->ID)
                : [];
              $from_schedule_id = (int) ($mr_log['from_schedule_id'] ?? 0);
              $to_schedule_id = (int) ($mr_log['to_schedule_id'] ?? 0);
              $other_meta['from_team_id'] = (int) ($mr_log['from_team_id'] ?? 0);
              $other_meta['to_team_id'] = (int) ($mr_log['to_team_id'] ?? 0);
              $other_meta['status'] = (string) ($mr_log['status'] ?? '');
              $other_meta['type'] = (string) ($mr_log['type'] ?? '');
            } elseif ($pt === 'match_board') {
              $mb_log = function_exists('aidunite_match_board_get_canonical_meta')
                ? aidunite_match_board_get_canonical_meta((int) $p->ID)
                : [];
              $other_meta['schedule_id'] = (int) ($mb_log['schedule_id'] ?? 0);
              $other_meta['match_board_status'] = (string) ($mb_log['board_status'] ?? '');
              $other_meta['team_id'] = (int) ($mb_log['team_id'] ?? 0);
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
