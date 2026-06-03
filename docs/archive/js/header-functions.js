/**
 * Ainy ヘッダー機能
 * サイドメニューとハンバーガーメニューの制御
 */

// デバッグ用のログ関数
function debugLog(message) {
    console.log('[Ainy Header Debug]:', message);
}

// サイドメニューのトグル機能
function toggleSideMenu() {
    debugLog('toggleSideMenu called');

    const sideMenu = document.getElementById('sideMenu');
    const overlay = document.querySelector('.ainy-side-menu-overlay');
    const hamburger = document.querySelector('.ainy-hamburger');

    debugLog('Elements found:', {
        sideMenu: !!sideMenu,
        overlay: !!overlay,
        hamburger: !!hamburger
    });

    if (sideMenu && overlay && hamburger) {
        const isOpen = sideMenu.classList.contains('open');
        debugLog('Menu is open:', isOpen);

        if (isOpen) {
            closeSideMenu();
        } else {
            openSideMenu();
        }
    } else {
        debugLog('Some elements not found');
    }
}

// サイドメニューを開く
function openSideMenu() {
    debugLog('openSideMenu called');

    const sideMenu = document.getElementById('sideMenu');
    const overlay = document.querySelector('.ainy-side-menu-overlay');
    const hamburger = document.querySelector('.ainy-hamburger');

    if (sideMenu && overlay && hamburger) {
        sideMenu.classList.add('open');
        overlay.classList.add('show');
        hamburger.classList.add('active');

        // スクロールを無効化
        document.body.style.overflow = 'hidden';

        debugLog('Side menu opened successfully');
    }
}

// サイドメニューを閉じる
function closeSideMenu() {
    debugLog('closeSideMenu called');

    const sideMenu = document.getElementById('sideMenu');
    const overlay = document.querySelector('.ainy-side-menu-overlay');
    const hamburger = document.querySelector('.ainy-hamburger');

    if (sideMenu && overlay && hamburger) {
        sideMenu.classList.remove('open');
        overlay.classList.remove('show');
        hamburger.classList.remove('active');

        // スクロールを有効化
        document.body.style.overflow = '';

        debugLog('Side menu closed successfully');
    }
}

// ESCキーでサイドメニューを閉じる
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeSideMenu();
    }
});

// ウィンドウリサイズ時の処理
window.addEventListener('resize', function() {
    // デスクトップサイズになったらサイドメニューを閉じる
    if (window.innerWidth > 768) {
        closeSideMenu();
    }
});

// ページ読み込み完了時の初期化
document.addEventListener('DOMContentLoaded', function() {
    debugLog('DOMContentLoaded event fired');

    // 要素の存在確認
    const sideMenu = document.getElementById('sideMenu');
    const overlay = document.querySelector('.ainy-side-menu-overlay');
    const hamburger = document.querySelector('.ainy-hamburger');

    debugLog('Initial element check:', {
        sideMenu: !!sideMenu,
        overlay: !!overlay,
        hamburger: !!hamburger
    });

    // ハンバーガーメニューのクリックイベントを直接追加
    if (hamburger) {
        debugLog('Adding click event to hamburger');
        hamburger.addEventListener('click', function(e) {
            debugLog('Hamburger clicked');
            e.preventDefault();
            toggleSideMenu();
        });
    }

    // オーバーレイのクリックイベントを追加
    if (overlay) {
        debugLog('Adding click event to overlay');
        overlay.addEventListener('click', function(e) {
            debugLog('Overlay clicked');
            closeSideMenu();
        });
    }

    // サイドメニューの閉じるボタンのイベントを追加
    const closeButton = document.querySelector('.ainy-side-menu-close');
    if (closeButton) {
        debugLog('Adding click event to close button');
        closeButton.addEventListener('click', function(e) {
            debugLog('Close button clicked');
            closeSideMenu();
        });
    }

    // ヘッダーのアニメーション効果
    const header = document.querySelector('.ainy-header');
    if (header) {
        header.style.opacity = '0';
        header.style.transform = 'translateY(-10px)';

        setTimeout(function() {
            header.style.transition = 'all 0.4s cubic-bezier(0.4, 0, 0.2, 1)';
            header.style.opacity = '1';
            header.style.transform = 'translateY(0)';
        }, 100);
    }

    // ロゴのホバー効果
    const logo = document.querySelector('.ainy-logo');
    if (logo) {
        logo.addEventListener('mouseenter', function() {
            const logoIcon = this.querySelector('.ainy-logo-icon');
            if (logoIcon) {
                logoIcon.style.transform = 'scale(1.05)';
                logoIcon.style.transition = 'transform 0.2s ease';
            }
        });

        logo.addEventListener('mouseleave', function() {
            const logoIcon = this.querySelector('.ainy-logo-icon');
            if (logoIcon) {
                logoIcon.style.transform = 'scale(1)';
            }
        });
    }

    // ボタンのホバー効果
    const authButtons = document.querySelectorAll('.ainy-auth-button');
    authButtons.forEach(function(button) {
        button.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-1px)';
            this.style.transition = 'transform 0.2s ease';
        });

        button.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });

    // ハンバーガーメニューのホバー効果
    if (hamburger) {
        hamburger.addEventListener('mouseenter', function() {
            const icon = this.querySelector('.ainy-hamburger-icon');
            if (icon) {
                icon.style.transform = 'scale(1.1)';
                icon.style.transition = 'transform 0.2s ease';
            }
        });

        hamburger.addEventListener('mouseleave', function() {
            const icon = this.querySelector('.ainy-hamburger-icon');
            if (icon) {
                icon.style.transform = 'scale(1)';
            }
        });
    }

    // サイドメニューリンクのクリック時にメニューを閉じる
    const sideMenuLinks = document.querySelectorAll('.ainy-side-nav-link');
    sideMenuLinks.forEach(function(link) {
        link.addEventListener('click', function() {
            // 少し遅延させてからメニューを閉じる（クリックの視覚的フィードバックのため）
            setTimeout(function() {
                closeSideMenu();
            }, 100);
        });
    });

    debugLog('Initialization completed');
});

// グローバル関数として公開（HTMLのonclick属性から呼び出せるように）
window.toggleSideMenu = toggleSideMenu;
window.openSideMenu = openSideMenu;
window.closeSideMenu = closeSideMenu;
