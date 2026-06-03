/**
 * ツールチップ共通管理
 * 必要に応じて使用（基本的にはCSSで対応）
 */

(function() {
    'use strict';

    /**
     * ツールチップの初期化
     * モバイルデバイスでのタッチ操作に対応
     */
    function initTooltips() {
        // モバイルデバイスの検出
        const isMobile = /iPhone|iPad|iPod|Android/i.test(navigator.userAgent) ||
                        (window.innerWidth <= 768);

        if (isMobile) {
            // モバイルではタップでツールチップを表示
            const tooltipTriggers = document.querySelectorAll('.tooltip-trigger[data-tooltip]');

            tooltipTriggers.forEach(trigger => {
                trigger.addEventListener('click', function(e) {
                    e.preventDefault();
                    const tooltipText = this.getAttribute('data-tooltip');
                    if (tooltipText) {
                        // モバイル用のモーダル表示（オプション）
                        showMobileTooltip(tooltipText, this);
                    }
                });
            });
        }
    }

    /**
     * モバイル用ツールチップ表示（オプション）
     */
    function showMobileTooltip(text, element) {
        // 簡易的なアラート表示（またはカスタムモーダル）
        // 実装は必要に応じて
        console.log('Tooltip:', text);
    }

    // DOM読み込み完了後に初期化
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTooltips);
    } else {
        initTooltips();
    }
})();
