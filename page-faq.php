<?php
/**
 * Template Name: FAQ
 */

get_header();
?>

<div class="team-dashboard-container page-faq">
  <!-- ダッシュボードヘッダー -->
  <div class="dashboard-header">
    <h1>FAQ</h1>
    <p>よくある質問と回答をご確認ください</p>
  </div>

  <!-- FAQセクション -->
  <section class="dashboard-section">
    <h2>📋 よくある質問</h2>
    <div class="main-content-area">
      <div class="faq-container">
        <!-- アカウント関連 -->
        <div class="faq-category">
          <h3>👤 アカウント関連</h3>
          <div class="faq-item">
            <div class="faq-question" onclick="toggleFaq(this)">
              <span>アカウントの作成方法を教えてください</span>
              <span class="faq-toggle">+</span>
            </div>
            <div class="faq-answer">
              <p>トップページの「新規登録」ボタンから、メールアドレスとパスワードを入力してアカウントを作成できます。登録後、確認メールが送信されますので、メール内のリンクをクリックしてアカウントを有効化してください。</p>
            </div>
          </div>
          <div class="faq-item">
            <div class="faq-question" onclick="toggleFaq(this)">
              <span>パスワードを忘れた場合の対処法</span>
              <span class="faq-toggle">+</span>
            </div>
            <div class="faq-answer">
              <p>ログイン画面の「パスワードを忘れた方」リンクをクリックし、登録済みのメールアドレスを入力してください。パスワードリセット用のリンクがメールで送信されます。</p>
            </div>
          </div>
        </div>

        <!-- チーム関連 -->
        <div class="faq-category">
          <h3>🏆 チーム関連</h3>
          <div class="faq-item">
            <div class="faq-question" onclick="toggleFaq(this)">
              <span>チームの作成方法を教えてください</span>
              <span class="faq-toggle">+</span>
            </div>
            <div class="faq-answer">
              <p>マイページから「チーム作成」を選択し、チーム名、活動地域、スポーツ種目などの基本情報を入力してください。作成後、メンバーを招待できます。</p>
            </div>
          </div>
          <div class="faq-item">
            <div class="faq-question" onclick="toggleFaq(this)">
              <span>チームメンバーの招待方法</span>
              <span class="faq-toggle">+</span>
            </div>
            <div class="faq-answer">
              <p>チーム管理画面から「メンバー招待」を選択し、招待したい方のメールアドレスを入力してください。招待メールが送信され、承認後にチームに参加できます。</p>
            </div>
          </div>
        </div>

        <!-- スケジュール関連 -->
        <div class="faq-category">
          <h3>📅 スケジュール関連</h3>
          <div class="faq-item">
            <div class="faq-question" onclick="toggleFaq(this)">
              <span>練習スケジュールの作成方法</span>
              <span class="faq-toggle">+</span>
            </div>
            <div class="faq-answer">
              <p>スケジュール管理画面から「新規作成」を選択し、日時、場所、内容を入力してください。メンバー全員に通知が送信され、出欠の回答を集計できます。</p>
            </div>
          </div>
          <div class="faq-item">
            <div class="faq-question" onclick="toggleFaq(this)">
              <span>スケジュールの変更・キャンセル方法</span>
              <span class="faq-toggle">+</span>
            </div>
            <div class="faq-answer">
              <p>スケジュール詳細画面から「編集」または「削除」を選択できます。変更・削除時は、メンバー全員に自動で通知が送信されます。</p>
            </div>
          </div>
        </div>

        <!-- マッチング関連 -->
        <div class="faq-category">
          <h3>🤝 マッチング関連</h3>
          <div class="faq-item">
            <div class="faq-question" onclick="toggleFaq(this)">
              <span>対戦相手の探し方</span>
              <span class="faq-toggle">+</span>
            </div>
            <div class="faq-answer">
              <p>マッチボードから地域やレベルに応じた対戦相手を検索できます。条件に合う相手が見つかったら、マッチリクエストを送信してください。</p>
            </div>
          </div>
          <div class="faq-item">
            <div class="faq-question" onclick="toggleFaq(this)">
              <span>マッチリクエストの承認・拒否方法</span>
              <span class="faq-toggle">+</span>
            </div>
            <div class="faq-answer">
              <p>通知画面からマッチリクエストを確認し、「承認」または「拒否」を選択してください。承認後は、詳細な調整が可能になります。</p>
            </div>
          </div>
        </div>

        <!-- 料金関連 -->
        <div class="faq-category">
          <h3>💰 料金関連</h3>
          <div class="faq-item">
            <div class="faq-question" onclick="toggleFaq(this)">
              <span>料金プランの詳細を教えてください</span>
              <span class="faq-toggle">+</span>
            </div>
            <div class="faq-answer">
              <p>無料プラン、スタンダードプラン（月額1,000円）、プレミアムプラン（月額2,000円）の3つのプランをご用意しています。詳細は料金ページでご確認ください。</p>
            </div>
          </div>
          <div class="faq-item">
            <div class="faq-question" onclick="toggleFaq(this)">
              <span>プランの変更方法</span>
              <span class="faq-toggle">+</span>
            </div>
            <div class="faq-answer">
              <p>マイページの「プラン管理」から、いつでもプランの変更が可能です。変更は翌月から適用され、差額分の精算が行われます。</p>
            </div>
          </div>
        </div>

        <!-- 技術サポート -->
        <div class="faq-category">
          <h3>🔧 技術サポート</h3>
          <div class="faq-item">
            <div class="faq-question" onclick="toggleFaq(this)">
              <span>アプリが正常に動作しない場合</span>
              <span class="faq-toggle">+</span>
            </div>
            <div class="faq-answer">
              <p>ブラウザのキャッシュをクリアするか、別のブラウザでお試しください。問題が解決しない場合は、お問い合わせフォームからご連絡ください。</p>
            </div>
          </div>
          <div class="faq-item">
            <div class="faq-question" onclick="toggleFaq(this)">
              <span>推奨ブラウザについて</span>
              <span class="faq-toggle">+</span>
            </div>
            <div class="faq-answer">
              <p>Chrome、Firefox、Safari、Edgeの最新版での動作を推奨しています。Internet Explorerはサポート対象外です。</p>
            </div>
          </div>
        </div>
      </div>

      <!-- お問い合わせセクション -->
      <div class="contact-section">
        <h3>📞 お問い合わせ</h3>
        <p>上記のFAQで解決しない場合は、お気軽にお問い合わせください。</p>
        <a href="<?php echo home_url('/feedback'); ?>" class="btn btn-primary">お問い合わせフォーム</a>
      </div>
    </div>
  </section>
</div>

<style>
.faq-container {
  max-width: 800px;
  margin: 0 auto;
}

.faq-category {
  margin-bottom: 40px;
}

.faq-category h3 {
  color: var(--text-primary);
  font-size: 1.2rem;
  margin-bottom: 20px;
  padding-bottom: 10px;
  border-bottom: 2px solid #e9ecef;
}

.faq-item {
  margin-bottom: 15px;
  border: 1px solid var(--border-light);
  border-radius: 8px;
  overflow: hidden;
  transition: all 0.3s ease;
}

.faq-item:hover {
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.faq-question {
  background: #f8f9fa;
  padding: 20px;
  cursor: pointer;
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-weight: 600;
  color: var(--text-primary);
  transition: background-color 0.3s ease;
}

.faq-question:hover {
  background: #e9ecef;
}

.faq-toggle {
  font-size: 1.2rem;
  font-weight: bold;
  color: var(--text-muted);
  transition: transform 0.3s ease;
}

.faq-question.active .faq-toggle {
  transform: rotate(45deg);
}

.faq-answer {
  max-height: 0;
  overflow: hidden;
  transition: max-height 0.3s ease;
  background: white;
}

.faq-answer.active {
  max-height: 200px;
}

.faq-answer p {
  padding: 20px;
  margin: 0;
  line-height: 1.6;
  color: var(--text-muted);
}

.contact-section {
  text-align: center;
  margin-top: 50px;
  padding: 30px;
  background: #f8f9fa;
  border-radius: 12px;
}

.contact-section h3 {
  color: var(--text-primary);
  margin-bottom: 15px;
}

.contact-section p {
  color: var(--text-muted);
  margin-bottom: 20px;
}

@media (max-width: 768px) {
  .faq-question {
    padding: 15px;
    font-size: 0.9rem;
  }

  .faq-answer p {
    padding: 15px;
  }

  .contact-section {
    padding: 20px;
  }
}
</style>

<script>
function toggleFaq(element) {
  const answer = element.nextElementSibling;
  const isActive = element.classList.contains('active');

  // 他のFAQアイテムを閉じる
  document.querySelectorAll('.faq-question.active').forEach(item => {
    if (item !== element) {
      item.classList.remove('active');
      item.nextElementSibling.classList.remove('active');
    }
  });

  // クリックされたアイテムの開閉
  if (isActive) {
    element.classList.remove('active');
    answer.classList.remove('active');
  } else {
    element.classList.add('active');
    answer.classList.add('active');
  }
}
</script>

<?php get_footer(); ?>
