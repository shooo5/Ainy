<?php
/**
 * 保護者（parent）承認待ちマイページ
 *
 * @package AidUnite
 */

if (!defined('ABSPATH')) {
    exit;
}

$context = isset($args['context']) && is_array($args['context'])
    ? $args['context']
    : (function_exists('aidunite_parent_get_pending_mypage_context')
        ? aidunite_parent_get_pending_mypage_context(get_current_user_id())
        : []);

$pending_teams = is_array($context['teams'] ?? null) ? $context['teams'] : [];
$show_registered_notice = !empty($args['show_registered_notice']);
$show_guard_notice = !empty($args['show_guard_notice']);
$icon_base = function_exists('aidunite_get_theme_icons_uri') ? aidunite_get_theme_icons_uri() : '';
?>

<div class="mypage-general-pending mypage-parent-pending" role="main">
  <?php if ($show_registered_notice) : ?>
  <div class="guardian-flow-alert guardian-flow-alert--success" role="status" style="margin-bottom: 1rem;">
    参加申請を受け付けました。チーム代表者の承認をお待ちください。
  </div>
  <?php endif; ?>

  <?php if ($show_guard_notice) : ?>
  <div class="guardian-flow-alert guardian-flow-alert--pending" role="status" style="margin-bottom: 1rem;">
    承認が完了するまで、スケジュール・連絡・出欠などの機能はご利用いただけません。
  </div>
  <?php endif; ?>

  <article class="mypage-general-pending__card">
    <header class="mypage-general-pending__intro">
      <h1 class="mypage-general-pending__title">マイページ</h1>
      <p class="mypage-general-pending__subtitle">チーム参加の承認をお待ちください。</p>
    </header>

    <div class="mypage-general-pending__status" role="status">
      <div class="mypage-general-pending__status-icon-wrap">
        <span class="mypage-general-pending__status-icon" aria-hidden="true">
          <?php if ($icon_base !== '') : ?>
          <img src="<?php echo esc_url($icon_base . 'hourglass_empty.svg'); ?>" alt="" width="28" height="28" decoding="async">
          <?php else : ?>
          ⏳
          <?php endif; ?>
        </span>
      </div>

      <div class="mypage-general-pending__status-col mypage-general-pending__status-col--state">
        <span class="mypage-general-pending__status-label">現在のステータス</span>
        <div class="mypage-general-pending__status-value-row">
          <strong class="mypage-general-pending__status-value">承認待ちです</strong>
          <span class="mypage-general-pending__status-badge">代表者が確認中</span>
        </div>
      </div>

      <div class="mypage-general-pending__status-col mypage-general-pending__status-col--message">
        <p class="mypage-general-pending__status-message">
          チーム代表者が参加申請を確認しています。承認されるとスケジュール確認・連絡・出欠連絡などがご利用いただけます。
        </p>
      </div>
    </div>

    <?php foreach ($pending_teams as $pending_team) : ?>
      <?php
      if (!is_array($pending_team)) {
          continue;
      }
      $team = is_array($pending_team['team'] ?? null) ? $pending_team['team'] : [];
      if ($team === []) {
          continue;
      }
      get_template_part('template-parts/parent/guardian', 'team-card', [
          'team' => $team,
          'heading' => '申請中のチーム',
      ]);
      ?>
    <?php endforeach; ?>
  </article>

  <section class="mypage-general-pending__future" aria-labelledby="mypage-parent-pending-future-title">
    <h2 id="mypage-parent-pending-future-title" class="mypage-general-pending__future-title">
      <span aria-hidden="true">✨</span> 承認後に利用できる機能
    </h2>

    <ul class="mypage-general-pending__feature-grid">
      <li class="mypage-general-pending__feature">
        <span class="mypage-general-pending__feature-icon mypage-general-pending__feature-icon--schedule" aria-hidden="true">
          <?php echo function_exists('aidunite_get_theme_icon_svg') ? aidunite_get_theme_icon_svg('calendar_month', ['width' => '26', 'height' => '26']) : '📅'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </span>
        <div class="mypage-general-pending__feature-body">
          <span class="mypage-general-pending__feature-title">スケジュール確認</span>
          <span class="mypage-general-pending__feature-desc">練習・試合の予定を確認できます。</span>
          <span class="mypage-general-pending__feature-lock">承認後に利用可能</span>
        </div>
      </li>
      <li class="mypage-general-pending__feature">
        <span class="mypage-general-pending__feature-icon mypage-general-pending__feature-icon--chat" aria-hidden="true">
          <?php echo function_exists('aidunite_get_theme_icon_svg') ? aidunite_get_theme_icon_svg('chat', ['width' => '26', 'height' => '26']) : '💬'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </span>
        <div class="mypage-general-pending__feature-body">
          <span class="mypage-general-pending__feature-title">チーム連絡</span>
          <span class="mypage-general-pending__feature-desc">チームからの連絡を確認できます。</span>
          <span class="mypage-general-pending__feature-lock">承認後に利用可能</span>
        </div>
      </li>
      <li class="mypage-general-pending__feature">
        <span class="mypage-general-pending__feature-icon mypage-general-pending__feature-icon--members" aria-hidden="true">
          <?php echo function_exists('aidunite_get_theme_icon_svg') ? aidunite_get_theme_icon_svg('group', ['width' => '26', 'height' => '26']) : '👨‍👩‍👧‍👦'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </span>
        <div class="mypage-general-pending__feature-body">
          <span class="mypage-general-pending__feature-title">お子さまの登録</span>
          <span class="mypage-general-pending__feature-desc">お子さまの情報を登録し、活動と紐付けできます。</span>
          <span class="mypage-general-pending__feature-lock">承認後に利用可能</span>
        </div>
      </li>
    </ul>
  </section>

  <div class="mypage-general-pending__email">
    <span class="mypage-general-pending__email-icon" aria-hidden="true">
      <?php echo function_exists('aidunite_get_theme_icon_svg') ? aidunite_get_theme_icon_svg('mail', ['width' => '28', 'height' => '28']) : '✉'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    </span>
    <div class="mypage-general-pending__email-body">
      <strong class="mypage-general-pending__email-title">承認結果はお知らせでもお知らせします</strong>
      <p class="mypage-general-pending__email-desc">承認が完了すると、アプリ内のお知らせとメールでご連絡します。</p>
    </div>
  </div>
</div>
