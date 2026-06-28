<?php
/* Template Name: お問合せ */

if (function_exists('aidunite_web_app_page_prepare_hero_shell_body_class')) {
    aidunite_web_app_page_prepare_hero_shell_body_class();
}

get_header();

$contact_shell_opened = false;
if (function_exists('aidunite_web_app_page_shell_open')) {
    aidunite_web_app_page_shell_open([
        'page_class'    => 'page-contact',
        'title'         => 'お問い合わせ',
        'subtitle'      => 'Ainyに関するご質問・ご相談はこちらから',
        'content_class' => 'ainy-webapp-content--support',
        'back'          => true,
        'back_url'      => home_url('/mypage/'),
    ]);
    $contact_shell_opened = true;
} else {
    echo '<div class="team-dashboard-container page-contact">';
    echo '<div class="dashboard-header"><h1>お問い合わせ</h1><p>Ainyに関するご質問・ご相談はこちらから</p></div>';
}
?>

  <div class="tab-navigation" role="tablist" aria-label="お問い合わせメニュー">
    <button type="button" class="tab-btn active" data-tab="contact-form" role="tab" aria-selected="true" aria-controls="contact-form">
      お問い合わせフォーム
    </button>
    <button type="button" class="tab-btn" data-tab="faq" role="tab" aria-selected="false" aria-controls="faq">
      よくある質問
    </button>
    <button type="button" class="tab-btn" data-tab="contact-info" role="tab" aria-selected="false" aria-controls="contact-info">
      連絡先情報
    </button>
  </div>

  <section class="dashboard-section tab-content active" id="contact-form" role="tabpanel">
    <h2 class="ainy-support-section-title">お問い合わせフォーム</h2>
    <div class="main-content-area">
      <div class="contact-form-container">
        <p class="form-description">以下のフォームからお気軽にお問い合わせください。通常2-3営業日以内にご返信いたします。</p>

        <form class="contact-form" id="contactForm">
          <div class="form-row">
            <div class="form-group">
              <label for="name" class="form-label">お名前 <span class="required">*</span></label>
              <input type="text" id="name" name="name" class="form-control" required>
            </div>

            <div class="form-group">
              <label for="email" class="form-label">メールアドレス <span class="required">*</span></label>
              <input type="email" id="email" name="email" class="form-control" required>
            </div>
          </div>

          <div class="form-group">
            <label for="subject" class="form-label">件名 <span class="required">*</span></label>
            <input type="text" id="subject" name="subject" class="form-control" required>
          </div>

          <div class="form-group">
            <label for="category" class="form-label">お問い合わせ種別 <span class="required">*</span></label>
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
            <label for="message" class="form-label">お問い合わせ内容 <span class="required">*</span></label>
            <textarea id="message" name="message" class="form-control" rows="8"
                      placeholder="詳細をお聞かせください..." required></textarea>
          </div>

          <div class="form-actions">
            <button type="submit" class="btn btn-primary">送信する</button>
          </div>
        </form>
      </div>
    </div>
  </section>

  <section class="dashboard-section tab-content" id="faq" role="tabpanel" hidden>
    <h2 class="ainy-support-section-title">よくある質問</h2>
    <div class="main-content-area">
      <div class="faq-container">
        <div class="faq-item">
          <h3>Q. アカウントの作成方法を教えてください</h3>
          <p>A. <a href="<?php echo esc_url(home_url('/member-registration')); ?>">会員登録ページ</a>から簡単に作成できます。</p>
        </div>
        <div class="faq-item">
          <h3>Q. チーム登録の方法を教えてください</h3>
          <p>A. <a href="<?php echo esc_url(home_url('/team-registration')); ?>">チーム登録ページ</a>から登録できます。</p>
        </div>
        <div class="faq-item">
          <h3>Q. 使い方がわかりません</h3>
          <p>A. <a href="<?php echo esc_url(home_url('/guide')); ?>">使い方ガイド</a>をご確認ください。</p>
        </div>
        <div class="faq-item">
          <h3>Q. 料金について</h3>
          <p>A. <a href="<?php echo esc_url(home_url('/faq')); ?>">FAQ</a>または<a href="<?php echo esc_url(home_url('/regulation')); ?>">レギュレーション</a>をご確認ください。</p>
        </div>
      </div>
    </div>
  </section>

  <section class="dashboard-section tab-content" id="contact-info" role="tabpanel" hidden>
    <h2 class="ainy-support-section-title">その他の連絡方法</h2>
    <div class="main-content-area">
      <div class="contact-info-grid">
        <div class="contact-info-item">
          <div class="contact-icon" aria-hidden="true">📧</div>
          <h3>メール</h3>
          <p>サイト管理者メール（設定 → 一般）</p>
          <p class="contact-note">24時間受付（返信は営業時間内）</p>
        </div>
        <div class="contact-info-item">
          <div class="contact-icon" aria-hidden="true">💬</div>
          <h3>フォーム</h3>
          <p>このページのお問い合わせフォーム</p>
          <p class="contact-note">ログイン後もご利用いただけます</p>
        </div>
        <div class="contact-info-item">
          <div class="contact-icon" aria-hidden="true">📋</div>
          <h3>FAQ</h3>
          <p><a href="<?php echo esc_url(home_url('/faq')); ?>">よくある質問一覧</a></p>
        </div>
      </div>
    </div>
  </section>

<?php
if ($contact_shell_opened && function_exists('aidunite_web_app_page_shell_close')) {
    aidunite_web_app_page_shell_close();
} else {
    echo '</div>';
}

get_footer();
