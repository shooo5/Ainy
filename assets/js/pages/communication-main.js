/**
 * コミュニケーションメインページ用スクリプト（統合タイムライン・新規投稿モーダル等）
 * team_id は wp_localize_script('aidunite-communication-main', 'aidunite_communication_main', { team_id: ... }) で渡すこと
 */
(function() {
    'use strict';

    function getTeamId() {
        return (typeof aidunite_communication_main !== 'undefined' && aidunite_communication_main.team_id != null && aidunite_communication_main.team_id !== '') ? String(aidunite_communication_main.team_id) : '';
    }

    function themeIconHtml(basename, size) {
        return (typeof AidUniteThemeIcons !== 'undefined') ? AidUniteThemeIcons.html(basename, size || 18) : '';
    }

    document.addEventListener('DOMContentLoaded', function() {
        const newMessageBtn = document.getElementById('newMessageBtn');
        const messageModal = document.getElementById('messageModal');
        const modalClose = document.getElementById('modalClose');
        const cancelBtn = document.getElementById('cancelBtn');
        const messageForm = document.getElementById('messageForm');
        const timelineList = document.getElementById('timelineList');
        const filterBtns = document.querySelectorAll('.filter-btn');
        const postBtn = document.getElementById('postBtn');
        const draftBtn = document.getElementById('draftBtn');

        let timelineItems = [];
        let filteredItems = [];
        let currentFilter = 'all';
        let lastTimelineData = null;
        const ACTIVE_POLL_MS = 1000;
        const IDLE_POLL_MS = 10000;
        let timelinePollTimer = null;
        let isTimelineRequestInFlight = false;
        let hasTimelineLoaded = false;
        let lastFilteredKeysSignature = '';
        let lastTimelineStateSignature = '';
        let lastAsideStateSignature = '';

        function isTimelineChatHidden(item) {
            return !!(item && (item.is_hidden === 1 || item.is_hidden === true || item.is_hidden === '1'));
        }

        function isTimelineChatCompleted(item) {
            return item && item.item_type === 'chat' && !isTimelineChatHidden(item)
                && (item.room_status || 'active') === 'completed';
        }

        function isTimelineChatActive(item) {
            return item && item.item_type === 'chat' && !isTimelineChatHidden(item)
                && (item.room_status || 'active') !== 'completed';
        }

        function isActionRequired(item) {
            if (isTimelineChatHidden(item)) return false;
            if (item.item_type === 'chat' && (item.room_status || 'active') === 'completed') return false;
            const unread = (item.unread_count || 0) > 0;
            const currentUserId = (typeof aidunite_messaging !== 'undefined' && aidunite_messaging.user_id) ? Number(aidunite_messaging.user_id) : null;
            const authorId = item.user_id != null ? Number(item.user_id) : null;
            const isFromOther = authorId != null && authorId !== 0 && authorId !== currentUserId;
            return unread && isFromOther;
        }

        function getActionRequiredCount(items) {
            return (items || []).filter(isActionRequired).length;
        }

        /** サマリー更新：要対応件数（相手送信の未読＝返信すべき件数） */
        function updateCommunicationSummary(data, items) {
            const unreadCount = getActionRequiredCount(items || []);
            const unreadEl = document.getElementById('unreadCount');
            const unreadWrap = document.getElementById('summaryUnread');
            if (unreadEl) unreadEl.textContent = (unreadCount == null || isNaN(unreadCount)) ? '—' : String(unreadCount);
            if (unreadWrap) unreadWrap.setAttribute('aria-label', '要対応 ' + (unreadEl && unreadEl.textContent === '—' ? '読み込み中' : unreadEl ? unreadEl.textContent + '件' : ''));
        }

        /** サマリークリック：要対応タブに切替 */
        function bindSummaryClicks() {
            document.querySelectorAll('.summary-item[data-action]').forEach(el => {
                el.addEventListener('click', function() {
                    const action = this.getAttribute('data-action');
                    if (action === 'action-required' || action === 'unread') {
                        activateFilter('action-required');
                    }
                });
                el.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        this.click();
                    }
                });
            });
        }
        bindSummaryClicks();

        function activateFilter(filterName) {
            currentFilter = filterName;
            filterBtns.forEach(b => {
                const isActive = b.dataset.filter === filterName;
                b.classList.toggle('active', isActive);
                b.setAttribute('aria-selected', isActive ? 'true' : 'false');
            });
            filterTimeline();
        }

        filterBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                activateFilter(this.dataset.filter);
            });
        });

        const fabMenu = document.getElementById('fabMenu');
        const fabMenuPostBoard = document.getElementById('fabMenuPostBoard');
        const fabMenuStartChat = document.getElementById('fabMenuStartChat');
        const startChatModal = document.getElementById('startChatModal');
        const startChatModalClose = document.getElementById('startChatModalClose');
        const startChatCancelBtn = document.getElementById('startChatCancelBtn');
        const startChatMemberList = document.getElementById('startChatMemberList');
        const startChatMemberLoading = document.getElementById('startChatMemberLoading');
        const startChatTitleWrap = document.getElementById('startChatTitleWrap');
        const startChatRoomTitle = document.getElementById('startChatRoomTitle');
        const startChatSubmitBtn = document.getElementById('startChatSubmitBtn');

        function isFabMenuOpen() {
            return fabMenu && fabMenu.getAttribute('aria-hidden') === 'false';
        }

        function openFabMenu() {
            if (fabMenu) {
                fabMenu.setAttribute('aria-hidden', 'false');
                fabMenu.classList.add('is-open');
            }
            if (newMessageBtn) newMessageBtn.setAttribute('aria-expanded', 'true');
        }

        function closeFabMenu() {
            if (fabMenu) {
                fabMenu.setAttribute('aria-hidden', 'true');
                fabMenu.classList.remove('is-open');
            }
            if (newMessageBtn) newMessageBtn.setAttribute('aria-expanded', 'false');
        }

        function toggleFabMenu(e) {
            if (e) e.stopPropagation();
            if (isFabMenuOpen()) closeFabMenu();
            else openFabMenu();
        }

        if (newMessageBtn) {
            newMessageBtn.addEventListener('click', function(e) {
                toggleFabMenu(e);
            });
        }

        if (fabMenuPostBoard) {
            fabMenuPostBoard.addEventListener('click', function() {
                closeFabMenu();
                if (messageModal) messageModal.style.display = 'flex';
            });
        }

        if (fabMenuStartChat) {
            fabMenuStartChat.addEventListener('click', function() {
                closeFabMenu();
                if (startChatModal) {
                    startChatModal.style.display = 'flex';
                    loadStartChatMembers();
                }
            });
        }

        const shortcutStartChat = document.getElementById('shortcutStartChat');
        const shortcutPostBoard = document.getElementById('shortcutPostBoard');
        if (shortcutStartChat && fabMenuStartChat) {
            shortcutStartChat.addEventListener('click', function() {
                fabMenuStartChat.click();
            });
        }
        if (shortcutPostBoard && fabMenuPostBoard) {
            shortcutPostBoard.addEventListener('click', function() {
                fabMenuPostBoard.click();
            });
        }

        document.addEventListener('click', function(e) {
            if (!isFabMenuOpen()) return;
            if (e.target.closest('.new-message-btn-fixed-wrap')) return;
            closeFabMenu();
        });

        let startChatMembers = [];

        function loadStartChatMembers() {
            if (!startChatMemberList) return;
            if (startChatMemberLoading) startChatMemberLoading.style.display = 'block';
            startChatMemberList.innerHTML = '';
            const teamId = getTeamId();
            if (!teamId) {
                if (startChatMemberLoading) startChatMemberLoading.textContent = 'チーム情報がありません';
                return;
            }
            fetch('/wp-json/aidunite/v1/team-members?team_id=' + encodeURIComponent(teamId), {
                headers: { 'X-WP-Nonce': (typeof aidunite_messaging !== 'undefined' ? aidunite_messaging.nonce : '') }
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data.members && Array.isArray(data.members)) {
                    startChatMembers = data.members;
                    startChatMemberList.innerHTML = '';
                    if (startChatMemberLoading) startChatMemberLoading.style.display = 'none';
                    if (startChatMembers.length === 0) {
                        startChatMemberList.innerHTML = '<p class="start-chat-no-members">チームに他のメンバーがいません</p>';
                    } else {
                        startChatMembers.forEach(function(m) {
                            const label = document.createElement('label');
                            label.className = 'start-chat-member-item';
                            label.innerHTML = '<input type="checkbox" class="start-chat-member-check" data-user-id="' + Number(m.user_id) + '"> <span class="start-chat-member-name">' + (m.display_name || 'ユーザー#' + m.user_id) + '</span>';
                            startChatMemberList.appendChild(label);
                        });
                        startChatMemberList.querySelectorAll('.start-chat-member-check').forEach(function(cb) {
                            cb.addEventListener('change', updateStartChatSubmitState);
                        });
                    }
                } else {
                    if (startChatMemberLoading) startChatMemberLoading.textContent = '取得に失敗しました';
                }
            })
            .catch(function() {
                if (startChatMemberLoading) startChatMemberLoading.textContent = '取得に失敗しました';
            });
        }

        function getStartChatSelectedIds() {
            if (!startChatMemberList) return [];
            const ids = [];
            startChatMemberList.querySelectorAll('.start-chat-member-check:checked').forEach(function(cb) {
                ids.push(Number(cb.dataset.userId));
            });
            return ids;
        }

        function updateStartChatSubmitState() {
            const ids = getStartChatSelectedIds();
            if (startChatSubmitBtn) startChatSubmitBtn.disabled = ids.length === 0;
            if (startChatTitleWrap) startChatTitleWrap.style.display = ids.length >= 2 ? 'block' : 'none';
        }

        function closeStartChatModal() {
            if (startChatModal) startChatModal.style.display = 'none';
            if (startChatRoomTitle) startChatRoomTitle.value = '';
            if (startChatMemberList) startChatMemberList.querySelectorAll('.start-chat-member-check').forEach(function(cb) { cb.checked = false; });
            updateStartChatSubmitState();
        }

        if (startChatModalClose) startChatModalClose.addEventListener('click', closeStartChatModal);
        if (startChatCancelBtn) startChatCancelBtn.addEventListener('click', closeStartChatModal);
        if (startChatModal) {
            startChatModal.addEventListener('click', function(e) {
                if (e.target === startChatModal) closeStartChatModal();
            });
        }

        if (startChatSubmitBtn) {
            startChatSubmitBtn.addEventListener('click', function() {
                const selectedIds = getStartChatSelectedIds();
                if (selectedIds.length === 0) return;
                const currentUserId = (typeof aidunite_messaging !== 'undefined' && aidunite_messaging.user_id != null) ? Number(aidunite_messaging.user_id) : 0;
                const participants = currentUserId ? [currentUserId].concat(selectedIds) : selectedIds;
                const title = startChatRoomTitle ? (startChatRoomTitle.value || '').trim() : '';
                const type = participants.length === 2 ? 'direct' : 'group';

                startChatSubmitBtn.disabled = true;
                startChatSubmitBtn.textContent = '作成中...';

                fetch('/wp-json/aidunite/v1/chats', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': (typeof aidunite_messaging !== 'undefined' ? aidunite_messaging.nonce : '')
                    },
                    body: JSON.stringify({
                        participants: participants,
                        type: type,
                        title: title || undefined
                    })
                })
                .then(function(res) { return res.json().then(function(data) { return { ok: res.ok, data: data }; }); })
                .then(function(result) {
                    if (result.ok && result.data && result.data.room_id) {
                        closeStartChatModal();
                        window.location.href = '/team-chat?room_id=' + encodeURIComponent(result.data.room_id);
                    } else {
                        alert(result.data && result.data.error ? result.data.error : 'チャットの作成に失敗しました');
                    }
                })
                .catch(function() {
                    alert('チャットの作成に失敗しました');
                })
                .finally(function() {
                    startChatSubmitBtn.disabled = false;
                    startChatSubmitBtn.textContent = '作成してチャットを開く';
                });
            });
        }

        function closeModal() {
            messageModal.style.display = 'none';
            if (messageForm) messageForm.reset();
        }

        if (modalClose) modalClose.addEventListener('click', closeModal);
        if (cancelBtn) cancelBtn.addEventListener('click', closeModal);

        if (messageModal) {
            messageModal.addEventListener('click', function(e) {
                if (e.target === messageModal) closeModal();
            });
        }

        if (messageForm) {
            messageForm.addEventListener('submit', function(e) {
                e.preventDefault();
                postBtn.disabled = true;
                postBtn.innerHTML = '<span class="post-icon">' + themeIconHtml('hourglass_empty', 18) + '</span><span class="post-text">投稿中...</span>';

                const formData = new FormData(messageForm);
                formData.append('action', 'post_message');
                formData.append('team_id', getTeamId());

                postMessage(formData).then(() => {
                    alert('メッセージを投稿しました！');
                    closeModal();
                    loadTimeline();
                }).catch(() => {
                    alert('投稿に失敗しました。もう一度お試しください。');
                }).finally(() => {
                    postBtn.disabled = false;
                    postBtn.innerHTML = '<span class="post-icon">' + themeIconHtml('send', 18) + '</span><span class="post-text">投稿</span>';
                });
            });
        }

        if (draftBtn) {
            draftBtn.addEventListener('click', function() {
                const formData = new FormData(messageForm);
                formData.append('action', 'save_message_draft');
                formData.append('team_id', getTeamId());

                draftBtn.disabled = true;
                draftBtn.innerHTML = '<span class="draft-icon">' + themeIconHtml('hourglass_empty', 18) + '</span><span class="draft-text">保存中...</span>';

                saveMessageDraft(formData).then(() => {
                    alert('下書きを保存しました！');
                }).catch(() => {
                    alert('保存に失敗しました。');
                }).finally(() => {
                    draftBtn.disabled = false;
                    draftBtn.innerHTML = '<span class="draft-icon">' + themeIconHtml('save', 18) + '</span><span class="draft-text">下書き保存</span>';
                });
            });
        }

        async function postMessage(formData) {
            const response = await fetch('/wp-json/aidunite/v1/messages', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': aidunite_messaging.nonce
                },
                body: JSON.stringify(Object.fromEntries(formData))
            });
            if (!response.ok) throw new Error('投稿に失敗しました');
            return response.json();
        }

        async function saveMessageDraft(formData) {
            const response = await fetch('/wp-json/aidunite/v1/messages', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': aidunite_messaging.nonce
                },
                body: JSON.stringify({
                    ...Object.fromEntries(formData),
                    status: 'draft'
                })
            });
            if (!response.ok) throw new Error('保存に失敗しました');
            return response.json();
        }

        loadTimeline();
        startTimelinePolling();

        async function loadTimeline() {
            if (!timelineList) return;
            if (isTimelineRequestInFlight) return;
            isTimelineRequestInFlight = true;
            const showLoading = !hasTimelineLoaded;
            try {
                if (showLoading) {
                    timelineList.innerHTML = '<div class="loading-message">タイムラインを読み込み中...</div>';
                }

                const teamId = getTeamId();
                const timelineTeamParam = teamId ? teamId : '0';
                const response = await fetch(
                    '/wp-json/aidunite/v1/timeline?team_id=' + encodeURIComponent(timelineTeamParam),
                    { headers: { 'X-WP-Nonce': aidunite_messaging.nonce } }
                );

                if (!response.ok) throw new Error('タイムラインの読み込みに失敗しました');

                const data = await response.json();
                timelineItems = (data.items || []).filter(function(item) {
                    return !(item.item_type === 'chat' && item.room_type === 'message_thread');
                });
                lastTimelineData = data;

                syncTimelineViewAfterFetch();
                hasTimelineLoaded = true;
            } catch (error) {
                console.error('タイムライン読み込みエラー:', error);
                if (showLoading) {
                    timelineList.innerHTML = '<div class="loading-message">タイムラインの読み込みに失敗しました</div>';
                    updateCommunicationSummary({ total_unread: 0, by_type: { board: 0, match: 0, team: 0 } }, []);
                }
            } finally {
                isTimelineRequestInFlight = false;
            }
        }

        function getTimelinePollingIntervalMs() {
            return document.hidden ? IDLE_POLL_MS : ACTIVE_POLL_MS;
        }

        function scheduleTimelinePolling(delayMs) {
            if (timelinePollTimer) {
                clearTimeout(timelinePollTimer);
                timelinePollTimer = null;
            }
            const nextMs = typeof delayMs === 'number' ? delayMs : getTimelinePollingIntervalMs();
            timelinePollTimer = setTimeout(async function() {
                await loadTimeline();
                scheduleTimelinePolling();
            }, nextMs);
        }

        function startTimelinePolling() {
            scheduleTimelinePolling(getTimelinePollingIntervalMs());
        }

        function formatUnreadBadge(n) {
            if (n <= 0) return '';
            return n > 99 ? '99+' : String(n);
        }

        function updateTabUnreadBadges(data, items) {
            const byType = data.by_type || { match: 0, team: 0, board: 0 };
            const unreadBoard = byType.board || 0;
            const activeChatItems = (items || []).filter(isTimelineChatActive);
            const unreadChat = activeChatItems.reduce((sum, item) => sum + (item.unread_count || 0), 0);

            const setBadge = (filter, count, opts) => {
                opts = opts || {};
                const el = document.querySelector('.tab-unread-badge[data-filter="' + filter + '"]');
                const btn = document.querySelector('.filter-btn[data-filter="' + filter + '"]');
                if (!el) return;
                const num = (count == null || isNaN(count)) ? 0 : Number(count);
                const nameMap = {
                    board: 'お知らせ',
                    chat: 'チャット',
                    'action-required': '要対応',
                    completed: '完了済み',
                    deleted: '削除済み'
                };
                const name = nameMap[filter] || filter;
                const isCountMode = opts.mode === 'count';
                if (num > 0) {
                    el.textContent = isCountMode ? (num > 99 ? '99+' : String(num)) : formatUnreadBadge(num);
                    el.setAttribute('aria-label', (isCountMode ? '' : '未読') + num + '件');
                    el.setAttribute('aria-hidden', 'true');
                    el.classList.add('has-unread');
                    if (btn) btn.setAttribute('aria-label', name + ' ' + (isCountMode ? num + '件' : '未読' + num + '件'));
                } else {
                    el.textContent = '';
                    el.removeAttribute('aria-label');
                    el.setAttribute('aria-hidden', 'true');
                    el.classList.remove('has-unread');
                    if (btn) btn.setAttribute('aria-label', name);
                }
            };
            setBadge('board', unreadBoard);
            setBadge('chat', unreadChat);
            setBadge('action-required', getActionRequiredCount(items));
            setBadge('completed', items.filter(isTimelineChatCompleted).length, { mode: 'count' });
            setBadge('deleted', items.filter(isTimelineChatHidden).length, { mode: 'count' });
        }

        function sortByPriority(items) {
            return items.slice().sort(function(a, b) {
                const aAction = isActionRequired(a) ? 1 : 0;
                const bAction = isActionRequired(b) ? 1 : 0;
                if (bAction !== aAction) return bAction - aAction;
                const aUnread = (a.unread_count || 0) > 0 ? 1 : 0;
                const bUnread = (b.unread_count || 0) > 0 ? 1 : 0;
                if (bUnread !== aUnread) return bUnread - aUnread;
                const tA = new Date(a.created_at || 0).getTime();
                const tB = new Date(b.created_at || 0).getTime();
                return tB - tA;
            });
        }

        function getTimelineItemKey(item) {
            if (!item) return '';
            if (item.id != null && item.id !== '') return String(item.id);
            if (item.room_id != null && item.room_id !== '') {
                return String(item.item_type || 'item') + ':' + String(item.room_id);
            }
            return String(item.item_type || 'item') + ':' + String(item.created_at || '');
        }

        function getFilteredKeysSignature(items) {
            return (items || []).map(getTimelineItemKey).join('\n');
        }

        function getTimelineStateSignature(items) {
            return (items || []).map(function(item) {
                const raw = String(item.content || '').replace(/\\n/g, '\n');
                return getTimelineItemKey(item) + ':' +
                    String(item.unread_count || 0) + ':' +
                    (isActionRequired(item) ? '1' : '0') + ':' +
                    raw.slice(0, 80);
            }).join('|');
        }

        function computeFilteredItems() {
            if (currentFilter === 'action-required' || currentFilter === 'unread') {
                filteredItems = timelineItems.filter(isActionRequired);
            } else if (currentFilter === 'all') {
                filteredItems = timelineItems.filter(item =>
                    item.item_type === 'board' || isTimelineChatActive(item)
                );
            } else if (currentFilter === 'board') {
                filteredItems = timelineItems.filter(item => item.item_type === 'board');
            } else if (currentFilter === 'chat') {
                filteredItems = timelineItems.filter(isTimelineChatActive);
            } else if (currentFilter === 'completed') {
                filteredItems = timelineItems.filter(isTimelineChatCompleted);
            } else if (currentFilter === 'deleted') {
                filteredItems = timelineItems.filter(isTimelineChatHidden);
            } else {
                filteredItems = timelineItems;
            }
            filteredItems = sortByPriority(filteredItems);
        }

        function patchTimelineCardElement(card, item) {
            const actionRequired = isActionRequired(item);
            card.classList.toggle('timeline-item--action-required', actionRequired);
            card.setAttribute('data-unread-count', String(item.unread_count || 0));
            card.setAttribute('data-action-required', actionRequired ? '1' : '0');

            const badges = card.querySelector('.timeline-badges');
            if (badges) {
                let arBadge = badges.querySelector('.action-required-badge');
                if (actionRequired && !arBadge) {
                    badges.insertAdjacentHTML('beforeend', '<span class="action-required-badge">要対応</span>');
                } else if (!actionRequired && arBadge) {
                    arBadge.remove();
                }
            }

            const headerRight = card.querySelector('.timeline-header-right');
            if (headerRight) {
                let unreadEl = headerRight.querySelector('.unread-count-badge');
                const unread = item.unread_count || 0;
                if (unread > 0) {
                    if (!unreadEl) {
                        headerRight.insertAdjacentHTML('afterbegin', '<span class="unread-count-badge timeline-card__unread">' + unread + '</span>');
                    } else {
                        unreadEl.textContent = String(unread);
                    }
                } else if (unreadEl) {
                    unreadEl.remove();
                }
            }

            const contentEl = card.querySelector('.timeline-item-content');
            if (contentEl) {
                const rawContent = String(item.content || '').replace(/\\n/g, '\n');
                const previewLen = 120;
                const contentPreview = rawContent.length > previewLen ? rawContent.substring(0, previewLen) : rawContent;
                contentEl.setAttribute('data-full', escapeHtml(rawContent));
                const expandedBtn = card.querySelector('.detail-toggle-btn[data-expanded="1"]');
                if (expandedBtn) {
                    contentEl.textContent = rawContent;
                } else if (!contentEl.classList.contains('timeline-item-content--expanded')) {
                    contentEl.textContent = contentPreview;
                }
            }
        }

        function patchTimelineCards() {
            if (!timelineList) return;
            const itemMap = {};
            filteredItems.forEach(function(item) {
                itemMap[getTimelineItemKey(item)] = item;
            });
            timelineList.querySelectorAll('.timeline-item[data-timeline-key]').forEach(function(card) {
                const key = card.getAttribute('data-timeline-key');
                const item = itemMap[key];
                if (item) {
                    patchTimelineCardElement(card, item);
                }
            });
        }

        function getActionRequiredAsideItems() {
            return timelineItems.filter(function(item) {
                return isActionRequired(item) && (item.item_type === 'board' || isTimelineChatActive(item));
            }).slice(0, 5);
        }

        function getAsideStateSignature() {
            return getActionRequiredAsideItems().map(function(item) {
                return getTimelineItemKey(item) + ':' + String(item.unread_count || 0);
            }).join('|');
        }

        function patchActionRequiredAside() {
            const asideList = document.getElementById('actionRequiredAsideList');
            if (!asideList) return;
            const required = getActionRequiredAsideItems();
            if (required.length === 0) {
                if (!asideList.querySelector('.communication-aside-empty')) {
                    asideList.innerHTML = '<p class="communication-aside-empty">要対応はありません</p>';
                }
                return;
            }
            required.forEach(function(item) {
                const key = getTimelineItemKey(item);
                const btn = asideList.querySelector('.communication-aside-item[data-timeline-key="' + CSS.escape(key) + '"]');
                if (!btn) return;
                const unread = item.unread_count || 0;
                let badge = btn.querySelector('.communication-aside-item__badge');
                if (unread > 0) {
                    if (!badge) {
                        btn.insertAdjacentHTML('beforeend', '<span class="communication-aside-item__badge">' + unread + '</span>');
                    } else {
                        badge.textContent = String(unread);
                    }
                } else if (badge) {
                    badge.remove();
                }
            });
        }

        function syncTimelineViewAfterFetch() {
            computeFilteredItems();
            updateCommunicationSummary(lastTimelineData, timelineItems);
            updateTabUnreadBadges(lastTimelineData, timelineItems);

            const keysSig = getFilteredKeysSignature(filteredItems);
            const stateSig = getTimelineStateSignature(filteredItems);
            const asideSig = getAsideStateSignature();

            if (!hasTimelineLoaded) {
                renderTimeline();
                renderActionRequiredAside();
            } else if (keysSig !== lastFilteredKeysSignature) {
                renderTimeline();
                renderActionRequiredAside();
            } else {
                if (stateSig !== lastTimelineStateSignature) {
                    patchTimelineCards();
                }
                if (asideSig !== lastAsideStateSignature) {
                    const asideList = document.getElementById('actionRequiredAsideList');
                    const required = getActionRequiredAsideItems();
                    const canPatchAside = asideList && required.length > 0 &&
                        required.length === asideList.querySelectorAll('.communication-aside-item[data-timeline-key]').length &&
                        required.every(function(item) {
                            return asideList.querySelector('[data-timeline-key="' + CSS.escape(getTimelineItemKey(item)) + '"]');
                        });
                    if (canPatchAside) {
                        patchActionRequiredAside();
                    } else {
                        renderActionRequiredAside();
                    }
                }
            }

            lastFilteredKeysSignature = keysSig;
            lastTimelineStateSignature = stateSig;
            lastAsideStateSignature = asideSig;
        }

        function filterTimeline() {
            computeFilteredItems();
            lastFilteredKeysSignature = '';
            lastTimelineStateSignature = '';
            lastAsideStateSignature = '';
            renderTimeline();
            renderActionRequiredAside();
            lastFilteredKeysSignature = getFilteredKeysSignature(filteredItems);
            lastTimelineStateSignature = getTimelineStateSignature(filteredItems);
            lastAsideStateSignature = getAsideStateSignature();
        }

        function renderActionRequiredAside() {
            const asideList = document.getElementById('actionRequiredAsideList');
            if (!asideList) return;
            const required = getActionRequiredAsideItems();
            if (required.length === 0) {
                asideList.innerHTML = '<p class="communication-aside-empty">要対応はありません</p>';
                lastAsideStateSignature = '';
                return;
            }
            asideList.innerHTML = required.map(function(item) {
                const title = escapeHtml(item.title || item.room_name || 'タイトルなし');
                const opponent = escapeHtml(getOpponentTeamLabel(item) || '—');
                const unread = item.unread_count || 0;
                const roomId = item.room_id || '';
                const key = escapeHtml(getTimelineItemKey(item));
                return '<button type="button" class="communication-aside-item" data-timeline-key="' + key + '" data-room-id="' + escapeHtml(String(roomId)) + '">' +
                    '<span class="communication-aside-item__title">' + title + '</span>' +
                    '<span class="communication-aside-item__meta">vs ' + opponent + '</span>' +
                    (unread > 0 ? '<span class="communication-aside-item__badge">' + unread + '</span>' : '') +
                    '</button>';
            }).join('');
            asideList.querySelectorAll('.communication-aside-item').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const rid = this.getAttribute('data-room-id');
                    if (rid) openChatRoom(rid);
                });
            });
            lastAsideStateSignature = getAsideStateSignature();
        }

        function escapeHtml(str) {
            return String(str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        function getOpponentTeamLabel(item) {
            if (item.opponent_team_label) return item.opponent_team_label;
            if (item.opponent_team_name) return item.opponent_team_name;
            if (item.item_type === 'board') return '';
            return '';
        }

        function formatTimelineDateLabel(iso) {
            const d = new Date(iso || 0);
            if (isNaN(d.getTime())) return '';
            const now = new Date();
            const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
            const target = new Date(d.getFullYear(), d.getMonth(), d.getDate());
            const w = ['日', '月', '火', '水', '木', '金', '土'];
            if (target.getTime() === today.getTime()) return '今日';
            const yesterday = new Date(today);
            yesterday.setDate(yesterday.getDate() - 1);
            if (target.getTime() === yesterday.getTime()) return '昨日';
            return (d.getMonth() + 1) + '/' + d.getDate() + '（' + w[d.getDay()] + '）';
        }

        function groupItemsByDate(items) {
            const groups = [];
            let lastKey = null;
            items.forEach(function(item) {
                const d = new Date(item.created_at || 0);
                const key = d.getFullYear() + '-' + (d.getMonth() + 1) + '-' + d.getDate();
                if (key !== lastKey) {
                    groups.push({ type: 'date', label: formatTimelineDateLabel(item.created_at) });
                    lastKey = key;
                }
                groups.push({ type: 'item', item: item });
            });
            return groups;
        }

        function renderTimeline() {
            if (!timelineList) return;
            if (filteredItems.length === 0) {
                timelineList.replaceChildren();
                const empty = document.createElement('div');
                empty.className = 'no-messages';
                empty.textContent = 'アイテムがありません';
                timelineList.appendChild(empty);
                return;
            }
            const groups = groupItemsByDate(filteredItems);
            let itemIndex = 0;
            const html = groups.map(function(group) {
                if (group.type === 'date') {
                    return '<div class="timeline-date-group" role="separator"><span class="timeline-date-group__label">' + escapeHtml(group.label) + '</span></div>';
                }
                const out = createTimelineItem(group.item, itemIndex);
                itemIndex += 1;
                return out;
            }).join('');
            const wrapper = document.createElement('div');
            wrapper.innerHTML = html;
            const fragment = document.createDocumentFragment();
            while (wrapper.firstChild) {
                fragment.appendChild(wrapper.firstChild);
            }
            timelineList.replaceChildren(fragment);
            attachTimelineEventListeners();
        }

        function attachTimelineEventListeners() {
            if (!timelineList) return;
            timelineList.querySelectorAll('.chat-btn').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const roomId = this.dataset.roomId;
                    if (roomId) openChatRoom(roomId);
                });
            });
            timelineList.querySelectorAll('.delete-message-btn').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const messageId = this.dataset.messageId;
                    if (messageId) deleteMessage(messageId);
                });
            });
            timelineList.querySelectorAll('.delete-chat-btn').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const roomId = this.dataset.roomId;
                    if (roomId) deleteChatRoom(roomId);
                });
            });
            timelineList.querySelectorAll('.detail-toggle-btn').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const card = this.closest('.timeline-item');
                    const content = card && card.querySelector('.timeline-item-content');
                    if (!content) return;
                    const expanded = this.getAttribute('data-expanded') === '1';
                    if (expanded) {
                        const full = content.getAttribute('data-full') || '';
                        const preview = full.length > 50 ? full.substring(0, 50) + '...' : full;
                        content.textContent = preview;
                        content.classList.add('timeline-item-content--collapsed');
                        content.classList.remove('timeline-item-content--expanded');
                        this.textContent = '続きを読む';
                        this.setAttribute('data-expanded', '0');
                    } else {
                        const full = content.getAttribute('data-full') || '';
                        content.textContent = full;
                        content.classList.remove('timeline-item-content--collapsed');
                        content.classList.add('timeline-item-content--expanded');
                        this.textContent = '閉じる';
                        this.setAttribute('data-expanded', '1');
                    }
                });
            });
        }

        function getPriorityLabel(priority) {
            const labels = { 'urgent': '緊急', 'important': '重要' };
            return labels[priority] || '';
        }

        function createTimelineItem(item, index) {
            const actionRequired = isActionRequired(item);
            const isBoard = item.item_type === 'board';
            const isChat = item.item_type === 'chat';

            const roomStatus = (item.room_status || 'active');
            const isHidden = isTimelineChatHidden(item);
            let typeLabel = isBoard ? 'お知らせ' : 'チャット';
            if (!isBoard && isHidden) {
                typeLabel = '削除済み';
            } else if (!isBoard && roomStatus === 'completed') {
                typeLabel = '完了済み';
            }
            let typeClass = item.item_type;
            if (!isBoard && isHidden) typeClass = 'deleted';
            else if (!isBoard && roomStatus === 'completed') typeClass = 'completed';
            let badges = '<span class="timeline-card-type-label timeline-card-type-label--' + typeClass + '">' + typeLabel + '</span>';
            if (item.priority && item.priority !== 'normal') {
                badges += '<span class="priority-badge ' + item.priority + '">' + getPriorityLabel(item.priority) + '</span>';
            }

            const time = new Date(item.created_at).toLocaleString('ja-JP', {
                year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit'
            });
            /* チャットはAPIで「最新1件」の内容が content に入る。お知らせは投稿本文。 */
            const rawContent = (item.content || '').replace(/\\n/g, '\n');
            const isUrgent = item.priority === 'urgent';
            /* 2行表示に収まるようプレビュー長を抑え、途中切れを防ぐ */
            const previewLen = 120;
            const hasMore = false;
            const contentPreview = rawContent.length > previewLen ? rawContent.substring(0, previewLen) : rawContent;
            const fullContentEscaped = rawContent.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
            const previewEscaped = contentPreview.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

            const messageId = isBoard ? item.id.replace('board_', '') : null;
            const roomId = isChat ? item.room_id : (item.thread_room_id || item.room_id || null);
            const unreadCount = item.unread_count || 0;
            const currentUserId = (typeof aidunite_messaging !== 'undefined' && aidunite_messaging.user_id) ? Number(aidunite_messaging.user_id) : null;
            const isAdmin = typeof aidunite_messaging !== 'undefined' && aidunite_messaging.is_admin;
            const itemUserId = item.user_id != null ? Number(item.user_id) : null;
            const canDeleteBoard = isBoard && (isAdmin || (itemUserId !== null && itemUserId !== 0 && itemUserId === currentUserId));

            const cardClasses = ['timeline-item', item.item_type];
            if (actionRequired) cardClasses.push('timeline-item--action-required');
            if (isChat) cardClasses.push('timeline-item--chat');
            if (isHidden) cardClasses.push('timeline-item--deleted');
            if (!isBoard && roomStatus === 'completed') cardClasses.push('timeline-item--completed');

            let actionsHtml = '';
            if (isBoard) {
                if (roomId) actionsHtml += '<button class="action-btn chat-btn open-board-chat-btn" data-room-id="' + roomId + '" title="チャット" type="button">チャット</button>';
                if (canDeleteBoard) actionsHtml += '<button class="action-btn delete-btn delete-message-btn" data-message-id="' + messageId + '" title="削除" type="button">削除</button>';
            } else {
                actionsHtml += '<button class="action-btn chat-btn" data-room-id="' + item.room_id + '" title="チャット">チャット</button>';
                if (!isHidden) {
                    actionsHtml += '<button class="action-btn delete-btn delete-chat-btn" data-room-id="' + item.room_id + '" title="削除">削除</button>';
                }
            }

            const gameTitle = escapeHtml(item.title || item.room_name || 'タイトルなし');
            const opponentLabel = escapeHtml(getOpponentTeamLabel(item) || (isBoard ? '—' : '—'));
            const shortTime = new Date(item.created_at).toLocaleString('ja-JP', { month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' });

            return '<article class="' + cardClasses.join(' ') + ' timeline-card" data-timeline-key="' + escapeHtml(getTimelineItemKey(item)) + '" data-item-type="' + item.item_type + '" data-item-index="' + index + '" data-message-id="' + (messageId || '') + '" data-room-id="' + (roomId || '') + '" data-unread-count="' + unreadCount + '" data-action-required="' + (actionRequired ? '1' : '0') + '">' +
                '<div class="timeline-card__rail" aria-hidden="true"><span class="timeline-card__dot"></span></div>' +
                '<div class="timeline-card__body">' +
                '<div class="timeline-item-header"><div class="timeline-badges">' + badges +
                (actionRequired ? '<span class="action-required-badge">要対応</span>' : '') +
                '</div><div class="timeline-header-right">' +
                (unreadCount > 0 ? '<span class="unread-count-badge timeline-card__unread">' + unreadCount + '</span>' : '') +
                '<time class="timeline-time" datetime="' + escapeHtml(item.created_at || '') + '">' + shortTime + '</time></div></div>' +
                '<h3 class="timeline-item-title timeline-card__game-title">' + gameTitle + '</h3>' +
                (opponentLabel && opponentLabel !== '—' ? '<p class="timeline-card__opponent">相手: ' + opponentLabel + '</p>' : '') +
                '<p class="timeline-item-content timeline-item-content--collapsed timeline-card__preview" data-full="' + fullContentEscaped + '">' + previewEscaped + '</p>' +
                (hasMore ? '<button type="button" class="detail-toggle-btn" data-expanded="0">続きを読む</button>' : '') +
                '<div class="timeline-item-footer timeline-card__footer"><span class="timeline-author">' + escapeHtml(item.author_name || 'システム') + '</span><div class="timeline-item-actions">' + actionsHtml + '</div></div>' +
                '</div></article>';
        }

        function openChatRoom(roomId) {
            if (!roomId || roomId === 'null') return;
            window.location.href = '/team-chat?room_id=' + encodeURIComponent(roomId);
        }

        function deleteMessage(messageId) {
            if (!confirm('このメッセージを削除しますか？')) return;
            fetch('/wp-json/aidunite/v1/messages/' + messageId, {
                method: 'DELETE',
                headers: { 'X-WP-Nonce': aidunite_messaging.nonce }
            })
            .then(response => {
                if (response.ok) loadTimeline();
                else alert('メッセージの削除に失敗しました');
            })
            .catch(error => {
                console.error('削除エラー:', error);
                alert('メッセージの削除に失敗しました');
            });
        }

        function deleteChatRoom(roomId) {
            if (!confirm('このチャットルームを削除しますか？')) return;
            fetch('/wp-json/aidunite/v1/chats/' + roomId, {
                method: 'DELETE',
                headers: { 'X-WP-Nonce': aidunite_messaging.nonce }
            })
            .then(response => {
                if (response.ok) loadTimeline();
                else alert('チャットルームの削除に失敗しました');
            })
            .catch(error => {
                console.error('削除エラー:', error);
                alert('チャットルームの削除に失敗しました');
            });
        }

        document.addEventListener('click', function(e) {
            const timelineItem = e.target.closest('.timeline-item');
            if (!timelineItem) return;
            if (e.target.closest('.timeline-item-actions')) return;
            if (e.target.closest('.detail-toggle-btn')) return;
            const itemType = timelineItem.dataset.itemType;
            const roomId = timelineItem.dataset.roomId;
            if (itemType === 'board' && roomId) openChatRoom(roomId);
            else if (itemType === 'chat' && roomId) openChatRoom(roomId);
        });

        document.addEventListener('visibilitychange', function() {
            if (!document.hidden) {
                scheduleTimelinePolling(0);
            } else {
                scheduleTimelinePolling(IDLE_POLL_MS);
            }
        });

        window.addEventListener('beforeunload', function() {
            if (timelinePollTimer) {
                clearTimeout(timelinePollTimer);
                timelinePollTimer = null;
            }
        });

        window.loadTimeline = loadTimeline;
    });
})();
