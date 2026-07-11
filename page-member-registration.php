<?php
/*
Template Name: 新規会員登録ページ（仮登録受付）
*/

require_once get_stylesheet_directory() . '/functions/member/member-register-icons.php';

wp_enqueue_style(
    'registration-complete-style',
    get_stylesheet_directory_uri() . '/assets/css/pages/registration-complete.css',
    ['aidunite-style'],
    filemtime(get_stylesheet_directory() . '/assets/css/pages/registration-complete.css')
);

if (isset($_GET['pending']) && $_GET['pending'] == '1') {
    $mail_failed = isset($_GET['mail_failed']) && $_GET['mail_failed'] === '1';
    $login_url = home_url('/login/');

    get_header();
    ?>
    <div class="registration-complete-page">
      <div class="registration-complete-bg" aria-hidden="true">
        <div class="registration-complete-bg-gradient"></div>
        <div class="registration-complete-bg-wave registration-complete-bg-wave--1"></div>
        <div class="registration-complete-bg-wave registration-complete-bg-wave--2"></div>
      </div>

      <div class="registration-complete-container">
        <div class="registration-complete-card registration-complete-card--enter">
          <?php if ($mail_failed) : ?>
            <div class="registration-complete-icon-wrap registration-complete-icon-wrap--enter">
              <div class="registration-complete-icon registration-complete-icon--warning" aria-hidden="true">
                <?php echo aidunite_get_theme_icon_svg('brightness_alert', ['width' => '48', 'height' => '48']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
              </div>
            </div>
            <h1 class="registration-complete-title">確認メールの送信に失敗しました。</h1>
            <div class="registration-complete-description">
              <p>仮登録は完了していますが、本登録用メールを送信できませんでした。</p>
              <p>しばらくしてから再度お試しいただくか、管理者にお問い合わせください。</p>
            </div>
          <?php else : ?>
            <div class="registration-complete-icon-wrap registration-complete-icon-wrap--enter">
              <span class="registration-complete-icon-glow" aria-hidden="true"></span>
              <span class="registration-complete-sparkle registration-complete-sparkle--icon" aria-hidden="true">
                <?php echo aidunite_registration_complete_sparkle_icon(); ?>
              </span>
              <div class="registration-complete-icon registration-complete-icon--inbox" aria-hidden="true">
                <?php echo aidunite_registration_complete_asset_icon('forward_to_inbox'); ?>
              </div>
            </div>
            <h1 class="registration-complete-title">確認メールを送信しました。</h1>
            <div class="registration-complete-description">
              <p>メール内のリンクから、<br>本登録を完了してください。</p>
              <p>※届かない場合は、<br>迷惑メールフォルダもご確認ください。</p>
            </div>
          <?php endif; ?>

          <div class="registration-complete-actions">
            <a href="<?php echo esc_url($login_url); ?>" class="registration-complete-btn registration-complete-btn--primary">
              ログインへ
              <span class="registration-complete-btn__icon"><?php echo aidunite_get_theme_icon_svg('arrow_forward', ['width' => '20', 'height' => '20']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
            </a>
          </div>
        </div>
      </div>
    </div>
    <?php
    get_footer();
    exit;
}

wp_redirect(home_url('/member-register/'));
exit;
