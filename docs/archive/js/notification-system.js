/**
 * Ainy統一通知システム
 *
 * 3種類の通知タイプ（Inline/Banner/Snackbar）を統一管理
 * 「ユーザーの状況で最小のストレスで伝わること」を最優先
 *
 * @version 1.0.0
 * @created 2026-01-13
 */

class AidUniteNotification {

    /**
     * 通知を表示（自動選択）
     *
     * @param {Object} response - APIレスポンス
     * @param {Object} options - オプション
     * @param {HTMLElement} options.formElement - フォーム要素（Inline通知用）
     * @param {string} options.urgency - 緊急度（'critical', 'urgent', 'normal'）
     */
    static show(response, options = {}) {
        // 1. errorsがある場合 → Inline優先
        if (response.errors && Object.keys(response.errors).length > 0) {
            this.inline(response.errors, options.formElement);

            // 全体メッセージも表示（Banner or Snackbar）
            if (response.message) {
                const urgency = response.urgency || options.urgency || 'normal';
                if (urgency === 'critical' || urgency === 'urgent') {
                    this.banner(response.message, 'error');
                } else {
                    this.snackbar(response.message, 'error');
                }
            }
            return;
        }

        // 2. 緊急度が高い場合 → Banner
        const urgency = response.urgency || options.urgency || 'normal';
        if (urgency === 'critical' || urgency === 'urgent') {
            this.banner(response.message, response.type || 'error');
            return;
        }

        // 3. デフォルト → Snackbar
        const type = response.success ? 'success' : (response.type || 'error');
        this.snackbar(response.message, type);
    }

    /**
     * Inline通知を表示（フォームエラー用）
     *
     * @param {Object} errors - エラーオブジェクト {fieldName: 'エラーメッセージ'}
     * @param {HTMLElement} formElement - フォーム要素
     */
    static inline(errors, formElement) {
        if (!formElement || !errors) return;

        // 既存のInlineエラーをクリア
        this.clearInlineErrors(formElement);

        // 各エラーを表示
        Object.keys(errors).forEach(fieldName => {
            const field = formElement.querySelector(`[name="${fieldName}"]`);
            if (field) {
                this.showInlineError(field, errors[fieldName]);
            }
        });
    }

    /**
     * 個別フィールドのInlineエラーを表示
     *
     * @param {HTMLElement} field - フィールド要素
     * @param {string} message - エラーメッセージ
     */
    static showInlineError(field, message) {
        // 既存のエラーを削除
        const existingError = field.parentElement.querySelector('.aidunite-inline-error');
        if (existingError) {
            existingError.remove();
        }

        // エラー要素を作成
        const errorDiv = document.createElement('div');
        errorDiv.className = 'aidunite-inline-error';
        errorDiv.textContent = message;
        errorDiv.id = `aidunite-error-${field.name || field.id || Date.now()}`;
        errorDiv.setAttribute('role', 'alert');
        errorDiv.setAttribute('aria-live', 'polite');

        // フィールドの直後に挿入
        field.parentElement.insertBefore(errorDiv, field.nextSibling);

        // フィールドにエラー状態を追加
        field.classList.add('aidunite-field-error');
        field.setAttribute('aria-invalid', 'true');
        field.setAttribute('aria-describedby', errorDiv.id);

        // フィールドにフォーカス（スクロール）
        // prefers-reduced-motionを考慮
        const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        field.scrollIntoView({
            behavior: prefersReducedMotion ? 'auto' : 'smooth',
            block: 'center'
        });
        field.focus();

        // blurイベントで再バリデーション（Phase 2仕様に基づく）
        const handleBlur = () => {
            // フィールドが修正されたらエラーをクリア
            if (field.value && field.value.trim() !== '') {
                this.clearInlineError(field);
            }
        };

        // 既存のイベントリスナーを削除してから追加
        field.removeEventListener('blur', field._aiduniteBlurHandler);
        field._aiduniteBlurHandler = handleBlur;
        field.addEventListener('blur', handleBlur);
    }

    /**
     * 個別フィールドのInlineエラーをクリア
     *
     * @param {HTMLElement} field - フィールド要素
     */
    static clearInlineError(field) {
        const errorDiv = field.parentElement.querySelector('.aidunite-inline-error');
        if (errorDiv) {
            errorDiv.remove();
        }

        field.classList.remove('aidunite-field-error');
        field.removeAttribute('aria-invalid');
        field.removeAttribute('aria-describedby');

        // blurイベントリスナーを削除
        if (field._aiduniteBlurHandler) {
            field.removeEventListener('blur', field._aiduniteBlurHandler);
            delete field._aiduniteBlurHandler;
        }
    }

    /**
     * Inlineエラーをクリア
     *
     * @param {HTMLElement} formElement - フォーム要素
     */
    static clearInlineErrors(formElement) {
        if (!formElement) return;

        // すべてのInlineエラーを削除
        const errors = formElement.querySelectorAll('.aidunite-inline-error');
        errors.forEach(error => error.remove());

        // すべてのフィールドからエラー状態を削除
        const errorFields = formElement.querySelectorAll('.aidunite-field-error');
        errorFields.forEach(field => {
            this.clearInlineError(field);
        });
    }

    /**
     * Banner通知を表示（緊急・重要情報用）
     *
     * @param {string} message - メッセージ
     * @param {string} type - タイプ（'error', 'warning', 'info'）
     */
    static banner(message, type = 'error') {
        // 既存のBannerを削除
        this.clearBanner();

        // Banner要素を作成
        const banner = document.createElement('div');
        banner.className = `aidunite-banner aidunite-banner-${type}`;
        banner.setAttribute('role', 'alert');
        banner.setAttribute('aria-live', 'assertive');
        banner.setAttribute('aria-atomic', 'true');

        const icon = this.getIcon(type);

        banner.innerHTML = `
            <div class="aidunite-banner-content">
                <span class="aidunite-banner-icon" aria-hidden="true">${icon}</span>
                <span class="aidunite-banner-message">${this.escapeHtml(message)}</span>
            </div>
            <button class="aidunite-banner-close" aria-label="閉じる" type="button" tabindex="0">×</button>
        `;

        document.body.insertBefore(banner, document.body.firstChild);

        // アニメーション（prefers-reduced-motionを考慮）
        const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (!prefersReducedMotion) {
            requestAnimationFrame(() => {
                banner.classList.add('aidunite-banner-show');
            });
        } else {
            banner.classList.add('aidunite-banner-show');
        }

        // 閉じるボタンのイベント
        const closeBtn = banner.querySelector('.aidunite-banner-close');
        closeBtn.addEventListener('click', () => {
            this.closeBanner(banner);
        });

        // ESCキーで閉じる（キーボード操作）
        const handleKeyDown = (e) => {
            if (e.key === 'Escape' || e.key === 'Esc') {
                this.closeBanner(banner);
                document.removeEventListener('keydown', handleKeyDown);
            }
        };
        document.addEventListener('keydown', handleKeyDown);

        // 自動閉じる（オプション、デフォルトはなし）
        // 緊急通知はユーザーが閉じるまで残す

        // フォーカス管理：Bannerが表示されたら閉じるボタンにフォーカス
        requestAnimationFrame(() => {
            closeBtn.focus();
        });
    }

    /**
     * Snackbar通知を表示（通常の成功/失敗用）
     *
     * @param {string} message - メッセージ
     * @param {string} type - タイプ（'success', 'error', 'info', 'warning'）
     * @param {number} duration - 表示時間（ミリ秒、デフォルト: success=3000, error=5000）
     */
    static snackbar(message, type = 'success', duration = null) {
        // 既存のSnackbarを削除
        this.clearSnackbar();

        // デフォルトの表示時間
        if (duration === null) {
            duration = type === 'success' ? 3000 : 5000;
        }

        // Snackbar要素を作成
        const snackbar = document.createElement('div');
        snackbar.className = `aidunite-snackbar aidunite-snackbar-${type}`;
        snackbar.setAttribute('role', 'status');
        snackbar.setAttribute('aria-live', 'polite');
        snackbar.setAttribute('aria-atomic', 'true');
        snackbar.setAttribute('tabindex', '-1');

        const icon = this.getIcon(type);

        snackbar.innerHTML = `
            <span class="aidunite-snackbar-icon" aria-hidden="true">${icon}</span>
            <span class="aidunite-snackbar-message">${this.escapeHtml(message)}</span>
        `;

        document.body.appendChild(snackbar);

        // アニメーション（prefers-reduced-motionを考慮）
        const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (!prefersReducedMotion) {
            requestAnimationFrame(() => {
                snackbar.classList.add('aidunite-snackbar-show');
            });
        } else {
            snackbar.classList.add('aidunite-snackbar-show');
        }

        // 自動削除
        const timeoutId = setTimeout(() => {
            this.closeSnackbar(snackbar);
        }, duration);

        // クリックで閉じる
        snackbar.addEventListener('click', () => {
            clearTimeout(timeoutId);
            this.closeSnackbar(snackbar);
        });

        // ESCキーで閉じる（キーボード操作）
        const handleKeyDown = (e) => {
            if (e.key === 'Escape' || e.key === 'Esc') {
                clearTimeout(timeoutId);
                this.closeSnackbar(snackbar);
                document.removeEventListener('keydown', handleKeyDown);
            }
        };
        document.addEventListener('keydown', handleKeyDown);
    }

    /**
     * Bannerを閉じる
     */
    static closeBanner(banner) {
        if (!banner) return;

        const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const animationDuration = prefersReducedMotion ? 0 : 300;

        banner.classList.remove('aidunite-banner-show');
        banner.classList.add('aidunite-banner-hide');

        // will-changeを削除（パフォーマンス最適化）
        banner.style.willChange = 'auto';

        setTimeout(() => {
            if (banner.parentElement) {
                banner.remove();
            }
        }, animationDuration);
    }

    /**
     * Snackbarを閉じる
     */
    static closeSnackbar(snackbar) {
        if (!snackbar) return;

        const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const animationDuration = prefersReducedMotion ? 0 : 300;

        snackbar.classList.remove('aidunite-snackbar-show');
        snackbar.classList.add('aidunite-snackbar-hide');

        // will-changeを削除（パフォーマンス最適化）
        snackbar.style.willChange = 'auto';

        setTimeout(() => {
            if (snackbar.parentElement) {
                snackbar.remove();
            }
        }, animationDuration);
    }

    /**
     * すべてのBannerをクリア
     */
    static clearBanner() {
        const banners = document.querySelectorAll('.aidunite-banner');
        banners.forEach(banner => banner.remove());
    }

    /**
     * すべてのSnackbarをクリア
     */
    static clearSnackbar() {
        const snackbars = document.querySelectorAll('.aidunite-snackbar');
        snackbars.forEach(snackbar => snackbar.remove());
    }

    /**
     * アイコンを取得
     *
     * @param {string} type - タイプ
     * @returns {string} アイコン（絵文字）
     */
    static getIcon(type) {
        const icons = {
            success: '✅',
            error: '⚠️',
            warning: '⚠️',
            info: 'ℹ️'
        };
        return icons[type] || 'ℹ️';
    }

    /**
     * HTMLエスケープ
     *
     * @param {string} text - エスケープするテキスト
     * @returns {string} エスケープされたテキスト
     */
    static escapeHtml(text) {
        if (typeof text !== 'string') {
            return '';
        }
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// グローバルに公開
if (typeof window !== 'undefined') {
    window.AidUniteNotification = AidUniteNotification;
}

// テスト用にグローバルに公開
if (typeof global !== 'undefined') {
    global.AidUniteNotification = AidUniteNotification;
}
