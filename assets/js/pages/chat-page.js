(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    var cfg = typeof aiduniteChatPage !== 'undefined' ? aiduniteChatPage : {};
    var msgCfg = typeof aidunite_messaging !== 'undefined' ? aidunite_messaging : {};
    var restBase = (msgCfg.rest_url || cfg.restUrl || '/wp-json/aidunite/v1/').replace(/\/?$/, '/');

    const chatMessages = document.getElementById('chatMessages');
    const messagesContainer = chatMessages.querySelector('.messages-container');
    const messageInput = document.getElementById('messageInput');
    const sendBtn = document.getElementById('sendBtn');
    const attachBtn = document.getElementById('attachBtn');
    const cameraBtn = document.getElementById('cameraBtn');
    const imageBtn = document.getElementById('imageBtn');
    const fileInput = document.getElementById('fileInput');
    const imageInput = document.getElementById('imageInput');
    const replyToCancelBtn = document.getElementById('replyToCancelBtn');

    if (replyToCancelBtn) replyToCancelBtn.addEventListener('click', function() { clearReplyState(); });

    let messages = [];
    let currentRoomId = parseInt(String(cfg.chatId || 0), 10) || 0;

    if (!currentRoomId && typeof window !== 'undefined' && window.location && window.location.search) {
        var params = new URLSearchParams(window.location.search);
        currentRoomId = parseInt(params.get('room_id') || params.get('chat_id') || '', 10) || 0;
    }

    const nonce = cfg.restNonce || msgCfg.nonce || '';
    const userId = parseInt(String(cfg.userId || msgCfg.user_id || 0), 10) || 0;
    const roomType = cfg.roomType || '';
    let eventSource = null;
    const ACTIVE_POLL_MS = 1000;
    const IDLE_POLL_MS = 10000;
    let pollTimer = null;
    let isPollingRequestInFlight = false;
    const chatIconsUrl = cfg.chatIconsUrl || '';
    var replyToMessageId = null;
    var replyToMessagePreview = '';
    var lastMarkedReadMessageId = 0;

    function markRoomAsRead(messageId) {
        var messageIdInt = parseInt(messageId || 0, 10) || 0;
        if (!currentRoomId) return Promise.resolve();
        if (messageIdInt > 0 && messageIdInt <= lastMarkedReadMessageId) return Promise.resolve();

        return fetch(restBase + 'chats/' + currentRoomId + '/read', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': nonce
            },
            body: JSON.stringify({ message_id: messageIdInt > 0 ? messageIdInt : 0 })
        }).then(function(response) {
            if (response.ok) {
                if (messageIdInt > 0) {
                    lastMarkedReadMessageId = messageIdInt;
                } else {
                    lastMarkedReadMessageId = Number.MAX_SAFE_INTEGER;
                }
                return;
            }
            return response.json().catch(function() { return {}; }).then(function(data) {
                var msg = (data && data.error) ? data.error : ('HTTP ' + response.status);
                throw new Error(msg);
            });
        }).catch(function(error) {
            console.warn('既読更新に失敗:', error);
        });
    }

    // メッセージ送信（返信時は parent_message_id を付与し、該当吹き出しの下に表示される）
    function sendMessage() {
        const content = messageInput.value.trim();
        if (!content) return;

        if (!currentRoomId && window.location && window.location.search) {
            var params = new URLSearchParams(window.location.search);
            currentRoomId = parseInt(params.get('room_id') || params.get('chat_id') || '', 10) || 0;
        }
        if (!currentRoomId) {
            aiduniteToast('チャットルームの初期化に失敗しました。\n\n・URLに room_id / chat_id / team_id / match_id が含まれているか確認してください。\n・チャット一覧から再度ルームを開き直すか、ページを再読み込みしてください。', 'error');
            return;
        }

        sendBtn.disabled = true;
        sendBtn.classList.add('send-btn--loading');

        const messageData = {
            room_id: currentRoomId,
            message_type: 'text',
            content: content
        };
        if (replyToMessageId) {
            messageData.parent_message_id = parseInt(replyToMessageId, 10);
        }

        fetch(restBase + 'chats/' + currentRoomId + '/messages', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': nonce
            },
            body: JSON.stringify(messageData)
        }).then(response => {
            if (response.ok) {
                messageInput.value = '';
                messageInput.style.height = 'auto';
                clearReplyState();
                loadMessages();
            } else {
                return response.json().catch(function() { return {}; }).then(function(data) {
                    var msg = (data && data.error) ? data.error : ('HTTP ' + response.status);
                    throw new Error(msg);
                });
            }
        }).catch(error => {
            console.error('送信エラー:', error);
            aiduniteToast('メッセージの送信に失敗しました: ' + (error && error.message ? error.message : String(error)), 'error');
        }).finally(() => {
            sendBtn.disabled = false;
            sendBtn.classList.remove('send-btn--loading');
        });
    }

    // 吹き出しへのリアクション追加/削除（APIでトグル。新規メッセージは作らない）
    function sendReaction(messageId, reactionType) {
        if (!currentRoomId || !messageId) return;
        fetch(restBase + 'chats/' + currentRoomId + '/messages/' + messageId + '/reactions', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': nonce
            },
            body: JSON.stringify({ reaction_type: reactionType })
        }).then(function(res) {
            if (res.ok) loadMessages();
        }).catch(function(err) {
            console.error('リアクションエラー:', err);
        });
    }

    function setReplyState(msgId, preview) {
        replyToMessageId = msgId;
        replyToMessagePreview = (preview || '').trim();
        if (replyToMessagePreview.length > 60) replyToMessagePreview = replyToMessagePreview.slice(0, 60) + '…';
        var el = document.getElementById('replyToIndicator');
        if (el) {
            el.classList.add('is-visible');
            var textEl = el.querySelector('.reply-to-preview');
            if (textEl) textEl.textContent = replyToMessagePreview ? '「' + replyToMessagePreview + '」へ返信' : '返信';
        }
        messageInput.focus();
    }

    function clearReplyState() {
        replyToMessageId = null;
        replyToMessagePreview = '';
        var el = document.getElementById('replyToIndicator');
        if (el) el.classList.remove('is-visible');
    }

    var reactionPickerEl = null;

    function closeReactionPicker() {
        if (reactionPickerEl) {
            reactionPickerEl.remove();
            reactionPickerEl = null;
        }
    }

    function closeAllMessageActions() {
        if (!chatMessages) return;
        chatMessages.querySelectorAll('.message.show-actions').forEach(function(el) { el.classList.remove('show-actions'); });
        closeReactionPicker();
    }

    function isMobile() {
        return window.matchMedia('(max-width: 768px)').matches;
    }

    function toggleReaction(messageId, reactionKey) {
        sendReaction(messageId, reactionKey);
    }

    function openReactionPicker(anchorEl, messageId) {
        closeReactionPicker();
        var emojis = [
            { key: 'thumb_up', label: '👍' },
            { key: 'favorite', label: '⭐' },
            { key: 'ok', label: '🆗' },
            { key: 'laugh', label: '😂' },
            { key: 'pray', label: '🙏' },
            { key: 'fire', label: '🔥' },
            { key: 'clap', label: '👏' },
            { key: 'heart', label: '❤️' },
            { key: 'wow', label: '😮' },
            { key: 'sad', label: '😢' },
            { key: 'angry', label: '😡' },
            { key: 'check', label: '✅' }
        ];
        var picker = document.createElement('div');
        picker.className = 'reaction-picker';
        picker.setAttribute('role', 'dialog');
        picker.setAttribute('aria-label', 'リアクションを選択');
        picker.innerHTML = emojis.map(function(e) { return '<button type="button" data-reaction="' + escapeHtml(e.key) + '" aria-label="' + escapeHtml(e.key) + '">' + e.label + '</button>'; }).join('');
        document.body.appendChild(picker);
        reactionPickerEl = picker;
        var rect = anchorEl.getBoundingClientRect();
        picker.style.top = (rect.bottom + 6) + 'px';
        picker.style.left = Math.min(rect.left, window.innerWidth - 260) + 'px';

        picker.addEventListener('click', function(ev) {
            var btn = ev.target.closest('button[data-reaction]');
            if (!btn) return;
            var reactionKey = btn.getAttribute('data-reaction');
            toggleReaction(messageId, reactionKey);
            closeReactionPicker();
        });
    }

    document.addEventListener('click', function(e) {
        var inMessage = e.target.closest('.chat-messages .message');
        var inPicker = e.target.closest('.reaction-picker');
        if (!inMessage && !inPicker) closeAllMessageActions();
    });

    messagesContainer.addEventListener('click', async function(e) {
        var msgEl = e.target.closest('.chat-messages .message');
        if (!msgEl || msgEl.classList.contains('system-message-wrapper')) return;

        var pill = e.target.closest('.message-reaction-pill');
        if (pill) {
            var messageId = msgEl.dataset && msgEl.dataset.messageId ? msgEl.dataset.messageId : msgEl.getAttribute('data-message-id');
            var reactionKey = pill.dataset && pill.dataset.reaction ? pill.dataset.reaction : pill.getAttribute('data-reaction');
            if (!messageId || !reactionKey) {
                console.warn('reaction data missing', { messageId: messageId, reactionKey: reactionKey });
                return;
            }
            try {
                var res = await fetch(restBase + 'chats/' + currentRoomId + '/messages/' + messageId + '/reactions', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': nonce
                    },
                    body: JSON.stringify({ reaction_type: reactionKey })
                });
                var data = res.ok ? await res.json().catch(function() { return {}; }) : await res.json().catch(function() { return {}; });
                if (!res.ok) {
                    console.warn('reaction api failed', data);
                    return;
                }
                await loadMessages();
            } catch (err) {
                console.error('reaction error', err);
            }
            return;
        }

        var addBtn = e.target.closest('.message-action-btn[data-action="add_reaction"]');
        if (addBtn) {
            var msgId = msgEl.getAttribute('data-message-id');
            if (!msgId) return;
            var anchor = msgEl.querySelector('.message-content') || addBtn;
            openReactionPicker(anchor, msgId);
            return;
        }

        if (isMobile()) {
            var tappedContent = e.target.closest('.message-content');
            var tappedButtonOrPill = e.target.closest('.message-action-btn') || e.target.closest('.message-reaction-pill');
            if (tappedContent && !tappedButtonOrPill) {
                var already = msgEl.classList.contains('show-actions');
                closeAllMessageActions();
                if (!already) msgEl.classList.add('show-actions');
                return;
            }
        }

        var btn = e.target.closest('.message-action-btn');
        if (!btn) return;
        var action = btn.getAttribute('data-action');
        var msgId = msgEl.getAttribute('data-message-id') || '';
        var msgContent = (msgEl.getAttribute('data-content') || '').replace(/&quot;/g, '"').replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&amp;/g, '&');
        if (action === 'thumb_up') {
            sendReaction(msgId, 'thumb_up');
        } else if (action === 'favorite') {
            sendReaction(msgId, 'favorite');
        } else if (action === 'ok') {
            sendReaction(msgId, 'ok');
        } else if (action === 'reply') {
            setReplyState(msgId, msgContent);
        }
    });

    // モバイル: タッチで click が発火しない/遅延することがあるため touchend でも同じ処理を行う
    function handleReactionTap(e) {
        var target = e.target;
        var msgEl = target.closest('.chat-messages .message');
        if (!msgEl || msgEl.classList.contains('system-message-wrapper')) return false;

        var pill = target.closest('.message-reaction-pill');
        if (pill) {
            var messageId = msgEl.dataset && msgEl.dataset.messageId ? msgEl.dataset.messageId : msgEl.getAttribute('data-message-id');
            var reactionKey = pill.dataset && pill.dataset.reaction ? pill.dataset.reaction : pill.getAttribute('data-reaction');
            if (!messageId || !reactionKey) return false;
            fetch(restBase + 'chats/' + currentRoomId + '/messages/' + messageId + '/reactions', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
                body: JSON.stringify({ reaction_type: reactionKey })
            }).then(function(res) {
                if (res.ok) loadMessages();
            }).catch(function(err) { console.error('reaction error', err); });
            return true;
        }

        var addBtn = target.closest('.message-action-btn[data-action="add_reaction"]');
        if (addBtn) {
            var msgId = msgEl.getAttribute('data-message-id');
            if (!msgId) return false;
            var anchor = msgEl.querySelector('.message-content') || addBtn;
            openReactionPicker(anchor, msgId);
            return true;
        }

        var btn = target.closest('.message-action-btn');
        if (!btn) return false;
        var action = btn.getAttribute('data-action');
        var msgId = msgEl.getAttribute('data-message-id') || '';
        var msgContent = (msgEl.getAttribute('data-content') || '').replace(/&quot;/g, '"').replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&amp;/g, '&');
        if (action === 'thumb_up') { sendReaction(msgId, 'thumb_up'); return true; }
        if (action === 'favorite') { sendReaction(msgId, 'favorite'); return true; }
        if (action === 'ok') { sendReaction(msgId, 'ok'); return true; }
        if (action === 'reply') { setReplyState(msgId, msgContent); return true; }
        return false;
    }

    messagesContainer.addEventListener('touchend', function(e) {
        if (!e.changedTouches || e.changedTouches.length === 0) return;
        var t = e.changedTouches[0];
        var el = document.elementFromPoint(t.clientX, t.clientY);
        if (!el) return;
        var msgEl = el.closest('.chat-messages .message');
        if (!msgEl || msgEl.classList.contains('system-message-wrapper')) return;
        var isButtonOrPill = el.closest('.message-action-btn') || el.closest('.message-reaction-pill');
        if (!isButtonOrPill) return;
        if (handleReactionTap({ target: el })) {
            e.preventDefault();
        }
    }, { passive: false });

    // メッセージ読み込み（suppressRender: true のときは描画せず配列だけ返す）
    function loadMessages(opts) {
        opts = opts || {};
        var suppressRender = opts.suppressRender === true;
        if (!currentRoomId) {
            if (!suppressRender) {
                messagesContainer.innerHTML = '<div class="loading-message">チャットルームの初期化に失敗しました</div>';
            }
            return Promise.resolve([]);
        }

        return fetch(restBase + 'chats/' + currentRoomId + '/messages', {
            headers: {
                'X-WP-Nonce': nonce
            }
        }).then(function(response) {
            if (!response.ok) throw new Error('メッセージの読み込みに失敗しました');
            return response.json();
        }).then(function(data) {
            var allMessages = data.messages || [];
            var list = allMessages.filter(function(message) {
                if (!message.room_id) return false;
                var messageRoomId = parseInt(message.room_id, 10);
                var currentRoomIdInt = parseInt(currentRoomId, 10);
                return messageRoomId === currentRoomIdInt;
            });

            var latestMessageId = 0;
            for (var i = 0; i < list.length; i++) {
                var currentMessageId = parseInt(list[i].id, 10) || 0;
                if (currentMessageId > latestMessageId) latestMessageId = currentMessageId;
            }
            markRoomAsRead(latestMessageId);

            if (suppressRender) {
                messages = list;
                return list;
            }
            var hadMessages = messages.length > 0;
            var existingKeys = {};
            messages.forEach(function(m) { existingKeys[getMessageKey(m)] = true; });
            var newMessages = list.filter(function(m) { return !existingKeys[getMessageKey(m)]; });
            if (!hadMessages && list.length > 0) {
                renderMessages(list);
                messages = list;
            } else if (newMessages.length > 0) {
                appendNewMessages(newMessages);
                messages = list;
            } else {
                messages = list;
                renderMessages(list); /* リアクション等の更新を即時反映（新着がなくても再描画） */
            }
            if (typeof updateScrollToBottomButtonVisibility === 'function') updateScrollToBottomButtonVisibility();
            return list;
        }).catch(function(error) {
            console.error('読み込みエラー:', error);
            if (!suppressRender) {
                messagesContainer.innerHTML = '<div class="loading-message">メッセージの読み込みに失敗しました</div>';
            }
            return [];
        });
    }

    // pending を一覧にマージ（重複排除。pending を先頭に）
    function mergePending(list, pending) {
        if (!pending) return list;

        var pendingKey = pending.id
            ? 'id:' + String(pending.id)
            : 'sys:' + (pending.message_type || 'system') + ':' + (pending.created_at || '') + ':' + (pending.content || pending.message || '');

        var exists = list.some(function(m) {
            var key = m.id
                ? 'id:' + String(m.id)
                : 'sys:' + (m.message_type || 'system') + ':' + (m.created_at || '') + ':' + (m.content || m.message || '');
            return key === pendingKey;
        });

        if (exists) return list;
        return [pending].concat(list);
    }

    // メッセージの一意キー（重複判定・新着判定用）
    function getMessageKey(msg) {
        if (msg.id) return 'id:' + String(msg.id);
        return 'sys:' + (msg.message_type || 'system') + ':' + (msg.created_at || '') + ':' + (msg.content || msg.message || '');
    }

    // 最下部付近にいるか（閾値px以内）
    function isNearBottom(threshold) {
        if (!messagesContainer) return false;
        threshold = threshold || 80;
        return messagesContainer.scrollTop + messagesContainer.clientHeight >= messagesContainer.scrollHeight - threshold;
    }

    // 新着メッセージをマージして全体を再描画（返信が親の直下に来るようツリーで表示）
    function appendNewMessages(newMessages) {
        if (!newMessages.length || !messagesContainer) return;
        var merged = messages.slice();
        var seen = {};
        merged.forEach(function(m) { seen[getMessageKey(m)] = true; });
        newMessages.forEach(function(m) {
            if (!seen[getMessageKey(m)]) {
                merged.push(m);
                seen[getMessageKey(m)] = true;
            }
        });
        merged.sort(function(a, b) { return parseMessageDate(a.created_at) - parseMessageDate(b.created_at); });
        renderMessages(merged);
        if (typeof updateScrollToBottomButtonVisibility === 'function') updateScrollToBottomButtonVisibility();
    }

    // 親メッセージと返信をグループ化（Google Chat風：返信は該当吹き出しの直下に表示）
    function buildMessageTree(list) {
        var roots = [];
        var repliesByParent = {};
        for (var i = 0; i < list.length; i++) {
            var m = list[i];
            var pid = m.parent_message_id ? parseInt(m.parent_message_id, 10) : null;
            if (!pid) {
                roots.push(m);
            } else {
                if (!repliesByParent[pid]) repliesByParent[pid] = [];
                repliesByParent[pid].push(m);
            }
        }
        roots.sort(function(a, b) { return parseMessageDate(a.created_at) - parseMessageDate(b.created_at); });
        Object.keys(repliesByParent).forEach(function(pid) {
            repliesByParent[pid].sort(function(a, b) { return parseMessageDate(a.created_at) - parseMessageDate(b.created_at); });
        });
        return { roots: roots, repliesByParent: repliesByParent };
    }

    // メッセージ表示（日付区切り・返信は親の直下にスレッド表示・リアクション表示）
    function renderMessages(msgs) {
        if (messagesContainer) messagesContainer.removeAttribute('data-state');
        var list = msgs !== undefined ? msgs : messages;
        if (list.length === 0) {
            messagesContainer.innerHTML = '<div class="no-messages">メッセージがありません</div>';
            return;
        }
        var tree = buildMessageTree(list);
        var html = [];
        var lastDateKey = null;
        var lastSenderId = null;
        for (var r = 0; r < tree.roots.length; r++) {
            var root = tree.roots[r];
            var isSystem = root.message_type === 'system' || (root.sender_name && root.sender_name === 'システム');
            var d = parseMessageDate(root.created_at);
            var dateKey = d.getFullYear() + '-' + (d.getMonth() + 1) + '-' + d.getDate();
            if (dateKey !== lastDateKey) {
                lastDateKey = dateKey;
                html.push(createDateSeparator(root.created_at));
            }
            var isContinuation = !isSystem && lastSenderId !== null && String(lastSenderId) === String(root.sender_id);
            if (!isSystem) lastSenderId = root.sender_id;
            html.push(createMessageElement(root, { isContinuation: isContinuation, isSystem: isSystem, isReply: false }));
            var replies = tree.repliesByParent[root.id] || [];
            if (replies.length > 0) {
                var replyCount = replies.length;
                var separatorLabel = '返信 ' + replyCount + '件';
                html.push('<div class="message-thread-replies">');
                html.push('<div class="message-thread-separator" role="separator" aria-label="' + escapeHtml(separatorLabel) + '"><span class="message-thread-separator__label">' + escapeHtml(separatorLabel) + '</span><span class="message-thread-separator__line" aria-hidden="true"></span></div>');
                for (var i = 0; i < replies.length; i++) {
                    var reply = replies[i];
                    var rCont = !isSystem && lastSenderId !== null && String(lastSenderId) === String(reply.sender_id);
                    if (!reply.message_type || reply.message_type !== 'system') lastSenderId = reply.sender_id;
                    html.push(createMessageElement(reply, { isContinuation: rCont, isSystem: false, isReply: true }));
                }
                html.push('</div>');
            }
        }
        messagesContainer.innerHTML = html.join('');
        if (msgs !== undefined) messages = list;
    }

    // モバイル：メッセージ長押しでアクション（👍👌🆗返信）表示
    (function setupLongPressActions() {
        var longPressTimer = null;
        var LONG_PRESS_MS = 500;

        function clearTimer() {
            if (longPressTimer) {
                clearTimeout(longPressTimer);
                longPressTimer = null;
            }
        }

        function removeShowActions() {
            [].forEach.call(document.querySelectorAll('.message.show-actions'), function(el) {
                el.classList.remove('show-actions');
            });
        }

        chatMessages.addEventListener('touchstart', function(e) {
            var msg = e.target.closest('.message:not(.system-message-wrapper)');
            if (!msg) return;
            clearTimer();
            longPressTimer = setTimeout(function() {
                longPressTimer = null;
                removeShowActions();
                msg.classList.add('show-actions');
                setTimeout(removeShowActions, 2500);
            }, LONG_PRESS_MS);
        }, { passive: true });

        chatMessages.addEventListener('touchend', clearTimer, { passive: true });
        chatMessages.addEventListener('touchcancel', clearTimer, { passive: true });
    })();

    function parseMessageDate(createdAt) {
        if (typeof AidUniteDateUtils !== 'undefined' && typeof AidUniteDateUtils.parseChatCreatedAt === 'function') {
            return AidUniteDateUtils.parseChatCreatedAt(createdAt);
        }
        var s = String(createdAt || '').trim();
        var m = s.match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})(?::(\d{2}))?$/);
        if (m) {
            return new Date(Date.UTC(
                parseInt(m[1], 10),
                parseInt(m[2], 10) - 1,
                parseInt(m[3], 10),
                parseInt(m[4], 10),
                parseInt(m[5], 10),
                m[6] ? parseInt(m[6], 10) : 0
            ));
        }
        return new Date(createdAt);
    }

    // 日付区切りラベル（例: 2/24 火）
    function getDateSeparatorLabel(createdAt) {
        var d = parseMessageDate(createdAt);
        var w = ['日', '月', '火', '水', '木', '金', '土'][d.getDay()];
        return (d.getMonth() + 1) + '/' + d.getDate() + ' ' + w;
    }

    function createDateSeparator(createdAt) {
        var label = getDateSeparatorLabel(createdAt);
        return '<div class="message-date-separator" role="separator" aria-label="' + escapeHtml(label) + '">' +
            '<span class="message-date-separator__label">' + escapeHtml(label) + '</span></div>';
    }

    // 相対時刻（参考: 昨日 13:33 / 今日 10:00 / 25/01/15 13:33）
    function formatMessageTime(createdAt) {
        if (typeof AidUniteDateUtils !== 'undefined' && typeof AidUniteDateUtils.formatChatMessageTime === 'function') {
            return AidUniteDateUtils.formatChatMessageTime(createdAt);
        }
        var d = parseMessageDate(createdAt);
        var now = new Date();
        var today = new Date(now.getFullYear(), now.getMonth(), now.getDate()).getTime();
        var yesterday = today - 86400000;
        var t = d.getTime();
        var timeStr = d.toLocaleTimeString('ja-JP', { hour: '2-digit', minute: '2-digit' });
        if (t >= today) return '今日 ' + timeStr;
        if (t >= yesterday && t < today) return '昨日 ' + timeStr;
        var y = String(d.getFullYear()).slice(-2);
        var m = String(d.getMonth() + 1).padStart(2, '0');
        var day = String(d.getDate()).padStart(2, '0');
        return y + '/' + m + '/' + day + ' ' + timeStr;
    }

    // メッセージ要素作成（会話の塊・時刻は吹き出し右下・リアクション表示・返信時は message--reply）
    function createMessageElement(message, opts) {
        opts = opts || {};
        var isContinuation = opts.isContinuation;
        var isSystem = opts.isSystem;
        var isReply = opts.isReply === true;

        if (isSystem) {
            return createSystemMessagePill(message);
        }

        var isOwn = message.sender_id == userId;
        var senderName = message.sender_name || '不明';
        var timeStr = formatMessageTime(message.created_at);
        var reactions = message.reactions || {};
        var myReactions = message.my_reactions || [];

        var body = '';
        if (message.message_type === 'image' && message.file_url) {
            body = '<img src="' + escapeHtml(message.file_url) + '" class="message-image" alt="画像">';
        } else {
            body = '<div class="message-text">' + escapeHtml(message.content || '') + '</div>';
        }

        var avatarHtml = '<div class="message-avatar">' + escapeHtml(senderName.charAt(0)) + '</div>';
        var msgId = message.id ? String(message.id) : '';
        var rawContent = message.content || '';
        var msgContent = rawContent.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

        var teamName = message.team_name || message.sender_team_name || '';
        var metaHtml = isContinuation ? '' : (
            '<div class="message-meta">' +
            (teamName ? '<span class="message-team">' + escapeHtml(teamName) + '</span>' : '') +
            '<span class="message-sender">' + escapeHtml(senderName) + '</span>' +
            '</div>'
        );

        var thumbUpActive = myReactions.indexOf('thumb_up') >= 0 ? ' message-action-btn--active' : '';
        var favoriteActive = myReactions.indexOf('favorite') >= 0 ? ' message-action-btn--active' : '';
        var okActive = myReactions.indexOf('ok') >= 0 ? ' message-action-btn--active' : '';

        var reactionLabels = { thumb_up: '👍', favorite: '⭐', ok: '🆗', laugh: '😂', pray: '🙏', fire: '🔥', clap: '👏', heart: '❤️', wow: '😮', sad: '😢', angry: '😡', check: '✅' };
        var reactionsHtml = '';
        for (var rKey in reactions) {
            if (!reactions[rKey]) continue;
            var isMine = myReactions.indexOf(rKey) >= 0;
            var label = reactionLabels[rKey] !== undefined ? reactionLabels[rKey] : rKey;
            reactionsHtml += '<button type="button" class="message-reaction-pill' + (isMine ? ' message-reaction-pill--mine' : '') + '" data-reaction="' + escapeHtml(rKey) + '" aria-label="' + escapeHtml(rKey) + '"><span class="message-reaction-icon">' + label + '</span><span class="message-reaction-count">' + reactions[rKey] + '</span></button>';
        }
        if (reactionsHtml) reactionsHtml = '<div class="message-reactions">' + reactionsHtml + '</div>';

        var replyClass = isReply ? ' message--reply' : '';
        return '<div class="message ' + (isOwn ? 'own' : '') + (isContinuation ? ' message-continuation' : '') + replyClass + '" data-message-id="' + escapeHtml(msgId) + '" data-content="' + msgContent + '">' +
            avatarHtml +
            '<div class="message-main">' +
            '<div class="message-content">' +
            metaHtml +
            '<div class="message-body">' + body + '</div>' +
            '<div class="message-footer">' +
            '<div class="message-actions">' +
            '<button type="button" class="message-action-btn' + thumbUpActive + '" data-action="thumb_up" aria-label="いいね">👍</button>' +
            '<button type="button" class="message-action-btn' + favoriteActive + '" data-action="favorite" aria-label="お気に入り">⭐</button>' +
            '<button type="button" class="message-action-btn' + okActive + '" data-action="ok" aria-label="了解">🆗</button>' +
            '<button type="button" class="message-action-btn" data-action="reply" aria-label="返信">返信</button>' +
            '<button type="button" class="message-action-btn" data-action="add_reaction" aria-label="リアクション追加">＋</button>' +
            '</div>' +
            '<span class="message-time">' + escapeHtml(timeStr) + '</span>' +
            '</div>' +
            '</div>' +
            reactionsHtml +
            '</div></div>';
    }

    // システムメッセージ：吹き出しにせず中央寄せピル
    function createSystemMessagePill(message) {
        var timeStr = formatMessageTime(message.created_at);
        var title = message.title ? ('<div class="system-message-pill__title">' + escapeHtml(message.title) + '</div>') : '';
        var content = '<div class="system-message-pill__content">' + escapeHtml(message.content || '') + '</div>';
        return '<div class="message system-message-wrapper" data-message-id="' + escapeHtml(String(message.id)) + '">' +
            '<div class="system-message-pill" role="status">' +
            title + content +
            '<span class="system-message-pill__time">' + escapeHtml(timeStr) + '</span>' +
            '</div></div>';
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // 最下部にスクロール（「最新へ」ボタン押下時のみ）
    function scrollToBottom() {
        if (!messagesContainer) return;
        function doScroll() {
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }
        requestAnimationFrame(function() {
            doScroll();
            requestAnimationFrame(doScroll);
        });
    }

    // 最新へボタン（LINE風）：古い箇所にいる時だけ表示し、押下で最下部へ
    const scrollToBottomBtn = document.getElementById('scrollToBottomBtn');
    function updateScrollToBottomButtonVisibility() {
        if (!scrollToBottomBtn || !messagesContainer) return;
        var show = !isNearBottom(120);
        scrollToBottomBtn.style.display = show ? 'flex' : 'none';
    }
    if (messagesContainer) {
        messagesContainer.addEventListener('scroll', updateScrollToBottomButtonVisibility);
    }
    if (scrollToBottomBtn) {
        scrollToBottomBtn.addEventListener('click', function() {
            scrollToBottom();
            updateScrollToBottomButtonVisibility();
        });
    }

    // 入力欄の高さ自動調整（1行→最大約4行）
    messageInput.addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 90) + 'px';
    });

    // Enterキーで送信（Shift+Enterで改行）
    messageInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    });

    // 送信ボタンクリック
    sendBtn.addEventListener('click', sendMessage);

    // ファイル添付
    attachBtn.addEventListener('click', () => fileInput.click());
    fileInput.addEventListener('change', handleFileUpload);

    // 画像選択
    imageBtn.addEventListener('click', () => imageInput.click());
    imageInput.addEventListener('change', handleImageUpload);

    // ファイルアップロード処理
    function handleFileUpload(e) {
        const files = e.target.files;
        if (files.length === 0) return;
        console.log('ファイルアップロード:', files);
    }

    // 画像アップロード処理
    function handleImageUpload(e) {
        const files = e.target.files;
        if (files.length === 0) return;
        console.log('画像アップロード:', files);
    }

    // 安全な可変ポーリング（表示中は短周期、非表示時は長周期）
    function getPollingIntervalMs() {
        return document.hidden ? IDLE_POLL_MS : ACTIVE_POLL_MS;
    }

    function runPollingTick() {
        if (!currentRoomId) {
            scheduleNextPolling();
            return;
        }
        if (isPollingRequestInFlight) {
            scheduleNextPolling();
            return;
        }

        isPollingRequestInFlight = true;
        Promise.resolve(loadMessages())
            .catch(function() {
                // loadMessages 内でエラーハンドリング済み
            })
            .finally(function() {
                isPollingRequestInFlight = false;
                scheduleNextPolling();
            });
    }

    function scheduleNextPolling(delayMs) {
        if (pollTimer) {
            clearTimeout(pollTimer);
            pollTimer = null;
        }
        var nextMs = typeof delayMs === 'number' ? delayMs : getPollingIntervalMs();
        pollTimer = setTimeout(runPollingTick, nextMs);
    }

    function startPolling() {
        scheduleNextPolling(getPollingIntervalMs());
    }

    // システム投稿としてメッセージを表示（中央寄せピル）
    function displayMessageAsSystemPost(message) {
        var timeStr = formatMessageTime(message.created_at);
        var title = message.title ? ('<div class="system-message-pill__title">' + escapeHtml(message.title) + '</div>') : '';
        var content = '<div class="system-message-pill__content">' + escapeHtml(message.content || '') + '</div>';
        var systemMessageElement = '<div class="message system-message-wrapper" data-message-id="' + escapeHtml(String(message.id)) + '">' +
            '<div class="system-message-pill" role="status">' +
            title + content +
            '<span class="system-message-pill__time">' + escapeHtml(timeStr) + '</span>' +
            '</div></div>';
        messagesContainer.insertAdjacentHTML('afterbegin', systemMessageElement);
    }

    // 時間フォーマット関数（統一ライブラリを使用）
    function formatTime(timestamp) {
        if (typeof AidUniteDateUtils !== 'undefined') {
            return AidUniteDateUtils.formatTimeFromTimestamp(timestamp);
        }
        // フォールバック（date-utils.jsが読み込まれていない場合）
        return new Date(timestamp).toLocaleTimeString('ja-JP', {
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    // チャットヘッダータイトルを更新する関数
    function updateChatHeaderTitle(message) {
        const headerTitle = document.getElementById('chatHeaderTitle');
        if (!headerTitle) return;

        let newTitle = cfg.defaultHeaderTitle || '';

        if (message.category === 'match') {
            newTitle = '試合について';
        } else if (message.category === 'schedule') {
            newTitle = 'スケジュールについて';
        } else if (message.category === 'practice') {
            newTitle = '練習について';
        } else if (message.title) {
            const title = message.title.length > 20 ? message.title.substring(0, 20) + '...' : message.title;
            newTitle = title;
        }
        headerTitle.textContent = newTitle;
    }

    // 評価モーダル（対戦チャットのみ）
    if (roomType === 'match') {
    const evaluationModal = document.getElementById('evaluationModal');
    const evaluationModalClose = document.getElementById('evaluationModalClose');
    const evaluationCancelBtn = document.getElementById('evaluationCancelBtn');
    const evaluationForm = document.getElementById('evaluationForm');

    if (evaluationModalClose) {
        evaluationModalClose.addEventListener('click', () => {
            evaluationModal.style.display = 'none';
        });
    }

    if (evaluationCancelBtn) {
        evaluationCancelBtn.addEventListener('click', () => {
            evaluationModal.style.display = 'none';
        });
    }

    if (evaluationForm) {
        evaluationForm.addEventListener('submit', function(e) {
            e.preventDefault();
            // 評価送信処理
            console.log('評価送信:', new FormData(evaluationForm));
        });
    }
    }

    // 試合情報パネルの読み込み（match/groupタイプのみ）
    if (roomType === 'match' || roomType === 'group') {
    function loadMatchInfo() {
        if (!currentRoomId) return;

        fetch(restBase + 'chats/' + currentRoomId + '/match-info', {
            headers: {
                'X-WP-Nonce': nonce
            }
        }).then(response => {
            if (!response.ok) {
                if (response.status === 404) {
                    // 試合情報がない場合はパネルを非表示
                    const panel = document.getElementById('matchInfoPanel');
                    if (panel) panel.style.display = 'none';
                    return;
                }
                throw new Error('試合情報の取得に失敗しました');
            }
            return response.json();
        }).then(data => {
            if (data && !data.error) {
                displayMatchInfo(data);
            }
        }).catch(error => {
            console.error('試合情報取得エラー:', error);
            // エラー時はパネルを非表示
            const panel = document.getElementById('matchInfoPanel');
            if (panel) panel.style.display = 'none';
        });
    }

    function displayMatchInfo(info) {
        const panel = document.getElementById('matchInfoPanel');
        if (!panel) return;

        // 日付（統一定義: Y年n月j日（曜）でAPIから返却）
        const dateElement = document.getElementById('matchInfoDate');
        if (dateElement) {
            dateElement.textContent = info.date_formatted || '-';
        }

        // 時間
        const timeElement = document.getElementById('matchInfoTime');
        if (timeElement) {
            timeElement.textContent = info.time_display || '-';
        }

        // 会場（統一定義でAPIから返却）
        const venueElement = document.getElementById('matchInfoVenue');
        if (venueElement) {
            venueElement.textContent = info.venue_name || '-';
        }

        // 性別（男子:1 / 女子:2 / 男子1、女子2）
        const genderElement = document.getElementById('matchInfoGender');
        if (genderElement) {
            genderElement.textContent = info.gender_display != null ? info.gender_display : '-';
        }

        const participantsElement = document.getElementById('matchInfoParticipants');
        const participantsList = document.getElementById('matchInfoParticipantsList');
        const namesText = (info.participant_names_display && info.participant_count > 0) ? info.participant_names_display : '—';
        if (participantsList && info.participant_teams && Array.isArray(info.participant_teams) && info.participant_teams.length > 0) {
            participantsList.innerHTML = info.participant_teams.map(function(t) {
                var label = (t.name || t.team_name || 'チーム') + (t.representative ? '（代表: ' + t.representative + '）' : '');
                return '<li class="chat-side-teams__item">' + escapeHtml(label) + '</li>';
            }).join('');
        } else if (participantsElement) {
            participantsElement.textContent = namesText;
        } else if (participantsList) {
            participantsList.innerHTML = '<li class="chat-side-teams__item">' + escapeHtml(namesText) + '</li>';
        }

        const memoEl = document.getElementById('matchInfoMemo');
        if (memoEl && info.memo) {
            memoEl.textContent = info.memo;
            memoEl.hidden = false;
        } else if (memoEl) {
            memoEl.hidden = true;
        }

        panel.style.display = '';
    }

    function initChatMobileInfoToggle() {
        var mobileBtn = document.getElementById('chatMobileInfoToggle');
        var side = document.getElementById('chatSidePanel');
        if (!mobileBtn || !side) return;
        mobileBtn.addEventListener('click', function() {
            var open = side.classList.toggle('is-mobile-open');
            mobileBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
            mobileBtn.textContent = open ? '試合情報を閉じる' : '試合情報を開く';
        });
    }
    }

    // 初期化（async：pending をマージしてから1回だけ描画）
    (async function initChat() {
        var pendingRaw = sessionStorage.getItem('pendingMessage');
        var pending = pendingRaw ? JSON.parse(pendingRaw) : null;

        var list = await loadMessages({ suppressRender: true });
        var merged = mergePending(list, pending);

        renderMessages(merged);

        if (pending) {
            sessionStorage.removeItem('pendingMessage');
            updateChatHeaderTitle(pending);
        }

        startPolling();
    })();

    if (roomType === 'match' || roomType === 'group') {
    initChatMobileInfoToggle();
    loadMatchInfo();
    }

    document.addEventListener('visibilitychange', function() {
        if (!document.hidden) {
            // 復帰直後は最新を早めに同期
            scheduleNextPolling(0);
        } else {
            scheduleNextPolling(IDLE_POLL_MS);
        }
    });

    // ページ離脱時に接続/タイマーを閉じる（コミュニケーション一覧の未読表示を即更新）
    window.addEventListener('pagehide', function() {
        try {
            sessionStorage.setItem('aidunite_timeline_force_refresh', '1');
        } catch (e) { /* ignore */ }
    });

    window.addEventListener('beforeunload', function() {
        if (pollTimer) {
            clearTimeout(pollTimer);
            pollTimer = null;
        }
        if (eventSource) {
            eventSource.close();
        }
    });
  });
})();
