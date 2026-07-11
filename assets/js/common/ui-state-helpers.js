/**
 * UI 状態演出ヘルパー（ボタンローディング・バッジパルス等）
 *
 * aiduniteWithButtonLoading(button, work, loadingText) → Promise
 * aidunitePulseElement(el) — 件数変化などの軽い強調
 */
(function initAiduniteUiStateHelpers(global) {
    'use strict';

    if (!global) {
        return;
    }

    /**
     * ボタンをローディング状態にしながら非同期処理を実行
     * @param {HTMLElement} button
     * @param {function(): (Promise|*)} work
     * @param {string} [loadingText]
     * @returns {Promise<*>}
     */
    function aiduniteWithButtonLoading(button, work, loadingText) {
        if (!button || typeof work !== 'function') {
            return Promise.resolve();
        }

        var loadingState = null;
        if (typeof global.showButtonLoading === 'function') {
            loadingState = global.showButtonLoading(button, loadingText || '処理中...');
        } else {
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
            button.classList.add('is-loading');
        }

        function restore() {
            if (loadingState && typeof loadingState.restore === 'function') {
                loadingState.restore();
                return;
            }
            button.disabled = false;
            button.removeAttribute('aria-busy');
            button.classList.remove('is-loading');
        }

        var result;
        try {
            result = work();
        } catch (err) {
            restore();
            return Promise.reject(err);
        }

        return Promise.resolve(result).finally(restore);
    }

    /**
     * 要素に短いパルスアニメーションを付与（未読バッジ等）
     * @param {HTMLElement|jQuery|null} el
     */
    function aidunitePulseElement(el) {
        if (!el) {
            return;
        }

        var node = el.jquery ? el[0] : el;
        if (!node || !node.classList) {
            return;
        }

        node.classList.remove('ui-state-pulse');
        // reflow で連続パルスを再トリガー
        void node.offsetWidth;
        node.classList.add('ui-state-pulse');
        node.addEventListener('animationend', function onEnd() {
            node.classList.remove('ui-state-pulse');
            node.removeEventListener('animationend', onEnd);
        });
    }

    global.aiduniteWithButtonLoading = aiduniteWithButtonLoading;
    global.aidunitePulseElement = aidunitePulseElement;
})(typeof window !== 'undefined' ? window : null);
