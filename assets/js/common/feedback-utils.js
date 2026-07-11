/**
 * OS alert/confirm 禁止 — トースト・確認モーダルへの統一ヘルパー
 *
 * aiduniteToast(message, type)
 * aiduniteConfirm({ message, title, confirmLabel, confirmVariant, onConfirm, onCancel }) → Promise<boolean>
 */
(function initAiduniteFeedback(global) {
    if (!global) return;

    function toast(message, type, options) {
        if (typeof showToastNotification === 'function') {
            showToastNotification(message, type || 'info', options);
            return;
        }
        console.warn('[AidUnite]', type || 'info', message);
    }

    function confirmModal(opts) {
        const o = typeof opts === 'string' ? { message: opts } : (opts || {});
        return new Promise((resolve) => {
            if (typeof showConfirmModal !== 'function') {
                console.warn('[AidUnite] showConfirmModal unavailable:', o.message);
                resolve(false);
                return;
            }
            showConfirmModal({
                title: o.title || '確認',
                message: o.message || '',
                confirmLabel: o.confirmLabel || 'OK',
                cancelLabel: o.cancelLabel || 'キャンセル',
                confirmVariant: o.confirmVariant || 'danger',
                messageAlign: o.messageAlign || '',
                onConfirm: () => {
                    if (typeof o.onConfirm === 'function') o.onConfirm();
                    resolve(true);
                },
                onCancel: () => {
                    if (typeof o.onCancel === 'function') o.onCancel();
                    resolve(false);
                }
            });
        });
    }

    function readConfirmVariant(el) {
        const variant = el.getAttribute('data-aidunite-confirm-variant');
        if (variant === 'primary' || variant === 'success' || variant === 'danger') {
            return variant;
        }
        return 'danger';
    }

    function readConfirmMessageAlign(el) {
        const align = el.getAttribute('data-aidunite-confirm-message-align');
        return align === 'center' ? 'center' : '';
    }

    function bindConfirmForms() {
        const doc = typeof document !== 'undefined' ? document : null;
        if (!doc) return;
        doc.querySelectorAll('form[data-aidunite-confirm]').forEach((form) => {
            if (form.getAttribute('data-aidunite-confirm-bound') === '1') return;
            form.setAttribute('data-aidunite-confirm-bound', '1');
            form.addEventListener('submit', (e) => {
                if (form.getAttribute('data-aidunite-confirm-approved') === '1') {
                    form.removeAttribute('data-aidunite-confirm-approved');
                    return;
                }
                e.preventDefault();
                e.stopImmediatePropagation();
                const msg = form.getAttribute('data-aidunite-confirm') || '実行しますか？';
                const title = form.getAttribute('data-aidunite-confirm-title') || '確認';
                const label = form.getAttribute('data-aidunite-confirm-label') || '実行する';
                confirmModal({
                    title,
                    message: msg,
                    confirmLabel: label,
                    confirmVariant: readConfirmVariant(form),
                    messageAlign: readConfirmMessageAlign(form),
                    onConfirm: () => {
                        form.setAttribute('data-aidunite-confirm-approved', '1');
                        const sub = e.submitter;
                        if (typeof form.requestSubmit === 'function') {
                            form.requestSubmit(sub || undefined);
                        } else {
                            form.submit();
                        }
                    }
                });
            }, true);
        });
    }

    function bindConfirmLinks() {
        const doc = typeof document !== 'undefined' ? document : null;
        if (!doc) return;
        doc.querySelectorAll('a[data-aidunite-confirm], button[data-aidunite-confirm]:not([type="submit"])').forEach((el) => {
            if (el.getAttribute('data-aidunite-confirm-bound') === '1') return;
            el.setAttribute('data-aidunite-confirm-bound', '1');
            el.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                const msg = el.getAttribute('data-aidunite-confirm') || '実行しますか？';
                const title = el.getAttribute('data-aidunite-confirm-title') || '確認';
                const label = el.getAttribute('data-aidunite-confirm-label') || 'OK';
                confirmModal({
                    title,
                    message: msg,
                    confirmLabel: label,
                    confirmVariant: readConfirmVariant(el),
                    messageAlign: readConfirmMessageAlign(el),
                    onConfirm: () => {
                        if (el.tagName === 'A') {
                            const href = el.getAttribute('href');
                            if (href) global.location.href = href;
                        } else {
                            el.dispatchEvent(new CustomEvent('aidunite-confirmed', { bubbles: true }));
                        }
                    }
                });
            });
        });
    }

    function initBindings() {
        bindConfirmForms();
        bindConfirmLinks();
    }

    global.aiduniteToast = toast;
    global.aiduniteConfirm = confirmModal;

    if (typeof document !== 'undefined') {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initBindings);
        } else {
            initBindings();
        }
    }
})(typeof window !== 'undefined' ? window : (typeof global !== 'undefined' ? global : null));
