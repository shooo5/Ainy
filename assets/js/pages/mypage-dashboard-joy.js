/**
 * マイページ Joy UI v2（コンパクトヒーロー＋最近のチームの様子＋今週の予定カード）
 */
(function () {
    'use strict';

    var cfg = window.aiduniteMypageJoy || {};
    var restNonce = cfg.restNonce || '';
    var scheduleUrl = cfg.scheduleUrl || '/schedule-management';
    var communicationUrl = cfg.communicationUrl || '/communication';
    var attendanceUrl = cfg.attendanceUrl || '/attendance-report';
    var matchBoardUrl = cfg.matchBoardUrl || '/match-board-own';
    var matchBoardMyUrl = cfg.matchBoardMyUrl || '';
    var activationStage = cfg.activationStage || '';
    var activationMissionUi = cfg.activationMissionUi === '1' || cfg.activationMissionUi === 1 || cfg.activationMissionUi === true;
    var activationChatUnlocked = cfg.activationChatUnlocked === '1' || cfg.activationChatUnlocked === 1 || cfg.activationChatUnlocked === true;

    function isMemberJoy() {
        return cfg.isMemberRole === '1' || cfg.isMemberRole === 1 || cfg.isMemberRole === true;
    }

    var state = {
        payload: null,
        weekSchedules: [],
        chatUnread: 0,
    };

    function escapeHtml(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function setJoyHtml(el, html) {
        if (!el) {
            return;
        }
        el.replaceChildren();
        if (!html) {
            return;
        }
        var template = document.createElement('template');
        template.innerHTML = html;
        el.appendChild(template.content);
    }

    function joyEmptyCalmHtml(message) {
        if (typeof aiduniteCompactEmptyHtml === 'function') {
            return aiduniteCompactEmptyHtml(message, 'mypage-joy-empty-calm');
        }
        return '<p class="mypage-joy-empty-calm">' + escapeHtml(message) + '</p>';
    }

    function getCopy(payload, key, fallback) {
        if (payload && payload.copy && payload.copy[key]) {
            return payload.copy[key];
        }
        return fallback || '';
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
        var today = new Date();
        today.setHours(0, 0, 0, 0);
        var dlDay = new Date(d);
        dlDay.setHours(0, 0, 0, 0);
        var prefix = dlDay.getTime() === today.getTime() ? '本日' : ((d.getMonth() + 1) + '/' + d.getDate());
        return prefix + ' ' + String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0') + 'まで';
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

    function celebrationDismissKey(requestId) {
        return 'aidunite_joy_celebration_seen_' + String(requestId || '');
    }

    function isCelebrationDismissed(requestId) {
        try {
            return localStorage.getItem(celebrationDismissKey(requestId)) === '1';
        } catch (e) {
            return false;
        }
    }

    function dismissCelebration(requestId) {
        try {
            localStorage.setItem(celebrationDismissKey(requestId), '1');
        } catch (e) {
            /* ignore */
        }
    }

    function buildTimeRange(match) {
        if (!match) return '';
        if (match.start_time && match.end_time) {
            return match.start_time + '〜' + match.end_time;
        }
        if (match.start_time) {
            return match.start_time;
        }
        return '';
    }

    function buildMatchMetaLine(match) {
        if (!match) return '';
        var parts = [];
        var dateLabel = formatDateLabel(match.date || match.match_date || '');
        var time = buildTimeRange(match);
        if (dateLabel && time) {
            parts.push(dateLabel + ' ' + time);
        } else if (dateLabel) {
            parts.push(dateLabel);
        } else if (time) {
            parts.push(time);
        }
        if (match.opponent_name || match.team_name) {
            parts.push('vs ' + (match.opponent_name || match.team_name));
        }
        return parts.join(' ');
    }

    function buildReplyMetaLine(priority) {
        if (!priority) return '';
        var dateLabel = formatDateLabel(priority.date || '');
        var time = buildTimeRange(priority);
        var line = dateLabel;
        if (time) {
            line += (line ? ' ' : '') + time;
        }
        if (line) {
            line += 'の';
        }
        line += '試合のお返事が届いています';
        return line;
    }

    function resolveDisplayState(payload) {
        if (!payload) {
            return 'calm';
        }
        var displayState = payload.display_state || 'calm';
        if (displayState === 'celebration' && payload.celebration) {
            var eligible = payload.celebration_hero_eligible !== false;
            if (!eligible || isCelebrationDismissed(payload.celebration.request_id)) {
                if (payload.month_progress && payload.month_progress.is_praise_worthy) {
                    return 'praise';
                }
                if (payload.discover) {
                    return 'discover';
                }
                return 'calm';
            }
        }
        return displayState;
    }

    function heroIconCompactHtml(stateKey) {
        var icons = {
            celebration: 'celebration',
            match_day: 'basketball',
            reply: 'forward_to_inbox',
            urgent: 'brightness_alert',
            discover: 'group',
            praise: 'trophy',
            waiting: 'hourglass_empty',
            action: 'check_circle',
            calm: 'check_circle',
        };
        var emoji = {
            celebration: '✓',
            match_day: '🏀',
            reply: '✉',
            urgent: '!',
            discover: '✨',
            praise: '★',
            waiting: '⏳',
            action: '☑',
            calm: '☺',
        };
        var iconName = icons[stateKey] || 'celebration';
        var inner = (typeof AidUniteThemeIcons !== 'undefined' && AidUniteThemeIcons.html)
            ? AidUniteThemeIcons.html(iconName, 22)
            : '';
        if (!inner) {
            inner = emoji[stateKey] || '•';
        }
        return '<span class="mypage-joy-hero-card__icon" aria-hidden="true">' + inner + '</span>';
    }

    function heroActionsHtml(buttons) {
        if (!buttons || !buttons.length) return '';
        var html = '<div class="mypage-joy-hero-card__actions">';
        buttons.forEach(function (btn) {
            if (btn.tag === 'button') {
                html += '<button type="button" class="mypage-joy-btn ' + escapeHtml(btn.className || 'mypage-joy-btn--primary') + '"'
                    + (btn.action ? ' data-joy-action="' + escapeHtml(btn.action) + '"' : '')
                    + (btn.itemId ? ' data-item-id="' + escapeHtml(String(btn.itemId)) + '"' : '')
                    + (btn.modal ? ' data-aidunite-recruit-modal="1"' : '')
                    + '>' + escapeHtml(btn.label) + '</button>';
            } else {
                html += '<a href="' + escapeHtml(btn.href || '#') + '" class="mypage-joy-btn ' + escapeHtml(btn.className || 'mypage-joy-btn--outline') + '"'
                    + (btn.dismissCelebrationId ? ' data-dismiss-celebration="' + escapeHtml(String(btn.dismissCelebrationId)) + '"' : '')
                    + '>' + escapeHtml(btn.label) + '</a>';
            }
        });
        html += '</div>';
        return html;
    }

    function buildHeroCompactHtml(stateKey, title, metaLine, extraLines, buttons, options) {
        options = options || {};
        var extras = Array.isArray(extraLines) ? extraLines : (extraLines ? [extraLines] : []);
        var extraHtml = extras.filter(Boolean).map(function (line) {
            var content = options.allowHtmlInExtras ? line : escapeHtml(line);
            return '<p class="mypage-joy-hero-card__extra">' + content + '</p>';
        }).join('');
        var metaContent = metaLine
            ? (options.metaHtml ? metaLine : escapeHtml(metaLine))
            : '';
        var metaHtml = metaContent
            ? '<p class="mypage-joy-hero-card__meta">' + metaContent + '</p>'
            : '';
        var titleHtml = escapeHtml(title || '');

        return ''
            + '<div class="mypage-joy-hero-card__inner">'
            + '<div class="mypage-joy-hero-card__head">'
            + '<div class="mypage-joy-hero-card__head-cluster">'
            + '<div class="mypage-joy-hero-card__head-main">'
            + heroIconCompactHtml(stateKey)
            + '<h2 class="mypage-joy-hero-card__title">' + titleHtml + '</h2>'
            + '</div>'
            + '<span class="mypage-joy-hero-card__head-accent" aria-hidden="true"></span>'
            + '</div>'
            + '</div>'
            + metaHtml
            + extraHtml
            + heroActionsHtml(buttons)
            + '</div>';
    }

    function applyHeroCard(el, modifierClasses, html) {
        el.className = 'mypage-joy-hero-card mypage-joy-hero-card--compact ' + modifierClasses;
        setJoyHtml(el, html);
    }

    function openRecruitQuickModal() {
        if (typeof AidUniteScheduleQuickModal === 'undefined'
            || typeof AidUniteScheduleQuickModal.openRegisterRecruit !== 'function') {
            return false;
        }
        if (!AidUniteScheduleQuickModal.canEdit()) {
            return false;
        }
        AidUniteScheduleQuickModal.openRegisterRecruit();
        return true;
    }

    function bindRecruitModalTriggers(root) {
        if (!root) return;
        root.querySelectorAll('[data-aidunite-recruit-modal]').forEach(function (btn) {
            if (btn.dataset.aiduniteRecruitBound === '1') {
                return;
            }
            btn.dataset.aiduniteRecruitBound = '1';
            btn.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                openRecruitQuickModal();
            });
        });
    }

    function bindHeroActions(el) {
        if (!el) return;
        el.querySelectorAll('[data-joy-action]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                handleMatchAction(btn.getAttribute('data-joy-action'), btn.getAttribute('data-item-id'), btn);
            });
        });
        el.querySelectorAll('[data-dismiss-celebration]').forEach(function (link) {
            link.addEventListener('click', function () {
                dismissCelebration(link.getAttribute('data-dismiss-celebration'));
            });
        });
        bindRecruitModalTriggers(el);
    }

    function maybeOpenRecruitModalFromQuery() {
        try {
            var params = new URLSearchParams(window.location.search);
            if (params.get('open_recruit') !== '1') {
                return;
            }
            openRecruitQuickModal();
        } catch (e) {
            /* ignore */
        }
    }

    function getMatchBoardMyUrl() {
        if (matchBoardMyUrl) {
            return matchBoardMyUrl;
        }
        return matchBoardUrl + (matchBoardUrl.indexOf('?') >= 0 ? '&' : '?') + 'market_tab=my';
    }

    function quickIconHtml(iconName, modifierClass) {
        var inner = (typeof AidUniteThemeIcons !== 'undefined' && AidUniteThemeIcons.html)
            ? AidUniteThemeIcons.html(iconName, 26)
            : '';
        return '<span class="mypage-joy-quick__icon mypage-joy-quick__icon--' + modifierClass + '" aria-hidden="true">' + inner + '</span>';
    }

    function buildMissionQuickNavHtml(stage) {
        var recruitBtn = ''
            + '<button type="button" class="mypage-joy-quick__item" id="mypage-joy-quick-recruit" data-aidunite-recruit-modal="1">'
            + quickIconHtml('campaign', 'recruit')
            + '<span class="mypage-joy-quick__label">試合を募集</span>'
            + '</button>';
        var confirmLink = ''
            + '<a href="' + escapeHtml(getMatchBoardMyUrl()) + '" class="mypage-joy-quick__item" id="mypage-joy-quick-confirm">'
            + quickIconHtml('check_circle', 'confirm')
            + '<span class="mypage-joy-quick__label">申請状況の確認</span>'
            + '</a>';

        if (stage === 'recruit_pending') {
            return {
                className: 'mypage-joy-quick mypage-joy-quick--one mypage-joy-quick--mission',
                html: '<button type="button" class="mypage-joy-quick__item" id="mypage-joy-quick-recruit-mission" data-aidunite-recruit-modal="1">'
                    + quickIconHtml('campaign', 'recruit')
                    + '<span class="mypage-joy-quick__label">試合を募集</span>'
                    + '</button>',
            };
        }

        if (stage === 'recruit_published' || stage === 'first_application') {
            return {
                className: 'mypage-joy-quick mypage-joy-quick--two mypage-joy-quick--mission',
                html: recruitBtn + confirmLink,
            };
        }

        var chatLink = '';
        if (activationChatUnlocked) {
            chatLink = ''
                + '<a href="' + escapeHtml(communicationUrl) + '" class="mypage-joy-quick__item" id="mypage-joy-quick-chat">'
                + quickIconHtml('chat', 'chat')
                + '<span class="mypage-joy-quick__label">チャット</span>'
                + '</a>';
        }

        return {
            className: 'mypage-joy-quick mypage-joy-quick--' + (activationChatUnlocked ? 'three' : 'two'),
            html: recruitBtn + confirmLink + chatLink,
        };
    }

    function syncMissionQuickNav() {
        if (!activationMissionUi) {
            return;
        }
        var nav = document.getElementById('mypage-joy-quick-nav');
        if (!nav) {
            return;
        }
        var built = buildMissionQuickNavHtml(activationStage);
        nav.className = built.className;
        nav.setAttribute('role', 'navigation');
        nav.setAttribute('aria-label', 'クイックアクション');
        setJoyHtml(nav, built.html);
        bindRecruitModalTriggers(nav);
    }

    function syncBottomNavForActivation() {
        if (!activationMissionUi) {
            return;
        }
        var inner = document.querySelector('.ainy-bottom-nav-inner');
        if (!inner) {
            return;
        }

        inner.querySelectorAll('.ainy-bottom-nav-link[data-nav-id]').forEach(function (link) {
            var navId = link.getAttribute('data-nav-id') || '';
            if (activationStage === 'recruit_pending') {
                link.hidden = navId === 'schedule' || navId === 'chat' || navId === 'match';
                return;
            }
            if (activationStage === 'recruit_published' || activationStage === 'first_application') {
                link.hidden = navId === 'schedule' || navId === 'chat';
                if (navId === 'match') {
                    link.setAttribute('href', getMatchBoardMyUrl());
                    var label = link.querySelector('span');
                    if (label) {
                        label.textContent = '申請状況';
                    }
                    link.setAttribute('title', '申請状況');
                }
            }
        });

        var visibleCount = 0;
        inner.querySelectorAll('.ainy-bottom-nav-link').forEach(function (link) {
            if (!link.hidden) {
                visibleCount += 1;
            }
        });
        inner.className = 'ainy-bottom-nav-inner ainy-bottom-nav-inner--count-' + Math.max(1, Math.min(5, visibleCount));
    }

    function applyActivationStageRecruitPublished() {
        activationStage = 'recruit_published';
        var heroEl = document.getElementById('mypage-joy-hero');
        if (heroEl) {
            renderActivationMissionHero(heroEl);
            bindHeroActions(heroEl);
        }
        syncMissionQuickNav();
        syncBottomNavForActivation();
    }

    function applyRecruitPublishedFromQuickModal() {
        applyActivationStageRecruitPublished();
    }

    function maybeOpenRecruitPublishedFromQuery() {
        try {
            var params = new URLSearchParams(window.location.search);
            if (params.get('activation_recruit_published') !== '1') {
                return;
            }
            applyRecruitPublishedFromQuickModal();
            if (window.history && typeof window.history.replaceState === 'function') {
                params.delete('activation_recruit_published');
                var qs = params.toString();
                var next = window.location.pathname + (qs ? '?' + qs : '') + window.location.hash;
                window.history.replaceState({}, '', next);
            }
        } catch (e) {
            /* ignore */
        }
    }

    function onActivationRecruitPublished() {
        applyRecruitPublishedFromQuickModal();
    }

    function renderActivationMissionHero(el) {
        if (!activationMissionUi) {
            return false;
        }

        var boardMyUrl = getMatchBoardMyUrl();

        if (activationStage === 'recruit_pending') {
            applyHeroCard(el, 'mypage-joy-hero-card--action mypage-joy-hero-card--mission', buildHeroCompactHtml(
                'action',
                '最初の試合募集を出しましょう！',
                '難しい設定は必要ありません。<br>まずは日時を決めるところから始めましょう。',
                '',
                [{ tag: 'button', label: '＋ 試合の募集を出す', className: 'mypage-joy-btn--primary', modal: true }],
                { metaHtml: true }
            ));
            return true;
        }

        if (activationStage === 'recruit_published' || activationStage === 'first_application') {
            applyHeroCard(el, 'mypage-joy-hero-card--action mypage-joy-hero-card--mission', buildHeroCompactHtml(
                'action',
                '募集が公開されました！！',
                '登録された申請を確認してみましょう。',
                '',
                [{ tag: 'a', href: boardMyUrl, label: '申請状況を見る', className: 'mypage-joy-btn--primary' }]
            ));
            return true;
        }

        return false;
    }

    function showRecruitPendingWelcomeToast() {
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

    function renderHero(payload) {
        var el = document.getElementById('mypage-joy-hero');
        if (!el) return;

        if (isMemberJoy()) {
            if (!payload) return;
            var memberActions = payload.secondary_actions || [];
            var memberPriority = null;
            memberActions.some(function (item) {
                if (!item) return false;
                if (item.type === 'attendance' || item.type === 'payment') {
                    memberPriority = item;
                    return true;
                }
                return false;
            });
            if (!memberPriority && memberActions.length) {
                memberPriority = memberActions[0];
            }

            if (memberPriority) {
                applyHeroCard(el, 'mypage-joy-hero-card--action', buildHeroCompactHtml(
                    'action',
                    memberPriority.title || 'やることがあります',
                    memberPriority.description || '',
                    '',
                    [{
                        tag: 'a',
                        href: memberPriority.link_url || attendanceUrl,
                        label: '確認する',
                        className: 'mypage-joy-btn--primary',
                    }]
                ));
                return;
            }

            var memberNext = payload.next_match;
            if (memberNext) {
                applyHeroCard(el, 'mypage-joy-hero-card--match-day', buildHeroCompactHtml(
                    'match_day',
                    '次の予定があります',
                    buildMatchMetaLine(memberNext),
                    '',
                    [{ tag: 'a', href: scheduleUrl, label: 'スケジュールを見る', className: 'mypage-joy-btn--primary' }]
                ));
                return;
            }

            applyHeroCard(el, 'mypage-joy-hero-card--calm', buildHeroCompactHtml(
                'calm',
                '今週の予定を確認しましょう',
                'チームの練習・試合予定はスケジュールから確認できます。',
                '',
                [{ tag: 'a', href: scheduleUrl, label: 'スケジュールを見る', className: 'mypage-joy-btn--primary' }]
            ));
            return;
        }

        if (renderActivationMissionHero(el)) {
            bindHeroActions(el);
            return;
        }

        if (!payload) return;

        var displayState = resolveDisplayState(payload);
        var celebration = payload.celebration;
        var nextMatch = payload.next_match;
        var priority = payload.priority_action;
        var discover = payload.discover;
        var summary = payload.summary || {};
        var urls = payload.urls || {};

        if (displayState === 'celebration' && celebration) {
            applyHeroCard(el, 'mypage-joy-hero-card--celebration', buildHeroCompactHtml(
                'celebration',
                '試合が決まりました',
                buildMatchMetaLine(celebration),
                '',
                [{
                    tag: 'a',
                    href: urls.match_my || celebration.detail_url || '#',
                    label: '詳細を見る',
                    className: 'mypage-joy-btn--primary',
                    dismissCelebrationId: celebration.request_id,
                }]
            ));
            bindHeroActions(el);
            return;
        }

        if (displayState === 'match_day' && nextMatch) {
            applyHeroCard(el, 'mypage-joy-hero-card--match-day', buildHeroCompactHtml(
                'match_day',
                '今日は試合があります',
                buildMatchMetaLine(nextMatch),
                '',
                [{ tag: 'a', href: scheduleUrl, label: '今日の予定を見る', className: 'mypage-joy-btn--primary' }]
            ));
            return;
        }

        if (displayState === 'reply_received' && priority && priority.type === 'match_request') {
            var isUrgent = priority.is_urgent || priority.urgency_level === 'URGENT';
            var opponent = priority.opponent_name
                || String(priority.description || '').replace(/^vs\s*/i, '')
                || '相手チーム';
            var extras = [];
            if (isUrgent) {
                extras.push(opponent + 'が日程を確認してくれました。');
                extras.push('このまま進めると、試合が決まりそうです。');
            } else if (priority.deadline) {
                extras.push('<strong>' + escapeHtml(formatDeadline(priority.deadline)) + 'にご返答ください</strong>');
            }
            applyHeroCard(el, isUrgent ? 'mypage-joy-hero-card--urgent' : 'mypage-joy-hero-card--reply', buildHeroCompactHtml(
                isUrgent ? 'urgent' : 'reply',
                isUrgent ? '試合のお返事が届いています' : '返信が届きました',
                buildReplyMetaLine(priority),
                extras,
                [
                    { tag: 'button', label: '確認して承認', className: 'mypage-joy-btn--primary', action: 'approve', itemId: priority.id },
                    { tag: 'a', href: priority.link_url || urls.match_my || '#', label: '詳細で確認', className: 'mypage-joy-btn--outline' },
                ],
                { allowHtmlInExtras: !isUrgent && !!priority.deadline }
            ));
            bindHeroActions(el);
            return;
        }

        if (displayState === 'waiting' && (summary.pending_sent || 0) > 0) {
            applyHeroCard(el, 'mypage-joy-hero-card--waiting', buildHeroCompactHtml(
                'waiting',
                '相手の返答をお待ちしています',
                String(summary.pending_sent) + '件の申請が進行中です。通常1〜3日以内に返答があります。',
                '',
                [{ tag: 'a', href: urls.match_my || '#', label: '申請状況を確認', className: 'mypage-joy-btn--primary' }]
            ));
            return;
        }

        if (displayState === 'discover' && discover) {
            applyHeroCard(el, 'mypage-joy-hero-card--discover', buildHeroCompactHtml(
                'discover',
                getCopy(payload, 'discover_title', '試合できそうなチームがあります'),
                (discover.team_name || '相手チーム') + ' · ' + buildMatchMetaLine(discover),
                '',
                [{ tag: 'a', href: discover.apply_url || urls.match_recruit || '#', label: '見てみる', className: 'mypage-joy-btn--primary' }]
            ));
            return;
        }

        if (displayState === 'praise') {
            applyHeroCard(el, 'mypage-joy-hero-card--praise', buildHeroCompactHtml(
                'praise',
                getCopy(payload, 'praise_title', '今月も子どもたちの試合機会が増えています。'),
                getCopy(payload, 'praise_sub', 'うちのチーム、順調に試合機会を作れています。この調子でいきましょう。'),
                '',
                [{ tag: 'a', href: urls.match_my || '#', label: '詳細を見る', className: 'mypage-joy-btn--primary' }]
            ));
            return;
        }

        if (displayState === 'action_other') {
            var other = (payload.secondary_actions || [])[0];
            applyHeroCard(el, 'mypage-joy-hero-card--action', buildHeroCompactHtml(
                'action',
                other ? (other.title || 'やることがあります') : 'やることがあります',
                other && other.description ? other.description : '',
                '',
                [{ tag: 'a', href: other && other.link_url ? other.link_url : scheduleUrl, label: '確認する', className: 'mypage-joy-btn--primary' }]
            ));
            return;
        }

        applyHeroCard(el, 'mypage-joy-hero-card--calm', buildHeroCompactHtml(
            'calm',
            '今日は対応不要です',
            getCopy(payload, 'calm_sub', '試合調整は順調です。このまま練習に集中できます。'),
            '',
            [{ tag: 'a', href: urls.match_recruit || '#', label: '相手を探す', className: 'mypage-joy-btn--outline' }]
        ));
    }

    function happyIconMarkup(icon) {
        var iconMap = {
            celebration: 'celebration',
            send: 'send',
            groups: 'group',
            group: 'group',
            favorite: 'handshake',
        };
        var iconName = iconMap[icon] || 'handshake';
        var inner = (typeof AidUniteThemeIcons !== 'undefined' && AidUniteThemeIcons.html)
            ? AidUniteThemeIcons.html(iconName, 22)
            : '';
        return '<span class="mypage-joy-feed-card__icon" aria-hidden="true">' + inner + '</span>';
    }

    function resolveScheduleBadgeModifier(schedule) {
        if (!schedule) return 'practice';
        if (schedule.card_modifier) {
            var modifier = String(schedule.card_modifier).trim();
            if (modifier) return modifier.replace(/[^a-z0-9-]/gi, '') || 'practice';
        }
        if (schedule.card_class) {
            var cardClass = String(schedule.card_class);
            if (cardClass.indexOf('match-recruit') >= 0) return 'match-recruit';
            if (cardClass.indexOf('match-confirmed') >= 0) return 'match-confirmed';
            if (cardClass.indexOf('tentative') >= 0) return 'tentative';
            if (cardClass.indexOf('meeting') >= 0) return 'meeting';
            if (cardClass.indexOf('event') >= 0) return 'event';
            if (cardClass.indexOf('off') >= 0) return 'off';
        }
        return 'practice';
    }

    function resolveScheduleBadgeLabel(schedule) {
        if (schedule && schedule.status_label) {
            return String(schedule.status_label).trim();
        }
        return scheduleTypeLabel(schedule.schedule_type || schedule.type || '');
    }

    function happyFeedTypeModifier(type) {
        var map = {
            established: 'established',
            application_sent: 'application',
            connection: 'connection',
        };
        return map[type] || 'fallback';
    }

    function renderHappyFeed(payload) {
        var el = document.getElementById('mypage-joy-happy-feed');
        var allLink = document.getElementById('mypage-joy-happy-all');
        if (!el) return;

        var items = (payload && payload.happy_feed) ? payload.happy_feed : [];
        var total = (payload && typeof payload.happy_feed_total === 'number')
            ? payload.happy_feed_total
            : items.length;
        var urls = (payload && payload.urls) ? payload.urls : {};

        if (allLink) {
            if (total > 3) {
                allLink.hidden = false;
                allLink.href = urls.match_my || allLink.getAttribute('href') || '#';
            } else {
                allLink.hidden = true;
            }
        }

        if (!items.length) {
            var emptyCopy = getCopy(payload, 'happy_feed_empty', 'まだお知らせはありません。\n試合が決まるとここに表示されます。');
            var emptyParts = emptyCopy.split('\n');
            if (typeof aiduniteEmptyStateHtml === 'function') {
                setJoyHtml(el, aiduniteEmptyStateHtml({
                    title: emptyParts[0] || 'まだお知らせはありません',
                    message: emptyParts.slice(1).join(' ') || '',
                    type: 'default',
                    custom_class: 'mypage-joy-feed-empty',
                }));
            } else {
                setJoyHtml(el, joyEmptyCalmHtml(emptyCopy.replace(/\n/g, ' ')));
            }
            return;
        }

        var html = '<div class="mypage-joy-stack-cards mypage-joy-feed-cards">';
        items.slice(0, 3).forEach(function (item) {
            var feedModifier = happyFeedTypeModifier(item.type);
            html += '<a href="' + escapeHtml(item.url || '#') + '" class="mypage-joy-stack-card mypage-joy-feed-card mypage-joy-feed-card--' + escapeHtml(feedModifier) + '">'
                + '<span class="mypage-joy-feed-card__main">'
                + happyIconMarkup(item.icon)
                + '<span class="mypage-joy-feed-card__text">' + escapeHtml(item.message || '') + '</span>'
                + '</span>'
                + '<span class="mypage-joy-feed-card__chev" aria-hidden="true">›</span>'
                + '</a>';
        });
        html += '</div>';
        setJoyHtml(el, html);
    }

    function renderSecondary(payload) {
        var section = document.getElementById('mypage-joy-secondary');
        var list = document.getElementById('mypage-joy-secondary-list');
        if (!section || !list || !payload) return;

        var displayState = resolveDisplayState(payload);
        var items = payload.secondary_actions || [];
        var startIndex = 0;
        if (isMemberJoy()) {
            if (items.length && (items[0].type === 'attendance' || items[0].type === 'payment')) {
                startIndex = 1;
            }
        } else if (displayState === 'action_other') {
            startIndex = 1;
        }
        var visible = items.slice(startIndex);

        if (!visible.length) {
            section.hidden = true;
            list.replaceChildren();
            return;
        }

        section.hidden = false;
        var html = '<div class="mypage-joy-secondary__cards">';
        visible.forEach(function (item) {
            html += '<a href="' + escapeHtml(item.link_url || '#') + '" class="mypage-joy-secondary-card">'
                + '<span class="mypage-joy-secondary-card__title">' + escapeHtml(item.title || '') + '</span>'
                + (item.description ? '<span class="mypage-joy-secondary-card__desc">' + escapeHtml(item.description) + '</span>' : '')
                + '</a>';
        });
        html += '</div>';
        setJoyHtml(list, html);
    }

    function updateQuickBadges(payload) {
        var confirmEl = document.getElementById('mypage-joy-quick-confirm');
        var chatEl = document.getElementById('mypage-joy-quick-chat');
        var received = (payload && payload.summary) ? (payload.summary.pending_received || 0) : 0;

        [confirmEl, chatEl].forEach(function (el) {
            if (el) el.classList.remove('has-badge');
        });
        if (confirmEl && received > 0) confirmEl.classList.add('has-badge');
        if (chatEl && state.chatUnread > 0) chatEl.classList.add('has-badge');
    }

    function scheduleEditUrl(scheduleId) {
        var base = scheduleEditBase;
        var sep = base.indexOf('?') >= 0 ? '&' : '?';
        return base + sep + 'schedule_id=' + encodeURIComponent(String(scheduleId || ''));
    }

    function isMatchScheduleType(type) {
        return type === 'practice_match' || type === 'official_match';
    }

    function buildScheduleCardLine2(schedule) {
        var time = buildTimeRange(schedule);
        var opponent = schedule.opponent_name || schedule.opponent || schedule.title || '';
        if (isMatchScheduleType(schedule.schedule_type || schedule.type || '')) {
            return time + (opponent ? ' vs ' + opponent : '');
        }
        return time;
    }

    function buildJoyWeekScheduleHtml(weekDays) {
        var html = '<div class="mypage-joy-stack-cards mypage-joy-week-cards">';
        (weekDays || []).forEach(function (day) {
            (day.schedules || []).forEach(function (schedule) {
                var modifier = resolveScheduleBadgeModifier(schedule);
                var badgeLabel = resolveScheduleBadgeLabel(schedule);
                var row2 = schedule.card_line2
                    ? String(schedule.card_line2).trim()
                    : buildScheduleCardLine2(schedule);
                var href = isMemberJoy()
                    ? scheduleUrl
                    : (schedule.id ? scheduleEditUrl(schedule.id) : scheduleUrl);
                html += '<a href="' + escapeHtml(href) + '" class="mypage-joy-stack-card mypage-joy-week-card mypage-joy-week-card--' + escapeHtml(modifier) + '">'
                    + '<div class="mypage-joy-week-card__row1">'
                    + '<span class="mypage-joy-week-card__date">' + escapeHtml(formatDateLabel(day.date)) + '</span>'
                    + '<span class="schedule-list-badge schedule-list-badge--' + escapeHtml(modifier) + '">' + escapeHtml(badgeLabel) + '</span>'
                    + '</div>'
                    + '<p class="mypage-joy-week-card__row2">' + escapeHtml(row2) + '</p>'
                    + '</a>';
            });
        });
        html += '</div>';
        return html;
    }

    function renderWeekList(weekDays) {
        var el = document.getElementById('mypage-joy-week-list');
        if (!el) return;

        if (!weekDays || !weekDays.length) {
            setJoyHtml(el, joyEmptyCalmHtml('今週の予定はありません。'));
            return;
        }

        setJoyHtml(el, buildJoyWeekScheduleHtml(weekDays));
    }

    function handleMatchAction(action, itemId, buttonEl) {
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
                        showToastNotification('マッチ申請を承認しました', 'success', {
                            title: '試合が決まりました！',
                        });
                    }
                    if (buttonEl) {
                        buttonEl.disabled = true;
                    }
                    loadContext().then(function () {
                        if (data.show_onboarding_bot_chat_modal
                            && typeof window.aiduniteShowOnboardingBotChatModal === 'function') {
                            window.aiduniteShowOnboardingBotChatModal(data.chat_url || '');
                        }
                    });
                }
            });
    }

    function loadContext() {
        return fetch('/wp-json/aidunite/v1/mypage-joy-context', {
            method: 'GET',
            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': restNonce },
        })
            .then(function (r) { return r.ok ? r.json() : { data: null }; })
            .then(function (res) {
                state.payload = (res && res.data) ? res.data : null;
                renderAll();
            })
            .catch(function (err) {
                console.debug('mypage-joy: context load failed', err);
                state.payload = null;
                var hero = document.getElementById('mypage-joy-hero');
                if (hero) {
                    hero.className = 'mypage-joy-hero-card mypage-joy-hero-card--calm';
                    setJoyHtml(hero, joyEmptyCalmHtml('読み込みに失敗しました。ページを再読み込みしてください。'));
                }
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
                if (state.payload) {
                    updateQuickBadges(state.payload);
                }
            })
            .catch(function (err) {
                console.debug('mypage-joy: chat unread fetch failed', err);
                state.chatUnread = 0;
            });
    }

    function loadSchedules() {
        var today = typeof AidUniteDateUtils !== 'undefined' && AidUniteDateUtils.formatDateLocal
            ? AidUniteDateUtils.formatDateLocal(new Date())
            : new Date().toISOString().slice(0, 10);
        var end = new Date();
        end.setDate(end.getDate() + 6);
        var endStr = typeof AidUniteDateUtils !== 'undefined' && AidUniteDateUtils.formatDateLocal
            ? AidUniteDateUtils.formatDateLocal(end)
            : end.toISOString().slice(0, 10);

        return fetch('/wp-json/aidunite/v1/get-user-schedules?start=' + encodeURIComponent(today) + '&end=' + encodeURIComponent(endStr), {
            method: 'GET',
            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': restNonce },
        })
            .then(function (r) { return r.ok ? r.json() : { data: [] }; })
            .then(function (data) {
                var list = (data && data.data) ? data.data : [];
                var byDate = {};
                list.forEach(function (raw) {
                    var s = (typeof AidUniteScheduleUtils !== 'undefined' && AidUniteScheduleUtils.normalizeScheduleRecord)
                        ? AidUniteScheduleUtils.normalizeScheduleRecord(raw)
                        : raw;
                    var k = s && s.date ? String(s.date).trim().substring(0, 10) : '';
                    if (k && /^\d{4}-\d{2}-\d{2}$/.test(k)) {
                        if (!byDate[k]) byDate[k] = [];
                        byDate[k].push(s);
                    }
                });

                var weekDays = [];
                for (var i = 0; i <= 6; i++) {
                    var d = new Date();
                    d.setDate(d.getDate() + i);
                    var key = typeof AidUniteDateUtils !== 'undefined' && AidUniteDateUtils.formatDateLocal
                        ? AidUniteDateUtils.formatDateLocal(d)
                        : d.toISOString().slice(0, 10);
                    if (byDate[key] && byDate[key].length) {
                        weekDays.push({ date: key, schedules: byDate[key] });
                    }
                }
                state.weekSchedules = weekDays;
                renderWeekList(weekDays);
            })
            .catch(function (err) {
                console.debug('mypage-joy: week schedules fetch failed', err);
                renderWeekList([]);
            });
    }

    function renderAll() {
        renderHero(state.payload);
        if (!isMemberJoy()) {
            renderHappyFeed(state.payload);
        }
        renderSecondary(state.payload);
        updateQuickBadges(state.payload);
    }

    function init() {
        if (window.__aiduniteMypageJoyInit === true) {
            return;
        }
        window.__aiduniteMypageJoyInit = true;

        renderHero(null);
        bindRecruitModalTriggers(document);
        showRecruitPendingWelcomeToast();
        maybeOpenRecruitModalFromQuery();
        maybeOpenRecruitPublishedFromQuery();
        window.addEventListener('aidunite:activation-recruit-published', onActivationRecruitPublished);
        Promise.all([loadContext(), loadChatUnread(), loadSchedules()]);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
