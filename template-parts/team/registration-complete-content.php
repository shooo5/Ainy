<?php
/**
 * チーム申請受付完了
 *
 * @var string $mypage_url
 * @var array  $context
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!isset($mypage_url) || $mypage_url === '') {
    $mypage_url = isset($args['mypage_url']) ? (string) $args['mypage_url'] : home_url('/mypage/');
}

$context = isset($args['context']) && is_array($args['context'])
    ? $args['context']
    : (function_exists('aidunite_get_team_registration_complete_context')
        ? aidunite_get_team_registration_complete_context()
        : []);

$complete_teams = !empty($context['teams']) && is_array($context['teams']) ? $context['teams'] : [];
if ($complete_teams === [] && !empty($context['team_id'])) {
    $complete_teams = [$context];
}

if (!function_exists('aidunite_registration_complete_asset_icon')) {
    require_once get_stylesheet_directory() . '/functions/member/member-register-icons.php';
}

?>

<article class="team-reg-complete-card registration-complete-card--enter">
  <div class="team-reg-complete-confetti" aria-hidden="true">
    <span class="team-reg-complete-confetti__piece team-reg-complete-confetti__piece--1"></span>
    <span class="team-reg-complete-confetti__piece team-reg-complete-confetti__piece--2"></span>
    <span class="team-reg-complete-confetti__piece team-reg-complete-confetti__piece--3"></span>
    <span class="team-reg-complete-confetti__piece team-reg-complete-confetti__piece--4"></span>
    <span class="team-reg-complete-confetti__piece team-reg-complete-confetti__piece--5"></span>
    <span class="team-reg-complete-confetti__piece team-reg-complete-confetti__piece--6"></span>
  </div>

  <header class="team-reg-complete-hero">
    <div class="team-reg-complete-hero__check registration-complete-icon-wrap--enter">
      <span class="registration-complete-icon-glow" aria-hidden="true"></span>
      <div class="registration-complete-icon registration-complete-icon--check team-reg-complete-hero__check-icon" aria-hidden="true">
        <?php echo aidunite_registration_complete_asset_icon('check_circle'); ?>
      </div>
    </div>

    <h1 class="team-reg-complete-title">チーム申請を受け付けました。</h1>

    <p class="team-reg-complete-lead">
      運営チームが確認後、承認を行います。<br>
      <strong>1〜2営業日以内</strong>にご連絡します。
    </p>
  </header>

  <?php
  if (function_exists('aidunite_render_team_registration_pending_section')) {
      echo aidunite_render_team_registration_pending_section($complete_teams); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
  }
  ?>

  <div class="team-reg-complete-email">
    <span class="team-reg-complete-email__icon" aria-hidden="true">
      <?php echo aidunite_get_theme_icon_svg('mail', ['width' => '28', 'height' => '28']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    </span>
    <div class="team-reg-complete-email__body">
      <strong class="team-reg-complete-email__title">承認結果はメールでもお知らせします。</strong>
      <p class="team-reg-complete-email__desc">登録いただいたメールアドレスへご連絡いたします。<br>しばらくお待ちください。</p>
    </div>
  </div>

  <div class="team-reg-complete-actions">
    <a href="<?php echo esc_url($mypage_url); ?>" class="team-reg-complete-cta">
      <span class="team-reg-complete-cta__icon" aria-hidden="true"><?php echo aidunite_get_theme_icon_svg('home', ['width' => '22', 'height' => '22']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
      マイページへ
    </a>
  </div>
</article>
