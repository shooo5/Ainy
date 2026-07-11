<?php
/**
 * Template Name: Cookieポリシー
 */

get_header();
?>

<div class="team-dashboard-container page-cookie-policy">
  <!-- ダッシュボードヘッダー -->
  <div class="dashboard-header">
    <h1>Cookieポリシー</h1>
    <p>TUNAGERUにおけるCookieの使用について</p>
  </div>

  <!-- Cookieポリシーセクション -->
  <section class="dashboard-section">
    <h2>📋 Cookieポリシー</h2>
    <div class="main-content-area">
      <div class="cookie-container">
        <div class="cookie-intro">
          <p>TUNAGERU（以下「当社」）は、お客様により良いサービスを提供するため、Cookieを使用しています。本ポリシーでは、当社がどのようにCookieを使用し、お客様がどのようにCookieを管理できるかについて説明します。</p>
          <p class="cookie-date">制定日：2024年1月1日<br>最終更新日：2024年1月1日</p>
        </div>

        <!-- Cookieとは -->
        <div class="cookie-section">
          <h3>Cookieとは</h3>
          <p>Cookieは、お客様がウェブサイトを訪問した際に、お客様のデバイス（コンピュータ、スマートフォン、タブレットなど）に保存される小さなテキストファイルです。Cookieは、お客様の設定やログイン情報を記憶し、より快適なウェブサイト体験を提供するために使用されます。</p>
        </div>

        <!-- Cookieの種類 -->
        <div class="cookie-section">
          <h3>当社が使用するCookieの種類</h3>

          <div class="cookie-type">
            <h4>1. 必須Cookie（Essential Cookies）</h4>
            <p>ウェブサイトの基本的な機能を提供するために必要なCookieです。これらのCookieがないと、ウェブサイトが正常に動作しません。</p>
            <div class="cookie-table">
              <table>
                <thead>
                  <tr>
                    <th>Cookie名</th>
                    <th>目的</th>
                    <th>保存期間</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td>session_id</td>
                    <td>セッション管理、ログイン状態の維持</td>
                    <td>ブラウザを閉じるまで</td>
                  </tr>
                  <tr>
                    <td>csrf_token</td>
                    <td>セキュリティ保護（CSRF攻撃防止）</td>
                    <td>ブラウザを閉じるまで</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <div class="cookie-type">
            <h4>2. 機能Cookie（Functional Cookies）</h4>
            <p>お客様の設定や選択を記憶し、よりパーソナライズされた体験を提供するためのCookieです。</p>
            <div class="cookie-table">
              <table>
                <thead>
                  <tr>
                    <th>Cookie名</th>
                    <th>目的</th>
                    <th>保存期間</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td>language</td>
                    <td>言語設定の記憶</td>
                    <td>1年</td>
                  </tr>
                  <tr>
                    <td>theme</td>
                    <td>テーマ設定の記憶</td>
                    <td>1年</td>
                  </tr>
                  <tr>
                    <td>notifications</td>
                    <td>通知設定の記憶</td>
                    <td>1年</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <div class="cookie-type">
            <h4>3. 分析Cookie（Analytics Cookies）</h4>
            <p>ウェブサイトの利用状況を分析し、サービス改善に役立てるためのCookieです。</p>
            <div class="cookie-table">
              <table>
                <thead>
                  <tr>
                    <th>Cookie名</th>
                    <th>目的</th>
                    <th>保存期間</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td>_ga</td>
                    <td>Google Analyticsによる利用統計</td>
                    <td>2年</td>
                  </tr>
                  <tr>
                    <td>_gid</td>
                    <td>Google Analyticsによるセッション識別</td>
                    <td>24時間</td>
                  </tr>
                  <tr>
                    <td>_gat</td>
                    <td>Google Analyticsのリクエスト制限</td>
                    <td>1分</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <div class="cookie-type">
            <h4>4. マーケティングCookie（Marketing Cookies）</h4>
            <p>お客様に関連性の高い広告を表示するためのCookieです。</p>
            <div class="cookie-table">
              <table>
                <thead>
                  <tr>
                    <th>Cookie名</th>
                    <th>目的</th>
                    <th>保存期間</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td>_fbp</td>
                    <td>Facebook広告の効果測定</td>
                    <td>3ヶ月</td>
                  </tr>
                  <tr>
                    <td>_fbc</td>
                    <td>Facebook広告のコンバージョン追跡</td>
                    <td>2年</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- 第三者Cookie -->
        <div class="cookie-section">
          <h3>第三者Cookie</h3>
          <p>当社は、以下の第三者サービスプロバイダーのCookieを使用しています：</p>
          <ul>
            <li><strong>Google Analytics：</strong>ウェブサイトの利用状況分析</li>
            <li><strong>Facebook Pixel：</strong>広告効果の測定と最適化</li>
            <li><strong>Stripe：</strong>決済処理の安全性確保</li>
          </ul>
          <p>これらの第三者サービスプロバイダーは、それぞれ独自のプライバシーポリシーを持っています。</p>
        </div>

        <!-- Cookieの管理 -->
        <div class="cookie-section">
          <h3>Cookieの管理方法</h3>

          <div class="management-method">
            <h4>ブラウザ設定による管理</h4>
            <p>お客様は、ブラウザの設定を変更することで、Cookieの使用を制限したり、削除したりすることができます。ただし、一部の機能が正常に動作しなくなる可能性があります。</p>

            <div class="browser-guide">
              <h5>主要ブラウザでの設定方法：</h5>
              <div class="browser-list">
                <div class="browser-item">
                  <strong>Chrome：</strong>設定 → プライバシーとセキュリティ → Cookieとその他のサイトデータ
                </div>
                <div class="browser-item">
                  <strong>Firefox：</strong>設定 → プライバシーとセキュリティ → Cookieとサイトデータ
                </div>
                <div class="browser-item">
                  <strong>Safari：</strong>環境設定 → プライバシー → Cookieとウェブサイトデータ
                </div>
                <div class="browser-item">
                  <strong>Edge：</strong>設定 → Cookieとサイトのアクセス許可
                </div>
              </div>
            </div>
          </div>

          <div class="management-method">
            <h4>TUNAGERUでの管理</h4>
            <p>当社のウェブサイトでは、Cookie設定をカスタマイズすることができます。設定は以下の方法で変更できます：</p>
            <ul>
              <li>ページ下部の「Cookie設定」リンクをクリック</li>
              <li>各Cookieカテゴリのオン/オフを切り替え</li>
              <li>設定を保存</li>
            </ul>
          </div>
        </div>

        <!-- Cookieの更新 -->
        <div class="cookie-section">
          <h3>Cookieポリシーの更新</h3>
          <p>当社は、必要に応じて本Cookieポリシーを更新する場合があります。重要な変更がある場合は、ウェブサイト上でお知らせいたします。</p>
        </div>

        <!-- お問い合わせ -->
        <div class="cookie-section">
          <h3>お問い合わせ</h3>
          <p>Cookieの使用に関するご質問やご要望がございましたら、以下までご連絡ください：</p>
          <div class="contact-info">
            <p><strong>TUNAGERU運営チーム</strong></p>
            <p>メール：<a href="mailto:cookies@tunageru.com">cookies@tunageru.com</a></p>
            <p>お問い合わせフォーム：<a href="<?php echo home_url('/feedback'); ?>">こちら</a></p>
          </div>
        </div>

        <!-- 関連リンク -->
        <div class="related-links">
          <h3>関連ページ</h3>
          <div class="links-grid">
            <a href="<?php echo home_url('/privacy-policy'); ?>" class="link-card">
              <span class="link-icon">🔒</span>
              <span class="link-text">プライバシーポリシー</span>
            </a>
            <a href="<?php echo home_url('/terms-of-service'); ?>" class="link-card">
              <span class="link-icon">📄</span>
              <span class="link-text">利用規約</span>
            </a>
            <a href="<?php echo home_url('/disclaimer'); ?>" class="link-card">
              <span class="link-icon">⚠️</span>
              <span class="link-text">免責事項</span>
            </a>
          </div>
        </div>
      </div>
    </div>
  </section>
</div>

<?php get_footer(); ?>
