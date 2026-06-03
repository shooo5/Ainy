/**
 * マイページ v2 ダッシュボード
 */
(function () {
    'use strict';

    var cfg = window.aiduniteMypageDashboard || {};
    var restNonce = cfg.restNonce || '';
    var scheduleUrl = cfg.scheduleUrl || '/schedule-list';
    var matchBoardUrl = cfg.matchBoardUrl || '/match-board-own';
    var communicationUrl = cfg.communicationUrl || '/communication';
    var tournamentsUrl = cfg.tournamentsUrl || '/tournaments/';
    var activationMissionUi = cfg.activationMissionUi === '1';
    var activationStage = cfg.activationStage || '';
    var activationChatUnlocked = cfg.activationChatUnlocked !== '0';
    var recruitEditUrl = cfg.recruitEditUrl || '/schedule-edit/';
    var activationLockMessage = cfg.activationLockMessage || '初回の試合が成立すると利用できます';

    var scheduleEmptyMessages = {
        affirmations: [
            '今日は予定がありません',
            '今日は特別な予定は入っていません',
            '今日はスケジュールが空いています',
            '今日は予定のない日です',
            '今日は落ち着いた一日になりそうです',
        ],
        affirmationsDetail: [
            '少し落ち着いた一日になりそうです',
            'ゆとりのある時間が取れそうですね',
            '今はひと息つけるタイミングです',
            '次に向けて整える時間にできます',
            '無理なく過ごしてください',
        ],
        suggestions: [
            'そろそろ次の予定を考えてみてもいいかもしれません',
            '今のうちにスケジュールを確認しておくと安心です',
            '次の練習や試合をゆっくり検討できるタイミングです',
            '余裕がある今のうちに予定を追加しておくのも一つです',
            'この時間を使って次の一歩を考えてみませんか',
        ],
        ctaLabels: ['スケジュールの登録', '練習試合の募集', '予定を入れる'],
    };

    var actionEmptyMessages = {
        affirmations: [
            '今対応が必要なことはありません',
            '現在、対応が必要な項目はありません',
            'やることはひと通り完了しています',
            '今すぐ対応するものはありません',
            '対応待ちの項目はありません',
        ],
        affirmationsDetail: [
            'すべて順調です',
            '落ち着いた状態です',
            'いい流れです',
            '状況は安定しています',
            'このまま進められそうです',
        ],
        suggestions: [
            '次の予定を確認しておくと安心です',
            '新しい連絡や予定がないか軽く見ておくのもおすすめです',
            '必要になったらいつでも対応できる状態です',
            'このまま様子を見ても問題ありません',
            '余裕がある今の状態を保っていきましょう',
        ],
        ctaLabels: ['スケジュールの登録', '練習試合の募集', '予定を入れる'],
    };

    var tournamentEmptyMessages = {
        affirmations: ['大会への応募・参加はまだありません'],
        affirmationsDetail: ['参加できる大会をチェックしてみましょう'],
        suggestions: ['チームに合う大会を探して、新しい挑戦のきっかけにしてみませんか'],
        ctaLabels: ['大会一覧を見る'],
    };

    var state = {
        focusSlides: [],
        focusIndex: 0,
        todaySchedules: [],
        upcomingByDate: [],
        actionItems: [],
        chatUnread: 0,
    };

    function escapeHtml(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function formatDateLabel(dateStr) {
        if (typeof AidUniteDateUtils !== 'undefined' && AidUniteDateUtils.formatDateWithWeekday) {
            return AidUniteDateUtils.formatDateWithWeekday(dateStr);
        }
        var d = new Date(dateStr + 'T00:00:00');
        var w = ['日', '月', '火', '水', '木', '金', '土'][d.getDay()];
        return (d.getMonth() + 1) + '/' + d.getDate() + '（' + w + '）';
    }

    function formatDeadline(deadline) {
        if (!deadline) return '';
        var ts = new Date(deadline.replace(' ', 'T')).getTime();
        if (isNaN(ts)) return '';
        var d = new Date(ts);
        return '期限：' + (d.getMonth() + 1) + '/' + d.getDate() + ' ' +
            String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
    }

    function scheduleTypeLabel(type) {
        var map = {
            practice: '練習',
            official_match: '公式試合',
            practice_match: '練習試合',
            joint_practice: '合同練習',
            meeting: 'ミーティング',
            rest: '休み',
            event: 'イベント',
            tbd: '未定',
        };
        return map[type] || type || 'スケジュール';
    }

    function placeLabel(place) {
        var map = { home: 'ホーム', away: 'アウェイ', both: 'どちらでも可', tbd: '未定' };
        return map[place] || place || '';
    }

    function pickRandom(arr) {
        return arr[Math.floor(Math.random() * arr.length)];
    }

    function ensurePeriod(text) {
        if (!text) return '';
        return /[。．!?]$/.test(text) ? text : text + '。';
    }

    function buildMissionIconHtml(basename, size) {
        if (typeof AidUniteThemeIcons !== 'undefined') {
            return AidUniteThemeIcons.html(basename, size || 24, { block: true });
        }
        return '';
    }

    function buildMissionBasketballIconHtml(size) {
        return buildMissionIconHtml('basketball', size);
    }

    function buildMissionKickerHtml(text, iconMode) {
        if (iconMode === 'basketball-both') {
            return '<span class="mypage-v2-todo-title mypage-v2-mission-card__kicker">' +
                '<span class="mypage-v2-mission-card__kicker-row">' +
                buildMissionBasketballIconHtml(24) +
                '<span class="mypage-v2-mission-card__kicker-text">' + escapeHtml(text) + '</span>' +
                buildMissionBasketballIconHtml(24) +
                '</span></span>';
        }
        if (iconMode === 'check-circle') {
            return '<span class="mypage-v2-todo-title mypage-v2-mission-card__kicker">' +
                '<span class="mypage-v2-mission-card__kicker-row mypage-v2-mission-card__kicker-row--check">' +
                buildMissionIconHtml('check_circle', 24) +
                '<span class="mypage-v2-mission-card__kicker-text">' + escapeHtml(text) + '</span>' +
                '</span></span>';
        }
        return '<span class="mypage-v2-todo-title mypage-v2-mission-card__kicker">' + escapeHtml(text) + '</span>';
    }

    /**
     * 空状態プロモ（affirmations + detail/suggestions マージから1件、suggestions なら CTA）
     */
    function buildPromoHtml(pool, ctaUrl) {
        var main = ensurePeriod(pickRandom(pool.affirmations));
        var merged = (pool.affirmationsDetail || []).concat(pool.suggestions || []);
        var secondRaw = pickRandom(merged);
        var isSuggestion = (pool.suggestions || []).indexOf(secondRaw) !== -1;
        var second = ensurePeriod(secondRaw);
        var html = '<div class="mypage-v2-promo">';
        html += '<p class="mypage-v2-promo-main">' + escapeHtml(main) + '</p>';
        html += '<p class="mypage-v2-promo-sub">' + escapeHtml(second) + '</p>';
        if (isSuggestion && pool.ctaLabels && pool.ctaLabels.length) {
            html += '<div class="mypage-v2-promo-cta-row">';
            html += '<a href="' + escapeHtml(ctaUrl) + '" class="mypage-v2-empty-cta">' + escapeHtml(pickRandom(pool.ctaLabels)) + '</a>';
            html += '</div>';
        }
        html += '</div>';
        return html;
    }

    function bindScheduleOpen(el) {
        if (!el) return;
        var open = function () {
            var sid = el.getAttribute('data-schedule-id');
            if (sid && typeof AidUniteScheduleModal !== 'undefined') {
                AidUniteScheduleModal.showDetail(sid, { mode: 'popup' });
            }
        };
        el.addEventListener('click', open);
        el.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                open();
            }
        });
    }

    function buildTodayScheduleMeta(item) {
        var time = (item.start_time || item.start || '') && (item.end_time || item.end || '')
            ? (item.start_time || item.start) + '〜' + (item.end_time || item.end)
            : (item.start_time || item.start || '');
        var place = placeLabel(item.place || item.venue_name || '');
        var lines = [];
        if (time) lines.push(time);
        if (place) lines.push(place);
        return lines;
    }

    function renderTodaySchedules(schedules) {
        var el = document.getElementById('mypage-today-schedules');
        if (!el) return;

        if (!schedules.length) {
            el.innerHTML = buildPromoHtml(scheduleEmptyMessages, scheduleUrl);
            return;
        }

        if (schedules.length === 1) {
            var one = schedules[0];
            var sid = String(one.ID || one.id || '');
            var lines = buildTodayScheduleMeta(one);
            var html = '<div class="mypage-v2-today-block" tabindex="0" role="button" data-schedule-id="' + escapeHtml(sid) + '">';
            html += '<strong>' + escapeHtml(scheduleTypeLabel(one.type)) + '</strong>';
            lines.forEach(function (line) {
                html += '<p>' + escapeHtml(line) + '</p>';
            });
            html += '</div>';
            el.innerHTML = html;
            bindScheduleOpen(el.querySelector('.mypage-v2-today-block'));
            return;
        }

        var grid = '<div class="mypage-v2-today-grid">';
        schedules.forEach(function (item) {
            var id = String(item.ID || item.id || '');
            var meta = buildTodayScheduleMeta(item);
            grid += '<div class="card card--status mypage-v2-today-card" tabindex="0" role="button" data-schedule-id="' + escapeHtml(id) + '">';
            grid += '<div class="card-body">';
            grid += '<div class="card-title mypage-v2-today-card-title">' + escapeHtml(scheduleTypeLabel(item.type)) + '</div>';
            meta.forEach(function (line) {
                grid += '<p class="mypage-v2-today-card-meta">' + escapeHtml(line) + '</p>';
            });
            grid += '</div></div>';
        });
        grid += '</div>';
        el.innerHTML = grid;
        el.querySelectorAll('.mypage-v2-today-card').forEach(bindScheduleOpen);
    }

    function buildTodoItemRowHtml(item) {
        var html = '<span class="mypage-v2-todo-item-main">';
        html += '<span class="mypage-v2-todo-title">' + escapeHtml(item.title) + '</span>';
        if (item.description) {
            html += '<span class="mypage-v2-todo-desc">' + escapeHtml(item.description) + '</span>';
        }
        html += '</span>';
        if (item.deadline) {
            html += '<span class="mypage-v2-todo-deadline">' + escapeHtml(formatDeadline(item.deadline)) + '</span>';
        }
        html += '<span class="mypage-v2-todo-chevron" aria-hidden="true">' + (typeof AidUniteThemeIcons !== 'undefined' ? AidUniteThemeIcons.html('chevron_right', 18) : '') + '</span>';
        return html;
    }

    function scrollToSection(selector, tabKey) {
        var el = document.querySelector(selector);
        if (!el) return;
        var tabs = document.querySelectorAll('.mypage-v2-tab');
        tabs.forEach(function (t) {
            var active = tabKey && t.getAttribute('data-tab') === tabKey;
            if (!tabKey) {
                active = t.getAttribute('data-scroll') === selector;
            }
            t.classList.toggle('active', active);
            t.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        el.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function initTabs() {
        var tabs = document.querySelectorAll('.mypage-v2-tab');
        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                var target = tab.getAttribute('data-scroll');
                if (target) {
                    scrollToSection(target, tab.getAttribute('data-tab'));
                }
            });
        });

        var focusCard = document.getElementById('mypage-focus-card');
        if (focusCard) {
            focusCard.addEventListener('click', function (e) {
                var link = e.target.closest('a[href="#mypage-section-todo"]');
                if (link) {
                    e.preventDefault();
                    scrollToSection('#mypage-section-todo', 'todo');
                }
            });
        }
    }

    function initTournamentEmpty() {
        var grid = document.getElementById('mypage-event-grid');
        var emptyEl = document.getElementById('mypage-event-empty');
        if (!grid || grid.getAttribute('data-has-cards') === '1') {
            return;
        }
        var promo = buildPromoHtml(tournamentEmptyMessages, tournamentsUrl);
        if (emptyEl) {
            emptyEl.outerHTML = promo;
        } else if (!grid.querySelector('.mypage-v2-mini-card, .mypage-v2-tournament-block')) {
            grid.innerHTML = promo;
        }
    }


    function renderFocusSlider() {
        var card = document.getElementById('mypage-focus-card');
        var dots = document.getElementById('mypage-focus-dots');
        var prev = document.getElementById('mypage-focus-prev');
        var next = document.getElementById('mypage-focus-next');
        if (!card || !dots) return;

        var slides = state.focusSlides;
        if (!slides.length) {
            var promo = buildPromoHtml(scheduleEmptyMessages, scheduleUrl);
            var tmp = document.createElement('div');
            tmp.innerHTML = promo;
            var mainEl = tmp.querySelector('.mypage-v2-promo-main');
            var subEl = tmp.querySelector('.mypage-v2-promo-sub');
            var ctaEl = tmp.querySelector('.mypage-v2-empty-cta');
            slides = [{
                tag: 'TODAY',
                title: mainEl ? mainEl.textContent.replace(/。$/, '') : '今日は予定がありません',
                lines: subEl ? [subEl.textContent] : [],
                cta: ctaEl
                    ? { text: ctaEl.textContent, url: ctaEl.getAttribute('href') || scheduleUrl }
                    : { text: '＋ 試合予定を登録', url: scheduleUrl },
            }];
        }

        state.focusSlides = slides;
        if (state.focusIndex >= slides.length) state.focusIndex = 0;

        var s = slides[state.focusIndex];
        var labelClass = 'mypage-v2-focus-label' + (s.tag === 'ACTION' ? ' mypage-v2-focus-label--action' : '');
        var html = '<small class="' + labelClass + '">' + escapeHtml(s.tag) + '</small>';
        html += '<h2>' + escapeHtml(s.title) + '</h2>';
        (s.lines || []).forEach(function (line) {
            html += '<p>' + escapeHtml(line) + '</p>';
        });
        if (s.cta && s.cta.url) {
            html += '<div class="mypage-v2-promo-cta-row">';
            html += '<a href="' + escapeHtml(s.cta.url) + '" class="mypage-v2-focus-cta">' + escapeHtml(s.cta.text) + '</a>';
            html += '</div>';
        }
        card.innerHTML = html;

        dots.innerHTML = '';
        slides.forEach(function (_, i) {
            var dot = document.createElement('button');
            dot.type = 'button';
            dot.className = i === state.focusIndex ? 'active' : '';
            dot.setAttribute('aria-label', 'カード ' + (i + 1));
            dot.addEventListener('click', function () {
                state.focusIndex = i;
                renderFocusSlider();
            });
            dots.appendChild(dot);
        });

        if (prev) prev.disabled = slides.length <= 1;
        if (next) next.disabled = slides.length <= 1;
    }

    function initFocusArrows() {
        var prev = document.getElementById('mypage-focus-prev');
        var next = document.getElementById('mypage-focus-next');
        if (prev) {
            prev.addEventListener('click', function () {
                if (state.focusSlides.length <= 1) return;
                state.focusIndex = (state.focusIndex - 1 + state.focusSlides.length) % state.focusSlides.length;
                renderFocusSlider();
            });
        }
        if (next) {
            next.addEventListener('click', function () {
                if (state.focusSlides.length <= 1) return;
                state.focusIndex = (state.focusIndex + 1) % state.focusSlides.length;
                renderFocusSlider();
            });
        }
    }

    function buildFocusSlides(todaySchedules, actionItems) {
        var slides = [];

        todaySchedules.forEach(function (item, idx) {
            if (idx > 2) return;
            var time = (item.start_time || item.start || '') && (item.end_time || item.end || '')
                ? (item.start_time || item.start) + '〜' + (item.end_time || item.end)
                : (item.start_time || item.start || '');
            slides.push({
                tag: 'TODAY',
                title: scheduleTypeLabel(item.type) + (time ? ' ' + time : ''),
                lines: [placeLabel(item.place || item.venue_name || '')].filter(Boolean),
                cta: {
                    text: '予定を見る',
                    url: scheduleUrl + (item.ID || item.id ? '?highlight=' + (item.ID || item.id) : ''),
                },
            });
        });

        actionItems.slice(0, 3).forEach(function (item) {
            var lines = [item.description || ''].filter(Boolean);
            if (item.deadline) lines.push(formatDeadline(item.deadline));
            slides.push({
                tag: 'ACTION',
                title: item.title || 'やること',
                lines: lines,
                cta: item.link_url
                    ? { text: '対応する', url: item.link_url }
                    : { text: 'やることへ', url: '#mypage-section-todo' },
            });
        });

        if (!slides.length) {
            if (!activationMissionUi) {
                var emptyPromo = buildPromoHtml(scheduleEmptyMessages, scheduleUrl);
                var wrap = document.createElement('div');
                wrap.innerHTML = emptyPromo;
                var m = wrap.querySelector('.mypage-v2-promo-main');
                var s = wrap.querySelector('.mypage-v2-promo-sub');
                var c = wrap.querySelector('.mypage-v2-empty-cta');
                slides.push({
                    tag: 'TODAY',
                    title: m ? m.textContent.replace(/。$/, '') : '最初の試合予定を登録',
                    lines: s ? [s.textContent] : ['Ainyは、日程を登録すると練習試合の相手候補が見つかります'],
                    cta: c
                        ? { text: c.textContent, url: c.getAttribute('href') || scheduleUrl }
                        : { text: '＋ 試合予定を登録', url: scheduleUrl },
                });
            }
        }

        state.focusSlides = slides;
        state.focusIndex = 0;
        renderFocusSlider();
    }

    function renderUpcomingGrid(upcomingDays) {
        var grid = document.getElementById('mypage-upcoming-grid');
        if (!grid) return;

        if (!upcomingDays.length) {
            grid.innerHTML = '<div class="mypage-v2-promo" style="grid-column:1/-1;">' +
                '<p class="mypage-v2-promo-main">直近の予定はございません。</p>' +
                '<p class="mypage-v2-promo-sub">まだ試合予定がありません。まずは1件登録すると、マッチ候補が表示されます。</p>' +
                '<div class="mypage-v2-promo-cta-row"><a href="' + escapeHtml(scheduleUrl) + '" class="mypage-v2-empty-cta">＋ 試合予定を登録</a></div>' +
                '</div>';
            return;
        }

        var html = '';
        upcomingDays.slice(0, 4).forEach(function (day) {
            var first = day.schedules[0];
            var time = (first.start_time || first.start || '') && (first.end_time || first.end || '')
                ? (first.start_time || first.start) + '〜' + (first.end_time || first.end)
                : (first.start_time || first.start || '');
            html += '<div class="mypage-v2-schedule-card" tabindex="0" role="button" data-schedule-id="' + escapeHtml(String(first.ID || first.id || '')) + '" data-date="' + escapeHtml(day.date) + '">';
            html += '<strong>' + escapeHtml(formatDateLabel(day.date)) + '</strong>';
            html += '<p>' + escapeHtml(time) + '</p>';
            html += '<b>' + escapeHtml(scheduleTypeLabel(first.type)) + '</b>';
            html += '</div>';
        });
        grid.innerHTML = html;

        grid.querySelectorAll('.mypage-v2-schedule-card').forEach(function (card) {
            card.addEventListener('click', function () {
                var sid = card.getAttribute('data-schedule-id');
                if (sid && typeof AidUniteScheduleModal !== 'undefined') {
                    AidUniteScheduleModal.showDetail(sid, { mode: 'popup' });
                }
            });
            card.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    card.click();
                }
            });
        });
    }

    function loadSchedules() {
        var today = new Date();
        var startStr = typeof AidUniteDateUtils !== 'undefined' && AidUniteDateUtils.formatDateLocal
            ? AidUniteDateUtils.formatDateLocal(today)
            : today.toISOString().slice(0, 10);
        var endDate = new Date(today);
        endDate.setDate(today.getDate() + 6);
        var endStr = typeof AidUniteDateUtils !== 'undefined' && AidUniteDateUtils.formatDateLocal
            ? AidUniteDateUtils.formatDateLocal(endDate)
            : endDate.toISOString().slice(0, 10);

        var url = '/wp-json/aidunite/v1/get-user-schedules?' + new URLSearchParams({ start: startStr, end: endStr });
        return fetch(url, {
            method: 'GET',
            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': restNonce },
        })
            .then(function (r) { return r.ok ? r.json() : Promise.reject(); })
            .then(function (payload) {
                var list = (payload && payload.success && Array.isArray(payload.data))
                    ? payload.data
                    : (Array.isArray(payload) ? payload : []);
                var byDate = {};
                list.forEach(function (s) {
                    var raw = s.date || s.schedule_date || s.start_date;
                    var k = raw ? String(raw).trim().substring(0, 10) : '';
                    if (k && /^\d{4}-\d{2}-\d{2}$/.test(k)) {
                        if (!byDate[k]) byDate[k] = [];
                        byDate[k].push(s);
                    }
                });

                state.todaySchedules = byDate[startStr] || [];
                var upcoming = [];
                for (var i = 1; i <= 6; i++) {
                    var d = new Date(today);
                    d.setDate(today.getDate() + i);
                    var key = typeof AidUniteDateUtils !== 'undefined' && AidUniteDateUtils.formatDateLocal
                        ? AidUniteDateUtils.formatDateLocal(d)
                        : d.toISOString().slice(0, 10);
                    if (byDate[key] && byDate[key].length) {
                        upcoming.push({ date: key, schedules: byDate[key] });
                    }
                }
                state.upcomingByDate = upcoming;
                renderTodaySchedules(state.todaySchedules);
                renderUpcomingGrid(upcoming);
                buildFocusSlides(state.todaySchedules, state.actionItems);
            })
            .catch(function () {
                state.todaySchedules = [];
                state.upcomingByDate = [];
                renderTodaySchedules([]);
                renderUpcomingGrid([]);
                buildFocusSlides([], state.actionItems);
            });
    }

    function buildMissionStage1Html() {
        return '<div class="card card--status mypage-v2-todo-item mypage-v2-mission-card">' +
            '<div class="card-body mypage-v2-mission-card__body">' +
            '<span class="mypage-v2-todo-item-main">' +
            buildMissionKickerHtml('最初の試合募集を出しましょう', 'basketball-both') +
            '<div class="mypage-v2-mission-card__lead">' +
            '<p><br>難しい設定は必要ありません。<br>まずは日時を決めるところから始めましょう。</p>' +
            '</div>' +
            '</span>' +
            '</div>' +
            '<div class="mypage-v2-mission-card__actions">' +
            '<a href="' + escapeHtml(recruitEditUrl) + '" class="btn btn-primary">＋ 試合募集を出す</a>' +
            '</div>' +
            '</div>';
    }

    function buildMissionStage2Html() {
        var boardUrl = matchBoardUrl + (matchBoardUrl.indexOf('?') >= 0 ? '&' : '?') + 'market_tab=my';
        return '<div class="card card--status mypage-v2-todo-item mypage-v2-mission-card">' +
            '<div class="card-body mypage-v2-mission-card__body">' +
            '<span class="mypage-v2-todo-item-main">' +
            buildMissionKickerHtml('申請した内容を確認しましょう。', 'check-circle') +
            '<div class="mypage-v2-mission-card__lead">' +
            '<p>試合一覧の申請状況から確認できます。</p>' +
            '</div>' +
            '</span>' +
            '</div>' +
            '<div class="mypage-v2-mission-card__actions">' +
            '<a href="' + escapeHtml(boardUrl) + '" class="btn btn-primary">試合一覧</a>' +
            '</div>' +
            '</div>';
    }

    function buildMissionTodoHtml() {
        if (activationStage === 'recruit_pending') {
            return buildMissionStage1Html();
        }
        if (activationStage === 'recruit_published' || activationStage === 'first_application') {
            return buildMissionStage2Html();
        }
        return '';
    }

    function renderTodoList(items, chatUnread) {
        var list = document.getElementById('mypage-todo-list');
        if (!list) return;

        var rows = [];
        var isMission = list.getAttribute('data-mission-ui') === '1';

        if (chatUnread > 0 && activationChatUnlocked) {
            rows.push({
                type: 'chat_unread',
                title: '未読のメッセージ・通知があります',
                description: chatUnread + '件',
                link_url: communicationUrl,
                urgency: 'soon',
            });
        }

        (items || []).forEach(function (item) {
            rows.push(item);
        });

        var html = '';
        if (isMission) {
            html = buildMissionTodoHtml();
            if (rows.length) {
                html += '<div class="mypage-v2-mission-actions-wrap">';
            }
        }

        if (!rows.length && !isMission) {
            list.innerHTML = buildPromoHtml(actionEmptyMessages, scheduleUrl);
            return;
        }

        if (!rows.length && isMission) {
            list.innerHTML = html;
            return;
        }

        rows.forEach(function (item) {
            var urgentClass = item.urgency_level === 'URGENT' || item.is_urgent
                ? ' mypage-v2-todo-item--urgent'
                : (item.urgency_level === 'SOON' ? ' mypage-v2-todo-item--soon' : '');
            var href = item.link_url || '#';
            var hasInlineActions = item.actions && item.actions.length && item.type !== 'chat_unread' && item.type !== 'payment' && item.type !== 'match_feedback';

            if (hasInlineActions && (item.type === 'match_request' || item.type === 'attendance')) {
                html += '<div class="mypage-v2-todo-group" data-action-id="' + escapeHtml(String(item.id)) + '" data-action-type="' + escapeHtml(item.type) + '">';
                html += '<a href="' + escapeHtml(href) + '" class="card card--status mypage-v2-todo-item' + urgentClass + '">';
                html += '<div class="card-body">' + buildTodoItemRowHtml(item) + '</div>';
                html += '</a>';
                html += '<div class="mypage-v2-todo-actions">';
                item.actions.forEach(function (act) {
                    var cls = act.type === 'primary' ? 'mypage-v2-todo-btn--primary' : 'mypage-v2-todo-btn--secondary';
                    html += '<button type="button" class="mypage-v2-todo-btn ' + cls + '" data-action="' + escapeHtml(act.action) + '" data-item-id="' + escapeHtml(String(item.id)) + '" data-item-type="' + escapeHtml(item.type) + '">' + escapeHtml(act.label) + '</button>';
                });
                html += '</div></div>';
            } else {
                html += '<a href="' + escapeHtml(href) + '" class="card card--status mypage-v2-todo-item' + urgentClass + '" data-action-id="' + escapeHtml(String(item.id || '')) + '" data-action-type="' + escapeHtml(item.type || '') + '">';
                html += '<div class="card-body">' + buildTodoItemRowHtml(item) + '</div>';
                html += '</a>';
            }
        });

        if (isMission && rows.length) {
            html += '</div>';
        }

        list.innerHTML = html;

        list.querySelectorAll('.mypage-v2-todo-btn').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                handleAction(btn.getAttribute('data-action'), btn.getAttribute('data-item-id'), btn.getAttribute('data-item-type'), btn);
            });
        });
    }

    function handleAction(action, itemId, itemType, buttonEl) {
        if (itemType === 'attendance') {
            var status = action === 'attending' ? 'attending' : (action === 'not_attending' ? 'not_attending' : 'maybe');
            fetch('/wp-json/aidunite/v1/attendance', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': restNonce },
                body: JSON.stringify({ schedule_id: itemId, status: status }),
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.success) {
                        if (typeof showToastNotification !== 'undefined') {
                            showToastNotification('出欠回答を送信しました', 'success');
                        }
                        removeTodoGroup(buttonEl);
                        loadActions();
                    }
                });
            return;
        }

        if (itemType === 'match_request') {
            fetch('/wp-json/aidunite/v1/update-match-status', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': restNonce },
                body: JSON.stringify({
                    match_id: itemId,
                    action: action === 'approve' ? 'approve' : 'reject',
                }),
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.success) {
                        if (typeof showToastNotification !== 'undefined') {
                            showToastNotification(action === 'approve' ? 'マッチ申請を承認しました' : 'マッチ申請を拒否しました', 'success');
                        }
                        removeTodoGroup(buttonEl);
                        loadActions().then(function () {
                            if (action === 'approve' && data.show_onboarding_bot_chat_modal
                                && typeof window.aiduniteShowOnboardingBotChatModal === 'function') {
                                window.aiduniteShowOnboardingBotChatModal(data.chat_url || '');
                            }
                        });
                    }
                });
        }
    }

    function removeTodoGroup(buttonEl) {
        var group = buttonEl.closest('.mypage-v2-todo-group');
        if (group) {
            group.style.opacity = '0';
            setTimeout(function () { group.remove(); }, 280);
        }
    }

    function loadActions() {
        return fetch('/wp-json/aidunite/v1/get-action-required-items', {
            method: 'GET',
            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': restNonce },
        })
            .then(function (r) { return r.ok ? r.json() : { items: [] }; })
            .then(function (data) {
                state.actionItems = (data && data.items) ? data.items : [];
                renderTodoList(state.actionItems, state.chatUnread);
                buildFocusSlides(state.todaySchedules, state.actionItems);
                updateQuickBadges(state.actionItems);
            })
            .catch(function () {
                state.actionItems = [];
                renderTodoList([], state.chatUnread);
                buildFocusSlides(state.todaySchedules, []);
            });
    }

    function loadChatUnread() {
        return fetch('/wp-json/aidunite/v1/timeline?team_id=0&per_page=1', {
            method: 'GET',
            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': restNonce },
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                state.chatUnread = (data && typeof data.total_unread !== 'undefined') ? parseInt(data.total_unread, 10) : 0;
                renderTodoList(state.actionItems, state.chatUnread);
                updateQuickBadges(state.actionItems);
            })
            .catch(function () {
                state.chatUnread = 0;
            });
    }

    function updateQuickBadges(actionItems) {
        var scheduleEl = document.getElementById('mypage-v2-quick-schedule');
        var matchEl = document.getElementById('mypage-v2-quick-match');
        var chatEl = document.getElementById('mypage-v2-quick-chat');

        var att = actionItems.filter(function (i) { return i.type === 'attendance'; });
        var mr = actionItems.filter(function (i) { return i.type === 'match_request'; });

        [scheduleEl, matchEl, chatEl].forEach(function (el) {
            if (!el) return;
            el.classList.remove('has-badge');
        });

        if (scheduleEl && att.length) scheduleEl.classList.add('has-badge');
        if (matchEl && mr.length) matchEl.classList.add('has-badge');
        if (chatEl && state.chatUnread > 0) chatEl.classList.add('has-badge');
    }

    function getMypageUrlParams() {
        if (typeof URLSearchParams === 'undefined') {
            return null;
        }
        return new URLSearchParams(window.location.search);
    }

    var ACTIVATION_STAGE2_TOAST_RELOAD_DEBUG = false;
    var ACTIVATION_STAGE2_TOAST_SESSION_KEY = 'aidunite_stage2_toasts_debug';

    /** URL 上の ?activation=stage2 */
    function isActivationStage2Url() {
        var params = getMypageUrlParams();
        return !!(params && params.get('activation') === 'stage2');
    }

    function persistActivationStage2ToastDebug() {
        if (!ACTIVATION_STAGE2_TOAST_RELOAD_DEBUG) {
            return;
        }
        try {
            sessionStorage.setItem(ACTIVATION_STAGE2_TOAST_SESSION_KEY, '1');
        } catch (e) {
            /* ignore */
        }
    }

    /** 初回公開直後トーストを出すか（URL または検証用 sessionStorage） */
    function shouldShowActivationStage2Toasts() {
        if (isActivationStage2Url()) {
            return true;
        }
        if (!ACTIVATION_STAGE2_TOAST_RELOAD_DEBUG) {
            return false;
        }
        try {
            return sessionStorage.getItem(ACTIVATION_STAGE2_TOAST_SESSION_KEY) === '1';
        } catch (e) {
            return false;
        }
    }

    /** 初回 recruit 公開直後: ①完了 → ②次は（連続トースト） */
    function showActivationStage2Toasts() {
        var secondToastDuration = ACTIVATION_STAGE2_TOAST_RELOAD_DEBUG ? 8000 : 3500;
        var items = [
            {
                message: '試合一覧の申請状況から確認できます。',
                type: 'success',
                options: {
                    title: '無事に試合募集を公開しました！！',
                    duration: 4000,
                    iconBasename: 'check_circle',
                },
            },
            {
                message: '試合一覧を押してください。',
                type: 'info',
                options: {
                    title: '募集をした内容を確認しにいきましょう！',
                    duration: secondToastDuration,
                    iconBasename: 'check_circle',
                    iconModifier: 'info',
                    iconSize: 48,
                },
            },
        ];

        if (typeof showToastNotificationSequence === 'function') {
            showToastNotificationSequence(items, { gapMs: 500 });
            return;
        }

        if (typeof showToastNotification === 'undefined') {
            return;
        }

        showToastNotification(items[0].message, items[0].type, Object.assign({}, items[0].options, {
            onClose: function () {
                setTimeout(function () {
                    showToastNotification(items[1].message, items[1].type, items[1].options);
                }, 500);
            },
        }));
    }

    function showRecruitPendingWelcomeToast() {
        if (shouldShowActivationStage2Toasts()) {
            return;
        }
        if (!activationMissionUi || activationStage !== 'recruit_pending' || typeof showToastNotification === 'undefined') {
            return;
        }

        showToastNotification(
            'Ainyでは、試合募集を公開すると、\n自分の条件にあるチームが表示されます。\nまずは最初の試合募集を出してみましょう！！',
            'success',
            {
                title: 'チーム登録おめでとうございます！',
                duration: 4000,
                iconBasename: 'check_circle',
            }
        );
    }

    function init() {
        if (window.__aiduniteMypageDashboardInit === true) {
            return;
        }
        window.__aiduniteMypageDashboardInit = true;

        initTabs();
        initFocusArrows();
        initTournamentEmpty();

        var params = getMypageUrlParams();
        var showStage2Toasts = shouldShowActivationStage2Toasts();

        // 登録直後は完了トーストのみ（ウェルカムトーストと連続表示しない）
        if (showStage2Toasts) {
            if (isActivationStage2Url()) {
                persistActivationStage2ToastDebug();
            }
            showActivationStage2Toasts();
            if (params && typeof window.history.replaceState === 'function' && isActivationStage2Url()) {
                params.delete('activation');
                var query = params.toString();
                var nextUrl = window.location.pathname + (query ? '?' + query : '') + window.location.hash;
                window.history.replaceState({}, '', nextUrl);
            }
        } else {
            showRecruitPendingWelcomeToast();
        }

        if (params) {
            if (params.get('activation_locked') && typeof showToastNotification !== 'undefined') {
                showToastNotification(activationLockMessage, 'info');
            }
        }

        Promise.all([loadChatUnread(), loadActions()]).then(function () {
            if (activationMissionUi) {
                return null;
            }
            return loadSchedules();
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
