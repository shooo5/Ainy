/**
 * page-regulation.php
 */
(function () {
  'use strict';
  var cfg = typeof aidunitePage_regulation !== 'undefined' ? aidunitePage_regulation : {};


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
  aiduniteToast(`${type}のレギュレーションをダウンロードします。（実際の実装では、PDFファイルのダウンロード処理を行います）`, 'info');
}

function downloadAllRegulations() {
  // 一括ダウンロード機能（実装例）
  aiduniteToast('すべてのレギュレーションをダウンロードします。（実際の実装では、ZIPファイルのダウンロード処理を行います）', 'info');
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
})();
