<?php
/*
Template Name: チーム作成申請完了ページプレビュー
*/

// 管理画面からのアクセスのみ許可
if (!current_user_can('manage_options')) {
    wp_die('このページにアクセスする権限がありません。');
}

$preview_complete_css = get_stylesheet_directory() . '/assets/css/pages/registration-complete.css';
wp_enqueue_style(
    'registration-complete-style',
    get_stylesheet_directory_uri() . '/assets/css/pages/registration-complete.css',
    ['aidunite-style'],
    is_readable($preview_complete_css) ? (string) filemtime($preview_complete_css) : '1.0.0'
);

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
            document.querySelector('.team-registration-preview-area').innerHTML = '';
            document.querySelector('.team-registration-preview-area').appendChild(targetPage);
            if (typeof startTeamRegistrationAnimations === 'function') {
                startTeamRegistrationAnimations();
            }
        }
    });
    </script>
    <?php
}
?>

<div class="team-registration-preview-container">
  <div class="team-registration-preview-header">
    <h1>チーム作成申請完了ページプレビュー</h1>
    <p>以下のボタンで各ページのプレビューを確認できます。「申請受付完了」は本番UIと同一です。承認完了・エラーは旧プレビュー用レイアウトです。</p>
  </div>

  <div class="team-registration-preview-buttons">
    <button class="preview-btn" data-preview="pending">
      <span class="preview-btn-icon">📋</span>
      申請受付完了ページ
    </button>
    <button class="preview-btn" data-preview="success">
      <span class="preview-btn-icon">🎊</span>
      申請承認完了ページ
    </button>
    <button class="preview-btn" data-preview="error">
      <span class="preview-btn-icon">⚠️</span>
      エラーページ
    </button>
  </div>

  <!-- プレビューエリア -->
  <div class="team-registration-preview-area">
    <div class="preview-placeholder">
      <div class="preview-placeholder-icon">👆</div>
      <h3>プレビューを表示</h3>
      <p>上記のボタンをクリックしてページを確認してください</p>
    </div>
  </div>
</div>

<!-- 申請受付完了ページ（本番と同一UI） -->
<div class="registration-complete-page team-registration-complete-page preview-page" id="preview-pending" style="display: none;">
  <div class="registration-complete-bg" aria-hidden="true">
    <div class="registration-complete-bg-gradient"></div>
    <div class="registration-complete-bg-wave registration-complete-bg-wave--1"></div>
    <div class="registration-complete-bg-wave registration-complete-bg-wave--2"></div>
  </div>
  <div class="registration-complete-container">
    <?php
    get_template_part(
        'team/registration-complete-content',
        null,
        ['mypage_url' => home_url('/mypage/')]
    );
    ?>
  </div>
</div>

<!-- 申請承認完了ページ -->
<div class="team-registration-complete-container preview-page" id="preview-success" style="display: none;">
  <!-- フローティング要素 -->
  <div class="team-registration-floating-elements">
    <div class="team-registration-floating-element">🎉</div>
    <div class="team-registration-floating-element">✨</div>
    <div class="team-registration-floating-element">🌟</div>
    <div class="team-registration-floating-element">🎊</div>
  </div>

  <div class="team-registration-complete-card">
    <!-- 成功アイコン -->
    <div class="team-registration-success-icon success">🎊</div>

    <!-- タイトル -->
    <h1 class="team-registration-complete-title">チーム作成申請が承認されました！</h1>

    <!-- 説明文 -->
    <div class="team-registration-complete-description">
      <p>おめでとうございます！<strong>チーム作成申請が承認されました！</strong></p>
      <p>これでAidUniteで新しいチームを運営していただけます。</p>
    </div>

    <!-- チーム情報セクション -->
    <div class="team-registration-team-section">
      <div class="team-registration-team-icon">🏆</div>
      <div class="team-registration-team-title">チーム情報</div>
      <div class="team-registration-team-info">
        <div class="team-info-item">
          <span class="team-info-label">チーム名：</span>
          <span class="team-info-value">サンプルチーム</span>
        </div>
        <div class="team-info-item">
          <span class="team-info-label">チームID：</span>
          <span class="team-info-value">TEAM-2024-001</span>
        </div>
        <div class="team-info-item">
          <span class="team-info-label">承認日：</span>
          <span class="team-info-value">2024年1月15日</span>
        </div>
      </div>
    </div>

    <!-- 次のステップ -->
    <div class="team-registration-next-steps">
      <div class="team-registration-next-steps-title">次のステップ</div>
      <div class="team-registration-next-steps-list">
        <div class="next-step-item">
          <span class="next-step-icon">👥</span>
          <span class="next-step-text">メンバーを招待する</span>
        </div>
        <div class="next-step-item">
          <span class="next-step-icon">📅</span>
          <span class="next-step-text">練習スケジュールを設定する</span>
        </div>
        <div class="next-step-item">
          <span class="next-step-icon">🏟️</span>
          <span class="next-step-text">試合を企画する</span>
        </div>
      </div>
    </div>

    <!-- アクションボタン -->
    <div class="team-registration-actions">
      <a href="<?php echo home_url('/mypage'); ?>" class="team-registration-btn team-registration-btn-success">
        <span class="team-registration-btn-icon">🏆</span>
        マイページへ
      </a>
      <a href="<?php echo home_url('/mypage'); ?>" class="team-registration-btn team-registration-btn-secondary">
        <span class="team-registration-btn-icon">👤</span>
        マイページへ
      </a>
    </div>
  </div>
</div>

<!-- エラーページ -->
<div class="team-registration-complete-container preview-page" id="preview-error" style="display: none;">
  <div class="team-registration-complete-card">
    <!-- エラーアイコン -->
    <div class="team-registration-error-icon">⚠️</div>

    <!-- タイトル -->
    <h1 class="team-registration-complete-title">申請処理でエラーが発生しました</h1>

    <!-- 説明文 -->
    <div class="team-registration-complete-description">
      <p>申し訳ございませんが、チーム作成申請の処理中にエラーが発生しました。</p>
      <p>しばらく時間をおいてから再度お試しください。</p>
    </div>

    <!-- エラー詳細 -->
    <div class="team-registration-error-details">
      <div class="team-registration-error-title">エラー詳細</div>
      <div class="team-registration-error-message">
        システムエラーが発生しました。お手数ですが、サポートまでお問い合わせください。
      </div>
    </div>

    <!-- アクションボタン -->
    <div class="team-registration-actions">
      <a href="<?php echo home_url('/team-registration'); ?>" class="team-registration-btn team-registration-btn-primary">
        <span class="team-registration-btn-icon">🔄</span>
        再度申請する
      </a>
      <a href="<?php echo home_url('/contact'); ?>" class="team-registration-btn team-registration-btn-secondary">
        <span class="team-registration-btn-icon">📞</span>
        サポートに問い合わせ
      </a>
    </div>
  </div>
</div>

<style>
/* プレビューコンテナ */
.team-registration-preview-container {
  max-width: 1200px;
  margin: 0 auto;
  padding: 40px 20px;
  background: var(--bg-secondary);
  min-height: 100vh;
}

.team-registration-preview-header {
  text-align: center;
  margin-bottom: 40px;
}

.team-registration-preview-header h1 {
  font-size: 2.5rem;
  font-weight: 700;
  color: var(--text-primary);
  margin-bottom: 1rem;
  background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.team-registration-preview-header p {
  font-size: 1.1rem;
  color: var(--text-secondary);
  margin: 0;
}

/* プレビューボタン */
.team-registration-preview-buttons {
  display: flex;
  justify-content: center;
  gap: 20px;
  margin-bottom: 40px;
  flex-wrap: wrap;
}

.preview-btn {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 15px 25px;
  border: none;
  border-radius: 12px;
  font-size: 1rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  background: white;
  color: var(--text-primary);
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
  border: 2px solid transparent;
}

.preview-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 15px rgba(0, 0, 0, 0.15);
}

.preview-btn.active {
  background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
  color: white;
  border-color: var(--primary-color);
}

.preview-btn-icon {
  font-size: 1.2rem;
}

/* プレビューエリア */
.team-registration-preview-area {
  background: white;
  border-radius: 16px;
  padding: 40px;
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
  min-height: 600px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.preview-placeholder {
  text-align: center;
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

/* チーム作成申請完了ページスタイル */
.team-registration-complete-container {
  min-height: 100vh;
  background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 40px 20px;
  position: relative;
  overflow: hidden;
}

.team-registration-complete-container::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="stars" width="20" height="20" patternUnits="userSpaceOnUse"><circle cx="10" cy="10" r="1" fill="white" opacity="0.3"/><circle cx="5" cy="5" r="0.5" fill="white" opacity="0.2"/><circle cx="15" cy="15" r="0.5" fill="white" opacity="0.2"/></pattern></defs><rect width="100" height="100" fill="url(%23stars)"/></svg>');
  animation: twinkle 3s infinite;
}

@keyframes twinkle {
  0%, 100% { opacity: 0.3; }
  50% { opacity: 0.6; }
}

.team-registration-complete-card {
  background: rgba(255, 255, 255, 0.95);
  backdrop-filter: blur(10px);
  border-radius: 24px;
  padding: 3rem 2rem;
  text-align: center;
  max-width: 600px;
  width: 100%;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
  position: relative;
  z-index: 2;
  animation: slideInUp 0.8s ease-out;
  border: 1px solid rgba(255, 255, 255, 0.2);
}

.team-registration-success-icon {
  font-size: 5rem;
  margin-bottom: 1.5rem;
  animation: bounceIn 1s ease-out 0.3s both;
  display: block;
}

.team-registration-success-icon.success {
  color: var(--success-color);
  animation: successPulse 2s infinite;
}

.team-registration-success-icon.pending {
  color: var(--warning-color);
  animation: pendingFloat 3s ease-in-out infinite;
}

.team-registration-error-icon {
  font-size: 5rem;
  margin-bottom: 1.5rem;
  color: var(--danger-color);
  animation: errorPulse 2s infinite;
}

.team-registration-complete-title {
  font-size: 2.5rem;
  font-weight: 700;
  margin-bottom: 1rem;
  background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  animation: fadeInUp 0.8s ease-out 0.5s both;
}

.team-registration-complete-description {
  font-size: 1.1rem;
  line-height: 1.7;
  color: var(--text-secondary);
  margin-bottom: 2rem;
  animation: fadeInUp 0.8s ease-out 0.7s both;
}

.team-registration-complete-description strong {
  color: var(--text-primary);
  font-weight: 600;
}

/* 確認セクション */
.team-registration-confirmation-section {
  background: linear-gradient(135deg, var(--bg-secondary), var(--border-light));
  border-radius: 16px;
  padding: 1.5rem;
  margin: 2rem 0;
  border-left: 4px solid var(--warning-color);
  animation: slideInRight 0.8s ease-out 0.9s both;
}

.team-registration-confirmation-icon {
  font-size: 2rem;
  margin-bottom: 1rem;
  animation: mailShake 2s infinite;
}

.team-registration-confirmation-title {
  font-size: 1.2rem;
  font-weight: 600;
  color: var(--text-primary);
  margin-bottom: 0.5rem;
}

.team-registration-confirmation-text {
  color: var(--text-secondary);
  font-size: 0.95rem;
  line-height: 1.5;
}

/* チーム情報セクション */
.team-registration-team-section {
  background: linear-gradient(135deg, rgba(40, 167, 69, 0.1), rgba(40, 167, 69, 0.15));
  border-radius: 16px;
  padding: 1.5rem;
  margin: 2rem 0;
  border-left: 4px solid var(--success-color);
  animation: slideInRight 0.8s ease-out 0.9s both;
}

.team-registration-team-icon {
  font-size: 2rem;
  margin-bottom: 1rem;
}

.team-registration-team-title {
  font-size: 1.2rem;
  font-weight: 600;
  color: var(--text-primary);
  margin-bottom: 1rem;
}

.team-registration-team-info {
  text-align: left;
}

.team-info-item {
  display: flex;
  justify-content: space-between;
  padding: 0.5rem 0;
  border-bottom: 1px solid var(--border-light);
}

.team-info-item:last-child {
  border-bottom: none;
}

.team-info-label {
  font-weight: 600;
  color: var(--text-primary);
}

.team-info-value {
  color: var(--text-primary);
  font-weight: 500;
}

/* 次のステップ */
.team-registration-next-steps {
  background: linear-gradient(135deg, rgba(23, 162, 184, 0.05), rgba(23, 162, 184, 0.1));
  border-radius: 16px;
  padding: 1.5rem;
  margin: 2rem 0;
  border-left: 4px solid var(--primary-color);
  animation: slideInLeft 0.8s ease-out 1.1s both;
}

.team-registration-next-steps-title {
  font-size: 1.2rem;
  font-weight: 600;
  color: var(--text-primary);
  margin-bottom: 1rem;
}

.team-registration-next-steps-list {
  text-align: left;
}

.next-step-item {
  display: flex;
  align-items: center;
  gap: 0.8rem;
  padding: 0.5rem 0;
}

.next-step-icon {
  font-size: 1.2rem;
}

.next-step-text {
  color: var(--text-primary);
  font-weight: 500;
}

/* エラー詳細 */
.team-registration-error-details {
  background: linear-gradient(135deg, rgba(220, 53, 69, 0.05), rgba(220, 53, 69, 0.1));
  border-radius: 16px;
  padding: 1.5rem;
  margin: 2rem 0;
  border-left: 4px solid var(--danger-color);
  animation: slideInLeft 0.8s ease-out 1.1s both;
}

.team-registration-error-title {
  font-size: 1.2rem;
  font-weight: 600;
  color: var(--text-primary);
  margin-bottom: 0.5rem;
}

.team-registration-error-message {
  color: var(--text-secondary);
  font-size: 0.95rem;
  line-height: 1.5;
}

/* 注意事項 */
.team-registration-notice {
  background: linear-gradient(135deg, rgba(255, 193, 7, 0.1), rgba(255, 193, 7, 0.15));
  border: 1px solid var(--warning-color);
  border-radius: 12px;
  padding: 1rem;
  margin: 1.5rem 0;
  animation: slideInLeft 0.8s ease-out 1.1s both;
}

.team-registration-notice-icon {
  font-size: 1.2rem;
  margin-right: 0.5rem;
}

/* アクションボタン */
.team-registration-actions {
  margin-top: 2.5rem;
  animation: fadeInUp 0.8s ease-out 1.3s both;
}

.team-registration-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 1rem 2rem;
  border: none;
  border-radius: 50px;
  font-size: 1.1rem;
  font-weight: 600;
  text-decoration: none;
  cursor: pointer;
  transition: all 0.3s ease;
  position: relative;
  overflow: hidden;
  margin: 0 0.5rem;
}

.team-registration-btn::before {
  content: '';
  position: absolute;
  top: 0;
  left: -100%;
  width: 100%;
  height: 100%;
  background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
  transition: left 0.5s ease;
}

.team-registration-btn:hover::before {
  left: 100%;
}

.team-registration-btn-primary {
  background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
  color: white;
  box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
}

.team-registration-btn-primary:hover {
  transform: translateY(-3px);
  box-shadow: 0 12px 30px rgba(102, 126, 234, 0.4);
}

.team-registration-btn-secondary {
  background: linear-gradient(135deg, var(--text-muted), var(--text-primary));
  color: white;
  box-shadow: 0 8px 20px rgba(113, 128, 150, 0.3);
}

.team-registration-btn-secondary:hover {
  transform: translateY(-3px);
  box-shadow: 0 12px 30px rgba(113, 128, 150, 0.4);
}

.team-registration-btn-success {
  background: linear-gradient(135deg, var(--success-color), var(--success-color));
  color: white;
  box-shadow: 0 8px 20px rgba(76, 175, 80, 0.3);
}

.team-registration-btn-success:hover {
  transform: translateY(-3px);
  box-shadow: 0 12px 30px rgba(76, 175, 80, 0.4);
}

.team-registration-btn-icon {
  font-size: 1.2rem;
}

/* フローティング要素 */
.team-registration-floating-elements {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  pointer-events: none;
  z-index: 1;
}

.team-registration-floating-element {
  position: absolute;
  font-size: 2rem;
  opacity: 0.3;
  animation: float 6s ease-in-out infinite;
}

.team-registration-floating-element:nth-child(1) {
  top: 10%;
  left: 10%;
  animation-delay: 0s;
}

.team-registration-floating-element:nth-child(2) {
  top: 20%;
  right: 15%;
  animation-delay: 2s;
}

.team-registration-floating-element:nth-child(3) {
  bottom: 20%;
  left: 15%;
  animation-delay: 4s;
}

.team-registration-floating-element:nth-child(4) {
  bottom: 10%;
  right: 10%;
  animation-delay: 1s;
}

/* アニメーション */
@keyframes bounceIn {
  0% {
    opacity: 0;
    transform: scale(0.3) translateY(-50px);
  }
  50% {
    opacity: 1;
    transform: scale(1.05) translateY(0);
  }
  70% {
    transform: scale(0.9) translateY(0);
  }
  100% {
    opacity: 1;
    transform: scale(1) translateY(0);
  }
}

@keyframes successPulse {
  0%, 100% {
    transform: scale(1);
  }
  50% {
    transform: scale(1.1);
  }
}

@keyframes pendingFloat {
  0%, 100% {
    transform: translateY(0px);
  }
  50% {
    transform: translateY(-10px);
  }
}

@keyframes errorPulse {
  0%, 100% {
    transform: scale(1);
  }
  50% {
    transform: scale(1.1);
  }
}

@keyframes mailShake {
  0%, 100% {
    transform: rotate(0deg);
  }
  25% {
    transform: rotate(-5deg);
  }
  75% {
    transform: rotate(5deg);
  }
}

@keyframes float {
  0%, 100% {
    transform: translateY(0px) rotate(0deg);
  }
  50% {
    transform: translateY(-20px) rotate(180deg);
  }
}

@keyframes slideInUp {
  from {
    opacity: 0;
    transform: translateY(30px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes slideInRight {
  from {
    opacity: 0;
    transform: translateX(30px);
  }
  to {
    opacity: 1;
    transform: translateX(0);
  }
}

@keyframes slideInLeft {
  from {
    opacity: 0;
    transform: translateX(-30px);
  }
  to {
    opacity: 1;
    transform: translateX(0);
  }
}

@keyframes fadeInUp {
  from {
    opacity: 0;
    transform: translateY(20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

/* プレビューページの調整 */
.preview-page {
  position: relative;
  min-height: auto;
  padding: 20px;
}

.preview-page .team-registration-complete-container {
  position: relative;
  min-height: auto;
  padding: 20px;
}

.preview-page .team-registration-complete-card {
  max-width: 100%;
  margin: 0;
}

/* レスポンシブ対応 */
@media (max-width: 768px) {
  .team-registration-preview-buttons {
    flex-direction: column;
    align-items: center;
  }

  .preview-btn {
    width: 100%;
    max-width: 300px;
    justify-content: center;
  }

  .team-registration-preview-header h1 {
    font-size: 2rem;
  }

  .team-registration-complete-title {
    font-size: 2rem;
  }

  .team-registration-success-icon {
    font-size: 4rem;
  }

  .team-registration-btn {
    display: block;
    width: 100%;
    margin: 0.5rem 0;
    justify-content: center;
  }
}

@media (max-width: 480px) {
  .team-registration-complete-title {
    font-size: 1.8rem;
  }

  .team-registration-success-icon {
    font-size: 3rem;
  }

  .team-registration-complete-description {
    font-size: 1rem;
  }

  .team-registration-confirmation-section,
  .team-registration-team-section,
  .team-registration-next-steps,
  .team-registration-error-details {
    padding: 1rem;
  }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const previewButtons = document.querySelectorAll('.preview-btn');
  const previewArea = document.querySelector('.team-registration-preview-area');
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

        if (previewType !== 'pending' && typeof startTeamRegistrationAnimations === 'function') {
          startTeamRegistrationAnimations();
        }
      }
    });
  });

  // アニメーション開始関数
  function startTeamRegistrationAnimations() {
    // フローティング要素のアニメーション
    const floatingElements = document.querySelectorAll('.team-registration-floating-element');
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

    // 確認アイコンの特別なアニメーション
    const confirmationIcon = document.querySelector('.team-registration-confirmation-icon');
    if (confirmationIcon) {
      setInterval(() => {
        confirmationIcon.style.transform = 'scale(1.1)';
        setTimeout(() => {
          confirmationIcon.style.transform = 'scale(1)';
        }, 200);
      }, 3000);
    }

    // 成功時の特別なアニメーション
    const successIcon = document.querySelector('.team-registration-success-icon.success');
    if (successIcon) {
      setInterval(() => {
        successIcon.style.transform = 'scale(1.2) rotate(10deg)';
        setTimeout(() => {
          successIcon.style.transform = 'scale(1) rotate(0deg)';
        }, 300);
      }, 2000);
    }

    // エラー時の特別なアニメーション
    const errorIcon = document.querySelector('.team-registration-error-icon');
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
