/**
 * スケジュール管理ページ用JavaScript
 * カレンダー表示、モーダル機能、日付クリック時の動作を制御
 */

// getDocument は js/common/dom-utils.js で定義。未読込時はフォールバック
if (typeof getDocument === 'undefined') {
    function getDocument() {
        if (typeof global !== 'undefined' && global.document) return global.document;
        return typeof document !== 'undefined' ? document : null;
    }
}

function getWindow() {
    return (typeof global !== 'undefined' && global.window) ? global.window : window;
}

// 日本語表記マップ（共通関数）
const TYPE_JA = {
    practice: '練習',
    official_match: '公式試合',  // 統一: 公式試合
    practice_match: '練習試合',
    joint_practice: '合同練習',
    meeting: 'ミーティング',
    rest: '休み',
    event: 'イベント',
    tbd: '未定'  // 統一: 未定を追加
};

const STATUS_JA = {
    planned: '予定',
    matched: '成立',
    confirmed: '確定'
};

// 日本語表記生成関数
function labelJa(type, status) {
    const base = TYPE_JA[type] || type;
    return status ? `${base}（${STATUS_JA[status]}）` : base;
}

class ScheduleManager {
    constructor() {
        this.currentDate = new Date();
        this.currentMonth = this.currentDate.getMonth() + 1;
        this.currentYear = this.currentDate.getFullYear();
        this.selectedDate = null;
        this.schedules = {}; // 日付をキーとしたスケジュールデータ

        // 2点タップ機能用のプロパティを追加
        this.clickTimer = null;
        this.isRangeSelection = false;
        this.rangeStart = null;
        this.rangeEnd = null;

        this.init();
    }

    init() {
        this.bindEvents();
        this.initializeCalendar();
        this.loadSchedules();
    }

    bindEvents() {
        const doc = getDocument();
        // 月選択の変更
        doc.getElementById('month-select')?.addEventListener('change', (e) => {
            this.currentMonth = parseInt(e.target.value);
            this.updateCalendar();
        });

        doc.getElementById('list-month-select')?.addEventListener('change', (e) => {
            this.currentMonth = parseInt(e.target.value);
            this.updateScheduleList();
        });

        // カレンダーナビゲーション
        doc.getElementById('prev-month')?.addEventListener('click', () => {
            this.navigateMonth(-1);
        });

        doc.getElementById('next-month')?.addEventListener('click', () => {
            this.navigateMonth(1);
        });

        // モーダル関連
        doc.getElementById('schedule-modal-close')?.addEventListener('click', () => {
            this.closeScheduleModal();
        });

        // モーダル外クリックで閉じる
        doc.getElementById('schedule-modal')?.addEventListener('click', (e) => {
            if (e.target.id === 'schedule-modal') {
                this.closeScheduleModal();
            }
        });

        // スケジュール編集ボタン
        doc.getElementById('schedule-edit-btn')?.addEventListener('click', (e) => {
            e.preventDefault();
            this.editSchedule();
        });

        // スケジュール削除ボタン
        doc.getElementById('schedule-delete-btn')?.addEventListener('click', () => {
            this.deleteSchedule();
        });

        // 削除ボタン（概要タブ）
        doc.getElementById('delete-schedule-btn')?.addEventListener('click', (e) => {
            e.preventDefault();
            this.showDeleteConfirmation();
        });

        // ESCキーでモーダルを閉じる
        doc.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this.closeScheduleModal();
            }
        });
    }

    initializeCalendar() {
        this.updateCalendar();
    }

    updateCalendar() {
        const doc = getDocument();
        const calendarGrid = doc.getElementById('calendar-grid');
        if (!calendarGrid) return;

        // 月の最初の日と最後の日を取得
        const firstDay = new Date(this.currentYear, this.currentMonth - 1, 1);
        const lastDay = new Date(this.currentYear, this.currentMonth, 0);
        const startDate = new Date(firstDay);
        startDate.setDate(startDate.getDate() - firstDay.getDay());

        // カレンダーグリッドをクリア
        calendarGrid.innerHTML = '';

        // 曜日ヘッダーを追加
        const weekdays = ['日', '月', '火', '水', '木', '金', '土'];
        weekdays.forEach(day => {
            const weekdayDiv = doc.createElement('div');
            weekdayDiv.className = 'calendar-weekday';
            weekdayDiv.textContent = day;
            calendarGrid.appendChild(weekdayDiv);
        });

        // 日付セルを生成
        const totalDays = 42; // 6週間分
        for (let i = 0; i < totalDays; i++) {
            const currentDate = new Date(startDate);
            currentDate.setDate(startDate.getDate() + i);

            const dayDiv = doc.createElement('div');
            dayDiv.className = 'calendar-day';

            // 他の月の日付かチェック
            if (currentDate.getMonth() !== this.currentMonth - 1) {
                dayDiv.classList.add('other-month');
            }

            // 今日の日付かチェック
            const today = new Date();
            if (currentDate.toDateString() === today.toDateString()) {
                dayDiv.classList.add('today');
            }

            // 日付番号
            const dayNumber = doc.createElement('div');
            dayNumber.className = 'calendar-day-number';
            dayNumber.textContent = currentDate.getDate();
            dayDiv.appendChild(dayNumber);

            // 日付コンテンツエリア
            const dayContent = doc.createElement('div');
            dayContent.className = 'calendar-day-content';

            // スケジュールがあるかチェック
            const dateKey = this.formatDateKey(currentDate);
            if (this.schedules[dateKey] && this.schedules[dateKey].length > 0) {
                // スケジュールインジケーター
                const indicator = doc.createElement('div');
                indicator.className = 'schedule-indicator';
                indicator.textContent = this.schedules[dateKey].length;
                indicator.title = `${this.schedules[dateKey].length}件のスケジュール`;
                indicator.addEventListener('click', (e) => {
                    e.stopPropagation();
                    this.showScheduleDetails(currentDate);
                });
                dayContent.appendChild(indicator);
            } else {
                // 新規追加ボタン
                const addButton = doc.createElement('div');
                addButton.className = 'calendar-add-button';
                addButton.textContent = '+';
                addButton.title = 'スケジュールを追加';
                addButton.addEventListener('click', (e) => {
                    e.stopPropagation();
                    this.addNewSchedule(currentDate);
                });
                dayContent.appendChild(addButton);
            }

            dayDiv.appendChild(dayContent);

            // 日付クリックイベント
            dayDiv.addEventListener('click', () => {
                this.handleDateClick(currentDate);
            });

            calendarGrid.appendChild(dayDiv);
        }

        // 月・年表示を更新
        this.updateMonthYearDisplay();
    }

    updateMonthYearDisplay() {
        const doc = getDocument();
        const monthYearDisplay = doc.getElementById('current-month-year');
        if (monthYearDisplay) {
            const monthNames = [
                '1月', '2月', '3月', '4月', '5月', '6月',
                '7月', '8月', '9月', '10月', '11月', '12月'
            ];
            monthYearDisplay.textContent = `${monthNames[this.currentMonth - 1]} ${this.currentYear}`;
        }
    }

    navigateMonth(direction) {
        if (direction === -1) {
            if (this.currentMonth === 1) {
                this.currentMonth = 12;
                this.currentYear--;
            } else {
                this.currentMonth--;
            }
        } else {
            if (this.currentMonth === 12) {
                this.currentMonth = 1;
                this.currentYear++;
            } else {
                this.currentMonth++;
            }
        }

        // 月選択セレクトボックスも更新
        const doc = getDocument();
        const monthSelect = doc.getElementById('month-select');
        if (monthSelect) {
            monthSelect.value = this.currentMonth;
        }

        this.updateCalendar();
    }

    handleDateClick(date) {
        const dateKey = this.formatDateKey(date);

        // 2点タップ処理を追加
        if (!this.clickTimer) {
            // シングルクリック：300ms後に実行
            this.clickTimer = setTimeout(() => {
                this.handleSingleClick(date, dateKey);
                this.clickTimer = null;
            }, 300);
        } else {
            // ダブルクリック：期間選択モード
            clearTimeout(this.clickTimer);
            this.clickTimer = null;
            this.handleDoubleClick(date, dateKey);
        }
    }

    // シングルクリック処理
    handleSingleClick(date, dateKey) {
        if (this.schedules[dateKey] && this.schedules[dateKey].length > 0) {
            // スケジュールがある場合は詳細を表示
            this.showScheduleDetails(date);
        } else {
            // スケジュールがない場合は新規作成画面に遷移
            this.addNewSchedule(date);
        }
    }

    // ダブルクリック処理（期間選択）
    handleDoubleClick(date, dateKey) {
        const win = getWindow();
        // 期間選択モードを開始
        if (!this.isRangeSelection) {
            this.rangeStart = date;
            this.isRangeSelection = true;
            console.log('🚀 期間選択を開始しました:', this.formatDateKey(date));
        } else {
            // 期間選択完了
            if (date < this.rangeStart) {
                this.rangeEnd = this.rangeStart;
                this.rangeStart = date;
            } else {
                this.rangeEnd = date;
            }

            // 期間内の日付を選択
            const selectedDates = [];
            const current = new Date(this.rangeStart);
            while (current <= this.rangeEnd) {
                selectedDates.push(new Date(current));
                current.setDate(current.getDate() + 1);
            }

            this.isRangeSelection = false;
            console.log('✅ 期間選択完了:', selectedDates.length + '日間');

            // 期間選択完了後、スケジュール編集ページに遷移
            const startDate = this.formatDateKey(this.rangeStart);
            const endDate = this.formatDateKey(this.rangeEnd);
            const url = new URL('/schedule-edit', win.location.origin);
            url.searchParams.set('start_date', startDate);
            url.searchParams.set('end_date', endDate);
            win.location.href = url.toString();
        }
    }

    addNewSchedule(date) {
        const dateKey = this.formatDateKey(date);
        const win = getWindow();
        const url = new URL('/schedule-edit', win.location.origin);
        url.searchParams.set('date', dateKey);
        win.location.href = url.toString();
    }

    showScheduleDetails(date) {
        const doc = getDocument();
        const dateKey = this.formatDateKey(date);
        const dateSchedules = this.schedules[dateKey] || [];

        if (dateSchedules.length === 0) return;

        const modal = doc.getElementById('schedule-modal');
        const modalBody = doc.getElementById('schedule-modal-body');
        const editBtn = doc.getElementById('schedule-edit-btn');
        const deleteBtn = doc.getElementById('schedule-delete-btn');

        if (!modal || !modalBody) return;

        // モーダル内容を生成
        let modalContent = '';

        if (dateSchedules.length === 1) {
            // 単一スケジュール
            const schedule = dateSchedules[0];
            modalContent = this.generateScheduleDetailHTML(schedule);

            // 編集・削除ボタンのリンクを設定
            editBtn.href = `/schedule-edit?id=${schedule.id}`;
            deleteBtn.dataset.scheduleId = schedule.id;
        } else {
            // 複数スケジュール
            modalContent = '<h4>複数のスケジュールがあります</h4>';
            dateSchedules.forEach((schedule, index) => {
                modalContent += this.generateScheduleDetailHTML(schedule, index);
            });

            // 編集・削除ボタンを無効化（複数選択が必要）
            editBtn.href = '#';
            deleteBtn.dataset.scheduleId = '';
        }

        modalBody.innerHTML = modalContent;
        modal.classList.add('active');
        this.selectedDate = date;
    }

    generateScheduleDetailHTML(schedule, index = 0) {
        const scheduleId = index > 0 ? `-${index}` : '';
        return `
            <div class="schedule-detail-item">
                <div class="schedule-detail-label">タイトル</div>
                <div class="schedule-detail-value">${this.escapeHtml(schedule.title)}</div>
            </div>
            <div class="schedule-detail-item">
                <div class="schedule-detail-label">日時</div>
                <div class="schedule-detail-value">${this.escapeHtml(schedule.date)} ${this.escapeHtml(schedule.time)}</div>
            </div>
            <div class="schedule-detail-item">
                <div class="schedule-detail-label">場所</div>
                <div class="schedule-detail-value">${this.escapeHtml(schedule.place)}</div>
            </div>
            <div class="schedule-detail-item">
                <div class="schedule-detail-label">種別</div>
                <div class="schedule-detail-value">${this.escapeHtml(schedule.type)}</div>
            </div>
            ${schedule.description ? `
            <div class="schedule-detail-item">
                <div class="schedule-detail-label">詳細</div>
                <div class="schedule-detail-value">${this.escapeHtml(schedule.description)}</div>
            </div>
            ` : ''}
        `;
    }

    closeScheduleModal() {
        const doc = getDocument();
        const modal = doc.getElementById('schedule-modal');
        if (modal) {
            modal.classList.remove('active');
        }
        this.selectedDate = null;
    }

    editSchedule() {
        const doc = getDocument();
        const win = getWindow();
        const editBtn = doc.getElementById('schedule-edit-btn');
        if (editBtn && editBtn.href !== '#') {
            win.location.href = editBtn.href;
        }
    }

    deleteSchedule() {
        const doc = getDocument();
        const deleteBtn = doc.getElementById('schedule-delete-btn');
        const scheduleId = deleteBtn.dataset.scheduleId;

        if (!scheduleId) {
            if (typeof showToastNotification !== 'undefined') {
                showToastNotification('削除するスケジュールを選択してください。', 'warning');
            } else {
                alert('削除するスケジュールを選択してください。');
            }
            return;
        }

        this.performDeleteSchedule(scheduleId);
    }

    performDeleteSchedule(scheduleId) {
        const self = this;
        const sid = String(scheduleId || '').trim();
        if (!sid) {
            this.showNotification('スケジュールIDが不正です', 'error');
            return;
        }
        const errText = function(err) {
            const code = (err && typeof err === 'object' && err.code) ? err.code : '';
            const raw = (err && typeof err === 'object' && err.message) ? err.message : String(err || '');
            if (code && typeof AidUniteScheduleModal !== 'undefined' && AidUniteScheduleModal.messageForScheduleErrorCode) {
                return AidUniteScheduleModal.messageForScheduleErrorCode(code, raw);
            }
            return raw || '不明なエラー';
        };
        const gateText = function(code) {
            if (typeof AidUniteScheduleModal !== 'undefined' && AidUniteScheduleModal.messageForDeleteGateCode) {
                return AidUniteScheduleModal.messageForDeleteGateCode(code);
            }
            return 'このスケジュールは現状では削除できません。';
        };
        const runRestDelete = function() {
            if (typeof AidUniteAjaxUtils === 'undefined' || typeof AidUniteAjaxUtils.deleteSchedule !== 'function') {
                self.showNotification('削除APIが利用できません。ページを再読み込みしてください。', 'error');
                return;
            }
            AidUniteAjaxUtils.deleteSchedule({
                schedule_id: sid,
                onSuccess: () => {
                    self.closeScheduleModal();
                    self.loadSchedules();
                    self.updateCalendar();
                    self.showNotification('スケジュールを削除しました', 'success');
                },
                onError: (err) => {
                    self.showNotification('スケジュールの削除に失敗しました: ' + errText(err), 'error');
                }
            });
        };

        if (typeof AidUniteAjaxUtils !== 'undefined' && typeof AidUniteAjaxUtils.getScheduleDependencies === 'function') {
            AidUniteAjaxUtils.getScheduleDependencies({
                schedule_id: sid,
                onSuccess: (data) => {
                    const gate = data && data.delete_gate;
                    if (gate && gate.allowed === false) {
                        self.showNotification(gateText(gate.code), 'error');
                        return;
                    }
                    const dep = (data && data.dependencies) || {};
                    const nBoard = (dep.match_board_ids && dep.match_board_ids.length) ? dep.match_board_ids.length : 0;
                    const nMr = (dep.match_request_ids && dep.match_request_ids.length) ? dep.match_request_ids.length : 0;
                    let confirmText = 'このスケジュールを削除してもよろしいですか？';
                    if (nBoard > 0 || nMr > 0) {
                        confirmText = '関連する掲示板・マッチ申請データも含めて削除される場合があります。' + confirmText;
                    }
                    if (!confirm(confirmText)) {
                        return;
                    }
                    runRestDelete();
                },
                onError: () => {
                    if (!confirm('依存状況を取得できませんでした。このまま削除を試みますか？')) {
                        return;
                    }
                    if (!confirm('このスケジュールを削除してもよろしいですか？')) {
                        return;
                    }
                    runRestDelete();
                }
            });
            return;
        }

        if (!confirm('このスケジュールを削除してもよろしいですか？')) {
            return;
        }
        runRestDelete();
    }

    showDeleteConfirmation() {
        if (typeof showToastNotification !== 'undefined') {
            showToastNotification('削除するスケジュールを選択してください。カレンダーから日付をクリックしてスケジュールを選択してから削除してください。', 'warning');
        } else {
            alert('削除するスケジュールを選択してください。カレンダーから日付をクリックしてスケジュールを選択してから削除してください。');
        }
    }

    loadSchedules() {
        // 新しいAPIを使用してスケジュールを読み込み
        if (typeof this.loadCalendarSchedules === 'function') {
            this.loadCalendarSchedules();
        }
        // loadCalendarSchedulesが存在しない場合は何もしない（テスト環境など）
    }

    updateScheduleList() {
        // スケジュール一覧表示の更新処理
        const doc = getDocument();
        const listContent = doc.getElementById('schedule-list-content');
        if (!listContent) return;

        // 選択された月のスケジュールを取得
        const monthSchedules = this.getSchedulesForMonth(this.currentMonth, this.currentYear);

        if (monthSchedules.length === 0) {
            listContent.innerHTML = '<p class="no-schedules">この月のスケジュールはありません</p>';
            return;
        }

        // スケジュール一覧を生成
        let listHTML = '<div class="schedule-list-items">';
        monthSchedules.forEach(schedule => {
            listHTML += this.generateScheduleListItemHTML(schedule);
        });
        listHTML += '</div>';

        listContent.innerHTML = listHTML;
    }

    getSchedulesForMonth(month, year) {
        const monthSchedules = [];
        const monthKey = `${year}-${month.toString().padStart(2, '0')}`;

        Object.keys(this.schedules).forEach(dateKey => {
            if (dateKey.startsWith(monthKey)) {
                monthSchedules.push(...this.schedules[dateKey]);
            }
        });

        // 日付順にソート
        monthSchedules.sort((a, b) => new Date(a.date) - new Date(b.date));

        return monthSchedules;
    }

    generateScheduleListItemHTML(schedule) {
        return `
            <div class="schedule-list-item">
                <div class="schedule-list-date">${this.formatDisplayDate(schedule.date)}</div>
                <div class="schedule-list-info">
                    <div class="schedule-list-title">${this.escapeHtml(schedule.title)}</div>
                    <div class="schedule-list-details">
                        <span class="schedule-list-time">${this.escapeHtml(schedule.time)}</span>
                        <span class="schedule-list-place">${this.escapeHtml(schedule.place)}</span>
                        <span class="schedule-list-type">${this.escapeHtml(schedule.type)}</span>
                    </div>
                </div>
                <div class="schedule-list-actions">
                    <button class="dashboard-btn btn-secondary btn-sm" onclick="scheduleManager.editScheduleById(${schedule.id})">編集</button>
                    <button class="dashboard-btn btn-danger btn-sm" onclick="scheduleManager.deleteScheduleById(${schedule.id})">削除</button>
                </div>
            </div>
        `;
    }

    editScheduleById(scheduleId) {
        const win = getWindow();
        win.location.href = `/schedule-edit?id=${scheduleId}`;
    }

    deleteScheduleById(scheduleId) {
        this.performDeleteSchedule(scheduleId);
    }

    formatDateKey(date) {
        const year = date.getFullYear();
        const month = (date.getMonth() + 1).toString().padStart(2, '0');
        const day = date.getDate().toString().padStart(2, '0');
        return `${year}-${month}-${day}`;
    }

    formatDisplayDate(dateString) {
        const date = new Date(dateString);
        const month = date.getMonth() + 1;
        const day = date.getDate();
        const weekday = ['日', '月', '火', '水', '木', '金', '土'][date.getDay()];
        return `${month}/${day} (${weekday})`;
    }

    escapeHtml(text) {
        // テキストが未定義またはnullの場合は空文字列を返す
        if (text == null || text === undefined) {
            return '';
        }

        const doc = getDocument();
        // getDocument()がnullを返した場合のフォールバック処理
        if (!doc || typeof doc.createElement !== 'function') {
            // フォールバック: 基本的なHTMLエスケープ処理
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

    showNotification(message, type = 'info') {
        // 通知表示の実装（実際の環境に合わせて修正）
        console.log(`${type}: ${message}`);

        // トースト通知を使用
        if (typeof showToastNotification !== 'undefined') {
            showToastNotification(message, type);
        } else {
            // フォールバック
            if (type === 'success') {
                alert(message);
            } else if (type === 'error') {
                alert(message);
            } else {
                alert(message);
            }
        }
    }
}

// テスト用にグローバルに公開（モジュール読み込み時に確実に実行される）
// addEventListenerの前に実行することで、確実にグローバルに公開される
if (typeof global !== 'undefined') {
    global.ScheduleManager = ScheduleManager;
    global.labelJa = labelJa;
}

// ページ読み込み完了後に初期化
// 重複読み込み対策: 既に初期化済みの場合はスキップ
if (!window._scheduleManagementInitialized) {
    // テスト環境ではglobal.documentを優先的に使用
    const docForEvent = (typeof global !== 'undefined' && global.document) ? global.document : document;
    const winForEvent = (typeof global !== 'undefined' && global.window) ? global.window : window;

    // DOMContentLoadedイベントが利用可能な場合のみ登録
    if (docForEvent && typeof docForEvent.addEventListener === 'function') {
        docForEvent.addEventListener('DOMContentLoaded', () => {
            winForEvent.scheduleManager = new ScheduleManager();
        });
    }
    window._scheduleManagementInitialized = true;
}

// グローバル関数として公開（HTMLからの直接呼び出し用）
if (typeof winForEvent !== 'undefined') {
    winForEvent.scheduleManager = null;
}
