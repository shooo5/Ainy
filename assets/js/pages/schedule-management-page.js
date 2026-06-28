/**
 * スケジュール管理ページ（page-schedule-management.php）
 * scheduleEditUrl / canEdit は wp_localize_script で注入
 */
window.SCHEDULE_PAGE_CAN_EDIT = (typeof aiduniteScheduleManagementPage !== 'undefined' && aiduniteScheduleManagementPage.canEdit) || false;

// スケジュール編集ページのURL
const scheduleEditUrl = (typeof aiduniteScheduleManagementPage !== 'undefined' && aiduniteScheduleManagementPage.scheduleEditUrl) || '';

// スケジュール編集ページを開く（ページ読み込み時に即座に定義）
function openScheduleEdit(dateString = null) {
    if (!window.SCHEDULE_PAGE_CAN_EDIT) {
        return;
    }
    try {
        let formattedDate = new Date().toISOString().split('T')[0];
        if (dateString) {
            const date = new Date(dateString);
            formattedDate = date.toISOString().split('T')[0];
        }
        if (typeof AidUniteScheduleQuickModal !== 'undefined' && typeof AidUniteScheduleQuickModal.openRegister === 'function') {
            AidUniteScheduleQuickModal.openRegister(formattedDate);
            return;
        }
        if (typeof showToastNotification === 'function') {
            showToastNotification('予定登録を開けませんでした。ページを再読み込みしてください。', 'error');
        }
    } catch (error) {
        console.error('openScheduleEdit エラー:', error);
        if (typeof showToastNotification === 'function') {
            showToastNotification('予定登録を開けませんでした: ' + error.message, 'error');
        }
    }
}

// グローバルスコープでも利用可能にする
window.openScheduleEdit = openScheduleEdit;

// スケジュール管理JavaScript
window.currentDate = window.currentDate || new Date();

// ローカル（JST）で YYYY-MM-DD を作る
// formatDateLocal関数は js/common/date-utils.js の AidUniteDateUtils.formatDateLocal() を使用
// 後方互換性のため、グローバル関数として利用可能
let selectedDate = null;
let currentView = 'calendar';

// bfcache（戻る/進む）で復元された場合は必ず再取得して最新を表示（キャッシュなし仕様のため再取得のみ）
window.addEventListener('pageshow', function(event) {
    if (event.persisted && typeof loadSchedules === 'function') {
        loadSchedules();
    }
});

// ページ読み込み時の初期化
document.addEventListener('DOMContentLoaded', function() {
    try {
        // URLパラメータにrefreshがある場合、キャッシュをクリアして強制的に再取得
        const urlParams = new URLSearchParams(window.location.search);
        const hasRefresh = urlParams.has('refresh');

        if (hasRefresh) {
            console.log('🔄 編集完了後のリフレッシュ: 再取得します');
        }

        loadSchedules(); // カレンダーのスケジュールを読み込み（キャッシュなし・常にAPIで取得）

        document.getElementById('schedule-add-btn')?.addEventListener('click', (e) => {
            e.preventDefault();
            openScheduleEdit(new Date().toISOString());
        });

        // refreshパラメータがある場合、読み込み後にURLから削除（履歴に残さない）
        if (hasRefresh) {
            setTimeout(() => {
                const newUrl = window.location.pathname;
                window.history.replaceState({}, document.title, newUrl);
            }, 100);
        }
        // グローバル公開（共通ユーティリティをそのまま利用）
        window.showScheduleDetail = function(scheduleId){
            const schedule = typeof findScheduleByIdInGlobal === 'function'
                ? findScheduleByIdInGlobal(scheduleId)
                : ((window.schedules && Object.values(window.schedules).flat().find(s => String(s.id) === String(scheduleId))) || null);
            if (typeof AidUniteScheduleQuickModal !== 'undefined' && schedule) {
                AidUniteScheduleQuickModal.openCardActions(schedule);
                return;
            }
            if (typeof AidUniteScheduleModal !== 'undefined') {
                AidUniteScheduleModal.showDetail(scheduleId, { mode: 'popup' });
            } else {
                console.error('スケジュール詳細を表示できません');
            }
        };
        console.log('✅ スケジュール管理ページ初期化完了');
    } catch (error) {
        console.error('初期化エラー:', error);
    }
});

// カレンダー表示は共通テンプレートから読み込み済み

// スケジュール詳細表示（共通テンプレから呼ばれる）
// showScheduleDetail関数は js/common/schedule-modal.js の AidUniteScheduleModal.showDetail() を使用
// 後方互換性のため、グローバル関数として利用可能

// createScheduleDetailModal関数は js/common/schedule-modal.js の AidUniteScheduleModal.createModal() を使用
// 後方互換性のため、グローバル関数として利用可能

// 全スケジュール表示は廃止（共通ポップアップに統一）
function showAllSchedules(dateString) {
    const scheduleList = schedules[dateString];
    if (!scheduleList || scheduleList.length === 0) return;
    // 一覧モーダルは使わず、最初のスケジュールを直接開く
    const first = scheduleList[0];
    if (first && typeof AidUniteScheduleModal !== 'undefined') {
        AidUniteScheduleModal.showDetail(first.id, { mode: 'popup' });
    }
}

// 一覧モーダル関連の生成コードは廃止（共通ポップアップに統一）

// モーダルを閉じる
// closeScheduleDetail関数は js/common/schedule-modal.js の AidUniteScheduleModal.closeModal() を使用
// 後方互換性のため、グローバル関数として利用可能

// editSchedule / deleteSchedule は本ファイル後半で定義（モーダル経由の ID 引数に対応した版）

// スケジュール確定（仮押さえから確定に変更）: 中央トーストモーダルを表示
function confirmSchedule(scheduleId) {
    showTentativeConfirmToast(scheduleId);
}

function tctEscapeHtml(str) {
    if (typeof AidUniteScheduleQuickModal !== 'undefined' && AidUniteScheduleQuickModal.escapeHtml) {
        return AidUniteScheduleQuickModal.escapeHtml(str);
    }
    return String(str ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function tctIconHtml(basename, size) {
    if (typeof AidUniteScheduleQuickModal !== 'undefined' && AidUniteScheduleQuickModal.confirmDetailIconHtml) {
        return AidUniteScheduleQuickModal.confirmDetailIconHtml(basename, size || 18);
    }
    if (typeof AidUniteThemeIcons !== 'undefined' && AidUniteThemeIcons.html) {
        return AidUniteThemeIcons.html(basename, size || 18);
    }
    return '';
}

function tctDetailRowHtml(iconBasename, label, value) {
    const v = String(value ?? '').trim() || 'ー';
    const icon = tctIconHtml(iconBasename, 18);
    const labelEsc = tctEscapeHtml(label);
    const valueEsc = tctEscapeHtml(v);
    return ''
        + '<div class="sqm-view-detail-row">'
        + '<div class="sqm-view-detail-row__meta">'
        + '<span class="sqm-view-detail-row__icon" aria-hidden="true">' + icon + '</span>'
        + '<span class="sqm-view-detail-row__label">' + labelEsc + '</span>'
        + '</div>'
        + '<span class="sqm-view-detail-row__sep" aria-hidden="true"></span>'
        + '<span class="sqm-view-detail-row__value">' + valueEsc + '</span>'
        + '</div>';
}

function tctFormatDateLabel(dateStr) {
    if (typeof AidUniteScheduleQuickModal !== 'undefined' && AidUniteScheduleQuickModal.formatDateLabel) {
        return AidUniteScheduleQuickModal.formatDateLabel(dateStr) || 'ー';
    }
    return String(dateStr || '').trim() || 'ー';
}

function tctBaseScheduleType(schedule) {
    return String(schedule.type || schedule.schedule_type || '')
        .replace(/（仮）|\(仮\)/g, '')
        .trim() || 'ー';
}

function tctThemeClass(schedule) {
    const g = String(schedule.gender_condition || schedule.gender || schedule.team_gender || '').toLowerCase();
    if (g === 'male') return ' tct--theme-male';
    if (g === 'female') return ' tct--theme-female';
    return '';
}

function tctBuildTimeFieldHtml(prefix, defaultValue) {
    if (typeof AidUniteScheduleQuickModal !== 'undefined' && AidUniteScheduleQuickModal.buildTimeFieldHtml) {
        return AidUniteScheduleQuickModal.buildTimeFieldHtml(prefix, defaultValue);
    }
    return '';
}

function tctReadTimeField(prefix) {
    if (typeof AidUniteScheduleQuickModal !== 'undefined' && AidUniteScheduleQuickModal.readTimeField) {
        return AidUniteScheduleQuickModal.readTimeField(prefix);
    }
    const hour = document.getElementById(prefix + '-hour')?.value || '00';
    const minute = document.getElementById(prefix + '-minute')?.value || '00';
    return hour + ':' + minute;
}

function tctEditableRowHtml(iconBasename, label, fieldHtml) {
    const icon = tctIconHtml(iconBasename, 18);
    const labelEsc = tctEscapeHtml(label);
    return ''
        + '<div class="sqm-view-detail-row tct-row--edit">'
        + '<div class="sqm-view-detail-row__meta">'
        + '<span class="sqm-view-detail-row__icon" aria-hidden="true">' + icon + '</span>'
        + '<span class="sqm-view-detail-row__label">' + labelEsc + '</span>'
        + '</div>'
        + '<span class="sqm-view-detail-row__sep" aria-hidden="true"></span>'
        + '<div class="sqm-view-detail-row__value tct-field-value">' + fieldHtml + '</div>'
        + '</div>';
}

function tctBuildTimeRowHtml(schedule) {
    const startRaw = String(schedule.start_time || '').trim();
    const endRaw = String(schedule.end_time || '').trim();
    const defaultAllDay = !startRaw && !endRaw;
    const startFields = tctBuildTimeFieldHtml('toast-start', startRaw || '13:00');
    const endFields = tctBuildTimeFieldHtml('toast-end', endRaw || '14:00');
    const timeInner = ''
        + '<label class="sqm-checkbox tct-all-day">'
        + '<input type="checkbox" id="toast-all-day" value="1"' + (defaultAllDay ? ' checked' : '') + '>'
        + '<span>終日</span>'
        + '</label>'
        + '<div class="sqm-row-time sqm-row-time--inline tct-time-row' + (defaultAllDay ? ' tct-is-hidden' : '') + '" id="toast-time-wrap"' + (defaultAllDay ? ' hidden' : '') + '>'
        + startFields
        + '<span class="sqm-time-range-sep" aria-hidden="true">～</span>'
        + endFields
        + '</div>';
    return tctEditableRowHtml('schedule', '時間', timeInner);
}

function tctBuildVenueRowHtml(schedule) {
    const val = tctEscapeHtml(String(schedule.venue_name || '').trim());
    const field = '<input type="text" id="toast-venue-name" class="sqm-input tct-field-input" placeholder="例: ○○体育館" value="' + val + '">';
    return tctEditableRowHtml('stadium', '会場', field);
}

function tctBuildMemoRowHtml(schedule) {
    const val = tctEscapeHtml(String(schedule.quick_memo || schedule.memo || schedule.note || '').trim());
    const field = '<textarea id="toast-memo" class="sqm-textarea tct-field-textarea" rows="2" placeholder="補足があれば入力">' + val + '</textarea>';
    return tctEditableRowHtml('stylus', 'メモ', field);
}

function tctValidateTimes(allDay, startTime, endTime) {
    if (allDay) return '';
    const start = String(startTime || '').trim();
    const end = String(endTime || '').trim();
    if (!start && !end) return '';
    if (!start || !end) return '開始・終了時間を入力するか、終日を選択してください';
    const toMin = (t) => {
        const m = String(t).match(/^(\d{1,2}):(\d{2})$/);
        if (!m) return -1;
        return (parseInt(m[1], 10) * 60) + parseInt(m[2], 10);
    };
    if (toMin(start) < 0 || toMin(end) < 0 || toMin(start) >= toMin(end)) {
        return '終了時間は開始時間より後にしてください';
    }
    return '';
}

function syncTctAllDayUi() {
    const allDay = !!document.getElementById('toast-all-day')?.checked;
    const wrap = document.getElementById('toast-time-wrap');
    if (!wrap) return;
    wrap.hidden = allDay;
    wrap.classList.toggle('tct-is-hidden', allDay);
    wrap.querySelectorAll('select').forEach((el) => {
        el.disabled = allDay;
    });
}

// 仮→確定用トーストモーダル（中央）を表示
function showTentativeConfirmToast(scheduleId) {
    try {
        const schedule = findScheduleByIdInGlobal(scheduleId);
        if (!schedule) {
            if (typeof showToastNotification === 'function') {
                showToastNotification('スケジュール情報が取得できませんでした。', 'error');
            }
            return;
        }

        const existing = document.getElementById('tentative-confirm-toast');
        if (existing) existing.remove();

        const kindIcon = (typeof AidUniteScheduleQuickModal !== 'undefined' && AidUniteScheduleQuickModal.resolveKindBadgeIcon)
            ? AidUniteScheduleQuickModal.resolveKindBadgeIcon(schedule)
            : 'schedule';
        const readRows = [
            tctDetailRowHtml('today', '日付', tctFormatDateLabel(schedule.date)),
            tctDetailRowHtml(kindIcon, '種別', tctBaseScheduleType(schedule)),
        ].join('');
        const editRows = [
            tctBuildTimeRowHtml(schedule),
            tctBuildVenueRowHtml(schedule),
            tctBuildMemoRowHtml(schedule),
        ].join('');
        const confirmIcon = tctIconHtml('check_circle', 18);
        const themeClass = tctThemeClass(schedule);

        const overlay = document.createElement('div');
        overlay.id = 'tentative-confirm-toast';
        overlay.className = 'tentative-confirm-toast-overlay';
        overlay.innerHTML = ''
            + '<div class="tentative-confirm-toast' + themeClass + '" role="dialog" aria-modal="true" aria-labelledby="tct-title">'
            + '<header class="tct-header">'
            + '<h3 id="tct-title">仮スケジュールを確定</h3>'
            + '<button type="button" class="tct-close" aria-label="閉じる">&times;</button>'
            + '</header>'
            + '<div class="tct-body">'
            + '<div class="sqm-view-detail-list tct-read-list">' + readRows + '</div>'
            + '<div class="tct-divider" aria-hidden="true"></div>'
            + '<div class="sqm-view-detail-list tct-edit-list">' + editRows + '</div>'
            + '</div>'
            + '<footer class="sqm-footer sqm-footer--stack sqm-footer--view tct-footer">'
            + '<button type="button" class="btn btn-primary sqm-full sqm-action-btn" id="toast-confirm-btn">'
            + '<span class="sqm-action-btn__inner">'
            + '<span class="sqm-action-btn__leading">' + confirmIcon + '<span>確定する</span></span>'
            + '</span>'
            + '</button>'
            + '<button type="button" class="btn btn-secondary sqm-full" id="toast-cancel-btn">キャンセル</button>'
            + '</footer>'
            + '</div>';

        document.body.appendChild(overlay);

        const closeToast = () => {
            overlay.classList.remove('show');
            setTimeout(() => overlay.remove(), 200);
        };

        overlay.querySelector('.tct-close').addEventListener('click', closeToast);
        document.getElementById('toast-cancel-btn').addEventListener('click', closeToast);
        document.getElementById('toast-all-day')?.addEventListener('change', syncTctAllDayUi);
        syncTctAllDayUi();

        document.getElementById('toast-confirm-btn').addEventListener('click', function () {
            const allDay = !!document.getElementById('toast-all-day')?.checked;
            const startVal = allDay ? '' : tctReadTimeField('toast-start');
            const endVal = allDay ? '' : tctReadTimeField('toast-end');
            const timeError = tctValidateTimes(allDay, startVal, endVal);
            if (timeError) {
                if (typeof showToastNotification === 'function') {
                    showToastNotification(timeError, 'warning');
                }
                return;
            }
            confirmTentativeSchedule(scheduleId, {
                date: schedule.date,
                start_time: startVal,
                end_time: endVal,
                all_day: allDay ? '1' : '0',
                type: tctBaseScheduleType(schedule),
                gender: String(schedule.gender_condition || schedule.gender || schedule.team_gender || ''),
                venue_name: String(document.getElementById('toast-venue-name')?.value || '').trim(),
                memo: String(document.getElementById('toast-memo')?.value || '').trim(),
            }, closeToast);
        });

        setTimeout(() => overlay.classList.add('show'), 10);
    } catch (e) {
        console.error('showTentativeConfirmToast error:', e);
        if (typeof showToastNotification === 'function') {
            showToastNotification('確定用のポップアップ表示中にエラーが発生しました。', 'error');
        }
    }
}

// グローバルスケジュールオブジェクトからIDで検索
function findScheduleByIdInGlobal(scheduleId) {
    if (!window.schedules) return null;
    for (const dateKey in window.schedules) {
        const dayList = window.schedules[dateKey];
        if (!Array.isArray(dayList)) continue;
        const found = dayList.find(s => String(s.id) === String(scheduleId));
        if (found) {
            if (!found.date) found.date = dateKey;
            return found;
        }
    }
    return null;
}

// 仮スケジュールをREST API経由で確定
function confirmTentativeSchedule(scheduleId, payload, onDone) {
    const nonce = (typeof wpApiSettings !== 'undefined') ? wpApiSettings.nonce : '';
    const body = Object.assign({}, payload, {
        post_id: scheduleId,
        intent: 'confirmed',
        certainty: 'firm'
    });

    const updateUrl = (typeof AIDUNITE_SCHEDULE_QUICK !== 'undefined' && AIDUNITE_SCHEDULE_QUICK.updateUrl)
        || (typeof aiduniteScheduleRest !== 'undefined' && aiduniteScheduleRest.root ? aiduniteScheduleRest.root + 'aidunite/v1/update-schedule-v2' : '');
    const updateNonce = (typeof AIDUNITE_SCHEDULE_QUICK !== 'undefined' && AIDUNITE_SCHEDULE_QUICK.nonce) || nonce;

    fetch(updateUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': updateNonce
        },
        body: JSON.stringify(body)
    })
    .then(res => res.json())
    .then(data => {
        if (data && data.success) {
            if (typeof onDone === 'function') onDone();
            if (typeof showToastNotification === 'function') {
                showToastNotification('スケジュールを確定しました', 'success');
            }
            // 再読み込み
            if (typeof loadSchedules === 'function') {
                loadSchedules();
            } else {
                window.location.reload();
            }
        } else {
            console.error('confirmTentativeSchedule error:', data);
            if (typeof showToastNotification === 'function') {
                showToastNotification('スケジュールの確定に失敗しました。', 'error');
            }
        }
    })
    .catch(err => {
        console.error('confirmTentativeSchedule fetch error:', err);
        if (typeof showToastNotification === 'function') {
            showToastNotification('スケジュールの確定中にエラーが発生しました。', 'error');
        }
    });
}

// 前月
function previousMonth() {
    currentDate.setMonth(currentDate.getMonth() - 1);
    loadSchedules();
}

// 次月
function nextMonth() {
    currentDate.setMonth(currentDate.getMonth() + 1);
    loadSchedules();
}

// スケジュール読み込み（最適化版）
// モバイルでも確実に表示するため「先に42マスを描画してからAPI取得」＋タイムアウトで読み込み中で止まらない
function loadSchedules() {
    console.log('📅 スケジュール読み込み開始');

    const year = currentDate.getFullYear();
    const monthIdx = currentDate.getMonth();
    const firstDayOfMonth = new Date(year, monthIdx, 1);
    const calendarStart = new Date(firstDayOfMonth);
    calendarStart.setDate(firstDayOfMonth.getDate() - firstDayOfMonth.getDay());
    const calendarEnd = new Date(calendarStart);
    calendarEnd.setDate(calendarStart.getDate() + 41);
    const start = (typeof AidUniteDateUtils !== 'undefined' && AidUniteDateUtils.formatDateLocal)
        ? AidUniteDateUtils.formatDateLocal(calendarStart)
        : (calendarStart.getFullYear() + '-' + String(calendarStart.getMonth() + 1).padStart(2, '0') + '-' + String(calendarStart.getDate()).padStart(2, '0'));
    const end = (typeof AidUniteDateUtils !== 'undefined' && AidUniteDateUtils.formatDateLocal)
        ? AidUniteDateUtils.formatDateLocal(calendarEnd)
        : (calendarEnd.getFullYear() + '-' + String(calendarEnd.getMonth() + 1).padStart(2, '0') + '-' + String(calendarEnd.getDate()).padStart(2, '0'));

    console.log('📅 取得期間（カレンダー表示42日分）:', { start, end });

    // 先に空グリッドを1回描画（モバイルで即日付表示）、fetch 後にデータ付きで再描画
    window.schedules = window.schedules || {};
    if (typeof renderCalendar === 'function') renderCalendar();
    showCalendarLoadingOverlay();

    const FETCH_TIMEOUT_MS = 12000;
    const timeoutPromise = new Promise(function(_, reject) {
        setTimeout(function() { reject(new Error('スケジュール取得がタイムアウトしました')); }, FETCH_TIMEOUT_MS);
    });

    function finishLoad() {
        hideCalendarLoadingOverlay();
        if (typeof renderCalendar === 'function') {
            renderCalendar();
            if (typeof applyScheduleFilter === 'function') applyScheduleFilter();
        }
        if (typeof AidUniteScheduleMonthView !== 'undefined' && AidUniteScheduleMonthView.afterDataLoaded) {
            AidUniteScheduleMonthView.afterDataLoaded();
        }
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('refresh')) {
            window.history.replaceState({}, document.title, window.location.pathname);
        }
        maybeOpenScheduleManagementDeepLink(urlParams);
    }

    function maybeOpenScheduleManagementDeepLink(urlParams) {
        if (window.__aiduniteScheduleMgmtDeepLinkHandled) {
            return;
        }
        const params = urlParams || new URLSearchParams(window.location.search);
        const editId = params.get('edit_schedule') || params.get('post_id') || params.get('id') || params.get('schedule_id');
        const openRegister = params.get('open_register') || params.get('date');
        if (!editId && !openRegister) {
            return;
        }
        window.__aiduniteScheduleMgmtDeepLinkHandled = true;
        window.history.replaceState({}, document.title, window.location.pathname);
        if (editId && typeof editSchedule === 'function') {
            editSchedule(editId);
            return;
        }
        if (openRegister && typeof openScheduleEdit === 'function') {
            openScheduleEdit(openRegister);
        }
    }

    Promise.race([
        fetchMonthSchedules(start, end),
        timeoutPromise
    ])
        .then(function() {
            console.log('📅 データ取得完了、カレンダー描画');
            finishLoad();
        })
        .catch(function(error) {
            console.warn('⚠️ 取得失敗またはタイムアウト、グリッドは表示のまま:', error);
            window.schedules = window.schedules || {};
            finishLoad();
            // 月単位失敗時は日別フォールバックをバックグラウンドで試行（結果は次回操作で反映）
            fetchMonthByDaily(start, end).then(function() {
                if (typeof renderCalendar === 'function') {
                    renderCalendar();
                    if (typeof applyScheduleFilter === 'function') applyScheduleFilter();
                }
            }).catch(function() {});
        });
}

// フィルタ状態
let activeScheduleFilters = new Set(['all']);

// カードにフィルタを適用
function applyScheduleFilter() {
    if (!window.schedules) return;
    const filters = activeScheduleFilters.has('all') ? new Set(['all']) : new Set(activeScheduleFilters);

    Object.keys(window.schedules).forEach(dateKey => {
        const list = window.schedules[dateKey];
        if (!Array.isArray(list)) return;
        const dayEl = document.querySelector(`.se-day[data-date="${dateKey}"]`);
        if (!dayEl) return;
        const cards = dayEl.querySelectorAll('.schedule-card');
        cards.forEach((card, idx) => {
            const sch = list[idx];
            if (!sch) return;
            let visible = true;
            if (typeof AidUniteScheduleUtils !== 'undefined' && typeof AidUniteScheduleUtils.matchesScheduleFilter === 'function') {
                visible = AidUniteScheduleUtils.matchesScheduleFilter(sch, filters);
            } else if (typeof matchesScheduleFilter === 'function') {
                visible = matchesScheduleFilter(sch, filters);
            } else if (!filters.has('all')) {
                const intent = String(sch.intent || '').toLowerCase();
                visible = intent !== '' && filters.has(intent);
            }
            const cfg = window.AIDUNITE_SCHEDULE_VIEW || {};
            const tf = cfg.activeTeamFilter;
            if (visible && tf && tf !== 'all' && sch.team_id) {
                visible = String(sch.team_id) === String(tf);
            }
            card.style.display = visible ? '' : 'none';
        });
    });
}

// フィルタチップの初期化
document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('schedule-filter-chips');
    if (!container) return;
    container.addEventListener('click', function(e) {
        const btn = e.target.closest('.filter-chip');
        if (!btn) return;
        const value = btn.getAttribute('data-filter');
        if (!value) return;

        if (value === 'all') {
            activeScheduleFilters = new Set(['all']);
            Array.from(container.querySelectorAll('.filter-chip')).forEach(el => {
                el.classList.toggle('active', el.getAttribute('data-filter') === 'all');
            });
        } else {
            if (activeScheduleFilters.has('all')) {
                activeScheduleFilters = new Set();
            }
            if (activeScheduleFilters.has(value)) {
                activeScheduleFilters.delete(value);
            } else {
                activeScheduleFilters.add(value);
            }
            if (activeScheduleFilters.size === 0) {
                activeScheduleFilters.add('all');
            }
            Array.from(container.querySelectorAll('.filter-chip')).forEach(el => {
                const key = el.getAttribute('data-filter');
                el.classList.toggle('active', activeScheduleFilters.has(key));
            });
        }
        applyScheduleFilter();
        if (typeof AidUniteScheduleMonthView !== 'undefined' && AidUniteScheduleMonthView.afterDataLoaded) {
            AidUniteScheduleMonthView.afterDataLoaded();
        }
    });
});

// カレンダーローディング表示（グリッドを置き換えず、オーバーレイで表示）
function showCalendarLoadingOverlay() {
    hideCalendarLoadingOverlay();
    const header = document.querySelector('.calendar-header');
    if (header) {
        const el = document.createElement('div');
        el.id = 'calendar-loading-overlay';
        el.className = 'calendar-loading-overlay';
        el.setAttribute('aria-live', 'polite');
        el.innerHTML = '<span class="calendar-loading-spinner" aria-hidden="true"></span><span>読み込み中...</span>';
        header.appendChild(el);
    }
}

function hideCalendarLoadingOverlay() {
    const el = document.getElementById('calendar-loading-overlay');
    if (el && el.parentNode) el.parentNode.removeChild(el);
}

// 後方互換（旧 showCalendarLoading はグリッドを潰さないオーバーレイに変更済みのため未使用）
function showCalendarLoading() {
    showCalendarLoadingOverlay();
}
function hideCalendarLoading() {
    hideCalendarLoadingOverlay();
}

// 月単位での一括取得（キャッシュなし・共通ロジック使用。常に最新を取得）
function fetchMonthSchedules(start, end) {
    console.log('📅 月単位一括取得開始:', { start, end });
    if (typeof AidUniteScheduleLoader === 'undefined') {
        return Promise.reject(new Error('AidUniteScheduleLoader が読み込まれていません'));
    }
    const fetchOpts = (typeof AidUniteScheduleMonthView !== 'undefined' && AidUniteScheduleMonthView.getFetchOptions)
        ? AidUniteScheduleMonthView.getFetchOptions()
        : (window.AIDUNITE_SCHEDULE_VIEW ? { scope: window.AIDUNITE_SCHEDULE_VIEW.fetchScope || 'operating' } : {});
    return AidUniteScheduleLoader.fetchMonthSchedulesForCalendar(start, end, fetchOpts)
        .then(() => {
            console.log('✅ 月単位取得完了:', Object.keys(window.schedules).length, '日分のデータ');
        })
        .catch(error => {
            console.error('❌ 月単位取得エラー:', error);
            throw error;
        });
}

// 最終フォールバック: 当月の全日について日別APIで取得し集約
function fetchMonthByDaily(start, end) {
    // 'YYYY-MM-DD' をローカル日付として安全に生成
    const parseYmd = (s) => {
        const [y, m, d] = s.split('-').map(n => parseInt(n, 10));
        return new Date(y, m - 1, d);
    };
    const dates = [];
    let d = parseYmd(start);
    const last = parseYmd(end);
    while (d <= last) {
        dates.push((typeof AidUniteDateUtils !== 'undefined' && AidUniteDateUtils.formatDateLocal)
            ? AidUniteDateUtils.formatDateLocal(d)
            : formatDateLocal(d));
        d.setDate(d.getDate() + 1);
    }
    window.schedules = {};
    return Promise.all(dates.map(ds => {
        return fetch('/wp-json/aidunite/v1/get-schedules-by-date', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': (typeof wpApiSettings !== 'undefined' ? wpApiSettings.nonce : '')
            },
            body: JSON.stringify({ date: ds })
        })
        .then(res => res.json())
        .then(payload => {
            const arr = Array.isArray(payload) ? payload : (payload && payload.data ? payload.data : []);
            if (Array.isArray(arr) && arr.length) {
                window.schedules[ds] = arr;
            }
        })
        .catch(() => {});
    }));
}

// スケジュールモーダルを閉じる（旧モーダル削除後は no-op。後方互換のため残す）
function closeScheduleModal() {
    const modal = document.getElementById('schedule-modal');
    if (modal) {
        modal.style.display = 'none';
    }
}

// スケジュールの編集（削除確認モーダル等からの編集用）
function editSchedule(scheduleId) {
    if (!window.SCHEDULE_PAGE_CAN_EDIT) {
        return;
    }
    const id = (scheduleId != null && scheduleId !== '') ? String(scheduleId) : '';
    if (id && typeof AidUniteScheduleQuickModal !== 'undefined') {
        const found = AidUniteScheduleQuickModal.findScheduleById(id);
        if (found) {
            AidUniteScheduleQuickModal.openEdit(found);
            return;
        }
        AidUniteScheduleQuickModal.openEditById(id);
        return;
    }

    if (selectedDate) {
        const formattedDate = selectedDate.toISOString().split('T')[0];

        // 指定日のスケジュールを取得して編集ページにリダイレクト
        fetch(`/wp-json/aidunite/v1/get-schedules-by-date`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': wpApiSettings.nonce
            },
            body: JSON.stringify({ date: formattedDate })
        })
        .then(response => response.json())
        .then(schedules => {
            if (Array.isArray(schedules) && schedules.length > 0) {
                const schedule = schedules[0];
                editSchedule(schedule.id);
            } else {
                // スケジュールがない場合は新規作成
                openScheduleEdit(selectedDate.toISOString());
            }
        })
        .catch(error => {
            console.error('スケジュール取得エラー:', error);
            // エラーの場合は新規作成
            openScheduleEdit(selectedDate.toISOString());
        });
    }
    closeScheduleModal();
}

// スケジュールの削除
function deleteSchedule(scheduleId) {
    if (!window.SCHEDULE_PAGE_CAN_EDIT) {
        return;
    }
    const id = (scheduleId != null && scheduleId !== '') ? String(scheduleId) : '';
    // ポップアップからID指定で呼ばれた場合は即時削除
    if (id) {
        const runDelete = () => deleteScheduleById(id);
        if (typeof AidUniteScheduleModal !== 'undefined' && typeof AidUniteScheduleModal.confirmAction === 'function') {
            AidUniteScheduleModal.confirmAction({
                message: 'このスケジュールを削除しますか？',
                onConfirm: runDelete
            });
        } else if (typeof showConfirmModal === 'function') {
            showConfirmModal({
                title: '削除確認',
                message: 'このスケジュールを削除しますか？',
                confirmLabel: '削除する',
                cancelLabel: 'キャンセル',
                confirmVariant: 'danger',
                onConfirm: runDelete
            });
        }
        return;
    }

    if (selectedDate) {
        showDeleteConfirmModal();
    }
}

function deleteScheduleById(scheduleId) {
    const nonce = (typeof wpApiSettings !== 'undefined') ? wpApiSettings.nonce : '';
    const deleteUrl = (typeof AIDUNITE_SCHEDULE_QUICK !== 'undefined' && AIDUNITE_SCHEDULE_QUICK.deleteUrl)
        || (typeof aiduniteScheduleRest !== 'undefined' && aiduniteScheduleRest.root ? aiduniteScheduleRest.root + 'aidunite/v1/delete-schedule-v2' : '');
    const deleteNonce = (typeof AIDUNITE_SCHEDULE_QUICK !== 'undefined' && AIDUNITE_SCHEDULE_QUICK.nonce) || nonce;

    fetch(deleteUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': deleteNonce
        },
        body: JSON.stringify({
            post_id: scheduleId,
            nonce: nonce
        })
    })
    .then(res => res.json())
    .then(data => {
        if (!data || !data.success) {
            throw new Error((data && data.message) ? data.message : '削除に失敗しました');
        }
        if (typeof AidUniteScheduleModal !== 'undefined') {
            AidUniteScheduleModal.closeAll();
        }
        if (typeof showToastNotification === 'function') {
            showToastNotification('スケジュールを削除しました', 'success');
        }
        if (typeof loadSchedules === 'function') {
            loadSchedules();
        } else {
            window.location.reload();
        }
    })
    .catch(error => {
        console.error('スケジュール削除エラー:', error);
        if (typeof showToastNotification === 'function') {
            showToastNotification('スケジュールの削除に失敗しました', 'error');
        }
    });
}

// 共通モーダル（AidUniteScheduleModal）からの呼び出し口
function editScheduleFromPopup(scheduleId) {
    editSchedule(scheduleId);
}

function deleteScheduleFromPopup(scheduleId) {
    deleteSchedule(scheduleId);
}

// 共通モーダルから仮→確定（トースト編集UIを開く）
function confirmScheduleFromPopup(scheduleId) {
    showTentativeConfirmToast(scheduleId);
}

// 削除確認モーダルを表示
function showDeleteConfirmModal() {
    const modal = document.getElementById('delete-confirm-modal');
    const scheduleTitle = document.getElementById('delete-schedule-title');

    if (!modal || !scheduleTitle) return;

    scheduleTitle.textContent = `${selectedDate.getMonth() + 1}月${selectedDate.getDate()}日のスケジュール`;
    modal.style.display = 'block';
}

// 削除確認モーダルを閉じる
function closeDeleteConfirmModal() {
    const modal = document.getElementById('delete-confirm-modal');
    if (modal) {
        modal.style.display = 'none';
    }
}

// スケジュールの削除を確認
function confirmDeleteSchedule() {
    if (!selectedDate) return;

    const formattedDate = selectedDate.toISOString().split('T')[0];

    // 指定日のスケジュールを取得
    fetch(`/wp-json/aidunite/v1/get-schedules-by-date`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': wpApiSettings.nonce
        },
        body: JSON.stringify({ date: formattedDate })
    })
    .then(response => response.json())
    .then(schedules => {
        if (Array.isArray(schedules) && schedules.length > 0) {
            // 各スケジュールを削除
            const deletePromises = schedules.map(schedule => {
                return fetch(`/wp-json/wp/v2/schedule/${schedule.id}`, {
                    method: 'DELETE',
                    headers: {
                        'X-WP-Nonce': wpApiSettings.nonce
                    }
                });
            });

            return Promise.all(deletePromises);
        }
        throw new Error('削除するスケジュールが見つかりません');
    })
    .then(() => {
        console.log('スケジュールを削除しました:', selectedDate);
        if (typeof showToastNotification === 'function') {
            showToastNotification('スケジュールを削除しました', 'success');
        }

        closeDeleteConfirmModal();
        closeScheduleModal();
        renderCalendar(); // カレンダーを再描画
    })
    .catch(error => {
        console.error('スケジュール削除エラー:', error);
        if (typeof showToastNotification === 'function') {
            showToastNotification('スケジュールの削除に失敗しました', 'error');
        }
    });
}

// モーダルからスケジュールを削除
function deleteScheduleFromModal() {
    showDeleteConfirmModal();
}

// 前月・次月の関数はテンプレートファイルに移行

// 今日のスケジュールを読み込み
function loadTodaySchedule() {
    const content = document.getElementById('today-schedule-content');
    if (!content) {
        console.warn('⚠️ 今日のスケジュール表示要素が見つかりません');
        return;
    }

    const today = new Date();
    const formattedDate = today.toISOString().split('T')[0];

    console.log('🔍 今日のスケジュール読み込み開始:', { today: today.toISOString(), formattedDate });

    // REST APIから今日のスケジュールを取得
    fetch(`/wp-json/aidunite/v1/get-schedules-by-date`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': wpApiSettings.nonce
        },
        body: JSON.stringify({ date: formattedDate })
    })
    .then(response => {
        console.log('📡 今日のスケジュールAPI レスポンス:', response);
        return response.json();
    })
    .then(schedules => {
        console.log('📋 今日のスケジュール:', schedules);

        if (Array.isArray(schedules) && schedules.length > 0) {
            let scheduleHTML = '';
            schedules.forEach(schedule => {
                scheduleHTML += `
                    <div class="today-schedule-item">
                        <div class="schedule-item-time">
                            <span class="time-icon">🕐</span>
                            <span class="time-text">${schedule.start_time} - ${schedule.end_time}</span>
                        </div>
                        <div class="schedule-item-content">
                            <div class="schedule-item-title">${schedule.type}</div>
                            <div class="schedule-item-venue">${schedule.place}</div>
                            <span class="schedule-item-type">${schedule.type}</span>
                        </div>
                    </div>
                `;
            });
            content.innerHTML = scheduleHTML;
            console.log('✅ 今日のスケジュール表示完了:', scheduleHTML);
        } else {
            content.innerHTML = `
                <div class="no-schedule">
                    <p>今日の予定はありません</p>
                </div>
            `;
            console.log('📝 今日の予定なし');
        }
    })
    .catch(error => {
        console.error('❌ 今日のスケジュール取得エラー:', error);
        content.innerHTML = `
            <div class="no-schedule">
                <p>今日の予定はありません</p>
            </div>
        `;
    });
}

// ビューの切り替え設定
function setupViewToggle() {
    const toggleButtons = document.querySelectorAll('.toggle-buttons .dashboard-btn');
    const calendarContainer = document.querySelector('.schedule-calendar-container');
    const listView = document.getElementById('list-view');
    const detailedListView = document.getElementById('detailed-list-view');

    toggleButtons.forEach(button => {
        button.addEventListener('click', function() {
            const view = this.dataset.view;

            // ボタンのアクティブ状態を更新
            toggleButtons.forEach(btn => btn.classList.remove('active'));
            this.classList.add('active');

            // ビューを切り替え
            if (view === 'calendar') {
                calendarContainer.style.display = 'block';

                listView.classList.remove('active');
                detailedListView.classList.remove('active');
                currentView = 'calendar';
            } else if (view === 'list') {
                listView.classList.add('active');
                calendarContainer.style.display = 'none';
                detailedListView.classList.remove('active');
                currentView = 'list';
                loadScheduleList();
            } else if (view === 'detailed-list') {
                detailedListView.classList.add('active');
                calendarContainer.style.display = 'none';
                listView.classList.remove('active');
                currentView = 'detailed-list';
                loadDetailedScheduleList();
            }
        });
    });
}

// スケジュールリストを読み込み（カード表示）
function loadScheduleList() {
    const listContainer = document.getElementById('schedule-list');
    if (!listContainer) {
        console.warn('⚠️ スケジュールリスト表示要素が見つかりません');
        return;
    }

    console.log('🔍 スケジュールリスト読み込み開始');

    // REST APIからスケジュールリストを取得
    fetch('/wp-json/aidunite/v1/get-user-schedules', {
        method: 'GET',
        headers: {
            'X-WP-Nonce': wpApiSettings.nonce
        }
    })
    .then(response => response.json())
    .then(data => {
        console.log('📋 取得したスケジュールリスト:', data);

        if (data.success && data.data && data.data.length > 0) {
            let listHTML = '<div class="schedule-cards-grid">';
            data.data.forEach(schedule => {
                const date = new Date(schedule.date);
                const formattedDate = date.toLocaleDateString('ja-JP', {
                    month: 'long',
                    day: 'numeric',
                    weekday: 'long'
                });

                listHTML += `
                    <div class="schedule-card">
                        <div class="card-header">
                            <div class="card-date">${formattedDate}</div>
                            <div class="card-actions">
                                <button type="button" class="btn btn-sm btn-primary" onclick="editSchedule('${schedule.id}')">編集</button>
                                <button class="btn btn-sm btn-danger" onclick="deleteScheduleFromList('${schedule.date}')">削除</button>
                            </div>
                        </div>
                        <div class="card-body">
                            <h4 class="card-title">${schedule.type}</h4>
                            <div class="card-details">
                                <div class="detail-item">
                                    <span class="detail-label">⏰ 時間:</span>
                                    <span class="detail-value">${schedule.start_time} - ${schedule.end_time}</span>
                                </div>
                                ${schedule.place ? `
                                <div class="detail-item">
                                    <span class="detail-label">📍 会場:</span>
                                    <span class="detail-value">${schedule.place}</span>
                                </div>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                `;
            });
            listHTML += '</div>';
            listContainer.innerHTML = listHTML;
            console.log('✅ スケジュールリスト表示完了');
        } else {
            listContainer.innerHTML = '<div class="no-schedule"><p>スケジュールが登録されていません</p></div>';
            console.log('📝 スケジュールなし');
        }
    })
    .catch(error => {
        console.error('❌ スケジュールリスト取得エラー:', error);
        listContainer.innerHTML = '<div class="no-schedule"><p>スケジュールの取得に失敗しました</p></div>';
    });
}

// 詳細スケジュールリストを読み込み（テーブル表示）
function loadDetailedScheduleList() {
    const listContainer = document.getElementById('schedule-detailed-list');
    if (!listContainer) {
        console.warn('⚠️ 詳細スケジュールリスト表示要素が見つかりません');
        return;
    }

    console.log('🔍 詳細スケジュールリスト読み込み開始');

    // REST APIから全期間のスケジュールを取得
    fetch('/wp-json/aidunite/v1/get-user-schedules', {
        method: 'GET',
        headers: {
            'X-WP-Nonce': wpApiSettings.nonce
        }
    })
    .then(response => response.json())
    .then(data => {
        console.log('📋 取得した詳細スケジュールリスト:', data);

        if (data.success && data.data && data.data.length > 0) {
            let listHTML = `
                <div class="schedule-table-container">
                    <table class="schedule-table">
                        <thead>
                            <tr>
                                <th>日付</th>
                                <th>時間</th>
                                <th>種別</th>
                                <th>会場</th>
                                <th>ステータス</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
            `;

            data.data.forEach(schedule => {
                const date = new Date(schedule.date);
                const formattedDate = date.toLocaleDateString('ja-JP', {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric',
                    weekday: 'long'
                });

                listHTML += `
                    <tr>
                        <td class="schedule-date">${formattedDate}</td>
                        <td class="schedule-time">${schedule.start_time} - ${schedule.end_time}</td>
                        <td class="schedule-type">${schedule.type}</td>
                        <td class="schedule-place">${schedule.place || '-'}</td>
                        <td class="schedule-status">
                            <span class="status-badge status-${schedule.match_status || 'planned'}">
                                ${getStatusText(schedule.match_status)}
                            </span>
                        </td>
                        <td class="schedule-actions">
                            <a href="${scheduleEditUrl}?date=${schedule.date}" class="btn btn-sm btn-primary">編集</a>
                            <button class="btn btn-sm btn-danger" onclick="deleteScheduleFromList('${schedule.date}')">削除</button>
                        </td>
                    </tr>
                `;
            });

            listHTML += `
                        </tbody>
                    </table>
                </div>
            `;

            listContainer.innerHTML = listHTML;
            console.log('✅ 詳細スケジュールリスト表示完了');
        } else {
            listContainer.innerHTML = '<div class="no-schedule"><p>スケジュールが登録されていません</p></div>';
            console.log('📝 スケジュールなし');
        }
    })
    .catch(error => {
        console.error('❌ 詳細スケジュールリスト取得エラー:', error);
        listContainer.innerHTML = '<div class="no-schedule"><p>スケジュールの取得に失敗しました</p></div>';
    });
}

// ステータステキストを取得
function getStatusText(status) {
    const statusMap = {
        'planned': '予定',
        'matched': '成立',
        'confirmed': '確定',
        'cancelled': 'キャンセル'
    };
    return statusMap[status] || '不明';
}

// リストからスケジュールを編集
function editScheduleFromList(dateString) {
    const date = new Date(dateString);
    const formattedDate = date.toISOString().split('T')[0];

    // 指定日のスケジュールを取得して編集ページにリダイレクト
    fetch(`/wp-json/aidunite/v1/get-schedules-by-date`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': wpApiSettings.nonce
        },
        body: JSON.stringify({ date: formattedDate })
    })
    .then(response => response.json())
    .then(schedules => {
        if (Array.isArray(schedules) && schedules.length > 0) {
            const schedule = schedules[0];
            editSchedule(schedule.id);
        } else {
            openScheduleEdit(dateString);
        }
    })
    .catch(error => {
        console.error('スケジュール取得エラー:', error);
        // エラーの場合は新規作成
        openScheduleEdit(dateString);
    });
}

// リストからスケジュールを削除
function deleteScheduleFromList(dateString) {
    selectedDate = new Date(dateString);
    showDeleteConfirmModal();
}

// 日付の比較（年月日のみ）
function isSameDate(date1, date2) {
    return date1.getFullYear() === date2.getFullYear() &&
           date1.getMonth() === date2.getMonth() &&
           date1.getDate() === date2.getDate();
}

// 今日の日付を取得（日本時間）
function getTodayDate() {
    const now = new Date();

    // 方法1: ローカルタイムゾーンで今日の日付を取得
    const localToday = now.toLocaleDateString('ja-JP', {
        timeZone: 'Asia/Tokyo',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit'
    }).split('/').join('-');

    // 方法2: UTC+9で計算（フォールバック）
    const jstDate = new Date(now.getTime() + (9 * 60 * 60 * 1000));
    const jstToday = jstDate.toISOString().split('T')[0];

    // 方法3: 現在のローカル日付（デバッグ用）
    const localDate = now.toLocaleDateString('en-CA'); // YYYY-MM-DD形式

    console.log('🔍 今日の日付判定:', {
        now: now.toISOString(),
        localToday: localToday,
        jstToday: jstToday,
        localDate: localDate,
        timezone: Intl.DateTimeFormat().resolvedOptions().timeZone
    });

    // ローカルタイムゾーンが利用可能な場合はそれを使用、そうでなければJST計算を使用
    return localToday || jstToday;
}

// 今日の日付かどうかを判定
function isToday(dateString) {
    const today = getTodayDate();
    const isTodayResult = dateString === today;

    console.log('🔍 今日判定:', {
        dateString: dateString,
        today: today,
        isToday: isTodayResult
    });

    return isTodayResult;
}

// 削除確認モーダルの外側クリックで閉じる
window.onclick = function(event) {
    const deleteModal = document.getElementById('delete-confirm-modal');
    if (deleteModal && event.target === deleteModal) {
        closeDeleteConfirmModal();
    }
}
