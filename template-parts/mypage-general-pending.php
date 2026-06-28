<?php
/**
 * 一般ユーザー（general）承認待ちマイページ — Joy UI 統一
 *
 * @package AidUnite
 */

if (!defined('ABSPATH')) {
    exit;
}

$context = isset($args['context']) && is_array($args['context'])
    ? $args['context']
    : (function_exists('aidunite_mypage_general_pending_context')
        ? aidunite_mypage_general_pending_context()
        : []);

if (function_exists('aidunite_get_team_registration_complete_context')) {
    $full = aidunite_get_team_registration_complete_context();
    if (!empty($full['team_id'])) {
        $context = array_merge($context, $full);
    }
}

$pending_teams = [];
if (!empty($context['teams']) && is_array($context['teams'])) {
    $pending_teams = $context['teams'];
} elseif (!empty($context['team_id'])) {
    $pending_teams = [$context];
}

$submitted_at      = (string) ($context['submitted_at'] ?? '');
$is_dual_pending   = count($pending_teams) > 1;
$service_url       = home_url('/service/');
$current_user      = wp_get_current_user();
$notification_count = function_exists('aidunite_get_notification_count')
    ? (int) aidunite_get_notification_count((int) $current_user->ID)
    : 0;
?>

<div class="mypage-joy mypage-joy--general-pending" id="mypage-joy-root" data-mypage-ui="joy-pending" role="main">
  <header class="ainy-webapp-hero ainy-webapp-hero--lg mypage-joy-hero" aria-label="マイページヘッダー">
    <?php
    if (function_exists('aidunite_render_web_app_integrated_header_bar')) {
        aidunite_render_web_app_integrated_header_bar([
            'active_nav'         => 'mypage',
            'notification_count' => $notification_count,
        ]);
    }
    ?>

    <div class="mypage-joy-profile">
      <div class="mypage-joy-profile__logo-wrap">
        <div class="mypage-joy-profile__logo mypage-joy-profile__logo--placeholder mypage-general-pending-joy__logo" aria-hidden="true">
          <?php echo aidunite_get_theme_icon_svg('hourglass_empty', ['width' => '40', 'height' => '40']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </div>
      </div>
      <div class="mypage-joy-profile__text">
        <h1 class="mypage-joy-profile__team">チーム申請 承認待ち</h1>
        <p class="mypage-joy-profile__login"><?php echo esc_html($current_user->display_name); ?></p>
      </div>
    </div>
  </header>

  <main class="ainy-webapp-content mypage-joy-content">
    <div class="mypage-joy-stack mypage-general-pending-joy">
      <section class="mypage-general-pending-joy__status" role="status">
        <div class="mypage-general-pending-joy__status-body">
          <span class="mypage-general-pending-joy__status-badge">運営チームが確認中</span>

          <p class="mypage-general-pending-joy__status-message">
            <?php if ($is_dual_pending) : ?>
              男子・女子のチーム申請を受け付けました。<br>
            <?php else : ?>
              チーム申請を受け付けました。<br>
            <?php endif; ?>
            <strong>1〜2営業日以内</strong> にご連絡します。
          </p>

          <?php if ($submitted_at !== '') : ?>
            <p class="mypage-general-pending-joy__status-date">
              <span class="mypage-general-pending-joy__status-date-label">申請日時</span>
              <time datetime="<?php echo esc_attr($submitted_at); ?>"><?php echo esc_html($submitted_at); ?></time>
            </p>
          <?php endif; ?>
        </div>
      </section>

      <?php
      if ($pending_teams !== [] && function_exists('aidunite_render_team_registration_pending_section')) {
          echo aidunite_render_team_registration_pending_section($pending_teams); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
      }
      ?>

      <div class="team-reg-complete-email mypage-general-pending-joy__email">
        <span class="team-reg-complete-email__icon" aria-hidden="true">
          <?php echo aidunite_get_theme_icon_svg('mail', ['width' => '28', 'height' => '28']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </span>
        <div class="team-reg-complete-email__body">
          <strong class="team-reg-complete-email__title">承認結果はメールでもお知らせします。</strong>
          <p class="team-reg-complete-email__desc">登録いただいたメールアドレスへご連絡いたします。<br>しばらくお待ちください。</p>
        </div>
      </div>

      <p class="mypage-general-pending-joy__pricing-note">
        チーム申請は無料です。<br>
        承認後の利用料金については、<br>
        <a href="<?php echo esc_url($service_url); ?>">サービス・料金</a>
        をご確認ください。
      </p>
    </div>
  </main>
</div>
