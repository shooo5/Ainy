<?php
/*
Template Name: メッセージ掲示板
*/
get_header();

// ログインチェック
if (!is_user_logged_in()) {
  wp_redirect(home_url('/login'));
  exit;
}

// チーム所属チェック
$user_id = get_current_user_id();
$team_id = get_user_meta($user_id, 'team_id', true);
if (!$team_id) {
  wp_redirect(home_url('/mypage'));
  exit;
}

list($user_role, $preview_mode) = aidunite_get_effective_user_role();
?>

<div class="message-board-container">
  <?php aidunite_preview_mode_banner($preview_mode); ?>

  <div class="board-header">
    <h1>メッセージ掲示板</h1>
    <p>チームからのお知らせや連絡事項</p>
  </div>

  <div class="board-main">
    <!-- メッセージ投稿エリア -->
    <div class="post-section">
      <div class="post-card">
        <h2>💬 新しいメッセージを投稿</h2>

        <form id="messageForm" class="message-form">
          <div class="form-group">
            <label for="messageTitle">タイトル *</label>
            <input type="text" id="messageTitle" name="title" required class="form-input" placeholder="メッセージのタイトル">
          </div>

          <div class="form-group">
            <label for="messageContent">内容 *</label>
            <textarea id="messageContent" name="content" required class="form-textarea" rows="6" placeholder="メッセージの内容を入力してください"></textarea>
          </div>

          <div class="form-group">
            <label for="messageCategory">カテゴリ</label>
            <select id="messageCategory" name="category" class="form-select">
              <option value="">選択してください</option>
              <option value="announcement">お知らせ</option>
              <option value="schedule">スケジュール</option>
              <option value="practice">練習</option>
              <option value="match">試合</option>
              <option value="general">その他</option>
            </select>
          </div>

          <div class="form-group">
            <label for="messagePriority">重要度</label>
            <select id="messagePriority" name="priority" class="form-select">
              <option value="normal">通常</option>
              <option value="important">重要</option>
              <option value="urgent">緊急</option>
            </select>
          </div>

          <div class="form-group">
            <label class="checkbox-label">
              <input type="checkbox" id="pinMessage" name="pinned">
              <span class="checkmark"></span>
              掲示板に固定する
            </label>
          </div>

          <div class="form-actions">
            <button type="submit" class="post-btn" id="postBtn">
              <span class="post-icon">📤</span>
              <span class="post-text">投稿</span>
            </button>
            <button type="button" class="draft-btn" id="draftBtn">
              <span class="draft-icon">💾</span>
              <span class="draft-text">下書き保存</span>
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- メッセージ一覧エリア -->
    <div class="messages-section">
      <div class="messages-card">
        <div class="messages-header">
          <h2>📋 メッセージ一覧</h2>
          <div class="filter-controls">
            <select id="categoryFilter" class="filter-select">
              <option value="">全てのカテゴリ</option>
              <option value="announcement">お知らせ</option>
              <option value="schedule">スケジュール</option>
              <option value="practice">練習</option>
              <option value="match">試合</option>
              <option value="general">その他</option>
            </select>
            <select id="priorityFilter" class="filter-select">
              <option value="">全ての重要度</option>
              <option value="urgent">緊急</option>
              <option value="important">重要</option>
              <option value="normal">通常</option>
            </select>
          </div>
        </div>

        <div id="messagesList" class="messages-list">
          <!-- メッセージがここに表示されます -->
          <div class="loading-message">メッセージを読み込み中...</div>
        </div>
      </div>
    </div>
  </div>
</div>

<style>
.message-board-container {
  max-width: 1200px;
  margin: 0 auto;
  padding: 20px;
}

.board-header {
  text-align: center;
  margin-bottom: 40px;
  padding: 30px;
  background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
  color: white;
  border-radius: 12px;
}

.board-header h1 {
  margin: 0 0 10px 0;
  font-size: 2.5rem;
}

.board-header p {
  margin: 0;
  font-size: 1.1rem;
  opacity: 0.9;
}

.board-main {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 30px;
}

.post-section,
.messages-section {
  display: flex;
  flex-direction: column;
}

.post-card,
.messages-card {
  background: white;
  border-radius: 12px;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
  overflow: hidden;
}

.post-card h2,
.messages-card h2 {
  margin: 0;
  padding: 25px;
  background: var(--bg-secondary);
  border-bottom: 1px solid var(--border-light);
  font-size: 1.3rem;
  color: var(--text-primary);
}

.message-form {
  padding: 25px;
}

.form-group {
  margin-bottom: 20px;
}

.form-group label {
  display: block;
  margin-bottom: 8px;
  font-weight: 600;
  color: var(--text-primary);
}

.form-input,
.form-select,
.form-textarea {
  width: 100%;
  padding: 12px;
  border: 2px solid var(--border-light);
  border-radius: 8px;
  font-size: 0.95rem;
  transition: border-color 0.3s ease;
  box-sizing: border-box;
}

.form-input:focus,
.form-select:focus,
.form-textarea:focus {
  outline: none;
  border-color: var(--primary-color);
}

.form-textarea {
  resize: vertical;
  min-height: 120px;
}

.checkbox-label {
  display: flex;
  align-items: center;
  cursor: pointer;
  font-weight: 500;
  color: var(--text-primary);
}

.checkbox-label input[type="checkbox"] {
  margin-right: 10px;
  width: 18px;
  height: 18px;
}

.form-actions {
  display: flex;
  gap: 15px;
  margin-top: 30px;
  padding-top: 20px;
  border-top: 1px solid var(--border-light);
}

.post-btn,
.draft-btn {
  flex: 1;
  padding: 12px 20px;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  font-weight: 600;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  transition: all 0.3s ease;
  font-size: 0.95rem;
}

.post-btn {
  background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
  color: white;
}

.post-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 8px rgba(102, 126, 234, 0.3);
}

.draft-btn {
  background: var(--text-muted);
  color: white;
}

.draft-btn:hover {
  background: var(--text-secondary);
  transform: translateY(-2px);
}

.messages-header {
  padding: 25px;
  background: var(--bg-secondary);
  border-bottom: 1px solid var(--border-light);
}

.messages-header h2 {
  margin: 0 0 20px 0;
  padding: 0;
  background: none;
  border: none;
  font-size: 1.3rem;
  color: var(--text-primary);
}

.filter-controls {
  display: flex;
  gap: 15px;
}

.filter-select {
  flex: 1;
  padding: 8px 12px;
  border: 1px solid var(--border-light);
  border-radius: 6px;
  font-size: 0.9rem;
  background: white;
}

.messages-list {
  max-height: 600px;
  overflow-y: auto;
}

.message-item {
  padding: 20px;
  border-bottom: 1px solid var(--border-light);
  transition: background-color 0.3s ease;
}

.message-item:hover {
  background: var(--bg-secondary);
}

.message-item.pinned {
  background: rgba(255, 193, 7, 0.1);
  border-left: 4px solid var(--warning-color);
}

.message-item.urgent {
  background: rgba(220, 53, 69, 0.1);
  border-left: 4px solid var(--danger-color);
}

.message-item.important {
  background: rgba(23, 162, 184, 0.1);
  border-left: 4px solid var(--info-color);
}

.message-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 10px;
}

.message-title {
  font-weight: 600;
  color: var(--text-primary);
  font-size: 1.1rem;
  margin: 0;
}

.message-meta {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 0.85rem;
  color: var(--text-muted);
}

.message-category {
  background: var(--border-light);
  padding: 2px 8px;
  border-radius: 12px;
  font-size: 0.8rem;
}

.message-priority {
  padding: 2px 8px;
  border-radius: 12px;
  font-size: 0.8rem;
  color: white;
}

.priority-urgent {
  background: var(--danger-color);
}

.priority-important {
  background: var(--info-color);
}

.priority-normal {
  background: var(--text-muted);
}

.message-content {
  color: var(--text-primary);
  line-height: 1.5;
  margin-bottom: 10px;
}

.message-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 0.85rem;
  color: var(--text-muted);
}

.message-author {
  font-weight: 500;
}

.message-actions {
  display: flex;
  gap: 10px;
}

.action-btn {
  padding: 4px 8px;
  background: none;
  border: 1px solid var(--border-light);
  border-radius: 4px;
  cursor: pointer;
  font-size: 0.8rem;
  color: var(--text-muted);
  transition: all 0.3s ease;
}

.action-btn:hover {
  background: var(--border-light);
  color: var(--text-primary);
}

.pin-icon {
  color: var(--warning-color);
}

.loading-message {
  text-align: center;
  color: var(--text-muted);
  padding: 40px;
}

.no-messages {
  text-align: center;
  color: var(--text-muted);
  padding: 40px;
}

@media (max-width: 768px) {
  .message-board-container {
    padding: 15px;
  }

  .board-header h1 {
    font-size: 2rem;
  }

  .board-main {
    grid-template-columns: 1fr;
    gap: 20px;
  }

  .filter-controls {
    flex-direction: column;
  }

  .form-actions {
    flex-direction: column;
  }

  .message-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 10px;
  }

  .message-meta {
    flex-wrap: wrap;
  }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const messageForm = document.getElementById('messageForm');
  const messagesList = document.getElementById('messagesList');
  const categoryFilter = document.getElementById('categoryFilter');
  const priorityFilter = document.getElementById('priorityFilter');
  const postBtn = document.getElementById('postBtn');
  const draftBtn = document.getElementById('draftBtn');

  let messages = [];
  let filteredMessages = [];

  // メッセージ投稿
  messageForm.addEventListener('submit', function(e) {
    e.preventDefault();

    // 送信ボタンを無効化
    postBtn.disabled = true;
    postBtn.innerHTML = '<span class="post-icon">⏳</span><span class="post-text">投稿中...</span>';

    // フォームデータを収集
    const formData = new FormData(messageForm);
    formData.append('action', 'post_message');
    formData.append('team_id', '<?php echo $team_id; ?>');

    // メッセージを投稿（実際のAPI呼び出し）
    postMessage(formData).then(() => {
      alert('メッセージを投稿しました！');
      messageForm.reset();
      loadMessages();
    }).catch(error => {
      console.error('投稿エラー:', error);
      alert('投稿に失敗しました。もう一度お試しください。');
    }).finally(() => {
      postBtn.disabled = false;
      postBtn.innerHTML = '<span class="post-icon">📤</span><span class="post-text">投稿</span>';
    });
  });

  // 下書き保存
  draftBtn.addEventListener('click', function() {
    const formData = new FormData(messageForm);
    formData.append('action', 'save_message_draft');
    formData.append('team_id', '<?php echo $team_id; ?>');

    draftBtn.disabled = true;
    draftBtn.innerHTML = '<span class="draft-icon">⏳</span><span class="draft-text">保存中...</span>';

    saveMessageDraft(formData).then(() => {
      alert('下書きを保存しました！');
    }).catch(error => {
      console.error('保存エラー:', error);
      alert('保存に失敗しました。');
    }).finally(() => {
      draftBtn.disabled = false;
      draftBtn.innerHTML = '<span class="draft-icon">💾</span><span class="draft-text">下書き保存</span>';
    });
  });

  // フィルター変更時の処理
  categoryFilter.addEventListener('change', filterMessages);
  priorityFilter.addEventListener('change', filterMessages);

  // メッセージ投稿関数（実際のAPI実装が必要）
  async function postMessage(formData) {
    console.log('メッセージ投稿:', Object.fromEntries(formData));

    // 実際の実装では、サーバーにデータを送信
    // const response = await fetch('/wp-json/aidunite/v1/message', {
    //   method: 'POST',
    //   headers: {
    //     'X-WP-Nonce': '<?php echo wp_create_nonce('wp_rest'); ?>'
    //   },
    //   body: formData
    // });

    return Promise.resolve();
  }

  // 下書き保存関数
  async function saveMessageDraft(formData) {
    console.log('下書き保存:', Object.fromEntries(formData));

    // 実際の実装では、サーバーにデータを送信
    // const response = await fetch('/wp-json/aidunite/v1/message-draft', {
    //   method: 'POST',
    //   headers: {
    //     'X-WP-Nonce': '<?php echo wp_create_nonce('wp_rest'); ?>'
    //   },
    //   body: formData
    // });

    return Promise.resolve();
  }

  // メッセージ読み込み
  loadMessages();

  async function loadMessages() {
    try {
      // 実際のAPI実装が必要
      console.log('メッセージ読み込み');

      // 仮のデータ
      messages = [
        {
          id: 1,
          title: '明日の練習について',
          content: '明日の練習は雨天のため、体育館で行います。時間は通常通りです。',
          category: 'practice',
          priority: 'important',
          pinned: true,
          author: 'チーム代表者',
          created_at: '2024-01-15 14:30:00'
        },
        {
          id: 2,
          title: '試合結果報告',
          content: '先日の試合で勝利しました。選手の皆さん、お疲れ様でした。',
          category: 'match',
          priority: 'normal',
          pinned: false,
          author: 'チーム代表者',
          created_at: '2024-01-14 18:00:00'
        }
      ];

      filterMessages();
    } catch (error) {
      console.error('メッセージ読み込みエラー:', error);
      messagesList.innerHTML = '<div class="loading-message">メッセージの読み込みに失敗しました</div>';
    }
  }

  // メッセージフィルター
  function filterMessages() {
    const categoryValue = categoryFilter.value;
    const priorityValue = priorityFilter.value;

    filteredMessages = messages.filter(message => {
      const categoryMatch = !categoryValue || message.category === categoryValue;
      const priorityMatch = !priorityValue || message.priority === priorityValue;
      return categoryMatch && priorityMatch;
    });

    renderMessages();
  }

  // メッセージ表示
  function renderMessages() {
    if (filteredMessages.length === 0) {
      messagesList.innerHTML = '<div class="no-messages">メッセージがありません</div>';
      return;
    }

    // 固定メッセージを先に表示
    const pinnedMessages = filteredMessages.filter(msg => msg.pinned);
    const normalMessages = filteredMessages.filter(msg => !msg.pinned);
    const sortedMessages = [...pinnedMessages, ...normalMessages];

    messagesList.innerHTML = sortedMessages.map(message => createMessageElement(message)).join('');
  }

  // メッセージ要素作成
  function createMessageElement(message) {
    const categoryLabels = {
      'announcement': 'お知らせ',
      'schedule': 'スケジュール',
      'practice': '練習',
      'match': '試合',
      'general': 'その他'
    };

    const priorityLabels = {
      'urgent': '緊急',
      'important': '重要',
      'normal': '通常'
    };

    const priorityClasses = {
      'urgent': 'priority-urgent',
      'important': 'priority-important',
      'normal': 'priority-normal'
    };

    const date = new Date(message.created_at).toLocaleDateString('ja-JP');
    const time = new Date(message.created_at).toLocaleTimeString('ja-JP', {
      hour: '2-digit',
      minute: '2-digit'
    });

    const messageClasses = ['message-item'];
    if (message.pinned) messageClasses.push('pinned');
    if (message.priority === 'urgent') messageClasses.push('urgent');
    if (message.priority === 'important') messageClasses.push('important');

    return `
      <div class="${messageClasses.join(' ')}">
        <div class="message-header">
          <h3 class="message-title">
            ${message.title}
            ${message.pinned ? '<span class="pin-icon">📌</span>' : ''}
          </h3>
          <div class="message-meta">
            <span class="message-category">${categoryLabels[message.category]}</span>
            <span class="message-priority ${priorityClasses[message.priority]}">${priorityLabels[message.priority]}</span>
          </div>
        </div>
        <div class="message-content">${message.content}</div>
        <div class="message-footer">
          <span class="message-author">投稿者: ${message.author}</span>
          <div class="message-actions">
            <span>${date} ${time}</span>
            ${message.pinned ? '<button class="action-btn" onclick="togglePin(${message.id})">📌 固定解除</button>' : '<button class="action-btn" onclick="togglePin(${message.id})">📌 固定</button>'}
            <button class="action-btn" onclick="deleteMessage(${message.id})">🗑️ 削除</button>
          </div>
        </div>
      </div>
    `;
  }
});

// メッセージ固定切り替え
function togglePin(messageId) {
  console.log('メッセージ固定切り替え:', messageId);
  // 実際のAPI実装が必要
}

// メッセージ削除
function deleteMessage(messageId) {
  if (confirm('このメッセージを削除しますか？')) {
    console.log('メッセージ削除:', messageId);
    // 実際のAPI実装が必要
  }
}
</script>

<?php get_footer(); ?>
