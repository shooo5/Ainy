/**
 * AidUnite スケジュール読み込みユーティリティ
 *
 * プロジェクト全体で使用されるスケジュール読み込み関連の共通関数を提供します。
 * 重複していた関数を統一し、保守性を向上させます。
 *
 * @version 1.0.0
 * @created 2024-12
 */

class AidUniteScheduleLoader {

    /**
     * スケジュールを読み込み
     *
     * @param {Object} options - 読み込みオプション
     * @param {string} options.view - 表示形式 ('calendar' | 'list' | 'cards')
     * @param {Date} options.date - 基準日
     * @param {string} options.period - 期間 ('month' | 'week' | 'day')
     * @param {Function} options.onSuccess - 成功時のコールバック
     * @param {Function} options.onError - エラー時のコールバック
     *
     * @example
     * AidUniteScheduleLoader.loadSchedules({
     *     view: 'calendar',
     *     date: new Date(),
     *     period: 'month'
     * });
     */
    static loadSchedules(options = {}) {
        const {
            view = 'calendar',
            date = new Date(),
            period = 'month',
            onSuccess = null,
            onError = null
        } = options;

        console.log('📅 スケジュール読み込み開始:', { view, period, date });

        try {
            // 期間に応じて読み込み方法を選択
            switch (period) {
                case 'month':
                    this.loadMonthlySchedules(date, { view, onSuccess, onError });
                    break;
                case 'week':
                    this.loadWeeklySchedules(date, { view, onSuccess, onError });
                    break;
                case 'day':
                    this.loadDailySchedules(date, { view, onSuccess, onError });
                    break;
                default:
                    this.loadMonthlySchedules(date, { view, onSuccess, onError });
            }
        } catch (error) {
            console.error('スケジュール読み込みエラー:', error);
            if (onError) onError(error);
        }
    }

    /**
     * 月間スケジュールを読み込み
     *
     * @param {Date} date - 基準日
     * @param {Object} options - オプション
     */
    static loadMonthlySchedules(date, options = {}) {
        const { view = 'calendar', onSuccess, onError } = options;

        console.log('📅 月間スケジュール読み込み開始');

        const year = date.getFullYear();
        const month = date.getMonth();
        const startDate = new Date(year, month, 1);
        const endDate = new Date(year, month + 1, 0);

        this.fetchSchedules(startDate, endDate, {
            view,
            onSuccess: (schedules) => {
                this.renderSchedules(schedules, view);
                if (onSuccess) onSuccess(schedules);
            },
            onError
        });
    }

    /**
     * 週間スケジュールを読み込み
     *
     * @param {Date} date - 基準日
     * @param {Object} options - オプション
     */
    static loadWeeklySchedules(date, options = {}) {
        const { view = 'cards', onSuccess, onError } = options;

        console.log('📅 週間スケジュール読み込み開始');

        // 週の開始日を計算（月曜日）
        const startOfWeek = new Date(date);
        const day = startOfWeek.getDay();
        const diff = startOfWeek.getDate() - day + (day === 0 ? -6 : 1);
        startOfWeek.setDate(diff);

        // 週の終了日を計算（日曜日）
        const endOfWeek = new Date(startOfWeek);
        endOfWeek.setDate(startOfWeek.getDate() + 6);

        this.fetchSchedules(startOfWeek, endOfWeek, {
            view,
            onSuccess: (schedules) => {
                this.renderSchedules(schedules, view);
                if (onSuccess) onSuccess(schedules);
            },
            onError
        });
    }

    /**
     * 日間スケジュールを読み込み
     *
     * @param {Date} date - 基準日
     * @param {Object} options - オプション
     */
    static loadDailySchedules(date, options = {}) {
        const { view = 'list', onSuccess, onError } = options;

        console.log('📅 日間スケジュール読み込み開始');

        this.fetchSchedules(date, date, {
            view,
            onSuccess: (schedules) => {
                this.renderSchedules(schedules, view);
                if (onSuccess) onSuccess(schedules);
            },
            onError
        });
    }

    /**
     * スケジュールデータを取得
     *
     * @param {Date} startDate - 開始日
     * @param {Date} endDate - 終了日
     * @param {Object} options - オプション
     */
    static fetchSchedules(startDate, endDate, options = {}) {
        const { view, onSuccess, onError } = options;

        // 日付範囲を文字列に変換
        const start = this.formatDateForAPI(startDate);
        const end = this.formatDateForAPI(endDate);

        // 既存のスケジュールデータがあるかチェック
        if (this.hasCachedSchedules(start, end)) {
            const schedules = this.getCachedSchedules(start, end);
            if (onSuccess) onSuccess(schedules);
            return;
        }

        // 既存のAPIを使用してスケジュールを取得
        this.fetchSchedulesFromAPI(start, end, {
            onSuccess: (schedules) => {
                this.cacheSchedules(start, end, schedules);
                if (onSuccess) onSuccess(schedules);
            },
            onError: (error) => {
                console.error('スケジュール取得エラー:', error);
                if (onError) onError(error);
            }
        });
    }

    /**
     * スケジュールを表示
     *
     * @param {Array} schedules - スケジュール配列
     * @param {string} view - 表示形式
     */
    static renderSchedules(schedules, view) {
        console.log('📅 スケジュール表示開始:', { count: schedules.length, view });

        switch (view) {
            case 'calendar':
                this.renderCalendarView(schedules);
                break;
            case 'list':
                this.renderListView(schedules);
                break;
            case 'cards':
                this.renderCardsView(schedules);
                break;
            default:
                this.renderCalendarView(schedules);
        }
    }

    /**
     * カレンダー表示でスケジュールを表示
     *
     * @param {Array} schedules - スケジュール配列
     */
    static renderCalendarView(schedules) {
        // 既存のカレンダー表示ロジックを使用
        if (typeof window.updateCalendarDisplay === 'function') {
            window.updateCalendarDisplay(schedules);
        } else if (typeof window.renderCalendar === 'function') {
            window.renderCalendar();
        } else {
            console.warn('カレンダー表示関数が見つかりません');
        }
    }

    /**
     * リスト表示でスケジュールを表示
     *
     * @param {Array} schedules - スケジュール配列
     */
    static renderListView(schedules) {
        const container = document.getElementById('schedule-list');
        if (!container) {
            console.warn('スケジュールリスト表示要素が見つかりません');
            return;
        }

        container.innerHTML = '';

        if (schedules.length === 0) {
            container.innerHTML = '<div class="no-schedules">スケジュールがありません</div>';
            return;
        }

        schedules.forEach(schedule => {
            const scheduleElement = this.createScheduleListItem(schedule);
            container.appendChild(scheduleElement);
        });
    }

    /**
     * カード表示でスケジュールを表示
     *
     * @param {Array} schedules - スケジュール配列
     */
    static renderCardsView(schedules) {
        // マイページのカード表示要素を探す
        let container = document.getElementById('mypage-cards-view');
        if (!container) {
            // スケジュール管理ページのリスト表示要素を探す
            container = document.getElementById('schedule-list');
        }

        if (!container) {
            console.warn('カード表示要素が見つかりません');
            return;
        }

        container.innerHTML = '';

        if (schedules.length === 0) {
            container.innerHTML = '<div class="no-schedules">スケジュールがありません</div>';
            return;
        }

        // 既存の表示ロジックを使用
        if (typeof window.generateScheduleCard === 'function') {
            // マイページ用の表示
            let scheduleHTML = '<div class="monthly-schedule-list">';
            schedules.forEach(schedule => {
                const date = new Date(schedule.date);
                const formattedDate = date.toLocaleDateString('ja-JP', {
                    month: 'long',
                    day: 'numeric',
                    weekday: 'long'
                });

                scheduleHTML += `
                    <div class="schedule-item" style="background:white;border:1px solid #e9ecef;border-radius:8px;padding:1rem;margin-bottom:0.75rem;box-shadow:0 1px 3px rgba(0,0,0,0.1);">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.5rem;">
                            <span style="font-weight:600;color:#495057;">${formattedDate}</span>
                            <div style="display:flex;gap:0.5rem;">
                                <a href="${window.location.origin}/schedule-edit?date=${schedule.date}" class="btn btn-sm btn-primary">編集</a>
                            </div>
                        </div>
                        <div style="font-weight:600;color:#212529;margin-bottom:0.5rem;">${schedule.type}</div>
                        <div style="color:#6c757d;font-size:0.9rem;">${schedule.start_time} - ${schedule.end_time} ${schedule.location || ''}</div>
                    </div>
                `;
            });
            scheduleHTML += '</div>';
            container.innerHTML = scheduleHTML;
        } else {
            // フォールバック: 基本的なカード表示
            schedules.forEach(schedule => {
                const scheduleElement = this.createScheduleCard(schedule);
                container.appendChild(scheduleElement);
            });
        }
    }

    /**
     * スケジュールリストアイテムを作成
     *
     * @param {Object} schedule - スケジュールオブジェクト
     * @returns {HTMLElement} リストアイテム要素
     */
    static createScheduleListItem(schedule) {
        const item = document.createElement('div');
        item.className = 'schedule-list-item';
        item.innerHTML = `
            <div class="schedule-info">
                <span class="schedule-time">${schedule.start_time}～${schedule.end_time}</span>
                <span class="schedule-type">${schedule.type}</span>
                <span class="schedule-place">${schedule.place || '未設定'}</span>
            </div>
            <div class="schedule-actions">
                <button class="btn btn-sm btn-primary" onclick="AidUniteScheduleModal.showDetail('${schedule.id}')">
                    詳細
                </button>
            </div>
        `;
        return item;
    }

    /**
     * スケジュールカードを作成
     *
     * @param {Object} schedule - スケジュールオブジェクト
     * @returns {HTMLElement} カード要素
     */
    static createScheduleCard(schedule) {
        const iconHtml = (typeof AidUniteScheduleUtils !== 'undefined' && AidUniteScheduleUtils.getScheduleIconHtml)
            ? AidUniteScheduleUtils.getScheduleIconHtml(schedule.type, 20)
            : '';

        const card = document.createElement('div');
        card.className = 'schedule-card';
        card.innerHTML = `
            <div class="card-header">
                <span class="schedule-icon">${iconHtml}</span>
                <span class="schedule-type">${schedule.type}</span>
            </div>
            <div class="card-body">
                <div class="schedule-time">${schedule.start_time}～${schedule.end_time}</div>
                <div class="schedule-place">${schedule.place || '未設定'}</div>
                ${schedule.content ? `<div class="schedule-content">${schedule.content}</div>` : ''}
            </div>
            <div class="card-footer">
                <button class="btn btn-sm btn-primary" onclick="AidUniteScheduleModal.showDetail('${schedule.id}')">
                    詳細
                </button>
            </div>
        `;
        return card;
    }

    /**
     * 日付をAPI用にフォーマット
     *
     * @param {Date} date - 日付オブジェクト
     * @returns {string} YYYY-MM-DD形式の文字列
     */
    static formatDateForAPI(date) {
        return AidUniteDateUtils?.formatDateLocal(date) ||
               (typeof formatDateLocal === 'function' ? formatDateLocal(date) :
                date.toISOString().split('T')[0]);
    }

    /**
     * キャッシュされたスケジュールがあるかチェック
     *
     * @param {string} start - 開始日
     * @param {string} end - 終了日
     * @returns {boolean} キャッシュがあるかどうか
     */
    static hasCachedSchedules(start, end) {
        const cacheKey = `schedules_${start}_${end}`;
        return window.scheduleCache && window.scheduleCache[cacheKey];
    }

    /**
     * キャッシュされたスケジュールを取得
     *
     * @param {string} start - 開始日
     * @param {string} end - 終了日
     * @returns {Array} スケジュール配列
     */
    static getCachedSchedules(start, end) {
        const cacheKey = `schedules_${start}_${end}`;
        return window.scheduleCache ? window.scheduleCache[cacheKey] : [];
    }

    /**
     * スケジュールをキャッシュ
     *
     * @param {string} start - 開始日
     * @param {string} end - 終了日
     * @param {Array} schedules - スケジュール配列
     */
    static cacheSchedules(start, end, schedules) {
        if (!window.scheduleCache) {
            window.scheduleCache = {};
        }
        const cacheKey = `schedules_${start}_${end}`;
        window.scheduleCache[cacheKey] = schedules;
    }

    /**
     * 既存のAPIを使用してスケジュールを取得
     *
     * @param {string} start - 開始日
     * @param {string} end - 終了日
     * @param {Object} options - オプション
     */
    static fetchSchedulesFromAPI(start, end, options = {}) {
        const { onSuccess, onError } = options;

        // 既存のfetchMonthByDaily関数を使用
        if (typeof window.fetchMonthByDaily === 'function') {
            window.fetchMonthByDaily(start, end)
                .then(() => {
                    // グローバル変数からスケジュールを取得
                    const schedules = this.extractSchedulesFromGlobal(start, end);
                    if (onSuccess) onSuccess(schedules);
                })
                .catch(error => {
                    if (onError) onError(error);
                });
        } else {
            // フォールバック: 直接APIを呼び出し
            this.makeAjaxRequest({
                start_date: start,
                end_date: end
            }, {
                onSuccess: (response) => {
                    if (response.success) {
                        if (onSuccess) onSuccess(response.data);
                    } else {
                        if (onError) onError(new Error(response.message || 'スケジュールの取得に失敗しました'));
                    }
                },
                onError: onError
            });
        }
    }

    /**
     * グローバル変数からスケジュールを抽出
     *
     * @param {string} start - 開始日
     * @param {string} end - 終了日
     * @returns {Array} スケジュール配列
     */
    static extractSchedulesFromGlobal(start, end) {
        const schedules = [];

        // 既存のグローバル変数からスケジュールを取得
        if (window.schedules) {
            Object.keys(window.schedules).forEach(date => {
                if (date >= start && date <= end) {
                    schedules.push(...window.schedules[date]);
                }
            });
        }

        // マイページ用のグローバル変数からも取得
        if (window.mypageSchedules) {
            Object.keys(window.mypageSchedules).forEach(date => {
                if (date >= start && date <= end) {
                    schedules.push(...window.mypageSchedules[date]);
                }
            });
        }

        return schedules;
    }

    /**
     * AJAXリクエストを実行
     *
     * @param {Object} data - 送信データ
     * @param {Object} options - オプション
     */
    static makeAjaxRequest(data, options = {}) {
        const { onSuccess, onError } = options;

        // WordPress REST API URLを使用
        const apiUrl = '/wp-json/aidunite/v1/get-schedules';

        fetch(apiUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': window.wpApiSettings?.nonce || ''
            },
            body: JSON.stringify(data)
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (onSuccess) onSuccess(data);
        })
        .catch(error => {
            console.error('AJAXリクエストエラー:', error);
            if (onError) onError(error);
        });
    }

    /**
     * カレンダー月表示用：get-user-schedules を1回呼び、window.schedules に格納する（キャッシュなし）
     * スケジュール管理・マイページで共通使用。レスポンスは { success, data } と配列の両方に対応。
     *
     * @param {string} start - Y-m-d（表示月の1日）
     * @param {string} end - Y-m-d（表示月の最終日）
     * @returns {Promise<void>} 成功時 resolve、失敗時 reject
     */
    static fetchMonthSchedulesForCalendar(start, end, fetchOptions = {}) {
        const params = { start, end };
        if (fetchOptions.scope) {
            params.scope = fetchOptions.scope;
        }
        if (fetchOptions.team_id) {
            params.team_id = String(fetchOptions.team_id);
        }
        const url = `/wp-json/aidunite/v1/get-user-schedules?${new URLSearchParams(params).toString()}`;
        const nonce = (typeof wpApiSettings !== 'undefined' && wpApiSettings.nonce) ? wpApiSettings.nonce : '';
        return fetch(url, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': nonce,
                'Cache-Control': 'no-cache'
            }
        })
            .then(res => {
                if (!res.ok) throw new Error(`HTTP ${res.status}: ${res.statusText}`);
                return res.json();
            })
            .then(payload => {
                const schedules = (payload && payload.success && Array.isArray(payload.data))
                    ? payload.data
                    : (Array.isArray(payload) ? payload : []);
                window.schedules = {};
                schedules.forEach(schedule => {
                    if (schedule && !schedule.id && (schedule.post_id || schedule.schedule_id)) {
                        schedule.id = schedule.post_id || schedule.schedule_id;
                    }
                    const raw = schedule.date || schedule.schedule_date || schedule.start_date;
                    const dateKey = raw ? String(raw).trim().substring(0, 10) : '';
                    if (dateKey && /^\d{4}-\d{2}-\d{2}$/.test(dateKey)) {
                        if (!window.schedules[dateKey]) window.schedules[dateKey] = [];
                        window.schedules[dateKey].push(schedule);
                    }
                });
            });
    }
}

// 後方互換性のためのグローバル関数（一時的に無効化）
// 注意: 既存のコードとの干渉を避けるため一時的に無効化
if (typeof window !== 'undefined') {
    // 既存の関数名での後方互換性（一時的にコメントアウト）
    // window.loadSchedules = AidUniteScheduleLoader.loadSchedules.bind(AidUniteScheduleLoader);
    // window.loadMypageSchedules = () => AidUniteScheduleLoader.loadSchedules({ view: 'cards' });
    // window.loadMonthlySchedules = (date) => AidUniteScheduleLoader.loadMonthlySchedules(date, { view: 'cards' });
    // window.loadWeeklySchedules = (date) => AidUniteScheduleLoader.loadWeeklySchedules(date, { view: 'cards' });
    // window.loadScheduleList = () => AidUniteScheduleLoader.loadSchedules({ view: 'list' });
}

// モジュールエクスポート（ES6モジュール対応）
if (typeof module !== 'undefined' && module.exports) {
    module.exports = AidUniteScheduleLoader;
}

// AMD対応
if (typeof define === 'function' && define.amd) {
    define([], function() {
        return AidUniteScheduleLoader;
    });
}
