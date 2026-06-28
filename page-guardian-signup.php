<?php
/**
 * Template Name: 保護者登録ページ
 */

$token = isset($_GET['token']) ? sanitize_text_field(wp_unslash($_GET['token'])) : '';
$team_id = isset($_GET['team_id']) ? (int) $_GET['team_id'] : 0;

$form_repersist = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardian_signup_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['guardian_signup_nonce'])), 'guardian_signup')) {
    $submit_result = aidunite_parent_submit_guardian_signup(wp_unslash($_POST));
    if (!empty($submit_result['ok']) && !empty($submit_result['redirect'])) {
        wp_safe_redirect($submit_result['redirect']);
        exit;
    }
    $form_repersist = is_array($submit_result['form_repersist'] ?? null) ? $submit_result['form_repersist'] : wp_unslash($_POST);
    $token = (string) ($submit_result['token'] ?? $token);
    $team_id = (int) ($submit_result['team_id'] ?? $team_id);
}

$ctx = aidunite_parent_get_signup_context($token, $team_id, [
    'form_repersist' => $form_repersist,
    'viewer_user_id' => get_current_user_id(),
    'query' => $_GET,
]);

get_header();

if (empty($ctx['ok'])) :
?>
<div class="guardian-flow-page guardian-signup-flow" data-team-theme="boys">
  <div class="guardian-flow-inner">
    <section class="guardian-flow-card">
      <h1 class="guardian-flow-card__title">無効な招待リンク</h1>
      <p class="guardian-flow-hint"><?php echo esc_html($ctx['message'] ?? 'この招待リンクは無効または期限切れです。'); ?></p>
      <p class="guardian-flow-hint">チーム代表者に新しい招待リンクを依頼してください。</p>
      <a href="<?php echo esc_url(home_url('/')); ?>" class="guardian-flow-cta">ホームに戻る</a>
    </section>
  </div>
</div>
<?php
    get_footer();
    return;
endif;

$team = $ctx['team'];
$defaults = $ctx['form_defaults'];
$password_required = !empty($ctx['form']['password_required']);
$terms_required = !empty($ctx['form']['terms_required']);
$email_locked = !empty($ctx['token']['email_locked']);
$invite_type = (string) ($ctx['token']['invite_type'] ?? 'email');
$admin_preview = !empty($ctx['admin_preview']);
$submit_enabled = !isset($ctx['form']['submit_enabled']) || !empty($ctx['form']['submit_enabled']);
$submit_errors = isset($submit_result) && is_array($submit_result['errors'] ?? null) ? $submit_result['errors'] : [];
$theme_key = (string) ($team['theme_key'] ?? 'boys');
$team_name = (string) ($team['team_name'] ?? 'チーム');
$terms_url = home_url('/terms/');
$privacy_url = home_url('/privacy/');
?>

<div class="guardian-flow-page guardian-signup-flow" data-team-theme="<?php echo esc_attr($theme_key); ?>">
  <div class="guardian-flow-inner guardian-flow-inner--signup">

    <?php if ($admin_preview) : ?>
    <div class="guardian-flow-alert guardian-flow-alert--info" role="status">
      <strong>管理者プレビュー</strong> — 招待トークンなしで画面を表示しています。登録送信はできません。
      <?php if (!empty($ctx['preview_links'])) : ?>
      <div style="margin-top:0.5rem;display:flex;gap:0.5rem;flex-wrap:wrap;">
        <a href="<?php echo esc_url($ctx['preview_links']['email'] ?? ''); ?>" class="guardian-flow-cta guardian-flow-cta--secondary" style="width:auto;min-height:40px;padding:0.5rem 1rem;">メール招待UI</a>
        <a href="<?php echo esc_url($ctx['preview_links']['qr'] ?? ''); ?>" class="guardian-flow-cta guardian-flow-cta--secondary" style="width:auto;min-height:40px;padding:0.5rem 1rem;">QR招待UI</a>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <header class="guardian-flow-hero">
      <div class="guardian-flow-hero__icon" aria-hidden="true">
        <?php echo function_exists('aidunite_render_theme_icon') ? aidunite_render_theme_icon('person', ['width' => '36', 'height' => '36']) : '👤'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
      </div>
      <h1>保護者登録</h1>
      <?php if ($invite_type === 'qr') : ?>
      <p><?php echo esc_html($team_name); ?> への参加申請と、Ainy 会員登録（保護者アカウント作成）を行います。</p>
      <?php else : ?>
      <p><?php echo esc_html($team_name); ?> からの招待を受け取りました。Ainy 会員登録（保護者アカウント作成）を完了してください。</p>
      <?php endif; ?>
      <p class="guardian-flow-hint">メールアドレスが Ainy へのログインIDになります。</p>
    </header>

    <nav class="guardian-flow-stepper guardian-flow-stepper--compact" aria-label="登録の進捗">
      <div class="guardian-flow-step is-active"><span class="guardian-flow-step__dot">1</span>情報入力</div>
      <div class="guardian-flow-step"><span class="guardian-flow-step__dot">2</span>メール確認</div>
      <?php if ($invite_type === 'qr') : ?>
      <div class="guardian-flow-step"><span class="guardian-flow-step__dot">3</span>チーム参加承認</div>
      <?php else : ?>
      <div class="guardian-flow-step"><span class="guardian-flow-step__dot">3</span>登録完了</div>
      <?php endif; ?>
    </nav>

    <?php if (!empty($submit_errors)) : ?>
    <div class="guardian-flow-alert guardian-flow-alert--error" role="alert">
      <strong>入力内容をご確認ください</strong>
      <ul>
        <?php foreach ($submit_errors as $error) : ?>
        <li><?php echo esc_html($error); ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php endif; ?>

    <div class="guardian-flow-layout">
      <div class="guardian-flow-main">
        <form method="post" id="guardian-signup-form" class="guardian-flow-form" data-password-required="<?php echo $password_required ? '1' : '0'; ?>"<?php echo $submit_enabled ? '' : ' onsubmit="return false;"'; ?>>
          <?php wp_nonce_field('guardian_signup', 'guardian_signup_nonce'); ?>
          <input type="hidden" name="token" value="<?php echo esc_attr($ctx['token_raw'] ?? $token); ?>">
          <input type="hidden" name="team_id" value="<?php echo esc_attr((string) ($ctx['team_id'] ?? $team_id)); ?>">

          <section class="guardian-flow-card">
            <h2 class="guardian-flow-section-title">保護者情報</h2>

            <div class="guardian-flow-row-2">
              <div class="guardian-flow-field">
                <label class="guardian-flow-label" for="parent_name_sei">姓 <span class="guardian-flow-badge-required">必須</span></label>
                <input type="text" id="parent_name_sei" name="parent_name_sei" class="guardian-flow-input" required aria-required="true" value="<?php echo esc_attr($defaults['parent_name_sei'] ?? ''); ?>">
                <span class="field-error" id="error_parent_name_sei" style="display:none;"></span>
              </div>
              <div class="guardian-flow-field">
                <label class="guardian-flow-label" for="parent_name_mei">名 <span class="guardian-flow-badge-required">必須</span></label>
                <input type="text" id="parent_name_mei" name="parent_name_mei" class="guardian-flow-input" required aria-required="true" value="<?php echo esc_attr($defaults['parent_name_mei'] ?? ''); ?>">
                <span class="field-error" id="error_parent_name_mei" style="display:none;"></span>
              </div>
            </div>

            <div class="guardian-flow-row-2">
              <div class="guardian-flow-field">
                <label class="guardian-flow-label" for="parent_kana_sei">セイ <span class="guardian-flow-badge-required">必須</span></label>
                <input type="text" id="parent_kana_sei" name="parent_kana_sei" class="guardian-flow-input" required aria-required="true" value="<?php echo esc_attr($defaults['parent_kana_sei'] ?? ''); ?>">
              </div>
              <div class="guardian-flow-field">
                <label class="guardian-flow-label" for="parent_kana_mei">メイ <span class="guardian-flow-badge-required">必須</span></label>
                <input type="text" id="parent_kana_mei" name="parent_kana_mei" class="guardian-flow-input" required aria-required="true" value="<?php echo esc_attr($defaults['parent_kana_mei'] ?? ''); ?>">
              </div>
            </div>

            <div class="guardian-flow-field">
              <label class="guardian-flow-label" for="parent_email">メールアドレス <span class="guardian-flow-badge-required">必須</span></label>
              <input type="email" id="parent_email" name="parent_email" class="guardian-flow-input" required aria-required="true" value="<?php echo esc_attr($defaults['parent_email'] ?? ''); ?>" <?php echo ($email_locked || (!$password_required && !empty($defaults['parent_email']))) ? 'readonly' : ''; ?>>
              <p class="guardian-flow-hint"><?php echo $email_locked ? 'この招待は上記メールアドレス専用です。' : 'ログインIDとして使用されます。'; ?></p>
              <span class="field-error" id="error_parent_email" style="display:none;"></span>
            </div>

            <div class="guardian-flow-field">
              <label class="guardian-flow-label" for="parent_phone">電話番号 <span class="guardian-flow-badge-optional">任意</span></label>
              <input type="tel" id="parent_phone" name="parent_phone" class="guardian-flow-input" value="<?php echo esc_attr($defaults['parent_phone'] ?? ''); ?>">
            </div>
          </section>

          <?php if ($password_required) : ?>
          <section class="guardian-flow-card" id="guardian-password-section">
            <h2 class="guardian-flow-section-title">Ainy ログイン用パスワード</h2>
            <p class="guardian-flow-hint">上記メールアドレスで Ainy にログインするためのパスワードを設定します。</p>
            <div class="guardian-flow-field">
              <label class="guardian-flow-label" for="password">パスワード <span class="guardian-flow-badge-required">必須</span></label>
              <div class="guardian-flow-input-wrap guardian-flow-input-wrap--password">
                <input type="password" id="password" name="password" class="guardian-flow-input" required aria-required="true" minlength="8" autocomplete="new-password">
                <button type="button" class="guardian-flow-password-toggle" data-target="password" aria-label="パスワードを表示" aria-pressed="false">
                  <span class="guardian-flow-toggle-icon guardian-flow-toggle-icon--show"><?php echo function_exists('aidunite_get_theme_icon_svg') ? aidunite_get_theme_icon_svg('visibility_lock', ['width' => '20', 'height' => '20']) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                  <span class="guardian-flow-toggle-icon guardian-flow-toggle-icon--hide" hidden><?php echo function_exists('aidunite_get_theme_icon_svg') ? aidunite_get_theme_icon_svg('visibility', ['width' => '20', 'height' => '20']) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                </button>
              </div>
              <p class="guardian-flow-hint">8文字以上の英数字で入力してください。</p>
              <span class="field-error" id="error_password" style="display:none;"></span>
            </div>
            <div class="guardian-flow-field">
              <label class="guardian-flow-label" for="password_confirm">パスワード（確認） <span class="guardian-flow-badge-required">必須</span></label>
              <div class="guardian-flow-input-wrap guardian-flow-input-wrap--password">
                <input type="password" id="password_confirm" name="password_confirm" class="guardian-flow-input" required aria-required="true" minlength="8" autocomplete="new-password">
                <button type="button" class="guardian-flow-password-toggle" data-target="password_confirm" aria-label="パスワードを表示" aria-pressed="false">
                  <span class="guardian-flow-toggle-icon guardian-flow-toggle-icon--show"><?php echo function_exists('aidunite_get_theme_icon_svg') ? aidunite_get_theme_icon_svg('visibility_lock', ['width' => '20', 'height' => '20']) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                  <span class="guardian-flow-toggle-icon guardian-flow-toggle-icon--hide" hidden><?php echo function_exists('aidunite_get_theme_icon_svg') ? aidunite_get_theme_icon_svg('visibility', ['width' => '20', 'height' => '20']) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                </button>
              </div>
              <span class="field-error" id="error_password_confirm" style="display:none;"></span>
            </div>
          </section>
          <?php else : ?>
          <div class="guardian-flow-guidance">
            このメールアドレスは既に登録されています。パスワード入力は不要です。登録後、ログインしてご利用ください。
          </div>
          <?php endif; ?>

          <?php if ($terms_required) : ?>
          <section class="guardian-flow-card">
            <h2 class="guardian-flow-section-title">Ainy 会員登録に関する同意</h2>
            <label class="guardian-flow-check">
              <input type="checkbox" name="agree_terms" value="1" required aria-required="true" <?php checked(!empty($defaults['agree_terms'])); ?>>
              <span>
                <a href="<?php echo esc_url($terms_url); ?>" target="_blank" rel="noopener noreferrer">利用規約</a>および
                <a href="<?php echo esc_url($privacy_url); ?>" target="_blank" rel="noopener noreferrer">プライバシーポリシー</a>に同意します（Ainy 会員登録）
              </span>
            </label>
          </section>
          <?php endif; ?>

          <button type="submit" class="guardian-flow-cta" id="guardian-submit-btn"<?php echo $submit_enabled ? '' : ' disabled'; ?>>
            <?php echo $submit_enabled ? '仮登録する（確認メール送信）' : 'プレビュー中（送信不可）'; ?>
          </button>

          <div class="guardian-flow-guidance">
            <?php if ($invite_type === 'qr') : ?>
              送信後、確認メールのリンクで本登録（メール確認）を完了してください。その後、チーム代表者の参加承認をお待ちください。
            <?php else : ?>
              送信後、確認メールのリンクで本登録を完了すると、招待されたチームの保護者として参加できます。
            <?php endif; ?>
          </div>
        </form>
      </div>

      <aside class="guardian-flow-sidebar">
        <?php
        get_template_part('template-parts/parent/guardian', 'team-card', [
            'team' => $team,
            'heading' => '招待されたチーム',
        ]);
        get_template_part('template-parts/parent/guardian', 'benefits-grid', [
            'variant' => 'signup',
        ]);
        ?>
        <section class="guardian-flow-card guardian-flow-card--support">
          <h2 class="guardian-flow-card__title guardian-flow-card__title--accent">お困りの場合</h2>
          <p class="guardian-flow-hint">招待リンクが開けない・メールが届かない場合は、チーム代表者にお問い合わせください。</p>
        </section>
      </aside>
    </div>
  </div>
</div>

<?php get_footer(); ?>
