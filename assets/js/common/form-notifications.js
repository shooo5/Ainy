/**
 * フォーム通知機能
 * フォーム送信後のトースト通知を管理
 */

// getDocument は js/common/dom-utils.js で定義。未読込時はフォールバック
if (typeof getDocument === 'undefined') {
    function getDocument() {
        if (typeof global !== 'undefined' && global.document) return global.document;
        return typeof document !== 'undefined' ? document : null;
    }
}

class FormNotifications {
    constructor() {
        this.init();
    }

    init() {
        this.bindEvents();
    }

    bindEvents() {
        const doc = getDocument();
        if (!doc) return;

        // スケジュール編集ページでは処理をスキップ
        if (typeof window !== 'undefined' && window.disableFormNotifications) {
            return;
        }

        // フォーム送信イベントの監視
        doc.addEventListener('submit', (e) => {
            if (e.target.classList.contains('ajax-form')) {
                this.handleFormSubmit(e);
            }
        });
    }

    handleFormSubmit(e) {
        e.preventDefault();

        const form = e.target;
        const formData = new FormData(form);
        const submitButton = form.querySelector('.submit-button');
        const loadingElement = form.querySelector('.loading');
        const errorElement = form.querySelector('.error-message');
        const successElement = form.querySelector('.success-message');

        // ローディングスピナーを表示
        let loadingState = null;

        if (submitButton && window.loadingSpinnerManager) {
            loadingState = window.loadingSpinnerManager.setupFormLoading(form, '.submit-button', '送信中...');
        } else if (submitButton) {
            submitButton.disabled = true;
            submitButton.textContent = '送信中...';
        } else {
            // 送信ボタンが見つからない場合
            if (loadingElement) {
                loadingElement.style.display = 'block';
            }
        }

        // エラーメッセージをクリア
        if (errorElement) {
            errorElement.style.display = 'none';
            errorElement.textContent = '';
        }

        // 成功メッセージをクリア
        if (successElement) {
            successElement.style.display = 'none';
            successElement.textContent = '';
        }

        // AJAX送信
        fetch(ajaxurl || '/wp-admin/admin-ajax.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(response => {
            // レスポンスの確認
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            // ローディング要素を非表示
            if (loadingElement) {
                loadingElement.style.display = 'none';
            }

            // 新しいローディングスピナーを終了
            if (loadingState && window.loadingSpinnerManager) {
                window.loadingSpinnerManager.clearFormLoading(loadingState);
            }

            if (data.success) {
                // 成功処理
                const message = data.data.message || '処理が完了しました';
                this.showToast(message, 'success');

                if (successElement) {
                    successElement.textContent = message;
                    successElement.style.display = 'block';
                }

                // リダイレクト処理
                if (data.data.redirect) {
                    setTimeout(() => {
                        window.location.href = data.data.redirect;
                    }, 1500);
                }
            } else {
                // エラー処理
                let errorMessage = 'エラーが発生しました';

                if (data.data && data.data.code) {
                    switch (data.data.code) {
                        case 'permission_denied':
                            errorMessage = '権限がありません';
                            break;
                        case 'invalid_nonce':
                            errorMessage = 'セキュリティエラーが発生しました';
                            break;
                        case 'payment_required':
                            errorMessage = '決済が必要です';
                            break;
                        default:
                            errorMessage = data.data.message || errorMessage;
                    }
                } else if (data.data && data.data.message) {
                    errorMessage = data.data.message;
                }

                this.showToast(errorMessage, 'error');

                if (errorElement) {
                    errorElement.textContent = errorMessage;
                    errorElement.style.display = 'block';
                }
            }
        })
        .catch(error => {
            // エラー処理
            let errorMessage = 'エラーが発生しました';

            if (error.name === 'TypeError' && error.message.includes('fetch')) {
                errorMessage = 'ネットワークエラーが発生しました';
            } else if (error.message.includes('HTTP error')) {
                errorMessage = 'サーバーエラーが発生しました';
            }

            this.showToast(errorMessage, 'error');

            if (errorElement) {
                errorElement.textContent = errorMessage;
                errorElement.style.display = 'block';
            }
        })
        .finally(() => {
            // 送信ボタンを再有効化
            if (submitButton && !window.loadingSpinnerManager) {
                submitButton.disabled = false;
                submitButton.textContent = '送信';
            }

            // ローディング要素を非表示（エラー時）
            if (loadingElement) {
                loadingElement.style.display = 'none';
            }

            // 新しいローディングスピナーを終了（エラー時）
            if (loadingState && window.loadingSpinnerManager) {
                window.loadingSpinnerManager.clearFormLoading(loadingState);
            }
        });
    }

    /**
     * トースト表示（統一 API: showToastNotification のみ）
     */
    showToast(message, type = 'info') {
        if (typeof showToastNotification === 'function') {
            showToastNotification(message, type, { duration: type === 'error' ? 5000 : 3000 });
            return;
        }
        console.warn('[FormNotifications] showToastNotification is not loaded');
    }
}

// ページ読み込み完了後に初期化
// 重複読み込み対策: 既に初期化済みの場合はスキップ
if (!window._formNotificationsInitialized) {
const docForEvent = getDocument();
if (docForEvent && typeof docForEvent.addEventListener === 'function') {
    docForEvent.addEventListener('DOMContentLoaded', function() {
        const formNotifications = new FormNotifications();

        // グローバルに公開（他のスクリプトからアクセス可能）
        if (typeof window !== 'undefined') {
            window.formNotifications = formNotifications;
        }
    });
    }
    window._formNotificationsInitialized = true;
}

// テスト用にグローバルに公開（モジュール読み込み時に確実に実行される）
if (typeof global !== 'undefined') {
    global.FormNotifications = FormNotifications;
}

