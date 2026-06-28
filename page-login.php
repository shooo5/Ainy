<?php
/*
Template Name: ログインページ
*/

// エラーメッセージの取得（クエリパラメータから）
$error_message = '';
if (isset($_GET['login_error'])) {
    $error_message = urldecode((string) $_GET['login_error']);
    if (function_exists('aidunite_normalize_login_display_error')) {
        $error_message = aidunite_normalize_login_display_error($error_message);
    }
}

get_header();
?>

<div class="login-container">
  <!-- フローティング要素 -->
  <div class="login-floating-elements">
    <div class="login-floating-element">🔑</div>
    <div class="login-floating-element">✨</div>
    <div class="login-floating-element">🌟</div>
    <div class="login-floating-element">🎯</div>
  </div>

  <div class="login-card">
    <!-- ログインアイコン -->
    <div class="login-icon"><?php echo aidunite_render_theme_icon('lock_open', ['width' => '48', 'height' => '48']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>

    <!-- タイトル -->
    <h1 class="login-title">ログイン</h1>

    <!-- サブタイトル -->
            <p class="login-subtitle">Ainyの世界へようこそ！</p>

    <!-- ログインフォーム -->
    <?php echo aidunite_get_login_form_html($error_message); ?>
  </div>
</div>

<?php get_footer(); ?>
