<?php
/*
Template Name: 新規会員登録フォーム
*/

require_once get_stylesheet_directory() . '/functions/member/register-functions.php';

$form_errors = [];

if (!empty($_POST)) {
    if (
        isset($_POST['tunageru_register_nonce'])
        && wp_verify_nonce($_POST['tunageru_register_nonce'], 'tunageru_register_member')
    ) {
        $registration_data = [
            'last_name' => sanitize_text_field($_POST['last_name'] ?? ''),
            'first_name' => sanitize_text_field($_POST['first_name'] ?? ''),
            'user_email' => sanitize_email($_POST['user_email'] ?? ''),
            'password' => $_POST['password'] ?? '',
            'password_confirm' => $_POST['password_confirm'] ?? '',
            'agree_terms' => isset($_POST['agree_terms']),
        ];

        $registration_result = aidunite_register_member($registration_data);

        if (!empty($registration_result['success'])) {
            wp_redirect($registration_result['redirect_url']);
            exit;
        }

        $form_errors = $registration_result['errors'] ?? [$registration_result['message'] ?? '登録に失敗しました。'];
    } else {
        $form_errors = ['セキュリティチェックに失敗しました。ページを再読み込みして再度お試しください。'];
    }
}

$login_url = home_url('/login/');
$terms_url = home_url('/terms/');
$privacy_url = home_url('/privacy/');

get_header();
?>

<div class="member-register-page">
  <div class="member-register-bg" aria-hidden="true">
    <div class="member-register-bg-gradient"></div>
    <div class="member-register-bg-wave member-register-bg-wave--1"></div>
    <div class="member-register-bg-wave member-register-bg-wave--2"></div>
  </div>

  <div class="member-register-container">
    <div class="member-register-main-col">
      <div class="member-register-signup-body">
        <div class="member-register-card">
            <div class="member-register-card-intro">
              <div class="member-register-card-intro-icon" aria-hidden="true">
                <?php echo aidunite_get_theme_icon_svg('person_add', ['width' => '48', 'height' => '48']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
              </div>
              <h1 class="member-register-title ainy-title-accent">新規会員登録</h1>
              <p class="member-register-subtitle">Ainyでチーム運営をもっとスムーズに。</p>
              <p class="member-register-card-lead">
                <span class="member-register-card-lead-icon" aria-hidden="true"><?php echo aidunite_get_theme_icon_svg('mail', ['width' => '20', 'height' => '20']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                <span>メール認証後、本登録が完了します。</span>
              </p>
            </div>

            <?php if (!empty($form_errors)) : ?>
              <div class="member-register-error-message" role="alert">
                <span class="member-register-error-icon"><?php echo aidunite_get_theme_icon_svg('brightness_alert', ['width' => '22', 'height' => '22']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                <div class="member-register-error-content">
                  <strong>登録に失敗しました</strong>
                  <ul class="member-register-error-list">
                    <?php foreach ($form_errors as $error) : ?>
                      <li><?php echo esc_html($error); ?></li>
                    <?php endforeach; ?>
                  </ul>
                </div>
              </div>
            <?php endif; ?>

            <form method="post" id="tunageru-register-form" class="member-register-form" novalidate>
              <?php wp_nonce_field('tunageru_register_member', 'tunageru_register_nonce'); ?>

              <div class="member-register-name-row">
                <div class="member-register-field">
                  <label for="last_name" class="member-register-label">姓 <span class="member-register-required">必須</span></label>
                  <div class="member-register-input-wrap member-register-input-wrap--plain">
                    <input type="text" id="last_name" name="last_name" class="member-register-input"
                           value="<?php echo esc_attr($_POST['last_name'] ?? ''); ?>"
                           placeholder="例）山田" required autocomplete="family-name">
                  </div>
                </div>
                <div class="member-register-field">
                  <label for="first_name" class="member-register-label">名 <span class="member-register-required">必須</span></label>
                  <div class="member-register-input-wrap member-register-input-wrap--plain">
                    <input type="text" id="first_name" name="first_name" class="member-register-input"
                           value="<?php echo esc_attr($_POST['first_name'] ?? ''); ?>"
                           placeholder="例）太郎" required autocomplete="given-name">
                  </div>
                </div>
              </div>

              <div class="member-register-field">
                <label for="user_email" class="member-register-label">メールアドレス <span class="member-register-required">必須</span></label>
                <div class="member-register-input-wrap">
                  <span class="member-register-input-icon"><?php echo aidunite_get_theme_icon_svg('mail', ['width' => '20', 'height' => '20']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                  <input type="email" id="user_email" name="user_email" class="member-register-input"
                         value="<?php echo esc_attr($_POST['user_email'] ?? ''); ?>"
                         placeholder="example@email.com" required autocomplete="email">
                </div>
                <p class="member-register-hint">ログインIDとして使用されます。</p>
              </div>

              <div class="member-register-field">
                <label for="password" class="member-register-label">パスワード <span class="member-register-required">必須</span></label>
                <div class="member-register-input-wrap member-register-input-wrap--password">
                  <span class="member-register-input-icon"><?php echo aidunite_get_theme_icon_svg('lock', ['width' => '20', 'height' => '20']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                  <input type="password" id="password" name="password" class="member-register-input"
                         placeholder="8文字以上" required autocomplete="new-password">
                  <button type="button" class="member-register-password-toggle" data-target="password"
                          aria-label="パスワードを表示" aria-pressed="false">
                    <span class="member-register-toggle-icon member-register-toggle-icon--show"><?php echo aidunite_get_theme_icon_svg('visibility_lock', ['width' => '20', 'height' => '20']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                    <span class="member-register-toggle-icon member-register-toggle-icon--hide" hidden><?php echo aidunite_get_theme_icon_svg('visibility', ['width' => '20', 'height' => '20']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                  </button>
                </div>
                <div class="member-register-password-strength" aria-live="polite">
                  <div class="member-register-strength-bar">
                    <div class="member-register-strength-fill" id="strength-fill"></div>
                  </div>
                  <p class="member-register-strength-text" id="strength-text">パスワード強度</p>
                </div>
                <p class="member-register-hint">8文字以上で、英大文字・英小文字・数字を含めてください。</p>
              </div>

              <div class="member-register-field">
                <label for="password_confirm" class="member-register-label">パスワード（確認） <span class="member-register-required">必須</span></label>
                <div class="member-register-input-wrap member-register-input-wrap--password">
                  <span class="member-register-input-icon"><?php echo aidunite_get_theme_icon_svg('lock', ['width' => '20', 'height' => '20']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                  <input type="password" id="password_confirm" name="password_confirm" class="member-register-input"
                         placeholder="もう一度入力" required autocomplete="new-password">
                  <button type="button" class="member-register-password-toggle" data-target="password_confirm"
                          aria-label="パスワード（確認）を表示" aria-pressed="false">
                    <span class="member-register-toggle-icon member-register-toggle-icon--show"><?php echo aidunite_get_theme_icon_svg('visibility_lock', ['width' => '20', 'height' => '20']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                    <span class="member-register-toggle-icon member-register-toggle-icon--hide" hidden><?php echo aidunite_get_theme_icon_svg('visibility', ['width' => '20', 'height' => '20']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                  </button>
                </div>
                <p class="member-register-password-match" id="password-match" aria-live="polite"></p>
              </div>

              <div class="member-register-terms">
                <input type="checkbox" id="agree_terms" name="agree_terms" class="member-register-checkbox"
                       <?php checked(isset($_POST['agree_terms'])); ?> required>
                <label for="agree_terms" class="member-register-terms-label">
                  <a href="<?php echo esc_url($terms_url); ?>" class="member-register-link" target="_blank" rel="noopener noreferrer">利用規約</a>および
                  <a href="<?php echo esc_url($privacy_url); ?>" class="member-register-link" target="_blank" rel="noopener noreferrer">プライバシーポリシー</a>に同意します。
                </label>
              </div>

              <button type="submit" class="member-register-submit" id="submit-button">
                <span class="member-register-submit-text">無料で登録する</span>
                <span class="member-register-submit-icon"><?php echo aidunite_get_theme_icon_svg('arrow_forward', ['width' => '20', 'height' => '20']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
              </button>

              <p class="member-register-login-prompt">
                すでにアカウントをお持ちですか？
                <a href="<?php echo esc_url($login_url); ?>" class="member-register-link">ログイン</a>
              </p>
            </form>
        </div>
      </div>

      <section class="member-register-value" aria-labelledby="member-register-value-heading">
        <h2 id="member-register-value-heading" class="member-register-value-heading">Ainyでできること</h2>
        <ul class="member-register-value-grid">
          <li class="member-register-value-card member-register-value-card--purple">
            <span class="member-register-value-card-icon" aria-hidden="true"><?php echo aidunite_get_theme_icon_svg('search', ['width' => '22', 'height' => '22']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
            <div class="member-register-value-card-body">
              <h3 class="member-register-value-card-title">練習試合を探せる</h3>
              <p class="member-register-value-card-desc">自チームに合った対戦相手を見つけられます。</p>
            </div>
          </li>
          <li class="member-register-value-card member-register-value-card--blue">
            <span class="member-register-value-card-icon" aria-hidden="true"><?php echo aidunite_get_theme_icon_svg('calendar_month', ['width' => '22', 'height' => '22']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
            <div class="member-register-value-card-body">
              <h3 class="member-register-value-card-title">スケジュール共有</h3>
              <p class="member-register-value-card-desc">練習や試合予定をチーム全体で共有できます。</p>
            </div>
          </li>
          <li class="member-register-value-card member-register-value-card--green">
            <span class="member-register-value-card-icon" aria-hidden="true"><?php echo aidunite_get_theme_icon_svg('chat', ['width' => '22', 'height' => '22']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
            <div class="member-register-value-card-body">
              <h3 class="member-register-value-card-title">チーム連絡を効率化</h3>
              <p class="member-register-value-card-desc">保護者・選手との連絡をまとめて管理できます。</p>
            </div>
          </li>
        </ul>
      </section>
    </div>
  </div>
</div>

<?php get_footer(); ?>
