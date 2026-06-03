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
        const cfg = getViewConfig();
        const bp = cfg.listBreakpointPx || 768;
        return window.matchMedia('(max-width: ' + bp + 'px)').matches;
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
        if (cardClass.indexOf('event') >= 0) return 'event';
        if (cardClass.indexOf('off') >= 0) return 'off';
        return 'practice';
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

        if (filtered.length === 0) {
            listEl.innerHTML = '';
            if (emptyEl) emptyEl.hidden = false;
            return;
        }
        if (emptyEl) emptyEl.hidden = true;

        let html = '';
        let lastDate = '';
        filtered.forEach(function(row) {
            const sch = row.schedule;
            const compact = getCompactLines(sch);
            const modifier = presentationToModifier(compact.cardClass);
            const teamClass = typeof window.AidUniteGetTeamGenderClass === 'function'
                ? window.AidUniteGetTeamGenderClass(sch)
                : 'team-gender-unknown';
            const scheduleId = sch.id || sch.post_id || '';

            if (row.dateKey !== lastDate) {
                if (lastDate !== '') html += '</section>';
                html += '<section class="schedule-month-list__day">';
                html += '<h4 class="schedule-month-list__date">' + escapeHtml(formatListDateHeading(row.dateKey)) + '</h4>';
                lastDate = row.dateKey;
            }

            html += '<article class="schedule-month-list__item schedule-month-list__item--' + modifier + ' ' + teamClass + '"';
            html += ' data-schedule-id="' + scheduleId + '" role="button" tabindex="0">';
            html += '<div class="schedule-month-list__line1">' + escapeHtml(compact.line1 || '予定') + '</div>';
            if (compact.line2) {
                html += '<div class="schedule-month-list__line2">' + escapeHtml(compact.line2) + '</div>';
            }
            html += '</article>';
        });
        if (lastDate !== '') html += '</section>';
        listEl.innerHTML = html;
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
            renderMonthScheduleList();
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

    function initListItemClicks() {
        const listWrap = document.getElementById('schedule-month-list-wrap');
        if (!listWrap || listWrap.dataset.detailBound === '1') return;
        listWrap.dataset.detailBound = '1';

        listWrap.addEventListener('click', function(e) {
            const item = e.target.closest('.schedule-month-list__item[data-schedule-id]');
            if (!item) return;
            const id = item.getAttribute('data-schedule-id');
            if (!id) return;
            e.preventDefault();
            if (typeof window.showScheduleDetail === 'function') {
                window.showScheduleDetail(id);
            } else if (typeof AidUniteScheduleModal !== 'undefined') {
                AidUniteScheduleModal.showDetail(id, { mode: 'popup' });
            }
        });
    }

    function afterDataLoaded() {
        const mode = window.AIDUNITE_SCHEDULE_VIEW?.currentViewMode || resolveDefaultViewMode();
        if (mode === 'list') {
            renderMonthScheduleList();
        }
        if (typeof window.applyScheduleFilter === 'function') {
            window.applyScheduleFilter();
        }
    }

    window.AidUniteScheduleMonthView = {
        renderMonthScheduleList: renderMonthScheduleList,
        applyViewMode: applyViewMode,
        afterDataLoaded: afterDataLoaded,
        getFetchOptions: getFetchOptions,
        resolveDefaultViewMode: resolveDefaultViewMode,
        init: function() {
            initViewToggle();
            initTeamFilter();
            initListItemClicks();
        }
    };

    document.addEventListener('DOMContentLoaded', function() {
        window.AidUniteScheduleMonthView.init();
    });
})();
