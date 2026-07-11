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

    function setIconButtonContent(button, iconBasename, text, iconClassName, textClassName) {
        if (!button) {
            return;
        }
        button.textContent = '';
        const iconWrap = document.createElement('span');
        iconWrap.className = iconClassName || 'post-icon';
        if (iconBasename && typeof AidUniteThemeIcons !== 'undefined') {
            iconWrap.innerHTML = AidUniteThemeIcons.html(iconBasename, 18);
        }
        button.appendChild(iconWrap);
        const label = document.createElement('span');
        label.className = textClassName || 'post-text';
        label.textContent = String(text || '');
        button.appendChild(label);
    }

    function captureIconButtonHtml(button) {
        if (!button || button.dataset.originalHtml) {
            return;
        }
        button.dataset.originalHtml = button.innerHTML;
    }

    function restoreIconButtonHtml(button, iconBasename, text, iconClassName, textClassName) {
        if (!button) {
            return;
        }
        if (button.dataset.originalHtml) {
            button.innerHTML = button.dataset.originalHtml;
            return;
        }
        setIconButtonContent(button, iconBasename, text, iconClassName, textClassName);
    }

    function relocateCommunicationDomNodes() {
        if (!document.body) {
            return;
        }
        var wrap = document.querySelector('.new-message-btn-fixed-wrap');
        if (wrap) {
            document.body.appendChild(wrap);
        }
        ['startChatModal', 'messageModal', 'timelineDetailModal'].forEach(function(id) {
            var el = document.getElementById(id);
            if (el) {
                document.body.appendChild(el);
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        relocateCommunicationDomNodes();

        const newMessageBtn = document.getElementById('newMessageBtn');
        const messageModal = document.getElementById('messageModal');
        const modalClose = document.getElementById('modalClose');
        const cancelBtn = document.getElementById('cancelBtn');
        const messageForm = document.getElementById('messageForm');
        const timelineList = document.getElementById('timelineList');
        const filterBtns = document.querySelectorAll('.filter-btn');
        const postBtn = document.getElementById('postBtn');
        const draftBtn = document.getElementById('draftBtn');
        const timelineDetailModal = document.getElementById('timelineDetailModal');
        const timelineDetailModalClose = document.getElementById('timelineDetailModalClose');
        const timelineDetailModalChatBtn = document.getElementById('timelineDetailModalChatBtn');
        let timelineDetailModalLastFocus = null;
        let timelineDetailModalRoomId = null;

        const CONTENT_PREVIEW_LEN = 120;

        function isContentPreviewTruncated(rawContent) {
            const text = String(rawContent || '').trim();
            if (!text) return false;
            if (text.length > CONTENT_PREVIEW_LEN) return true;
            return text.indexOf('\n') !== -1;
        }

        function getTimelineItemFromCard(card) {
            if (!card) return null;
            const key = card.getAttribute('data-timeline-key');
            if (!key) return null;
            for (let i = 0; i < filteredItems.length; i += 1) {
                if (getTimelineItemKey(filteredItems[i]) === key) {
                    return filteredItems[i];
                }
            }
            return null;
        }

        function getTimelineDetailTypeLabel(item) {
            const isBoard = item && item.item_type === 'board';
            const isHidden = isTimelineChatHidden(item);
            const roomStatus = (item && item.room_status) || 'active';
            if (isBoard) return 'お知らせ';
            if (isHidden) return '削除済み';
            if (roomStatus === 'completed') return '完了済み';
            return 'チャット';
        }

        function getTimelineDetailTypeClass(item) {
            if (!item) return 'chat';
            if (item.item_type === 'board') return 'board';
            if (isTimelineChatHidden(item)) return 'deleted';
            if ((item.room_status || 'active') === 'completed') return 'completed';
            return 'chat';
        }

        function closeTimelineDetailModal() {
            if (!timelineDetailModal || !timelineDetailModal.classList.contains('is-open')) return;
            timelineDetailModal.classList.remove('is-open');
            timelineDetailModal.setAttribute('aria-hidden', 'true');
            timelineDetailModal.setAttribute('hidden', 'hidden');
            document.body.classList.remove('comm-timeline-detail-modal-open');
            timelineDetailModalRoomId = null;
            if (timelineDetailModalChatBtn) {
                timelineDetailModalChatBtn.hidden = true;
            }
            if (timelineDetailModalLastFocus && typeof timelineDetailModalLastFocus.focus === 'function') {
                timelineDetailModalLastFocus.focus();
            }
            timelineDetailModalLastFocus = null;
        }

        function openTimelineDetailModal(item) {
            if (!timelineDetailModal || !item) return;

            const titleEl = document.getElementById('timelineDetailModalTitle');
            const timeEl = document.getElementById('timelineDetailModalTime');
            const bodyEl = document.getElementById('timelineDetailModalBody');
            const metaEl = document.getElementById('timelineDetailModalMeta');
            const rawContent = String(item.content || '').replace(/\\n/g, '\n').trim();
            const gameTitle = item.title || item.room_name || 'タイトルなし';
            const opponentLabel = getOpponentTeamLabel(item);
            const isBoard = item.item_type === 'board';
            const roomId = isBoard
                ? (item.thread_room_id || item.room_id || null)
                : (item.room_id || null);

            if (titleEl) titleEl.textContent = gameTitle;
            if (timeEl) {
                const createdAt = item.created_at || '';
                timeEl.textContent = createdAt
                    ? new Date(createdAt).toLocaleString('ja-JP', {
                        year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit'
                    })
                    : '';
                if (createdAt) {
                    timeEl.setAttribute('datetime', createdAt);
                } else {
                    timeEl.removeAttribute('datetime');
                }
            }
            if (bodyEl) {
                bodyEl.textContent = rawContent || (isBoard ? '本文がありません。' : 'メッセージがありません。');
            }
            if (metaEl) {
                metaEl.textContent = '';
                const typeClass = getTimelineDetailTypeClass(item);
                const typeLabel = document.createElement('span');
                typeLabel.className = 'timeline-card-type-label timeline-card-type-label--' + typeClass;
                typeLabel.textContent = getTimelineDetailTypeLabel(item);
                metaEl.appendChild(typeLabel);
                if (item.priority && item.priority !== 'normal') {
                    const priClass = timelinePriorityClass(item.priority);
                    if (priClass) {
                        const badge = document.createElement('span');
                        badge.className = 'priority-badge ' + priClass;
                        badge.textContent = getPriorityLabel(item.priority);
                        metaEl.appendChild(badge);
                    }
                }
                if (opponentLabel) {
                    const opponent = document.createElement('span');
                    opponent.className = 'comm-timeline-detail-modal__opponent';
                    opponent.textContent = '相手: ' + opponentLabel;
                    metaEl.appendChild(opponent);
                }
                if (item.author_name) {
                    const author = document.createElement('span');
                    author.className = 'comm-timeline-detail-modal__author';
                    author.textContent = item.author_name;
                    metaEl.appendChild(author);
                }
            }

            timelineDetailModalRoomId = roomId || null;
            if (timelineDetailModalChatBtn) {
                if (roomId) {
                    timelineDetailModalChatBtn.hidden = false;
                    timelineDetailModalChatBtn.textContent = isBoard ? 'チャットを開く' : 'チャットルームを開く';
                } else {
                    timelineDetailModalChatBtn.hidden = true;
                }
            }

            timelineDetailModalLastFocus = document.activeElement;
            timelineDetailModal.removeAttribute('hidden');
            timelineDetailModal.setAttribute('aria-hidden', 'false');
            timelineDetailModal.classList.add('is-open');
            document.body.classList.add('comm-timeline-detail-modal-open');

            const focusTarget = timelineDetailModalClose || timelineDetailModal.querySelector('[data-close-timeline-detail]');
            if (focusTarget && typeof focusTarget.focus === 'function') {
                focusTarget.focus();
            }
        }

        function bindTimelineDetailModalEvents() {
            if (!timelineDetailModal) return;

            timelineDetailModal.querySelectorAll('[data-close-timeline-detail]').forEach(function(el) {
                el.addEventListener('click', function() {
                    closeTimelineDetailModal();
                });
            });

            if (timelineDetailModalChatBtn) {
                timelineDetailModalChatBtn.addEventListener('click', function() {
                    const roomId = timelineDetailModalRoomId;
                    closeTimelineDetailModal();
                    if (roomId) openChatRoom(roomId);
                });
            }

            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && timelineDetailModal.classList.contains('is-open')) {
                    e.preventDefault();
                    closeTimelineDetailModal();
                }
            });
        }
        bindTimelineDetailModalEvents();

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

        /** タイムライン表示用未読数（完了済みチャットは 0） */
        function getTimelineDisplayUnread(item) {
            if (!item) return 0;
            if (item.item_type === 'chat' && (item.room_status || 'active') === 'completed') return 0;
            return item.unread_count || 0;
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
                        startChatMemberList.innerHTML = typeof aiduniteCompactEmptyHtml === 'function'
                            ? aiduniteCompactEmptyHtml('チームに他のメンバーがいません', 'start-chat-no-members')
                            : '<p class="start-chat-no-members">チームに他のメンバーがいません</p>';
                    } else {
                        startChatMembers.forEach(function(m) {
                            const label = document.createElement('label');
                            label.className = 'start-chat-member-item';
                            const checkbox = document.createElement('input');
                            checkbox.type = 'checkbox';
                            checkbox.className = 'start-chat-member-check';
                            checkbox.dataset.userId = String(Number(m.user_id) || 0);
                            const name = document.createElement('span');
                            name.className = 'start-chat-member-name';
                            name.textContent = m.display_name || ('ユーザー#' + m.user_id);
                            label.appendChild(checkbox);
                            label.appendChild(document.createTextNode(' '));
                            label.appendChild(name);
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
            .catch(function (err) {
                console.warn('start-chat members fetch failed:', err);
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
                        aiduniteToast(result.data && result.data.error ? result.data.error : 'チャットの作成に失敗しました', 'error');
                    }
                })
                .catch(function() {
                    aiduniteToast('チャットの作成に失敗しました', 'error');
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
                captureIconButtonHtml(postBtn);
                postBtn.disabled = true;
                setIconButtonContent(postBtn, 'hourglass_empty', '投稿中...', 'post-icon', 'post-text');

                const formData = new FormData(messageForm);
                formData.append('action', 'post_message');
                formData.append('team_id', getTeamId());

                postMessage(formData).then(() => {
                    aiduniteToast('メッセージを投稿しました！', 'success');
                    closeModal();
                    loadTimeline();
                }).catch(() => {
                    aiduniteToast('投稿に失敗しました。もう一度お試しください。', 'error');
                }).finally(() => {
                    postBtn.disabled = false;
                    restoreIconButtonHtml(postBtn, 'send', '投稿', 'post-icon', 'post-text');
                });
            });
        }

        if (draftBtn) {
            draftBtn.addEventListener('click', function() {
                const formData = new FormData(messageForm);
                formData.append('action', 'save_message_draft');
                formData.append('team_id', getTeamId());

                captureIconButtonHtml(draftBtn);
                draftBtn.disabled = true;
                setIconButtonContent(draftBtn, 'hourglass_empty', '保存中...', 'draft-icon', 'draft-text');

                saveMessageDraft(formData).then(() => {
                    aiduniteToast('下書きを保存しました！', 'success');
                }).catch(() => {
                    aiduniteToast('保存に失敗しました。', 'error');
                }).finally(() => {
                    draftBtn.disabled = false;
                    restoreIconButtonHtml(draftBtn, 'save', '下書き保存', 'draft-icon', 'draft-text');
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
                    timelineList.innerHTML = typeof aiduniteListLoadingHtml === 'function'
                        ? aiduniteListLoadingHtml('タイムラインを読み込み中...')
                        : '<div class="loading-message">タイムラインを読み込み中...</div>';
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
                    timelineList.innerHTML = typeof aiduniteListErrorHtml === 'function'
                        ? aiduniteListErrorHtml('タイムラインの読み込みに失敗しました')
                        : '<div class="loading-message">タイムラインの読み込みに失敗しました</div>';
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
                    el.setAttribute('aria-label', isCountMode ? (name + ' ' + num + '件') : ('未読' + num + '件'));
                    el.setAttribute('aria-hidden', 'true');
                    el.classList.toggle('has-unread', !isCountMode);
                    el.classList.toggle('has-count', isCountMode);
                    if (btn) btn.setAttribute('aria-label', name + ' ' + (isCountMode ? num + '件' : '未読' + num + '件'));
                } else {
                    el.textContent = '';
                    el.removeAttribute('aria-label');
                    el.setAttribute('aria-hidden', 'true');
                    el.classList.remove('has-unread');
                    el.classList.remove('has-count');
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
                    String(getTimelineDisplayUnread(item)) + ':' +
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
            const displayUnread = getTimelineDisplayUnread(item);
            card.classList.toggle('timeline-item--action-required', actionRequired);
            card.setAttribute('data-unread-count', String(displayUnread));
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
                const unread = displayUnread;
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
                const contentPreview = rawContent.length > CONTENT_PREVIEW_LEN
                    ? rawContent.substring(0, CONTENT_PREVIEW_LEN)
                    : rawContent;
                contentEl.setAttribute('data-full', escapeHtml(rawContent));
                if (!contentEl.classList.contains('timeline-item-content--expanded')) {
                    contentEl.textContent = contentPreview;
                }
                const hasMore = isContentPreviewTruncated(rawContent);
                let toggleBtn = card.querySelector('.detail-toggle-btn');
                if (hasMore && !toggleBtn) {
                    const previewEl = card.querySelector('.timeline-card__preview');
                    if (previewEl) {
                        previewEl.insertAdjacentHTML('afterend', '<button type="button" class="detail-toggle-btn" data-expanded="0">全文を見る</button>');
                        toggleBtn = card.querySelector('.detail-toggle-btn');
                        if (toggleBtn) {
                            toggleBtn.addEventListener('click', function(e) {
                                e.stopPropagation();
                                const timelineItem = getTimelineItemFromCard(card);
                                if (timelineItem) openTimelineDetailModal(timelineItem);
                            });
                        }
                    }
                } else if (!hasMore && toggleBtn) {
                    toggleBtn.remove();
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

        function asideEmptyHtml() {
            if (typeof aiduniteCompactEmptyHtml === 'function') {
                return aiduniteCompactEmptyHtml('要対応はありません', 'communication-aside-empty');
            }
            return '<p class="communication-aside-empty" role="status">要対応はありません</p>';
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
                if (!asideList.querySelector('.communication-aside-empty, .aidunite-compact-empty')) {
                    asideList.innerHTML = asideEmptyHtml();
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
                asideList.innerHTML = asideEmptyHtml();
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
                timelineList.innerHTML = typeof aiduniteEmptyStateHtml === 'function'
                    ? aiduniteEmptyStateHtml({
                        title: 'アイテムがありません',
                        message: 'フィルターを変更するか、新しいお知らせ・チャットが届くのをお待ちください。',
                        type: 'default'
                    })
                    : '<div class="no-messages">アイテムがありません</div>';
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
                    const item = getTimelineItemFromCard(card);
                    if (item) openTimelineDetailModal(item);
                });
            });
        }

        function getPriorityLabel(priority) {
            const labels = { 'urgent': '緊急', 'important': '重要' };
            return labels[priority] || '';
        }

        function timelineTypeClass(item) {
            if (item.item_type === 'board') {
                return 'board';
            }
            const roomStatus = item.room_status || 'active';
            if (isTimelineChatHidden(item)) {
                return 'deleted';
            }
            if (roomStatus === 'completed') {
                return 'completed';
            }
            return 'chat';
        }

        function timelinePriorityClass(priority) {
            return priority === 'urgent' || priority === 'important' ? priority : '';
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
            let typeClass = timelineTypeClass(item);
            let badges = '<span class="timeline-card-type-label timeline-card-type-label--' + typeClass + '">' + escapeHtml(typeLabel) + '</span>';
            const priClass = timelinePriorityClass(item.priority);
            if (priClass) {
                badges += '<span class="priority-badge ' + priClass + '">' + escapeHtml(getPriorityLabel(item.priority)) + '</span>';
            }

            const time = new Date(item.created_at).toLocaleString('ja-JP', {
                year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit'
            });
            /* チャットはAPIで「最新1件」の内容が content に入る。お知らせは投稿本文。 */
            const rawContent = (item.content || '').replace(/\\n/g, '\n');
            const isUrgent = item.priority === 'urgent';
            /* 2行表示に収まるようプレビュー長を抑え、途中切れを防ぐ */
            const previewLen = CONTENT_PREVIEW_LEN;
            const hasMore = isContentPreviewTruncated(rawContent);
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

            const safeMessageId = escapeHtml(String(messageId || ''));
            const safeRoomId = escapeHtml(String(roomId || ''));
            const safeItemRoomId = escapeHtml(String(item.room_id || ''));

            let actionsHtml = '';
            if (isBoard) {
                if (roomId) actionsHtml += '<button class="action-btn chat-btn open-board-chat-btn" data-room-id="' + safeRoomId + '" title="チャット" type="button">チャット</button>';
                if (canDeleteBoard) actionsHtml += '<button class="action-btn delete-btn delete-message-btn" data-message-id="' + safeMessageId + '" title="削除" type="button">削除</button>';
            } else {
                actionsHtml += '<button class="action-btn chat-btn" data-room-id="' + safeItemRoomId + '" title="チャット">チャット</button>';
                if (!isHidden) {
                    actionsHtml += '<button class="action-btn delete-btn delete-chat-btn" data-room-id="' + safeItemRoomId + '" title="削除">削除</button>';
                }
            }

            const gameTitle = escapeHtml(item.title || item.room_name || 'タイトルなし');
            const opponentLabel = escapeHtml(getOpponentTeamLabel(item) || (isBoard ? '—' : '—'));
            const shortTime = new Date(item.created_at).toLocaleString('ja-JP', { month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' });
            const displayUnread = getTimelineDisplayUnread(item);

            return '<article class="' + cardClasses.join(' ') + ' timeline-card" data-timeline-key="' + escapeHtml(getTimelineItemKey(item)) + '" data-item-type="' + (item.item_type === 'board' ? 'board' : 'chat') + '" data-item-index="' + index + '" data-message-id="' + safeMessageId + '" data-room-id="' + safeRoomId + '" data-unread-count="' + displayUnread + '" data-action-required="' + (actionRequired ? '1' : '0') + '">' +
                '<div class="timeline-card__rail" aria-hidden="true"><span class="timeline-card__dot"></span></div>' +
                '<div class="timeline-card__body">' +
                '<div class="timeline-item-header"><div class="timeline-badges">' + badges +
                (actionRequired ? '<span class="action-required-badge">要対応</span>' : '') +
                '</div><div class="timeline-header-right">' +
                (displayUnread > 0 ? '<span class="unread-count-badge timeline-card__unread">' + displayUnread + '</span>' : '') +
                '<time class="timeline-time" datetime="' + escapeHtml(item.created_at || '') + '">' + shortTime + '</time></div></div>' +
                '<h3 class="timeline-item-title timeline-card__game-title">' + gameTitle + '</h3>' +
                (opponentLabel && opponentLabel !== '—' ? '<p class="timeline-card__opponent">相手: ' + opponentLabel + '</p>' : '') +
                '<p class="timeline-item-content timeline-item-content--collapsed timeline-card__preview" data-full="' + fullContentEscaped + '">' + previewEscaped + '</p>' +
                (hasMore ? '<button type="button" class="detail-toggle-btn" data-expanded="0">全文を見る</button>' : '') +
                '<div class="timeline-item-footer timeline-card__footer"><span class="timeline-author">' + escapeHtml(item.author_name || 'システム') + '</span><div class="timeline-item-actions">' + actionsHtml + '</div></div>' +
                '</div></article>';
        }

        function openChatRoom(roomId) {
            if (!roomId || roomId === 'null') return;
            window.location.href = '/team-chat?room_id=' + encodeURIComponent(roomId);
        }

        function deleteMessage(messageId) {
            aiduniteConfirm({
                message: 'このメッセージを削除しますか？',
                confirmLabel: '削除する',
                confirmVariant: 'danger',
                onConfirm: function () {
                    fetch('/wp-json/aidunite/v1/messages/' + messageId, {
                        method: 'DELETE',
                        headers: { 'X-WP-Nonce': aidunite_messaging.nonce }
                    })
                    .then(response => {
                        if (response.ok) loadTimeline();
                        else aiduniteToast('メッセージの削除に失敗しました', 'error');
                    })
                    .catch(error => {
                        console.error('削除エラー:', error);
                        aiduniteToast('メッセージの削除に失敗しました', 'error');
                    });
                }
            });
        }

        function deleteChatRoom(roomId) {
            aiduniteConfirm({
                message: 'このチャットルームを削除しますか？',
                confirmLabel: '削除する',
                confirmVariant: 'danger',
                onConfirm: function () {
                    fetch('/wp-json/aidunite/v1/chats/' + roomId, {
                        method: 'DELETE',
                        headers: { 'X-WP-Nonce': aidunite_messaging.nonce }
                    })
                    .then(response => {
                        if (response.ok) loadTimeline();
                        else aiduniteToast('チャットルームの削除に失敗しました', 'error');
                    })
                    .catch(error => {
                        console.error('削除エラー:', error);
                        aiduniteToast('チャットルームの削除に失敗しました', 'error');
                    });
                }
            });
        }

        document.addEventListener('click', function(e) {
            const timelineItem = e.target.closest('.timeline-item');
            if (!timelineItem) return;
            if (e.target.closest('.timeline-item-actions')) return;
            if (e.target.closest('.detail-toggle-btn')) return;
            const item = getTimelineItemFromCard(timelineItem);
            if (!item) return;
            const itemType = timelineItem.dataset.itemType;
            const roomId = timelineItem.dataset.roomId;
            if (itemType === 'board') {
                openTimelineDetailModal(item);
            } else if (itemType === 'chat' && roomId) {
                openChatRoom(roomId);
            }
        });

        document.addEventListener('visibilitychange', function() {
            if (!document.hidden) {
                scheduleTimelinePolling(0);
            } else {
                scheduleTimelinePolling(IDLE_POLL_MS);
            }
        });

        window.addEventListener('pageshow', function() {
            scheduleTimelinePolling(0);
        });

        window.addEventListener('focus', function() {
            if (!document.hidden) {
                scheduleTimelinePolling(0);
            }
        });

        try {
            if (sessionStorage.getItem('aidunite_timeline_force_refresh')) {
                sessionStorage.removeItem('aidunite_timeline_force_refresh');
                scheduleTimelinePolling(0);
            }
        } catch (e) {
            console.debug('sessionStorage timeline refresh skipped:', e);
        }

        window.addEventListener('beforeunload', function() {
            if (timelinePollTimer) {
                clearTimeout(timelinePollTimer);
                timelinePollTimer = null;
            }
        });

        window.loadTimeline = loadTimeline;
    });
})();
