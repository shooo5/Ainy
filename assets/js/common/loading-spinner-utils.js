/**
 * ローディングスピナー管理ユーティリティ
 * Design Tokens（--primary-color / --success-color）ベースのスピナー CSS と連携
 */

// getDocument は js/common/dom-utils.js で定義。未読込時はフォールバック
if (typeof getDocument === 'undefined') {
    function getDocument() {
        if (typeof global !== 'undefined' && global.document) return global.document;
        return typeof document !== 'undefined' ? document : null;
    }
}

class LoadingSpinnerManager {
    constructor() {
        this.activeSpinners = new Set();
        this.init();
    }

    init() {
        this.cleanupExistingSpinners();
    }

    getDocument() {
        return getDocument();
    }

    /**
     * フルスクリーンローディングスピナーを表示
     * @param {string} title - ローディングタイトル
     * @param {string} subtitle - ローディングサブタイトル
     * @returns {string|null} スピナーID
     */
    showFullscreen(title = '処理中...', subtitle = 'しばらくお待ちください') {
        const doc = getDocument();
        if (!doc || !doc.body) return null;

        const spinnerId = 'loading-spinner-' + Date.now();

        const overlay = doc.createElement('div');
        overlay.className = 'loading-spinner-overlay';
        overlay.id = spinnerId;

        overlay.innerHTML = `
            <div class="loading-spinner-container loading-spinner-container--simple">
                <div class="spinner spinner-lg loading-spinner-fallback" role="status" aria-label="読み込み中"></div>
                <div class="loading-text">
                    <div class="loading-title">${title}</div>
                    <div class="loading-subtitle">${subtitle}</div>
                </div>
            </div>
        `;

        doc.body.appendChild(overlay);

        requestAnimationFrame(() => {
            overlay.classList.add('active');
        });

        this.activeSpinners.add(spinnerId);
        return spinnerId;
    }

    /**
     * フルスクリーンスピナーを非表示
     * @param {string} spinnerId - スピナーID
     */
    hideFullscreen(spinnerId) {
        const doc = getDocument();
        if (!doc) return;

        const overlay = doc.getElementById(spinnerId);
        if (!overlay) {
            return;
        }

        overlay.classList.add('fade-out');

        setTimeout(() => {
            if (overlay.parentElement) {
                overlay.parentElement.removeChild(overlay);
            }
            this.activeSpinners.delete(spinnerId);
        }, 300);
    }

    /**
     * ボタン内にローディングスピナーを表示
     * @param {HTMLElement} button - 対象のボタン要素
     * @param {string} loadingText - ローディング中のテキスト
     * @returns {Object} 元のボタン状態を保存したオブジェクト
     */
    showButtonSpinner(button, loadingText = '処理中...') {
        const originalHTML = button.innerHTML;
        const originalDisabled = button.disabled;

        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
        button.classList.add('is-loading');
        button.innerHTML = `
            <span class="button-loading-spinner"></span>
            <span>${loadingText}</span>
        `;

        return {
            originalHTML,
            originalDisabled,
            restore: () => this.hideButtonSpinner(button, originalHTML, originalDisabled)
        };
    }

    /**
     * ボタンのローディングスピナーを非表示
     * @param {HTMLElement} button - 対象のボタン要素
     * @param {string} originalHTML - 元のHTML
     * @param {boolean} originalDisabled - 元の無効状態
     */
    hideButtonSpinner(button, originalHTML, originalDisabled) {
        button.innerHTML = originalHTML;
        button.disabled = originalDisabled;
        button.removeAttribute('aria-busy');
        button.classList.remove('is-loading');
    }

    /**
     * インラインローディングスピナーを表示
     * @param {HTMLElement} element - 対象の要素
     * @param {string} loadingText - ローディング中のテキスト
     * @returns {Object} 元の要素状態を保存したオブジェクト
     */
    showInlineSpinner(element, loadingText = '処理中...') {
        const originalHTML = element.innerHTML;

        element.innerHTML = `
            <span class="inline-loading-spinner"></span>
            <span>${loadingText}</span>
        `;

        return {
            originalHTML,
            restore: () => this.hideInlineSpinner(element, originalHTML)
        };
    }

    /**
     * インラインローディングスピナーを非表示
     * @param {HTMLElement} element - 対象の要素
     * @param {string} originalHTML - 元のHTML
     */
    hideInlineSpinner(element, originalHTML) {
        element.innerHTML = originalHTML;
    }

    /**
     * フォーム送信時のローディング処理
     * @param {HTMLFormElement} form - 対象のフォーム
     * @param {string} buttonSelector - 送信ボタンのセレクタ
     * @param {string} loadingText - ローディング中のテキスト
     * @returns {Object|null} ローディング状態管理オブジェクト
     */
    setupFormLoading(form, buttonSelector = '.submit-button', loadingText = '送信中...') {
        const submitButton = form.querySelector(buttonSelector);
        if (!submitButton) {
            return null;
        }

        const buttonState = this.showButtonSpinner(submitButton, loadingText);

        return {
            ...buttonState,
            form,
            submitButton
        };
    }

    /**
     * フォームローディングを終了
     * @param {Object} loadingState - ローディング状態管理オブジェクト
     */
    finishFormLoading(loadingState) {
        if (loadingState && loadingState.restore) {
            loadingState.restore();
        }
    }

    /**
     * 既存のスピナーをクリーンアップ
     */
    cleanupExistingSpinners() {
        const doc = getDocument();
        if (!doc) return;

        const existingSpinners = doc.querySelectorAll('.loading-spinner-overlay');
        existingSpinners.forEach((spinner) => {
            if (spinner.parentElement) {
                spinner.parentElement.removeChild(spinner);
            }
        });

        this.activeSpinners.clear();
    }

    /**
     * 全てのアクティブなスピナーを非表示
     */
    hideAllSpinners() {
        this.activeSpinners.forEach((spinnerId) => {
            this.hideFullscreen(spinnerId);
        });
    }

    /**
     * ページ離脱時のクリーンアップ
     */
    setupPageCleanup() {
        window.addEventListener('beforeunload', () => {
            this.hideAllSpinners();
        });

        window.addEventListener('pagehide', () => {
            this.hideAllSpinners();
        });
    }
}

const loadingSpinnerManager = new LoadingSpinnerManager();

if (!window._loadingSpinnerUtilsInitialized) {
    const docForEvent = getDocument();
    if (docForEvent && typeof docForEvent.addEventListener === 'function') {
        docForEvent.addEventListener('DOMContentLoaded', () => {
            loadingSpinnerManager.setupPageCleanup();
        });
    }
    window._loadingSpinnerUtilsInitialized = true;
}

window.LoadingSpinnerManager = LoadingSpinnerManager;
window.loadingSpinnerManager = loadingSpinnerManager;

window.showLoadingSpinner = (title, subtitle) => loadingSpinnerManager.showFullscreen(title, subtitle);
window.hideLoadingSpinner = (spinnerId) => loadingSpinnerManager.hideFullscreen(spinnerId);
window.showButtonLoading = (button, text) => loadingSpinnerManager.showButtonSpinner(button, text);
window.hideButtonLoading = (button, originalHTML, originalDisabled) =>
    loadingSpinnerManager.hideButtonSpinner(button, originalHTML, originalDisabled);
