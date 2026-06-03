<?php
/**
 * 一般ユーザー（general）承認待ちマイページ
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

$submitted_at = (string) ($context['submitted_at'] ?? '');
$is_dual_pending = count($pending_teams) > 1;
$icon_base = aidunite_get_theme_icons_uri();
?>

<div class="mypage-general-pending" role="main">
  <article class="mypage-general-pending__card">
    <header class="mypage-general-pending__intro">
      <h1 class="mypage-general-pending__title">マイページ</h1>
      <p class="mypage-general-pending__subtitle">チーム運営をもっとスマートに。</p>
    </header>

    <div class="mypage-general-pending__status" role="status">
      <div class="mypage-general-pending__status-icon-wrap">
        <span class="mypage-general-pending__status-icon" aria-hidden="true">
          <img src="<?php echo esc_url($icon_base . 'hourglass_empty.svg'); ?>" alt="" width="28" height="28" decoding="async">
        </span>
      </div>

      <div class="mypage-general-pending__status-col mypage-general-pending__status-col--state">
        <span class="mypage-general-pending__status-label">現在のステータス</span>
        <div class="mypage-general-pending__status-value-row">
          <strong class="mypage-general-pending__status-value">承認待ちです</strong>
          <span class="mypage-general-pending__status-badge">運営チームが確認中</span>
        </div>
      </div>

      <div class="mypage-general-pending__status-col mypage-general-pending__status-col--message">
        <p class="mypage-general-pending__status-message">
          <?php if ($is_dual_pending) : ?>
            男子・女子のチーム申請を受け付けました。それぞれ承認されます。
          <?php else : ?>
            チーム申請を受け付けました。
          <?php endif; ?>
        </p>
        <p class="mypage-general-pending__status-message">
          通常 <strong>1〜2営業日以内</strong> にご連絡いたします。
        </p>
      </div>

      <div class="mypage-general-pending__status-col mypage-general-pending__status-col--date">
        <span class="mypage-general-pending__status-date-label">申請日時</span>
        <strong class="mypage-general-pending__status-date-value">
          <?php echo $submitted_at !== '' ? esc_html($submitted_at) : '—'; ?>
        </strong>
      </div>
    </div>

    <?php
    if ($pending_teams !== [] && function_exists('aidunite_render_registration_application_details')) {
        foreach ($pending_teams as $team_ctx) {
            if (!is_array($team_ctx)) {
                continue;
            }
            $gender_label = (string) ($team_ctx['team_gender_label'] ?? '');
            $accordion_title = (string) ($team_ctx['team_name'] ?? '申請内容の確認');
            if ($gender_label !== '' && $is_dual_pending) {
                $accordion_title = $accordion_title . '（' . $gender_label . '）';
            }
            ?>
            <div class="mypage-general-pending__team-block">
              <?php if ($is_dual_pending) : ?>
                <h2 class="mypage-general-pending__team-block-title"><?php echo esc_html($accordion_title); ?></h2>
              <?php endif; ?>
              <?php
              aidunite_render_registration_application_details(
                  array_merge($team_ctx, ['accordion_title' => $accordion_title]),
                  false
              );
              ?>
            </div>
            <?php
        }
    } elseif (function_exists('aidunite_render_registration_application_details')) {
        aidunite_render_registration_application_details($context, false);
    }
    ?>
  </article>

  <section class="mypage-general-pending__future" aria-labelledby="mypage-general-pending-future-title">
    <h2 id="mypage-general-pending-future-title" class="mypage-general-pending__future-title">
      <span aria-hidden="true">✨</span> 承認後に利用できる機能
    </h2>

    <ul class="mypage-general-pending__feature-grid">
      <li class="mypage-general-pending__feature">
        <span class="mypage-general-pending__feature-icon mypage-general-pending__feature-icon--match" aria-hidden="true">
          <?php echo aidunite_get_chat_icon_svg('basketball', ['width' => '26', 'height' => '26']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </span>
        <div class="mypage-general-pending__feature-body">
          <span class="mypage-general-pending__feature-title">練習試合募集</span>
          <span class="mypage-general-pending__feature-desc">条件に合う相手チームを見つけて、練習試合を簡単に募集できます。</span>
          <span class="mypage-general-pending__feature-lock">承認後に利用可能</span>
        </div>
      </li>
      <li class="mypage-general-pending__feature">
        <span class="mypage-general-pending__feature-icon mypage-general-pending__feature-icon--schedule" aria-hidden="true">
          <?php echo aidunite_get_chat_icon_svg('calendar_month', ['width' => '26', 'height' => '26']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </span>
        <div class="mypage-general-pending__feature-body">
          <span class="mypage-general-pending__feature-title">スケジュール共有</span>
          <span class="mypage-general-pending__feature-desc">練習や試合の予定をまとめて管理。メンバーとも共有できます。</span>
          <span class="mypage-general-pending__feature-lock">承認後に利用可能</span>
        </div>
      </li>
      <li class="mypage-general-pending__feature">
        <span class="mypage-general-pending__feature-icon mypage-general-pending__feature-icon--chat" aria-hidden="true">
          <?php echo aidunite_get_chat_icon_svg('chat', ['width' => '26', 'height' => '26']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </span>
        <div class="mypage-general-pending__feature-body">
          <span class="mypage-general-pending__feature-title">チーム連絡</span>
          <span class="mypage-general-pending__feature-desc">チャットでスムーズに連絡・調整ができます。</span>
          <span class="mypage-general-pending__feature-lock">承認後に利用可能</span>
        </div>
      </li>
      <li class="mypage-general-pending__feature">
        <span class="mypage-general-pending__feature-icon mypage-general-pending__feature-icon--members" aria-hidden="true">
          <?php echo aidunite_get_chat_icon_svg('group', ['width' => '26', 'height' => '26']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </span>
        <div class="mypage-general-pending__feature-body">
          <span class="mypage-general-pending__feature-title">メンバー管理</span>
          <span class="mypage-general-pending__feature-desc">メンバー招待や役割設定など、チーム運営を簡単に行えます。</span>
          <span class="mypage-general-pending__feature-lock">承認後に利用可能</span>
        </div>
      </li>
    </ul>
  </section>

  <div class="mypage-general-pending__email">
    <span class="mypage-general-pending__email-icon" aria-hidden="true">
      <?php echo aidunite_get_theme_icon_svg('mail', ['width' => '28', 'height' => '28']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    </span>
    <div class="mypage-general-pending__email-body">
      <strong class="mypage-general-pending__email-title">承認結果はメールでもお知らせします</strong>
      <p class="mypage-general-pending__email-desc">登録いただいたメールアドレスへご連絡いたしますので、しばらくお待ちください。</p>
    </div>
  </div>
</div>
