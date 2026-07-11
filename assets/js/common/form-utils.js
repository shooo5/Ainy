/**
 * AidUnite フォームユーティリティ
 *
 * プロジェクト全体で使用されるフォーム関連の共通関数を提供します。
 * 重複していた関数を統一し、保守性を向上させます。
 *
 * @version 1.0.0
 * @created 2024-12
 */

class AidUniteFormUtils {

    /**
     * フォームをバリデーション
     *
     * @param {HTMLFormElement} form - フォーム要素
     * @param {Object} rules - バリデーションルール
     * @returns {Object} バリデーション結果
     *
     * @example
     * const result = AidUniteFormUtils.validateForm(form, {
     *     required: ['name', 'email'],
     *     email: ['email'],
     *     minLength: { password: 8 }
     * });
     */
    static validateForm(form, rules = {}) {
        const errors = [];
        const formData = new FormData(form);

        // 必須項目チェック
        if (rules.required) {
            rules.required.forEach(field => {
                const value = formData.get(field);
                if (!value || value.trim() === '') {
                    errors.push(`${this.getFieldLabel(field)}は必須です`);
                }
            });
        }

        // メールアドレスチェック
        if (rules.email) {
            rules.email.forEach(field => {
                const value = formData.get(field);
                if (value && !this.isValidEmail(value)) {
                    errors.push(`${this.getFieldLabel(field)}の形式が正しくありません`);
                }
            });
        }

        // 最小文字数チェック
        if (rules.minLength) {
            Object.entries(rules.minLength).forEach(([field, minLength]) => {
                const value = formData.get(field);
                if (value && value.length < minLength) {
                    errors.push(`${this.getFieldLabel(field)}は${minLength}文字以上で入力してください`);
                }
            });
        }

        // 最大文字数チェック
        if (rules.maxLength) {
            Object.entries(rules.maxLength).forEach(([field, maxLength]) => {
                const value = formData.get(field);
                if (value && value.length > maxLength) {
                    errors.push(`${this.getFieldLabel(field)}は${maxLength}文字以下で入力してください`);
                }
            });
        }

        // パスワード確認チェック
        if (rules.passwordConfirm) {
            const password = formData.get(rules.passwordConfirm.password);
            const confirm = formData.get(rules.passwordConfirm.confirm);
            if (password && confirm && password !== confirm) {
                errors.push('パスワードが一致しません');
            }
        }

        return {
            valid: errors.length === 0,
            errors: errors
        };
    }

    /**
     * フォームデータを取得
     *
     * @param {HTMLFormElement} form - フォーム要素
     * @returns {Object} フォームデータ
     */
    static getFormData(form) {
        const formData = new FormData(form);
        const data = {};

        for (const [key, value] of formData.entries()) {
            data[key] = value;
        }

        return data;
    }

    /**
     * フォームをリセット
     *
     * @param {HTMLFormElement} form - フォーム要素
     */
    static resetForm(form) {
        form.reset();
        this.clearFormErrors(form);
    }

    /**
     * フォームエラーをクリア
     *
     * @param {HTMLFormElement} form - フォーム要素
     */
    static clearFormErrors(form) {
        // エラーメッセージを削除
        const errorMessages = form.querySelectorAll('.error-message');
        errorMessages.forEach(msg => msg.remove());

        // エラークラスを削除
        const errorFields = form.querySelectorAll('.error');
        errorFields.forEach(field => {
            field.classList.remove('error');
        });
    }

    /**
     * フォームエラーを表示
     *
     * @param {HTMLFormElement} form - フォーム要素
     * @param {Array} errors - エラーメッセージ配列
     */
    static showFormErrors(form, errors) {
        this.clearFormErrors(form);

        errors.forEach(error => {
            // エラーメッセージを表示
            const errorDiv = document.createElement('div');
            errorDiv.className = 'error-message';
            errorDiv.textContent = error;

            // フォームの先頭に追加
            form.insertBefore(errorDiv, form.firstChild);
        });
    }

    /**
     * フィールドエラーを表示
     *
     * @param {HTMLElement} field - フィールド要素
     * @param {string} message - エラーメッセージ
     */
    static showFieldError(field, message) {
        // 既存のエラーメッセージを削除
        const existingError = field.parentNode.querySelector('.field-error');
        if (existingError) {
            existingError.remove();
        }

        // エラークラスを追加
        field.classList.add('error');

        // エラーメッセージを表示
        const errorDiv = document.createElement('div');
        errorDiv.className = 'field-error error-message';
        errorDiv.textContent = message;

        field.parentNode.insertBefore(errorDiv, field.nextSibling);
    }

    /**
     * フィールドエラーをクリア
     *
     * @param {HTMLElement} field - フィールド要素
     */
    static clearFieldError(field) {
        // エラークラスを削除
        field.classList.remove('error');

        // エラーメッセージを削除
        const errorDiv = field.parentNode.querySelector('.field-error');
        if (errorDiv) {
            errorDiv.remove();
        }
    }

    /**
     * メールアドレスの妥当性をチェック
     *
     * @param {string} email - メールアドレス
     * @returns {boolean} 妥当かどうか
     */
    static isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    /**
     * パスワードの強度をチェック
     *
     * @param {string} password - パスワード
     * @returns {Object} 強度チェック結果
     */
    static checkPasswordStrength(password) {
        const result = {
            score: 0,
            feedback: []
        };

        if (password.length >= 8) {
            result.score += 1;
        } else {
            result.feedback.push('8文字以上で入力してください');
        }

        if (/[a-z]/.test(password)) {
            result.score += 1;
        } else {
            result.feedback.push('小文字を含めてください');
        }

        if (/[A-Z]/.test(password)) {
            result.score += 1;
        } else {
            result.feedback.push('大文字を含めてください');
        }

        if (/[0-9]/.test(password)) {
            result.score += 1;
        } else {
            result.feedback.push('数字を含めてください');
        }

        if (/[^a-zA-Z0-9]/.test(password)) {
            result.score += 1;
        } else {
            result.feedback.push('記号を含めてください');
        }

        return result;
    }

    /**
     * フィールドラベルを取得
     *
     * @param {string} fieldName - フィールド名
     * @returns {string} ラベル
     */
    static getFieldLabel(fieldName) {
        const labels = {
            'user_name': 'ユーザー名',
            'user_email': 'メールアドレス',
            'password': 'パスワード',
            'password_confirm': 'パスワード確認',
            'team_name': 'チーム名',
            'schedule_type': 'スケジュール種別',
            'start_time': '開始時間',
            'end_time': '終了時間',
            'place': '場所',
            'content': '内容'
        };

        return labels[fieldName] || fieldName;
    }

    /**
     * フォーム送信を処理
     *
     * @param {HTMLFormElement} form - フォーム要素
     * @param {Object} options - オプション
     */
    static handleFormSubmit(form, options = {}) {
        const {
            validationRules = {},
            onSubmit = null,
            onSuccess = null,
            onError = null
        } = options;

        form.addEventListener('submit', (e) => {
            e.preventDefault();

            // バリデーション
            const validation = this.validateForm(form, validationRules);
            if (!validation.valid) {
                this.showFormErrors(form, validation.errors);
                if (onError) onError(validation.errors);
                return;
            }

            // フォームデータを取得
            const formData = this.getFormData(form);

            // 送信処理
            if (onSubmit) {
                onSubmit(formData);
            } else {
                // デフォルトの送信処理
                this.submitForm(form, formData, { onSuccess, onError });
            }
        });
    }

    /**
     * フォームを送信
     *
     * @param {HTMLFormElement} form - フォーム要素
     * @param {Object} formData - フォームデータ
     * @param {Object} options - オプション
     */
    static submitForm(form, formData, options = {}) {
        const { onSuccess, onError } = options;

        // AJAX送信
        const xhr = new XMLHttpRequest();
        xhr.open('POST', form.action || window.location.href);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

        xhr.onload = function() {
            if (xhr.status === 200) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        if (onSuccess) onSuccess(response);
                    } else {
                        if (onError) onError(response.message || '送信に失敗しました');
                    }
                } catch (e) {
                    if (onError) onError('レスポンスの解析に失敗しました');
                }
            } else {
                if (onError) onError('サーバーエラーが発生しました');
            }
        };

        xhr.onerror = function() {
            if (onError) onError('ネットワークエラーが発生しました');
        };

        // フォームデータを送信
        const params = new URLSearchParams(formData);
        xhr.send(params);
    }
}

// 後方互換性のためのグローバル関数（段階的移行用）
// 注意: 将来的に削除予定
if (typeof window !== 'undefined') {
    // 既存の関数名での後方互換性
    window.validateForm = AidUniteFormUtils.validateForm.bind(AidUniteFormUtils);
    window.getFormData = AidUniteFormUtils.getFormData.bind(AidUniteFormUtils);
    window.resetForm = AidUniteFormUtils.resetForm.bind(AidUniteFormUtils);
}

// モジュールエクスポート（ES6モジュール対応）
if (typeof module !== 'undefined' && module.exports) {
    module.exports = AidUniteFormUtils;
}

// AMD対応
if (typeof define === 'function' && define.amd) {
    define([], function() {
        return AidUniteFormUtils;
    });
}
