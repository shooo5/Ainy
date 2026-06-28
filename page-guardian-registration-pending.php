<?php
/*
Template Name: 保護者仮登録完了
*/

require_once get_stylesheet_directory() . '/functions/member/member-register-icons.php';

$css = get_stylesheet_directory() . '/assets/css/pages/registration-complete.css';
if (is_readable($css)) {
    wp_enqueue_style(
        'registration-complete-style',
        get_stylesheet_directory_uri() . '/assets/css/pages/registration-complete.css',
        ['aidunite-style'],
        (string) filemtime($css)
    );
}

if (!isset($_GET['pending']) || (string) $_GET['pending'] !== '1') {
    wp_safe_redirect(home_url('/'));
    exit;
}

$mail_failed = isset($_GET['mail_failed']) && (string) $_GET['mail_failed'] === '1';
$invite_type = isset($_GET['invite_type']) ? sanitize_text_field(wp_unslash($_GET['invite_type'])) : 'email';
$is_qr = $invite_type === 'qr';
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
          <p>しばらくしてから再度お試しいただくか、チーム代表者にお問い合わせください。</p>
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
          <p>Ainy 会員登録（仮登録）が完了しました。</p>
          <p>メール内のリンクをクリックして、<strong>本登録（メール確認）</strong>を完了してください。</p>
          <?php if ($is_qr) : ?>
          <p>本登録後、チーム代表者の<strong>参加承認</strong>をお待ちください。<br>承認後にチーム機能をご利用いただけます。</p>
          <?php else : ?>
          <p>本登録が完了すると、招待されたチームの保護者として参加できます。</p>
          <?php endif; ?>
          <p>メールが見つからない場合は、迷惑メールフォルダもご確認ください。</p>
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
