<?php
/**
 * Template Name: サービス
 */

get_header();
?>

<div class="team-dashboard-container page-service">
  <!-- ダッシュボードヘッダー -->
  <div class="dashboard-header">
    <h1>Ainyサービス</h1>
    <p>スポーツチーム運営をサポートする総合プラットフォーム</p>
  </div>

  <!-- サービス概要セクション -->
  <section class="dashboard-section">
    <h2>サービス概要</h2>
    <div class="main-content-area">
      <div class="service-overview">
        <p>TUNAGERUは、スポーツチームの運営を効率化し、チームメンバー間のコミュニケーションを円滑にするための総合プラットフォームです。スケジュール管理からマッチング、チーム管理まで、チーム運営に必要な機能を一つのサービスで提供します。</p>
      </div>

      <!-- 主要機能 -->
      <div class="features-section">
        <h3>主要機能</h3>
        <div class="features-grid">
          <div class="feature-card">
            <div class="feature-icon"><?php echo aidunite_render_theme_icon('calendar_month', ['width' => '40', 'height' => '40']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
            <h4>スケジュール管理</h4>
            <p>練習や試合のスケジュールを簡単に作成・管理。メンバーへの通知や出欠確認も自動化できます。</p>
            <ul>
              <li>カレンダー形式での視覚的な管理</li>
              <li>自動通知機能</li>
              <li>出欠確認の自動集計</li>
              <li>繰り返しスケジュールの設定</li>
            </ul>
          </div>

          <div class="feature-card">
            <div class="feature-icon"><?php echo aidunite_render_theme_icon('handshake', ['width' => '40', 'height' => '40']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
            <h4>マッチング機能</h4>
            <p>対戦相手を簡単に探すことができます。地域やレベルに応じた検索で、最適な相手を見つけましょう。</p>
            <ul>
              <li>地域・レベル別検索</li>
              <li>マッチリクエスト機能</li>
              <li>対戦履歴の管理</li>
              <li>評価・レビューシステム</li>
            </ul>
          </div>

          <div class="feature-card">
            <div class="feature-icon"><?php echo aidunite_render_theme_icon('group', ['width' => '40', 'height' => '40']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
            <h4>チーム管理</h4>
            <p>チームメンバーの情報管理や権限設定を一元化。チーム運営を効率的に行えます。</p>
            <ul>
              <li>メンバー情報の管理</li>
              <li>権限レベルの設定</li>
              <li>チーム統計の表示</li>
              <li>メンバー招待機能</li>
            </ul>
          </div>

          <div class="feature-card">
            <div class="feature-icon"><?php echo aidunite_render_theme_icon('chat', ['width' => '40', 'height' => '40']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
            <h4>コミュニケーション</h4>
            <p>チーム内での連絡を円滑に。メッセージ機能や通知システムで、重要な情報を確実に共有できます。</p>
            <ul>
              <li>チーム内メッセージ</li>
              <li>重要通知の配信</li>
              <li>ファイル共有機能</li>
              <li>リアルタイム通知</li>
            </ul>
          </div>

          <div class="feature-card">
            <div class="feature-icon"><?php echo aidunite_render_theme_icon('bar_chart_4_bars', ['width' => '40', 'height' => '40']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
            <h4>分析・レポート</h4>
            <p>チームの活動状況や成果をデータで可視化。改善点を見つけ、チームの成長をサポートします。</p>
            <ul>
              <li>活動統計の表示</li>
              <li>出席率の分析</li>
              <li>対戦結果の記録</li>
              <li>成長レポートの生成</li>
            </ul>
          </div>

          <div class="feature-card">
            <div class="feature-icon"><?php echo aidunite_render_theme_icon('notification_add', ['width' => '40', 'height' => '40']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
            <h4>通知システム</h4>
            <p>重要な情報を確実にお届け。カスタマイズ可能な通知設定で、必要な情報だけを受け取れます。</p>
            <ul>
              <li>カスタマイズ可能な通知</li>
              <li>メール・プッシュ通知</li>
              <li>緊急連絡機能</li>
              <li>通知履歴の管理</li>
            </ul>
          </div>
        </div>
      </div>

      <!-- 料金プラン -->
      <div class="pricing-section">
        <h3><?php echo aidunite_render_theme_icon('currency_yen', ['width' => '22', 'height' => '22'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> 料金プラン</h3>
        <div class="pricing-grid">
          <div class="pricing-card">
            <div class="pricing-header">
              <h4>無料プラン</h4>
              <div class="price">¥0<span>/月</span></div>
            </div>
            <ul class="pricing-features">
              <li><?php echo aidunite_render_theme_icon('check', ['width' => '16', 'height' => '16'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> 基本スケジュール管理</li>
              <li><?php echo aidunite_render_theme_icon('check', ['width' => '16', 'height' => '16'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> チームメンバー管理（最大10名）</li>
              <li><?php echo aidunite_render_theme_icon('check', ['width' => '16', 'height' => '16'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> 基本通知機能</li>
              <li><?php echo aidunite_render_theme_icon('check', ['width' => '16', 'height' => '16'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> マッチング機能（月5回まで）</li>
              <li><?php echo aidunite_render_theme_icon('check', ['width' => '16', 'height' => '16'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> 基本レポート</li>
            </ul>
            <div class="pricing-cta">
              <a href="<?php echo home_url('/member-register'); ?>" class="btn btn-outline">無料で始める</a>
            </div>
          </div>

          <div class="pricing-card featured">
            <div class="pricing-badge">人気</div>
            <div class="pricing-header">
              <h4>スタンダード</h4>
              <div class="price">¥1,000<span>/月</span></div>
            </div>
            <ul class="pricing-features">
              <li><?php echo aidunite_render_theme_icon('check', ['width' => '16', 'height' => '16'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> 無料プランの全機能</li>
              <li><?php echo aidunite_render_theme_icon('check', ['width' => '16', 'height' => '16'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> チームメンバー管理（最大50名）</li>
              <li><?php echo aidunite_render_theme_icon('check', ['width' => '16', 'height' => '16'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> 高度なスケジュール管理</li>
              <li><?php echo aidunite_render_theme_icon('check', ['width' => '16', 'height' => '16'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> 無制限マッチング</li>
              <li><?php echo aidunite_render_theme_icon('check', ['width' => '16', 'height' => '16'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> 詳細分析レポート</li>
              <li><?php echo aidunite_render_theme_icon('check', ['width' => '16', 'height' => '16'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> 優先サポート</li>
              <li><?php echo aidunite_render_theme_icon('check', ['width' => '16', 'height' => '16'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> カスタム通知設定</li>
            </ul>
            <div class="pricing-cta">
              <a href="<?php echo home_url('/member-register'); ?>" class="btn btn-primary">スタンダードを選択</a>
            </div>
          </div>

          <div class="pricing-card">
            <div class="pricing-header">
              <h4>プレミアム</h4>
              <div class="price">¥2,000<span>/月</span></div>
            </div>
            <ul class="pricing-features">
              <li><?php echo aidunite_render_theme_icon('check', ['width' => '16', 'height' => '16'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> スタンダードの全機能</li>
              <li><?php echo aidunite_render_theme_icon('check', ['width' => '16', 'height' => '16'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> チームメンバー管理（無制限）</li>
              <li><?php echo aidunite_render_theme_icon('check', ['width' => '16', 'height' => '16'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> 高度な分析ツール</li>
              <li><?php echo aidunite_render_theme_icon('check', ['width' => '16', 'height' => '16'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> API連携</li>
              <li><?php echo aidunite_render_theme_icon('check', ['width' => '16', 'height' => '16'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> 専任サポート</li>
              <li><?php echo aidunite_render_theme_icon('check', ['width' => '16', 'height' => '16'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> カスタム機能開発</li>
              <li><?php echo aidunite_render_theme_icon('check', ['width' => '16', 'height' => '16'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> ホワイトラベル対応</li>
            </ul>
            <div class="pricing-cta">
              <a href="<?php echo home_url('/member-register'); ?>" class="btn btn-outline">プレミアムを選択</a>
            </div>
          </div>
        </div>
      </div>

      <!-- 利用シーン -->
      <div class="use-cases-section">
        <h3><?php echo aidunite_render_theme_icon('campaign', ['width' => '22', 'height' => '22'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> 利用シーン</h3>
        <div class="use-cases-grid">
          <div class="use-case-card">
            <div class="use-case-icon"><?php echo aidunite_render_theme_icon('exercise', ['width' => '36', 'height' => '36']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
            <h4>サッカーチーム</h4>
            <p>練習スケジュールの管理、対戦相手の検索、チーム内連絡の一元化</p>
          </div>

          <div class="use-case-card">
            <div class="use-case-icon"><?php echo aidunite_render_theme_icon('basketball', ['width' => '36', 'height' => '36']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
            <h4>バスケットボールチーム</h4>
            <p>リーグ戦のスケジュール管理、選手の出欠確認、試合結果の記録</p>
          </div>

          <div class="use-case-card">
            <div class="use-case-icon"><?php echo aidunite_render_theme_icon('exercise', ['width' => '36', 'height' => '36']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
            <h4>バレーボールチーム</h4>
            <p>練習メニューの共有、大会参加の調整、チーム内コミュニケーション</p>
          </div>

          <div class="use-case-card">
            <div class="use-case-icon"><?php echo aidunite_render_theme_icon('exercise', ['width' => '36', 'height' => '36']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
            <h4>卓球チーム</h4>
            <p>練習時間の調整、対戦相手のマッチング、技術向上の記録</p>
          </div>

          <div class="use-case-card">
            <div class="use-case-icon"><?php echo aidunite_render_theme_icon('exercise', ['width' => '36', 'height' => '36']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
            <h4>バドミントンチーム</h4>
            <p>ダブルスペアの組み合わせ、練習スケジュールの最適化</p>
          </div>

          <div class="use-case-card">
            <div class="use-case-icon"><?php echo aidunite_render_theme_icon('exercise', ['width' => '36', 'height' => '36']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
            <h4>テニスチーム</h4>
            <p>コート予約の管理、練習相手のマッチング、技術分析</p>
          </div>
        </div>
      </div>

      <!-- お客様の声 -->
      <div class="testimonials-section">
        <h3><?php echo aidunite_render_theme_icon('chat', ['width' => '22', 'height' => '22'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> お客様の声</h3>
        <div class="testimonials-grid">
          <div class="testimonial-card">
            <div class="testimonial-content">
              <p>「TUNAGERUを使い始めてから、チーム運営が格段に楽になりました。スケジュール管理や連絡が自動化され、メンバー全員が参加しやすくなりました。」</p>
            </div>
            <div class="testimonial-author">
              <div class="author-info">
                <strong>田中さん</strong>
                <span>サッカーチーム コーチ</span>
              </div>
            </div>
          </div>

          <div class="testimonial-card">
            <div class="testimonial-content">
              <p>「対戦相手を探すのが簡単になりました。地域のチームとの交流も増え、チームのレベル向上につながっています。」</p>
            </div>
            <div class="testimonial-author">
              <div class="author-info">
                <strong>佐藤さん</strong>
                <span>バスケットボールチーム キャプテン</span>
              </div>
            </div>
          </div>

          <div class="testimonial-card">
            <div class="testimonial-content">
              <p>「練習の出欠確認が自動化され、メンバーの参加率が向上しました。チームの一体感も高まっています。」</p>
            </div>
            <div class="testimonial-author">
              <div class="author-info">
                <strong>鈴木さん</strong>
                <span>バレーボールチーム マネージャー</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- CTA -->
      <div class="cta-section">
        <h3><?php echo aidunite_render_theme_icon('start', ['width' => '22', 'height' => '22'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> 今すぐ始めましょう</h3>
        <p>TUNAGERUで、あなたのチーム運営を次のレベルへ</p>
        <div class="cta-buttons">
          <a href="<?php echo home_url('/member-register'); ?>" class="btn btn-primary btn-lg">無料で始める</a>
          <a href="<?php echo home_url('/about'); ?>" class="btn btn-outline btn-lg">詳しく見る</a>
        </div>
      </div>
    </div>
  </section>
</div>

<style>
.service-overview {
  background: #f8f9fa;
  padding: 25px;
  border-radius: 12px;
  margin-bottom: 30px;
  border-left: 4px solid var(--primary-color);
}

.service-overview p {
  margin: 0;
  color: #495057;
  font-size: 1.1rem;
  line-height: 1.6;
}

.features-section,
.pricing-section,
.use-cases-section,
.testimonials-section,
.cta-section {
  margin-bottom: 40px;
}

.features-section h3,
.pricing-section h3,
.use-cases-section h3,
.testimonials-section h3,
.cta-section h3 {
  color: #495057;
  font-size: 1.5rem;
  margin-bottom: 25px;
  text-align: center;
}

.features-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 25px;
}

.feature-card {
  background: white;
  padding: 25px;
  border-radius: 12px;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
  transition: all 0.3s ease;
}

.feature-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
}

.feature-icon {
  font-size: 3rem;
  margin-bottom: 15px;
  text-align: center;
}

.feature-card h4 {
  color: #495057;
  font-size: 1.2rem;
  margin-bottom: 15px;
  text-align: center;
}

.feature-card p {
  color: var(--text-muted);
  margin-bottom: 15px;
  line-height: 1.6;
}

.feature-card ul {
  list-style: none;
  padding: 0;
  margin: 0;
}

.feature-card li {
  color: #495057;
  padding: 5px 0;
  border-bottom: 1px solid var(--border-light);
}

.feature-card li:last-child {
  border-bottom: none;
}

.pricing-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 25px;
  max-width: 1000px;
  margin: 0 auto;
}

.pricing-card {
  background: white;
  border-radius: 12px;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
  padding: 30px;
  position: relative;
  transition: all 0.3s ease;
}

.pricing-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
}

.pricing-card.featured {
  border: 2px solid #667eea;
  transform: scale(1.05);
}

.pricing-badge {
  position: absolute;
  top: -10px;
  right: 20px;
  background: var(--primary-color);
  color: white;
  padding: 5px 15px;
  border-radius: 20px;
  font-size: 0.8rem;
  font-weight: 600;
}

.pricing-header {
  text-align: center;
  margin-bottom: 25px;
}

.pricing-header h4 {
  color: #495057;
  font-size: 1.3rem;
  margin-bottom: 10px;
}

.price {
  font-size: 2.5rem;
  font-weight: 700;
  color: var(--primary-color);
}

.price span {
  font-size: 1rem;
  color: var(--text-muted);
}

.pricing-features {
  list-style: none;
  padding: 0;
  margin-bottom: 25px;
}

.pricing-features li {
  padding: 8px 0;
  color: #495057;
  border-bottom: 1px solid var(--border-light);
}

.pricing-features li:last-child {
  border-bottom: none;
}

.pricing-cta {
  text-align: center;
}

.use-cases-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 20px;
}

.use-case-card {
  background: white;
  padding: 25px;
  border-radius: 12px;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
  text-align: center;
  transition: all 0.3s ease;
}

.use-case-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
}

.use-case-icon {
  font-size: 3rem;
  margin-bottom: 15px;
}

.use-case-card h4 {
  color: #495057;
  font-size: 1.2rem;
  margin-bottom: 10px;
}

.use-case-card p {
  color: var(--text-muted);
  line-height: 1.6;
  margin: 0;
}

.testimonials-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 25px;
}

.testimonial-card {
  background: white;
  padding: 25px;
  border-radius: 12px;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
}

.testimonial-content {
  margin-bottom: 20px;
}

.testimonial-content p {
  color: #495057;
  font-style: italic;
  line-height: 1.6;
  margin: 0;
}

.testimonial-author {
  border-top: 1px solid var(--border-light);
  padding-top: 15px;
}

.author-info strong {
  color: #495057;
  display: block;
  margin-bottom: 5px;
}

.author-info span {
  color: var(--text-muted);
  font-size: 0.9rem;
}

.cta-section {
  text-align: center;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  padding: 40px;
  border-radius: 12px;
  margin-top: 40px;
}

.cta-section h3 {
  color: white;
  margin-bottom: 15px;
}

.cta-section p {
  font-size: 1.1rem;
  margin-bottom: 25px;
  opacity: 0.9;
}

.cta-buttons {
  display: flex;
  gap: 15px;
  justify-content: center;
  flex-wrap: wrap;
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
  background: white;
  color: #667eea;
}

.btn-primary:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 15px rgba(255, 255, 255, 0.3);
}

.btn-outline {
  background: transparent;
  color: white;
  border: 2px solid white;
}

.btn-outline:hover {
  background: white;
  color: #667eea;
  transform: translateY(-2px);
}

.btn-lg {
  padding: 15px 40px;
  font-size: 1.1rem;
}

@media (max-width: 768px) {
  .features-grid,
  .pricing-grid,
  .use-cases-grid,
  .testimonials-grid {
    grid-template-columns: 1fr;
  }

  .pricing-card.featured {
    transform: none;
  }

  .cta-buttons {
    flex-direction: column;
    align-items: center;
  }

  .cta-section {
    padding: 30px 20px;
  }
}


</style>

<?php get_footer(); ?>
