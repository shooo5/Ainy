<?php
/* Template Name: お問合せ */

get_header();
?>

<div class="team-dashboard-container page-contact">
  <!-- ダッシュボードヘッダー -->
  <div class="dashboard-header">
    <h1>お問い合わせ</h1>
            <p>Ainyに関するご質問・ご相談はこちらから</p>
  </div>

  <!-- タブナビゲーション -->
  <div class="tab-navigation">
    <button class="tab-btn active" data-tab="contact-form">
      📝 お問い合わせフォーム
    </button>
    <button class="tab-btn" data-tab="faq">
      ❓ よくある質問
    </button>
    <button class="tab-btn" data-tab="contact-info">
      📞 連絡先情報
    </button>
  </div>

  <!-- お問い合わせフォームタブ -->
  <section class="dashboard-section tab-content active" id="contact-form">
    <h2>📝 お問い合わせフォーム</h2>
    <div class="main-content-area">
      <div class="contact-form-container">
        <p class="form-description">以下のフォームからお気軽にお問い合わせください。通常2-3営業日以内にご返信いたします。</p>

        <form class="contact-form" id="contactForm">
          <div class="form-row">
            <div class="form-group">
              <label for="name" class="form-label">お名前 *</label>
              <input type="text" id="name" name="name" class="form-control" required>
            </div>

            <div class="form-group">
              <label for="email" class="form-label">メールアドレス *</label>
              <input type="email" id="email" name="email" class="form-control" required>
            </div>
          </div>

          <div class="form-group">
            <label for="subject" class="form-label">件名 *</label>
            <input type="text" id="subject" name="subject" class="form-control" required>
          </div>

          <div class="form-group">
            <label for="category" class="form-label">お問い合わせ種別 *</label>
            <select id="category" name="category" class="form-control" required>
              <option value="">選択してください</option>
              <option value="general">一般的な質問</option>
              <option value="technical">技術的な問題</option>
              <option value="billing">料金・支払いについて</option>
              <option value="feature">機能のご要望</option>
              <option value="bug">バグ報告</option>
              <option value="other">その他</option>
            </select>
          </div>

          <div class="form-group">
            <label for="message" class="form-label">お問い合わせ内容 *</label>
            <textarea id="message" name="message" class="form-control" rows="8"
                      placeholder="詳細をお聞かせください..." required></textarea>
          </div>

          <div class="form-group">
            <button type="submit" class="aidunite-btn aidunite-btn-primary">
              📤 送信する
            </button>
          </div>
        </form>
      </div>
    </div>
  </section>

  <!-- よくある質問タブ -->
  <section class="dashboard-section tab-content" id="faq">
    <h2>❓ よくある質問</h2>
    <div class="main-content-area">
      <div class="faq-container">
        <div class="faq-item">
          <h3>Q. アカウントの作成方法を教えてください</h3>
          <p>A. <a href="<?php echo home_url('/member-registration'); ?>">会員登録ページ</a>から簡単に作成できます。無料でご利用いただけます。</p>
        </div>
        <div class="faq-item">
          <h3>Q. チーム登録の方法を教えてください</h3>
          <p>A. <a href="<?php echo home_url('/team-registration'); ?>">チーム登録ページ</a>から登録できます。チーム代表者・コーチの方が登録してください。</p>
        </div>
        <div class="faq-item">
          <h3>Q. 使い方がわかりません</h3>
          <p>A. <a href="<?php echo home_url('/guide'); ?>">使い方ガイド</a>をご確認ください。ステップバイステップで説明しています。</p>
        </div>
        <div class="faq-item">
          <h3>Q. 料金体系について教えてください</h3>
          <p>A. 基本的な機能は無料でご利用いただけます。詳細は<a href="<?php echo home_url('/regulation'); ?>">レギュレーションページ</a>をご確認ください。</p>
        </div>
        <div class="faq-item">
          <h3>Q. セキュリティについて</h3>
          <p>A. お客様の個人情報は適切に管理し、第三者に提供することはありません。詳細はプライバシーポリシーをご確認ください。</p>
        </div>
      </div>
    </div>
  </section>

  <!-- 連絡先情報タブ -->
  <section class="dashboard-section tab-content" id="contact-info">
    <h2>📞 その他の連絡方法</h2>
    <div class="main-content-area">
      <div class="contact-info-grid">
        <div class="contact-info-item">
          <div class="contact-icon">📧</div>
          <h3>メール</h3>
          <p>support@ainy.com</p>
          <p class="contact-note">24時間受付（返信は営業時間内）</p>
        </div>
        <div class="contact-info-item">
          <div class="contact-icon">📱</div>
          <h3>電話</h3>
          <p>03-1234-5678</p>
          <p class="contact-note">平日 9:00-18:00</p>
        </div>
        <div class="contact-info-item">
          <div class="contact-icon">💬</div>
          <h3>チャット</h3>
          <p>オンラインサポート</p>
          <p class="contact-note">ログイン後利用可能</p>
        </div>
      </div>
    </div>
  </section>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  // タブ切り替え機能
  const tabBtns = document.querySelectorAll('.tab-btn');
  const tabContents = document.querySelectorAll('.tab-content');

  tabBtns.forEach(btn => {
    btn.addEventListener('click', function() {
      const targetTab = this.getAttribute('data-tab');

      // タブボタンのアクティブ状態を切り替え
      tabBtns.forEach(b => b.classList.remove('active'));
      this.classList.add('active');

      // タブコンテンツの表示を切り替え
      tabContents.forEach(content => {
        content.classList.remove('active');
        if (content.id === targetTab) {
          content.classList.add('active');
        }
      });
    });
  });

  // お問い合わせフォーム送信処理
  document.getElementById('contactForm').addEventListener('submit', function(e) {
    e.preventDefault();

    // フォームデータの取得
    const formData = new FormData(this);

    // 送信ボタンを無効化
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;
    submitBtn.disabled = true;
    submitBtn.textContent = '送信中...';

    // 実際の送信処理（ここではアラートで代用）
    setTimeout(() => {
      if (typeof showToastNotification !== 'undefined') {
        showToastNotification('お問い合わせありがとうございます。内容を確認の上、2-3営業日以内にご返信いたします。', 'success');
      } else {
        alert('お問い合わせありがとうございます。\n内容を確認の上、2-3営業日以内にご返信いたします。');
      }

      // フォームをリセット
      this.reset();

      // ボタンを元に戻す
      submitBtn.disabled = false;
      submitBtn.textContent = originalText;
    }, 1000);
  });
});
</script>

<style>
.contact-form-container {
  max-width: 800px;
  margin: 0 auto;
}

.form-description {
  text-align: center;
  margin-bottom: 2rem;
  color: var(--text-secondary);
}

.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
}

.form-group {
  margin-bottom: 1.5rem;
}

.form-label {
  display: block;
  margin-bottom: 0.5rem;
  font-weight: 600;
  color: var(--text-primary);
}

.form-control {
  width: 100%;
  padding: 0.75rem;
  border: 1px solid var(--border-color);
  border-radius: 4px;
  font-size: 1rem;
}

.form-control:focus {
  outline: none;
  border-color: var(--primary-color);
  box-shadow: 0 0 0 2px rgba(0, 124, 186, 0.2);
}

.faq-container {
  max-width: 800px;
  margin: 0 auto;
}

.faq-item {
  background: #f8f9fa;
  padding: 1.5rem;
  margin-bottom: 1rem;
  border-radius: 8px;
  border-left: 4px solid var(--primary-color);
}

.faq-item h3 {
  margin: 0 0 0.5rem 0;
  color: var(--text-primary);
  font-size: 1.1rem;
}

.faq-item p {
  margin: 0;
  color: var(--text-secondary);
  line-height: 1.6;
}

.contact-info-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 2rem;
  max-width: 900px;
  margin: 0 auto;
}

.contact-info-item {
  text-align: center;
  padding: 2rem;
  background: #f8f9fa;
  border-radius: 8px;
  border: 1px solid var(--border-light);
}

.contact-icon {
  font-size: 3rem;
  margin-bottom: 1rem;
}

.contact-info-item h3 {
  margin: 0 0 0.5rem 0;
  color: var(--text-primary);
}

.contact-info-item p {
  margin: 0.25rem 0;
  color: var(--text-secondary);
}

.contact-note {
  font-size: 0.9rem;
  color: var(--text-muted);
  margin-top: 0.5rem;
}

@media (max-width: 768px) {
  .form-row {
    grid-template-columns: 1fr;
  }

  .contact-info-grid {
    grid-template-columns: 1fr;
  }
}
</style>

<style>

</style>

<?php get_footer(); ?>
