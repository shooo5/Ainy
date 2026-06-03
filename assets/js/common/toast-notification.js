/**
 * トースト通知ベースファイル
 *
 * スケジュール登録完了時のトースト通知を基本とした統一トースト通知システム
 *
 * 使用方法:
 *   // 成功通知
 *   showToastNotification('スケジュールを登録しました', 'success');
 *
 *   // エラー通知
 *   showToastNotification('登録に失敗しました', 'error');
 *
 *   // 情報通知
 *   showToastNotification('お知らせがあります', 'info');
 *
 *   // 警告通知
 *   showToastNotification('注意が必要です', 'warning');
 *
 *   // カスタムタイトルとメッセージ
 *   showToastNotification('カスタムメッセージ', 'success', {
 *     title: 'カスタムタイトル',
 *     duration: 3000,
 *     onClose: () => console.log('閉じました')
 *   });
 */

// getDocument は js/common/dom-utils.js で定義。未読込時はフォールバック
if (typeof getDocument === 'undefined') {
    function getDocument() {
        if (typeof global !== 'undefined' && global.document) return global.document;
        return typeof document !== 'undefined' ? document : null;
    }
}

/** トースト非表示アニメーション時間（CSS と一致） */
const AIDUNITE_TOAST_HIDE_ANIM_MS = 550;

const aiduniteToastState = (typeof window !== 'undefined'
    ? (window.__aiduniteToastState = window.__aiduniteToastState || {
        hideTimer: null,
        removeTimer: null,
        activeToast: null,
        activeOverlay: null,
    })
    : {
        hideTimer: null,
        removeTimer: null,
        activeToast: null,
        activeOverlay: null,
    });

function aiduniteClearToastTimers() {
    if (aiduniteToastState.hideTimer) {
        clearTimeout(aiduniteToastState.hideTimer);
        aiduniteToastState.hideTimer = null;
    }
    if (aiduniteToastState.removeTimer) {
        clearTimeout(aiduniteToastState.removeTimer);
        aiduniteToastState.removeTimer = null;
    }
}

function aiduniteRemoveActiveToast() {
    aiduniteClearToastTimers();
    if (aiduniteToastState.activeToast && aiduniteToastState.activeToast.parentElement) {
        aiduniteToastState.activeToast.remove();
    }
    if (aiduniteToastState.activeOverlay && aiduniteToastState.activeOverlay.parentElement) {
        aiduniteToastState.activeOverlay.remove();
    }
    aiduniteToastState.activeToast = null;
    aiduniteToastState.activeOverlay = null;
}

function aiduniteDismissToast(toast, overlay, config) {
    if (!toast || !toast.parentElement || toast.classList.contains('hide')) {
        return;
    }

    toast.classList.remove('show');
    toast.classList.add('hide');

    if (overlay && overlay.parentElement) {
        overlay.classList.add('is-hiding');
    }

    aiduniteToastState.removeTimer = setTimeout(() => {
        if (toast.parentElement) {
            toast.remove();
        }
        if (overlay && overlay.parentElement) {
            overlay.remove();
        }
        if (aiduniteToastState.activeToast === toast) {
            aiduniteToastState.activeToast = null;
            aiduniteToastState.activeOverlay = null;
        }
        aiduniteToastState.removeTimer = null;

        if (config && config.onClose && typeof config.onClose === 'function') {
            config.onClose();
        }
    }, AIDUNITE_TOAST_HIDE_ANIM_MS);
}

/**
 * トースト通知を表示する関数
 *
 * @param {string} message - 表示するメッセージ
 * @param {string} type - 通知タイプ ('success', 'error', 'info', 'warning')
 * @param {Object} options - オプション設定
 * @param {string} options.title - カスタムタイトル（省略時はタイプに応じたデフォルトタイトル）
 * @param {number} options.duration - 表示時間（ミリ秒、デフォルト: success=2000, error=5000, その他=3000）
 * @param {Function} options.onClose - 閉じた時のコールバック関数
 * @param {boolean} options.showOverlay - オーバーレイを表示するか（デフォルト: true）
 * @param {boolean} options.hideIcon - アイコンを非表示にするか
 * @param {string} options.iconBasename - カスタムアイコン basename（例: check_circle）
 * @param {string} options.iconSvg - iconBasename の別名
 */
function showToastNotification(message, type = 'info', options = {}) {
    const doc = getDocument();
    if (!doc) return;

    aiduniteRemoveActiveToast();

    // デフォルト設定（success は汎用「完了」。登録専用は showScheduleRegistrationToast でタイトル指定）
    const defaultTitles = {
        success: '完了',
        error: 'エラーが発生しました',
        info: 'お知らせ',
        warning: '警告'
    };

    const defaultIconBasenames = {
        success: 'check_circle',
        error: 'brightness_alert',
        info: 'info',
        warning: 'brightness_alert'
    };

    const defaultDurations = {
        success: 2000,
        error: 5000,
        info: 3000,
        warning: 3000
    };

    const hideIcon = options.hideIcon === true;
    const iconBasename = options.iconBasename || options.iconSvg || defaultIconBasenames[type] || 'info';
    const iconSize = options.iconSize !== undefined
        ? options.iconSize
        : (type === 'success' ? 48 : 28);
    const iconHtml = (!hideIcon && typeof AidUniteThemeIcons !== 'undefined' && AidUniteThemeIcons.html)
        ? AidUniteThemeIcons.html(iconBasename, iconSize, {
            block: true,
            modifierClass: options.iconModifier || (type === 'success' ? 'success' : ''),
        })
        : '';

    const config = {
        title: options.title || defaultTitles[type] || 'お知らせ',
        iconHtml: iconHtml,
        duration: options.duration !== undefined ? options.duration : defaultDurations[type] || 3000,
        onClose: options.onClose || null,
        showOverlay: options.showOverlay !== undefined ? options.showOverlay : true
    };

    let overlay = null;
    if (config.showOverlay && doc.body) {
        overlay = doc.createElement('div');
        overlay.className = 'aidunite-toast-overlay';
        doc.body.appendChild(overlay);
    }

    const toast = doc.createElement('div');
    toast.className = 'aidunite-toast-notification';
    toast.setAttribute('data-type', type);

    const iconBlock = config.iconHtml
        ? `<div class="aidunite-toast-icon">${config.iconHtml}</div>`
        : '';
    toast.innerHTML = `
        <div class="aidunite-toast-card">
            ${iconBlock}
            <div class="aidunite-toast-message">
                <div class="aidunite-toast-title">${escapeHtml(config.title)}</div>
                <div class="aidunite-toast-content">${formatToastText(message)}</div>
            </div>
        </div>
    `;

    if (doc.body) {
        doc.body.appendChild(toast);
    }

    aiduniteToastState.activeToast = toast;
    aiduniteToastState.activeOverlay = overlay;

    requestAnimationFrame(() => {
        if (toast.parentElement) {
            toast.classList.add('show');
        }
    });

    aiduniteToastState.hideTimer = setTimeout(() => {
        aiduniteToastState.hideTimer = null;
        aiduniteDismissToast(toast, overlay, config);
    }, config.duration);

    if (options.clickToClose !== false) {
        toast.addEventListener('click', () => {
            aiduniteClearToastTimers();
            aiduniteDismissToast(toast, overlay, config);
        });
    }
}

/**
 * HTMLエスケープ関数
 *
 * @param {string} text - エスケープするテキスト
 * @returns {string} エスケープされたテキスト
 */
function escapeHtml(text) {
    if (typeof text !== 'string') {
        return '';
    }
    const doc = getDocument();
    if (!doc) {
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }
    const div = doc.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * トースト本文用の整形（改行を <br> に変換）
 *
 * @param {string} text
 * @returns {string}
 */
function formatToastText(text) {
    return escapeHtml(String(text || '')).replace(/\r\n|\r|\n/g, '<br>');
}

/**
 * スケジュール登録完了時のトースト通知（専用関数）
 *
 * @param {string} message - 表示するメッセージ（省略時はデフォルトメッセージ）
 * @param {Object} options - オプション設定
 */
function showScheduleRegistrationToast(message, options = {}) {
    const defaultMessage = message || 'スケジュールの登録が完了しました！';
    showToastNotification(defaultMessage, 'success', {
        title: 'スケジュール登録完了',
        iconBasename: 'check_circle',
        duration: 2000,
        ...options
    });
}

/**
 * トーストを順番に表示（前のトーストが閉じたあと gapMs 後に次を表示）
 *
 * @param {Array<{message: string, type?: string, options?: Object}>} items
 * @param {{ gapMs?: number }} sequenceOptions
 */
function showToastNotificationSequence(items, sequenceOptions = {}) {
    if (!Array.isArray(items) || items.length === 0) {
        return;
    }
    if (typeof showToastNotification !== 'function') {
        return;
    }

    const gapMs = sequenceOptions.gapMs !== undefined ? sequenceOptions.gapMs : 500;
    let index = 0;

    const showNext = () => {
        if (index >= items.length) {
            return;
        }

        const item = items[index];
        const isLast = index === items.length - 1;
        index += 1;

        const itemOptions = item.options && typeof item.options === 'object' ? { ...item.options } : {};
        const userOnClose = itemOptions.onClose;

        itemOptions.onClose = () => {
            if (typeof userOnClose === 'function') {
                userOnClose();
            }
            if (!isLast) {
                setTimeout(showNext, gapMs);
            }
        };

        showToastNotification(item.message, item.type || 'info', itemOptions);
    };

    showNext();
}

if (typeof window !== 'undefined') {
    window.showToastNotification = showToastNotification;
    window.showScheduleRegistrationToast = showScheduleRegistrationToast;
    window.showToastNotificationSequence = showToastNotificationSequence;
    window.escapeHtml = escapeHtml;
}

if (typeof global !== 'undefined') {
    global.showToastNotification = showToastNotification;
    global.showScheduleRegistrationToast = showScheduleRegistrationToast;
    global.showToastNotificationSequence = showToastNotificationSequence;
    global.escapeHtml = escapeHtml;
}
