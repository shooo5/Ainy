/**
 * スケジュールカレンダー統一JavaScript
 * マイページ、管理画面、登録画面で共通使用
 * mode: 'view' | 'edit' で動作を切り替え
 */

(function() {
    'use strict';

    // グローバル変数（後方互換性のため）
    window.currentDate = window.currentDate || new Date();
    window.schedules = window.schedules || {};

    // formatDateLocal関数は js/common/date-utils.js の AidUniteDateUtils.formatDateLocal() を使用
    // 後方互換性のため、グローバル関数として利用可能
    function formatDateLocal(date) {
        if (typeof AidUniteDateUtils !== 'undefined' && AidUniteDateUtils.formatDateLocal) {
            return AidUniteDateUtils.formatDateLocal(date);
        }
        // フォールバック: 直接実装
        if (!date || !(date instanceof Date)) {
            console.warn('formatDateLocal: 有効なDateオブジェクトが必要です');
            return '';
        }
        const y = date.getFullYear();
        const m = (date.getMonth() + 1).toString().padStart(2, '0');
        const d = date.getDate().toString().padStart(2, '0');
        return `${y}-${m}-${d}`;
    }

    /**
     * 今日の日付かどうかチェック
     */
    function isTodayDate(dateString) {
        const today = new Date();
        const jstToday = formatDateLocal(today);
        return dateString === jstToday;
    }

    /**
     * 日本の祝日・イベント名を取得（例: 元日 / 文化の日 / 大晦日 など）
     */
    function getJapaneseHolidayOrEventLabel(date) {
        const year = date.getFullYear();
        const month = date.getMonth() + 1;
        const day = date.getDate();

        // 固定祝日・イベント
        const fixedHolidays = {
            '1-1':  '元日',
            '2-11': '建国記念の日',
            '4-29': '昭和の日',
            '5-3':  '憲法記念日',
            '5-4':  'みどりの日',
            '5-5':  'こどもの日',
            '8-11': '山の日',
            '11-3': '文化の日',
            '11-23': '勤労感謝の日',
            '2-23': '天皇誕生日',
            '12-31': '大晦日',
            '12-25': 'クリスマス'
        };

        const key = `${month}-${day}`;
        if (fixedHolidays[key]) {
            return fixedHolidays[key];
        }

        // 春分の日（簡易計算）
        if (month === 3) {
            const springEquinox = year <= 1980 ? Math.floor(20.8357 + 0.242194 * (year - 1980) - Math.floor((year - 1980) / 4)) :
                year <= 2099 ? Math.floor(20.8431 + 0.242194 * (year - 1980) - Math.floor((year - 1980) / 4)) : 20;
            if (day === springEquinox) {
                return '春分の日';
            }
        }

        // 秋分の日（簡易計算）
        if (month === 9) {
            const autumnEquinox = year <= 1980 ? Math.floor(23.2588 + 0.242194 * (year - 1980) - Math.floor((year - 1980) / 4)) :
                year <= 2099 ? Math.floor(23.2488 + 0.242194 * (year - 1980) - Math.floor((year - 1980) / 4)) : 23;
            if (day === autumnEquinox) {
                return '秋分の日';
            }
        }

        // 海の日（7月23日、2020年以降）
        if (month === 7 && year >= 2020 && day === 23) {
            return '海の日';
        }

        // 敬老の日（9月の第3月曜日 - 簡易版）
        if (month === 9) {
            const firstDay = new Date(year, 8, 1);
            const firstMonday = (9 - firstDay.getDay()) % 7;
            const thirdMonday = firstMonday + 14;
            if (day === thirdMonday) {
                return '敬老の日';
            }
        }

        // スポーツの日（10月の第2月曜日 - 簡易版）
        if (month === 10) {
            const firstDay = new Date(year, 9, 1);
            const firstMonday = (9 - firstDay.getDay()) % 7;
            const secondMonday = firstMonday + 7;
            if (day === secondMonday) {
                return 'スポーツの日';
            }
        }

        return '';
    }

    /**
     * 日本の祝日判定（簡易版：登録ページと同じロジック）
     * 既存コードとの互換性のため、boolean を返すラッパーとして残す
     */
    function isJapaneseHoliday(date) {
        return !!getJapaneseHolidayOrEventLabel(date);
    }

    /**
     * スケジュールタイトルを短縮
     */
    function shortenScheduleTitle(title) {
        const shortTitles = {
            '練習': '練習',
            '練習（仮）': '練習（仮）',
            '公式試合': '公式試合',
            '練習試合': '練習試合',
            '練習試合（募集）': '練習試合（募）',
            '練習試合（募）': '練習試合（募）',
            '練習試合（仮）': '練習試合（仮）',
            '合同練習': '合同練習',
            '合同練習（募集）': '合同練習（募）',
            '合同練習（募）': '合同練習（募）',
            '合同練習（仮）': '合同練習（仮）',
            '合宿': '合宿',
            '合宿（仮）': '合宿（仮）',
            '遠征': '遠征',
            '遠征（仮）': '遠征（仮）',
            '休み': '休み',
            '休み（仮）': '休み（仮）',
            'イベント': 'イベント',
            'イベント（仮）': 'イベント（仮）'
        };
        return shortTitles[title] || title;
    }

    /**
     * intentに応じて表示用typeを正規化（確定時は募集表記を除去）
     */
    function normalizeScheduleType(schedule) {
        const rawType = String(
            schedule?.type || schedule?.schedule_type || schedule?.display_type || ''
        ).trim();
        if (!rawType) return rawType;
        const intent = String(schedule?.intent || '').toLowerCase();
        if (intent === 'confirmed') {
            return rawType
                .replace(/（募集）/g, '')
                .replace(/\(募集\)/g, '')
                .replace(/（募）/g, '')
                .replace(/\(募\)/g, '')
                .trim();
        }
        return rawType;
    }

    function escapeCalendarHtml(text) {
        if (text == null) return '';
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function formatScheduleTimeRange(schedule) {
        const start = String(schedule?.start_time || '').trim();
        const end = String(schedule?.end_time || '').trim();
        if (start && end) {
            return start + '〜' + end;
        }
        if (start || end) {
            return start || end;
        }
        return '';
    }

    /** リスト表示用（09:00 - 11:00） */
    function formatScheduleListTime(schedule) {
        const start = String(schedule?.start_time || '').trim();
        const end = String(schedule?.end_time || '').trim();
        if (start && end) {
            return start + ' - ' + end;
        }
        if (start || end) {
            return start || end;
        }
        return '';
    }

    function presentationToListModifier(cardClass) {
        if (!cardClass) return 'practice';
        if (cardClass.indexOf('match-confirmed') >= 0) return 'match-confirmed';
        if (cardClass.indexOf('match-recruit') >= 0) return 'match-recruit';
        if (cardClass.indexOf('tentative') >= 0) return 'tentative';
        if (cardClass.indexOf('meeting') >= 0) return 'meeting';
        if (cardClass.indexOf('event') >= 0) return 'event';
        if (cardClass.indexOf('off') >= 0) return 'off';
        return 'practice';
    }

    function resolveScheduleVenueLabel(schedule) {
        if (typeof AidUniteScheduleUtils !== 'undefined' && AidUniteScheduleUtils.resolveScheduleVenueLabel) {
            return AidUniteScheduleUtils.resolveScheduleVenueLabel(schedule);
        }
        return '';
    }

    function resolveScheduleMemoLabel(schedule) {
        return String(schedule?.memo || schedule?.note || schedule?.quick_memo || '').trim();
    }

    /**
     * 月間リスト／日別パネル用の行データ
     */
    function hasNormalizedCardDisplay(schedule) {
        return !!(schedule && (schedule.card_line1 != null || schedule.status_label != null));
    }

    function buildScheduleListRowData(schedule) {
        const compact = buildScheduleCompactLines(schedule);
        const modifier = schedule?.card_modifier
            ? String(schedule.card_modifier)
            : presentationToListModifier(compact.cardClass);
        const teamName = schedule?.team_name ? String(schedule.team_name).trim() : '';
        const kindLabel = schedule?.status_label != null && String(schedule.status_label).trim() !== ''
            ? String(schedule.status_label).trim()
            : resolveStatusLabel(
                resolveScheduleIntent(schedule),
                normalizeScheduleType(schedule),
                getScheduleTypeFlags(schedule)
            );
        const listTimeLabel = schedule?.list_time_label != null
            ? String(schedule.list_time_label)
            : (schedule?.card_line2 != null ? String(schedule.card_line2) : formatScheduleListTime(schedule));

        return {
            scheduleId: String(schedule.id || schedule.post_id || schedule.schedule_id || ''),
            modifier: modifier,
            teamClass: getTeamGenderClass(schedule),
            teamName: teamName,
            kindLabel: kindLabel,
            timeLabel: listTimeLabel,
            venueLabel: resolveScheduleVenueLabel(schedule),
            opponentLabel: resolveOpponentDisplay(schedule),
            opponentTitle: resolveOpponentFullDisplay(schedule),
            memoLabel: resolveScheduleMemoLabel(schedule),
        };
    }

    function isScheduleMobileViewport() {
        const cfg = window.AIDUNITE_SCHEDULE_VIEW || {};
        const bp = cfg.listBreakpointPx || 768;
        return window.matchMedia('(max-width: ' + bp + 'px)').matches;
    }

    function shouldHideInlineCalendarCards() {
        const cfg = window.AIDUNITE_SCHEDULE_VIEW || {};
        const mode = cfg.currentViewMode || 'calendar';
        return mode === 'calendar' && isScheduleMobileViewport();
    }

    function shouldUseMobileCalendarDayPanel() {
        return shouldHideInlineCalendarCards();
    }

    function resolveScheduleIntent(schedule) {
        if (typeof AidUniteScheduleUtils !== 'undefined' && AidUniteScheduleUtils.normalizeScheduleIntent) {
            return AidUniteScheduleUtils.normalizeScheduleIntent(schedule);
        }
        let intent = String(schedule?.intent || '').toLowerCase().trim();
        const matching = schedule?.matching === 1 || schedule?.matching === '1' || schedule?.matching === true;
        if (!intent && matching) {
            intent = 'recruit';
        }
        if (!intent) {
            intent = 'confirmed';
        }
        return intent;
    }

    /** 種別名から「（仮）」を除いた表示用ラベル */
    function stripTentativeTypeSuffix(type) {
        return String(type || '')
            .replace(/（仮）/g, '')
            .replace(/\(仮\)/g, '')
            .trim();
    }

    /** 仮予定カード1行目: 「仮　合宿」形式 */
    function resolveTentativeStatusLabel(scheduleType) {
        const base = stripTentativeTypeSuffix(scheduleType);
        const label = shortenScheduleTitle(base) || base || '予定';
        return '仮\u3000' + label;
    }

    function getScheduleTypeFlags(schedule) {
        const t = normalizeScheduleType(schedule)
            || String(schedule?.schedule_type || schedule?.display_type || '');
        const uiKind = String(schedule?.ui_schedule_kind || '');
        const isJointPractice = t.includes('合同練習');
        return {
            scheduleType: t,
            isPractice: t.includes('練習')
                && !t.includes('練習試合')
                && !t.includes('公式試合')
                && !isJointPractice,
            isMatch: t.includes('公式試合') || t.includes('練習試合') || t.includes('公式戦'),
            isMeeting: t.includes('ミーティング') || t.includes('会議'),
            isEvent: t.includes('合宿') || t.includes('遠征') || t.includes('イベント') || isJointPractice,
            isOff: uiKind === 'rest' || t.includes('休み'),
        };
    }

    function splitOpponentNameList(text) {
        return String(text || '')
            .split(/[,、]/)
            .map((s) => s.trim())
            .filter(Boolean);
    }

    function abbreviateOpponentNames(names) {
        const unique = [];
        names.forEach((name) => {
            const n = String(name || '').trim();
            if (!n) return;
            if (!unique.includes(n)) unique.push(n);
        });
        if (unique.length === 0) return '';
        if (unique.length === 1) return unique[0];
        return unique[0] + '他' + (unique.length - 1);
    }

    function isAbbreviatedOpponentLabel(text) {
        return /他\d+$/.test(String(text || '').trim());
    }

    function collectOpponentNameList(schedule) {
        const names = [];
        const participantsList = String(schedule?.participants_list || '').trim();
        if (participantsList && participantsList !== '-') {
            names.push(...splitOpponentNameList(participantsList));
        }
        if (names.length > 0) {
            return names;
        }

        const display = String(schedule?.opponent_display || '').trim();
        if (display) {
            if (isAbbreviatedOpponentLabel(display)) {
                return [display];
            }
            const split = splitOpponentNameList(display);
            return split.length > 0 ? split : [display];
        }

        const opponentName = String(schedule?.opponent?.name || '').trim();
        if (opponentName) {
            const split = splitOpponentNameList(opponentName);
            return split.length > 0 ? split : [opponentName];
        }

        const count = parseInt(String(schedule?.participant_count || 0), 10) || 0;
        if (count > 1 && display) {
            return [display];
        }

        return [];
    }

    function resolveOpponentFullDisplay(schedule) {
        const names = collectOpponentNameList(schedule);
        if (names.length === 0) return '';
        if (names.length === 1 && isAbbreviatedOpponentLabel(names[0])) {
            return names[0];
        }
        return names.join('、');
    }

    function resolveOpponentDisplay(schedule) {
        const names = collectOpponentNameList(schedule);
        if (names.length === 0) return '';
        if (names.length === 1 && isAbbreviatedOpponentLabel(names[0])) {
            return names[0];
        }
        return abbreviateOpponentNames(names);
    }

    function resolveStatusLabel(schedule, intent, scheduleType, flags) {
        if (intent === 'tentative') {
            return resolveTentativeStatusLabel(scheduleType);
        }
        if (flags.isOff) {
            return '休み';
        }
        if (flags.isMeeting) {
            return 'ミーティング';
        }
        if (flags.isEvent) {
            return shortenScheduleTitle(scheduleType) || 'イベント';
        }
        if (intent === 'recruit') {
            return '試合の募集';
        }
        if (intent === 'confirmed' && flags.isMatch) {
            if (scheduleType.includes('練習試合')) {
                return '練習試合';
            }
            if (scheduleType.includes('公式')) {
                return '試合確定';
            }
            return '試合確定';
        }
        if (flags.isPractice) {
            return '通常練習';
        }
        return shortenScheduleTitle(scheduleType) || '予定';
    }

    function resolveCardClass(intent, flags) {
        if (intent === 'tentative') {
            return 'schedule-card off';
        }
        if (flags.isOff) {
            return 'schedule-card off';
        }
        if (flags.isMeeting) {
            return 'schedule-card meeting';
        }
        if (flags.isEvent) {
            return 'schedule-card event';
        }
        if (intent === 'recruit') {
            return 'schedule-card match-recruit';
        }
        if (intent === 'confirmed' && flags.isMatch) {
            return 'schedule-card match-confirmed';
        }
        return 'schedule-card practice';
    }

    /**
     * カレンダー／リスト共通：2行コンパクト表示
     * line1: チーム名　種別（マルチチーム時） / 種別のみ（単一チーム）
     * line2: 時間 / 時間　相手（試合系）
     */
    function buildScheduleCompactLines(schedule) {
        if (hasNormalizedCardDisplay(schedule)) {
            const line1 = String(schedule.card_line1 || '').trim();
            const line2 = String(schedule.card_line2 || '').trim();
            const cardClass = schedule.card_class
                ? String(schedule.card_class)
                : ('schedule-card ' + (schedule.card_modifier || 'practice'));
            return {
                line1: line1,
                line2: line2,
                cardClass: cardClass,
                lines: [line1, line2].filter(Boolean),
            };
        }

        const intent = resolveScheduleIntent(schedule);
        const scheduleType = normalizeScheduleType(schedule);
        const flags = getScheduleTypeFlags(schedule);
        const timeLine = formatScheduleTimeRange(schedule);
        const opponentName = resolveOpponentDisplay(schedule);
        const statusLabel = resolveStatusLabel(schedule, intent, scheduleType, flags);
        const cfg = window.AIDUNITE_SCHEDULE_VIEW || {};
        const teamName = schedule?.team_name ? String(schedule.team_name).trim() : '';
        const showTeam = cfg.multiTeam && teamName;
        const cardClass = resolveCardClass(intent, flags);

        let line1 = showTeam ? teamName + '\u3000' + statusLabel : statusLabel;
        let line2 = timeLine;
        if (intent === 'confirmed' && flags.isMatch && opponentName) {
            line2 = timeLine ? timeLine + '\u3000' + opponentName : opponentName;
        }

        return {
            line1: line1,
            line2: line2,
            cardClass: cardClass,
            lines: [line1, line2].filter(Boolean),
        };
    }

    /**
     * 月間カレンダー用：表示行と配色クラス（intent × 種別）
     */
    function resolveCalendarCardPresentation(schedule) {
        const compact = buildScheduleCompactLines(schedule);
        return {
            cardClass: compact.cardClass,
            lines: compact.lines,
            line1: compact.line1,
            line2: compact.line2,
        };
    }

    /**
     * 月間カレンダー用スケジュールカード HTML（最大3行）
     */
    function getTeamGenderClass(schedule) {
        const gender = String(schedule?.team_gender || '').toLowerCase();
        if (gender === 'male') {
            return 'team-gender-male';
        }
        if (gender === 'female') {
            return 'team-gender-female';
        }
        const label = String(schedule?.team_gender_label || '');
        if (label.indexOf('女') >= 0) {
            return 'team-gender-female';
        }
        if (label.indexOf('男') >= 0) {
            return 'team-gender-male';
        }
        return 'team-gender-unknown';
    }

    function buildCalendarScheduleCard(schedule, options = {}) {
        const { simpleMode = false } = options;
        const compact = buildScheduleCompactLines(schedule);
        const scheduleId = schedule.id || schedule.post_id || schedule.schedule_id || '';
        const teamGenderClass = getTeamGenderClass(schedule);
        const cardClasses = compact.cardClass + ' ' + teamGenderClass;
        const line1 = compact.line1 || '予定';
        const line2 = compact.line2 || '';

        if (simpleMode) {
            return (
                '<div class="' + cardClasses + '">' +
                '<div class="schedule-line-1">' + escapeCalendarHtml(line1) + '</div>' +
                '</div>'
            );
        }

        let linesHtml =
            '<div class="schedule-line-1">' + escapeCalendarHtml(line1) + '</div>';
        if (line2) {
            linesHtml += '<div class="schedule-line-2">' + escapeCalendarHtml(line2) + '</div>';
        }

        return (
            '<div class="' + cardClasses + '" data-schedule-id="' + scheduleId + '" data-team-id="' + (schedule.team_id || '') + '" role="button" tabindex="0">' +
            linesHtml +
            '</div>'
        );
    }

    /**
     * スケジュールカードを生成（月間カレンダー）
     */
    function generateScheduleCard(schedule, options = {}) {
        return buildCalendarScheduleCard(schedule, options);
    }

    /**
     * スケジュールのHTMLを生成
     * dateString は Y-m-d に正規化して window.schedules と照合（マイページとの整合を確保）
     */
    function getDissolutionMarkerHTML(dateString) {
        const key = dateString ? String(dateString).trim().substring(0, 10) : '';
        if (!key || !window.scheduleDissolutionMarkers || !window.scheduleDissolutionMarkers[key]) {
            return '';
        }
        return window.scheduleDissolutionMarkers[key].map(function(marker) {
            const team = marker.team_name ? String(marker.team_name) : 'チーム';
            const label = marker.label ? String(marker.label) : 'チーム解散予定日';
            return '<div class="schedule-dissolution-marker" title="' + team + '">'
                + '<span class="schedule-dissolution-marker__label">' + label + '</span>'
                + '<span class="schedule-dissolution-marker__team">' + team + '</span>'
                + '</div>';
        }).join('');
    }

    function getScheduleHTML(dateString) {
        const key = dateString ? String(dateString).trim().substring(0, 10) : '';
        if (!key) {
            return '';
        }
        const dissolutionHtml = getDissolutionMarkerHTML(key);
        if (shouldHideInlineCalendarCards()) {
            return dissolutionHtml;
        }
        if ((!window.schedules[key] || window.schedules[key].length === 0) && !dissolutionHtml) {
            return '';
        }

        const scheduleList = Array.isArray(window.schedules[key]) ? window.schedules[key] : [];
        const maxDisplay = 2; // 最大表示件数（3件目以降は +N件）
        const showMore = scheduleList.length > maxDisplay;
        const displaySchedules = showMore ? scheduleList.slice(0, maxDisplay) : scheduleList;

        let html = '';

        // generateScheduleCardを安全に参照（output_schedule_calendar_js()内で定義されたものを優先）
        const cardGenerator = typeof window.generateScheduleCard === 'function'
            ? window.generateScheduleCard
            : (typeof generateScheduleCard === 'function'
                ? generateScheduleCard
                : null);

        if (!cardGenerator && !dissolutionHtml) {
            console.warn('⚠️ generateScheduleCard関数が見つかりません');
            return dissolutionHtml;
        }

        html += dissolutionHtml;

        // 表示するスケジュール
        if (cardGenerator && window.schedules[key]) {
            displaySchedules.forEach(schedule => {
                html += cardGenerator(schedule);
            });
        }

        // 残り件数表示（複数スケジュールをすべて表示）
        if (showMore) {
            const remainingCount = scheduleList.length - maxDisplay;
            html += `<div class="schedule-more" onclick="if(window.showAllSchedulesForDate){window.showAllSchedulesForDate('${dateString}');}else if(window.showAllSchedules){window.showAllSchedules('${dateString}');}">+ ${remainingCount}件</div>`;
        }

        return html;
    }

    /**
     * 月タイトル（カレンダー／リスト共通）
     */
    function updateScheduleMonthTitle() {
        const calendarTitle = document.getElementById('calendar-title');
        if (!calendarTitle) {
            return;
        }
        const currentDate = window.currentDate || new Date();
        calendarTitle.textContent = currentDate.getFullYear() + '年' + (currentDate.getMonth() + 1) + '月';
    }

    /**
     * カレンダーを描画
     */
    function renderCalendar() {
        console.log('📅 renderCalendar関数開始');

        const calendarGrid = document.getElementById('calendar-grid');
        const calendarPanel = document.getElementById('schedule-calendar-panel');
        updateScheduleMonthTitle();

        if (!calendarGrid) {
            console.warn('❌ カレンダーグリッドが見つかりません');
            return;
        }

        const mobileCompact = shouldHideInlineCalendarCards();
        calendarGrid.classList.toggle('calendar-grid--mobile-compact', mobileCompact);
        if (calendarPanel) {
            calendarPanel.classList.toggle('schedule-calendar-panel--mobile-compact', mobileCompact);
        }

        // window.currentDateを安全に参照
        const currentDate = window.currentDate || new Date();
        const year = currentDate.getFullYear();
        const month = currentDate.getMonth();

        console.log('📅 カレンダー描画:', { year, month });
        console.log('📅 スケジュールデータ:', window.schedules);

        // 月の最初の日を取得
        const firstDay = new Date(year, month, 1);
        const startDate = new Date(firstDay);
        startDate.setDate(startDate.getDate() - firstDay.getDay());

        let calendarHTML = '';
        let currentWeek = new Date(startDate);

        // 6週間分のカレンダーを生成（週ごとにラップ）
        for (let week = 0; week < 6; week++) {
            calendarHTML += '<div class="calendar-week">';
            for (let day = 0; day < 7; day++) {
                const date = new Date(currentWeek);
                const isCurrentMonth = date.getMonth() === month;
                const isToday = isTodayDate(formatDateLocal(date));
                const isPast = date < new Date(new Date().setHours(0, 0, 0, 0));

                const dateString = formatDateLocal(date);

                // 曜日・祝日の判定
                const dayOfWeek = date.getDay();
                const isSaturday = dayOfWeek === 6;
                const isSunday = dayOfWeek === 0;
                const holidayLabel = getJapaneseHolidayOrEventLabel(date);
                const isHoliday = !!holidayLabel;

                // スケジュール登録画面のUIに統一（se-プレフィックス）
                let dayClass = 'se-day';
                if (!isCurrentMonth) dayClass += ' se-other-month';
                if (isToday) dayClass += ' se-today';
                if (isPast) dayClass += ' se-past';

                // 土曜・日曜・祝日のクラス追加（色分け用）
                if (isSaturday) dayClass += ' se-saturday';
                if (isSunday) dayClass += ' se-sunday';
                if (isHoliday) dayClass += ' se-holiday';
                if (!isSaturday && !isSunday && !isHoliday) dayClass += ' se-weekday';

                // スケジュールがあるかチェック（Y-m-d で照合）
                const scheduleKey = dateString ? String(dateString).trim().substring(0, 10) : '';
                if (scheduleKey && window.schedules[scheduleKey] && window.schedules[scheduleKey].length > 0) {
                    dayClass += ' has-schedule';
                }
                if (scheduleKey && window.scheduleDissolutionMarkers
                    && window.scheduleDissolutionMarkers[scheduleKey]
                    && window.scheduleDissolutionMarkers[scheduleKey].length > 0) {
                    dayClass += ' has-dissolution';
                }

                const selectedDate = String(window.AIDUNITE_SCHEDULE_VIEW?.selectedCalendarDate || '');
                if (selectedDate && dateString === selectedDate) {
                    dayClass += ' se-selected';
                }

                const allowDayTap = !isPast || shouldUseMobileCalendarDayPanel();
                const clickHandler = allowDayTap
                    ? `onclick="handleDateCellClick(event, '${dateString}')"`
                    : '';
                const labelHtml = holidayLabel ? `<span class="day-label">${holidayLabel}</span>` : '';
                calendarHTML += `
                    <div class="${dayClass}" data-date="${dateString}" ${clickHandler}>
                        <div class="day-number">
                            <span class="day-number-main">${date.getDate()}</span>
                            ${labelHtml}
                        </div>
                        <div class="schedule-list" style="position:relative; z-index:1;">
                            ${getScheduleHTML(dateString)}
                        </div>
                    </div>
                `;

                currentWeek.setDate(currentWeek.getDate() + 1);
            }
            calendarHTML += '</div>';
        }

        // 曜日ヘッダーはHTMLで既に定義されているため、日付部分のみ更新
        console.log('📅 生成されたカレンダーHTML:', calendarHTML);

        calendarGrid.innerHTML = calendarHTML;

        console.log('📅 カレンダー描画完了');
    }

    /**
     * スケジュール読み込み（各ページで実装）
     */
    function loadSchedules() {
        // 各ページで個別に実装
        if (typeof window.loadSchedulesCallback === 'function') {
            window.loadSchedulesCallback();
        } else {
            // デフォルトの動作：カレンダーのみ再描画
            renderCalendar();
        }
    }

    /**
     * 前月
     */
    function previousMonth() {
        window.currentDate.setMonth(window.currentDate.getMonth() - 1);
        loadSchedules();
    }

    /**
     * 次月
     */
    function nextMonth() {
        window.currentDate.setMonth(window.currentDate.getMonth() + 1);
        loadSchedules();
    }

    /**
     * 前月・次月ボタンにクリックを接続
     */
    function setupCalendarMonthNav() {
        const prevBtn = document.getElementById('calendar-prev-btn');
        const nextBtn = document.getElementById('calendar-next-btn');
        if (prevBtn && !prevBtn.dataset.navBound) {
            prevBtn.dataset.navBound = '1';
            prevBtn.addEventListener('click', function(e) {
                e.preventDefault();
                if (typeof window.previousMonth === 'function') {
                    window.previousMonth();
                }
            });
        }
        if (nextBtn && !nextBtn.dataset.navBound) {
            nextBtn.dataset.navBound = '1';
            nextBtn.addEventListener('click', function(e) {
                e.preventDefault();
                if (typeof window.nextMonth === 'function') {
                    window.nextMonth();
                }
            });
        }
    }

    /**
     * モバイル：横スワイプで月移動
     * 左スワイプ = 次月、右スワイプ = 前月
     */
    function setupCalendarSwipe() {
        const calendarContainer = document.querySelector('.calendar-container');
        if (!calendarContainer) return;

        let touchStartX = 0;
        let touchEndX = 0;
        const minSwipeDistance = 50; // 最小スワイプ距離（px）

        // タッチ開始
        calendarContainer.addEventListener('touchstart', function(e) {
            touchStartX = e.changedTouches[0].screenX;
        }, { passive: true });

        // タッチ終了
        calendarContainer.addEventListener('touchend', function(e) {
            touchEndX = e.changedTouches[0].screenX;
            const swipeDistance = touchEndX - touchStartX;

            // 最小距離を超えた場合のみ処理
            if (Math.abs(swipeDistance) > minSwipeDistance) {
                if (swipeDistance > 0) {
                    // 右スワイプ = 前月
                    if (typeof window.previousMonth === 'function') {
                        window.previousMonth();
                    }
                } else {
                    // 左スワイプ = 次月
                    if (typeof window.nextMonth === 'function') {
                        window.nextMonth();
                    }
                }
            }
        }, { passive: true });
    }

    /**
     * 日付セルクリック（カード以外をタップしたときは新規登録、カード上は詳細へ）
     * インライン onclick から呼ぶため event と dateString を受け取る
     */
    function handleDateCellClick(event, dateString) {
        if (!dateString) return;
        // カードまたは「+N件」をクリックした場合は何もしない（カード側の処理に任せる）
        if (event && event.target && (event.target.closest('.schedule-card') || event.target.closest('.schedule-more'))) {
            return;
        }
        if (shouldUseMobileCalendarDayPanel()) {
            if (typeof window.AidUniteScheduleMonthView !== 'undefined'
                && typeof window.AidUniteScheduleMonthView.selectCalendarDay === 'function') {
                window.AidUniteScheduleMonthView.selectCalendarDay(dateString);
            }
            return;
        }
        // 過去の日付は選択不可
        const selectedDate = new Date(dateString + 'T00:00:00');
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        if (selectedDate < today) {
            return;
        }
        showNewSchedulePopup(dateString);
    }

    /**
     * 日付選択（後方互換・ページ側で selectDate を上書きしている場合用）
     */
    function selectDate(dateString) {
        console.log('日付選択:', dateString);
        const selectedDate = new Date(dateString + 'T00:00:00');
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        if (selectedDate < today) {
            console.log('過去の日付は選択できません:', dateString);
            return;
        }
        showNewSchedulePopup(dateString);
    }

    /**
     * 新規登録ポップアップを表示
     */
    function showNewSchedulePopup(dateString) {
        if (typeof AidUniteScheduleQuickModal !== 'undefined' && AidUniteScheduleQuickModal.canEdit()) {
            AidUniteScheduleQuickModal.openRegister(dateString);
            return;
        }
        const viewCfg = window.AIDUNITE_SCHEDULE_VIEW || {};
        if (viewCfg.canEdit === false || viewCfg.canEdit === '0' || viewCfg.canEdit === 0) {
            return;
        }
        goToScheduleEdit(dateString);
    }

    /**
     * 新規登録ポップアップを閉じる
     */
    function closeNewSchedulePopup() {
        const popup = document.getElementById('new-schedule-popup');
        if (popup) {
            popup.classList.remove('show');
            setTimeout(() => {
                popup.remove();
            }, 300);
        }
    }

    /**
     * スケジュール編集ページに遷移
     */
    function goToScheduleEdit(dateString) {
        if (typeof AidUniteScheduleQuickModal !== 'undefined' && typeof AidUniteScheduleQuickModal.openRegister === 'function') {
            AidUniteScheduleQuickModal.openRegister(dateString);
            return;
        }
        window.location.href = window.location.origin + '/schedule-management/?open_register=' + encodeURIComponent(dateString);
    }

    /**
     * 全スケジュール表示（必要に応じて各ページで上書き可能）
     */
    function showAllSchedules(dateString) {
        if (typeof window.showAllSchedules === 'function' && window.showAllSchedules !== showAllSchedules) {
            // ページ側で定義されていればそちらを優先
            return window.showAllSchedules(dateString);
        }
        console.log('全スケジュール表示（デフォルト）:', dateString);
    }

    /**
     * スケジュール詳細表示（共通：ポップアップに統一）
     */
    function showScheduleDetail(scheduleId) {
        try {
            // 既存の一覧モーダルが開いていたら先に閉じる/除去（位置ズレ・干渉対策）
            const openListModals = document.querySelectorAll('.schedule-detail-modal');
            if (openListModals && openListModals.length) {
                openListModals.forEach(m => m.parentNode && m.parentNode.removeChild(m));
            }
            if (typeof AidUniteScheduleModal !== 'undefined') {
                AidUniteScheduleModal.showDetail(scheduleId, { mode: 'popup' });
                return;
            }
            // 後方互換: ページ側で関数があれば呼ぶ
            if (typeof window.showScheduleDetail === 'function' && window.showScheduleDetail !== showScheduleDetail) {
                return window.showScheduleDetail(scheduleId);
            }
            console.error('AidUniteScheduleModal が利用できず、ページ固有の showScheduleDetail も見つかりません');
        } catch (e) {
            console.error('showScheduleDetail 実行時エラー:', e);
        }
    }

    // スワイプ機能の初期化（DOMContentLoaded時または即座に実行）
    function initializeSwipe() {
        setupCalendarMonthNav();
        // カレンダーコンテナが存在する場合のみ初期化
        const calendarContainer = document.querySelector('.calendar-container');
        if (calendarContainer && !calendarContainer.hasAttribute('data-swipe-initialized')) {
            setupCalendarSwipe();
            calendarContainer.setAttribute('data-swipe-initialized', 'true');
        }
    }

    // グローバルに公開（後方互換性のため）
    // renderCalendarをラップしてスワイプ機能を初期化
    const originalRenderCalendar = renderCalendar;
    window.renderCalendar = function() {
        const result = originalRenderCalendar.apply(this, arguments);
        // 描画後にスワイプ機能を初期化（遅延実行で確実に要素が存在するように）
        setTimeout(initializeSwipe, 100);
        return result;
    };
    window.previousMonth = previousMonth;
    window.nextMonth = nextMonth;
    window.handleDateCellClick = handleDateCellClick;
    window.selectDate = selectDate;
    window.showNewSchedulePopup = showNewSchedulePopup;
    window.closeNewSchedulePopup = closeNewSchedulePopup;
    window.goToScheduleEdit = goToScheduleEdit;
    window.showAllSchedules = showAllSchedules;
    window.showScheduleDetail = showScheduleDetail;
    window.generateScheduleCard = generateScheduleCard;
    window.AidUniteBuildCalendarScheduleCard = buildCalendarScheduleCard;
    window.AidUniteBuildScheduleCompactLines = buildScheduleCompactLines;
    window.AidUniteBuildScheduleListRowData = buildScheduleListRowData;
    window.AidUniteGetTeamGenderClass = getTeamGenderClass;
    window.AidUniteIsScheduleMobileViewport = isScheduleMobileViewport;
    window.AidUniteShouldUseMobileCalendarDayPanel = shouldUseMobileCalendarDayPanel;
    window.AidUniteUpdateScheduleMonthTitle = updateScheduleMonthTitle;
    window.AidUniteResolveCalendarCardPresentation = resolveCalendarCardPresentation;
    window.AidUniteCalendarEscapeHtml = escapeCalendarHtml;
    window.getScheduleHTML = getScheduleHTML;
    window.isTodayDate = isTodayDate;
    window.isJapaneseHoliday = isJapaneseHoliday;
    window.getJapaneseHolidayOrEventLabel = getJapaneseHolidayOrEventLabel;
    window.shortenScheduleTitle = shortenScheduleTitle;

    // カードクリックを委譲（1回で詳細が開くようにする）
    document.body.addEventListener('click', function scheduleCardClick(e) {
        const card = e.target.closest('#calendar-grid .schedule-card[data-schedule-id]');
        if (!card) return;
        const id = card.getAttribute('data-schedule-id');
        if (!id) return;
        e.preventDefault();
        e.stopPropagation();
        let schedule = null;
        if (window.schedules) {
            for (const dateKey in window.schedules) {
                const list = window.schedules[dateKey];
                if (!Array.isArray(list)) continue;
                schedule = list.find((s) => String(s.id || s.schedule_id) === String(id));
                if (schedule) break;
            }
        }
        if (typeof AidUniteScheduleQuickModal !== 'undefined') {
            if (schedule) {
                AidUniteScheduleQuickModal.openCardActions(schedule);
            } else if (typeof window.showScheduleDetail === 'function') {
                window.showScheduleDetail(id);
            }
            return;
        }
        if (typeof window.showScheduleDetail === 'function') {
            window.showScheduleDetail(id);
        } else if (typeof AidUniteScheduleModal !== 'undefined') {
            AidUniteScheduleModal.showDetail(id, { mode: 'popup' });
        }
    }, true);

    document.body.addEventListener('keydown', function scheduleCardKeydown(e) {
        const card = e.target.closest('#calendar-grid .schedule-card[data-schedule-id]');
        if (!card || (e.key !== 'Enter' && e.key !== ' ')) return;
        e.preventDefault();
        const id = card.getAttribute('data-schedule-id');
        if (!id) return;
        card.click();
    }, true);

    // DOMContentLoaded時に初期化
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeSwipe);
    } else {
        // 既に読み込み完了している場合は即座に実行
        initializeSwipe();
    }

})();
