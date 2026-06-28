<?php
/*
Template Name: チーム申請ページ
*/
get_header();

if (!is_user_logged_in()) {
  echo '<p>このページを利用するにはログインが必要です。</p>';
  get_footer();
  exit;
}

$current_user_id = get_current_user_id();

// メッセージ表示
if (isset($_GET['applied']) && $_GET['applied'] === '1') {
  echo '<div class="notice-success" style="padding:10px;background:rgba(40, 167, 69, 0.1);border:1px solid var(--success-color);margin-bottom:20px;">
          公開チームへの申請が送信されました。
        </div>';
}
if (isset($_GET['secret_applied']) && $_GET['secret_applied'] === '1') {
  echo '<div class="notice-success" style="padding:10px;background:rgba(40, 167, 69, 0.1);border:1px solid var(--success-color);margin-bottom:20px;">
          シークレットコードの申請が完了しました。
        </div>';
}
if (isset($_GET['secret_failed']) && $_GET['secret_failed'] === '1') {
  echo '<div class="notice-error" style="padding:10px;background:rgba(220, 53, 69, 0.1);border:1px solid var(--danger-color);margin-bottom:20px;">
          シークレットコードが一致しませんでした。
        </div>';
}
if (isset($_GET['invited']) && $_GET['invited'] == '1') {
  echo '<div class="notice-success" style="padding:10px;background:rgba(40, 167, 69, 0.1);border:1px solid var(--success-color);margin-bottom:20px;">
          招待コードでの申請が完了しました。
        </div>';
}
if (isset($_GET['invite_failed']) && $_GET['invite_failed'] == '1') {
  echo '<div class="notice-error" style="padding:10px;background:rgba(220, 53, 69, 0.1);border:1px solid var(--danger-color);margin-bottom:20px;">
          招待コードが無効です。
        </div>';
}

// 公開チーム一覧の取得（公開 or シークレット用）
$args = array(
  'post_type' => 'team',
  'post_status' => 'publish',
  'meta_query' => array(
    array(
      'key' => 'team_visibility',
      'value' => 'public',
      'compare' => '='
    )
  ),
  'posts_per_page' => -1
);
$public_teams = get_posts($args);
?>

<div class="team-apply-wrapper" style="padding: 20px;">

  <h2>公開チームから選んで申請</h2>
  <?php if ($public_teams): ?>
    <ul style="list-style:none;padding:0;">
      <?php foreach ($public_teams as $team): ?>
        <li style="border:1px solid var(--border-color);margin-bottom:15px;padding:10px;">
          <h3><?php echo esc_html($team->post_title); ?></h3>
          <?php
            $apply_display = function_exists('aidunite_team_get_display_bundle')
                ? aidunite_team_get_display_bundle((int) $team->ID)
                : [];
            $apply_description = (string) ($apply_display['team_description'] ?? $apply_display['description_legacy'] ?? '');
            $accepting = (string) ($apply_display['accepting_applications'] ?? '');
            $has_secret = (string) ($apply_display['secret_code'] ?? '');
          ?>
          <p><?php echo esc_html($apply_description); ?></p>

          <?php if ($accepting === 'true' && empty($has_secret)): ?>
            <!-- ➀ 公開申請受付中 -->
            <form method="post">
              <input type="hidden" name="team_id" value="<?php echo esc_attr($team->ID); ?>">
              <input type="submit" name="submit_public_request" value="このチームに申請する">
            </form>

          <?php elseif ($accepting !== 'true'): ?>
            <!-- ② 申請不可 -->
            <p style="color: gray;">現在このチームは申請を受け付けておりません。</p>

          <?php elseif (!empty($has_secret)): ?>
            <!-- ④ シークレット申請対応：一覧表示のみ、下部で入力 -->
            <p style="color: gray;">このチームへの申請には専用コードが必要です。</p>
          <?php endif; ?>

        </li>
      <?php endforeach; ?>
    </ul>
  <?php else: ?>
    <p>現在、公開チームは登録されていません。</p>
  <?php endif; ?>

  <hr style="margin: 40px 0;">

  <h2>シークレットコードで参加申請</h2>
  <form method="post" style="max-width:400px;">
    <label for="secret_code">チームコードを入力：</label><br>
    <input type="text" name="secret_code" id="secret_code" required style="width:100%;margin:10px 0;"><br>
    <input type="submit" name="submit_secret_request" value="コードで申請">
  </form>

</div>

<?php get_footer(); ?>
