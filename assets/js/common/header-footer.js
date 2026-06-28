/**
 * AidUnite Header & Footer JavaScript
 * モバイルメニュー機能とヘッダー・フッター関連の機能を提供
 */

// モバイルメニューの切り替え機能（クラス制御と display 制御の両方に対応）
function toggleMobileMenu() {
    const nav = document.querySelector('.aidunite-nav');
    const toggleButton = document.querySelector('.aidunite-mobile-menu-toggle');
    const search = document.querySelector('.aidunite-search');
    const userMenu = document.querySelector('.aidunite-user-menu');

    // パターン1: .aidunite-mobile-menu-toggle がある場合はクラスで制御
    if (nav && toggleButton) {
        nav.classList.toggle('mobile-active');
        toggleButton.classList.toggle('active');
        if (toggleButton.classList.contains('active')) {
            if (typeof AidUniteThemeIcons !== 'undefined') {
                AidUniteThemeIcons.setHtml(toggleButton, 'close', 24);
            }
        } else {
            if (typeof AidUniteThemeIcons !== 'undefined') {
                AidUniteThemeIcons.setHtml(toggleButton, 'menu', 24);
            }
        }
        return;
    }

    // パターン2: display で制御（.aidunite-nav / .aidunite-search / .aidunite-user-menu）
    if (nav) {
        const isVisible = nav.style.display === 'flex';
        nav.style.display = isVisible ? 'none' : 'flex';
        if (search) search.style.display = isVisible ? 'none' : 'block';
        if (userMenu) userMenu.style.display = isVisible ? 'none' : 'flex';
    }
}

// 検索機能
function initSearch() {
    const searchInput = document.querySelector('.aidunite-search-input');
    const searchIcon = document.querySelector('.aidunite-search-icon');

    if (searchInput && searchIcon) {
        // 検索アイコンクリック時の処理
        searchIcon.addEventListener('click', function() {
            const query = searchInput.value.trim();
            if (query) {
                window.location.href = `${window.location.origin}/team-list?search=${encodeURIComponent(query)}`;
            }
        });

        // Enterキー押下時の処理
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                const query = searchInput.value.trim();
                if (query) {
                    window.location.href = `${window.location.origin}/team-list?search=${encodeURIComponent(query)}`;
                }
            }
        });
    }
}

// スクロール時のヘッダー固定
function initHeaderScroll() {
    const header = document.querySelector('.aidunite-header');
    let lastScrollTop = 0;

    if (header) {
        window.addEventListener('scroll', function() {
            const scrollTop = window.pageYOffset || document.documentElement.scrollTop;

            if (scrollTop > 100) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }

            // スクロール方向に応じたヘッダーの表示/非表示
            if (scrollTop > lastScrollTop && scrollTop > 200) {
                header.classList.add('header-hidden');
            } else {
                header.classList.remove('header-hidden');
            }

            lastScrollTop = scrollTop;
        });
    }
}

// フッターのSNSリンク機能
function initFooterLinks() {
    const socialLinks = document.querySelectorAll('.ainy-social-link');

    socialLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const platform = this.getAttribute('title');
            const url = this.getAttribute('href');

            if (url && url !== '#') {
                window.open(url, '_blank');
            } else {
                // デフォルトのSNS URL（実際のURLに置き換えてください）
                const defaultUrls = {
                    'X (Twitter)': 'https://twitter.com/ainyunite',
                    'Facebook': 'https://facebook.com/ainyunite',
                    'Instagram': 'https://instagram.com/ainyunite'
                };

                if (defaultUrls[platform]) {
                    window.open(defaultUrls[platform], '_blank');
                }
            }
        });
    });
}

// ボトムナビ：アクティブ状態は PHP 側で付与（クリック時の class 操作は行わない）
function initBottomNav() {
    // 将来の拡張用フック（現状は no-op）
}

// ページ読み込み完了時の初期化
document.addEventListener('DOMContentLoaded', function() {
    initSearch();
    initHeaderScroll();
    initFooterLinks();
    initBottomNav();

    const mobileToggle = document.querySelector('.aidunite-mobile-menu-toggle');
    if (mobileToggle && typeof AidUniteThemeIcons !== 'undefined') {
        AidUniteThemeIcons.setHtml(mobileToggle, 'menu', 24);
    }

    // モバイル時（768px以下）の初期表示：.aidunite-nav 系を非表示
    if (window.innerWidth <= 768) {
        const nav = document.querySelector('.aidunite-nav');
        const search = document.querySelector('.aidunite-search');
        const userMenu = document.querySelector('.aidunite-user-menu');
        if (nav) nav.style.display = 'none';
        if (search) search.style.display = 'none';
        if (userMenu) userMenu.style.display = 'none';
    }

    // ログイン成功メッセージ
    const params = new URLSearchParams(window.location.search);
    if (params.get('login') === 'success') {
        setTimeout(() => {
            showNotification('ログイン成功！ようこそ Ainy Unite へ！', 'success');
        }, 500);
    }

    // ログアウト成功メッセージ
    if (params.get('logout') === 'success') {
        setTimeout(() => {
            showNotification('ログアウトしました。またのご利用をお待ちしています！', 'info');
        }, 500);
    }
});

// 通知表示機能
function showNotification(message, type = 'info') {
    // 既存の通知を削除
    const existingNotification = document.querySelector('.aidunite-notification');
    if (existingNotification) {
        existingNotification.remove();
    }

    // 新しい通知を作成
    const notification = document.createElement('div');
    notification.className = `aidunite-notification aidunite-notification-${type}`;
    notification.innerHTML = `
        <div class="aidunite-notification-content">
            <span class="aidunite-notification-message">${message}</span>
            <button type="button" class="aidunite-notification-close" onclick="this.parentElement.parentElement.remove()" aria-label="閉じる"></button>
        </div>
    `;

    // スタイルを適用
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${type === 'success' ? '#4CAF50' : type === 'error' ? '#f44336' : '#2196F3'};
        color: white;
        padding: 15px 20px;
        border-radius: 5px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        z-index: 10000;
        max-width: 400px;
        animation: slideInRight 0.3s ease-out;
    `;

    document.body.appendChild(notification);

    const closeBtn = notification.querySelector('.aidunite-notification-close');
    if (closeBtn && typeof AidUniteThemeIcons !== 'undefined') {
        AidUniteThemeIcons.setHtml(closeBtn, 'close', 18);
    }

    // 5秒後に自動削除
    setTimeout(() => {
        if (notification.parentElement) {
            notification.remove();
        }
    }, 5000);
}

// グローバル関数として公開
window.toggleMobileMenu = toggleMobileMenu;
window.showNotification = showNotification;
