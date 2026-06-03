<?php
/**
 * Template Name: Approve Registration
 * 登録確認リンクからアクセスされたときの処理
 *
 * @package AidUnite
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once get_stylesheet_directory() . '/functions/member/register-functions.php';
require_once get_stylesheet_directory() . '/functions/member/member-register-icons.php';

add_action('wp_enqueue_scripts', static function () {
    if (!function_exists('aidunite_is_approve_registration_request')
        || !aidunite_is_approve_registration_request()) {
        return;
    }
    $css = get_stylesheet_directory() . '/assets/css/pages/registration-complete.css';
    if (is_readable($css)) {
        wp_enqueue_style(
            'registration-complete-style',
            get_stylesheet_directory_uri() . '/assets/css/pages/registration-complete.css',
            ['aidunite-style'],
            (string) filemtime($css)
        );
    }
}, 20);

$approve_state = function_exists('aidunite_resolve_approve_registration_view_state')
    ? aidunite_resolve_approve_registration_view_state()
    : 'missing';

$login_url = home_url('/login/');
$home_url = home_url('/');
$register_url = home_url('/member-register/');

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
      <?php if ($approve_state === 'success' || $approve_state === 'already_completed') : ?>
        <div class="registration-complete-icon-wrap registration-complete-icon-wrap--enter">
          <span class="registration-complete-icon-glow" aria-hidden="true"></span>
          <div class="registration-complete-icon registration-complete-icon--check" aria-hidden="true">
            <?php echo aidunite_registration_complete_asset_icon('check_circle'); ?>
          </div>
        </div>
        <h1 class="registration-complete-title">登録が完了しました！</h1>
        <p class="registration-complete-subtitle">Ainyへようこそ</p>
        <div class="registration-complete-description">
          <p>アカウント登録が完了しました。</p>
          <p>練習試合の調整や、<br>チーム運営をもっとスムーズに始められます。</p>
        </div>
        <div class="registration-complete-next-step">
          <span class="registration-complete-sparkle" aria-hidden="true">
            <?php echo aidunite_registration_complete_sparkle_icon(); ?>
          </span>
          <p class="registration-complete-next-step-text">
            まずはログインして、<br>チーム作成やスケジュール登録を始めましょう。
          </p>
        </div>
        <div class="registration-complete-actions">
          <a href="<?php echo esc_url($login_url); ?>" class="registration-complete-btn registration-complete-btn--primary">
            ログインする
            <span class="registration-complete-btn__icon"><?php echo aidunite_get_theme_icon_svg('arrow_forward', ['width' => '20', 'height' => '20']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
          </a>
          <a href="<?php echo esc_url($home_url); ?>" class="registration-complete-btn registration-complete-btn--secondary">
            ホームへ戻る
          </a>
        </div>

      <?php elseif ($approve_state === 'invalid' || $approve_state === 'expired') : ?>
        <div class="registration-complete-icon-wrap registration-complete-icon-wrap--enter">
          <div class="registration-complete-icon registration-complete-icon--warning" aria-hidden="true">
            <?php echo aidunite_get_theme_icon_svg('brightness_alert', ['width' => '48', 'height' => '48']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
          </div>
        </div>
        <h1 class="registration-complete-title">無効なリンクです</h1>
        <div class="registration-complete-description">
          <?php if ($approve_state === 'expired') : ?>
            <p>本登録リンクの<strong>有効期限（24時間）が切れています</strong>。</p>
          <?php else : ?>
            <p>リンクが間違っているか、<strong>すでに使用済み</strong>です。</p>
          <?php endif; ?>
          <p>新しい登録をお試しください。</p>
        </div>
        <div class="registration-complete-actions">
          <a href="<?php echo esc_url($register_url); ?>" class="registration-complete-btn registration-complete-btn--primary">
            再登録する
          </a>
          <a href="<?php echo esc_url($home_url); ?>" class="registration-complete-btn registration-complete-btn--secondary">
            ホームへ戻る
          </a>
        </div>

      <?php else : ?>
        <div class="registration-complete-icon-wrap registration-complete-icon-wrap--enter">
          <div class="registration-complete-icon registration-complete-icon--warning" aria-hidden="true">
            <?php echo aidunite_get_theme_icon_svg('brightness_alert', ['width' => '48', 'height' => '48']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
          </div>
        </div>
        <h1 class="registration-complete-title">アクセスが不正です</h1>
        <div class="registration-complete-description">
          <p>メール内の本登録リンクからアクセスしてください。</p>
        </div>
        <div class="registration-complete-actions">
          <a href="<?php echo esc_url($register_url); ?>" class="registration-complete-btn registration-complete-btn--primary">
            新規登録
          </a>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php get_footer(); ?>
