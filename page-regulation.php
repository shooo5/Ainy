<?php
/**
 * Template Name: レギュレーションページ
 */

get_header();
?>

<div class="team-dashboard-container page-regulation">
  <!-- ダッシュボードヘッダー -->
  <div class="dashboard-header">
    <h1>レギュレーション</h1>
    <p>AidUniteプラットフォームの利用規約、プライバシーポリシー、各種ガイドラインをご確認いただけます。</p>
  </div>

  <!-- タブナビゲーション -->
  <div class="tab-navigation">
    <button class="tab-btn active" data-tab="regulations">
      📄 レギュレーション一覧
    </button>
    <button class="tab-btn" data-tab="search">
      🔍 検索・フィルター
    </button>
    <button class="tab-btn" data-tab="downloads">
      📥 ダウンロード
    </button>
  </div>

  <!-- レギュレーション一覧タブ -->
  <section class="dashboard-section tab-content active" id="regulations">
    <h2>📄 レギュレーション一覧</h2>
    <div class="main-content-area">
      <div class="regulation-grid">
        <!-- 利用規約 -->
        <div class="regulation-card" data-category="terms">
          <div class="regulation-card-header">
            <h3 class="regulation-card-title">📄 利用規約</h3>
            <span class="regulation-badge terms">利用規約</span>
          </div>

          <div class="regulation-card-body">
            <div class="regulation-card-info">
              <div class="regulation-card-info-item">
                <span class="regulation-card-info-label">更新日</span>
                <span class="regulation-card-info-value">2024年1月15日</span>
              </div>
              <div class="regulation-card-info-item">
                <span class="regulation-card-info-label">バージョン</span>
                <span class="regulation-card-info-value">v2.1</span>
              </div>
              <div class="regulation-card-info-item">
                <span class="regulation-card-info-label">重要度</span>
                <span class="regulation-card-info-value">🔴 必須確認</span>
              </div>
            </div>

            <div class="regulation-card-description">
              AidUniteプラットフォームの利用に関する基本規約です。サービス利用前に必ずご確認ください。
            </div>

            <div class="regulation-card-actions">
              <a href="#" class="btn-view-details" onclick="showRegulation('terms')">
                詳細を見る
              </a>
              <a href="#" class="btn-download" onclick="downloadRegulation('terms')">
                📥 PDFダウンロード
              </a>
            </div>
          </div>
        </div>

        <!-- プライバシーポリシー -->
        <div class="regulation-card" data-category="privacy">
          <div class="regulation-card-header">
            <h3 class="regulation-card-title">🔒 プライバシーポリシー</h3>
            <span class="regulation-badge privacy">プライバシー</span>
          </div>

          <div class="regulation-card-body">
            <div class="regulation-card-info">
              <div class="regulation-card-info-item">
                <span class="regulation-card-info-label">更新日</span>
                <span class="regulation-card-info-value">2024年1月10日</span>
              </div>
              <div class="regulation-card-info-item">
                <span class="regulation-card-info-label">バージョン</span>
                <span class="regulation-card-info-value">v1.8</span>
              </div>
              <div class="regulation-card-info-item">
                <span class="regulation-card-info-label">重要度</span>
                <span class="regulation-card-info-value">🔴 必須確認</span>
              </div>
            </div>

            <div class="regulation-card-description">
              お客様の個人情報の取り扱いについて定めたポリシーです。データ保護の取り組みをご確認ください。
            </div>

            <div class="regulation-card-actions">
              <a href="#" class="btn-view-details" onclick="showRegulation('privacy')">
                詳細を見る
              </a>
              <a href="#" class="btn-download" onclick="downloadRegulation('privacy')">
                📥 PDFダウンロード
              </a>
            </div>
          </div>
        </div>

        <!-- コミュニティガイドライン -->
        <div class="regulation-card" data-category="guidelines">
          <div class="regulation-card-header">
            <h3 class="regulation-card-title">🤝 コミュニティガイドライン</h3>
            <span class="regulation-badge guidelines">ガイドライン</span>
          </div>

          <div class="regulation-card-body">
            <div class="regulation-card-info">
              <div class="regulation-card-info-item">
                <span class="regulation-card-info-label">更新日</span>
                <span class="regulation-card-info-value">2024年1月20日</span>
              </div>
              <div class="regulation-card-info-item">
                <span class="regulation-card-info-label">バージョン</span>
                <span class="regulation-card-info-value">v1.5</span>
              </div>
              <div class="regulation-card-info-item">
                <span class="regulation-card-info-label">重要度</span>
                <span class="regulation-card-info-value">🟡 推奨確認</span>
              </div>
            </div>

            <div class="regulation-card-description">
              安全で快適なコミュニティを維持するためのガイドラインです。すべてのユーザーが従うべきルールです。
            </div>

            <div class="regulation-card-actions">
              <a href="#" class="btn-view-details" onclick="showRegulation('guidelines')">
                詳細を見る
              </a>
              <a href="#" class="btn-download" onclick="downloadRegulation('guidelines')">
                📥 PDFダウンロード
              </a>
            </div>
          </div>
        </div>

        <!-- 安全・セキュリティポリシー -->
        <div class="regulation-card" data-category="safety">
          <div class="regulation-card-header">
            <h3 class="regulation-card-title">🛡️ 安全・セキュリティポリシー</h3>
            <span class="regulation-badge safety">安全・セキュリティ</span>
          </div>

          <div class="regulation-card-body">
            <div class="regulation-card-info">
              <div class="regulation-card-info-item">
                <span class="regulation-card-info-label">更新日</span>
                <span class="regulation-card-info-value">2024年1月12日</span>
              </div>
              <div class="regulation-card-info-item">
                <span class="regulation-card-info-label">バージョン</span>
                <span class="regulation-card-info-value">v1.3</span>
              </div>
              <div class="regulation-card-info-item">
                <span class="regulation-card-info-label">重要度</span>
                <span class="regulation-card-info-value">🟡 推奨確認</span>
              </div>
            </div>

            <div class="regulation-card-description">
              プラットフォームの安全性とセキュリティに関する取り組みと、ユーザーが注意すべき事項について説明します。
            </div>

            <div class="regulation-card-actions">
              <a href="#" class="btn-view-details" onclick="showRegulation('safety')">
                詳細を見る
              </a>
              <a href="#" class="btn-download" onclick="downloadRegulation('safety')">
                📥 PDFダウンロード
              </a>
            </div>
          </div>
        </div>

        <!-- 決済・料金規約 -->
        <div class="regulation-card" data-category="payment">
          <div class="regulation-card-header">
            <h3 class="regulation-card-title">💳 決済・料金規約</h3>
            <span class="regulation-badge payment">決済・料金</span>
          </div>

          <div class="regulation-card-body">
            <div class="regulation-card-info">
              <div class="regulation-card-info-item">
                <span class="regulation-card-info-label">更新日</span>
                <span class="regulation-card-info-value">2024年1月18日</span>
              </div>
              <div class="regulation-card-info-item">
                <span class="regulation-card-info-label">バージョン</span>
                <span class="regulation-card-info-value">v1.2</span>
              </div>
              <div class="regulation-card-info-item">
                <span class="regulation-card-info-label">重要度</span>
                <span class="regulation-card-info-value">🟢 参考情報</span>
              </div>
            </div>

            <div class="regulation-card-description">
              有料サービスの料金体系、決済方法、返金ポリシーなど、決済に関する詳細な規約です。
            </div>

            <div class="regulation-card-actions">
              <a href="#" class="btn-view-details" onclick="showRegulation('payment')">
                詳細を見る
              </a>
              <a href="#" class="btn-download" onclick="downloadRegulation('payment')">
                📥 PDFダウンロード
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- 検索・フィルタータブ -->
  <section class="dashboard-section tab-content" id="search">
    <h2>🔍 検索・フィルター</h2>
    <div class="main-content-area">
      <div class="search-container">
        <div class="regulation-filters">
          <div class="filter-row">
            <div class="filter-group">
              <label for="category">カテゴリ</label>
              <select id="category" name="category" onchange="filterRegulations(this.value)">
                <option value="">すべて</option>
                <option value="terms">利用規約</option>
                <option value="privacy">プライバシー</option>
                <option value="guidelines">ガイドライン</option>
                <option value="safety">安全・セキュリティ</option>
                <option value="payment">決済・料金</option>
              </select>
            </div>

            <div class="filter-group">
              <label for="keyword">キーワード</label>
              <input type="text" id="keyword" name="keyword" placeholder="レギュレーション名で検索" onkeyup="searchRegulations(this.value)">
            </div>
          </div>
        </div>

        <div class="filtered-results" id="filteredResults">
          <!-- フィルタリング結果がここに表示されます -->
        </div>
      </div>
    </div>
  </section>

  <!-- ダウンロードタブ -->
  <section class="dashboard-section tab-content" id="downloads">
    <h2>📥 ダウンロード</h2>
    <div class="main-content-area">
      <div class="downloads-container">
        <p class="section-description">すべてのレギュレーションを一括ダウンロードできます。</p>

        <div class="download-options">
          <div class="download-option">
            <h3>📄 個別ダウンロード</h3>
            <p>必要なレギュレーションのみを選択してダウンロードできます。</p>
            <div class="download-buttons">
              <button class="aidunite-btn aidunite-btn-secondary" onclick="downloadRegulation('terms')">利用規約</button>
              <button class="aidunite-btn aidunite-btn-secondary" onclick="downloadRegulation('privacy')">プライバシーポリシー</button>
              <button class="aidunite-btn aidunite-btn-secondary" onclick="downloadRegulation('guidelines')">ガイドライン</button>
              <button class="aidunite-btn aidunite-btn-secondary" onclick="downloadRegulation('safety')">セキュリティポリシー</button>
              <button class="aidunite-btn aidunite-btn-secondary" onclick="downloadRegulation('payment')">決済規約</button>
            </div>
          </div>

          <div class="download-option">
            <h3>📚 一括ダウンロード</h3>
            <p>すべてのレギュレーションをまとめてダウンロードできます。</p>
            <button class="aidunite-btn aidunite-btn-primary" onclick="downloadAllRegulations()">
              📥 すべてダウンロード
            </button>
          </div>
        </div>
      </div>
    </div>
  </section>
</div>

<!-- レギュレーション詳細モーダル -->
<div id="regulationModal" class="regulation-modal" style="display: none;">
  <div class="regulation-modal-content">
    <div class="regulation-modal-header">
      <h2 id="regulationModalTitle">レギュレーション詳細</h2>
      <button class="modal-close" onclick="closeRegulationModal()">&times;</button>
    </div>
    <div class="regulation-modal-body" id="regulationModalBody">
      <!-- モーダル内容がここに表示されます -->
    </div>
  </div>
</div>

<?php get_footer(); ?>
