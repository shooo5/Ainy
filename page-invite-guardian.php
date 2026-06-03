<?php
/*
Template Name: 保護者招待フォーム
*/

if (!is_user_logged_in()) {
    wp_redirect(wp_login_url(get_permalink()));
    exit;
}

$current_user = wp_get_current_user();
$team_id = get_user_meta($current_user->ID, 'team_id', true);

// チーム未所属の場合はエラー表示
$error_message = '';
$success_message = '';
if (isset($_GET['sent']) && $_GET['sent'] === '1') {
    $success_message = '保護者に招待メールを送信しました。';
}
if (isset($_GET['error'])) {
    if ($_GET['error'] === 'no_team') {
        $error_message = 'チームに所属していません。先にチームを作成または参加してください。';
    } elseif ($_GET['error'] === 'send_failed') {
        $error_message = '招待メールの送信に失敗しました。しばらく経ってから再度お試しください。';
    }
}

get_header();

$invite_shell_opened = false;
if (function_exists('aidunite_web_app_page_shell_open')) {
    aidunite_web_app_page_shell_open([
        'page_class' => 'page-invite-guardian invite-guardian-container',
        'title' => '保護者をチームに招待',
        'subtitle' => 'メールアドレスを入力すると、参加用のURLが保護者に送信されます。',
    ]);
    $invite_shell_opened = true;
} else {
    echo '<div class="team-dashboard-container invite-guardian-container">';
}
?>
  <div class="mypage-card invite-guardian-card">

    <?php if ($error_message) : ?>
      <div class="alert alert-danger" role="alert">
        <?php echo esc_html($error_message); ?>
      </div>
    <?php endif; ?>

    <?php if ($success_message) : ?>
      <div class="alert alert-success" role="status">
        <?php echo esc_html($success_message); ?>
      </div>
    <?php endif; ?>

    <?php if ($team_id) : ?>
      <form method="post" action="">
        <div class="form-group">
          <label for="guardian_email" class="form-label">保護者のメールアドレス <span class="required" aria-hidden="true">*</span></label>
          <input type="email" name="guardian_email" id="guardian_email" class="form-control" required aria-required="true" placeholder="example@example.com" autocomplete="email" value="<?php echo isset($_POST['guardian_email']) ? esc_attr(wp_unslash($_POST['guardian_email'])) : ''; ?>">
        </div>
        <button type="submit" name="send_invite" value="1" class="btn btn-primary">
          招待メールを送信する
        </button>
      </form>
    <?php else : ?>
      <div class="alert alert-danger" role="alert">
        チームに所属していません。先にチームを作成または参加してください。
      </div>
      <p><a href="<?php echo esc_url(home_url('/mypage')); ?>" class="btn btn-secondary">マイページへ戻る</a></p>
    <?php endif; ?>
  </div>

<?php
if ($invite_shell_opened && function_exists('aidunite_web_app_page_shell_close')) {
    aidunite_web_app_page_shell_close();
} else {
    echo '</div>';
}
?>

<?php get_footer(); ?>
