/**
 * AidUnite APIレスポンスハンドラー
 *
 * 統一されたAPIレスポンス形式とWordPress標準形式の両方を処理する互換レイヤー
 * 段階的に新形式に移行するための後方互換性を提供
 *
 * @version 1.0.0
 * @created 2026-01-13
 */

class AidUniteApiResponseHandler {
    /**
     * APIレスポンスを処理（旧形式と新形式の両方に対応）
     *
     * @param {Object} response - APIレスポンス
     * @param {Function} onSuccess - 成功時のコールバック
     * @param {Function} onError - エラー時のコールバック
     * @returns {Object} 正規化されたレスポンス
     */
    static handleResponse(response, onSuccess = null, onError = null) {
        // レスポンス形式を正規化
        const normalized = this.normalizeResponse(response);

        if (normalized.success) {
            if (onSuccess) {
                onSuccess(normalized.data, normalized.message);
            }
            return normalized;
        } else {
            // エラー処理
            this.handleError(normalized, onError);
            return normalized;
        }
    }

    /**
     * レスポンスを正規化（旧形式と新形式の両方に対応）
     *
     * @param {Object} response - APIレスポンス
     * @returns {Object} 正規化されたレスポンス
     */
    static normalizeResponse(response) {
        // WordPress標準形式: { success: true/false, data: {...} }
        if (response.hasOwnProperty('success') && response.hasOwnProperty('data')) {
            return {
                success: response.success,
                message: response.data?.message || '',
                data: response.data?.data || response.data,
                errors: response.data?.errors || null,
                urgency: response.data?.urgency || 'normal',
                code: response.data?.code || null
            };
        }

        // 統一形式: { success: true/false, message: '', data: {}, urgency: '', errors: {} }
        if (response.hasOwnProperty('success') && response.hasOwnProperty('message')) {
            return {
                success: response.success,
                message: response.message || '',
                data: response.data || null,
                errors: response.errors || null,
                urgency: response.urgency || 'normal',
                code: response.code || null
            };
        }

        // フォールバック: 成功とみなす
        return {
            success: true,
            message: '',
            data: response,
            errors: null,
            urgency: 'normal',
            code: null
        };
    }

    /**
     * エラーを処理
     *
     * @param {Object} normalized - 正規化されたレスポンス
     * @param {Function} onError - エラー時のコールバック
     */
    static handleError(normalized, onError = null) {
        const { message, errors, urgency, code } = normalized;

        // エラーメッセージを表示
        if (urgency === 'critical') {
            // 緊急エラー: アラート表示
            alert(message || '重大なエラーが発生しました');
        } else if (errors && Object.keys(errors).length > 0) {
            // バリデーションエラー: インライン表示
            this.showValidationErrors(errors);
        } else {
            // 通常エラー: トースト通知
            if (typeof showToastNotification !== 'undefined') {
                showToastNotification(message || 'エラーが発生しました', 'error');
            } else {
                console.error('API Error:', message, errors, code);
            }
        }

        if (onError) {
            onError(message, errors, urgency, code);
        }
    }

    /**
     * バリデーションエラーを表示
     *
     * @param {Object} errors - エラーオブジェクト { field_name: 'エラーメッセージ' }
     */
    static showValidationErrors(errors) {
        Object.keys(errors).forEach(fieldName => {
            const field = document.querySelector(`[name="${fieldName}"], #${fieldName}`);
            if (field) {
                // 既存のエラーメッセージを削除
                const existingError = field.parentElement.querySelector('.validation-error');
                if (existingError) {
                    existingError.remove();
                }

                // エラーメッセージを追加
                const errorElement = document.createElement('div');
                errorElement.className = 'validation-error';
                errorElement.textContent = errors[fieldName];
                errorElement.style.color = '#dc3545';
                errorElement.style.fontSize = '0.875rem';
                errorElement.style.marginTop = '0.25rem';

                field.parentElement.appendChild(errorElement);
                field.classList.add('error');
            }
        });
    }

    /**
     * 成功メッセージを表示
     *
     * @param {string} message - メッセージ
     * @param {string} type - 通知タイプ（'success', 'info'）
     */
    static showSuccess(message, type = 'success') {
        if (typeof showToastNotification !== 'undefined') {
            showToastNotification(message, type);
        } else {
            console.log('Success:', message);
        }
    }

    /**
     * AJAXリクエストを実行（統一形式対応）
     *
     * @param {Object} options - リクエストオプション
     * @returns {Promise} 正規化されたレスポンス
     */
    static async request(options = {}) {
        const {
            url,
            method = 'POST',
            data = {},
            headers = {}
        } = options;

        try {
            const response = await fetch(url, {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': (typeof wpApiSettings !== 'undefined' ? wpApiSettings.nonce : ''),
                    ...headers
                },
                body: method !== 'GET' ? JSON.stringify(data) : null
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const jsonResponse = await response.json();
            return this.normalizeResponse(jsonResponse);
        } catch (error) {
            return {
                success: false,
                message: error.message || 'ネットワークエラーが発生しました',
                data: null,
                errors: null,
                urgency: 'normal',
                code: 'network_error'
            };
        }
    }
}

// グローバルに公開
if (typeof window !== 'undefined') {
    window.AidUniteApiResponseHandler = AidUniteApiResponseHandler;
}
