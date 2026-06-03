/**
 * AidUnite AJAXユーティリティ
 *
 * プロジェクト全体で使用されるAJAX処理の共通関数を提供します。
 * 重複していた関数を統一し、保守性を向上させます。
 *
 * @version 1.0.0
 * @created 2024-12
 */

class AidUniteAjaxUtils {

    /**
     * AJAXリクエストを実行
     *
     * @param {Object} options - リクエストオプション
     * @param {string} options.action - WordPressアクション
     * @param {Object} options.data - 送信データ
     * @param {string} options.method - HTTPメソッド
     * @param {Function} options.onSuccess - 成功時のコールバック
     * @param {Function} options.onError - エラー時のコールバック
     * @param {boolean} options.showLoading - ローディング表示
     *
     * @example
     * AidUniteAjaxUtils.request({
     *     action: 'get_schedules',
     *     data: { start_date: '2024-12-01' },
     *     onSuccess: (response) => console.log(response)
     * });
     */
    static request(options = {}) {
        const {
            action,
            data = {},
            method = 'POST',
            onSuccess = null,
            onError = null,
            showLoading = true
        } = options;

        if (!action) {
            console.error('AJAXアクションが指定されていません');
            if (onError) onError('アクションが指定されていません');
            return;
        }

        // ローディング表示
        if (showLoading) {
            this.showLoading();
        }

        // リクエストデータを準備
        const requestData = {
            action: action,
            nonce: this.getNonce(action),
            ...data
        };

        // リクエスト実行
        fetch(this.getAjaxUrl(), {
            method: method,
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams(requestData)
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            this.hideLoading();

            if (data.success) {
                if (onSuccess) onSuccess(data);
            } else {
                const errorMessage = data.message || 'リクエストに失敗しました';
                console.error('AJAXエラー:', errorMessage);
                if (onError) onError(errorMessage);
            }
        })
        .catch(error => {
            this.hideLoading();
            console.error('AJAXリクエストエラー:', error);
            if (onError) onError(error.message || 'ネットワークエラーが発生しました');
        });
    }

    /**
     * GETリクエストを実行
     *
     * @param {Object} options - リクエストオプション
     */
    static get(options = {}) {
        this.request({ ...options, method: 'GET' });
    }

    /**
     * POSTリクエストを実行
     *
     * @param {Object} options - リクエストオプション
     */
    static post(options = {}) {
        this.request({ ...options, method: 'POST' });
    }

    /**
     * スケジュールを取得
     *
     * @param {Object} options - オプション
     */
    static getSchedules(options = {}) {
        const {
            start_date,
            end_date,
            view = 'calendar',
            onSuccess = null,
            onError = null
        } = options;

        this.request({
            action: 'get_schedules',
            data: {
                start_date,
                end_date,
                view
            },
            onSuccess,
            onError
        });
    }

    /**
     * スケジュールを保存
     *
     * @param {Object} options - オプション
     */
    static saveSchedule(options = {}) {
        const {
            schedule_data,
            onSuccess = null,
            onError = null
        } = options;

        this.request({
            action: 'save_schedule',
            data: schedule_data,
            onSuccess,
            onError
        });
    }

    static scheduleRestNonce() {
        return (typeof wpApiSettings !== 'undefined' && wpApiSettings.nonce)
            || (typeof aidunite_messaging !== 'undefined' && aidunite_messaging.nonce)
            || (typeof aiduniteScheduleRest !== 'undefined' && aiduniteScheduleRest.nonce)
            || '';
    }

    static scheduleRestRoot() {
        const root = (typeof aiduniteScheduleRest !== 'undefined' && aiduniteScheduleRest.root)
            ? String(aiduniteScheduleRest.root)
            : ((typeof wpApiSettings !== 'undefined' && wpApiSettings.root) ? String(wpApiSettings.root) : '/wp-json/');
        return root.replace(/\/?$/, '/');
    }

    /**
     * GET schedule-dependencies（削除・重要編集前の確認用。書き込みなし）
     *
     * @param {Object} options
     * @param {string|number} options.schedule_id
     * @param {Function} options.onSuccess - (data) => void  data: { success, delete_gate, dependencies }
     * @param {Function} options.onError - (messageOrObj) => void
     */
    static getScheduleDependencies(options = {}) {
        const {
            schedule_id,
            onSuccess = null,
            onError = null
        } = options || {};
        const id = schedule_id != null ? String(schedule_id).trim() : '';
        if (!id) {
            if (onError) onError('スケジュールIDが指定されていません');
            return;
        }
        const restNonce = this.scheduleRestNonce();
        if (!restNonce) {
            if (onError) onError('認証トークンがありません。ページを再読み込みしてください。');
            return;
        }
        const url = this.scheduleRestRoot() + 'aidunite/v1/schedule-dependencies/' + encodeURIComponent(id);
        fetch(url, {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-WP-Nonce': restNonce
            }
        })
            .then((response) => {
                if (!response.ok) {
                    return response.json().then((j) => {
                        const code = (j && (j.code || j.data?.code)) || '';
                        const msg = (j && (j.message || j.data?.message)) || ('HTTP ' + response.status);
                        const err = new Error(msg);
                        if (code) err.code = code;
                        throw err;
                    });
                }
                return response.json();
            })
            .then((data) => {
                if (onSuccess) onSuccess(data);
            })
            .catch((error) => {
                console.error('schedule-dependencies 取得エラー:', error);
                if (onError) {
                    if (error && error.message) {
                        onError({ message: error.message, code: error.code || '' });
                    } else {
                        onError({ message: 'ネットワークエラーが発生しました', code: '' });
                    }
                }
            });
    }

    /**
     * クイックメモのみ更新（POST update-schedule に post_id + memo のみ。マッチ依存時もサーバーが許可すれば通る）
     *
     * @param {Object} options
     * @param {string|number} options.schedule_id
     * @param {string} options.memo
     */
    static updateScheduleMemoOnly(options = {}) {
        const {
            schedule_id,
            memo = '',
            onSuccess = null,
            onError = null
        } = options || {};
        const postId = schedule_id != null ? String(schedule_id).trim() : '';
        if (!postId) {
            if (onError) onError({ message: 'スケジュールIDが指定されていません', code: '' });
            return;
        }
        const restNonce = this.scheduleRestNonce();
        if (!restNonce) {
            if (onError) onError({ message: '認証トークンがありません。ページを再読み込みしてください。', code: '' });
            return;
        }
        const url = this.scheduleRestRoot() + 'aidunite/v1/update-schedule-v2';
        this.showLoading();
        fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': restNonce
            },
            body: JSON.stringify({
                post_id: parseInt(postId, 10),
                memo: memo != null ? String(memo) : ''
            })
        })
            .then((response) => {
                this.hideLoading();
                if (!response.ok) {
                    return response.json().then((j) => {
                        const code = (j && (j.code || j.data?.code)) || '';
                        const msg = (j && (j.message || j.data?.message)) || ('HTTP ' + response.status);
                        const err = new Error(msg);
                        if (code) err.code = code;
                        throw err;
                    });
                }
                return response.json();
            })
            .then((data) => {
                if (data && (data.success === true || data.success === undefined)) {
                    if (onSuccess) onSuccess(data);
                } else {
                    const em = (data && data.message) || 'メモの保存に失敗しました';
                    const c = (data && (data.code || data.data?.code)) || '';
                    if (onError) onError(c ? { message: em, code: c } : { message: em, code: '' });
                }
            })
            .catch((error) => {
                this.hideLoading();
                console.error('メモ保存エラー:', error);
                if (onError) {
                    const msg = error.message || 'ネットワークエラーが発生しました';
                    if (error.code) onError({ message: msg, code: error.code });
                    else onError({ message: msg, code: '' });
                }
            });
    }

    /**
     * スケジュールを削除
     *
     * @param {Object} options - オプション
     */
    static deleteSchedule(options = {}) {
        // 旧呼び出し互換: deleteSchedule(123) / deleteSchedule('123') のみ渡された場合
        if (options != null && (typeof options === 'number' || typeof options === 'string')) {
            options = { schedule_id: options };
        }
        if (options == null || typeof options !== 'object') {
            options = {};
        }
        const {
            schedule_id,
            onSuccess = null,
            onError = null
        } = options;

        const postId = schedule_id != null ? String(schedule_id).trim() : '';
        if (!postId) {
            if (onError) onError('スケジュールIDが指定されていません');
            return;
        }

        // admin-ajax の delete_schedule は未登録のため、WordPress REST に統一
        const restNonce = this.scheduleRestNonce();
        if (!restNonce) {
            if (onError) onError('認証トークンがありません。ページを再読み込みしてください。');
            return;
        }

        this.showLoading();
        const url = this.scheduleRestRoot() + 'aidunite/v1/delete-schedule-v2';
        fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': restNonce
            },
            body: JSON.stringify({
                post_id: parseInt(postId, 10),
                schedule_id: parseInt(postId, 10),
                nonce: restNonce
            })
        })
            .then((response) => {
                this.hideLoading();
                if (!response.ok) {
                    return response.json().then((j) => {
                        const code = (j && (j.code || j.data?.code)) || '';
                        const msg = (j && (j.message || j.data?.message)) || ('HTTP ' + response.status);
                        const err = new Error(msg);
                        if (code) err.code = code;
                        throw err;
                    });
                }
                return response.json();
            })
            .then((data) => {
                if (data && (data.success === true || data.success === undefined)) {
                    if (onSuccess) onSuccess(data);
                } else {
                    const em = (data && data.message) || 'スケジュールの削除に失敗しました';
                    const c = (data && (data.code || data.data?.code)) || '';
                    if (onError) onError(c ? { message: em, code: c } : em);
                }
            })
            .catch((error) => {
                this.hideLoading();
                console.error('スケジュール削除エラー:', error);
                if (onError) {
                    const msg = error.message || 'ネットワークエラーが発生しました';
                    if (error.code) onError({ message: msg, code: error.code });
                    else onError(msg);
                }
            });
    }

    /**
     * ユーザー情報を取得
     *
     * @param {Object} options - オプション
     */
    static getUserInfo(options = {}) {
        const {
            user_id,
            onSuccess = null,
            onError = null
        } = options;

        this.request({
            action: 'get_user_info',
            data: {
                user_id
            },
            onSuccess,
            onError
        });
    }

    /**
     * チーム情報を取得
     *
     * @param {Object} options - オプション
     */
    static getTeamInfo(options = {}) {
        const {
            team_id,
            onSuccess = null,
            onError = null
        } = options;

        this.request({
            action: 'get_team_info',
            data: {
                team_id
            },
            onSuccess,
            onError
        });
    }

    /**
     * 通知を送信
     *
     * @param {Object} options - オプション
     */
    static sendNotification(options = {}) {
        const {
            user_id,
            type,
            data,
            onSuccess = null,
            onError = null
        } = options;

        this.request({
            action: 'send_notification',
            data: {
                user_id,
                type,
                data: JSON.stringify(data)
            },
            onSuccess,
            onError
        });
    }

    /**
     * WordPress AJAX URLを取得
     *
     * @returns {string} AJAX URL
     */
    static getAjaxUrl() {
        return window.ajaxurl || '/wp-admin/admin-ajax.php';
    }

    /**
     * ノンスを取得
     *
     * @param {string} action - アクション名
     * @returns {string} ノンス
     */
    static getNonce(action) {
        // グローバル変数からノンスを取得
        if (window.ajax_nonce) {
            return window.ajax_nonce;
        }

        // フォームからノンスを取得
        const nonceField = document.querySelector('input[name="_wpnonce"]');
        if (nonceField) {
            return nonceField.value;
        }

        // デフォルトのノンス生成（開発用）
        console.warn('ノンスが見つかりません。セキュリティリスクがあります。');
        return 'dev_nonce';
    }

    /**
     * ローディング表示（全画面中央・テーマの .spinner 等と干渉しないよう専用クラス）
     */
    static showLoading() {
        // 既存のローディングを削除
        this.hideLoading();

        if (!document.getElementById('aidunite-loading-keyframes')) {
            const kf = document.createElement('style');
            kf.id = 'aidunite-loading-keyframes';
            kf.textContent = '@keyframes aidunite-loading-rotate{to{transform:rotate(360deg)}}';
            document.head.appendChild(kf);
        }

        // スケジュール詳細モーダル（.schedule-detail-modal 等 z-index:10000）より前面に出す
        const SYS_OVERLAY_Z = 20000;

        const loading = document.createElement('div');
        loading.id = 'aidunite-loading';
        loading.setAttribute('role', 'status');
        loading.setAttribute('aria-live', 'polite');
        loading.setAttribute('aria-busy', 'true');

        const inner = document.createElement('div');
        inner.className = 'aidunite-loading__box';
        inner.style.cssText = 'display:flex;flex-direction:column;align-items:center;justify-content:center;gap:16px;box-sizing:border-box;';

        const ring = document.createElement('div');
        ring.className = 'aidunite-loading__ring';
        ring.setAttribute('aria-hidden', 'true');
        ring.style.cssText = [
            'box-sizing:border-box',
            'width:44px',
            'height:44px',
            'border:3px solid rgba(255,255,255,0.25)',
            'border-top-color:#2ecc71',
            'border-radius:50%',
            'animation:aidunite-loading-rotate 0.75s linear infinite',
            'flex-shrink:0',
        ].join(';');

        const label = document.createElement('div');
        label.className = 'aidunite-loading__label';
        label.textContent = '読み込み中...';
        label.style.cssText = 'color:#fff;font-size:15px;font-weight:600;margin:0;line-height:1.4;text-align:center;white-space:nowrap;';

        inner.appendChild(ring);
        inner.appendChild(label);
        loading.appendChild(inner);

        loading.style.cssText = [
            'position:fixed',
            'inset:0',
            'width:100%',
            'height:100%',
            'margin:0',
            'padding:24px',
            'box-sizing:border-box',
            'background:rgba(0,0,0,0.5)',
            `z-index:${SYS_OVERLAY_Z}`,
            'display:flex',
            'align-items:center',
            'justify-content:center',
            'pointer-events:auto',
        ].join(';');

        document.body.appendChild(loading);
    }

    /**
     * ローディング非表示
     */
    static hideLoading() {
        const loading = document.getElementById('aidunite-loading');
        if (loading) {
            loading.remove();
        }
    }

    /**
     * エラーメッセージを表示
     *
     * @param {string} message - エラーメッセージ
     * @param {string} type - メッセージタイプ
     */
    static showMessage(message, type = 'error') {
        // 既存のメッセージを削除
        this.hideMessage();

        // メッセージ要素を作成
        const messageDiv = document.createElement('div');
        messageDiv.id = 'aidunite-message';
        messageDiv.className = `aidunite-message aidunite-message-${type}`;
        messageDiv.textContent = message;

        // スタイルを追加
        const bgColor = type === 'error' ? '#f8d7da' : '#d4edda';
        const textColor = type === 'error' ? '#721c24' : '#155724';

        const SYS_MESSAGE_Z = 20100;
        messageDiv.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            background: ${bgColor};
            color: ${textColor};
            border: 1px solid ${type === 'error' ? '#f5c6cb' : '#c3e6cb'};
            border-radius: 4px;
            z-index: ${SYS_MESSAGE_Z};
            max-width: 300px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        `;

        document.body.appendChild(messageDiv);

        // 3秒後に自動削除
        setTimeout(() => {
            this.hideMessage();
        }, 3000);
    }

    /**
     * メッセージ非表示
     */
    static hideMessage() {
        const message = document.getElementById('aidunite-message');
        if (message) {
            message.remove();
        }
    }

    /**
     * 汎用AJAXリクエスト（REST API用）
     *
     * @param {string} url - リクエストURL
     * @param {Object} options - リクエストオプション
     * @returns {Promise} レスポンス
     */
    static makeRequest(url, options = {}) {
        const {
            method = 'GET',
            data = null,
            headers = {},
            onSuccess = null,
            onError = null,
            showLoading = true
        } = options;

        if (showLoading) {
            this.showLoading();
        }

        const requestOptions = {
            method: method,
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': (typeof wpApiSettings !== 'undefined' ? wpApiSettings.nonce : ''),
                ...headers
            }
        };

        if (data && (method === 'POST' || method === 'PUT' || method === 'PATCH')) {
            requestOptions.body = JSON.stringify(data);
        }

        return fetch(url, requestOptions)
            .then(response => {
                if (showLoading) {
                    this.hideLoading();
                }

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                return response.json();
            })
            .then(data => {
                if (onSuccess) onSuccess(data);
                return data;
            })
            .catch(error => {
                if (showLoading) {
                    this.hideLoading();
                }

                console.error('AJAX request failed:', error);
                if (onError) onError(error);
                throw error;
            });
    }

    /**
     * 成功メッセージを表示
     *
     * @param {string} message - メッセージ
     */
    static showSuccess(message) {
        this.showMessage(message, 'success');
    }

    /**
     * エラーメッセージを表示
     *
     * @param {string} message - メッセージ
     */
    static showError(message) {
        this.showMessage(message, 'error');
    }
}

// 後方互換性のためのグローバル関数（段階的移行用）
// 注意: 将来的に削除予定
if (typeof window !== 'undefined') {
    // 既存の関数名での後方互換性
    window.ajaxRequest = AidUniteAjaxUtils.request.bind(AidUniteAjaxUtils);
    window.makeRequest = AidUniteAjaxUtils.makeRequest.bind(AidUniteAjaxUtils);
    window.getSchedules = AidUniteAjaxUtils.getSchedules.bind(AidUniteAjaxUtils);
    window.saveSchedule = AidUniteAjaxUtils.saveSchedule.bind(AidUniteAjaxUtils);
    window.deleteSchedule = AidUniteAjaxUtils.deleteSchedule.bind(AidUniteAjaxUtils);
}

// モジュールエクスポート（ES6モジュール対応）
if (typeof module !== 'undefined' && module.exports) {
    module.exports = AidUniteAjaxUtils;
}

// AMD対応
if (typeof define === 'function' && define.amd) {
    define([], function() {
        return AidUniteAjaxUtils;
    });
}
