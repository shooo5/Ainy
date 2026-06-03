<?php
/**
 * Template Name: フィードバック
 */

get_header();
?>

<div class="team-dashboard-container page-feedback">
  <!-- ダッシュボードヘッダー -->
  <div class="dashboard-header">
    <h1>フィードバック</h1>
    <p>TUNAGERUの改善のため、ご意見・ご要望をお聞かせください</p>
  </div>

  <!-- フィードバックフォームセクション -->
  <section class="dashboard-section">
    <h2>📝 フィードバック送信</h2>
    <div class="main-content-area">
      <div class="feedback-form-container">
        <form id="feedbackForm" class="feedback-form">
          <!-- フィードバックタイプ -->
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

          <!-- 優先度 -->
          <div class="form-group">
            <label for="priority" class="form-label">優先度</label>
            <select id="priority" name="priority" class="form-control">
              <option value="low">低</option>
              <option value="medium" selected>中</option>
              <option value="high">高</option>
              <option value="urgent">緊急</option>
            </select>
          </div>

          <!-- タイトル -->
          <div class="form-group">
            <label for="title" class="form-label">タイトル <span class="required">*</span></label>
            <input type="text" id="title" name="title" class="form-control" placeholder="簡潔なタイトルを入力してください" required>
          </div>

          <!-- 詳細説明 -->
          <div class="form-group">
            <label for="description" class="form-label">詳細説明 <span class="required">*</span></label>
            <textarea id="description" name="description" class="form-control" rows="6" placeholder="具体的な内容を詳しく記入してください" required></textarea>
          </div>

          <!-- 環境情報 -->
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

          <!-- ブラウザ -->
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

          <!-- 添付ファイル -->
          <div class="form-group">
            <label for="attachment" class="form-label">添付ファイル（任意）</label>
            <input type="file" id="attachment" name="attachment" class="form-control" accept=".jpg,.jpeg,.png,.gif,.pdf,.txt">
            <small class="form-text">画像、PDF、テキストファイル（5MB以下）</small>
          </div>

          <!-- 連絡先メール -->
          <div class="form-group">
            <label for="email" class="form-label">連絡先メールアドレス（任意）</label>
            <input type="email" id="email" name="email" class="form-control" placeholder="返信が必要な場合のみ入力してください">
            <small class="form-text">ご入力いただいた場合、対応状況をお知らせいたします</small>
          </div>

          <!-- 送信ボタン -->
          <div class="form-actions">
            <button type="submit" class="btn btn-primary">フィードバックを送信</button>
            <button type="reset" class="btn btn-secondary">リセット</button>
          </div>
        </form>
      </div>

      <!-- フィードバックガイド -->
      <div class="feedback-guide">
        <h3>📋 フィードバック送信のコツ</h3>
        <div class="guide-content">
          <div class="guide-item">
            <h4>🎯 具体的に記述する</h4>
            <p>「使いにくい」ではなく「○○の操作で○○が起こる」のように具体的に記述していただけると、より迅速に対応できます。</p>
          </div>
          <div class="guide-item">
            <h4>📱 環境情報を詳しく</h4>
            <p>デバイス、ブラウザ、OSの情報があると、問題の特定が早くなります。</p>
          </div>
          <div class="guide-item">
            <h4>🖼️ スクリーンショットを添付</h4>
            <p>バグ報告の際は、可能であればスクリーンショットを添付してください。</p>
          </div>
          <div class="guide-item">
            <h4>💡 改善案も歓迎</h4>
            <p>「こうすれば良くなる」というご提案も大歓迎です。ユーザーの視点からのアイデアがサービスの改善につながります。</p>
          </div>
        </div>
      </div>
    </div>
  </section>
</div>

<style>
.feedback-form-container {
  max-width: 800px;
  margin: 0 auto;
}

.feedback-form {
  background: white;
  padding: 30px;
  border-radius: 12px;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
}

.form-group {
  margin-bottom: 25px;
}

.form-label {
  display: block;
  margin-bottom: 8px;
  font-weight: 600;
  color: var(--text-primary);
}

.required {
  color: var(--danger-color);
}

.form-control {
  width: 100%;
  padding: 12px 15px;
  border: 2px solid var(--border-light);
  border-radius: 8px;
  font-size: 1rem;
  transition: border-color 0.3s ease, box-shadow 0.3s ease;
}

.form-control:focus {
  outline: none;
  border-color: var(--primary-color);
  box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.form-control.error {
  border-color: var(--danger-color);
}

.form-control.success {
  border-color: var(--success-color);
}

select.form-control {
  appearance: none;
  -webkit-appearance: none;
  -moz-appearance: none;
  background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e");
  background-position: right 12px center;
  background-repeat: no-repeat;
  background-size: 16px;
  padding-right: 40px;
}

select.form-control:focus {
  background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236a5af9' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e");
}

/* ブラウザのデフォルト矢印を完全に非表示 */
select.form-control::-ms-expand {
  display: none;
}

textarea.form-control {
  resize: vertical;
  min-height: 120px;
}

.form-text {
  display: block;
  margin-top: 5px;
  font-size: 0.875rem;
  color: var(--text-muted);
}

.checkbox-group {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.checkbox-label {
  display: flex;
  align-items: center;
  cursor: pointer;
  font-size: 1rem;
  color: var(--text-primary);
}

.checkbox-custom {
  width: 20px;
  height: 20px;
  border: 2px solid var(--border-light);
  border-radius: 4px;
  margin-right: 10px;
  position: relative;
  transition: all 0.3s ease;
}

input[type="checkbox"]:checked + .checkbox-label .checkbox-custom {
  background-color: var(--primary-color);
  border-color: var(--primary-color);
}

input[type="checkbox"]:checked + .checkbox-label .checkbox-custom::after {
  content: '✓';
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  color: white;
  font-size: 12px;
  font-weight: bold;
}

.checkbox-text {
  flex: 1;
}

.form-actions {
  display: flex;
  gap: 15px;
  justify-content: center;
  margin-top: 30px;
  padding-top: 20px;
  border-top: 1px solid var(--border-light);
}

.btn {
  padding: 12px 30px;
  border: none;
  border-radius: 8px;
  font-size: 1rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  text-decoration: none;
  display: inline-block;
  text-align: center;
}

.btn-primary {
  background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
  color: white;
}

.btn-primary:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
}

.btn-secondary {
  background: var(--text-muted);
  color: white;
}

.btn-secondary:hover {
  background: var(--text-secondary);
  transform: translateY(-2px);
}

.feedback-guide {
  margin-top: 40px;
  padding: 30px;
  background: var(--bg-secondary);
  border-radius: 12px;
  border-left: 4px solid var(--primary-color);
}

.feedback-guide h3 {
  color: var(--text-primary);
  margin-bottom: 20px;
  font-size: 1.3rem;
}

.guide-content {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 20px;
}

.guide-item {
  background: white;
  padding: 20px;
  border-radius: 8px;
  box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
}

.guide-item h4 {
  color: var(--primary-color);
  margin-bottom: 10px;
  font-size: 1.1rem;
}

.guide-item p {
  color: var(--text-muted);
  line-height: 1.6;
  margin: 0;
}

@media (max-width: 768px) {
  .feedback-form {
    padding: 20px;
  }

  .form-actions {
    flex-direction: column;
  }

  .guide-content {
    grid-template-columns: 1fr;
  }

  .feedback-guide {
    padding: 20px;
  }
}


</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const form = document.getElementById('feedbackForm');
  const fileInput = document.getElementById('attachment');

  // ファイルサイズチェック
  fileInput.addEventListener('change', function() {
    const file = this.files[0];
    if (file && file.size > 5 * 1024 * 1024) { // 5MB
      if (typeof showToastNotification !== 'undefined') {
        showToastNotification('ファイルサイズは5MB以下にしてください。', 'warning');
      } else {
        alert('ファイルサイズは5MB以下にしてください。');
      }
      this.value = '';
    }
  });

  // フォーム送信
  form.addEventListener('submit', function(e) {
    e.preventDefault();

    // フォームデータの収集
    const formData = new FormData(form);
    const data = {};

    for (let [key, value] of formData.entries()) {
      if (key === 'environment[]') {
        if (!data[key]) data[key] = [];
        data[key].push(value);
      } else {
        data[key] = value;
      }
    }

    // コンソールに送信データを表示（デモ用）
    console.log('送信データ:', data);

    // 送信成功メッセージ
    if (typeof showToastNotification !== 'undefined') {
      showToastNotification('フィードバックを送信しました。ご協力ありがとうございます。', 'success');
    } else {
      alert('フィードバックを送信しました。ご協力ありがとうございます。');
    }

    // フォームリセット
    form.reset();
  });

  // フォームバリデーション
  const requiredFields = form.querySelectorAll('[required]');
  requiredFields.forEach(field => {
    field.addEventListener('blur', function() {
      if (this.value.trim() === '') {
        this.classList.add('error');
        this.classList.remove('success');
      } else {
        this.classList.remove('error');
        this.classList.add('success');
      }
    });
  });
});
</script>

<?php get_footer(); ?>
