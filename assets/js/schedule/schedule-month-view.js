/**
 * 月間スケジュール：リスト表示・表示切替・チームフィルタ（マルチチーム横断）
 */
(function() {
    'use strict';

    const WEEKDAY_LABELS = ['日', '月', '火', '水', '木', '金', '土'];

    function getViewConfig() {
        return window.AIDUNITE_SCHEDULE_VIEW || {};
    }

    function isMobileViewport() {
        if (typeof window.AidUniteIsScheduleMobileViewport === 'function') {
            return window.AidUniteIsScheduleMobileViewport();
        }
        const cfg = getViewConfig();
        const bp = cfg.listBreakpointPx || 768;
        return window.matchMedia('(max-width: ' + bp + 'px)').matches;
    }

    function shouldUseMobileDayPanel() {
        if (typeof window.AidUniteShouldUseMobileCalendarDayPanel === 'function') {
            return window.AidUniteShouldUseMobileCalendarDayPanel();
        }
        const mode = getViewConfig().currentViewMode || resolveDefaultViewMode();
        return isMobileViewport() && mode === 'calendar';
    }

    function getStoredViewMode() {
        const cfg = getViewConfig();
        try {
            return localStorage.getItem(cfg.viewModeStorageKey || 'ainy_schedule_view_mode') || '';
        } catch (e) {
            return '';
        }
    }

    function setStoredViewMode(mode) {
        const cfg = getViewConfig();
        try {
            localStorage.setItem(cfg.viewModeStorageKey || 'ainy_schedule_view_mode', mode);
        } catch (e) {
            /* ignore */
        }
    }

    function resolveDefaultViewMode() {
        const stored = getStoredViewMode();
        if (stored === 'calendar' || stored === 'list') {
            return stored;
        }
        return isMobileViewport() ? 'list' : 'calendar';
    }

    function getActiveTeamFilter() {
        const cfg = getViewConfig();
        return cfg.activeTeamFilter != null ? String(cfg.activeTeamFilter) : 'all';
    }

    function setActiveTeamFilter(value) {
        const cfg = getViewConfig();
        cfg.activeTeamFilter = value;
    }

    function getFetchOptions() {
        const cfg = getViewConfig();
        return { scope: cfg.fetchScope || 'operating' };
    }

    function formatDateLocal(date) {
        if (typeof AidUniteDateUtils !== 'undefined' && AidUniteDateUtils.formatDateLocal) {
            return AidUniteDateUtils.formatDateLocal(date);
        }
        if (typeof window.formatDateLocal === 'function') {
            return window.formatDateLocal(date);
        }
        const y = date.getFullYear();
        const m = String(date.getMonth() + 1).padStart(2, '0');
        const d = String(date.getDate()).padStart(2, '0');
        return y + '-' + m + '-' + d;
    }

    function parseYmd(ymd) {
        const parts = String(ymd).substring(0, 10).split('-').map(Number);
        return new Date(parts[0], parts[1] - 1, parts[2]);
    }

    function formatListDateHeading(ymd) {
        const d = parseYmd(ymd);
        const m = d.getMonth() + 1;
        const day = d.getDate();
        const w = WEEKDAY_LABELS[d.getDay()];
        return m + '/' + day + '（' + w + '）';
    }

    function formatListDateHeadingLong(ymd) {
        const d = parseYmd(ymd);
        const m = d.getMonth() + 1;
        const day = d.getDate();
        const w = WEEKDAY_LABELS[d.getDay()];
        return m + '月' + day + '日(' + w + ')';
    }

    function escapeHtml(text) {
        if (typeof window.AidUniteCalendarEscapeHtml === 'function') {
            return window.AidUniteCalendarEscapeHtml(text);
        }
        if (text == null) return '';
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function getListRowData(schedule) {
        if (typeof window.AidUniteBuildScheduleListRowData === 'function') {
            return window.AidUniteBuildScheduleListRowData(schedule);
        }
        const compact = getCompactLines(schedule);
        return {
            scheduleId: String(schedule.id || schedule.post_id || ''),
            modifier: presentationToModifier(compact.cardClass),
            teamClass: 'team-gender-unknown',
            teamName: String(schedule.team_name || ''),
            kindLabel: compact.line1 || '予定',
            timeLabel: compact.line2 || '',
            venueLabel: '',
            opponentLabel: '',
            memoLabel: '',
        };
    }

    function getCompactLines(schedule) {
        if (typeof window.AidUniteBuildScheduleCompactLines === 'function') {
            return window.AidUniteBuildScheduleCompactLines(schedule);
        }
        return { line1: '予定', line2: '', cardClass: 'schedule-card practice', lines: ['予定'] };
    }

    function presentationToModifier(cardClass) {
        if (!cardClass) return 'practice';
        if (cardClass.indexOf('match-confirmed') >= 0) return 'match-confirmed';
        if (cardClass.indexOf('match-recruit') >= 0) return 'match-recruit';
        if (cardClass.indexOf('tentative') >= 0) return 'tentative';
        if (cardClass.indexOf('meeting') >= 0) return 'meeting';
        if (cardClass.indexOf('event') >= 0) return 'event';
        if (cardClass.indexOf('off') >= 0) return 'off';
        return 'practice';
    }

    function canEditSchedule() {
        const cfg = getViewConfig();
        if (cfg.canEdit === true || cfg.canEdit === '1' || cfg.canEdit === 1) {
            return true;
        }
        if (cfg.canEdit === false || cfg.canEdit === '0' || cfg.canEdit === 0) {
            return false;
        }
        return typeof AidUniteScheduleQuickModal !== 'undefined'
            && typeof AidUniteScheduleQuickModal.canEdit === 'function'
            && AidUniteScheduleQuickModal.canEdit();
    }

    function shouldShowAttendanceSummary() {
        const cfg = getViewConfig();
        return cfg.showAttendanceSummary === true || cfg.showAttendanceSummary === '1' || cfg.showAttendanceSummary === 1;
    }

    function formatAttendanceSummaryLabel(schedule) {
        if (!shouldShowAttendanceSummary()) {
            return '—';
        }
        if (String(schedule.attendance_required || '') !== '1') {
            return '—';
        }
        const summary = schedule.attendance_summary;
        if (summary && summary.label) {
            return String(summary.label);
        }
        return '—';
    }

    function listActionIcon(name, size) {
        if (typeof AidUniteThemeIcons !== 'undefined' && AidUniteThemeIcons.html) {
            return AidUniteThemeIcons.html(name, size || 18);
        }
        return '';
    }

    function buildListActionsHtml(scheduleId, layout) {
        const id = escapeHtml(scheduleId);
        const viewBtn = '<button type="button" class="schedule-list-action" data-action="view" data-schedule-id="' + id + '" aria-label="詳細を見る">'
            + listActionIcon('id_card', 18) + '</button>';
        if (!canEditSchedule()) {
            return layout === 'inline'
                ? '<div class="schedule-list-actions schedule-list-actions--inline">' + viewBtn + '</div>'
                : viewBtn;
        }
        const editBtn = '<button type="button" class="schedule-list-action" data-action="edit" data-schedule-id="' + id + '" aria-label="編集する">'
            + listActionIcon('stylus', 18) + '</button>';
        const deleteBtn = '<button type="button" class="schedule-list-action schedule-list-action--danger" data-action="delete" data-schedule-id="' + id + '" aria-label="削除する">'
            + listActionIcon('delete_forever', 18) + '</button>';
        const inner = viewBtn + editBtn + deleteBtn;
        if (layout === 'inline') {
            return '<div class="schedule-list-actions schedule-list-actions--inline">' + inner + '</div>';
        }
        return '<div class="schedule-list-actions">' + inner + '</div>';
    }

    function buildKindBadgeHtml(kindLabel, modifier) {
        return '<span class="schedule-list-badge schedule-list-badge--' + escapeHtml(modifier) + '">'
            + escapeHtml(kindLabel) + '</span>';
    }

    function buildMobileCardHtml(row, options) {
        const opts = options || {};
        const sch = row.schedule;
        const data = getListRowData(sch);
        const extraMetaParts = [];
        if (data.opponentLabel) {
            extraMetaParts.push('<div class="schedule-month-card__meta">対戦: ' + escapeHtml(data.opponentLabel) + '</div>');
        } else if (data.memoLabel) {
            extraMetaParts.push('<div class="schedule-month-card__meta">' + escapeHtml(data.memoLabel) + '</div>');
        }
        const teamHtml = data.teamName
            ? '<div class="schedule-month-card__team">' + escapeHtml(data.teamName) + '</div>'
            : '<div class="schedule-month-card__team"></div>';
        const timeHtml = data.timeLabel
            ? '<span class="schedule-month-card__time">' + escapeHtml(data.timeLabel) + '</span>'
            : '<span class="schedule-month-card__time schedule-month-card__time--empty"></span>';
        const extraMetaHtml = extraMetaParts.length
            ? '<div class="schedule-month-card__extra">' + extraMetaParts.join('') + '</div>'
            : '';
        const attendanceHtml = shouldShowAttendanceSummary()
            ? '<div class="schedule-month-card__attendance">出欠: ' + escapeHtml(formatAttendanceSummaryLabel(sch)) + '</div>'
            : '';
        const actionsHtml = opts.showActions === false
            ? ''
            : '<div class="schedule-month-card__actions">' + buildListActionsHtml(data.scheduleId, 'inline') + '</div>';

        return ''
            + '<article class="schedule-month-card schedule-month-card--' + escapeHtml(data.modifier) + ' ' + escapeHtml(data.teamClass) + '"'
            + ' data-schedule-id="' + escapeHtml(data.scheduleId) + '">'
            + teamHtml
            + '<div class="schedule-month-card__status">' + buildKindBadgeHtml(data.kindLabel, data.modifier) + '</div>'
            + '<div class="schedule-month-card__time-cell">' + timeHtml + extraMetaHtml + attendanceHtml + '</div>'
            + actionsHtml
            + '</article>';
    }

    function buildTableRowHtml(row, showDateCell, rowspan) {
        const sch = row.schedule;
        const data = getListRowData(sch);
        let html = '<tr class="schedule-month-table__row schedule-month-table__row--' + escapeHtml(data.modifier) + '">';
        if (showDateCell) {
            const dateGenderClass = data.teamClass || 'team-gender-unknown';
            html += '<th class="schedule-month-table__date" scope="rowgroup" rowspan="' + rowspan + '">'
                + '<span class="schedule-month-table__date-text ' + escapeHtml(dateGenderClass) + '">'
                + escapeHtml(formatListDateHeading(row.dateKey)) + '</span>'
                + '</th>';
        }
        html += '<td class="schedule-month-table__team">' + escapeHtml(data.teamName || '—') + '</td>';
        html += '<td class="schedule-month-table__kind">' + buildKindBadgeHtml(data.kindLabel, data.modifier) + '</td>';
        html += '<td class="schedule-month-table__time">' + escapeHtml(data.timeLabel || '—') + '</td>';
        html += '<td class="schedule-month-table__venue">' + escapeHtml(data.venueLabel || 'ー') + '</td>';
        const opponentTitle = data.opponentTitle && data.opponentTitle !== data.opponentLabel
            ? ' title="' + escapeHtml(data.opponentTitle) + '"'
            : '';
        html += '<td class="schedule-month-table__opponent"' + opponentTitle + '>'
            + escapeHtml(data.opponentLabel || '—') + '</td>';
        html += '<td class="schedule-month-table__memo">' + escapeHtml(data.memoLabel || '—') + '</td>';
        if (shouldShowAttendanceSummary()) {
            html += '<td class="schedule-month-table__attendance">' + escapeHtml(formatAttendanceSummaryLabel(sch)) + '</td>';
        }
        html += '<td class="schedule-month-table__actions">' + buildListActionsHtml(data.scheduleId, 'table') + '</td>';
        html += '</tr>';
        return html;
    }

    function matchesIntentFilter(schedule, filters) {
        if (typeof AidUniteScheduleUtils !== 'undefined' && AidUniteScheduleUtils.matchesScheduleFilter) {
            return AidUniteScheduleUtils.matchesScheduleFilter(schedule, filters);
        }
        if (!filters || filters.has('all')) return true;
        const intent = String(schedule.intent || '').toLowerCase();
        return intent !== '' && filters.has(intent);
    }

    function matchesTeamFilter(schedule) {
        const tf = getActiveTeamFilter();
        if (tf === 'all' || tf === '') return true;
        return String(schedule.team_id || '') === String(tf);
    }

    function getActiveIntentFilters() {
        if (typeof window.activeScheduleFilters !== 'undefined' && window.activeScheduleFilters) {
            if (window.activeScheduleFilters.has('all')) {
                return new Set(['all']);
            }
            return new Set(window.activeScheduleFilters);
        }
        return new Set(['all']);
    }

    function getDissolutionMarkersForDate(dateKey) {
        if (!window.scheduleDissolutionMarkers || !window.scheduleDissolutionMarkers[dateKey]) {
            return [];
        }
        return window.scheduleDissolutionMarkers[dateKey];
    }

    function buildDissolutionListRowHtml(dateKey, marker) {
        const team = marker && marker.team_name ? String(marker.team_name) : 'チーム';
        const label = marker && marker.label ? String(marker.label) : 'チーム解散予定日';
        return '<tr class="schedule-month-table__row schedule-month-table__row--dissolution">'
            + '<th class="schedule-month-table__date" scope="row"><span class="schedule-month-table__date-text">'
            + escapeHtml(formatListDateHeading(dateKey)) + '</span></th>'
            + '<td class="schedule-month-table__team">' + escapeHtml(team) + '</td>'
            + '<td class="schedule-month-table__kind"><span class="schedule-list-badge schedule-list-badge--dissolution">'
            + escapeHtml(label) + '</span></td>'
            + '<td colspan="' + (shouldShowAttendanceSummary() ? 6 : 5) + '">この日をもってチームが解散する予定です。</td>'
            + '</tr>';
    }

    function buildDissolutionCardHtml(dateKey, marker) {
        const team = marker && marker.team_name ? String(marker.team_name) : 'チーム';
        return '<article class="schedule-month-card schedule-month-card--dissolution">'
            + '<div class="schedule-month-card__team">' + escapeHtml(team) + '</div>'
            + '<div class="schedule-month-card__status"><span class="schedule-list-badge schedule-list-badge--dissolution">チーム解散予定日</span></div>'
            + '<div class="schedule-month-card__time-cell"><span class="schedule-month-card__time">' + escapeHtml(dateKey) + '</span></div>'
            + '</article>';
    }

    function collectMonthSchedules(year, month) {
        const schedules = window.schedules || {};
        const items = [];
        Object.keys(schedules).forEach(function(dateKey) {
            if (!/^\d{4}-\d{2}-\d{2}$/.test(dateKey)) return;
            const d = parseYmd(dateKey);
            if (d.getFullYear() !== year || d.getMonth() !== month) return;
            const list = schedules[dateKey];
            if (!Array.isArray(list)) return;
            list.forEach(function(sch) {
                items.push({ dateKey: dateKey, schedule: sch });
            });
        });
        items.sort(function(a, b) {
            if (a.dateKey !== b.dateKey) {
                return a.dateKey < b.dateKey ? -1 : 1;
            }
            const ta = String(a.schedule.start_time || '');
            const tb = String(b.schedule.start_time || '');
            if (ta !== tb) return ta < tb ? -1 : 1;
            const na = String(a.schedule.team_name || '');
            const nb = String(b.schedule.team_name || '');
            return na.localeCompare(nb, 'ja');
        });
        return items;
    }

    function collectDaySchedules(dateKey) {
        const list = (window.schedules && window.schedules[dateKey]) || [];
        if (!Array.isArray(list)) return [];
        const intentFilters = getActiveIntentFilters();
        return list
            .map(function(sch) {
                return { dateKey: dateKey, schedule: sch };
            })
            .filter(function(row) {
                return matchesTeamFilter(row.schedule) && matchesIntentFilter(row.schedule, intentFilters);
            })
            .sort(function(a, b) {
                const ta = String(a.schedule.start_time || '');
                const tb = String(b.schedule.start_time || '');
                if (ta !== tb) return ta < tb ? -1 : 1;
                return String(a.schedule.team_name || '').localeCompare(String(b.schedule.team_name || ''), 'ja');
            });
    }

    function groupByDate(items) {
        const groups = [];
        let current = null;
        items.forEach(function(row) {
            if (!current || current.dateKey !== row.dateKey) {
                current = { dateKey: row.dateKey, items: [] };
                groups.push(current);
            }
            current.items.push(row);
        });
        return groups;
    }

    function buildGroupedScheduleListHtml(groups) {
        if (!groups || !groups.length) {
            return '';
        }

        let tableHtml = ''
            + '<table class="schedule-month-table">'
            + '<thead><tr>'
            + '<th scope="col">日付</th>'
            + '<th scope="col">チーム名</th>'
            + '<th scope="col">予定種別</th>'
            + '<th scope="col">時間</th>'
            + '<th scope="col">会場</th>'
            + '<th scope="col">対戦相手</th>'
            + '<th scope="col">メモ</th>'
            + (shouldShowAttendanceSummary() ? '<th scope="col">出欠</th>' : '')
            + '<th scope="col">操作</th>'
            + '</tr></thead><tbody>';

        let cardsHtml = '';
        groups.forEach(function(group) {
            const dissolutionMarkers = group.dissolutionMarkers || getDissolutionMarkersForDate(group.dateKey);
            dissolutionMarkers.forEach(function(marker) {
                tableHtml += buildDissolutionListRowHtml(group.dateKey, marker);
            });
            group.items.forEach(function(row, idx) {
                tableHtml += buildTableRowHtml(row, idx === 0 && dissolutionMarkers.length === 0, group.items.length);
            });

            const dayGenderClass = group.items.length
                ? (getListRowData(group.items[0].schedule).teamClass || 'team-gender-unknown')
                : 'team-gender-unknown';
            const dayCount = group.items.length + dissolutionMarkers.length;
            cardsHtml += '<section class="schedule-month-list__day">';
            cardsHtml += '<div class="schedule-month-list__day-head">';
            cardsHtml += '<h4 class="schedule-month-list__date ' + escapeHtml(dayGenderClass) + '">'
                + escapeHtml(formatListDateHeadingLong(group.dateKey)) + '</h4>';
            cardsHtml += '<span class="schedule-month-list__day-count">' + dayCount + '件の予定</span>';
            cardsHtml += '</div>';
            dissolutionMarkers.forEach(function(marker) {
                cardsHtml += buildDissolutionCardHtml(group.dateKey, marker);
            });
            group.items.forEach(function(row) {
                cardsHtml += buildMobileCardHtml(row);
            });
            cardsHtml += '</section>';
        });
        tableHtml += '</tbody></table>';

        return ''
            + '<div class="schedule-month-list__table-wrap">' + tableHtml + '</div>'
            + '<div class="schedule-month-list__cards-wrap">' + cardsHtml + '</div>';
    }

    function renderMonthScheduleList() {
        const listEl = document.getElementById('schedule-month-list');
        const emptyEl = document.getElementById('schedule-month-list-empty');
        if (!listEl) return;

        const current = window.currentDate || new Date();
        const year = current.getFullYear();
        const month = current.getMonth();
        const intentFilters = getActiveIntentFilters();
        const items = collectMonthSchedules(year, month);
        const filtered = items.filter(function(row) {
            return matchesTeamFilter(row.schedule) && matchesIntentFilter(row.schedule, intentFilters);
        });

        const groups = groupByDate(filtered);
        const markerDates = window.scheduleDissolutionMarkers ? Object.keys(window.scheduleDissolutionMarkers) : [];
        markerDates.forEach(function(dateKey) {
            const d = parseYmd(dateKey);
            if (d.getFullYear() !== year || d.getMonth() !== month) return;
            const markers = getDissolutionMarkersForDate(dateKey).filter(function(marker) {
                if (marker.team_id && getActiveTeamFilter() !== 'all' && String(marker.team_id) !== String(getActiveTeamFilter())) {
                    return false;
                }
                return true;
            });
            if (!markers.length) return;
            const existing = groups.find(function(group) { return group.dateKey === dateKey; });
            if (existing) {
                existing.dissolutionMarkers = markers;
                return;
            }
            groups.push({ dateKey: dateKey, items: [], dissolutionMarkers: markers });
        });
        groups.sort(function(a, b) {
            return a.dateKey < b.dateKey ? -1 : (a.dateKey > b.dateKey ? 1 : 0);
        });

        if (groups.length === 0) {
            listEl.innerHTML = '';
            if (emptyEl) emptyEl.hidden = false;
            return;
        }
        if (emptyEl) emptyEl.hidden = true;

        listEl.innerHTML = buildGroupedScheduleListHtml(groups);
    }

    function updateDayPanelVisibility() {
        const panel = document.getElementById('schedule-day-panel');
        if (!panel) return;
        panel.hidden = !shouldUseMobileDayPanel();
    }

    function renderCalendarDayPanel() {
        const panel = document.getElementById('schedule-day-panel');
        const titleEl = document.getElementById('schedule-day-panel-title');
        const countEl = document.getElementById('schedule-day-panel-count');
        const listEl = document.getElementById('schedule-day-panel-list');
        const emptyEl = document.getElementById('schedule-day-panel-empty');
        if (!panel || !titleEl || !listEl) return;

        if (!shouldUseMobileDayPanel()) {
            panel.hidden = true;
            return;
        }

        const dateKey = String(getViewConfig().selectedCalendarDate || '');
        if (!dateKey) {
            titleEl.textContent = '';
            if (countEl) countEl.textContent = '';
            listEl.innerHTML = '';
            if (emptyEl) emptyEl.hidden = true;
            panel.hidden = true;
            return;
        }

        panel.hidden = false;
        titleEl.textContent = formatListDateHeadingLong(dateKey);
        const rows = collectDaySchedules(dateKey);
        if (countEl) {
            countEl.textContent = rows.length > 0 ? rows.length + '件の予定' : '予定なし';
        }

        if (rows.length === 0) {
            listEl.innerHTML = '';
            if (emptyEl) emptyEl.hidden = false;
            return;
        }
        if (emptyEl) emptyEl.hidden = true;
        listEl.innerHTML = rows.map(function(row) {
            return buildMobileCardHtml(row);
        }).join('');
    }

    function ensureMobileCalendarDaySelection() {
        if (!shouldUseMobileDayPanel()) {
            updateDayPanelVisibility();
            return;
        }
        const cfg = getViewConfig();
        if (!cfg.selectedCalendarDate) {
            selectCalendarDay(formatDateLocal(new Date()));
            return;
        }
        updateDayPanelVisibility();
        renderCalendarDayPanel();
    }

    function selectCalendarDay(dateString) {
        if (!dateString) return;
        window.AIDUNITE_SCHEDULE_VIEW = window.AIDUNITE_SCHEDULE_VIEW || {};
        window.AIDUNITE_SCHEDULE_VIEW.selectedCalendarDate = String(dateString).substring(0, 10);
        if (typeof window.renderCalendar === 'function') {
            window.renderCalendar();
        }
        updateDayPanelVisibility();
        renderCalendarDayPanel();
    }

    function findScheduleById(scheduleId) {
        const sid = String(scheduleId || '');
        if (!sid || !window.schedules) return null;
        for (const dateKey in window.schedules) {
            const list = window.schedules[dateKey];
            if (!Array.isArray(list)) continue;
            const found = list.find((s) => String(s.id || s.schedule_id || s.post_id) === sid);
            if (found) return found;
        }
        return null;
    }

    function handleListAction(action, scheduleId) {
        const schedule = findScheduleById(scheduleId);
        if (!schedule) return;
        if (typeof AidUniteScheduleQuickModal === 'undefined') {
            if (typeof window.showScheduleDetail === 'function') {
                window.showScheduleDetail(scheduleId);
            }
            return;
        }
        if (action === 'view') {
            AidUniteScheduleQuickModal.openCardActions(schedule);
            return;
        }
        if (action === 'edit') {
            AidUniteScheduleQuickModal.openEdit(schedule);
            return;
        }
        if (action === 'delete') {
            const runDelete = () => {
                if (typeof AidUniteScheduleQuickModal.deleteScheduleById === 'function') {
                    AidUniteScheduleQuickModal.deleteScheduleById(scheduleId);
                }
            };
            if (typeof AidUniteScheduleModal !== 'undefined' && typeof AidUniteScheduleModal.confirmAction === 'function') {
                AidUniteScheduleModal.confirmAction({
                    message: 'このスケジュールを削除してもよろしいですか？',
                    onConfirm: runDelete,
                });
            } else if (typeof showConfirmModal === 'function') {
                showConfirmModal({
                    title: '削除確認',
                    message: 'このスケジュールを削除してもよろしいですか？',
                    confirmLabel: '削除する',
                    cancelLabel: 'キャンセル',
                    confirmVariant: 'danger',
                    onConfirm: runDelete,
                });
            } else {
                runDelete();
            }
        }
    }

    function applyViewMode(mode) {
        const calendarPanel = document.getElementById('schedule-calendar-panel');
        const listWrap = document.getElementById('schedule-month-list-wrap');
        const toggle = document.getElementById('schedule-view-toggle');
        if (!calendarPanel || !listWrap) return;

        const useList = mode === 'list';
        calendarPanel.hidden = useList;
        listWrap.hidden = !useList;

        if (toggle) {
            toggle.querySelectorAll('.filter-chip[data-view]').forEach(function(btn) {
                const v = btn.getAttribute('data-view');
                const active = v === mode;
                btn.classList.toggle('active', active);
                btn.setAttribute('aria-selected', active ? 'true' : 'false');
            });
        }

        window.AIDUNITE_SCHEDULE_VIEW = window.AIDUNITE_SCHEDULE_VIEW || {};
        window.AIDUNITE_SCHEDULE_VIEW.currentViewMode = mode;

        if (typeof window.AidUniteUpdateScheduleMonthTitle === 'function') {
            window.AidUniteUpdateScheduleMonthTitle();
        }

        if (useList) {
            updateDayPanelVisibility();
            renderMonthScheduleList();
        } else if (typeof window.renderCalendar === 'function') {
            window.renderCalendar();
            ensureMobileCalendarDaySelection();
        } else {
            ensureMobileCalendarDaySelection();
        }
    }

    function initViewToggle() {
        const toggle = document.getElementById('schedule-view-toggle');
        if (!toggle || toggle.dataset.bound === '1') return;
        toggle.dataset.bound = '1';

        const mode = resolveDefaultViewMode();
        applyViewMode(mode);

        toggle.addEventListener('click', function(e) {
            const btn = e.target.closest('.filter-chip[data-view]');
            if (!btn) return;
            const view = btn.getAttribute('data-view');
            if (view !== 'calendar' && view !== 'list') return;
            setStoredViewMode(view);
            applyViewMode(view);
        });

        const mq = window.matchMedia('(max-width: ' + (getViewConfig().listBreakpointPx || 768) + 'px)');
        if (typeof mq.addEventListener === 'function') {
            mq.addEventListener('change', function() {
                if (!getStoredViewMode()) {
                    applyViewMode(isMobileViewport() ? 'list' : 'calendar');
                } else {
                    applyViewMode(getStoredViewMode());
                }
            });
        }
    }

    function initTeamFilter() {
        const container = document.getElementById('schedule-team-filter-chips');
        if (!container || container.dataset.bound === '1') return;
        container.dataset.bound = '1';

        container.addEventListener('click', function(e) {
            const btn = e.target.closest('.filter-chip[data-team-filter]');
            if (!btn) return;
            const value = btn.getAttribute('data-team-filter');
            setActiveTeamFilter(value);
            container.querySelectorAll('.filter-chip').forEach(function(el) {
                el.classList.toggle('active', el.getAttribute('data-team-filter') === value);
            });
            if (typeof renderCalendar === 'function') {
                renderCalendar();
            }
            if (typeof window.applyScheduleFilter === 'function') {
                window.applyScheduleFilter();
            }
            afterDataLoaded();
        });
    }

    function bindScheduleListInteractions(root) {
        if (!root || root.dataset.scheduleListBound === '1') return;
        root.dataset.scheduleListBound = '1';

        root.addEventListener('click', function(e) {
            const actionBtn = e.target.closest('[data-action][data-schedule-id]');
            if (actionBtn) {
                e.preventDefault();
                e.stopPropagation();
                handleListAction(actionBtn.getAttribute('data-action'), actionBtn.getAttribute('data-schedule-id'));
                return;
            }

            const card = e.target.closest('[data-schedule-id].schedule-month-card, [data-schedule-id].schedule-month-list__item');
            if (!card) return;
            const id = card.getAttribute('data-schedule-id');
            if (!id) return;
            e.preventDefault();
            handleListAction('view', id);
        });
    }

    function initListItemClicks() {
        bindScheduleListInteractions(document.getElementById('schedule-month-list-wrap'));
        bindScheduleListInteractions(document.getElementById('schedule-day-panel'));
    }

    function afterDataLoaded() {
        const mode = window.AIDUNITE_SCHEDULE_VIEW?.currentViewMode || resolveDefaultViewMode();
        if (mode === 'list') {
            renderMonthScheduleList();
        } else {
            if (typeof window.renderCalendar === 'function') {
                window.renderCalendar();
            }
            renderCalendarDayPanel();
        }
        if (typeof window.applyScheduleFilter === 'function') {
            window.applyScheduleFilter();
        }
    }

    window.AidUniteScheduleMonthView = {
        renderMonthScheduleList: renderMonthScheduleList,
        buildGroupedScheduleListHtml: buildGroupedScheduleListHtml,
        bindScheduleListInteractions: bindScheduleListInteractions,
        renderCalendarDayPanel: renderCalendarDayPanel,
        selectCalendarDay: selectCalendarDay,
        applyViewMode: applyViewMode,
        afterDataLoaded: afterDataLoaded,
        getFetchOptions: getFetchOptions,
        resolveDefaultViewMode: resolveDefaultViewMode,
        init: function() {
            initViewToggle();
            initTeamFilter();
            initListItemClicks();
        },
    };

    document.addEventListener('DOMContentLoaded', function() {
        window.AidUniteScheduleMonthView.init();
    });
})();
