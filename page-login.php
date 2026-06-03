<?php
/*
Template Name: ログインページ
*/

// エラーメッセージの取得（クエリパラメータから）
$error_message = '';
if (isset($_GET['login_error'])) {
    $error_message = urldecode($_GET['login_error']);
}

// 専用CSSファイルの読み込み
wp_enqueue_style('login-style', get_stylesheet_directory_uri() . '/assets/css/pages/login.css');

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
    <div class="login-icon"><?php echo aidunite_render_theme_icon('lock', ['width' => '48', 'height' => '48']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>

    <!-- タイトル -->
    <h1 class="login-title">ログイン</h1>

    <!-- サブタイトル -->
            <p class="login-subtitle">Ainyの世界へようこそ！</p>

    <!-- ログインフォーム -->
    <?php echo aidunite_get_login_form_html($error_message); ?>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  // フローティング要素のアニメーション
  const floatingElements = document.querySelectorAll('.login-floating-element');
  floatingElements.forEach((element, index) => {
    const speed = 0.3 + (index * 0.1);
    let position = 0;

    function animate() {
      position += speed * 0.01;
      element.style.transform = `translateY(${Math.sin(position) * 10}px) rotate(${position * 15}deg)`;
      requestAnimationFrame(animate);
    }
    animate();
  });

  // パスワード表示切り替え（新規登録と同じパターン）
  document.querySelectorAll('.login-password-toggle').forEach(function(button) {
    button.addEventListener('click', function() {
      const target = document.getElementById(this.dataset.target);
      const showIcon = this.querySelector('.login-toggle-icon--show');
      const hideIcon = this.querySelector('.login-toggle-icon--hide');
      if (!target || !showIcon || !hideIcon) {
        return;
      }

      const isVisible = target.type === 'text';
      target.type = isVisible ? 'password' : 'text';
      showIcon.hidden = !isVisible;
      hideIcon.hidden = isVisible;
      this.setAttribute('aria-pressed', String(!isVisible));
      this.setAttribute('aria-label', isVisible ? 'パスワードを表示' : 'パスワードを非表示');
    });
  });

  // フォーム送信時のアニメーション（一時的に無効化）
  const loginForm = document.querySelector('.login-form');
  const submitBtn = document.querySelector('.login-submit-btn');

  if (loginForm && submitBtn) {
    console.log('ログインフォームとボタンが見つかりました');

    // 一時的にイベントリスナーを無効化してテスト
    /*
    loginForm.addEventListener('submit', function(e) {
      console.log('=== フォーム送信処理開始 ===');

      // フォームデータの確認
      const formData = new FormData(loginForm);
      console.log('フォームデータ:');
      for (let [key, value] of formData.entries()) {
        console.log(`${key}: ${value}`);
      }

      // 必須フィールドのチェック
      const emailInput = document.getElementById('user_email');
      const passwordInput = document.getElementById('user_pass');

      console.log('必須フィールドチェック:');
      console.log(`メールアドレス: ${emailInput.value} (required: ${emailInput.required})`);
      console.log(`パスワード: ${passwordInput.value} (required: ${passwordInput.required})`);

      // 一時的にバリデーションを無効化してテスト
      console.log('✅ バリデーション無効化: フォーム送信実行');

      // 送信ボタンを無効化
      submitBtn.disabled = true;
      submitBtn.innerHTML = 'ログイン中...';

      // ログインアイコンの特別なアニメーション
      const loginIcon = document.querySelector('.login-icon');
      if (loginIcon) {
        loginIcon.style.animation = 'loginBounce 0.5s ease infinite';
      }

      console.log('送信ボタン無効化完了');
      console.log('=== フォーム送信処理終了 ===');

      // フォーム送信を許可（バリデーションを無効化）
      // e.preventDefault(); // コメントアウト
    });
    */
  } else {
    console.log('❌ ログインフォームまたはボタンが見つかりません');
    console.log('loginForm:', loginForm);
    console.log('submitBtn:', submitBtn);
  }

  // 入力フィールドのフォーカス効果
  const inputs = document.querySelectorAll('.login-form-input');
  inputs.forEach(input => {
    input.addEventListener('focus', function() {
      const wrap = this.closest('.login-input-wrap');
      if (wrap) {
        wrap.style.transform = 'scale(1.01)';
      }
    });

    input.addEventListener('blur', function() {
      const wrap = this.closest('.login-input-wrap');
      if (wrap) {
        wrap.style.transform = 'scale(1)';
      }
    });
  });

  // エラーメッセージがある場合の特別なアニメーション
  const errorMessage = document.querySelector('.login-error-message');
  if (errorMessage) {
    errorMessage.addEventListener('animationend', function() {
      this.style.animation = 'none';
      setTimeout(() => {
        this.style.animation = 'shake 0.5s ease';
      }, 100);
    });
  }
});
</script>

<style>

</style>

<?php get_footer(); ?>
