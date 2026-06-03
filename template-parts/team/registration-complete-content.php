<?php
/**
 * チーム申請受付完了（チーム運営スタート体験）
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

$team_name       = (string) ($context['team_name'] ?? 'あなたのチーム');
$complete_teams  = !empty($context['teams']) && is_array($context['teams']) ? $context['teams'] : [];
$is_dual_complete = count($complete_teams) > 1;
if ($is_dual_complete) {
    $names = array_map(static function ($row) {
        return is_array($row) ? (string) ($row['team_name'] ?? '') : '';
    }, $complete_teams);
    $names = array_values(array_filter($names));
    $team_name = $names !== [] ? implode('・', $names) : $team_name;
}
$icon_base = aidunite_get_theme_icons_uri();

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
    <p class="team-reg-complete-pill">
      <span class="team-reg-complete-pill__icon" aria-hidden="true"><?php echo aidunite_registration_complete_asset_icon('check_circle'); ?></span>
      申請を受け付けました。
    </p>

    <div class="team-reg-complete-hero__check registration-complete-icon-wrap--enter">
      <span class="registration-complete-icon-glow" aria-hidden="true"></span>
      <div class="registration-complete-icon registration-complete-icon--check team-reg-complete-hero__check-icon" aria-hidden="true">
        <?php echo aidunite_registration_complete_asset_icon('check_circle'); ?>
      </div>
    </div>

    <h1 class="team-reg-complete-title">
      <span class="team-reg-complete-title__team"><?php echo esc_html($team_name); ?></span>
      <span class="team-reg-complete-title__suffix"><?php echo $is_dual_complete ? 'のチーム申請（2件）を受け付けました。' : 'のチーム申請を受け付けました。'; ?></span>
    </h1>

    <div class="team-reg-complete-lead">
      <p>運営チームが確認後、承認を行います。</p>
      <p>承認後は、チーム運営機能をご利用いただけます。</p>
    </div>
  </header>

  <div class="team-reg-complete-status" role="status">
    <span class="team-reg-complete-status__icon" aria-hidden="true">
      <img src="<?php echo esc_url($icon_base . 'hourglass_empty.svg'); ?>" alt="" width="28" height="28" decoding="async">
    </span>
    <div class="team-reg-complete-status__main">
      <span class="team-reg-complete-status__label">現在のステータス</span>
      <strong class="team-reg-complete-status__value">運営チームが確認中です。</strong>
    </div>
    <p class="team-reg-complete-status__eta">
      通常 <strong>1〜2営業日以内</strong> に<br class="team-reg-complete-status__eta-br">ご連絡いたします。
    </p>
  </div>

  <section class="team-reg-complete-future" aria-labelledby="team-reg-complete-future-title">
    <h2 id="team-reg-complete-future-title" class="team-reg-complete-future__title">
      <span class="team-reg-complete-future__spark" aria-hidden="true">✨</span>
      承認後に利用できること
    </h2>

    <ul class="team-reg-complete-feature-grid">
      <li class="team-reg-complete-feature">
        <span class="team-reg-complete-feature__icon team-reg-complete-feature__icon--match" aria-hidden="true">
          <?php echo aidunite_get_chat_icon_svg('basketball', ['width' => '28', 'height' => '28']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </span>
        <div class="team-reg-complete-feature__body">
          <span class="team-reg-complete-feature__title">練習試合の募集</span>
          <span class="team-reg-complete-feature__desc">条件に合う相手チームを見つけて、練習試合を簡単に募集できます。</span>
        </div>
        <span class="team-reg-complete-feature__chev" aria-hidden="true"></span>
      </li>
      <li class="team-reg-complete-feature">
        <span class="team-reg-complete-feature__icon team-reg-complete-feature__icon--schedule" aria-hidden="true">
          <?php echo aidunite_get_chat_icon_svg('group', ['width' => '28', 'height' => '28']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </span>
        <div class="team-reg-complete-feature__body">
          <span class="team-reg-complete-feature__title">スケジュール登録</span>
          <span class="team-reg-complete-feature__desc">練習や試合の予定をまとめて管理。メンバーとも共有できます。</span>
        </div>
        <span class="team-reg-complete-feature__chev" aria-hidden="true"></span>
      </li>
      <li class="team-reg-complete-feature">
        <span class="team-reg-complete-feature__icon team-reg-complete-feature__icon--chat" aria-hidden="true">
          <?php echo aidunite_get_chat_icon_svg('chat', ['width' => '28', 'height' => '28']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </span>
        <div class="team-reg-complete-feature__body">
          <span class="team-reg-complete-feature__title">チャット</span>
          <span class="team-reg-complete-feature__desc">チャットでスムーズに連絡・調整ができます。</span>
        </div>
        <span class="team-reg-complete-feature__chev" aria-hidden="true"></span>
      </li>
    </ul>
  </section>

  <div class="team-reg-complete-actions">
    <a href="<?php echo esc_url($mypage_url); ?>" class="team-reg-complete-cta">
      <span class="team-reg-complete-cta__icon" aria-hidden="true"><?php echo aidunite_get_chat_icon_svg('home', ['width' => '22', 'height' => '22']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
      マイページへ
    </a>
  </div>

  <div class="team-reg-complete-email">
    <span class="team-reg-complete-email__icon" aria-hidden="true">
      <?php echo aidunite_get_theme_icon_svg('mail', ['width' => '28', 'height' => '28']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    </span>
    <div class="team-reg-complete-email__body">
      <strong class="team-reg-complete-email__title">承認結果はメールでもお知らせします。</strong>
      <p class="team-reg-complete-email__desc">登録いただいたメールアドレスへご連絡いたしますので、しばらくお待ちください。</p>
    </div>
  </div>

  <?php
  if (function_exists('aidunite_render_registration_application_details')) {
      if ($is_dual_complete) {
          foreach ($complete_teams as $team_ctx) {
              if (!is_array($team_ctx)) {
                  continue;
              }
              $gender_label = (string) ($team_ctx['team_gender_label'] ?? '');
              $accordion_title = (string) ($team_ctx['team_name'] ?? '申請内容の確認');
              if ($gender_label !== '') {
                  $accordion_title .= '（' . $gender_label . '）';
              }
              aidunite_render_registration_application_details(
                  array_merge($team_ctx, ['accordion_title' => $accordion_title]),
                  false
              );
          }
      } else {
          aidunite_render_registration_application_details($context, false);
      }
  }
  ?>
</article>
