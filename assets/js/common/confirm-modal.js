/**
 * 確認モーダル（統一コンポーネント）
 *
 * confirm() の代替。破壊的操作・重要判断の前に使用する。
 * 詳細: docs/UI-Feedback-and-Modal-Guide.md
 *
 * @example
 * showConfirmModal({
 *   title: '削除確認',
 *   message: 'このスケジュールを削除してもよろしいですか？',
 *   confirmLabel: '削除する',
 *   cancelLabel: 'キャンセル',
 *   confirmVariant: 'danger',
 *   onConfirm: () => { /* 削除処理 *\/ }
 * });
 */

if (typeof getDocument === 'undefined') {
    function getDocument() {
        if (typeof global !== 'undefined' && global.document) return global.document;
        return typeof document !== 'undefined' ? document : null;
    }
}

/**
 * HTML エスケープ
 * @param {string} text
 * @returns {string}
 */
function confirmModalEscapeHtml(text) {
    return String(text ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

/** @type {HTMLElement|null} */
let confirmModalLastFocus = null;

/** @type {((e: KeyboardEvent) => void)|null} */
let confirmModalKeyHandler = null;

/**
 * @param {'danger'|'primary'|'success'|string} variant
 * @returns {'danger'|'primary'|'success'}
 */
function normalizeConfirmVariant(variant) {
    if (variant === 'primary' || variant === 'success') {
        return variant;
    }
    return 'danger';
}

/**
 * @param {'danger'|'primary'|'success'} variant
 * @returns {string}
 */
function confirmModalVariantClass(variant) {
    switch (variant) {
        case 'primary':
            return 'btn btn-primary';
        case 'success':
            return 'btn btn-success';
        default:
            return 'btn btn-danger';
    }
}

/**
 * 確認モーダルを閉じる
 * @param {boolean} invokeCancel
 * @param {Function|null} onCancel
 */
function closeConfirmModal(invokeCancel, onCancel) {
    const doc = getDocument();
    if (!doc) return;

    const overlay = doc.querySelector('.aidunite-confirm-overlay');
    const modal = doc.querySelector('.aidunite-confirm-modal');

    if (confirmModalKeyHandler) {
        doc.removeEventListener('keydown', confirmModalKeyHandler);
        confirmModalKeyHandler = null;
    }

    if (overlay) overlay.classList.add('is-closing');
    if (modal) modal.classList.add('is-closing');

    setTimeout(() => {
        if (overlay && overlay.parentElement) overlay.remove();
        if (modal && modal.parentElement) modal.remove();
        doc.body.classList.remove('aidunite-confirm-modal-open');

        if (invokeCancel && typeof onCancel === 'function') {
            onCancel();
        }

        if (confirmModalLastFocus && typeof confirmModalLastFocus.focus === 'function') {
            confirmModalLastFocus.focus();
        }
        confirmModalLastFocus = null;
    }, 300);
}

/**
 * 確認モーダルを表示
 * @param {Object} options
 * @param {string} [options.title='確認']
 * @param {string} [options.message='']
 * @param {string} [options.confirmLabel='OK']
 * @param {string} [options.cancelLabel='キャンセル']
 * @param {'danger'|'primary'|'success'} [options.confirmVariant='danger']
 * @param {Function|null} [options.onConfirm=null]
 * @param {Function|null} [options.onCancel=null]
 */
function showConfirmModal(options = {}) {
    const doc = getDocument();
    if (!doc || !doc.body) return;

    const config = {
        title: options.title || '確認',
        message: options.message || '',
        confirmLabel: options.confirmLabel || 'OK',
        cancelLabel: options.cancelLabel || 'キャンセル',
        confirmVariant: normalizeConfirmVariant(options.confirmVariant),
        messageAlign: options.messageAlign === 'center' ? 'center' : '',
        onConfirm: options.onConfirm || null,
        onCancel: options.onCancel || null
    };

    closeConfirmModal(false, null);

    const existingOverlay = doc.querySelector('.aidunite-confirm-overlay');
    const existingModal = doc.querySelector('.aidunite-confirm-modal');
    if (existingOverlay) existingOverlay.remove();
    if (existingModal) existingModal.remove();

    confirmModalLastFocus = doc.activeElement;

    const confirmBtnClass = confirmModalVariantClass(config.confirmVariant);
    const messageClass = config.messageAlign === 'center'
        ? 'aidunite-confirm-dialog__message aidunite-confirm-dialog__message--center'
        : 'aidunite-confirm-dialog__message';

    const overlay = doc.createElement('div');
    overlay.className = 'aidunite-confirm-overlay';
    overlay.setAttribute('aria-hidden', 'true');

    const modal = doc.createElement('div');
    modal.className = 'aidunite-confirm-modal';
    modal.setAttribute('role', 'dialog');
    modal.setAttribute('aria-modal', 'true');
    modal.setAttribute('aria-labelledby', 'aidunite-confirm-title');

    modal.innerHTML = `
        <div class="aidunite-confirm-dialog">
            <div class="aidunite-confirm-dialog__header">
                <h2 class="aidunite-confirm-dialog__title" id="aidunite-confirm-title">${confirmModalEscapeHtml(config.title)}</h2>
                <button type="button" class="aidunite-confirm-dialog__close" aria-label="閉じる">&times;</button>
            </div>
            <div class="aidunite-confirm-dialog__body">
                <p class="${messageClass}">${confirmModalEscapeHtml(config.message)}</p>
            </div>
            <div class="aidunite-confirm-dialog__footer">
                <button type="button" class="btn btn-secondary aidunite-confirm-cancel">${confirmModalEscapeHtml(config.cancelLabel)}</button>
                <button type="button" class="${confirmBtnClass} aidunite-confirm-ok">${confirmModalEscapeHtml(config.confirmLabel)}</button>
            </div>
        </div>
    `;

    doc.body.appendChild(overlay);
    doc.body.appendChild(modal);
    doc.body.classList.add('aidunite-confirm-modal-open');

    const closeBtn = modal.querySelector('.aidunite-confirm-dialog__close');
    const cancelBtn = modal.querySelector('.aidunite-confirm-cancel');
    const confirmBtn = modal.querySelector('.aidunite-confirm-ok');

    const handleConfirm = () => {
        closeConfirmModal(false, null);
        if (typeof config.onConfirm === 'function') {
            config.onConfirm();
        }
    };

    const handleCancel = () => {
        closeConfirmModal(true, config.onCancel);
    };

    if (closeBtn) closeBtn.addEventListener('click', handleCancel);
    if (cancelBtn) cancelBtn.addEventListener('click', handleCancel);
    if (confirmBtn) confirmBtn.addEventListener('click', handleConfirm);

    confirmModalKeyHandler = (e) => {
        if (e.key === 'Escape') {
            e.preventDefault();
            handleCancel();
        }
    };
    doc.addEventListener('keydown', confirmModalKeyHandler);

    requestAnimationFrame(() => {
        if (cancelBtn && typeof cancelBtn.focus === 'function') {
            cancelBtn.focus();
        }
    });
}

if (typeof window !== 'undefined') {
    window.showConfirmModal = showConfirmModal;
    window.closeConfirmModal = closeConfirmModal;
}

if (typeof global !== 'undefined') {
    global.showConfirmModal = showConfirmModal;
    global.closeConfirmModal = closeConfirmModal;
}
