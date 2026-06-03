/**
 * フロントページ用JavaScript
 */

document.addEventListener('DOMContentLoaded', function() {

    // 信頼獲得セクションの表示/非表示切り替え
    function toggleTrustSection() {
        const trustSection = document.querySelector('.aidunite-trust');
        if (trustSection) {
            // データがある場合は表示、ない場合は非表示
            const hasData = false; // 実際のデータチェックロジックに置き換え

            if (hasData) {
                trustSection.style.display = 'block';
            } else {
                trustSection.style.display = 'none';
            }
        }
    }

    // 信頼獲得セクションの初期化
    toggleTrustSection();

    // スムーズスクロール
    function smoothScrollTo(target) {
        const element = document.querySelector(target);
        if (element) {
            element.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    }

    // ボタンクリック時のスムーズスクロール
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = this.getAttribute('href');
            smoothScrollTo(target);
        });
    });

    // アニメーション効果
    function animateOnScroll() {
        const elements = document.querySelectorAll('.aidunite-service-card, .aidunite-ui-item, .aidunite-step-item');

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, {
            threshold: 0.1
        });

        elements.forEach(element => {
            element.style.opacity = '0';
            element.style.transform = 'translateY(20px)';
            element.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            observer.observe(element);
        });
    }

    // アニメーション初期化
    animateOnScroll();

    // 統計データの更新（将来的に実装）
    function updateStats() {
        // チーム数、ユーザー数などの統計を動的に更新
        const stats = {
            teams: 0,
            users: 0,
            matches: 0
        };

        // APIからデータを取得して更新する処理
        // 現在は仮のデータ
    }

    // 統計データの初期化
    updateStats();

    // フォーム送信処理
    function handleFormSubmission() {
        const forms = document.querySelectorAll('.aidunite-form');

        forms.forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault();

                // フォームデータの取得
                const formData = new FormData(this);

                // 送信処理（実際のAPIエンドポイントに送信）
                console.log('フォーム送信:', Object.fromEntries(formData));

                // 成功メッセージの表示
                showMessage('お問い合わせを送信しました。ありがとうございます。', 'success');
            });
        });
    }

    // メッセージ表示
    function showMessage(message, type = 'info') {
        const messageDiv = document.createElement('div');
        messageDiv.className = `aidunite-message aidunite-message-${type}`;
        messageDiv.textContent = message;

        document.body.appendChild(messageDiv);

        // 3秒後に自動削除
        setTimeout(() => {
            messageDiv.remove();
        }, 3000);
    }

    // フォーム処理の初期化
    handleFormSubmission();

    // レスポンシブ対応
    function handleResponsive() {
        const heroActions = document.querySelector('.aidunite-hero-actions');

        if (window.innerWidth <= 768 && heroActions) {
            heroActions.style.flexDirection = 'column';
            heroActions.style.gap = '12px';
        }
    }

    // リサイズ時の処理
    window.addEventListener('resize', handleResponsive);

    // 初期化
    handleResponsive();
});

// 統計データの管理（将来的に実装）
class StatsManager {
    constructor() {
        this.stats = {
            teams: 0,
            users: 0,
            matches: 0
        };
    }

    async fetchStats() {
        try {
            // APIから統計データを取得
            // const response = await fetch('/api/stats');
            // this.stats = await response.json();

            // 現在は仮のデータ
            this.stats = {
                teams: 25,
                users: 150,
                matches: 80
            };

            this.updateDisplay();
        } catch (error) {
            console.error('統計データの取得に失敗しました:', error);
        }
    }

    updateDisplay() {
        // 統計データを画面に表示
        const trustSection = document.querySelector('.aidunite-trust');
        if (trustSection && this.stats.teams > 0) {
            trustSection.style.display = 'block';

            // 統計データを更新
            const teamCount = trustSection.querySelector('.aidunite-trust-item p');
            if (teamCount) {
                teamCount.textContent = `${this.stats.teams}校以上が登録`;
            }
        }
    }
}

// 統計マネージャーの初期化
const statsManager = new StatsManager();
statsManager.fetchStats();
