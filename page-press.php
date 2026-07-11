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

<?php get_footer(); ?>
