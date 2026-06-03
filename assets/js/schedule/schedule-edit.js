/**
 * スケジュール編集機能専用JavaScript
 * 4ステップウィザード形式のスケジュール登録・編集機能
 */

// getDocument は js/common/dom-utils.js で定義。未読込時はフォールバック
if (typeof getDocument === 'undefined') {
    function getDocument() {
        if (typeof global !== 'undefined' && global.document) return global.document;
        return typeof document !== 'undefined' ? document : null;
    }
}

// カレンダー関連の変数
let scheduleEditCalendarCurrentDate = new Date();
let selectedDates = [];

/** STEP1 目的カード選択後の「次へ」へスクロール（STEP遷移時にキャンセル） */
let scheduleEditIntentScrollTimer = null;

function cancelScheduleEditIntentScrollTimer() {
    if (scheduleEditIntentScrollTimer !== null) {
        clearTimeout(scheduleEditIntentScrollTimer);
        scheduleEditIntentScrollTimer = null;
    }
}

/**
 * STEP2〜4 入室時：ヒーロー＋4段ステッパーが見えるようページ先頭へスクロール
 */
function scrollScheduleEditPageToTop() {
    const hero =
        document.querySelector('.page-schedule-edit--renewal .ainy-webapp-hero') ||
        document.querySelector('.page-schedule-edit .ainy-webapp-hero') ||
        document.getElementById('schedule-edit-wizard-stepper');
    try {
        if (hero) {
            const offset = 8;
            const rect = hero.getBoundingClientRect();
            const currentScroll = window.pageYOffset || document.documentElement.scrollTop || 0;
            const top = Math.max(0, rect.top + currentScroll - offset);
            window.scrollTo({ top: top, behavior: 'smooth' });
            return;
        }
    } catch (e) {
        // fall through
    }
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

/** テーマ SVG アイコン（AidUniteThemeIcons / wp_localize_script） */
function scheduleEditIconHtml(basename, size) {
    if (typeof window.AidUniteThemeIcons !== 'undefined' && typeof window.AidUniteThemeIcons.html === 'function') {
        return window.AidUniteThemeIcons.html(basename, size, { block: true });
    }
    return '';
}

// 日本の祝日判定（簡易版：主要な祝日のみ）
function isJapaneseHoliday(date) {
    const year = date.getFullYear();
    const month = date.getMonth() + 1; // 0-11 → 1-12
    const day = date.getDate();
    const dayOfWeek = date.getDay();

    // 固定祝日
    const fixedHolidays = {
        '1-1': '元日',
        '2-11': '建国記念の日',
        '4-29': '昭和の日',
        '5-3': '憲法記念日',
        '5-4': 'みどりの日',
        '5-5': 'こどもの日',
        '8-11': '山の日',
        '11-3': '文化の日',
        '11-23': '勤労感謝の日',
        '12-23': '天皇誕生日' // 2024年まで
    };

    const key = `${month}-${day}`;
    if (fixedHolidays[key]) {
        return true;
    }

    // 春分の日（3月20日または21日）
    if (month === 3) {
        const springEquinox = calculateSpringEquinox(year);
        if (day === springEquinox) {
            return true;
        }
    }

    // 秋分の日（9月22日、23日、または24日）
    if (month === 9) {
        const autumnEquinox = calculateAutumnEquinox(year);
        if (day === autumnEquinox) {
            return true;
        }
    }

    // 海の日（7月の第3月曜日、2020年以降は7月23日固定）
    if (month === 7) {
        if (year >= 2020) {
            if (day === 23) {
                return true;
            }
        } else {
            // 第3月曜日
            const thirdMonday = getNthWeekday(year, 7, 1, 3); // 月曜日 = 1
            if (day === thirdMonday) {
                return true;
            }
        }
    }

    // 敬老の日（9月の第3月曜日）
    if (month === 9) {
        const thirdMonday = getNthWeekday(year, 9, 1, 3);
        if (day === thirdMonday) {
            return true;
        }
    }

    // 体育の日/スポーツの日（10月の第2月曜日）
    if (month === 10) {
        const secondMonday = getNthWeekday(year, 10, 1, 2);
        if (day === secondMonday) {
            return true;
        }
    }

    return false;
}

// 春分の日を計算（簡易版）
function calculateSpringEquinox(year) {
    // 簡易計算式（1900-2099年で有効）
    if (year < 1900 || year > 2099) return 20;

    if (year <= 1980) {
        return Math.floor(20.8357 + 0.242194 * (year - 1980) - Math.floor((year - 1980) / 4));
    } else if (year <= 2099) {
        return Math.floor(20.8431 + 0.242194 * (year - 1980) - Math.floor((year - 1980) / 4));
    }
    return 20;
}

// 秋分の日を計算（簡易版）
function calculateAutumnEquinox(year) {
    // 簡易計算式（1900-2099年で有効）
    if (year < 1900 || year > 2099) return 23;

    if (year <= 1980) {
        return Math.floor(23.2588 + 0.242194 * (year - 1980) - Math.floor((year - 1980) / 4));
    } else if (year <= 2099) {
        return Math.floor(23.2488 + 0.242194 * (year - 1980) - Math.floor((year - 1980) / 4));
    }
    return 23;
}

// 第N週の曜日を取得
function getNthWeekday(year, month, weekday, n) {
    // weekday: 0=日, 1=月, 2=火, ..., 6=土
    const firstDay = new Date(year, month - 1, 1);
    const firstWeekday = firstDay.getDay();

    // 最初の指定曜日までの日数
    let daysToFirst = (weekday - firstWeekday + 7) % 7;
    if (daysToFirst === 0 && firstWeekday !== weekday) {
        daysToFirst = 7;
    }

    // 第N週の日付
    return 1 + daysToFirst + (n - 1) * 7;
}

document.addEventListener('DOMContentLoaded', function() {


    // タブ切り替え機能
    const tabBtns = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');

    tabBtns.forEach(btn => {
        // クリックイベント
        btn.addEventListener('click', function() {
            handleTabSwitch(this);
        });

        // タッチイベント（スマホ対応）
        btn.addEventListener('touchstart', function(e) {
            e.preventDefault();
            this.style.transform = 'scale(0.95)';
        });

        btn.addEventListener('touchend', function(e) {
            e.preventDefault();
            this.style.transform = 'scale(1)';
            handleTabSwitch(this);
        });
    });

    // タブ切り替え処理
    function handleTabSwitch(btn) {
        const targetTab = btn.getAttribute('data-tab');
        activateWizardTab(targetTab);
    }

    /**
     * ウィザードタブ表示の切替（goToStep からも利用。非表示 tab-btn の .click() は使わない）
     * @param {string} targetTab step1|step2|step3|step4
     */
    window.activateScheduleEditWizardTab = function (targetTab) {
        if (!targetTab) {
            return;
        }
        tabBtns.forEach(function (b) {
            b.classList.toggle('active', b.getAttribute('data-tab') === targetTab);
        });
        tabContents.forEach(function (content) {
            content.classList.toggle('active', content.id === targetTab);
        });
    };

    // 初期化
    updateWizardStepIndicator(1);

    // ナビゲーション状態の初期化
    updateNavigationState(1);

    // カレンダーの初期化
    initializeCalendar();

    // カード選択の初期化
    initializeCardSelection();
    initTrialSimplifiedScheduleEdit();

    // マッチ条件カードの初期化
    initializeMatchConditionCards();
    initializeScheduleTypeConditionDisplay();

    // チーム数スライダーの初期化
    initializeTeamSliders();

    // 会場名datalistの初期化
    updateVenueNameDatalist();


    // 初期状態でのチーム数表示制御（性別条件が未選択の場合は非表示）
    const genderConditionInput = document.getElementById('gender_condition');
    if (genderConditionInput && genderConditionInput.value) {
        updateTeamCountVisibility(genderConditionInput.value);
    } else {
        // 性別条件が未選択の場合は非表示
        updateTeamCountVisibility('');
    }

    // 初期状態での会場条件制御
    updateVenueConditionByRecruitCount();

    // 登録画面カレンダー用に、既存スケジュールを読み込んで反映
    loadExistingSchedulesForEditCalendar();

    // 日時タブ・曜日指定・メモカウンター
    initializeDateTabs();
    initializeMemoCounter();

    // リアルタイムバリデーションの初期化
    initializeRealtimeValidation();

    // 次へボタンの初期状態を更新
    updateNextButtonState();

    const intentInput = document.getElementById('intent');
    if (intentInput) {
        intentInput.addEventListener('change', function() {
            if (String(this.value || '').trim()) {
                syncScheduleTypesFromIntent({ force: true });
            }
        });
    }

    syncScheduleTypesFromIntent();
});

// 登録画面カレンダー用：日付ごとの既存スケジュール
let existingSchedulesByDate = {};

// カレンダーの初期化
function initializeCalendar() {
    bindScheduleEditCalendarMonthNav();
    renderCalendar();
}

/** 登録画面：前月・次月ボタン */
function bindScheduleEditCalendarMonthNav() {
    const prevBtn = document.getElementById('schedule-edit-calendar-prev-btn');
    const nextBtn = document.getElementById('schedule-edit-calendar-next-btn');
    if (prevBtn && !prevBtn.dataset.navBound) {
        prevBtn.dataset.navBound = '1';
        prevBtn.addEventListener('click', function(e) {
            e.preventDefault();
            previousMonth();
        });
    }
    if (nextBtn && !nextBtn.dataset.navBound) {
        nextBtn.dataset.navBound = '1';
        nextBtn.addEventListener('click', function(e) {
            e.preventDefault();
            nextMonth();
        });
    }
}

// カレンダーの描画
function renderCalendar() {
    const grid = document.getElementById('calendar_grid');
    if (!grid) return;

    const year = scheduleEditCalendarCurrentDate.getFullYear();
    const month = scheduleEditCalendarCurrentDate.getMonth();

    // プリセット適用後の確定日付を取得
    const confirmedDates = getConfirmedDates();

    // デバッグ用ログは削除（本番ノイズ防止）

    // 選択された日付のタイムスタンプを取得（複数選択対応、プリセット適用後の状態を反映）
    const selectedTimes = new Set();
    if (confirmedDates.length > 0) {
        confirmedDates.forEach(d => {
            // 日付のみを比較（時間は無視）
            const dateForCompare = new Date(d.getFullYear(), d.getMonth(), d.getDate());
            const timestamp = dateForCompare.getTime();
            selectedTimes.add(timestamp);
        });
    }

    // 範囲表示用（連続する日付がある場合）
    let rangeStartTime = null;
    let rangeEndTime = null;
    if (confirmedDates.length > 0) {
        const times = confirmedDates.map(d => {
            const newDate = new Date(d.getFullYear(), d.getMonth(), d.getDate());
            return newDate.getTime();
        });
        rangeStartTime = Math.min(...times);
        rangeEndTime = Math.max(...times);
    }

    // 選択外の斜線表示は削除（不要な処理を削除）

    // 月表示の更新
    const currentMonthElement = document.getElementById('current_month');
    if (currentMonthElement) {
        currentMonthElement.textContent = `${year}年${month + 1}月`;
    }

    // 月の最初の日と最後の日を取得
    const firstDay = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0);
    const startDate = new Date(firstDay);
    startDate.setDate(startDate.getDate() - firstDay.getDay());

    let html = '';

    // 曜日ヘッダーを生成（統一化）- calendar-gridの外に配置
    const weekdays = ['日', '月', '火', '水', '木', '金', '土'];
    const weekdayClasses = ['weekday-sunday', 'weekday-monday', 'weekday-tuesday', 'weekday-wednesday', 'weekday-thursday', 'weekday-friday', 'weekday-saturday'];
    let weekdaysHtml = '<div class="calendar-weekdays">';
    for (let i = 0; i < 7; i++) {
        weekdaysHtml += `<div class="weekday ${weekdayClasses[i]}">${weekdays[i]}</div>`;
    }
    weekdaysHtml += '</div>';

    // 曜日ヘッダーをcalendar-gridの前に挿入
    if (grid.parentElement) {
        const weekdaysElement = grid.parentElement.querySelector('.calendar-weekdays');
        if (weekdaysElement) {
            weekdaysElement.outerHTML = weekdaysHtml;
        } else {
            grid.insertAdjacentHTML('beforebegin', weekdaysHtml);
        }
    }

    let currentWeek = new Date(startDate);

    for (let week = 0; week < 6; week++) {
        html += '<div class="calendar-week">';
        for (let day = 0; day < 7; day++) {
            const date = new Date(currentWeek);
            const isCurrentMonth = date.getMonth() === month;

            // 日付比較を正確に行う（時間は無視）
            const dateForComparison = new Date(date.getFullYear(), date.getMonth(), date.getDate());

            // 選択された日付かどうか（複数選択対応）
            const dateTime = dateForComparison.getTime();
            const isSelected = selectedTimes.has(dateTime);

            // 今日の日付との比較
            const today = new Date();
            const todayForComparison = new Date(today.getFullYear(), today.getMonth(), today.getDate());
            const isToday = dateForComparison.getTime() === todayForComparison.getTime();

            // 過去の日付かどうかの判定
            const isPast = dateForComparison < todayForComparison;

            // 選択範囲内（連続する日付がある場合のみ、端点を除く）
            const isInRange = confirmedDates.length > 1 &&
                rangeStartTime !== null &&
                rangeEndTime !== null &&
                dateTime > rangeStartTime &&
                dateTime < rangeEndTime &&
                !isSelected;

            // 選択外の斜線表示は削除（未選択と一緒のため）
            const isExcluded = false;

            // 曜日・祝日の判定
            const dayOfWeek = date.getDay();
            const isSaturday = dayOfWeek === 6;
            const isSunday = dayOfWeek === 0;
            const isHoliday = isJapaneseHoliday(date);
            const isWeekday = !isSaturday && !isSunday && !isHoliday;

            // 統一クラス名を使用（calendar-day）
            let classes = 'calendar-day';
            if (!isCurrentMonth) classes += ' other-month';
            if (isPast) classes += ' past';
            if (isSelected) classes += ' selected';
            if (isToday && !isSelected) classes += ' today';
            if (isInRange) classes += ' in-range';
            // 選択外の斜線表示は削除（isExcluded は常に false）

            // 曜日・祝日のクラス追加
            if (isSaturday) classes += ' saturday';
            if (isSunday) classes += ' sunday';
            if (isHoliday) classes += ' holiday';
            if (isWeekday) classes += ' weekday';

            // ローカルタイムゾーンで日付文字列を生成（toISOString()によるタイムゾーン問題を回避）
            const dateString = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
            const clickHandler = isPast ? '' : `onclick="selectDate('${dateString}')"`;
            // 該当日の既存スケジュールを取得
            const daySchedules = existingSchedulesByDate[dateString] || [];
            let innerHtml = `<span class="day-number">${date.getDate()}`;
            if (isSelected) {
                innerHtml += `<span class="calendar-day__selected-mark">${scheduleEditIconHtml('check', 14)}</span>`;
            }
            innerHtml += '</span>';
            if (daySchedules.length > 0) {
                innerHtml += '<div class="schedule-list">';
                daySchedules.forEach(s => {
                    const title = s.type || 'スケジュール';
                    const hasTime = s.start_time && s.end_time;
                    const timeText = hasTime ? ' ' + s.start_time + '～' + s.end_time : '';
                    innerHtml += '<div class="schedule-card"><span class="schedule-line-1">' + title + timeText + '</span></div>';
                });
                innerHtml += '</div>';
            }
            html += `<div class="${classes}" data-date="${dateString}" ${clickHandler}>${innerHtml}</div>`;

            currentWeek.setDate(currentWeek.getDate() + 1);
        }
        html += '</div>';
    }

    grid.innerHTML = html;
}

// 登録画面カレンダー用：自チームの既存スケジュールをREST APIから取得して反映
function loadExistingSchedulesForEditCalendar() {
    try {
        fetch('/wp-json/aidunite/v1/get-user-schedules', {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': (window.wpApiSettings && window.wpApiSettings.nonce) ? window.wpApiSettings.nonce : ''
            }
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('HTTP status ' + response.status);
            }
            return response.json();
        })
        .then(responseBody => {
            // API は配列または { success, data } のどちらかを返す可能性がある
            var events = Array.isArray(responseBody)
                ? responseBody
                : (responseBody && Array.isArray(responseBody.data) ? responseBody.data : null);
            if (!events) {
                console.warn('get-user-schedules のレスポンス形式が想定外です', responseBody);
                return;
            }
            const byDate = {};
            events.forEach(ev => {
                if (!ev || !ev.date) return;
                if (!byDate[ev.date]) byDate[ev.date] = [];
                byDate[ev.date].push(ev);
            });
            existingSchedulesByDate = byDate;
            // データ反映後に再描画
            renderCalendar && renderCalendar();
        })
        .catch(error => {
            console.error('既存スケジュール取得エラー:', error);
        });
    } catch (e) {
        console.error('loadExistingSchedulesForEditCalendar 実行時エラー:', e);
    }
}

// 日付選択（個別選択のみ）
function selectDate(dateString) {
    const dateParts = dateString.split('-');
    const year = parseInt(dateParts[0]);
    const month = parseInt(dateParts[1]) - 1;
    const day = parseInt(dateParts[2]);
    const date = new Date(year, month, day, 12, 0, 0, 0);

    // 過去の日付は選択不可
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    if (date < today) return;

    const dateTime = new Date(date.getFullYear(), date.getMonth(), date.getDate()).getTime();

    // 既に選択されているかチェック
    const existingIndex = selectedDates.findIndex(d => {
        const dTime = new Date(d.getFullYear(), d.getMonth(), d.getDate()).getTime();
        return dTime === dateTime;
    });

    if (existingIndex >= 0) {
        // 既に選択されている場合は解除（トグル）
        selectedDates.splice(existingIndex, 1);
    } else {
        // 選択されていない場合は追加（時間を無視した日付のみ）
        const dateForStore = new Date(date.getFullYear(), date.getMonth(), date.getDate());
        selectedDates.push(dateForStore);
    }

    // 日付をソート（表示用）
    selectedDates.sort((a, b) => a.getTime() - b.getTime());

    // フォーム更新とカレンダー再描画
    updateDateInputs();
    renderCalendar();
    updateConfirmation();

    // リアルタイムバリデーション
    if (getCurrentStep() === 2) {
        validateDateAndTimeSelection();
        updateNextButtonState();
    }
}

// 日時タブ（カレンダー / 繰り返し）の初期化
function initializeDateTabs() {
    const tabs = document.querySelectorAll('.schedule-date-tab');
    const calendarPanel = document.getElementById('schedule-date-panel-calendar');
    const repeatPanel = document.getElementById('schedule-date-panel-repeat');

    tabs.forEach((tab) => {
        tab.addEventListener('click', function() {
            const clickedTab = this;
            const mode = clickedTab.getAttribute('data-date-mode');
            tabs.forEach((t) => {
                const isActive = t === clickedTab;
                t.classList.toggle('is-active', isActive);
                t.setAttribute('aria-selected', isActive ? 'true' : 'false');
            });

            if (mode === 'repeat') {
                if (calendarPanel) calendarPanel.hidden = true;
                if (repeatPanel) repeatPanel.hidden = false;
            } else {
                if (calendarPanel) calendarPanel.hidden = false;
                if (repeatPanel) repeatPanel.hidden = true;
            }
        });
    });

    const weekdaySelection = document.getElementById('weekday_selection_simple');
    if (weekdaySelection) {
        weekdaySelection.addEventListener('click', function(e) {
            const weekdayBtn = e.target.closest('.weekday-btn');
            if (weekdayBtn) {
                e.preventDefault();
                e.stopPropagation();
                weekdayBtn.classList.toggle('active');
                setTimeout(() => {
                    applyWeekdaySelection();
                }, 100);
            }
        });
    }

    const clearBtn = document.getElementById('clear-weekday-selection');
    if (clearBtn) {
        clearBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            clearWeekdaySelection();
        });
    }
}

// メモ文字数カウンター
function initializeMemoCounter() {
    const memoInput = document.getElementById('schedule_quick_memo');
    const counter = document.getElementById('schedule_memo_count');
    if (!memoInput || !counter) return;

    const sync = () => {
        counter.textContent = String(memoInput.value.length);
        if (typeof updateConfirmation === 'function') {
            updateConfirmation();
        }
    };
    memoInput.addEventListener('input', sync);
    memoInput.addEventListener('change', sync);
    sync();
}

// 曜日指定の適用（当月のみ）
function applyWeekdaySelection() {
    const weekdayButtons = document.querySelectorAll('#weekday_selection_simple .weekday-btn.active');

    if (weekdayButtons.length === 0) {
        if (typeof showToastNotification !== 'undefined') {
            showToastNotification('曜日を選択してください', 'warning');
        } else {
            console.warn('曜日を選択してください（トースト通知が利用できません）');
        }
        return;
    }

    // 既存の選択をクリア（上書き）
    selectedDates = [];

    // 選択された曜日を取得
    const selectedWeekdays = Array.from(weekdayButtons).map(btn => parseInt(btn.getAttribute('data-weekday')));

    // 当月の指定された曜日の日付を選択
    const year = scheduleEditCalendarCurrentDate.getFullYear();
    const month = scheduleEditCalendarCurrentDate.getMonth();
    const lastDay = new Date(year, month + 1, 0).getDate();
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    for (let day = 1; day <= lastDay; day++) {
        // 日付のみを作成（時間は無視）
        const date = new Date(year, month, day);
        const dayOfWeek = date.getDay(); // 曜日を取得（0=日曜日, 1=月曜日, ...）
        const dateForCompare = new Date(date.getFullYear(), date.getMonth(), date.getDate());

        // 過去の日付は除外し、選択された曜日のみを追加
        if (dateForCompare >= today && selectedWeekdays.includes(dayOfWeek)) {
            // 日付のみを保存（時間は無視）
            selectedDates.push(new Date(dateForCompare));
        }
    }

    // 日付をソート（表示用）
    selectedDates.sort((a, b) => a.getTime() - b.getTime());

    // フォーム更新とカレンダー再描画
    updateDateInputs();
    renderCalendar();
    updateConfirmation();

    // リアルタイムバリデーション
    if (getCurrentStep() === 2) {
        validateDateAndTimeSelection();
        updateNextButtonState();
    }
}

// 曜日指定の一括解除
function clearWeekdaySelection() {
    // 曜日ボタンの選択を解除
    const weekdayButtons = document.querySelectorAll('#weekday_selection_simple .weekday-btn');
    weekdayButtons.forEach(btn => {
        btn.classList.remove('active');
    });

    // 選択された日付をクリア
    selectedDates = [];

    // フォーム更新とカレンダー再描画
    updateDateInputs();
    renderCalendar();
    updateConfirmation();

    // リアルタイムバリデーション
    if (getCurrentStep() === 2) {
        validateDateAndTimeSelection();
        updateNextButtonState();
    }
}


// 日付入力フィールドの更新（複数選択対応）
function updateDateInputs() {
    if (selectedDates.length > 0) {
        // 日付をソート
        const sortedDates = [...selectedDates].sort((a, b) => a.getTime() - b.getTime());

        const startDateInput = document.getElementById('start_date');
        if (startDateInput) {
            const firstDate = sortedDates[0];
            const year = firstDate.getFullYear();
            const month = String(firstDate.getMonth() + 1).padStart(2, '0');
            const day = String(firstDate.getDate()).padStart(2, '0');
            const startDateValue = `${year}-${month}-${day}`;
            startDateInput.value = startDateValue;
        }

        // 終了日は最後の日付（複数選択時）
        const endDateInput = document.getElementById('end_date');
        if (endDateInput) {
            if (sortedDates.length > 1) {
                const lastDate = sortedDates[sortedDates.length - 1];
                const year = lastDate.getFullYear();
                const month = String(lastDate.getMonth() + 1).padStart(2, '0');
                const day = String(lastDate.getDate()).padStart(2, '0');
                const endDateValue = `${year}-${month}-${day}`;
                endDateInput.value = endDateValue;
            } else {
                endDateInput.value = '';
            }
        }

        // 全日程をカンマ区切りで保存
        const allDatesInput = document.getElementById('all_confirmed_dates');
        if (allDatesInput) {
            const allDatesString = sortedDates.map(date => {
                const year = date.getFullYear();
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const day = String(date.getDate()).padStart(2, '0');
                return `${year}-${month}-${day}`;
            }).join(',');
            allDatesInput.value = allDatesString;
        }

        // 選択された日付の表示を更新
        updateSelectedDatesDisplay();
    } else {
        // 選択がない場合はクリア
        const startDateInput = document.getElementById('start_date');
        const endDateInput = document.getElementById('end_date');
        const allDatesInput = document.getElementById('all_confirmed_dates');
        if (startDateInput) startDateInput.value = '';
        if (endDateInput) endDateInput.value = '';
        if (allDatesInput) allDatesInput.value = '';
        updateSelectedDatesDisplay();
    }
}


// 選択された日付の表示を更新（チップ形式: 5/3（日））
function updateSelectedDatesDisplay() {
    const displayElement = document.getElementById('selected_dates_display');
    const textElement = document.getElementById('selected_dates_text');
    const weekdayNames = ['日', '月', '火', '水', '木', '金', '土'];

    if (!displayElement || !textElement) return;

    if (selectedDates.length === 0) {
        displayElement.style.display = 'none';
        textElement.innerHTML = '';
        return;
    }

    const sortedDates = [...selectedDates].sort((a, b) => a.getTime() - b.getTime());
    textElement.innerHTML = '';

    sortedDates.forEach((d) => {
        const chip = document.createElement('div');
        chip.className = 'selected-date-chip';
        chip.setAttribute('role', 'group');

        const labelEl = document.createElement('span');
        labelEl.className = 'selected-date-chip__label';
        const label = `${d.getMonth() + 1}/${d.getDate()}（${weekdayNames[d.getDay()]}）`;
        labelEl.textContent = label;

        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'selected-date-chip__remove';
        removeBtn.setAttribute('aria-label', `${label} を解除`);
        removeBtn.innerHTML = scheduleEditIconHtml('close', 16);
        removeBtn.addEventListener('click', () => {
            const dateString = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
            selectDate(dateString);
        });

        chip.appendChild(labelEl);
        chip.appendChild(removeBtn);
        textElement.appendChild(chip);
    });

    displayElement.style.display = 'block';
}





// 前月・翌月の移動
function previousMonth() {
    scheduleEditCalendarCurrentDate.setMonth(scheduleEditCalendarCurrentDate.getMonth() - 1);
    renderCalendar();
}

function nextMonth() {
    scheduleEditCalendarCurrentDate.setMonth(scheduleEditCalendarCurrentDate.getMonth() + 1);
    renderCalendar();
}


function isTrialSimplifiedScheduleEdit() {
    return !!(window.AIDUNITE && window.AIDUNITE.trialSimplified);
}

function getVisibleSelectionCards(selector) {
    return Array.from(document.querySelectorAll(selector)).filter(function (card) {
        if (card.classList.contains('selection-card--trial-hidden') || card.hasAttribute('hidden')) {
            return false;
        }
        return card.offsetParent !== null;
    });
}

function trialPickableCardsSatisfied(selector) {
    const cards = getVisibleSelectionCards(selector);
    if (!cards.length) {
        return true;
    }
    return cards.every(function (card) {
        return card.classList.contains('selected');
    });
}

function syncVisibilitySelectionCards() {
    document.querySelectorAll('.schedule-visibility-selection .selection-card--visibility').forEach(function (card) {
        const input = card.querySelector('input[type="radio"]');
        card.classList.toggle('selected', !!(input && input.checked));
    });
    if (typeof updateNextButtonState === 'function') {
        updateNextButtonState();
    }
}

// カード選択の初期化
function initializeCardSelection() {
    const visibilitySection = document.querySelector('.schedule-visibility-selection');
    if (visibilitySection) {
        visibilitySection.querySelectorAll('input[type="radio"][name="schedule_visibility"]').forEach(function (radio) {
            radio.addEventListener('change', syncVisibilitySelectionCards);
        });
        visibilitySection.addEventListener('click', function (event) {
            const card = event.target.closest('.selection-card--visibility');
            if (!card || !visibilitySection.contains(card)) {
                return;
            }
            const radio = card.querySelector('input[type="radio"]');
            if (radio) {
                radio.checked = true;
                syncVisibilitySelectionCards();
            }
        });
    }

    // 目的選択カード（Step 1の目的選択セクション内のみ）
    const intentSection = document.querySelector('.intent-selection');
    if (intentSection) {
        intentSection.addEventListener('click', function (event) {
            const card = event.target.closest('.selection-card[data-value]');
            if (!card || !intentSection.contains(card) || card.classList.contains('is-locked')) {
                return;
            }
            if (card.classList.contains('selection-card--trial-hidden') || card.hasAttribute('hidden')) {
                return;
            }
            const value = card.getAttribute('data-value');
            selectCard(card, 'intent', value);
            syncScheduleTypesFromIntent({ force: true });

            if (!event.isTrusted) {
                return;
            }
            cancelScheduleEditIntentScrollTimer();
            scheduleEditIntentScrollTimer = setTimeout(function () {
                scheduleEditIntentScrollTimer = null;
                if (getCurrentStep() !== 1) {
                    return;
                }
                const nextButton = document.getElementById('nav-next');
                if (nextButton && nextButton.offsetParent !== null) {
                    nextButton.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }, 300);
        });

        intentSection.addEventListener('keydown', function (event) {
            const card = event.target.closest('.selection-card[data-value]');
            if (!card || !intentSection.contains(card) || card.classList.contains('is-locked')) {
                return;
            }
            if (event.key !== 'Enter' && event.key !== ' ') {
                return;
            }
            event.preventDefault();
            const value = card.getAttribute('data-value');
            selectCard(card, 'intent', value);
            syncScheduleTypesFromIntent({ force: true });
        });
    }
}

/** スケジュール登録フォームの document（テスト環境では getDocument を優先） */
function getScheduleEditDocument() {
    if (typeof getDocument === 'function') {
        const doc = getDocument();
        if (doc) {
            return doc;
        }
    }
    return typeof document !== 'undefined' ? document : null;
}

/**
 * STEP1 の intent 値を取得（hidden + 選択カードから補完）
 */
function resolveScheduleEditIntent() {
    const doc = getScheduleEditDocument();
    if (!doc) {
        return '';
    }
    const intentInput = doc.getElementById('intent');
    let intent = intentInput ? String(intentInput.value || '').trim() : '';

    if (!intent) {
        const selectedCard = doc.querySelector('.intent-selection .selection-card.selected[data-value]');
        if (selectedCard) {
            intent = selectedCard.getAttribute('data-value') || '';
            if (intent && intentInput) {
                intentInput.value = intent;
            }
        }
    }

    return intent;
}

// カード選択
function selectCard(card, field, value) {
    // 同じフィールドの他のカードの選択を解除
    const container = card.closest('.card-selection');
    if (container) {
        container.querySelectorAll('.selection-card').forEach(c => c.classList.remove('selected'));
    }

    // 選択されたカードをハイライト
    card.classList.add('selected');

    // 隠しフィールドの値を更新
    const fieldInput = document.getElementById(field);
    if (fieldInput) {
        fieldInput.value = value;
    }

    // 性別条件が変更された場合、チーム数の表示を制御
    if (field === 'gender_condition') {
        updateTeamCountVisibility(value);
        // 会場条件の制御も更新
        updateVenueConditionByRecruitCount();
    }

    // エラーメッセージを非表示にする
    if (field === 'intent') {
        const errorElement = document.getElementById('intent-error');
        if (errorElement) {
            errorElement.style.display = 'none';
        }
        // 目的変更時：募集チーム数はマッチのときのみ表示のため再評価
        const genderConditionInput = document.getElementById('gender_condition');
        updateTeamCountVisibility(genderConditionInput ? genderConditionInput.value : '');
        if (typeof updateNextButtonState === 'function') {
            updateNextButtonState();
        }
    } else if (field === 'schedule_type') {
        const errorElement = document.getElementById('schedule-type-error');
        if (errorElement) {
            errorElement.style.display = 'none';
        }
        if (typeof updateNextButtonState === 'function') {
            updateNextButtonState();
        }
    } else if (field === 'venue_condition') {
        const genderConditionInput = document.getElementById('gender_condition');
        if (typeof updateTeamCountVisibility === 'function') {
            updateTeamCountVisibility(genderConditionInput ? genderConditionInput.value : '');
        }
        if (typeof updateNextButtonState === 'function') {
            updateNextButtonState();
        }
    }

    // 確認画面の更新
    updateConfirmation();
}

// スケジュール種別の更新
function updateScheduleTypes(intent) {
    const doc = getScheduleEditDocument();
    if (!doc) {
        return;
    }

    const scheduleTypeCards = doc.getElementById('schedule_type_cards');
    const matchConditions = doc.getElementById('match_conditions');
    const normalizedIntent = String(intent || '').trim();

    if (!normalizedIntent) {
        if (scheduleTypeCards) scheduleTypeCards.innerHTML = '';
        if (matchConditions) matchConditions.style.display = 'none';
        return;
    }

    // 種別オプションをクリア
    if (scheduleTypeCards) scheduleTypeCards.innerHTML = '';

    resetRecruitGenderCardsVisibility();

    if (matchConditions && normalizedIntent !== 'recruit') {
        matchConditions.style.display = 'none';
    }

    if (normalizedIntent === 'confirmed') {
        // 確定の予定
        const options = [
            {value: '練習', text: '練習', icon: 'exercise', description: 'チーム練習'},
            {value: '公式試合', text: '公式試合', icon: 'trophy', description: '公式試合'},
            {value: '練習試合', text: '練習試合', icon: 'handshake', description: '練習試合'},
            {value: '合同練習', text: '合同練習', icon: 'group', description: '他チームとの合同練習'},
            {value: '合宿', text: '合宿', icon: 'camping', description: '合宿'},
            {value: '遠征', text: '遠征', icon: 'flight_takeoff', description: '遠征'},
            {value: 'ミーティング', text: 'ミーティング', icon: 'list_alt_add', description: 'ミーティング'},
            {value: '休み', text: '休み', icon: 'airline_seat_recline_extra', description: '練習休み'},
            {value: 'イベント', text: 'イベント', icon: 'celebration', description: 'チームイベント'},
            {value: '未定', text: '未定', icon: 'question_mark', description: '未定'}
        ];
        options.forEach(option => {
            if (scheduleTypeCards) {
                scheduleTypeCards.appendChild(createScheduleTypeCard(option));
            }
        });
    } else if (normalizedIntent === 'recruit') {
        const cfg = window.AIDUNITE || {};
        const options = [
            {value: '練習試合（募集）', text: '練習試合（募集）', icon: 'handshake', description: '対戦相手を募集します'},
            {value: '合同練習（募集）', text: '合同練習（募集）', icon: 'group', description: '合同練習の相手を募集します'},
        ];
        options.forEach(option => {
            if (scheduleTypeCards) {
                scheduleTypeCards.appendChild(createScheduleTypeCard(option));
            }
        });

        // マッチ条件を表示
        if (matchConditions) {
            matchConditions.style.display = 'block';
            // 表示後に recruit 向けデフォルト・制限を適用（リスナーは initializeMatchConditionCards で1回のみ）
            setTimeout(() => {
                applyRecruitGenderCardsTeamRestriction();
                normalizeLegacyRecruitGenderValue();
                applyTeamDefaultGenderConditionIfNeeded();
                applyTrialRecruitTypeRestrictions();
            }, 100);
        }
    } else if (normalizedIntent === 'tentative') {
        // 仮押さえ
        const options = [
            {value: '練習（仮）', text: '練習（仮）', icon: 'exercise', description: '練習日程の仮押さえ'},
            {value: '公式試合（仮）', text: '公式試合（仮）', icon: 'trophy', description: '公式試合の仮押さえ'},
            {value: '練習試合（仮）', text: '練習試合（仮）', icon: 'handshake', description: '練習試合の仮押さえ'},
            {value: '合同練習（仮）', text: '合同練習（仮）', icon: 'group', description: '合同練習の仮押さえ'},
            {value: '合宿（仮）', text: '合宿（仮）', icon: 'camping', description: '合宿の仮押さえ'},
            {value: '遠征（仮）', text: '遠征（仮）', icon: 'flight_takeoff', description: '遠征の仮押さえ'},
            {value: 'ミーティング（仮）', text: 'ミーティング（仮）', icon: 'list_alt_add', description: 'ミーティングの仮押さえ'},
            {value: '休み（仮）', text: '休み（仮）', icon: 'airline_seat_recline_extra', description: '練習休みの仮押さえ'},
            {value: 'イベント（仮）', text: 'イベント（仮）', icon: 'celebration', description: 'イベントの仮押さえ'},
            {value: '未定（仮）', text: '未定（仮）', icon: 'question_mark', description: '未定の仮押さえ'}
        ];
        options.forEach(option => {
            if (scheduleTypeCards) {
                scheduleTypeCards.appendChild(createScheduleTypeCard(option));
            }
        });
    }
}

/**
 * STEP1 で選んだ目的（intent）から STEP3 の種別カードを再構築する。
 * タブ非表示中の取りこぼし・履歴復帰・編集読込時に同期する。
 */
function syncScheduleTypesFromIntent(options) {
    const opts = options || {};
    const force = !!opts.force;
    const intent = resolveScheduleEditIntent();

    if (!intent) {
        return;
    }

    const doc = getScheduleEditDocument();
    const scheduleTypeCards = doc ? doc.getElementById('schedule_type_cards') : null;
    const needsBuild = !scheduleTypeCards || scheduleTypeCards.children.length === 0;

    if (force || needsBuild) {
        updateScheduleTypes(intent);
        return;
    }

    if (intent === 'recruit') {
        const matchConditions = doc ? doc.getElementById('match_conditions') : null;
        if (matchConditions && matchConditions.style.display === 'none') {
            updateScheduleTypes(intent);
        }
    }
}

/**
 * 無料期間中の recruit 特化モード初期化
 */
function initTrialSimplifiedScheduleEdit() {
    const cfg = window.AIDUNITE || {};
    if (!cfg.trialSimplified) {
        return;
    }

    // トライアル: チームの予定はタップで確定（初期は未選択の見た目）
    document.querySelectorAll('.schedule-visibility-selection input[name="schedule_visibility"]').forEach(function (radio) {
        radio.checked = false;
    });
    syncVisibilitySelectionCards();
    applyRecruitGenderFromTeamDefault();

    if (typeof updateNextButtonState === 'function') {
        updateNextButtonState();
    }
}

/**
 * チーム登録性別を性別カードへ自動反映（トライアル・新規 recruit 共通）
 */
function applyRecruitGenderFromTeamDefault() {
    applyRecruitGenderCardsTeamRestriction();
    normalizeLegacyRecruitGenderValue();
    applyTeamDefaultGenderConditionIfNeeded();
}

/**
 * トライアル中: 練習試合（募集）のみ選択可、合同練習（募集）はロック
 */
function applyTrialRecruitTypeRestrictions() {
    const cfg = window.AIDUNITE || {};
    if (!cfg.trialSimplified) {
        return;
    }

    const cards = document.querySelectorAll('#schedule_type_cards .selection-card');
    cards.forEach(function (card) {
        const val = card.getAttribute('data-value') || '';
        if (val.indexOf('練習試合（募集）') >= 0) {
            card.classList.add('is-locked');
            card.classList.remove('selected');
            const typeInput = document.getElementById('schedule_type');
            if (typeInput) {
                typeInput.value = val;
            }
        } else if (val.indexOf('合同練習（募集）') >= 0) {
            card.classList.add('selection-card--trial-hidden', 'is-locked');
        }
    });
}

// スケジュール種別カードの作成
function createScheduleTypeCard(option) {
    const card = document.createElement('div');
    card.className = 'selection-card';
    card.setAttribute('data-value', option.value);
    card.innerHTML = `
        <div class="card-header-row">
            <div class="card-icon">${scheduleEditIconHtml(option.icon, 24)}</div>
            <div class="card-title">${option.text}</div>
        </div>
        <div class="card-description">${option.description}</div>
    `;

    card.addEventListener('click', function() {
        if (this.classList.contains('is-locked') && !isTrialSimplifiedScheduleEdit()) {
            return;
        }
        selectCard(this, 'schedule_type', option.value);

        setTimeout(function () {
            scrollToNextStep3Section('schedule_type');
        }, 300);
    });

    return card;
}

/**
 * 新規登録かつ対戦マッチ希望時、チームの team_gender_option 由来のデフォルトで性別カードを選ぶ（未選択のときのみ）。
 * 編集モード・既に性別が入っている場合は何もしない。ユーザーは従来どおりカードで変更可能。
 */
function applyTeamDefaultGenderConditionIfNeeded() {
    const aid = window.AIDUNITE || {};
    if (aid.editPostId) {
        return;
    }
    const def = normalizeRecruitGenderForUi(aid.defaultGenderConditionFromTeam);
    if (!def || !['male', 'female'].includes(def)) {
        return;
    }
    const defUse = def;
    const genderInput = document.getElementById('gender_condition');
    if (!genderInput || genderInput.value) {
        return;
    }
    const intentInput = document.getElementById('intent');
    if (!intentInput || intentInput.value !== 'recruit') {
        return;
    }
    const genderCard = document.querySelector(
        `#match_conditions .match-conditions__gender .selection-card[data-value="${defUse}"]`
    );
    if (genderCard) {
        activateGenderConditionCard(genderCard, defUse);
    }
}

/**
 * チーム属性に応じて性別カードの表示を戻す（目的をマッチ以外にしたとき用）
 */
function resetRecruitGenderCardsVisibility() {
    const genderSection = document.querySelector('#match_conditions .match-conditions__gender');
    if (!genderSection) return;
    genderSection.querySelectorAll('.selection-card[data-value]').forEach((card) => {
        card.style.display = '';
        card.removeAttribute('aria-hidden');
    });
    const hint = document.getElementById('gender-restrict-notice');
    if (hint) {
        hint.style.display = 'none';
        hint.textContent = '';
    }
}

/**
 * 募集性別の正規化（MVP: male|female のみ）。レガシー both はチーム属性へフォールバック。
 *
 * @param {string} raw
 * @returns {string} male|female|''
 */
function normalizeRecruitGenderForUi(raw) {
    const g = String(raw || '').toLowerCase().trim();
    if (g === 'male' || g === 'female') {
        return g;
    }
    if (g === 'both' || g === 'mixed') {
        const aid = window.AIDUNITE || {};
        const tg = aid.teamGenderCanonical;
        if (tg === 'male' || tg === 'female') {
            return tg;
        }
        return 'male';
    }
    return '';
}

/**
 * 入力 hidden のレガシー both を male/female に置き換え、カード選択を同期する。
 */
function normalizeLegacyRecruitGenderValue() {
    const inp = document.getElementById('gender_condition');
    if (!inp) return;
    const normalized = normalizeRecruitGenderForUi(inp.value);
    if (!normalized || normalized === inp.value) {
        return;
    }
    const genderSection = document.querySelector('#match_conditions .match-conditions__gender');
    const card = genderSection
        ? genderSection.querySelector('.selection-card[data-value="' + normalized + '"]')
        : null;
    if (card && typeof selectCard === 'function') {
        selectCard(card, 'gender_condition', normalized);
    } else {
        inp.value = normalized;
        if (typeof updateTeamCountVisibility === 'function') {
            updateTeamCountVisibility(normalized);
        }
    }
}

/**
 * 男子／女子チームのとき募集性別カードを1枚に制限し、必ずその値を選択する。
 */
function applyRecruitGenderCardsTeamRestriction() {
    const aid = window.AIDUNITE || {};
    resetRecruitGenderCardsVisibility();
    const tg = aid.teamGenderCanonical;
    if (!tg || tg === '') {
        return;
    }
    const genderSection = document.querySelector('#match_conditions .match-conditions__gender');
    if (!genderSection) return;
    genderSection.querySelectorAll('.selection-card[data-value]').forEach((card) => {
        const v = card.getAttribute('data-value');
        card.style.display = v === tg ? '' : 'none';
    });
    const hint = document.getElementById('gender-restrict-notice');
    if (hint) {
        hint.style.display = 'none';
        hint.textContent = '';
    }
    const target = genderSection.querySelector('.selection-card[data-value="' + tg + '"]');
    if (target && typeof selectCard === 'function') {
        selectCard(target, 'gender_condition', tg);
    }
}

/**
 * 会場条件カードを選択（スクロールなし・STEP遷移時の自動反映用）
 * @param {HTMLElement} card
 * @param {string} value
 */
function activateVenueConditionCard(card, value) {
    const intent = (document.getElementById('intent') || {}).value || '';
    if (intent === 'confirmed' && value === 'both') {
        return;
    }
    selectCard(card, 'venue_condition', value);
    const scheduleTypeInput = document.getElementById('schedule_type');
    const scheduleType = scheduleTypeInput ? scheduleTypeInput.value : '';
    toggleVenueNameField(value, scheduleType, intent);

    if (intent === 'recruit' && value === 'away') {
        ensureRecruitCountForAway();
    }
    const genderConditionInput = document.getElementById('gender_condition');
    if (typeof updateTeamCountVisibility === 'function') {
        updateTeamCountVisibility(genderConditionInput ? genderConditionInput.value : '');
    }
}

/**
 * 性別条件カードを選択（スクロールなし）
 * @param {HTMLElement} card
 * @param {string} value
 */
function activateGenderConditionCard(card, value) {
    selectCard(card, 'gender_condition', value);
}

// マッチ条件カードの初期化（イベント委譲・1回のみバインド）
function initializeMatchConditionCards() {
    const matchConditionsSection = document.getElementById('match_conditions');
    if (!matchConditionsSection || matchConditionsSection.dataset.matchBound === '1') {
        return;
    }
    matchConditionsSection.dataset.matchBound = '1';

    matchConditionsSection.addEventListener('click', function(e) {
        const card = e.target.closest('.selection-card[data-value]');
        if (!card || !matchConditionsSection.contains(card)) {
            return;
        }

        const venueSection = matchConditionsSection.querySelector('.match-conditions__venue');
        const genderSection = matchConditionsSection.querySelector('.match-conditions__gender');
        const fromUser = e.isTrusted;

        if (venueSection && venueSection.contains(card)) {
            const value = card.getAttribute('data-value');
            activateVenueConditionCard(card, value);

            if (fromUser) {
                setTimeout(function () {
                    scrollToNextStep3Section('venue_condition');
                }, 300);
            }
            return;
        }

        if (genderSection && genderSection.contains(card)) {
            const value = card.getAttribute('data-value');
            activateGenderConditionCard(card, value);

            if (fromUser) {
                setTimeout(function () {
                    scrollToNextStep3Section('gender_condition');
                }, 300);
            }
        }
    });
}

// アウェイ選択時の募集数補完
function ensureRecruitCountForAway() {
    const genderConditionInput = document.getElementById('gender_condition');
    const maleTeamsInput = document.getElementById('male_teams');
    const femaleTeamsInput = document.getElementById('female_teams');
    const maleTeamsValue = document.getElementById('male_teams_value');
    const femaleTeamsValue = document.getElementById('female_teams_value');
    const gender = genderConditionInput ? genderConditionInput.value : '';

    if (gender === 'male' && maleTeamsInput && parseInt(maleTeamsInput.value || '0', 10) < 1) {
        maleTeamsInput.value = 1;
        if (maleTeamsValue) maleTeamsValue.textContent = '1チーム';
    } else if (gender === 'female' && femaleTeamsInput && parseInt(femaleTeamsInput.value || '0', 10) < 1) {
        femaleTeamsInput.value = 1;
        if (femaleTeamsValue) femaleTeamsValue.textContent = '1チーム';
    }

    updateTotalTeams();
}

// 種別選択に応じた条件表示の切替（イベント委譲・1回のみ）
function initializeScheduleTypeConditionDisplay() {
    const scheduleTypeCards = document.getElementById('schedule_type_cards');
    if (!scheduleTypeCards || scheduleTypeCards.dataset.conditionBound === '1') {
        return;
    }
    scheduleTypeCards.dataset.conditionBound = '1';

    scheduleTypeCards.addEventListener('click', function(e) {
        const card = e.target.closest('.selection-card');
        if (!card) return;
        const type = card.getAttribute('data-value') || '';
        const intent = (document.getElementById('intent') || {}).value || '';
        const matchConditions = document.getElementById('match_conditions');
        if (!matchConditions) return;
        // 初期状態
        matchConditions.style.display = 'none';
        // 会場名入力の未定カードの表示制御
        const undecided = matchConditions.querySelector('.selection-card[data-value="undecided"]');
        if (undecided) undecided.style.display = 'none';
        // セクション参照（順序：会場、性別、募集チーム数）
        const genderSection = matchConditions.querySelector('.match-conditions__gender');
        const venueSection = matchConditions.querySelector('.match-conditions__venue');
        const teamCountSection = document.getElementById('team-count-section');
        // アウェイ選択時は募集チーム数を非表示
        const venueConditionInput = document.getElementById('venue_condition');
        const venueCondition = venueConditionInput ? venueConditionInput.value : '';
        const genderConditionInput = document.getElementById('gender_condition');
        if (typeof updateTeamCountVisibility === 'function') {
            updateTeamCountVisibility(genderConditionInput ? genderConditionInput.value : '');
        }
        // 試合系かどうか
        const isMatchType = /(公式試合|練習試合|合同練習)/.test(type);
        const isPracticeCampTripEvent = /(練習|合宿|遠征|イベント)/.test(type);
        const isMeetingOrEvent = /(ミーティング|イベント)/.test(type);
        const isRest = type === '休み' || type === '休み（仮）';

        // 会場条件セクションを常に表示（休み以外）
        if (!isRest) {
            matchConditions.style.display = 'block';
            // ミーティングとイベントの場合は会場条件セクションを非表示（会場名は表示）
            if (venueSection) {
                venueSection.style.display = isMeetingOrEvent ? 'none' : 'block';
            }
            if (genderSection) genderSection.style.display = 'block';

            // 種別によって会場条件オプションを制限
            const venueCards = venueSection ? venueSection.querySelectorAll('.selection-card[data-value]') : [];
            venueCards.forEach(card => {
                const value = card.getAttribute('data-value');
                if (value === 'undecided') {
                    // 未定は仮の場合のみ表示
                    card.style.display = (intent === 'tentative') ? 'block' : 'none';
                } else if (value === 'away') {
                    // アウェイは「練習」（練習試合を除く）の場合は非表示（ただしマッチ希望の場合は表示）
                    // 練習試合は公式試合と同じように表示
                    const isPracticeOnly = /^練習$/.test(type) || (/^練習（仮）$/.test(type));
                    card.style.display = isPracticeOnly ? 'none' : 'block';
                    if (intent === 'recruit' && isPracticeOnly) {
                        card.style.display = 'block';
                    }
                } else if (value === 'both') {
                    // どちらでも可は「練習」（練習試合を除く）の場合は非表示（ただしマッチ希望の場合は表示）
                    // 練習試合は公式試合と同じように表示
                    const isPracticeOnly = /^練習$/.test(type) || (/^練習（仮）$/.test(type));
                    card.style.display = isPracticeOnly ? 'none' : 'block';
                    if (intent === 'recruit' && isPracticeOnly) {
                        card.style.display = 'block';
                    }
                }
            });

            // 性別条件の説明文の表示/非表示制御（試合系のみ表示）
            const genderDescriptions = genderSection ? genderSection.querySelectorAll('.gender-description') : [];
            genderDescriptions.forEach(desc => {
                desc.style.display = isMatchType ? 'block' : 'none';
            });
        } else {
            // 休みの場合は会場条件セクションを非表示
            matchConditions.style.display = 'none';
        }
        // 仮のときは会場に「未定」を表示
        if (intent === 'tentative' && undecided) {
            undecided.style.display = 'block';
        }
        // intent=confirmed のときのみ「会場条件」の「どちらでも可」をグレーアウト
        // NOTE: 性別条件の「男子・女子可」まで誤って無効化しないよう、会場セクションに限定する
        const bothCard = venueSection ? venueSection.querySelector('.selection-card[data-value="both"]') : null;
        if (bothCard) {
            if (intent === 'confirmed') {
                bothCard.classList.add('disabled');
                bothCard.style.opacity = '0.5';
                bothCard.style.pointerEvents = 'none';
                bothCard.setAttribute('aria-disabled', 'true');
            } else {
                bothCard.classList.remove('disabled');
                bothCard.style.opacity = '';
                bothCard.style.pointerEvents = '';
                bothCard.removeAttribute('aria-disabled');
            }
        }

        // 会場名フィールドの表示を更新（venueConditionInputは既に1031行目で宣言済み）
        const currentVenueCondition = venueConditionInput ? venueConditionInput.value : '';
        const currentIntent = (document.getElementById('intent') || {}).value || '';
        // ミーティングとイベントの場合は会場名を表示（会場条件は非表示）
        if (isMeetingOrEvent) {
            const venueNameSection = document.getElementById('venue-name-section');
            if (venueNameSection) {
                venueNameSection.style.display = 'block';
            }
        } else {
            toggleVenueNameField(currentVenueCondition, type, currentIntent);
        }

    });
}

// 会場名入力フィールドの表示/非表示（ホーム選択時または確定のときは表示。仮・対戦希望でアウェイのときは非表示。ミーティング・イベントは会場名のみ表示）
function toggleVenueNameField(venueCondition, scheduleType, intent) {
    const venueNameSection = document.getElementById('venue-name-section');
    if (!venueNameSection) return;
    const isMeetingOrEvent = /(ミーティング|イベント)/.test(scheduleType || '');
    const isConfirmed = (intent || (document.getElementById('intent') || {}).value || '') === 'confirmed';
    const show = isMeetingOrEvent || (venueCondition === 'home') || isConfirmed;
    venueNameSection.style.display = show ? 'block' : 'none';
    if (show) updateVenueNameDatalist();
}

// 会場名datalistを更新
function updateVenueNameDatalist() {
    const datalist = document.getElementById('venue-names');
    if (!datalist) return;

    // 既存のオプションをクリア
    datalist.innerHTML = '';

    // チームデータが利用可能か確認
    if (typeof window.teamVenueData === 'undefined') {
        return;
    }

    const { homeVenueName, venueHistory } = window.teamVenueData;

    // ホーム名を追加（存在する場合）
    if (homeVenueName && homeVenueName.trim() !== '') {
        const option = document.createElement('option');
        option.value = homeVenueName;
        datalist.appendChild(option);
    }

    // 履歴を追加（重複を避ける）
    const addedValues = new Set();
    if (homeVenueName && homeVenueName.trim() !== '') {
        addedValues.add(homeVenueName);
    }

    if (Array.isArray(venueHistory)) {
        venueHistory.forEach(venueName => {
            if (venueName && venueName.trim() !== '' && !addedValues.has(venueName)) {
                const option = document.createElement('option');
                option.value = venueName;
                datalist.appendChild(option);
                addedValues.add(venueName);
            }
        });
    }
}

// チーム数スライダーの初期化
function initializeTeamSliders() {
    const maleSlider = document.getElementById('male_teams');
    const femaleSlider = document.getElementById('female_teams');

    if (maleSlider) {
        maleSlider.addEventListener('input', function() {
            const maleTeamsValue = document.getElementById('male_teams_value');
            if (maleTeamsValue) {
                maleTeamsValue.textContent = this.value + 'チーム';
            }
            updateTotalTeams();
        });
    }

    if (femaleSlider) {
        femaleSlider.addEventListener('input', function() {
            const femaleTeamsValue = document.getElementById('female_teams_value');
            if (femaleTeamsValue) {
                femaleTeamsValue.textContent = this.value + 'チーム';
            }
            updateTotalTeams();
        });
    }

}

// 合計チーム数の更新
function updateTotalTeams() {
    const maleTeamsInput = document.getElementById('male_teams');
    const femaleTeamsInput = document.getElementById('female_teams');
    const totalTeamsValue = document.getElementById('total_teams_value');

    if (maleTeamsInput && femaleTeamsInput && totalTeamsValue) {
        const maleTeams = parseInt(maleTeamsInput.value) || 0;
        const femaleTeams = parseInt(femaleTeamsInput.value) || 0;

        // 募集チーム数の総計を計算（自チームは含めない）
        const maleTotal = maleTeams; // 男子募集チーム数
        const femaleTotal = femaleTeams; // 女子募集チーム数
        const grandTotal = maleTotal + femaleTotal;

        // 性別条件に応じて表示を変更
        const genderCondition = document.getElementById('gender_condition').value;

        if (genderCondition === 'male') {
            totalTeamsValue.textContent = maleTotal;
            // 表示テキストも更新
            const totalTeamsDiv = document.querySelector('.total-teams');
            if (totalTeamsDiv) {
                totalTeamsDiv.innerHTML = `募集チーム数: <span id="total_teams_value">${maleTotal}</span>チーム`;
            }
        } else if (genderCondition === 'female') {
            totalTeamsValue.textContent = femaleTotal;
            // 表示テキストも更新
            const totalTeamsDiv = document.querySelector('.total-teams');
            if (totalTeamsDiv) {
                totalTeamsDiv.innerHTML = `募集チーム数: <span id="total_teams_value">${femaleTotal}</span>チーム`;
            }
        } else { // 'both' or null/undefined
            totalTeamsValue.textContent = grandTotal;
            // 表示テキストも更新
            const totalTeamsDiv = document.querySelector('.total-teams');
            if (totalTeamsDiv) {
                totalTeamsDiv.innerHTML = `募集チーム数: <span id="total_teams_value">${grandTotal}</span>チーム`;
            }
        }
    }

    // 最小チーム数は参考情報として表示（制限なし）

    // 会場条件の制御（募集数に応じて）
    updateVenueConditionByRecruitCount();

    // 確認画面の更新
    updateConfirmation();
}

// 募集数に応じて会場条件を制御（どちらでもで2以上でもホーム固定しない方針のため、常に全選択肢表示・補足文非表示）
function updateVenueConditionByRecruitCount() {
    const matchConditionsSection = document.getElementById('match_conditions');
    const venueConditionHelp = document.getElementById('venue-condition-help');

    if (venueConditionHelp) {
        venueConditionHelp.style.display = 'none';
    }

    if (matchConditionsSection && matchConditionsSection.style.display !== 'none') {
        const venueSection = matchConditionsSection.querySelector('.match-conditions__venue');
        if (venueSection) {
            const venueCards = venueSection.querySelectorAll('.card-selection .selection-card[data-value]');
            const homeCard = Array.from(venueCards).find(card => card.getAttribute('data-value') === 'home');
            const awayCard = Array.from(venueCards).find(card => card.getAttribute('data-value') === 'away');
            const bothCard = Array.from(venueCards).find(card => card.getAttribute('data-value') === 'both');
            if (awayCard) awayCard.style.display = 'block';
            if (bothCard) bothCard.style.display = 'block';
            if (homeCard) homeCard.style.display = 'block';
        }
    }
}

// ヒーローステッパー（.step-item）の active / completed を更新
function updateWizardStepIndicator(stepNumber) {
    const stepItems = document.querySelectorAll('.step-item');
    if (!stepItems.length) {
        return;
    }
    const activeStep = Math.max(0, Math.min(3, stepNumber - 1));
    stepItems.forEach((item, index) => {
        item.classList.remove('active', 'completed');
        if (index < activeStep) {
            item.classList.add('completed');
        } else if (index === activeStep) {
            item.classList.add('active');
        }
    });
}

// ステップ間の移動
function goToStep(stepNumber, options) {
    const opts = options || {};
    const stepMap = { 1: 'step1', 2: 'step2', 3: 'step3', 4: 'step4' };
    const targetTab = stepMap[stepNumber];

    cancelScheduleEditIntentScrollTimer();

    if (document.activeElement && typeof document.activeElement.blur === 'function') {
        document.activeElement.blur();
    }

    if (targetTab) {
        if (typeof window.activateScheduleEditWizardTab === 'function') {
            window.activateScheduleEditWizardTab(targetTab);
        } else {
            const tabBtn = document.querySelector(`[data-tab="${targetTab}"]`);
            if (tabBtn) {
                tabBtn.click();
            }
        }
    }

    updateNavigationState(stepNumber);
    updateWizardStepIndicator(stepNumber);

    if (stepNumber === 3) {
        syncScheduleTypesFromIntent({ force: true });
    }

    if (stepNumber === 4 && typeof updateConfirmation === 'function') {
        updateConfirmation();
    }

    // STEP2〜4：ヒーローステッパーを見せるため先頭へ（フォーム中腹へのフォーカス追従を避ける）
    if (stepNumber >= 2 && !opts.skipScrollToTop) {
        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                scrollScheduleEditPageToTop();
            });
        });
    }

    // HistoryAPIでステップを履歴化（戻る/進むでページ離脱しない）
    try {
        if (!opts.skipPush) {
            const url = new URL(window.location.href);
            url.searchParams.set('step', String(stepNumber));
            const state = { page: 'schedule-edit', step: stepNumber };
            history.pushState(state, '', url.toString());
        }
    } catch (e) {
        // 無視（古いブラウザ等）
    }
}

function isWizardSectionVisible(sectionEl) {
    return !!(sectionEl && sectionEl.offsetParent !== null);
}

function scrollWizardSectionIntoView(sectionEl) {
    if (!isWizardSectionVisible(sectionEl)) {
        return;
    }
    sectionEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

/**
 * STEP3: カード選択後、次の未入力セクション（または次へ）へスクロール
 * @param {'schedule_type'|'gender_condition'|'venue_condition'} afterField
 */
function scrollToNextStep3Section(afterField) {
    if (getCurrentStep() !== 3) {
        return;
    }
    const matchConditions = document.getElementById('match_conditions');
    const genderSection = matchConditions ? matchConditions.querySelector('.match-conditions__gender') : null;
    const venueSection = matchConditions ? matchConditions.querySelector('.match-conditions__venue') : null;
    const teamCountSection = document.getElementById('team-count-section');
    const nextButton = document.getElementById('nav-next-3');

    if (afterField === 'schedule_type') {
        if (isWizardSectionVisible(venueSection)) {
            scrollWizardSectionIntoView(venueSection);
        } else if (isWizardSectionVisible(genderSection)) {
            scrollWizardSectionIntoView(genderSection);
        } else {
            scrollWizardSectionIntoView(nextButton);
        }
        return;
    }

    if (afterField === 'venue_condition') {
        if (isWizardSectionVisible(genderSection)) {
            scrollWizardSectionIntoView(genderSection);
        } else if (isWizardSectionVisible(teamCountSection)) {
            scrollWizardSectionIntoView(teamCountSection);
        } else {
            scrollWizardSectionIntoView(nextButton);
        }
        return;
    }

    if (afterField === 'gender_condition') {
        if (isWizardSectionVisible(teamCountSection)) {
            scrollWizardSectionIntoView(teamCountSection);
        } else {
            scrollWizardSectionIntoView(nextButton);
        }
    }
}

// 前のステップに移動
function goToPreviousStep() {
    const currentStep = getCurrentStep();
    if (currentStep > 1) {
        goToStep(currentStep - 1);
    }
}

// 次のステップに移動
function goToNextStep() {
    const currentStep = getCurrentStep();

    // 現在のステップでの必須チェック
    if (currentStep === 1) {
        // Step 1: 目的の選択（種別カード構築は STEP3 入室時に実施）
        if (!validateIntentSelection()) return;
    } else if (currentStep === 2) {
        // Step 2: 日程・時間の設定
        if (!validateDateAndTimeSelection()) return;
    } else if (currentStep === 3) {
        // Step 3: 種別・条件の設定
        if (!validateScheduleTypeSelection()) return;
    }

    if (currentStep < 4) {
        goToStep(currentStep + 1);
    }
}

// 現在のステップを取得
function getCurrentStep() {
    const doc = getDocument();
    if (!doc) return 1;

    const activeTab = doc.querySelector('.tab-content.active');
    if (activeTab) {
        const tabId = activeTab.id;
        if (tabId === 'step1') return 1; // 目的
        if (tabId === 'step2') return 2; // 日程・時間
        if (tabId === 'step3') return 3; // 種別・条件
        if (tabId === 'step4') return 4; // 確認・登録
    }
    return 1;
}

// ナビゲーション状態を更新
function updateNavigationState(step) {
    // 各ステップのナビゲーション要素を取得
    const navElements = {
        1: {
            next: document.getElementById('nav-next')
        },
        2: {
            prev: document.getElementById('nav-prev-2'),
            next: document.getElementById('nav-next-2')
        },
        3: {
            prev: document.getElementById('nav-prev-3'),
            next: document.getElementById('nav-next-3')
        },
        4: {
            prev: document.getElementById('nav-prev-4'),
            submit: document.getElementById('nav-submit-4')
        }
    };

    const currentElements = navElements[step];
    if (currentElements) {
        // 戻るボタン（step=1の時のみ非表示）— display:block にするとシェブロンとラベルが縦ずれする
        if (currentElements.prev) {
            currentElements.prev.style.display = step === 1 ? 'none' : 'inline-flex';
        }

        // 次へボタン（step=1,2,3の時表示）
        if (currentElements.next) {
            currentElements.next.style.display = step < 4 ? 'inline-flex' : 'none';
        }

        // 登録ボタン（step=4の時のみ表示）
        if (currentElements.submit) {
            currentElements.submit.style.display = step === 4 ? 'inline-flex' : 'none';
        }
    }
}



// 目的選択のバリデーション
function validateIntentSelection() {
    const doc = getDocument();
    const intentInput = doc ? doc.getElementById('intent') : null;
    const errorElement = doc ? doc.getElementById('intent-error') : null;

    if (!intentInput || !intentInput.value) {
        if (errorElement) {
            errorElement.style.display = 'flex';
        }
        return false;
    }

    if (errorElement) {
        errorElement.style.display = 'none';
    }
    return true;
}

// "HH:MM" を 0 時からの分数に変換（重複チェック用）
function timeStringToMinutes(str) {
    if (!str || typeof str !== 'string') return 0;
    const parts = str.trim().split(':');
    const h = parseInt(parts[0], 10) || 0;
    const m = parseInt(parts[1], 10) || 0;
    return h * 60 + m;
}

// 指定日・時間帯と重なる既存予定のラベル一覧を返す
function getOverlappingScheduleLabels(dateString, startMin, endMin) {
    const list = existingSchedulesByDate[dateString] || [];
    const labels = [];
    for (let i = 0; i < list.length; i++) {
        const s = list[i];
        if (!s.start_time || !s.end_time) continue;
        const a1 = timeStringToMinutes(s.start_time);
        const a2 = timeStringToMinutes(s.end_time);
        if (startMin < a2 && a1 < endMin) {
            labels.push((s.type || 'スケジュール') + ' ' + s.start_time + '～' + s.end_time);
        }
    }
    return labels;
}

// 日程・時間選択のバリデーション
function validateDateAndTimeSelection() {
    const errors = [];
    const doc = getDocument();

    // 日程のチェック
    const startDateInput = doc ? doc.getElementById('start_date') : null;
    const allConfirmedDatesInput = doc ? doc.getElementById('all_confirmed_dates') : null;
    const hasDates = (startDateInput && startDateInput.value) || (allConfirmedDatesInput && allConfirmedDatesInput.value);

    // テスト環境ではglobal.selectedDatesを優先的に使用
    const datesToCheck = (typeof global !== 'undefined' && global.selectedDates) ? global.selectedDates : selectedDates;

    if (!hasDates || datesToCheck.length === 0) {
        errors.push('日程を選択してください');
        showDateError('日程を選択してください');
    } else {
        hideDateError();
    }

    // 時間のチェック
    const startHour = doc ? doc.getElementById('start_hour') : null;
    const startMinute = doc ? doc.getElementById('start_minute') : null;
    const endHour = doc ? doc.getElementById('end_hour') : null;
    const endMinute = doc ? doc.getElementById('end_minute') : null;

    if (!startHour || !startMinute || !endHour || !endMinute) {
        errors.push('時間を設定してください');
        showTimeError('時間を設定してください');
    } else {
        const startTime = parseInt(startHour.value, 10) * 60 + parseInt(startMinute.value, 10);
        const endTime = parseInt(endHour.value, 10) * 60 + parseInt(endMinute.value, 10);

        if (startTime >= endTime) {
            errors.push('終了時間は開始時間より後にしてください');
            showTimeError('終了時間は開始時間より後にしてください');
        } else {
            hideTimeError();
        }
    }

    // バリデーション通過時のみ、既存予定との時間重複をチェックしてトーストで注意喚起
    if (errors.length === 0 && Array.isArray(datesToCheck) && datesToCheck.length > 0) {
        const startMin = parseInt(startHour.value, 10) * 60 + parseInt(startMinute.value, 10);
        const endMin = parseInt(endHour.value, 10) * 60 + parseInt(endMinute.value, 10);
        const allLabels = [];
        for (let d = 0; d < datesToCheck.length; d++) {
            const dateStr = datesToCheck[d];
            if (!dateStr) continue;
            const labels = getOverlappingScheduleLabels(dateStr, startMin, endMin);
            for (let i = 0; i < labels.length; i++) {
                if (allLabels.indexOf(labels[i]) === -1) allLabels.push(labels[i]);
            }
        }
        if (allLabels.length > 0) {
            const message = 'この時間帯は既に「' + allLabels.join('」「') + '」が登録されています。重複してよければこのまま登録できます。';
            if (typeof showToastNotification !== 'undefined') {
                showToastNotification(message, 'warning');
            }
        }
    }

    return errors.length === 0;
}

// 日程エラーの表示
function showDateError(message) {
    const doc = getDocument();
    if (!doc) return;

    let errorElement = doc.getElementById('date-error');
    if (!errorElement) {
        const dateSelection = doc.querySelector('.date-selection');
        if (dateSelection) {
            errorElement = doc.createElement('div');
            errorElement.id = 'date-error';
            errorElement.className = 'error-message';
            errorElement.style.display = 'flex';
            // XSS対策: innerHTMLの代わりにtextContentを使用
            const errorIcon = doc.createElement('div');
            errorIcon.className = 'error-icon';
            if (typeof AidUniteThemeIcons !== 'undefined') {
                AidUniteThemeIcons.setHtml(errorIcon, 'brightness_alert', 48);
            }
            const errorText = doc.createElement('div');
            errorText.className = 'error-text';
            errorText.textContent = message;
            errorElement.appendChild(errorIcon);
            errorElement.appendChild(errorText);
            dateSelection.appendChild(errorElement);
        }
    } else {
        errorElement.style.display = 'flex';
        const errorText = errorElement.querySelector('.error-text');
        if (errorText) errorText.textContent = message;
    }

    // エラーがある項目を視覚的に強調
    const calendarContainer = doc.querySelector('.calendar-container');
    if (calendarContainer) {
        calendarContainer.classList.add('has-error');
    }
}

// 日程エラーの非表示
function hideDateError() {
    const doc = getDocument();
    if (!doc) return;

    const errorElement = doc.getElementById('date-error');
    if (errorElement) {
        errorElement.style.display = 'none';
    }

    const calendarContainer = doc.querySelector('.calendar-container');
    if (calendarContainer) {
        calendarContainer.classList.remove('has-error');
    }
}

// 時間エラーの表示
function showTimeError(message) {
    const doc = getDocument();
    if (!doc) return;

    let errorElement = doc.getElementById('time-error');
    if (!errorElement) {
        const timeSettings = doc.querySelector('.time-settings');
        if (timeSettings) {
            errorElement = doc.createElement('div');
            errorElement.id = 'time-error';
            errorElement.className = 'error-message';
            errorElement.style.display = 'flex';
            // XSS対策: innerHTMLの代わりにtextContentを使用
            const errorIcon = doc.createElement('div');
            errorIcon.className = 'error-icon';
            if (typeof AidUniteThemeIcons !== 'undefined') {
                AidUniteThemeIcons.setHtml(errorIcon, 'brightness_alert', 48);
            }
            const errorText = doc.createElement('div');
            errorText.className = 'error-text';
            errorText.textContent = message;
            errorElement.appendChild(errorIcon);
            errorElement.appendChild(errorText);
            timeSettings.appendChild(errorElement);
        }
    } else {
        errorElement.style.display = 'flex';
        const errorText = errorElement.querySelector('.error-text');
        if (errorText) errorText.textContent = message;
    }

    // エラーがある項目を視覚的に強調
    const timeInputContainer = doc.querySelector('.time-input-container');
    if (timeInputContainer) {
        timeInputContainer.classList.add('has-error');
    }
}

// 時間エラーの非表示
function hideTimeError() {
    const doc = getDocument();
    if (!doc) return;

    const errorElement = doc.getElementById('time-error');
    if (errorElement) {
        errorElement.style.display = 'none';
    }

    const timeInputContainer = doc.querySelector('.time-input-container');
    if (timeInputContainer) {
        timeInputContainer.classList.remove('has-error');
    }
}

// 種別選択のバリデーション
function validateScheduleTypeSelection() {
    const doc = getDocument();
    const scheduleTypeInput = doc ? doc.getElementById('schedule_type') : null;
    const errorElement = doc ? doc.getElementById('schedule-type-error') : null;

    if (!scheduleTypeInput || !scheduleTypeInput.value) {
        if (errorElement) {
            errorElement.style.display = 'flex';
        }
        return false;
    }

    // マッチ希望の場合、募集チーム数のバリデーション
    const intentInput = doc ? doc.getElementById('intent') : null;
    const intent = intentInput ? intentInput.value : '';

    if (intent === 'recruit') {
        const genderCondition = doc ? (doc.getElementById('gender_condition')?.value || '') : '';
        const venueCondition = doc ? (doc.getElementById('venue_condition')?.value || '') : '';
        const maleTeamsInput = doc ? doc.getElementById('male_teams') : null;
        const femaleTeamsInput = doc ? doc.getElementById('female_teams') : null;

        // 性別条件のエラーチェックは削除（性別が選択されていなくてもエラーにしない）

    }

    if (errorElement) {
        errorElement.style.display = 'none';
    }
    return true;
}

// 確定した日程を取得する関数（複数選択対応）
function getConfirmedDates() {
    // 選択された日付をそのまま返す
    if (selectedDates.length === 0) {
        return [];
    }

    // 日付をソートして返す
    return [...selectedDates].sort((a, b) => a.getTime() - b.getTime());
}

// 確認画面の更新
function updateConfirmation() {
    const intentInput = document.getElementById('intent');
    const scheduleTypeInput = document.getElementById('schedule_type');
    const startDateInput = document.getElementById('start_date');
    const endDateInput = document.getElementById('end_date');
    const startHourInput = document.getElementById('start_hour');
    const startMinuteInput = document.getElementById('start_minute');
    const endHourInput = document.getElementById('end_hour');
    const endMinuteInput = document.getElementById('end_minute');

    // 確認項目の更新
    const confirmIntent = document.getElementById('confirm_intent');
    const confirmScheduleType = document.getElementById('confirm_schedule_type');
    const confirmStartDate = document.getElementById('confirm_start_date');

    if (confirmIntent && intentInput) {
        confirmIntent.textContent = getIntentLabel(intentInput.value);
    }

    // 予定の種類（チーム/プライベート）の確認表示（代表者のみ）
    const visibilityRadio = document.querySelector('input[name="schedule_visibility"]:checked');
    const visibilityValue = visibilityRadio ? visibilityRadio.value : 'personal';
    const visibilityLabel = visibilityValue === 'team' ? 'チーム' : 'プライベート';
    const confirmVisibility = document.getElementById('confirm_visibility');
    const confirmVisibilityRow = document.getElementById('confirm_visibility_row');
    if (confirmVisibility) confirmVisibility.textContent = visibilityLabel;
    if (confirmVisibilityRow) confirmVisibilityRow.style.display = visibilityRadio ? '' : 'none';

    if (confirmScheduleType && scheduleTypeInput) {
        // 種別の値をそのまま表示（既に適切なラベルが設定されている）
        const scheduleTypeValue = scheduleTypeInput.value;
        confirmScheduleType.textContent = scheduleTypeValue || '-';
    }

    // 登録日程の確認（yy/mm/dd統一。複数日は1日目のみyy/mm/dd、2日目以降はmm/dd）
    if (confirmStartDate) {
        const confirmedDates = selectedDates.length > 0 ? getConfirmedDates() : [];
        if (confirmedDates.length > 0) {
            const toYyMmDd = (d) => {
                const y = String(d.getFullYear()).slice(-2);
                const m = String(d.getMonth()+1).padStart(2,'0');
                const day = String(d.getDate()).padStart(2,'0');
                return `${y}/${m}/${day}`;
            };
            const toMmDd = (d) => {
                const m = String(d.getMonth()+1).padStart(2,'0');
                const day = String(d.getDate()).padStart(2,'0');
                return `${m}/${day}`;
            };
            if (confirmedDates.length === 1) {
                confirmStartDate.textContent = toYyMmDd(confirmedDates[0]);
            } else {
                confirmStartDate.innerHTML = '';
                const first = document.createElement('div');
                first.className = 'date-list-first';
                first.textContent = toYyMmDd(confirmedDates[0]);
                confirmStartDate.appendChild(first);
                const rest = document.createElement('div');
                rest.className = 'date-list-rest';
                confirmedDates.slice(1).forEach((d) => {
                    const span = document.createElement('div');
                    span.className = 'date-list-item';
                    span.textContent = toMmDd(d);
                    rest.appendChild(span);
                });
                confirmStartDate.appendChild(rest);
            }
            confirmStartDate.setAttribute('data-type', 'date');
        } else if (startDateInput && startDateInput.value) {
            const dateValue = startDateInput.value;
            if (dateValue.match(/^\d{4}-\d{2}-\d{2}$/)) {
                const parts = dateValue.split('-');
                confirmStartDate.textContent = `${parts[0].slice(-2)}/${parts[1]}/${parts[2]}`;
                if (endDateInput && endDateInput.value && endDateInput.value !== startDateInput.value) {
                    const endParts = endDateInput.value.split('-');
                    confirmStartDate.textContent += ` 〜 ${endParts[0].slice(-2)}/${endParts[1]}/${endParts[2]}`;
                }
            } else {
                confirmStartDate.textContent = dateValue;
            }
            confirmStartDate.setAttribute('data-type', 'date');
        } else {
            confirmStartDate.textContent = '-';
        }
    }

    // 時間の確認
    const confirmTime = document.getElementById('confirm_time');
    if (confirmTime && startHourInput && startMinuteInput && endHourInput && endMinuteInput) {
        const startH = String(startHourInput.value).padStart(2,'0');
        const startM = String(startMinuteInput.value).padStart(2,'0');
        const endH = String(endHourInput.value).padStart(2,'0');
        const endM = String(endMinuteInput.value).padStart(2,'0');
        confirmTime.textContent = `${startH}:${startM}-${endH}:${endM}` || '-';
        confirmTime.setAttribute('data-type', 'time');
    }

    // 性別条件の確認
    const genderConditionInput = document.getElementById('gender_condition');
    const confirmGender = document.getElementById('confirm_gender');
    if (confirmGender && genderConditionInput) {
        const genderValue = genderConditionInput.value;
        confirmGender.textContent = genderValue ? getGenderLabel(genderValue) : '-';
    }

    // 会場（条件＋会場名）の確認
    const venueConditionInput = document.getElementById('venue_condition');
    const venueNameInput = document.getElementById('venue_name');
    const confirmVenueConditionAndName = document.getElementById('confirm_venue_condition_and_name');
    if (confirmVenueConditionAndName) {
        const venueParts = [];
        if (venueConditionInput && venueConditionInput.value) {
            venueParts.push(getVenueLabel(venueConditionInput.value));
        }
        if (venueNameInput && venueNameInput.value) {
            venueParts.push(venueNameInput.value);
        }
        confirmVenueConditionAndName.textContent = venueParts.length > 0 ? venueParts.join(' ') : '-';
    }

    // マッチ希望の場合：募集数とメモを表示
    const confirmTeamCountRow = document.getElementById('confirm_team_count_row');
    const confirmMemoRow = document.getElementById('confirm_memo_row');
    const confirmMemoRowLeader = document.getElementById('confirm_memo_row_leader');
    const confirmTeamCount = document.getElementById('confirm_team_count');
    const confirmMemo = document.getElementById('confirm_quick_memo');
    const confirmMemoSingle = document.getElementById('confirm_quick_memo_single');
    const memoInput = document.getElementById('schedule_quick_memo');

    if (intentInput && intentInput.value === 'recruit') {
        // マッチ希望の場合：募集数とメモを2列表示
        if (confirmTeamCountRow) {
            confirmTeamCountRow.style.display = '';
        }
        if (confirmMemoRow) {
            confirmMemoRow.style.display = 'none';
        }
        if (confirmMemoRowLeader) {
            confirmMemoRowLeader.style.display = 'none';
        }

        // 募集数の表示
        if (confirmTeamCount) {
            const maleTeamsInput = document.getElementById('male_teams');
            const femaleTeamsInput = document.getElementById('female_teams');
            const maleTeams = maleTeamsInput ? parseInt(maleTeamsInput.value) || 0 : 0;
            const femaleTeams = femaleTeamsInput ? parseInt(femaleTeamsInput.value) || 0 : 0;

            const teamDetails = [];
            if (maleTeams > 0) teamDetails.push(`男子${maleTeams}チーム`);
            if (femaleTeams > 0) teamDetails.push(`女子${femaleTeams}チーム`);
            confirmTeamCount.textContent = teamDetails.length > 0 ? teamDetails.join('・') : '-';
        }

        // メモの表示（マッチ希望用）
        if (confirmMemo) {
            confirmMemo.textContent = (memoInput && memoInput.value) ? memoInput.value : '-';
        }
    } else {
        // 確定・仮の場合：メモのみを1列表示
        if (confirmTeamCountRow) {
            confirmTeamCountRow.style.display = 'none';
        }
        if (confirmMemoRow) {
            confirmMemoRow.style.display = '';
        }
        if (confirmMemoRowLeader) {
            confirmMemoRowLeader.style.display = '';
        }

        // メモの表示（確定・仮用）
        if (confirmMemoSingle) {
            confirmMemoSingle.textContent = (memoInput && memoInput.value) ? memoInput.value : '-';
        }
    }
}

// ラベル取得関数
function getIntentLabel(intent) {
    const labels = {
        'confirmed': '確定の予定を登録',
        'recruit': '試合を募集する',
        'tentative': '日程を仮押さえ'
    };
    return labels[intent] || '-';
}

function getVenueLabel(venue) {
    const aid = window.AIDUNITE || {};
    const labels = aid.venueConditionLabels || {};
    const v = String(venue || '').toLowerCase().trim();
    if (labels[v]) {
        return labels[v];
    }
    if (typeof AidUniteScheduleUtils !== 'undefined' && AidUniteScheduleUtils.getVenueConditionLabel) {
        return AidUniteScheduleUtils.getVenueConditionLabel(v);
    }
    return v || '—';
}

function getGenderLabel(gender) {
    const aid = window.AIDUNITE || {};
    const labels = aid.recruitGenderLabels || { male: '男子', female: '女子' };
    const g = normalizeRecruitGenderForUi(gender);
    return labels[g] || '—';
}

// 編集モード: 既存スケジュールデータを読み込む
function loadScheduleForEdit(postId) {
    if (!postId) {
        console.error('loadScheduleForEdit: postId is required');
        return;
    }

    //  loadScheduleForEdit: Fetching schedule data for post_id=' + postId);

    // REST APIでスケジュールデータを取得
    fetch(`/wp-json/wp/v2/schedule/${postId}`, {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': (window.wpApiSettings && window.wpApiSettings.nonce) ? window.wpApiSettings.nonce : ''
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('HTTP status ' + response.status);
        }
        return response.json();
    })
    .then(schedule => {
        //  loadScheduleForEdit: Loaded schedule data', schedule);

        // 目的（intent）を設定
        const intentInput = document.getElementById('intent');
        if (intentInput && schedule.meta && schedule.meta.intent) {
            intentInput.value = schedule.meta.intent;
            updateScheduleTypes(schedule.meta.intent);
            const intentCard = document.querySelector(`.intent-selection .selection-card[data-value="${schedule.meta.intent}"]`);
            if (intentCard) {
                intentCard.classList.add('selected');
            }
        }

        // 種別を設定
        const scheduleTypeInput = document.getElementById('schedule_type');
        if (scheduleTypeInput && schedule.meta && schedule.meta.schedule_type) {
            scheduleTypeInput.value = schedule.meta.schedule_type;
            // 種別選択のイベントを発火
            const scheduleTypeCards = document.getElementById('schedule_type_cards');
            if (scheduleTypeCards) {
                const typeCard = scheduleTypeCards.querySelector(`[data-value="${schedule.meta.schedule_type}"]`);
                if (typeCard) {
                    selectCard(typeCard, 'schedule_type', schedule.meta.schedule_type);
                }
            }
            //  loadScheduleForEdit: Set schedule_type=' + schedule.meta.schedule_type);
        }

        // 日付を設定
        if (schedule.meta && schedule.meta.schedule_date) {
            const startDateInput = document.getElementById('start_date');
            if (startDateInput) {
                startDateInput.value = schedule.meta.schedule_date;
                selectedDates = [new Date(schedule.meta.schedule_date)];
                renderCalendar && renderCalendar();
                //  loadScheduleForEdit: Set date=' + schedule.meta.schedule_date);
            }
        }

        // 時間を設定
        if (schedule.meta && schedule.meta.schedule_start_time) {
            const [startHour, startMinute] = schedule.meta.schedule_start_time.split(':');
            const startHourInput = document.getElementById('start_hour');
            const startMinuteInput = document.getElementById('start_minute');
            if (startHourInput) startHourInput.value = startHour;
            if (startMinuteInput) startMinuteInput.value = startMinute;
            //  loadScheduleForEdit: Set start_time=' + schedule.meta.schedule_start_time);
        }

        if (schedule.meta && schedule.meta.schedule_end_time) {
            const [endHour, endMinute] = schedule.meta.schedule_end_time.split(':');
            const endHourInput = document.getElementById('end_hour');
            const endMinuteInput = document.getElementById('end_minute');
            if (endHourInput) endHourInput.value = endHour;
            if (endMinuteInput) endMinuteInput.value = endMinute;
            //  loadScheduleForEdit: Set end_time=' + schedule.meta.schedule_end_time);
        }

        // 会場条件を設定（統一メタキー優先）
        const venueCondition = schedule.meta.schedule_place || schedule.meta.schedule_place_option || schedule.meta.venue_condition || '';
        if (venueCondition) {
            const venueConditionInput = document.getElementById('venue_condition');
            if (venueConditionInput) {
                venueConditionInput.value = venueCondition;
                // 会場条件カードを選択状態にする
                const venueCard = document.querySelector(`[data-value="${venueCondition}"]`);
                if (venueCard && venueCard.closest('#match_conditions .match-conditions__venue')) {
                    activateVenueConditionCard(venueCard, venueCondition);
                }
                //  loadScheduleForEdit: Set venue_condition=' + venueCondition);
            }
        }

        // 性別条件を設定（統一メタキー優先）
        const genderConditionRaw = schedule.meta.schedule_gender || schedule.meta.matching_gender_condition || schedule.meta.gender_condition || '';
        const genderCondition = normalizeRecruitGenderForUi(genderConditionRaw);
        if (genderCondition) {
            const genderConditionInput = document.getElementById('gender_condition');
            if (genderConditionInput) {
                genderConditionInput.value = genderCondition;
                // 性別条件カードを選択状態にする
                const genderCard = document.querySelector(`#match_conditions .match-conditions__gender .selection-card[data-value="${genderCondition}"]`);
                if (genderCard && genderCard.closest('#match_conditions .match-conditions__gender')) {
                    activateGenderConditionCard(genderCard, genderCondition);
                }
                //  loadScheduleForEdit: Set gender_condition=' + genderCondition);
            }
        }

        // 会場名を設定
        if (schedule.meta && schedule.meta.venue_name) {
            const venueNameInput = document.getElementById('venue_name');
            if (venueNameInput) {
                venueNameInput.value = schedule.meta.venue_name;
                //  loadScheduleForEdit: Set venue_name=' + schedule.meta.venue_name);
            }
        }

        // メモを設定
        if (schedule.meta && schedule.meta.schedule_quick_memo) {
            const memoInput = document.getElementById('schedule_quick_memo');
            if (memoInput) {
                memoInput.value = schedule.meta.schedule_quick_memo;
                //  loadScheduleForEdit: Set schedule_quick_memo=' + schedule.meta.schedule_quick_memo);
            }
        }

        // チーム数を設定（マッチ希望の場合）
        if (schedule.meta && schedule.meta.male_teams) {
            const maleTeamsInput = document.getElementById('male_teams');
            if (maleTeamsInput) {
                maleTeamsInput.value = schedule.meta.male_teams;
                maleTeamsInput.dispatchEvent(new Event('input'));
            }
        }
        if (schedule.meta && schedule.meta.female_teams) {
            const femaleTeamsInput = document.getElementById('female_teams');
            if (femaleTeamsInput) {
                femaleTeamsInput.value = schedule.meta.female_teams;
                femaleTeamsInput.dispatchEvent(new Event('input'));
            }
        }

        // 確認画面を更新
        updateConfirmation();

        const m = schedule.meta || {};
        const venueInit = m.schedule_place || m.schedule_place_option || m.venue_condition || '';
        const genderInit = m.schedule_gender || m.matching_gender_condition || m.gender_condition || '';
        const intentStr = String(m.intent || '');
        const matchOne = (m.matching === 1 || m.matching === '1' || intentStr === 'recruit') ? 1 : 0;
        window.__scheduleEditInitialMeta = {
            intent: intentStr,
            schedule_type: String(m.schedule_type || ''),
            schedule_date: String(m.schedule_date || ''),
            schedule_start_time: String(m.schedule_start_time || ''),
            schedule_end_time: String(m.schedule_end_time || ''),
            venue: String(venueInit),
            gender: String(genderInit),
            matching: matchOne
        };

        if (intentStr === 'recruit') {
            setTimeout(() => {
                applyRecruitGenderCardsTeamRestriction();
                normalizeLegacyRecruitGenderValue();
            }, 250);
        }

        //  loadScheduleForEdit: Completed loading schedule data');
    })
    .catch(error => {
        console.error('loadScheduleForEdit: ERROR - Failed to load schedule data', error);
    });
}

// チーム数表示の可視性を制御（マッチ希望・会場選択後のみ表示。操作順: 種別→会場→募集）
function updateTeamCountVisibility(genderCondition) {
    const teamCountSection = document.getElementById('team-count-section');
    const intentInput = document.getElementById('intent');
    const intent = intentInput ? intentInput.value : '';
    const venueConditionInput = document.getElementById('venue_condition');
    const venueCondition = venueConditionInput ? String(venueConditionInput.value || '').trim() : '';

    // マッチ以外（確定・仮）の場合は常に非表示
    if (intent !== 'recruit') {
        if (teamCountSection) {
            teamCountSection.style.display = 'none';
        }
        return;
    }

    // 性別未設定、会場未選択、アウェイのときは非表示（会場確定後に募集へ進む）
    if (!genderCondition || !venueCondition || venueCondition === 'away') {
        if (teamCountSection) {
            teamCountSection.style.display = 'none';
        }
        return;
    }

    if (teamCountSection) {
        teamCountSection.style.display = 'block';
    }

    // チーム数設定セクションの要素を取得
    const teamCountItems = document.querySelectorAll('.team-count-item');
    const maleTeamsItem = Array.from(teamCountItems).find(item =>
        item.querySelector('.team-count-label') &&
        item.querySelector('.team-count-label').textContent.includes('男子')
    );
    const femaleTeamsItem = Array.from(teamCountItems).find(item =>
        item.querySelector('.team-count-label') &&
        item.querySelector('.team-count-label').textContent.includes('女子')
    );
    const totalTeamsDiv = document.querySelector('.total-teams');

    // すべての要素を一旦非表示
    if (maleTeamsItem) maleTeamsItem.style.display = 'none';
    if (femaleTeamsItem) femaleTeamsItem.style.display = 'none';
    if (totalTeamsDiv) totalTeamsDiv.style.display = 'none';

    // 性別条件に応じて表示制御
    if (genderCondition === 'male') {
        // 男子のみ：男子チーム数のみ表示
        if (maleTeamsItem) {
            maleTeamsItem.style.display = 'block';
        }
        // 女子チーム数を0にリセット
        const femaleTeamsSlider = document.getElementById('female_teams');
        if (femaleTeamsSlider) {
            femaleTeamsSlider.value = 0;
            const femaleTeamsValue = document.getElementById('female_teams_value');
            if (femaleTeamsValue) {
                femaleTeamsValue.textContent = '0チーム';
            }
        }
        // 合計チーム数を表示（男子のみ）
        if (totalTeamsDiv) {
            totalTeamsDiv.style.display = 'block';
        }
    } else if (genderCondition === 'female') {
        // 女子のみ：女子チーム数のみ表示
        if (femaleTeamsItem) {
            femaleTeamsItem.style.display = 'block';
        }
        // 男子チーム数を0にリセット
        const maleTeamsSlider = document.getElementById('male_teams');
        if (maleTeamsSlider) {
            maleTeamsSlider.value = 0;
            const maleTeamsValue = document.getElementById('male_teams_value');
            if (maleTeamsValue) {
                maleTeamsValue.textContent = '0チーム';
            }
        }
        // 合計チーム数を表示（女子のみ）
        if (totalTeamsDiv) {
            totalTeamsDiv.style.display = 'block';
        }
    }

    // 合計チーム数を更新
    updateTotalTeams();
}

// フォーム入力時の確認画面更新
document.addEventListener('DOMContentLoaded', function() {
    // 初期状態でのチーム数表示制御
    const genderConditionInput = document.getElementById('gender_condition');
    if (genderConditionInput && genderConditionInput.value) {
        // 性別条件が既に設定されている場合、その設定に基づいて表示制御
        updateTeamCountVisibility(genderConditionInput.value);
    }

    const formInputs = document.querySelectorAll('#scheduleForm input, #scheduleForm select');
    formInputs.forEach(input => {
        input.addEventListener('change', function() {
            updateConfirmation();
            // リアルタイムバリデーション
            const currentStep = getCurrentStep();
            if (currentStep === 1) {
                validateIntentSelection();
            } else if (currentStep === 2) {
                validateDateAndTimeSelection();
            } else if (currentStep === 3) {
                validateScheduleTypeSelection();
            }
            updateNextButtonState();
        });

        // 入力中もリアルタイムバリデーション（時間入力など）
        if (input.type === 'select-one' || input.tagName === 'SELECT') {
            input.addEventListener('input', function() {
                const currentStep = getCurrentStep();
                if (currentStep === 2) {
                    validateDateAndTimeSelection();
                    updateNextButtonState();
                }
            });
        }
    });

    // 初期ステップの履歴状態を整える + 事前選択日付の反映
    try {
        const url = new URL(window.location.href);
        const stepParam = parseInt(url.searchParams.get('step') || '1', 10);
        const initialStep = isNaN(stepParam) ? 1 : Math.min(Math.max(stepParam, 1), 4);
        history.replaceState({ page: 'schedule-edit', step: initialStep }, '', url.toString());
        if (initialStep !== getCurrentStep()) {
            // URLのstepに合わせて表示だけ切替（pushはしない）
            goToStep(initialStep, { skipPush: true });
        }

        // 編集モードの処理
        const editPostId = (window.AIDUNITE && window.AIDUNITE.editPostId) || url.searchParams.get('post_id') || null;
        if (editPostId) {
            //  Edit mode: Loading schedule data for post_id=' + editPostId);
            loadScheduleForEdit(editPostId);
        }

        // スケジュール一覧などから ?date= で来た場合のみ事前日付を選択（未選択のままにしたいためURLパラメータのみ反映）
        const presetFromQuery = url.searchParams.get('date');
        if (presetFromQuery) {
            const startDateInput = document.getElementById('start_date');
            if (startDateInput) startDateInput.value = presetFromQuery;
            selectedDates = [new Date(presetFromQuery)];
            renderCalendar && renderCalendar();
            updateConfirmation();
        } else {
            // URLに日付がない場合は未選択で開始（HTMLのvalueは使わずクリア）
            const startDateInput = document.getElementById('start_date');
            const endDateInput = document.getElementById('end_date');
            const allDatesInput = document.getElementById('all_confirmed_dates');
            if (startDateInput) startDateInput.value = '';
            if (endDateInput) endDateInput.value = '';
            if (allDatesInput) allDatesInput.value = '';
            selectedDates = [];
            updateDateInputs && updateDateInputs();
            renderCalendar && renderCalendar();
        }
    } catch (e) {}

    // 戻る/進むでステップだけ戻す
    window.addEventListener('popstate', function(event) {
        const state = event.state;
        if (state && state.page === 'schedule-edit' && typeof state.step === 'number') {
            goToStep(state.step, { skipPush: true });
        }
    });

    // フォーム送信時の処理
    const scheduleForm = document.getElementById('scheduleForm');
    if (scheduleForm) {
        scheduleForm.addEventListener('submit', handleFormSubmit);
    }

    // 送信ボタンのタッチイベント処理（スマホ対応）
    const submitButton = document.querySelector('button[type="submit"]');
    if (submitButton) {
        submitButton.addEventListener('touchstart', function(e) {
            e.preventDefault();
            this.style.transform = 'scale(0.95)';
            this.style.opacity = '0.8';
        });

        submitButton.addEventListener('touchend', function(e) {
            e.preventDefault();
            this.style.transform = 'scale(1)';
            this.style.opacity = '1';
            // フォーム送信をトリガー
            if (scheduleForm && !isSubmitting) {
                handleFormSubmit({ target: scheduleForm, preventDefault: () => {} });
            }
        });
    }
});

// フォーム送信処理
let isSubmitting = false; // 重複送信防止フラグ

/**
 * 編集フォームから「敏感項目」相当のスナップショットを取得（サーバー aidunite_can_update_schedule と対応）
 * @returns {{intent:string,schedule_date:string,schedule_start_time:string,schedule_end_time:string,schedule_type:string,venue:string,gender:string,matching:number}}
 */
function scheduleEditCollectSensitiveSnapshot() {
    const intentEl = document.getElementById('intent');
    const intent = intentEl ? String(intentEl.value || '') : '';
    const startDate = (document.getElementById('start_date') && document.getElementById('start_date').value) ? String(document.getElementById('start_date').value) : '';
    const sh = (document.getElementById('start_hour') && document.getElementById('start_hour').value) ? String(document.getElementById('start_hour').value) : '00';
    const sm = (document.getElementById('start_minute') && document.getElementById('start_minute').value) ? String(document.getElementById('start_minute').value) : '00';
    const eh = (document.getElementById('end_hour') && document.getElementById('end_hour').value) ? String(document.getElementById('end_hour').value) : '00';
    const em = (document.getElementById('end_minute') && document.getElementById('end_minute').value) ? String(document.getElementById('end_minute').value) : '00';
    const scheduleTypeEl = document.getElementById('schedule_type');
    const scheduleType = scheduleTypeEl ? String(scheduleTypeEl.value || '') : '';
    const venueEl = document.getElementById('venue_condition');
    const venue = venueEl ? String(venueEl.value || '') : '';
    const genderEl = document.getElementById('gender_condition');
    const gender = genderEl ? String(genderEl.value || '') : '';
    const matching = (intent === 'recruit') ? 1 : 0;
    return {
        intent: intent,
        schedule_date: startDate,
        schedule_start_time: sh + ':' + sm,
        schedule_end_time: eh + ':' + em,
        schedule_type: scheduleType,
        venue: venue,
        gender: gender,
        matching: matching
    };
}

/**
 * @param {object|null} initial
 * @param {object} cur
 * @returns {boolean}
 */
function scheduleEditSensitiveChanged(initial, cur) {
    if (!initial) {
        return false;
    }
    return String(initial.intent) !== String(cur.intent)
        || String(initial.schedule_date) !== String(cur.schedule_date)
        || String(initial.schedule_start_time) !== String(cur.schedule_start_time)
        || String(initial.schedule_end_time) !== String(cur.schedule_end_time)
        || String(initial.schedule_type) !== String(cur.schedule_type)
        || String(initial.venue) !== String(cur.venue)
        || String(initial.gender) !== String(cur.gender)
        || Number(initial.matching) !== Number(cur.matching);
}

function scheduleEditMessageForBlocked() {
    if (typeof AidUniteScheduleModal !== 'undefined' && AidUniteScheduleModal.messageForScheduleErrorCode) {
        return AidUniteScheduleModal.messageForScheduleErrorCode('schedule_edit_blocked', '');
    }
    return '申請中または成立に関するマッチがあるため、日時・会場・目的などの重要項目を変更できません。先に申請の扱いを解消するか、メモのみ更新してください。';
}

/**
 * 送信直前: カード選択 → hidden（intent / 性別 / 会場）を同期
 */
function scheduleEditSyncHiddenFieldsBeforeSubmit() {
    if (typeof resolveScheduleEditIntent === 'function') {
        resolveScheduleEditIntent();
    }
    const cfg = window.AIDUNITE || {};
    if (cfg.trialSimplified) {
        const intentInput = document.getElementById('intent');
        if (intentInput) {
            intentInput.value = 'recruit';
        }
    }
    const syncFromSection = function (hiddenId) {
        const input = document.getElementById(hiddenId);
        if (!input) {
            return;
        }
        const section = input.closest('.schedule-wizard-section');
        const card = section
            ? section.querySelector('.selection-card.selected[data-value]')
            : null;
        if (card) {
            const value = card.getAttribute('data-value') || '';
            if (value) {
                input.value = value;
            }
        }
    };
    syncFromSection('gender_condition');
    syncFromSection('venue_condition');
    if (typeof normalizeLegacyRecruitGenderValue === 'function') {
        normalizeLegacyRecruitGenderValue();
    }
}

function handleFormSubmit(event) {
    // 重複送信防止
    if (isSubmitting) {
        event.preventDefault();
        return;
    }

    // フォーム送信を一時停止
    event.preventDefault();

    scheduleEditSyncHiddenFieldsBeforeSubmit();

    // 送信フラグを設定
    isSubmitting = true;

    // 送信ボタンを無効化
    const submitButton = document.querySelector('button[type="submit"]');
    if (submitButton) {
        submitButton.disabled = true;
        submitButton.textContent = '登録中...';
    }

    // フォーム送信前に複数日程の情報を更新
    updateFormWithConfirmedDates();
    // アウェイ選択時は募集数入力が隠れるため、送信直前にも最小値を補完
    const intentInputForSubmit = document.getElementById('intent');
    const venueConditionForSubmit = document.getElementById('venue_condition');
    if (intentInputForSubmit && venueConditionForSubmit
        && intentInputForSubmit.value === 'recruit'
        && venueConditionForSubmit.value === 'away') {
        ensureRecruitCountForAway();
    }

    // ローディングスピナーを表示
    const loadingSpinner = document.getElementById('loading-spinner');
    if (loadingSpinner) {
        loadingSpinner.style.display = 'flex';
    }

    const form = event.target;
    const formDataToSend = new FormData(form);
    const formAction = form.action || window.location.href;

    const resetSubmitUi = function() {
        isSubmitting = false;
        if (submitButton) {
            submitButton.disabled = false;
            submitButton.textContent = '登録する（公開）';
        }
        if (loadingSpinner) {
            loadingSpinner.style.display = 'none';
        }
    };

    const runSubmitFetch = function() {
    fetch(formAction, {
        method: 'POST',
        body: formDataToSend
    })
    .then(response => {
        const aidCfg = window.AIDUNITE || {};
        if (response.redirected && response.url) {
            // TODO(検証後削除): マイページで stage2 トーストをリロード再表示
            if (
                aidCfg.activationRedirectUrl
                || String(response.url).indexOf('activation=stage2') !== -1
            ) {
                try {
                    sessionStorage.setItem('aidunite_stage2_toasts_debug', '1');
                } catch (storageErr) {
                    /* ignore */
                }
            }
            window.location.replace(response.url);
            return { success: true, handledRedirect: true };
        }

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }

        return response.text().then(text => {
            if (response.redirected || response.url !== formAction) {
                return { success: true, redirected: true };
            }

            if (text.includes('error-message') || text.includes('入力エラー') || text.includes('エラーがあります')) {
                console.error('スケジュール登録: 入力エラーを検出');
                return { success: false, error: '入力エラーがあります' };
            }

            if (response.url && response.url.includes('saved=1')) {
                return { success: true };
            }

            if (text.includes('success') || text.includes('登録') || response.status === 200) {
                return { success: true };
            }

            if (response.status === 200 && !text.includes('error')) {
                return { success: true };
            }

            try {
                const jsonData = JSON.parse(text);
                return jsonData;
            } catch (e) {
                return { success: true };
            }
        });
    })
    .then(data => {
        if (data && data.handledRedirect) {
            return;
        }

        // 登録成功時のみリダイレクト
        if (data && data.success !== false) {
            // セッションストレージのキャッシュをクリア（登録した月のデータ）
            const now = new Date();
            const year = now.getFullYear();
            const month = now.getMonth() + 1;
            const start = `${year}-${String(month).padStart(2, '0')}-01`;
            const end = `${year}-${String(month).padStart(2, '0')}-${new Date(year, month, 0).getDate()}`;
            const cacheKey = `schedules_${start}_${end}`;
            sessionStorage.removeItem(cacheKey);
            sessionStorage.removeItem(`${cacheKey}_time`);

            // 1.0秒後にローディングスピナーを非表示
            setTimeout(() => {
                if (loadingSpinner) loadingSpinner.style.display = 'none';

                // 完了モーダルを一瞬だけ出してから管理画面へ戻る
                const completionMessage = document.getElementById('completion-message');
                if (completionMessage) {
                    completionMessage.style.display = 'flex';
                }
                setTimeout(() => {
                    const aid = window.AIDUNITE || {};
                    const redirectUrl = aid.activationRedirectUrl
                        || ((window.AIDUNITE && window.AIDUNITE.scheduleManagementUrl) || '/schedule-management');
                    const timestamp = Date.now();
                    if (aid.activationRedirectUrl) {
                        // TODO(検証後削除): マイページで stage2 トーストをリロード再表示
                        try {
                            sessionStorage.setItem('aidunite_stage2_toasts_debug', '1');
                        } catch (storageErr) {
                            /* ignore */
                        }
                        window.location.replace(redirectUrl);
                        return;
                    }
                    window.location.replace(`${redirectUrl}?refresh=${timestamp}`);
                }, 1500); // 1.5秒待機してデータベース反映を確実に
            }, 1000);
        } else {
            throw new Error('スケジュールの登録に失敗しました');
        }
    })
    .catch(error => {
        resetSubmitUi();
        console.error('フォーム送信エラー:', error);
        if (typeof showToastNotification !== 'undefined') {
            showToastNotification('登録中にエラーが発生しました。もう一度お試しください。', 'error');
        } else {
            console.error('トースト通知が利用できません');
        }
    });
    };

    const postIdInput = form.querySelector('input[name="post_id"]');
    const editPostIdRaw = (window.AIDUNITE && window.AIDUNITE.editPostId) || (postIdInput && postIdInput.value);
    const editPostIdNum = editPostIdRaw ? parseInt(String(editPostIdRaw), 10) : 0;

    if (editPostIdNum > 0 && typeof AidUniteAjaxUtils !== 'undefined' && typeof AidUniteAjaxUtils.getScheduleDependencies === 'function') {
        AidUniteAjaxUtils.getScheduleDependencies({
            schedule_id: editPostIdNum,
            onSuccess: function(data) {
                const dep = (data && data.dependencies) || {};
                const risky = !!(dep.has_pending || dep.has_in_play);
                const curSnap = scheduleEditCollectSensitiveSnapshot();
                if (risky && scheduleEditSensitiveChanged(window.__scheduleEditInitialMeta, curSnap)) {
                    resetSubmitUi();
                    const msg = scheduleEditMessageForBlocked();
                    if (typeof showToastNotification !== 'undefined') {
                        showToastNotification(msg, 'error');
                    } else {
                        alert(msg);
                    }
                    return;
                }
                runSubmitFetch();
            },
            onError: function() {
                runSubmitFetch();
            }
        });
        return;
    }

    runSubmitFetch();
}

// 確定された日程をフォームに反映する関数
function updateFormWithConfirmedDates() {
    const confirmedDates = getConfirmedDates();

    if (confirmedDates.length === 0) {
        return;
    }

    // 開始日と終了日を更新
    const startDateInput = document.getElementById('start_date');
    const endDateInput = document.getElementById('end_date');
    const allDatesInput = document.getElementById('all_confirmed_dates');

    if (startDateInput && confirmedDates.length > 0) {
        // 開始日（最初の日付）
        const startDate = confirmedDates[0];
        const startDateString = `${startDate.getFullYear()}-${String(startDate.getMonth() + 1).padStart(2, '0')}-${String(startDate.getDate()).padStart(2, '0')}`;
        startDateInput.value = startDateString;
    }

    if (endDateInput && confirmedDates.length > 1) {
        // 終了日（最後の日付）
        const endDate = confirmedDates[confirmedDates.length - 1];
        const endDateString = `${endDate.getFullYear()}-${String(endDate.getMonth() + 1).padStart(2, '0')}-${String(endDate.getDate()).padStart(2, '0')}`;
        endDateInput.value = endDateString;
    } else if (endDateInput) {
        endDateInput.value = '';
    }

    // 隠しフィールドに全日程を保存
    if (allDatesInput && confirmedDates.length > 0) {
        const allDatesString = confirmedDates.map(date =>
            `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
        ).join(',');
        allDatesInput.value = allDatesString;
    }
}

// リアルタイムバリデーションの初期化
function initializeRealtimeValidation() {
    const intentSection = document.querySelector('.intent-selection');
    if (intentSection && intentSection.dataset.validationBound !== '1') {
        intentSection.dataset.validationBound = '1';
        intentSection.addEventListener('click', function(e) {
            if (!e.target.closest('.selection-card[data-value]')) {
                return;
            }
            setTimeout(() => {
                validateIntentSelection();
                updateNextButtonState();
            }, 100);
        });
    }

    // 種別選択のリアルタイムバリデーション
    document.addEventListener('click', function(e) {
        const scheduleTypeCard = e.target.closest('#schedule_type_cards .selection-card');
        if (scheduleTypeCard && getCurrentStep() === 3) {
            setTimeout(() => {
                validateScheduleTypeSelection();
                updateNextButtonState();
            }, 100);
        }
    });

    // 開始・終了の時・分（別セレクト）変更時
    const timeInputs = document.querySelectorAll('#start_hour, #start_minute, #end_hour, #end_minute');
    timeInputs.forEach(function (input) {
        if (input.dataset.timeValidationBound === '1') {
            return;
        }
        input.dataset.timeValidationBound = '1';
        const onTimeFieldChange = function () {
            if (getCurrentStep() !== 2) {
                return;
            }
            validateDateAndTimeSelection();
            updateNextButtonState();
        };
        input.addEventListener('change', onTimeFieldChange);
        input.addEventListener('input', onTimeFieldChange);
    });
}

// 次へボタンの状態を更新（必須項目が未入力の場合、無効化）
function updateNextButtonState() {
    const currentStep = getCurrentStep();
    let isValid = false;

    const trialMode = isTrialSimplifiedScheduleEdit();

    if (currentStep === 1) {
        const intentInput = document.getElementById('intent');
        if (trialMode) {
            isValid = trialPickableCardsSatisfied('.schedule-visibility-selection .selection-card--visibility:not(.selection-card--trial-hidden)')
                && trialPickableCardsSatisfied('.intent-selection .selection-card[data-value="recruit"]')
                && intentInput
                && intentInput.value !== '';
        } else {
            isValid = intentInput && intentInput.value !== '';
        }
    } else if (currentStep === 2) {
        // Step 2: 日程・時間の設定
        const hasDates = selectedDates.length > 0 ||
                        (document.getElementById('start_date') && document.getElementById('start_date').value) ||
                        (document.getElementById('all_confirmed_dates') && document.getElementById('all_confirmed_dates').value);
        const startHour = document.getElementById('start_hour');
        const endHour = document.getElementById('end_hour');
        const hasTime = startHour && endHour && startHour.value !== '' && endHour.value !== '';

        if (hasDates && hasTime) {
            const startTime = parseInt(startHour.value) * 60 + parseInt(document.getElementById('start_minute').value);
            const endTime = parseInt(endHour.value) * 60 + parseInt(document.getElementById('end_minute').value);
            isValid = startTime < endTime;
        }
    } else if (currentStep === 3) {
        const scheduleTypeInput = document.getElementById('schedule_type');
        isValid = scheduleTypeInput && scheduleTypeInput.value !== '';

        const intentInput = document.getElementById('intent');
        if (intentInput && intentInput.value === 'recruit') {
            const venueCondition = document.getElementById('venue_condition');
            const genderCondition = document.getElementById('gender_condition');
            const maleTeams = document.getElementById('male_teams');
            const femaleTeams = document.getElementById('female_teams');

            if (!venueCondition || !venueCondition.value) {
                isValid = false;
            }
            if (!genderCondition || !genderCondition.value) {
                isValid = false;
            }
            if (maleTeams && femaleTeams) {
                const total = parseInt(maleTeams.value, 10) + parseInt(femaleTeams.value, 10);
                if (total < 1) {
                    isValid = false;
                }
            }

            if (trialMode) {
                isValid = isValid && trialPickableCardsSatisfied('#schedule_type_cards .selection-card');
                const venueSection = document.querySelector('#match_conditions .match-conditions__venue');
                if (venueSection && venueSection.offsetParent !== null) {
                    const venueCards = getVisibleSelectionCards('#match_conditions .match-conditions__venue .selection-card[data-value]');
                    isValid = isValid && venueCards.some(function (card) {
                        return card.classList.contains('selected');
                    });
                }
            }
        }
    } else if (currentStep === 4) {
        // Step 4: 確認・登録（常に有効）
        isValid = true;
    }

    // 次へボタンを取得して状態を更新
    const navElements = {
        1: document.getElementById('nav-next'),
        2: document.getElementById('nav-next-2'),
        3: document.getElementById('nav-next-3')
    };

    const nextButton = navElements[currentStep];
    if (nextButton) {
        nextButton.disabled = !isValid;
        if (!isValid) {
            nextButton.classList.add('disabled');
            nextButton.style.opacity = '0.5';
            nextButton.style.cursor = 'not-allowed';
        } else {
            nextButton.classList.remove('disabled');
            nextButton.style.opacity = '1';
            nextButton.style.cursor = 'pointer';
        }
    }
}

// テスト用にグローバルに公開（モジュール読み込み時に確実に実行される）
// テスト環境で関数を利用できるようにする
var scheduleEditGlobal = (typeof global !== 'undefined') ? global : (typeof window !== 'undefined' ? window : null);
if (scheduleEditGlobal) {
    scheduleEditGlobal.isJapaneseHoliday = isJapaneseHoliday;
    scheduleEditGlobal.calculateSpringEquinox = calculateSpringEquinox;
    scheduleEditGlobal.calculateAutumnEquinox = calculateAutumnEquinox;
    scheduleEditGlobal.getNthWeekday = getNthWeekday;
    scheduleEditGlobal.getIntentLabel = getIntentLabel;
    scheduleEditGlobal.getVenueLabel = getVenueLabel;
    scheduleEditGlobal.getGenderLabel = getGenderLabel;
    scheduleEditGlobal.getCurrentStep = getCurrentStep;
    scheduleEditGlobal.syncScheduleTypesFromIntent = syncScheduleTypesFromIntent;
    scheduleEditGlobal.goToStep = goToStep;
    scheduleEditGlobal.goToPreviousStep = goToPreviousStep;
    scheduleEditGlobal.goToNextStep = goToNextStep;
    scheduleEditGlobal.validateIntentSelection = validateIntentSelection;
    scheduleEditGlobal.validateDateAndTimeSelection = validateDateAndTimeSelection;
    scheduleEditGlobal.validateScheduleTypeSelection = validateScheduleTypeSelection;
    scheduleEditGlobal.showDateError = showDateError;
    scheduleEditGlobal.hideDateError = hideDateError;
    scheduleEditGlobal.showTimeError = showTimeError;
    scheduleEditGlobal.hideTimeError = hideTimeError;
    scheduleEditGlobal.initializeCalendar = initializeCalendar;
    scheduleEditGlobal.renderCalendar = renderCalendar;
    scheduleEditGlobal.selectDate = selectDate;
    scheduleEditGlobal.previousMonth = previousMonth;
    scheduleEditGlobal.nextMonth = nextMonth;
    scheduleEditGlobal.updateConfirmation = updateConfirmation;
    scheduleEditGlobal.loadScheduleForEdit = loadScheduleForEdit;
    // グローバル変数も公開
    scheduleEditGlobal.selectedDates = selectedDates;
    scheduleEditGlobal.scheduleEditCalendarCurrentDate = scheduleEditCalendarCurrentDate;
}
