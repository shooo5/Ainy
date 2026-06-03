<?php
/**
 * Template Name: プレスリリース
 */

get_header();
?>

<div class="team-dashboard-container page-press">
  <!-- ダッシュボードヘッダー -->
  <div class="dashboard-header">
    <h1>プレスリリース</h1>
    <p>TUNAGERUの最新ニュースとお知らせ</p>
  </div>

  <!-- プレスリリースセクション -->
  <section class="dashboard-section">
    <h2>📋 プレスリリース一覧</h2>
    <div class="main-content-area">
      <!-- フィルターボタン -->
      <div class="filter-section">
        <div class="filter-buttons">
          <button class="filter-btn active" data-filter="all">すべて</button>
          <button class="filter-btn" data-filter="news">ニュース</button>
          <button class="filter-btn" data-filter="release">リリース</button>
          <button class="filter-btn" data-filter="update">アップデート</button>
        </div>
      </div>

      <!-- プレスリリース一覧 -->
      <div class="press-list">
        <div class="press-item" data-category="release">
          <div class="press-meta">
            <span class="press-date">2024年1月15日</span>
            <span class="press-category release">リリース</span>
          </div>
          <h3 class="press-title">TUNAGERU正式リリースのお知らせ</h3>
          <p class="press-excerpt">スポーツチーム運営支援プラットフォーム「TUNAGERU」の正式リリースを開始いたします。スケジュール管理、マッチング機能、チーム管理など、チーム運営に必要な機能を一つのサービスで提供します。</p>
          <div class="press-content" style="display: none;">
            <p>TUNAGERUは、スポーツチームの運営を効率化し、チームメンバー間のコミュニケーションを円滑にするための総合プラットフォームです。</p>
            <h4>主な機能</h4>
            <ul>
              <li>スケジュール管理機能</li>
              <li>マッチング機能</li>
              <li>チーム管理機能</li>
              <li>コミュニケーション機能</li>
              <li>分析・レポート機能</li>
            </ul>
            <p>本サービスは、無料プランからスタートし、チームの規模やニーズに応じてスタンダードプラン（月額1,000円）、プレミアムプラン（月額2,000円）をご用意しています。</p>
            <p>詳しくは<a href="<?php echo home_url('/service'); ?>">サービスページ</a>をご覧ください。</p>
          </div>
          <button class="read-more-btn">詳細を読む</button>
        </div>

        <div class="press-item" data-category="news">
          <div class="press-meta">
            <span class="press-date">2024年1月10日</span>
            <span class="press-category news">ニュース</span>
          </div>
          <h3 class="press-title">ベータ版ユーザー数が1,000名を突破</h3>
          <p class="press-excerpt">TUNAGERUのベータ版サービスにおいて、登録ユーザー数が1,000名を突破いたしました。多くのスポーツチームにご利用いただき、ありがとうございます。</p>
          <div class="press-content" style="display: none;">
            <p>2023年12月から開始したベータ版サービスにおいて、多くのスポーツチームにご参加いただき、登録ユーザー数が1,000名を突破いたしました。</p>
            <h4>ベータ版での成果</h4>
            <ul>
              <li>登録チーム数：150チーム</li>
              <li>総ユーザー数：1,000名以上</li>
              <li>サポートされているスポーツ：10種目以上</li>
              <li>平均満足度：4.5/5.0</li>
            </ul>
            <p>ベータ版での貴重なフィードバックを基に、正式リリースに向けてサービスを改善してまいります。</p>
          </div>
          <button class="read-more-btn">詳細を読む</button>
        </div>

        <div class="press-item" data-category="update">
          <div class="press-meta">
            <span class="press-date">2024年1月5日</span>
            <span class="press-category update">アップデート</span>
          </div>
          <h3 class="press-title">モバイルアプリのベータ版をリリース</h3>
          <p class="press-excerpt">TUNAGERUのモバイルアプリ（iOS/Android）のベータ版をリリースいたします。スマートフォンからも快適にチーム運営を行えるようになります。</p>
          <div class="press-content" style="display: none;">
            <p>スマートフォンでの利用ニーズにお応えし、TUNAGERUのモバイルアプリのベータ版をリリースいたします。</p>
            <h4>モバイルアプリの主な機能</h4>
            <ul>
              <li>スケジュールの確認・作成</li>
              <li>出欠の回答</li>
              <li>チーム内メッセージの送受信</li>
              <li>プッシュ通知</li>
              <li>マッチング機能</li>
            </ul>
            <h4>対応OS</h4>
            <ul>
              <li>iOS 14.0以上</li>
              <li>Android 8.0以上</li>
            </ul>
            <p>アプリのダウンロードは、App StoreとGoogle Play Storeから可能です。</p>
          </div>
          <button class="read-more-btn">詳細を読む</button>
        </div>

        <div class="press-item" data-category="news">
          <div class="press-meta">
            <span class="press-date">2023年12月20日</span>
            <span class="press-category news">ニュース</span>
          </div>
          <h3 class="press-title">スポーツ団体との連携を開始</h3>
          <p class="press-excerpt">複数のスポーツ団体との連携を開始いたします。これにより、より多くのチームにTUNAGERUをご利用いただけるようになります。</p>
          <div class="press-content" style="display: none;">
            <p>TUNAGERUの普及とスポーツコミュニティの発展を目指し、複数のスポーツ団体との連携を開始いたします。</p>
            <h4>連携先団体</h4>
            <ul>
              <li>日本サッカー協会（JFA）</li>
              <li>日本バスケットボール協会（JBA）</li>
              <li>日本バレーボール協会（JVA）</li>
              <li>日本テニス協会（JTA）</li>
            </ul>
            <p>これらの団体との連携により、以下のような取り組みを進めてまいります：</p>
            <ul>
              <li>公式大会でのTUNAGERU活用</li>
              <li>チーム登録の促進</li>
              <li>技術的なサポート</li>
              <li>イベントでの共同開催</li>
            </ul>
          </div>
          <button class="read-more-btn">詳細を読む</button>
        </div>

        <div class="press-item" data-category="update">
          <div class="press-meta">
            <span class="press-date">2023年12月15日</span>
            <span class="press-category update">アップデート</span>
          </div>
          <h3 class="press-title">分析機能の大幅強化</h3>
          <p class="press-excerpt">チームの活動状況をより詳細に分析できる機能を追加いたします。データに基づいたチーム運営の改善をサポートします。</p>
          <div class="press-content" style="display: none;">
            <p>チーム運営の改善に役立つ分析機能を大幅に強化いたします。</p>
            <h4>新機能</h4>
            <ul>
              <li>出席率の詳細分析</li>
              <li>活動頻度の可視化</li>
              <li>メンバー別の参加状況</li>
              <li>対戦結果の統計</li>
              <li>月次・年次レポート</li>
              <li>カスタム分析ダッシュボード</li>
            </ul>
            <p>これらの機能により、チームの現状を正確に把握し、改善点を特定できるようになります。</p>
          </div>
          <button class="read-more-btn">詳細を読む</button>
        </div>

        <div class="press-item" data-category="release">
          <div class="press-meta">
            <span class="press-date">2023年12月1日</span>
            <span class="press-category release">リリース</span>
          </div>
          <h3 class="press-title">TUNAGERUベータ版の開始</h3>
          <p class="press-excerpt">スポーツチーム運営支援プラットフォーム「TUNAGERU」のベータ版サービスを開始いたします。限定ユーザーを対象としたテスト運用を開始します。</p>
          <div class="press-content" style="display: none;">
            <p>スポーツチームの運営をサポートする新しいプラットフォーム「TUNAGERU」のベータ版サービスを開始いたします。</p>
            <h4>ベータ版の特徴</h4>
            <ul>
              <li>限定ユーザーによるテスト運用</li>
              <li>フィードバックに基づく機能改善</li>
              <li>無料での利用</li>
              <li>専任サポート</li>
            </ul>
            <p>ベータ版では、以下の機能をテストしていただけます：</p>
            <ul>
              <li>スケジュール管理</li>
              <li>チームメンバー管理</li>
              <li>マッチング機能</li>
              <li>コミュニケーション機能</li>
            </ul>
            <p>ベータ版への参加をご希望の方は、<a href="<?php echo home_url('/feedback'); ?>">お問い合わせフォーム</a>からご連絡ください。</p>
          </div>
          <button class="read-more-btn">詳細を読む</button>
        </div>
      </div>

      <!-- メディア向け情報 -->
      <div class="media-section">
        <h3>📞 メディアの方へ</h3>
        <div class="media-content">
          <p>プレスリリースに関するお問い合わせや取材のお申し込みは、以下までご連絡ください。</p>
          <div class="media-info">
            <div class="media-item">
              <h4>📧 お問い合わせ</h4>
              <p>メール：<a href="mailto:press@tunageru.com">press@tunageru.com</a></p>
            </div>
            <div class="media-item">
              <h4>📱 お電話</h4>
              <p>03-1234-5678（平日 9:00-18:00）</p>
            </div>
            <div class="media-item">
              <h4>📄 資料ダウンロード</h4>
              <p><a href="#" class="download-link">会社概要資料</a> | <a href="#" class="download-link">ロゴ・画像素材</a></p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
</div>

<!-- モーダル -->
<div id="pressModal" class="modal">
  <div class="modal-content">
    <span class="close">&times;</span>
    <div id="modalContent"></div>
  </div>
</div>

<style>
.filter-section {
  margin-bottom: 30px;
  text-align: center;
}

.filter-buttons {
  display: flex;
  justify-content: center;
  gap: 15px;
  flex-wrap: wrap;
}

.filter-btn {
  padding: 10px 20px;
  border: 2px solid var(--primary-color);
  background: white;
  color: var(--primary-color);
  border-radius: 25px;
  cursor: pointer;
  font-weight: 600;
  transition: all 0.3s ease;
}

.filter-btn:hover,
.filter-btn.active {
  background: var(--primary-color);
  color: white;
}

.press-list {
  display: grid;
  gap: 25px;
  margin-bottom: 40px;
}

.press-item {
  background: white;
  padding: 25px;
  border-radius: 12px;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
  transition: all 0.3s ease;
}

.press-item:hover {
  transform: translateY(-3px);
  box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
}

.press-meta {
  display: flex;
  align-items: center;
  gap: 15px;
  margin-bottom: 15px;
}

.press-date {
  color: var(--text-muted);
  font-size: 0.9rem;
}

.press-category {
  padding: 4px 12px;
  border-radius: 15px;
  font-size: 0.8rem;
  font-weight: 600;
  color: white;
}

.press-category.news {
  background: var(--success-color);
}

.press-category.release {
  background: var(--primary-color);
}

.press-category.update {
  background: var(--warning-color);
  color: var(--text-primary);
}

.press-title {
  color: var(--text-primary);
  font-size: 1.3rem;
  margin-bottom: 15px;
  line-height: 1.4;
}

.press-excerpt {
  color: var(--text-muted);
  line-height: 1.6;
  margin-bottom: 20px;
}

.press-content {
  color: var(--text-primary);
  line-height: 1.6;
  margin-bottom: 20px;
}

.press-content h4 {
  color: var(--text-primary);
  margin: 20px 0 10px 0;
}

.press-content ul {
  margin: 10px 0;
  padding-left: 20px;
}

.press-content li {
  margin-bottom: 5px;
}

.press-content a {
  color: var(--primary-color);
  text-decoration: none;
}

.press-content a:hover {
  text-decoration: underline;
}

.read-more-btn {
  background: var(--primary-color);
  color: white;
  border: none;
  padding: 10px 20px;
  border-radius: 6px;
  cursor: pointer;
  font-weight: 600;
  transition: all 0.3s ease;
}

.read-more-btn:hover {
  background: var(--primary-dark);
  transform: translateY(-2px);
}

.media-section {
  background: var(--bg-secondary);
  padding: 30px;
  border-radius: 12px;
  border-left: 4px solid var(--primary-color);
}

.media-section h3 {
  color: var(--text-primary);
  margin-bottom: 20px;
  text-align: center;
}

.media-content p {
  color: var(--text-primary);
  text-align: center;
  margin-bottom: 25px;
}

.media-info {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 20px;
}

.media-item {
  background: white;
  padding: 20px;
  border-radius: 8px;
  text-align: center;
}

.media-item h4 {
  color: var(--text-primary);
  margin-bottom: 10px;
}

.media-item p {
  color: var(--text-muted);
  margin: 0;
}

.media-item a {
  color: var(--primary-color);
  text-decoration: none;
}

.media-item a:hover {
  text-decoration: underline;
}

.download-link {
  color: var(--primary-color);
  text-decoration: none;
  font-weight: 600;
}

.download-link:hover {
  text-decoration: underline;
}

/* モーダル */
.modal {
  display: none;
  position: fixed;
  z-index: 1000;
  left: 0;
  top: 0;
  width: 100%;
  height: 100%;
  background-color: rgba(0, 0, 0, 0.5);
}

.modal-content {
  background-color: white;
  margin: 5% auto;
  padding: 30px;
  border-radius: 12px;
  width: 90%;
  max-width: 800px;
  max-height: 80vh;
  overflow-y: auto;
  position: relative;
}

.close {
  color: var(--text-muted);
  float: right;
  font-size: 28px;
  font-weight: bold;
  cursor: pointer;
  position: absolute;
  right: 20px;
  top: 15px;
}

.close:hover,
.close:focus {
  color: var(--text-primary);
}

#modalContent {
  margin-top: 20px;
}

#modalContent h3 {
  color: var(--text-primary);
  margin-bottom: 15px;
}

#modalContent .press-meta {
  margin-bottom: 20px;
}

#modalContent .press-content {
  display: block;
  margin-bottom: 0;
}

@media (max-width: 768px) {
  .filter-buttons {
    flex-direction: column;
    align-items: center;
  }

  .filter-btn {
    width: 200px;
  }

  .press-meta {
    flex-direction: column;
    align-items: flex-start;
    gap: 8px;
  }

  .media-info {
    grid-template-columns: 1fr;
  }

  .modal-content {
    width: 95%;
    margin: 10% auto;
    padding: 20px;
  }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
  // フィルター機能
  const filterBtns = document.querySelectorAll('.filter-btn');
  const pressItems = document.querySelectorAll('.press-item');

  filterBtns.forEach(btn => {
    btn.addEventListener('click', function() {
      const filter = this.getAttribute('data-filter');

      // アクティブボタンの切り替え
      filterBtns.forEach(b => b.classList.remove('active'));
      this.classList.add('active');

      // アイテムの表示/非表示
      pressItems.forEach(item => {
        if (filter === 'all' || item.getAttribute('data-category') === filter) {
          item.style.display = 'block';
        } else {
          item.style.display = 'none';
        }
      });
    });
  });

  // モーダル機能
  const modal = document.getElementById('pressModal');
  const modalContent = document.getElementById('modalContent');
  const closeBtn = document.querySelector('.close');
  const readMoreBtns = document.querySelectorAll('.read-more-btn');

  readMoreBtns.forEach(btn => {
    btn.addEventListener('click', function() {
      const pressItem = this.closest('.press-item');
      const title = pressItem.querySelector('.press-title').textContent;
      const meta = pressItem.querySelector('.press-meta').innerHTML;
      const content = pressItem.querySelector('.press-content').innerHTML;

      modalContent.innerHTML = `
        <h3>${title}</h3>
        <div class="press-meta">${meta}</div>
        <div class="press-content">${content}</div>
      `;

      modal.style.display = 'block';
    });
  });

  closeBtn.addEventListener('click', function() {
    modal.style.display = 'none';
  });

  window.addEventListener('click', function(event) {
    if (event.target === modal) {
      modal.style.display = 'none';
    }
  });
});
</script>

<?php get_footer(); ?>
