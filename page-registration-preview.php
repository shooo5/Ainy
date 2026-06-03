<?php
/*
Template Name: 登録完了ページプレビュー
*/

// 管理画面からのアクセスのみ許可
if (!current_user_can('manage_options')) {
    wp_die('このページにアクセスする権限がありません。');
}

// 専用CSSファイルの読み込み
wp_enqueue_style('registration-complete-style', get_stylesheet_directory_uri() . '/assets/css/pages/registration-complete.css');

get_header();

// URLパラメータで直接プレビューを表示
$preview_type = isset($_GET['preview']) ? sanitize_text_field($_GET['preview']) : '';

// 直接プレビューの場合
if ($preview_type && in_array($preview_type, ['pending', 'success', 'error'])) {
    // 直接プレビューページを表示
    $preview_page_id = "preview-{$preview_type}";
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // 指定されたプレビューページを表示
        const targetPage = document.getElementById('<?php echo $preview_page_id; ?>');
        if (targetPage) {
            targetPage.style.display = 'block';
            document.querySelector('.registration-preview-area').innerHTML = '';
            document.querySelector('.registration-preview-area').appendChild(targetPage);
            startAnimations();
        }
    });
    </script>
    <?php
}
?>

<div class="registration-preview-container">
  <div class="registration-preview-header">
    <h1>登録完了ページプレビュー</h1>
    <p>以下のボタンで各ページのプレビューを確認できます</p>
  </div>

  <div class="registration-preview-buttons">
    <button class="preview-btn" data-preview="pending">
      <span class="preview-btn-icon">📧</span>
      仮登録完了ページ
    </button>
    <button class="preview-btn" data-preview="success">
      <span class="preview-btn-icon">🎊</span>
      本登録完了ページ
    </button>
    <button class="preview-btn" data-preview="error">
      <span class="preview-btn-icon">⚠️</span>
      エラーページ
    </button>
  </div>

  <!-- プレビューエリア -->
  <div class="registration-preview-area">
    <div class="preview-placeholder">
      <div class="preview-placeholder-icon">👆</div>
      <h3>プレビューを表示</h3>
      <p>上記のボタンをクリックしてページを確認してください</p>
    </div>
  </div>
</div>

<!-- 仮登録完了ページ -->
<div class="registration-complete-container preview-page" id="preview-pending" style="display: none;">
  <!-- フローティング要素 -->
  <div class="registration-floating-elements">
    <div class="registration-floating-element">🎉</div>
    <div class="registration-floating-element">✨</div>
    <div class="registration-floating-element">🌟</div>
    <div class="registration-floating-element">🎊</div>
  </div>

  <div class="registration-complete-card">
    <!-- 成功アイコン -->
    <div class="registration-success-icon pending">📧</div>

    <!-- タイトル -->
    <h1 class="registration-complete-title">仮登録を受け付けました！</h1>

    <!-- 説明文 -->
    <div class="registration-complete-description">
      <p>ご登録いただき、<strong>ありがとうございます！</strong></p>
      <p>AidUniteの素晴らしい世界への第一歩を踏み出していただきました。</p>
    </div>

    <!-- メール確認セクション -->
    <div class="registration-email-section">
      <div class="registration-email-icon">📬</div>
      <div class="registration-email-title">本登録用メールを送信しました</div>
      <div class="registration-email-text">
        ご入力いただいたメールアドレス宛に本登録用の確認メールを送信しました。<br>
        <strong>メール内のリンクをクリック</strong>して本登録を完了してください。
      </div>
    </div>

    <!-- 注意事項 -->
    <div class="registration-notice">
      <span class="registration-notice-icon">💡</span>
      <strong>メールが届かない場合</strong>は、迷惑メールフォルダもご確認ください。
    </div>

    <!-- アクションボタン -->
    <div class="registration-actions">
      <a href="<?php echo esc_url(home_url('/login/')); ?>" class="registration-btn registration-btn-primary">
        <span class="registration-btn-icon">🚀</span>
        ログイン画面へ
      </a>
      <a href="<?php echo esc_url(home_url('/')); ?>" class="registration-btn registration-btn-secondary">
        <span class="registration-btn-icon">🏠</span>
        ホームへ戻る
      </a>
    </div>
  </div>
</div>

<!-- 本登録完了ページ -->
<div class="registration-complete-container preview-page" id="preview-success" style="display: none;">
  <!-- フローティング要素 -->
  <div class="registration-floating-elements">
    <div class="registration-floating-element">🎉</div>
    <div class="registration-floating-element">✨</div>
    <div class="registration-floating-element">🌟</div>
    <div class="registration-floating-element">🎊</div>
    <div class="registration-floating-element">🎈</div>
    <div class="registration-floating-element">🎆</div>
  </div>

  <div class="registration-complete-card">
    <!-- 成功アイコン -->
    <div class="registration-success-icon success">🎊</div>

    <!-- タイトル -->
    <h1 class="registration-complete-title">本登録が完了しました！</h1>

    <!-- 説明文 -->
    <div class="registration-complete-description">
      <p><strong>おめでとうございます！</strong></p>
      <p>AidUniteの正式メンバーとして登録が完了しました。</p>
      <p>これで全ての機能をご利用いただけます。</p>
    </div>

    <!-- 成功セクション -->
    <div class="registration-email-section">
      <div class="registration-email-icon">✅</div>
      <div class="registration-email-title">登録完了</div>
      <div class="registration-email-text">
        アカウントが正常に有効化されました。<br>
        <strong>ログインしてサービスをご利用ください。</strong>
      </div>
    </div>

    <!-- 次のステップ -->
    <div class="registration-notice">
      <span class="registration-notice-icon">🚀</span>
      <strong>次のステップ</strong>：ログインしてマイページから各種設定を行ってください。
    </div>

    <!-- アクションボタン -->
    <div class="registration-actions">
      <a href="<?php echo esc_url(home_url('/login/')); ?>" class="registration-btn registration-btn-primary">
        <span class="registration-btn-icon">🔑</span>
        ログインする
      </a>
      <a href="<?php echo esc_url(home_url('/')); ?>" class="registration-btn registration-btn-secondary">
        <span class="registration-btn-icon">🏠</span>
        ホームへ戻る
      </a>
    </div>
  </div>
</div>

<!-- エラーページ -->
<div class="registration-complete-container preview-page" id="preview-error" style="display: none;">
  <div class="registration-complete-card">
    <div class="registration-error">
      <div class="registration-error-icon">⚠️</div>
      <h1 class="registration-complete-title">無効なリンクです</h1>
      <div class="registration-complete-description">
        <p>リンクが間違っているか、<strong>有効期限が切れています</strong>。</p>
        <p>新しい登録をお試しください。</p>
      </div>

      <div class="registration-actions">
        <a href="<?php echo esc_url(home_url('/member-register/')); ?>" class="registration-btn registration-btn-primary">
          <span class="registration-btn-icon">🔄</span>
          再登録する
        </a>
        <a href="<?php echo esc_url(home_url('/')); ?>" class="registration-btn registration-btn-secondary">
          <span class="registration-btn-icon">🏠</span>
          ホームへ戻る
        </a>
      </div>
    </div>
  </div>
</div>

<style>
/* プレビューページ専用スタイル */
.registration-preview-container {
  max-width: 1200px;
  margin: 0 auto;
  padding: 40px 20px;
  background: var(--bg-secondary);
  min-height: 100vh;
}

.registration-preview-header {
  text-align: center;
  margin-bottom: 40px;
  background: white;
  padding: 2rem;
  border-radius: 12px;
  box-shadow: var(--shadow-md);
}

.registration-preview-header h1 {
  font-size: 2.5rem;
  color: var(--text-primary);
  margin-bottom: 1rem;
}

.registration-preview-header p {
  font-size: 1.1rem;
  color: var(--text-secondary);
}

.registration-preview-buttons {
  display: flex;
  gap: 1rem;
  justify-content: center;
  margin-bottom: 40px;
  flex-wrap: wrap;
}

.preview-btn {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 1rem 2rem;
  border: none;
  border-radius: 12px;
  font-size: 1.1rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
  color: white;
  box-shadow: var(--shadow-lg);
}

.preview-btn:hover {
  transform: translateY(-2px);
  box-shadow: var(--shadow-xl);
}

.preview-btn.active {
  background: linear-gradient(135deg, var(--success-color), var(--success-color));
  box-shadow: var(--shadow-lg);
}

.preview-btn-icon {
  font-size: 1.2rem;
}

.registration-preview-area {
  background: white;
  border-radius: 12px;
  box-shadow: var(--shadow-md);
  overflow: hidden;
  min-height: 600px;
}

.preview-placeholder {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  height: 600px;
  color: var(--text-secondary);
}

.preview-placeholder-icon {
  font-size: 4rem;
  margin-bottom: 1rem;
  opacity: 0.5;
}

.preview-placeholder h3 {
  font-size: 1.5rem;
  margin-bottom: 0.5rem;
  color: var(--text-primary);
}

.preview-placeholder p {
  font-size: 1rem;
}

/* プレビューページの調整 */
.preview-page {
  position: relative;
  min-height: auto;
  padding: 20px;
}

.preview-page .registration-complete-container {
  position: relative;
  min-height: auto;
  padding: 20px;
}

.preview-page .registration-complete-card {
  max-width: 100%;
  margin: 0;
}

/* レスポンシブ対応 */
@media (max-width: 768px) {
  .registration-preview-buttons {
    flex-direction: column;
    align-items: center;
  }

  .preview-btn {
    width: 100%;
    max-width: 300px;
    justify-content: center;
  }

  .registration-preview-header h1 {
    font-size: 2rem;
  }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const previewButtons = document.querySelectorAll('.preview-btn');
  const previewArea = document.querySelector('.registration-preview-area');
  const previewPages = document.querySelectorAll('.preview-page');
  const placeholder = document.querySelector('.preview-placeholder');

  // プレビューボタンのクリックイベント
  previewButtons.forEach(button => {
    button.addEventListener('click', function() {
      const previewType = this.dataset.preview;

      // ボタンのアクティブ状態を更新
      previewButtons.forEach(btn => btn.classList.remove('active'));
      this.classList.add('active');

      // プレビューページを表示
      previewPages.forEach(page => {
        page.style.display = 'none';
      });

      const targetPage = document.getElementById(`preview-${previewType}`);
      if (targetPage) {
        targetPage.style.display = 'block';
        previewArea.innerHTML = '';
        previewArea.appendChild(targetPage);

        // アニメーションを開始
        startAnimations();
      }
    });
  });

  // アニメーション開始関数
  function startAnimations() {
    // フローティング要素のアニメーション
    const floatingElements = document.querySelectorAll('.registration-floating-element');
    floatingElements.forEach((element, index) => {
      const speed = 0.5 + (index * 0.2);
      let position = 0;

      function animate() {
        position += speed * 0.01;
        element.style.transform = `translateY(${Math.sin(position) * 15}px) rotate(${position * 20}deg)`;
        requestAnimationFrame(animate);
      }
      animate();
    });

    // メールアイコンの特別なアニメーション
    const mailIcon = document.querySelector('.registration-email-icon');
    if (mailIcon) {
      setInterval(() => {
        mailIcon.style.transform = 'scale(1.1)';
        setTimeout(() => {
          mailIcon.style.transform = 'scale(1)';
        }, 200);
      }, 3000);
    }

    // 成功時の特別なアニメーション
    const successIcon = document.querySelector('.registration-success-icon.success');
    if (successIcon) {
      setInterval(() => {
        successIcon.style.transform = 'scale(1.2) rotate(10deg)';
        setTimeout(() => {
          successIcon.style.transform = 'scale(1) rotate(0deg)';
        }, 300);
      }, 2000);
    }

    // エラー時の特別なアニメーション
    const errorIcon = document.querySelector('.registration-error-icon');
    if (errorIcon) {
      setInterval(() => {
        errorIcon.style.transform = 'scale(1.1)';
        setTimeout(() => {
          errorIcon.style.transform = 'scale(1)';
        }, 200);
      }, 3000);
    }
  }
});
</script>

<?php get_footer(); ?>
