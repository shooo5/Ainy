<?php
/**
 * 未所属（general）修正依頼マイページ
 *
 * @package AidUnite
 */

if (!defined('ABSPATH')) {
    exit;
}

$context = isset($args['context']) && is_array($args['context'])
    ? $args['context']
    : (function_exists('aidunite_mypage_general_needs_revision_context')
        ? aidunite_mypage_general_needs_revision_context()
        : []);

$team_name = (string) ($context['team_name'] ?? '');
$revision_message = (string) ($context['revision_message'] ?? '');
$team_registration_url = home_url('/team-registration/');
$icon_base = aidunite_get_theme_icons_uri();
?>

<div class="mypage-general-needs-revision" role="main">
  <header class="mypage-general-needs-revision__intro">
    <h1 class="mypage-general-needs-revision__title">マイページ</h1>
    <p class="mypage-general-needs-revision__subtitle">チーム運営をもっとスマートに。</p>
  </header>

  <div class="mypage-general-needs-revision__status" role="status">
    <div class="mypage-general-needs-revision__status-icon-wrap">
      <span class="mypage-general-needs-revision__status-icon" aria-hidden="true"><?php echo aidunite_render_theme_icon('brightness_alert', ['width' => '24', 'height' => '24'], 'aidunite-icon--warning'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
    </div>

    <div class="mypage-general-needs-revision__status-col mypage-general-needs-revision__status-col--main">
      <span class="mypage-general-needs-revision__status-label">確認事項があります</span>
      <strong class="mypage-general-needs-revision__status-value">チーム申請内容をご確認ください</strong>
      <p class="mypage-general-needs-revision__status-desc">
        一部確認事項があるため、修正後に再申請をお願いします。
      </p>
    </div>

    <?php if ($team_name !== '') : ?>
      <div class="mypage-general-needs-revision__status-col mypage-general-needs-revision__status-col--team">
        <span class="mypage-general-needs-revision__status-team-label">対象チーム</span>
        <strong class="mypage-general-needs-revision__status-team-name"><?php echo esc_html($team_name); ?></strong>
      </div>
    <?php endif; ?>
  </div>

  <?php if ($revision_message !== '') : ?>
    <div class="mypage-general-needs-revision__message" role="note">
      <h2 class="mypage-general-needs-revision__message-title">運営からの確認事項</h2>
      <p class="mypage-general-needs-revision__message-body"><?php echo nl2br(esc_html($revision_message)); ?></p>
    </div>
  <?php endif; ?>

  <?php
  if (function_exists('aidunite_render_registration_application_details')) {
      aidunite_render_registration_application_details($context, false);
  }
  ?>

  <div class="mypage-general-needs-revision__actions">
    <a href="<?php echo esc_url($team_registration_url); ?>" class="mypage-general-needs-revision__cta">
      申請内容を修正する
    </a>
    <p class="mypage-general-needs-revision__cta-note">確認事項を反映のうえ、再度チーム申請を送信してください。</p>
  </div>
</div>
