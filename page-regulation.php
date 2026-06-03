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

<script>
document.addEventListener('DOMContentLoaded', function() {
  // メインタブ切り替え機能
  const tabBtns = document.querySelectorAll('.tab-btn');
  const tabContents = document.querySelectorAll('.tab-content');

  tabBtns.forEach(btn => {
    btn.addEventListener('click', function() {
      const targetTab = this.getAttribute('data-tab');

      // タブボタンのアクティブ状態を切り替え
      tabBtns.forEach(b => b.classList.remove('active'));
      this.classList.add('active');

      // タブコンテンツの表示を切り替え
      tabContents.forEach(content => {
        content.classList.remove('active');
        if (content.id === targetTab) {
          content.classList.add('active');
        }
      });
    });
  });
});

// レギュレーション表示モーダル
function showRegulation(type) {
  const modal = document.getElementById('regulationModal');
  const modalTitle = document.getElementById('regulationModalTitle');
  const modalBody = document.getElementById('regulationModalBody');

  const regulationData = {
    terms: {
      title: '利用規約',
      content: `
        <h3>第1条（適用）</h3>
        <p>本規約は、AidUnite（以下「本サービス」）の利用に関する条件を定めるものです。</p>

        <h3>第2条（利用登録）</h3>
        <p>本サービスの利用を希望する者は、本規約に同意の上、当社の定める方法によって利用登録を申請するものとします。</p>

        <h3>第3条（禁止事項）</h3>
        <p>利用者は、本サービスの利用にあたり、以下の行為をしてはなりません。</p>
        <ul>
          <li>法令または公序良俗に違反する行為</li>
          <li>犯罪行為に関連する行為</li>
          <li>当社のサーバーまたはネットワークの機能を破壊したり、妨害したりする行為</li>
          <li>本サービスの運営を妨害するおそれのある行為</li>
          <li>他の利用者に関する個人情報等を収集または蓄積する行為</li>
          <li>他の利用者に成りすます行為</li>
          <li>当社のサービスに関連して、反社会的勢力に対して直接または間接に利益を供与する行為</li>
        </ul>

        <h3>第4条（本サービスの提供の停止等）</h3>
        <p>当社は、以下のいずれかの事由があると判断した場合、利用者に事前に通知することなく本サービスの全部または一部の提供を停止または中断することができるものとします。</p>
        <ul>
          <li>本サービスにかかるコンピュータシステムの保守点検または更新を行う場合</li>
          <li>地震、落雷、火災、停電または天災などの不可抗力により、本サービスの提供が困難となった場合</li>
          <li>その他、当社が本サービスの提供が困難と判断した場合</li>
        </ul>
      `
    },
    privacy: {
      title: 'プライバシーポリシー',
      content: `
        <h3>個人情報の収集について</h3>
        <p>当社は、本サービスの提供にあたり、以下の個人情報を収集いたします。</p>
        <ul>
          <li>氏名、メールアドレス、電話番号</li>
          <li>チーム情報（チーム名、活動地域、競技種目など）</li>
          <li>利用履歴、アクセスログ</li>
        </ul>

        <h3>個人情報の利用目的</h3>
        <p>収集した個人情報は、以下の目的で利用いたします。</p>
        <ul>
          <li>本サービスの提供・運営</li>
          <li>ユーザーからのお問い合わせへの対応</li>
          <li>サービスの改善・新機能の開発</li>
          <li>不正利用の防止</li>
        </ul>

        <h3>個人情報の管理</h3>
        <p>当社は、お客さまの個人情報を正確かつ安全に管理し、以下のいずれかに該当する場合を除き、個人情報を第三者に開示いたしません。</p>
        <ul>
          <li>お客さまの同意がある場合</li>
          <li>お客さまが希望されるサービスを行なうために当社が業務を委託する業者に対して開示する場合</li>
          <li>法令に基づき開示することが必要である場合</li>
        </ul>

        <h3>個人情報の訂正・削除</h3>
        <p>お客さまが当社にご登録いただいた個人情報について、ご本人からの開示・訂正・削除のご要望があった場合、速やかに対応いたします。</p>
      `
    },
    guidelines: {
      title: 'コミュニティガイドライン',
      content: `
        <h3>コミュニティガイドライン</h3>
        <p>私たちは、すべてのユーザーが安全で快適にAidUniteを利用できるよう、以下のガイドラインを定めています。</p>

        <h3>1. 相互尊重</h3>
        <p>他のユーザーを尊重し、差別的な発言や攻撃的な態度を避けてください。</p>

        <h3>2. 適切なコミュニケーション</h3>
        <p>建設的で有益なコミュニケーションを心がけ、スパムや不適切な宣伝は避けてください。</p>

        <h3>3. プライバシーの尊重</h3>
        <p>他のユーザーの個人情報を無断で公開したり、共有したりしないでください。</p>

        <h3>4. 安全な活動</h3>
        <p>スポーツ活動やイベント参加時は、安全を最優先に考え、適切な準備と注意を払ってください。</p>

        <h3>5. 報告と対応</h3>
        <p>ガイドライン違反を発見した場合は、すぐに報告してください。適切な対応を行います。</p>
      `
    },
    safety: {
      title: '安全・セキュリティポリシー',
      content: `
        <h3>セキュリティ対策</h3>
        <p>当社は、ユーザーの情報とプラットフォームの安全性を最優先に考え、包括的なセキュリティ対策を実施しています。</p>

        <h3>1. データ暗号化</h3>
        <p>すべての個人情報と通信データは、業界標準の暗号化技術（SSL/TLS）で保護されています。</p>

        <h3>2. アクセス制御</h3>
        <p>システムへのアクセスは、必要最小限の権限を持つ担当者のみに限定されています。</p>

        <h3>3. 定期的なセキュリティ監査</h3>
        <p>外部専門機関による定期的なセキュリティ監査を実施し、脆弱性の早期発見と修正を行っています。</p>

        <h3>4. インシデント対応</h3>
        <p>セキュリティインシデントが発生した場合の迅速な対応体制を整備しています。</p>

        <h3>ユーザーの安全対策</h3>
        <ul>
          <li>強力なパスワードの使用</li>
          <li>二段階認証の有効活用</li>
          <li>不審なリンクやファイルの注意</li>
          <li>定期的なパスワード変更</li>
        </ul>
      `
    },
    payment: {
      title: '決済・料金規約',
      content: `
        <h3>料金体系</h3>
        <p>AidUniteの料金体系について説明いたします。</p>

        <h3>1. 基本プラン（無料）</h3>
        <ul>
          <li>基本的なチーム管理機能</li>
          <li>スケジュール管理</li>
          <li>コミュニティ参加</li>
        </ul>

        <h3>2. プレミアムプラン（月額1,980円）</h3>
        <ul>
          <li>高度な分析機能</li>
          <li>優先サポート</li>
          <li>広告非表示</li>
          <li>カスタム機能</li>
        </ul>

        <h3>3. チームプラン（月額4,980円）</h3>
        <ul>
          <li>チーム全体の管理機能</li>
          <li>詳細な統計・レポート</li>
          <li>専任サポート</li>
        </ul>

        <h3>決済方法</h3>
        <p>以下の決済方法をご利用いただけます。</p>
        <ul>
          <li>クレジットカード（Visa、Mastercard、JCB、American Express）</li>
          <li>デビットカード</li>
          <li>銀行振込</li>
        </ul>

        <h3>返金ポリシー</h3>
        <p>サービス開始後30日以内であれば、全額返金いたします。</p>
      `
    }
  };

  const regulation = regulationData[type];
  if (regulation) {
    modalTitle.textContent = regulation.title;
    modalBody.innerHTML = regulation.content;
    modal.style.display = 'block';
  }
}

function closeRegulationModal() {
  document.getElementById('regulationModal').style.display = 'none';
}

function downloadRegulation(type) {
  // PDFダウンロード機能（実装例）
  if (typeof showToastNotification !== 'undefined') {
    showToastNotification(`${type}のレギュレーションをダウンロードします。（実際の実装では、PDFファイルのダウンロード処理を行います）`, 'info');
  } else {
    alert(`${type}のレギュレーションをダウンロードします。\n（実際の実装では、PDFファイルのダウンロード処理を行います）`);
  }
}

function downloadAllRegulations() {
  // 一括ダウンロード機能（実装例）
  if (typeof showToastNotification !== 'undefined') {
    showToastNotification('すべてのレギュレーションをダウンロードします。（実際の実装では、ZIPファイルのダウンロード処理を行います）', 'info');
  } else {
    alert('すべてのレギュレーションをダウンロードします。\n（実際の実装では、ZIPファイルのダウンロード処理を行います）');
  }
}

// フィルタリング機能
function filterRegulations(category) {
  const cards = document.querySelectorAll('.regulation-card');
  const keyword = document.getElementById('keyword').value.toLowerCase();

  cards.forEach(card => {
    const cardCategory = card.getAttribute('data-category');
    const cardTitle = card.querySelector('.regulation-card-title').textContent.toLowerCase();
    const cardDescription = card.querySelector('.regulation-card-description').textContent.toLowerCase();

    const categoryMatch = !category || cardCategory === category;
    const keywordMatch = !keyword || cardTitle.includes(keyword) || cardDescription.includes(keyword);

    if (categoryMatch && keywordMatch) {
      card.style.display = 'block';
    } else {
      card.style.display = 'none';
    }
  });
}

// 検索機能
function searchRegulations(keyword) {
  const category = document.getElementById('category').value;
  filterRegulations(category);
}

// モーダル外クリックで閉じる
window.onclick = function(event) {
  const modal = document.getElementById('regulationModal');
  if (event.target === modal) {
    closeRegulationModal();
  }
}
</script>

<style>
.regulation-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
  gap: 20px;
  max-width: 1200px;
  margin: 0 auto;
}

.regulation-card {
  background: white;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
  overflow: hidden;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.regulation-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}

.regulation-card-header {
  padding: 20px;
  border-bottom: 1px solid var(--border-light);
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.regulation-card-title {
  margin: 0;
  color: var(--text-primary);
  font-size: 1.2rem;
}

.regulation-badge {
  padding: 4px 8px;
  border-radius: 12px;
  font-size: 0.8rem;
  font-weight: 600;
}

.regulation-badge.terms {
  background: rgba(23, 162, 184, 0.1);
  color: var(--info-color);
}

.regulation-badge.privacy {
  background: rgba(177, 108, 234, 0.1);
  color: var(--secondary-color);
}

.regulation-badge.guidelines {
  background: rgba(40, 167, 69, 0.1);
  color: var(--success-color);
}

.regulation-badge.safety {
  background: rgba(255, 193, 7, 0.1);
  color: var(--warning-color);
}

.regulation-badge.payment {
  background: rgba(220, 53, 69, 0.1);
  color: var(--danger-color);
}

.regulation-card-body {
  padding: 20px;
}

.regulation-card-info {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
  gap: 15px;
  margin-bottom: 15px;
}

.regulation-card-info-item {
  display: flex;
  flex-direction: column;
  gap: 5px;
}

.regulation-card-info-label {
  font-size: 0.8rem;
  color: var(--text-secondary);
  font-weight: 500;
}

.regulation-card-info-value {
  font-size: 0.9rem;
  color: var(--text-primary);
  font-weight: 600;
}

.regulation-card-description {
  color: var(--text-secondary);
  line-height: 1.5;
  margin-bottom: 20px;
}

.regulation-card-actions {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}

.btn-view-details,
.btn-download {
  padding: 8px 16px;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  text-decoration: none;
  font-size: 0.9rem;
  font-weight: 500;
  transition: all 0.2s ease;
}

.btn-view-details {
  background: var(--primary-color);
  color: white;
}

.btn-view-details:hover {
  background: var(--primary-dark);
}

.btn-download {
  background: var(--success-color);
  color: white;
}

.btn-download:hover {
  background: var(--success-color);
}

/* 検索・フィルター */
.search-container {
  max-width: 800px;
  margin: 0 auto;
}

.regulation-filters {
  background: white;
  padding: 20px;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
  margin-bottom: 30px;
}

.filter-row {
  display: flex;
  gap: 20px;
  align-items: center;
  flex-wrap: wrap;
}

.filter-group {
  display: flex;
  flex-direction: column;
  gap: 5px;
}

.filter-group label {
  font-weight: 600;
  color: var(--text-primary);
  font-size: 0.9rem;
}

.filter-group select,
.filter-group input {
  padding: 8px 12px;
  border: 1px solid var(--border-color);
  border-radius: 4px;
  font-size: 0.9rem;
}

/* ダウンロード */
.downloads-container {
  max-width: 800px;
  margin: 0 auto;
}

.section-description {
  text-align: center;
  margin-bottom: 2rem;
  color: var(--text-secondary);
  font-size: 1.1rem;
}

.download-options {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 2rem;
}

.download-option {
  background: var(--bg-secondary);
  padding: 2rem;
  border-radius: 8px;
  border: 1px solid var(--border-light);
  text-align: center;
}

.download-option h3 {
  margin: 0 0 1rem 0;
  color: var(--text-primary);
}

.download-option p {
  margin: 0 0 1.5rem 0;
  color: var(--text-secondary);
}

.download-buttons {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

/* モーダル */
.regulation-modal {
  display: none;
  position: fixed;
  z-index: 1000;
  left: 0;
  top: 0;
  width: 100%;
  height: 100%;
  background-color: rgba(0,0,0,0.5);
}

.regulation-modal-content {
  background-color: white;
  margin: 5% auto;
  padding: 0;
  border-radius: 8px;
  width: 90%;
  max-width: 800px;
  max-height: 80vh;
  overflow-y: auto;
  position: relative;
}

.regulation-modal-header {
  padding: 20px;
  border-bottom: 1px solid var(--border-light);
  display: flex;
  justify-content: space-between;
  align-items: center;
  position: sticky;
  top: 0;
  background: white;
  z-index: 1;
}

.regulation-modal-header h2 {
  margin: 0;
  color: var(--text-primary);
}

.modal-close {
  background: none;
  border: none;
  font-size: 24px;
  cursor: pointer;
  color: var(--text-secondary);
  padding: 0;
  width: 30px;
  height: 30px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.modal-close:hover {
  color: var(--text-primary);
}

.regulation-modal-body {
  padding: 20px;
  line-height: 1.6;
}

.regulation-modal-body h3 {
  color: var(--text-primary);
  margin: 1.5rem 0 0.5rem 0;
  border-bottom: 2px solid var(--primary-color);
  padding-bottom: 0.5rem;
}

.regulation-modal-body h3:first-child {
  margin-top: 0;
}

.regulation-modal-body p {
  margin: 0.5rem 0;
  color: var(--text-secondary);
}

.regulation-modal-body ul {
  margin: 0.5rem 0;
  padding-left: 1.5rem;
}

.regulation-modal-body li {
  margin-bottom: 0.25rem;
  color: var(--text-secondary);
}

@media (max-width: 768px) {
  .regulation-grid {
    grid-template-columns: 1fr;
  }

  .regulation-card-info {
    grid-template-columns: 1fr;
  }

  .regulation-card-actions {
    flex-direction: column;
  }

  .filter-row {
    flex-direction: column;
    align-items: stretch;
  }

  .download-options {
    grid-template-columns: 1fr;
  }

  .regulation-modal-content {
    width: 95%;
    margin: 10% auto;
  }
}
</style>

<?php get_footer(); ?>
