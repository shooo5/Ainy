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

      <!-- 料金プラン（Match / Club — payment-config 正本） -->
      <?php
      $payment_config  = function_exists('aidunite_get_payment_config') ? aidunite_get_payment_config() : [];
      $match_monthly   = (int) ($payment_config['match']['monthly_amount'] ?? 2000);
      $club_minimum    = (int) ($payment_config['club']['minimum_addon'] ?? 6000);
      $club_per_player = (int) ($payment_config['club']['per_player_amount'] ?? 500);
      ?>
      <div class="pricing-section pricing-section--match-club">
        <h3><?php echo aidunite_render_theme_icon('currency_yen', ['width' => '22', 'height' => '22'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> 料金プラン</h3>
        <p class="pricing-section__intro">
          <strong>アカウント登録・チーム申請は無料</strong>です。チーム承認後、代表者向け機能の本格利用に Match プラン（2ヶ月無料）をご案内します。クラブ運営向けの機能は Club プランでご利用いただけます。
        </p>
        <div class="pricing-grid pricing-grid--match-club">
          <div class="pricing-card">
            <div class="pricing-header">
              <h4>Match プラン</h4>
              <div class="price">¥<?php echo esc_html(number_format($match_monthly)); ?><span>/月（税込）</span></div>
              <p class="pricing-card__trial">2ヶ月無料</p>
            </div>
            <ul class="pricing-features">
              <li><?php echo aidunite_render_theme_icon('check', ['width' => '16', 'height' => '16'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> 練習試合の募集・マッチボード</li>
              <li><?php echo aidunite_render_theme_icon('check', ['width' => '16', 'height' => '16'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> スケジュール管理</li>
              <li><?php echo aidunite_render_theme_icon('check', ['width' => '16', 'height' => '16'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> チーム連絡（チャット）</li>
              <li><?php echo aidunite_render_theme_icon('check', ['width' => '16', 'height' => '16'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> 代表者向けマイページ</li>
            </ul>
          </div>

          <div class="pricing-card featured">
            <div class="pricing-badge">クラブ運営</div>
            <div class="pricing-header">
              <h4>Club プラン</h4>
              <div class="price">¥<?php echo esc_html(number_format($club_minimum)); ?><span>〜/月（税込）</span></div>
              <p class="pricing-card__trial">Match 機能＋クラブ運営（2ヶ月無料）</p>
            </div>
            <ul class="pricing-features">
              <li><?php echo aidunite_render_theme_icon('check', ['width' => '16', 'height' => '16'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> Match プランの全機能</li>
              <li><?php echo aidunite_render_theme_icon('check', ['width' => '16', 'height' => '16'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> メンバー管理・保護者招待</li>
              <li><?php echo aidunite_render_theme_icon('check', ['width' => '16', 'height' => '16'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> 出欠管理・出欠連絡</li>
              <li><?php echo aidunite_render_theme_icon('check', ['width' => '16', 'height' => '16'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> 人数に応じた料金（<?php echo esc_html(number_format($club_per_player)); ?>円/人 等）</li>
            </ul>
          </div>
        </div>
        <p class="pricing-section__note">料金・機能は変更になる場合があります。最新情報は利用規約およびお支払い設定画面をご確認ください。</p>
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

<?php get_footer(); ?>
