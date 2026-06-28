<?php
/*
Template Name: 保護者招待フォーム
*/

require_once get_template_directory() . '/functions/common/auth-middleware.php';
$auth_result = AidUniteAuthMiddleware::require_team_leader(null, true);
if (!$auth_result->is_valid()) {
    return;
}

$current_user_id = (int) $auth_result->user_id;
$result = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_invite'])) {
    $result = aidunite_parent_submit_invite(wp_unslash($_POST), $current_user_id);
    if (!empty($result['redirect'])) {
        wp_safe_redirect($result['redirect']);
        exit;
    }
}

$ctx = aidunite_parent_get_invite_page_context($current_user_id, [
    'query' => $_GET,
    'post_defaults' => $_POST,
]);

$team = is_array($ctx['team'] ?? null) ? $ctx['team'] : [];
$theme_key = (string) ($team['theme_key'] ?? 'boys');
$is_locked = !empty($ctx['locked']);

get_header();
?>
<div class="guardian-flow-page invite-guardian-flow page-invite-guardian" data-team-theme="<?php echo esc_attr($theme_key); ?>">
  <div class="guardian-flow-inner">

    <header class="guardian-flow-hero">
      <div class="guardian-flow-hero__icon" aria-hidden="true">
        <?php echo function_exists('aidunite_render_theme_icon') ? aidunite_render_theme_icon('mail', ['width' => '36', 'height' => '36']) : '✉'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
      </div>
      <h1>保護者を招待する</h1>
      <p>メールアドレスを入力すると、参加用のURLが保護者に送信されます。</p>
    </header>

    <?php if (!empty($ctx['flash']['message'])) : ?>
      <div class="guardian-flow-alert guardian-flow-alert--<?php echo ($ctx['flash']['status'] ?? '') === 'sent' ? 'success' : 'error'; ?>" role="<?php echo ($ctx['flash']['status'] ?? '') === 'sent' ? 'status' : 'alert'; ?>">
        <?php echo esc_html($ctx['flash']['message']); ?>
      </div>
    <?php endif; ?>

    <?php if (!empty($result['errors']) && is_array($result['errors'])) : ?>
      <div class="guardian-flow-alert guardian-flow-alert--error" role="alert">
        <ul>
          <?php foreach ($result['errors'] as $err) : ?>
            <li><?php echo esc_html($err); ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <?php if ($is_locked) : ?>
      <div class="guardian-flow-alert guardian-flow-alert--pending" role="status">
        <strong>この機能はまだ利用できません</strong><br>
        <?php echo esc_html($ctx['lock_message'] ?? '初回の試合が成立すると利用できます。'); ?>
      </div>
      <?php if (!empty($team['team_name'])) : ?>
        <?php
        get_template_part('template-parts/parent/guardian', 'team-card', [
            'team' => $team,
            'heading' => '招待先チーム（プレビュー）',
            'facts_layout' => 'inline',
        ]);
        get_template_part('template-parts/parent/guardian', 'benefits-grid', [
            'variant' => 'invite',
        ]);
        ?>
      <?php endif; ?>
      <a href="<?php echo esc_url(home_url('/mypage')); ?>" class="guardian-flow-cta guardian-flow-cta--secondary">マイページへ戻る</a>
    <?php elseif (!empty($ctx['ok'])) : ?>
      <form method="post" action="" class="guardian-flow-form">
        <section class="guardian-flow-card">
          <label class="guardian-flow-label" for="guardian_email">
            招待する保護者のメールアドレス
            <span class="guardian-flow-badge-required">必須</span>
          </label>
          <div class="guardian-flow-field">
            <input
              type="email"
              name="guardian_email"
              id="guardian_email"
              class="guardian-flow-input"
              required
              aria-required="true"
              autocomplete="email"
              placeholder="taro.yamada@example.com"
              value="<?php echo esc_attr($ctx['form_defaults']['invite_email'] ?? ''); ?>"
            >
            <p class="guardian-flow-hint">招待メールが送信されます。すでにAinyに登録済みの方も招待できます。招待先のメールアドレスでのみ登録できます。</p>
          </div>
        </section>

        <?php
        get_template_part('template-parts/parent/guardian', 'team-card', [
            'team' => $team,
            'heading' => '招待先チーム',
            'facts_layout' => 'inline',
        ]);
        get_template_part('template-parts/parent/guardian', 'benefits-grid', [
            'variant' => 'invite',
        ]);
        ?>

        <button type="submit" name="send_invite" value="1" class="guardian-flow-cta">
          <?php echo function_exists('aidunite_render_theme_icon') ? aidunite_render_theme_icon('send', ['width' => '20', 'height' => '20'], 'aidunite-icon--inline') : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
          招待メールを送信する
        </button>

        <section class="guardian-flow-card guardian-flow-card--notice" aria-labelledby="guardian-invite-notice">
          <h2 class="guardian-flow-card__title" id="guardian-invite-notice">ご注意</h2>
          <ul class="guardian-flow-hint" style="margin:0;padding-left:1.1rem;">
            <li>招待リンクの有効期限は72時間です。</li>
            <li>すでに登録済みの保護者も、招待からチームに追加できます。</li>
          </ul>
        </section>

        <?php if (!empty($ctx['qr']['enabled'])) : ?>
        <?php $qr_signup_url = (string) ($ctx['qr']['signup_url'] ?? ''); ?>
        <section class="guardian-flow-card guardian-flow-card--qr" aria-labelledby="guardian-qr-heading">
          <h2 class="guardian-flow-card__title guardian-flow-card__title--accent" id="guardian-qr-heading">QR・現場用の参加URL</h2>
          <p class="guardian-flow-hint">保護者会や現場で共有するURLです。登録後は代表者の承認が必要です。</p>
          <div class="guardian-qr-panel">
            <div class="guardian-qr-panel__url">
              <div class="guardian-flow-field">
                <label class="guardian-flow-label" for="qr_signup_url">参加URL</label>
                <input type="text" id="qr_signup_url" class="guardian-flow-input" readonly value="<?php echo esc_attr($qr_signup_url); ?>">
              </div>
              <button type="button" class="guardian-flow-cta guardian-flow-cta--secondary guardian-qr-panel__copy" id="copy-qr-url-btn">URLをコピー</button>
              <div class="guardian-flow-stats">
                <span>登録済み保護者: <strong><?php echo (int) ($ctx['parent_counts']['active'] ?? 0); ?></strong>名</span>
                <span>承認待ち: <strong><?php echo (int) ($ctx['parent_counts']['pending'] ?? 0); ?></strong>名</span>
              </div>
            </div>
            <div class="guardian-qr-panel__code" aria-labelledby="guardian-qr-code-label">
              <p class="guardian-qr-panel__code-label" id="guardian-qr-code-label">QRコード</p>
              <div
                id="guardian-qr-canvas-wrap"
                class="guardian-qr-panel__canvas"
                data-qr-url="<?php echo esc_attr($qr_signup_url); ?>"
              ></div>
            </div>
          </div>
        </section>
        <?php endif; ?>
      </form>
    <?php else : ?>
      <div class="guardian-flow-alert guardian-flow-alert--error" role="alert">
        <?php if (!empty($ctx['team_id']) && empty($ctx['can_invite'])) : ?>
          保護者招待はチーム代表者のみ実行できます。
        <?php else : ?>
          チームに所属していません。先にチームを作成または参加してください。
        <?php endif; ?>
      </div>
      <a href="<?php echo esc_url(home_url('/mypage')); ?>" class="guardian-flow-cta guardian-flow-cta--secondary">マイページへ戻る</a>
    <?php endif; ?>
  </div>
</div>

<?php get_footer(); ?>
