/**
 * ローディングスピナー管理ユーティリティ
 * Success Color (#28a745) ベース
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
        console.log('=== LoadingSpinnerManager 初期化開始 ===');
        this.activeSpinners = new Set();
        console.log('activeSpinners Set作成完了');
        this.init();
        console.log('=== LoadingSpinnerManager 初期化完了 ===');
    }

    init() {
        console.log('=== LoadingSpinnerManager init() 開始 ===');
        // ページ読み込み時に既存のスピナーをクリーンアップ
        this.cleanupExistingSpinners();
        console.log('=== LoadingSpinnerManager init() 完了 ===');
    }

    getDocument() {
        return getDocument();
    }

    /**
     * フルスクリーンローディングスピナーを表示
     * @param {string} title - ローディングタイトル
     * @param {string} subtitle - ローディングサブタイトル
     * @returns {string} スピナーID
     */
    showFullscreen(title = '処理中...', subtitle = 'しばらくお待ちください') {
        console.log('=== フルスクリーンスピナー表示開始 ===');
        console.log('title:', title);
        console.log('subtitle:', subtitle);

        const doc = getDocument();
        if (!doc || !doc.body) return null;

        const spinnerId = 'loading-spinner-' + Date.now();
        console.log('生成されたスピナーID:', spinnerId);

        const overlay = doc.createElement('div');
        overlay.className = 'loading-spinner-overlay';
        overlay.id = spinnerId;
        console.log('オーバーレイ要素作成:', overlay);

        overlay.innerHTML = `
            <div class="loading-spinner-container loading-spinner-container--simple">
                <div class="spinner spinner-lg loading-spinner-fallback" role="status" aria-label="読み込み中"></div>
                <div class="loading-text">
                    <div class="loading-title">${title}</div>
                    <div class="loading-subtitle">${subtitle}</div>
                </div>
            </div>
        `;

        console.log('オーバーレイをbodyに追加');
        doc.body.appendChild(overlay);
        console.log('オーバーレイ追加完了');

        // アニメーション表示
        requestAnimationFrame(() => {
            console.log('アニメーション表示開始');
            overlay.classList.add('active');
            console.log('activeクラス追加完了');
        });

        this.activeSpinners.add(spinnerId);
        console.log('アクティブスピナーに追加:', spinnerId);
        console.log('現在のアクティブスピナー数:', this.activeSpinners.size);

        console.log('=== フルスクリーンスピナー表示完了 ===');
        return spinnerId;
    }

    /**
     * フルスクリーンローディングスピナーを非表示
     * @param {string} spinnerId - スピナーID
     */
    hideFullscreen(spinnerId) {
        console.log('=== フルスクリーンスピナー非表示開始 ===');
        console.log('対象スピナーID:', spinnerId);

        const doc = getDocument();
        if (!doc) return;

        const overlay = doc.getElementById(spinnerId);
        if (!overlay) {
            console.log('スピナー要素が見つかりません:', spinnerId);
            return;
        }

        console.log('スピナー要素発見:', overlay);
        console.log('fade-outクラス追加');
        overlay.classList.add('fade-out');

        setTimeout(() => {
            console.log('スピナー要素削除処理開始');
            if (overlay.parentElement) {
                overlay.parentElement.removeChild(overlay);
                console.log('スピナー要素削除完了');
            } else {
                console.log('親要素が見つからないため削除できません');
            }

            this.activeSpinners.delete(spinnerId);
            console.log('アクティブスピナーから削除:', spinnerId);
            console.log('現在のアクティブスピナー数:', this.activeSpinners.size);
        }, 300);

        console.log('=== フルスクリーンスピナー非表示完了 ===');
    }

    /**
     * ボタン内にローディングスピナーを表示
     * @param {HTMLElement} button - 対象のボタン要素
     * @param {string} loadingText - ローディング中のテキスト
     * @returns {Object} 元のボタン状態を保存したオブジェクト
     */
    showButtonSpinner(button, loadingText = '処理中...') {
        console.log('=== ボタンスピナー表示開始 ===');
        console.log('対象ボタン:', button);
        console.log('loadingText:', loadingText);

        const originalHTML = button.innerHTML;
        const originalDisabled = button.disabled;

        console.log('元のボタン状態:');
        console.log('- HTML:', originalHTML);
        console.log('- disabled:', originalDisabled);

        button.disabled = true;
        button.innerHTML = `
            <span class="button-loading-spinner"></span>
            <span>${loadingText}</span>
        `;

        console.log('ボタン更新完了');
        console.log('新しいHTML:', button.innerHTML);

        const result = {
            originalHTML,
            originalDisabled,
            restore: () => this.hideButtonSpinner(button, originalHTML, originalDisabled)
        };

        console.log('返却オブジェクト:', result);
        console.log('=== ボタンスピナー表示完了 ===');
        return result;
    }

    /**
     * ボタンのローディングスピナーを非表示
     * @param {HTMLElement} button - 対象のボタン要素
     * @param {string} originalHTML - 元のHTML
     * @param {boolean} originalDisabled - 元の無効状態
     */
    hideButtonSpinner(button, originalHTML, originalDisabled) {
        console.log('=== ボタンスピナー非表示開始 ===');
        console.log('対象ボタン:', button);
        console.log('復元HTML:', originalHTML);
        console.log('復元disabled:', originalDisabled);

        button.innerHTML = originalHTML;
        button.disabled = originalDisabled;

        console.log('ボタン復元完了');
        console.log('復元後のHTML:', button.innerHTML);
        console.log('復元後のdisabled:', button.disabled);
        console.log('=== ボタンスピナー非表示完了 ===');
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
     * @returns {Object} ローディング状態管理オブジェクト
     */
    setupFormLoading(form, buttonSelector = '.submit-button', loadingText = '送信中...') {
        console.log('=== フォームローディング設定開始 ===');
        console.log('対象フォーム:', form);
        console.log('ボタンセレクタ:', buttonSelector);
        console.log('ローディングテキスト:', loadingText);

        const submitButton = form.querySelector(buttonSelector);
        if (!submitButton) {
            console.log('送信ボタンが見つかりません');
            return null;
        }

        console.log('送信ボタン発見:', submitButton);
        const buttonState = this.showButtonSpinner(submitButton, loadingText);

        const result = {
            ...buttonState,
            form,
            submitButton
        };

        console.log('フォームローディング設定完了:', result);
        console.log('=== フォームローディング設定完了 ===');
        return result;
    }

    /**
     * フォームローディングを終了
     * @param {Object} loadingState - ローディング状態管理オブジェクト
     */
    finishFormLoading(loadingState) {
        console.log('=== フォームローディング終了開始 ===');
        console.log('loadingState:', loadingState);

        if (loadingState && loadingState.restore) {
            console.log('restore関数を実行');
            loadingState.restore();
        } else {
            console.log('restore関数が見つからないか、loadingStateが無効');
        }

        console.log('=== フォームローディング終了完了 ===');
    }

    /**
     * 既存のスピナーをクリーンアップ
     */
    cleanupExistingSpinners() {
        console.log('=== 既存スピナークリーンアップ開始 ===');
        const doc = getDocument();
        if (!doc) return;

        const existingSpinners = doc.querySelectorAll('.loading-spinner-overlay');
        console.log('既存のスピナー要素数:', existingSpinners.length);

        existingSpinners.forEach((spinner, index) => {
            console.log(`スピナー ${index + 1} を削除:`, spinner);
            if (spinner.parentElement) {
                spinner.parentElement.removeChild(spinner);
                console.log(`スピナー ${index + 1} 削除完了`);
            } else {
                console.log(`スピナー ${index + 1} の親要素が見つかりません`);
            }
        });

        this.activeSpinners.clear();
        console.log('activeSpinners Set クリア完了');
        console.log('=== 既存スピナークリーンアップ完了 ===');
    }

    /**
     * 全てのアクティブなスピナーを非表示
     */
    hideAllSpinners() {
        console.log('=== 全スピナー非表示開始 ===');
        console.log('アクティブなスピナー数:', this.activeSpinners.size);

        this.activeSpinners.forEach(spinnerId => {
            console.log('スピナー非表示処理:', spinnerId);
            this.hideFullscreen(spinnerId);
        });

        console.log('=== 全スピナー非表示完了 ===');
    }

    /**
     * ページ離脱時のクリーンアップ
     */
    setupPageCleanup() {
        console.log('=== ページクリーンアップ設定開始 ===');

        window.addEventListener('beforeunload', () => {
            console.log('beforeunload イベント発火 - 全スピナー非表示');
            this.hideAllSpinners();
        });

        window.addEventListener('pagehide', () => {
            console.log('pagehide イベント発火 - 全スピナー非表示');
            this.hideAllSpinners();
        });

        console.log('ページクリーンアップイベントリスナー設定完了');
        console.log('=== ページクリーンアップ設定完了 ===');
    }
}

// グローバルインスタンスを作成
console.log('=== グローバルインスタンス作成開始 ===');
const loadingSpinnerManager = new LoadingSpinnerManager();
console.log('LoadingSpinnerManager インスタンス作成完了:', loadingSpinnerManager);

// ページ読み込み完了後にクリーンアップ設定
// 重複読み込み対策: 既に設定済みの場合はスキップ
if (!window._loadingSpinnerUtilsInitialized) {
const docForEvent = getDocument();
if (docForEvent && typeof docForEvent.addEventListener === 'function') {
    docForEvent.addEventListener('DOMContentLoaded', () => {
        console.log('=== DOMContentLoaded イベント発火 ===');
        console.log('ページクリーンアップ設定開始');
        loadingSpinnerManager.setupPageCleanup();
        console.log('ページクリーンアップ設定完了');
        console.log('=== DOMContentLoaded 処理完了 ===');
    });
    }
    window._loadingSpinnerUtilsInitialized = true;
}

// グローバル関数として公開
console.log('=== グローバル関数公開開始 ===');
window.LoadingSpinnerManager = LoadingSpinnerManager;
window.loadingSpinnerManager = loadingSpinnerManager;
console.log('LoadingSpinnerManager クラス公開完了');
console.log('loadingSpinnerManager インスタンス公開完了');

// 便利な関数をグローバルに公開
window.showLoadingSpinner = (title, subtitle) => {
    console.log('グローバル関数 showLoadingSpinner 呼び出し:', title, subtitle);
    return loadingSpinnerManager.showFullscreen(title, subtitle);
};
window.hideLoadingSpinner = (spinnerId) => {
    console.log('グローバル関数 hideLoadingSpinner 呼び出し:', spinnerId);
    return loadingSpinnerManager.hideFullscreen(spinnerId);
};
window.showButtonLoading = (button, text) => {
    console.log('グローバル関数 showButtonLoading 呼び出し:', button, text);
    return loadingSpinnerManager.showButtonSpinner(button, text);
};
window.hideButtonLoading = (button, originalHTML, originalDisabled) => {
    console.log('グローバル関数 hideButtonLoading 呼び出し:', button, originalHTML, originalDisabled);
    return loadingSpinnerManager.hideButtonSpinner(button, originalHTML, originalDisabled);
};
console.log('便利なグローバル関数公開完了');
console.log('=== グローバル関数公開完了 ===');
