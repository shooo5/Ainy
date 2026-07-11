<?php
/* Template Name: ガイド */
get_header();
?>

<div class="team-dashboard-container page-guide">
  <!-- ダッシュボードヘッダー -->
  <div class="dashboard-header">
    <h1>Ainy 使い方ガイド</h1>
    <p>Ainyの基本的な使い方をステップバイステップでご紹介します</p>
  </div>

  <!-- タブナビゲーション -->
  <div class="tab-navigation">
    <button class="tab-btn active" data-tab="quickstart">
      🚀 クイックスタート
    </button>
    <button class="tab-btn" data-tab="features">
      📖 機能別ガイド
    </button>
    <button class="tab-btn" data-tab="faq">
      ❓ よくある質問
    </button>
    <button class="tab-btn" data-tab="support">
      🆘 サポート
    </button>
  </div>

  <!-- クイックスタートタブ -->
  <section class="dashboard-section tab-content active" id="quickstart">
    <h2>🚀 クイックスタート</h2>
    <div class="main-content-area">
      <div class="quickstart-container">
        <p class="section-description">Ainyを始めるための3つのステップをご紹介します。</p>

        <div class="guide-steps">
          <div class="guide-step">
            <div class="step-number">1</div>
            <div class="step-content">
              <h3>アカウント作成</h3>
              <p>まずは無料でアカウントを作成してください。メールアドレスとパスワードだけで簡単に登録できます。</p>
              <a href="<?php echo home_url('/member-registration'); ?>" class="aidunite-btn aidunite-btn-primary">無料登録</a>
            </div>
          </div>

          <div class="guide-step">
            <div class="step-number">2</div>
            <div class="step-content">
              <h3>チーム登録</h3>
              <p>チームの代表者・コーチの方は、チーム情報を登録してください。承認後、チームメンバーを招待できます。</p>
              <a href="<?php echo home_url('/team-registration'); ?>" class="aidunite-btn aidunite-btn-secondary">チーム登録</a>
            </div>
          </div>

          <div class="guide-step">
            <div class="step-number">3</div>
            <div class="step-content">
              <h3>スケジュール管理</h3>
              <p>練習日程を登録して、自動マッチング機能を活用しましょう。他のチームとの練習試合も簡単にアレンジできます。</p>
              <a href="<?php echo home_url('/schedule-management'); ?>" class="aidunite-btn aidunite-btn-secondary">スケジュール管理</a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- 機能別ガイドタブ -->
  <section class="dashboard-section tab-content" id="features">
    <h2>📖 機能別ガイド</h2>
    <div class="main-content-area">
      <div class="features-container">
        <div class="feature-tabs">
          <button class="feature-tab-btn active" onclick="showFeatureTab('teams')">チーム管理</button>
          <button class="feature-tab-btn" onclick="showFeatureTab('schedules')">スケジュール</button>
          <button class="feature-tab-btn" onclick="showFeatureTab('matches')">マッチング</button>
          <button class="feature-tab-btn" onclick="showFeatureTab('communication')">コミュニケーション</button>
        </div>

        <div class="feature-content">
          <!-- チーム管理タブ -->
          <div id="teams" class="feature-tab-pane active">
            <h3>チーム管理機能</h3>
            <div class="feature-guide">
              <div class="feature-item">
                <h4>🏢 チーム登録</h4>
                <p>チーム名、競技種目、地域などの基本情報を登録します。承認後、チームページが公開されます。</p>
                <ul>
                  <li>チーム代表者の情報</li>
                  <li>競技種目とカテゴリ</li>
                  <li>活動地域</li>
                  <li>チーム紹介文</li>
                </ul>
              </div>

              <div class="feature-item">
                <h4>👥 メンバー管理</h4>
                <p>チームメンバーの追加・削除、役割の設定を行います。</p>
                <ul>
                  <li>選手の追加</li>
                  <li>保護者の招待</li>
                  <li>役割の設定</li>
                  <li>出欠管理</li>
                </ul>
              </div>
            </div>
          </div>

          <!-- スケジュールタブ -->
          <div id="schedules" class="feature-tab-pane">
            <h3>スケジュール管理機能</h3>
            <div class="feature-guide">
              <div class="feature-item">
                <h4>📅 練習日程登録</h4>
                <p>練習の日時、場所、内容を登録します。</p>
                <ul>
                  <li>日時と場所の設定</li>
                  <li>練習内容の記録</li>
                  <li>参加者の確認</li>
                  <li>繰り返し登録</li>
                </ul>
              </div>

              <div class="feature-item">
                <h4>🤝 マッチング機能</h4>
                <p>他のチームとの練習試合を自動でマッチングします。</p>
                <ul>
                  <li>条件に合うチームの検索</li>
                  <li>自動マッチング</li>
                  <li>試合の調整</li>
                  <li>結果の記録</li>
                </ul>
              </div>
            </div>
          </div>

          <!-- マッチングタブ -->
          <div id="matches" class="feature-tab-pane">
            <h3>マッチング機能</h3>
            <div class="feature-guide">
              <div class="feature-item">
                <h4>🔍 チーム検索</h4>
                <p>条件に合うチームを検索して、練習試合をアレンジします。</p>
                <ul>
                  <li>地域での検索</li>
                  <li>競技種目での絞り込み</li>
                  <li>レベルでのマッチング</li>
                  <li>日程での調整</li>
                </ul>
              </div>

              <div class="feature-item">
                <h4>📋 マッチ掲示板</h4>
                <p>練習試合の募集や応募を行います。</p>
                <ul>
                  <li>試合の募集投稿</li>
                  <li>応募の管理</li>
                  <li>条件の調整</li>
                  <li>結果の共有</li>
                </ul>
              </div>
            </div>
          </div>

          <!-- コミュニケーションタブ -->
          <div id="communication" class="feature-tab-pane">
            <h3>コミュニケーション機能</h3>
            <div class="feature-guide">
              <div class="feature-item">
                <h4>💬 チームチャット</h4>
                <p>チーム内での連絡や情報共有を行います。</p>
                <ul>
                  <li>リアルタイムチャット</li>
                  <li>ファイルの共有</li>
                  <li>通知機能</li>
                  <li>履歴の保存</li>
                </ul>
              </div>

              <div class="feature-item">
                <h4>📢 お知らせ機能</h4>
                <p>重要な連絡事項をメンバーに通知します。</p>
                <ul>
                  <li>お知らせの投稿</li>
                  <li>メール通知</li>
                  <li>重要度の設定</li>
                  <li>既読管理</li>
                </ul>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- よくある質問タブ -->
  <section class="dashboard-section tab-content" id="faq">
    <h2>❓ よくある質問</h2>
    <div class="main-content-area">
      <div class="faq-container">
        <div class="faq-item">
          <h3>Q. 無料で使えますか？</h3>
          <p>A. はい、基本的な機能は無料でご利用いただけます。プレミアム機能は有料プランでご利用いただけます。</p>
        </div>
        <div class="faq-item">
          <h3>Q. どのようなチームが利用できますか？</h3>
          <p>A. スポーツチーム、部活動、クラブ活動など、様々なチームでご利用いただけます。学校、クラブ、地域チームなど、規模を問わずご利用可能です。</p>
        </div>
        <div class="faq-item">
          <h3>Q. サポートはありますか？</h3>
          <p>A. お問い合わせフォームからご質問いただければ、サポートいたします。また、このガイドページでも基本的な使い方をご紹介しています。</p>
        </div>
        <div class="faq-item">
          <h3>Q. データの安全性は？</h3>
          <p>A. お客様のデータは適切に暗号化され、安全に管理されています。詳細は<a href="<?php echo home_url('/regulation'); ?>">プライバシーポリシー</a>をご確認ください。</p>
        </div>
        <div class="faq-item">
          <h3>Q. 複数のチームを管理できますか？</h3>
          <p>A. はい、複数のチームを管理することができます。各チームごとに独立したページと機能をご利用いただけます。</p>
        </div>
      </div>
    </div>
  </section>

  <!-- サポートタブ -->
  <section class="dashboard-section tab-content" id="support">
    <h2>🆘 サポート</h2>
    <div class="main-content-area">
      <div class="support-container">
        <p>ご不明な点がございましたら、お気軽にお問い合わせください。</p>
        <div class="support-actions">
          <a href="<?php echo home_url('/contact'); ?>" class="aidunite-btn aidunite-btn-primary">お問い合わせ</a>
          <a href="<?php echo home_url('/regulation'); ?>" class="aidunite-btn aidunite-btn-secondary">利用規約</a>
        </div>
      </div>
    </div>
  </section>
</div>

<?php get_footer(); ?>
