<?php
/*
Template Name: チーム申請承認
*/

// 管理者権限チェック
if (!current_user_can('administrator')) {
    wp_die('このページは管理者のみアクセスできます。');
}

require_once get_stylesheet_directory() . '/functions/team/team-display-template.php';
require_once get_stylesheet_directory() . '/functions/common/empty-state.php';

get_header();
aidunite_team_display_styles();
?>

<div class="page-team-approval team-dashboard-container">
  <header class="dashboard-header page-team-approval__header">
    <h1 class="page-team-approval__title">チーム申請承認</h1>
    <p class="page-team-approval__lead">新しいチーム作成申請の承認・却下を行います</p>
  </header>

  <?php if (isset($_GET['approved'])) : ?>
  <div class="page-team-approval__notice page-team-approval__notice--success" role="status">
    ✅ チーム申請を承認しました。申請者に通知メールが送信されます。
  </div>
  <?php endif; ?>

  <?php if (isset($_GET['revision_requested'])) : ?>
  <div class="page-team-approval__notice page-team-approval__notice--success" role="status">
    確認・修正依頼を送信しました。申請者のマイページと通知一覧に表示されます。
  </div>
  <?php endif; ?>

  <?php if (isset($_GET['rejected'])) : ?>
  <div class="page-team-approval__notice page-team-approval__notice--rejected" role="status">
    ❌ チーム申請を却下しました。申請者に通知メールが送信されます。
  </div>
  <?php endif; ?>

  <?php
  try {
    $args = array(
      'post_type' => 'team',
      'post_status' => 'pending',
      'posts_per_page' => -1,
      'orderby' => 'date',
      'order' => 'ASC'
    );
    $pending_teams = new WP_Query($args);

    if (is_wp_error($pending_teams)) {
      echo '<div class="page-team-approval__error" role="alert">データの取得中にエラーが発生しました。</div>';
    } elseif ($pending_teams->have_posts()) {
      ?>
      <section class="page-team-approval__section" aria-label="承認待ち申請">
        <h2 class="section-title page-team-approval__section-title">承認待ち申請</h2>
        <div class="page-team-approval__cards">
          <?php
          while ($pending_teams->have_posts()) {
            $pending_teams->the_post();
            $team_id = get_the_ID();
            if ($team_id && get_post($team_id)) {
              echo aidunite_display_team_info($team_id, 'admin_approval');
            }
          }
          ?>
        </div>
      </section>
      <?php
    } else {
      if (function_exists('aidunite_empty_state_styles')) {
        aidunite_empty_state_styles();
      }
      echo aidunite_empty_state([
        'title' => '承認待ちの申請はありません',
        'message' => '現在、承認待ちのチーム申請はありません。',
        'type' => 'success',
      ]);
    }
    wp_reset_postdata();
  } catch (Exception $e) {
    echo '<div class="page-team-approval__error" role="alert">エラーが発生しました: ' . esc_html($e->getMessage()) . '</div>';
  }
  ?>
</div>

<style>
.page-team-approval { max-width: 1200px; margin: 0 auto; padding: var(--spacing-lg); }
.page-team-approval__header { margin-bottom: var(--spacing-lg); }
.page-team-approval__title { margin: 0 0 var(--spacing-xs); font-size: var(--font-size-2xl); }
.page-team-approval__lead { margin: 0; font-size: var(--font-size-base); }
.page-team-approval__notice {
  padding: var(--spacing-base);
  border-radius: var(--radius-base);
  margin-bottom: var(--spacing-lg);
  font-size: var(--font-size-sm);
}
.page-team-approval__notice--success {
  background: rgba(40, 167, 69, 0.1);
  border: 1px solid var(--success-color);
  color: var(--success-color);
}
.page-team-approval__notice--rejected {
  background: rgba(220, 53, 69, 0.1);
  border: 1px solid var(--danger-color);
  color: var(--danger-color);
}
.page-team-approval__error {
  padding: var(--spacing-base);
  background: rgba(220, 53, 69, 0.1);
  border: 1px solid var(--danger-color);
  color: var(--danger-color);
  border-radius: var(--radius-base);
}
.page-team-approval__section { margin-top: var(--spacing-xl); }
.page-team-approval__section-title { margin: 0 0 var(--spacing-base); font-size: var(--font-size-lg); color: var(--text-primary); }
.page-team-approval__cards {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
  gap: var(--spacing-lg);
}
@media (max-width: 768px) {
  .page-team-approval__cards { grid-template-columns: 1fr; }
}
</style>

<?php get_footer(); ?>
