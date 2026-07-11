/** design-reference demo-scripts */
const AIDUNITE_DEMO_PUSH_ICON = (typeof aiduniteDesignReferenceDemo !== 'undefined' && aiduniteDesignReferenceDemo.pushIcon) || '';

let currentSpinnerId = null;

function demoShowFullscreenSpinner() {
    const btn = document.getElementById('fullscreen-spinner-btn');
    if (currentSpinnerId) {
        demoHideFullscreenSpinner();
        return;
    }
    if (typeof showLoadingSpinner !== 'undefined') {
        currentSpinnerId = showLoadingSpinner('処理中...', 'しばらくお待ちください');
        const overlay = document.getElementById(currentSpinnerId);
        if (overlay && !overlay.querySelector('.demo-spinner-close')) {
            const closeWrap = document.createElement('div');
            closeWrap.className = 'demo-spinner-actions';
            const closeBtn = document.createElement('button');
            closeBtn.type = 'button';
            closeBtn.className = 'btn btn-secondary demo-spinner-close';
            closeBtn.textContent = '戻る';
            closeBtn.addEventListener('click', demoHideFullscreenSpinner);
            closeWrap.appendChild(closeBtn);
            const container = overlay.querySelector('.loading-spinner-container');
            if (container) container.appendChild(closeWrap);
        }
    }
    if (btn) btn.textContent = 'フルスクリーンスピナーを非表示';
}

function demoHideFullscreenSpinner() {
    const btn = document.getElementById('fullscreen-spinner-btn');
    if (!currentSpinnerId) return;
    if (typeof hideLoadingSpinner !== 'undefined') {
        hideLoadingSpinner(currentSpinnerId);
    } else {
        const overlay = document.getElementById(currentSpinnerId);
        if (overlay) overlay.remove();
    }
    currentSpinnerId = null;
    if (btn) btn.textContent = 'フルスクリーンスピナー';
}

function demoButtonSpinner(button) {
    const buttonText = button.querySelector('.button-text');
    if (!buttonText) return;
    if (button.classList.contains('loading')) {
        button.classList.remove('loading');
        buttonText.textContent = buttonText.dataset.originalText || '送信';
        button.disabled = false;
        return;
    }
    button.classList.add('loading');
    buttonText.dataset.originalText = buttonText.textContent;
    buttonText.innerHTML = '<span class="button-loading-spinner"></span> 処理中...';
    button.disabled = true;
    setTimeout(() => demoButtonSpinner(button), 2000);
}

function demoTabSwitch(button, contentId) {
    const tabDemo = button.closest('.ref-tab-demo') || button.closest('.tab-demo');
    if (!tabDemo) return;
    const tabNav = button.parentElement;
    tabNav.querySelectorAll('.tab-btn').forEach((btn) => btn.classList.remove('active'));
    tabDemo.querySelectorAll('.ref-tab-panel, .tab-content').forEach((content) => content.classList.remove('active'));
    button.classList.add('active');
    const target = document.getElementById(contentId);
    if (target) target.classList.add('active');
}

function demoMatchPillSelect(button) {
    const group = button.closest('.match-select-pills');
    if (!group || button.disabled) return;
    group.querySelectorAll('.match-pill').forEach((pill) => pill.classList.remove('selected'));
    button.classList.add('selected');
}

function demoMarketPillTabSwitch(button) {
    const tabs = button.closest('.market-axis-pill-tabs');
    if (!tabs) return;
    tabs.querySelectorAll('.market-axis-pill-tab').forEach((tab) => {
        tab.classList.remove('active');
        tab.setAttribute('aria-selected', 'false');
    });
    button.classList.add('active');
    button.setAttribute('aria-selected', 'true');
}

function demoDropdownToggle(button) {
    const dropdown = button.closest('.dropdown-demo');
    if (!dropdown) return;
    dropdown.classList.toggle('active');
    document.addEventListener('click', function closeDropdown(e) {
        if (!dropdown.contains(e.target)) {
            dropdown.classList.remove('active');
            document.removeEventListener('click', closeDropdown);
        }
    });
}

function copyCode(elementId) {
    const codeElement = document.getElementById(elementId);
    if (!codeElement) return;
    const text = codeElement.textContent;
    navigator.clipboard.writeText(text).then(() => {
        const btn = codeElement.closest('.code-snippet')?.querySelector('.btn-copy');
        if (!btn) return;
        const originalText = btn.textContent;
        btn.textContent = 'コピーしました！';
        setTimeout(() => { btn.textContent = originalText; }, 2000);
    }).catch(() => {});
}

function demoShowToast(type) {
    const messages = {
        success: '登録しました',
        error: '登録に失敗しました。もう一度お試しください。',
        warning: '注意が必要な情報があります。',
        info: '参考になる情報をお知らせします。'
    };
    if (typeof showToastNotification !== 'undefined') {
        showToastNotification(messages[type] || messages.info, type);
    }
}

/** SC-4: スケジュール管理の仮確定成功ピル（page-schedule-management.php の showCenterToast 相当） */
function demoShowCenterToastSchedule() {
    const existing = document.getElementById('demo-center-toast');
    if (existing) existing.remove();
    const el = document.createElement('div');
    el.id = 'demo-center-toast';
    el.className = 'center-toast show';
    el.textContent = 'スケジュールを確定しました';
    el.style.cssText = 'position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);'
        + 'background:rgba(0,0,0,0.8);color:#fff;padding:0.75rem 1.5rem;border-radius:999px;'
        + 'z-index:11000;font-size:0.9rem;opacity:1;';
    document.body.appendChild(el);
    setTimeout(() => {
        el.style.opacity = '0';
        setTimeout(() => el.remove(), 200);
    }, 2000);
}

/** SC-5: loadSchedules のカレンダー読み込みオーバーレイ相当 */
function demoShowCalendarLoadingOverlay() {
    const existing = document.getElementById('demo-calendar-loading-overlay');
    if (existing) existing.remove();
    const el = document.createElement('div');
    el.id = 'demo-calendar-loading-overlay';
    el.className = 'calendar-loading-overlay';
    el.setAttribute('role', 'status');
    el.innerHTML = '<span class="calendar-loading-spinner" aria-hidden="true"></span><span>読み込み中...</span>';
    el.style.cssText = 'position:fixed;inset:0;display:flex;align-items:center;justify-content:center;'
        + 'gap:0.5rem;background:rgba(255,255,255,0.75);z-index:10500;font-size:0.9rem;';
    document.body.appendChild(el);
    setTimeout(() => el.remove(), 1800);
}

function demoSpinnerThenToast(outcome) {
    if (typeof showLoadingSpinner === 'undefined') {
        demoShowToast(outcome === 'error' ? 'error' : 'success');
        return;
    }
    const titles = { success: '保存中...', error: '処理中...' };
    const spinnerId = showLoadingSpinner(titles[outcome] || '処理中...', 'しばらくお待ちください');
    setTimeout(() => {
        if (spinnerId && typeof hideLoadingSpinner !== 'undefined') {
            hideLoadingSpinner(spinnerId);
        }
        demoShowToast(outcome === 'error' ? 'error' : 'success');
    }, 1500);
}

function demoButtonSpinnerThenToast(button, outcome) {
    if (!button || button.dataset.demoRunning === '1') return;
    button.dataset.demoRunning = '1';
    const originalHtml = button.innerHTML;
    const originalDisabled = button.disabled;
    button.disabled = true;
    button.innerHTML = '<span class="button-loading-spinner"></span> 処理中...';
    setTimeout(() => {
        button.disabled = originalDisabled;
        button.innerHTML = originalHtml;
        delete button.dataset.demoRunning;
        demoShowToast(outcome === 'error' ? 'error' : 'success');
    }, 1500);
}

function demoShowConfirmModal() {
    if (typeof showConfirmModal === 'undefined') return;
    showConfirmModal({
        title: '確認',
        message: 'この操作を実行してもよろしいですか？',
        confirmLabel: '実行する',
        cancelLabel: 'キャンセル',
        confirmVariant: 'primary',
        onConfirm: () => demoShowToast('success')
    });
}

function demoShowConfirmModalDanger() {
    if (typeof showConfirmModal === 'undefined') return;
    showConfirmModal({
        title: '削除確認',
        message: 'このスケジュールを削除してもよろしいですか？\nこの操作は取り消せません。',
        confirmLabel: '削除する',
        cancelLabel: 'キャンセル',
        confirmVariant: 'danger',
        onConfirm: () => demoShowToast('success')
    });
}

function demoShowFirstMatchBillingModal() {
    if (window.aidunitePaymentFirstMatchModal && typeof window.aidunitePaymentFirstMatchModal.show === 'function') {
        window.aidunitePaymentFirstMatchModal.show(null, { preview: true });
        return;
    }
    if (typeof demoShowToast === 'function') {
        demoShowToast('error');
    }
}

function demoShowOsAlert() {
    window.alert('保存しました\n\n※ OS 通知（alert）は AidUnite では禁止です。');
}

function demoShowOsConfirm() {
    const ok = window.confirm('削除しますか？\n\n※ OS 通知（confirm）は AidUnite では禁止です。');
    if (ok) demoShowToast('info');
}

function demoFeedbackEscapeHtml(text) {
    return String(text ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function demoRemoveLegacyFeedbackDemo() {
    document.querySelectorAll('.aidunite-demo-banner, .aidunite-demo-snackbar, .aidunite-demo-webpush').forEach((el) => el.remove());
}

function demoHideLegacyFeedbackEl(el) {
    if (!el?.parentElement) return;
    el.classList.add('is-hiding');
    setTimeout(() => el.remove(), 300);
}

function demoShowBannerNotification() {
    demoRemoveLegacyFeedbackDemo();
    const el = document.createElement('div');
    el.className = 'aidunite-demo-banner aidunite-demo-banner--success';
    el.setAttribute('role', 'status');
    el.innerHTML = '<span class="aidunite-demo-banner__message">' + demoFeedbackEscapeHtml('🎉 ログイン成功！ようこそ Ainy Unite へ！') + '</span><button type="button" class="aidunite-demo-banner__close" aria-label="閉じる">✕</button>';
    document.body.appendChild(el);
    el.querySelector('.aidunite-demo-banner__close')?.addEventListener('click', () => demoHideLegacyFeedbackEl(el));
    setTimeout(() => demoHideLegacyFeedbackEl(el), 5000);
}

function demoShowSnackbarNotification() {
    demoRemoveLegacyFeedbackDemo();
    const el = document.createElement('div');
    el.className = 'aidunite-demo-snackbar';
    el.setAttribute('role', 'status');
    el.innerHTML = '<span class="aidunite-demo-snackbar__message">' + demoFeedbackEscapeHtml('保存しました') + '</span><button type="button" class="aidunite-demo-snackbar__close" aria-label="閉じる">✕</button>';
    document.body.appendChild(el);
    el.querySelector('.aidunite-demo-snackbar__close')?.addEventListener('click', () => demoHideLegacyFeedbackEl(el));
    setTimeout(() => demoHideLegacyFeedbackEl(el), 4000);
}

function demoShowWebPushMock() {
    demoRemoveLegacyFeedbackDemo();
    const el = document.createElement('div');
    el.className = 'aidunite-demo-webpush';
    el.setAttribute('role', 'status');
    el.innerHTML = '<div class="aidunite-demo-webpush__icon" aria-hidden="true">🏀</div><div class="aidunite-demo-webpush__body"><div class="aidunite-demo-webpush__app">Ainy Unite</div><div class="aidunite-demo-webpush__title">' + demoFeedbackEscapeHtml('マッチ申請が届きました') + '</div><div class="aidunite-demo-webpush__text">' + demoFeedbackEscapeHtml('〇〇チームから申請があります（見本）') + '</div></div><button type="button" class="aidunite-demo-webpush__close" aria-label="閉じる">✕</button>';
    document.body.appendChild(el);
    el.querySelector('.aidunite-demo-webpush__close')?.addEventListener('click', () => demoHideLegacyFeedbackEl(el));
    setTimeout(() => demoHideLegacyFeedbackEl(el), 6000);
}

async function demoShowWebPushLive() {
    if (!('Notification' in window)) {
        demoShowWebPushMock();
        demoShowToast('warning');
        return;
    }
    let permission = Notification.permission;
    if (permission === 'default') permission = await Notification.requestPermission();
    if (permission === 'granted') {
        const options = { body: '〇〇チームからマッチ申請が届きました（デモ）', tag: 'aidunite-demo-push' };
        if (AIDUNITE_DEMO_PUSH_ICON) options.icon = AIDUNITE_DEMO_PUSH_ICON;
        const n = new Notification('Ainy Unite', options);
        n.onclick = () => { window.focus(); n.close(); };
        demoShowToast('info');
        return;
    }
    demoShowWebPushMock();
    demoShowToast('warning');
}

/* --- カレンダーデモ（design-reference 専用・簡易版） --- */
let demoCalendarDate = new Date();

const demoSchedulesByDate = {};

function demoFormatDateLocal(date) {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
}

function demoBuildScheduleCard(schedule) {
    const type = schedule.type || 'スケジュール';
    const intent = schedule.intent || '';
    let cls = 'schedule-card schedule-card--practice';
    if (type.includes('試合')) cls = 'schedule-card schedule-card--match';
    if (type.includes('イベント')) cls = 'schedule-card schedule-card--event';
    if (type.includes('休')) cls = 'schedule-card schedule-card--off';
    if (intent === 'tentative') cls += ' schedule-card--tentative';
    return `<div class="${cls}" data-schedule-id="${schedule.id || ''}"><span class="schedule-card__type">${type}</span></div>`;
}

function renderDemoCalendar() {
    const grid = document.getElementById('demo-calendar-grid');
    const title = document.getElementById('demo-calendar-title');
    if (!grid) return;

    const year = demoCalendarDate.getFullYear();
    const month = demoCalendarDate.getMonth();
    if (title) title.textContent = `${year}年${month + 1}月`;

    const firstDay = new Date(year, month, 1);
    const startDate = new Date(firstDay);
    startDate.setDate(startDate.getDate() - firstDay.getDay());

    const ym = `${year}-${String(month + 1).padStart(2, '0')}`;
    if (!demoSchedulesByDate[`${ym}-15`]) {
        demoSchedulesByDate[`${ym}-15`] = [{ id: 'demo-1', type: '通常練習', intent: 'confirmed' }];
        demoSchedulesByDate[`${ym}-20`] = [{ id: 'demo-2', type: '練習試合', intent: 'recruit' }];
        demoSchedulesByDate[`${ym}-25`] = [
            { id: 'demo-3', type: 'イベント', intent: 'confirmed' },
            { id: 'demo-4', type: '通常練習', intent: 'confirmed' }
        ];
    }

    let html = '';
    const cursor = new Date(startDate);
    for (let week = 0; week < 6; week++) {
        html += '<div class="calendar-week">';
        for (let day = 0; day < 7; day++) {
            const date = new Date(cursor);
            const ds = demoFormatDateLocal(date);
            const isCurrentMonth = date.getMonth() === month;
            const dow = date.getDay();
            let dayClass = 'se-day';
            if (!isCurrentMonth) dayClass += ' se-other-month';
            if (dow === 0) dayClass += ' se-sunday';
            if (dow === 6) dayClass += ' se-saturday';
            const schedules = demoSchedulesByDate[ds] || [];
            let cards = schedules.map(demoBuildScheduleCard).join('');
            html += `<div class="${dayClass}" data-date="${ds}"><div class="se-day-number">${date.getDate()}</div><div class="se-day-schedules">${cards}</div></div>`;
            cursor.setDate(cursor.getDate() + 1);
        }
        html += '</div>';
    }
    grid.innerHTML = html;
}

function demoCalendarPrev() {
    demoCalendarDate.setMonth(demoCalendarDate.getMonth() - 1);
    renderDemoCalendar();
}

function demoCalendarNext() {
    demoCalendarDate.setMonth(demoCalendarDate.getMonth() + 1);
    renderDemoCalendar();
}

document.addEventListener('DOMContentLoaded', renderDemoCalendar);
