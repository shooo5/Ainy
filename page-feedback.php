<?php
/**
 * Template Name: フィードバック
 */

if (function_exists('aidunite_web_app_page_prepare_hero_shell_body_class')) {
    aidunite_web_app_page_prepare_hero_shell_body_class();
}

get_header();

$feedback_shell_opened = false;
if (function_exists('aidunite_web_app_page_shell_open')) {
    aidunite_web_app_page_shell_open([
        'page_class'    => 'page-feedback',
        'title'         => 'フィードバック',
        'subtitle'      => 'Ainyの改善のため、ご意見・ご要望をお聞かせください',
        'content_class' => 'ainy-webapp-content--support',
        'back'          => true,
        'back_url'      => home_url('/mypage/'),
    ]);
    $feedback_shell_opened = true;
} else {
    echo '<div class="team-dashboard-container page-feedback">';
    echo '<div class="dashboard-header"><h1>フィードバック</h1><p>Ainyの改善のため、ご意見・ご要望をお聞かせください</p></div>';
}
?>

  <section class="dashboard-section">
    <h2 class="ainy-support-section-title">フィードバック送信</h2>
    <div class="main-content-area">
      <div class="feedback-form-container">
        <form id="feedbackForm" class="feedback-form">
          <div class="form-group">
            <label for="feedbackType" class="form-label">フィードバックの種類 <span class="required">*</span></label>
            <select id="feedbackType" name="feedbackType" class="form-control" required>
              <option value="">選択してください</option>
              <option value="bug">バグ報告</option>
              <option value="feature">機能要望</option>
              <option value="improvement">改善提案</option>
              <option value="complaint">苦情・不満</option>
              <option value="praise">称賛・感謝</option>
              <option value="other">その他</option>
            </select>
          </div>

          <div class="form-group">
            <label for="priority" class="form-label">優先度</label>
            <select id="priority" name="priority" class="form-control">
              <option value="low">低</option>
              <option value="medium" selected>中</option>
              <option value="high">高</option>
              <option value="urgent">緊急</option>
            </select>
          </div>

          <div class="form-group">
            <label for="title" class="form-label">タイトル <span class="required">*</span></label>
            <input type="text" id="title" name="title" class="form-control" placeholder="簡潔なタイトルを入力してください" required>
          </div>

          <div class="form-group">
            <label for="description" class="form-label">詳細説明 <span class="required">*</span></label>
            <textarea id="description" name="description" class="form-control" rows="6" placeholder="具体的な内容を詳しく記入してください" required></textarea>
          </div>

          <div class="form-group">
            <label class="form-label">使用環境（該当するものを選択）</label>
            <div class="checkbox-group">
              <label class="checkbox-label">
                <input type="checkbox" name="environment[]" value="desktop">
                <span class="checkbox-custom"></span>
                <span class="checkbox-text">デスクトップPC</span>
              </label>
              <label class="checkbox-label">
                <input type="checkbox" name="environment[]" value="mobile">
                <span class="checkbox-custom"></span>
                <span class="checkbox-text">スマートフォン</span>
              </label>
              <label class="checkbox-label">
                <input type="checkbox" name="environment[]" value="tablet">
                <span class="checkbox-custom"></span>
                <span class="checkbox-text">タブレット</span>
              </label>
            </div>
          </div>

          <div class="form-group">
            <label for="browser" class="form-label">使用ブラウザ</label>
            <select id="browser" name="browser" class="form-control">
              <option value="">選択してください</option>
              <option value="chrome">Chrome</option>
              <option value="firefox">Firefox</option>
              <option value="safari">Safari</option>
              <option value="edge">Edge</option>
              <option value="other">その他</option>
            </select>
          </div>

          <div class="form-group">
            <label for="attachment" class="form-label">添付ファイル（任意）</label>
            <input type="file" id="attachment" name="attachment" class="form-control" accept=".jpg,.jpeg,.png,.gif,.pdf,.txt">
            <small class="form-text">画像、PDF、テキストファイル（5MB以下）</small>
          </div>

          <div class="form-group">
            <label for="email" class="form-label">連絡先メールアドレス（任意）</label>
            <input type="email" id="email" name="email" class="form-control" placeholder="返信が必要な場合のみ入力してください">
            <small class="form-text">ご入力いただいた場合、対応状況をお知らせいたします</small>
          </div>

          <div class="form-actions">
            <button type="submit" class="btn btn-primary">フィードバックを送信</button>
            <button type="reset" class="btn btn-secondary">リセット</button>
          </div>
        </form>
      </div>

      <div class="feedback-guide">
        <h3>フィードバック送信のコツ</h3>
        <div class="guide-content">
          <div class="guide-item">
            <h4>具体的に記述する</h4>
            <p>「使いにくい」ではなく「○○の操作で○○が起こる」のように具体的に記述していただけると、より迅速に対応できます。</p>
          </div>
          <div class="guide-item">
            <h4>環境情報を詳しく</h4>
            <p>デバイス、ブラウザ、OSの情報があると、問題の特定が早くなります。</p>
          </div>
          <div class="guide-item">
            <h4>スクリーンショットを添付</h4>
            <p>バグ報告の際は、可能であればスクリーンショットを添付してください。</p>
          </div>
        </div>
      </div>
    </div>
  </section>

<?php
if ($feedback_shell_opened && function_exists('aidunite_web_app_page_shell_close')) {
    aidunite_web_app_page_shell_close();
} else {
    echo '</div>';
}

get_footer();
