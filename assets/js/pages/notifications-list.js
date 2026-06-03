/**

 * 通知一覧（page-notifications.php）

 */

(function ($) {

    'use strict';



    let detailModalLastFocus = null;



    function clearFocusedNotificationCard() {

        const active = document.activeElement;

        if (active && active.classList && active.classList.contains('ainy-notification-card')) {

            active.blur();

        }

    }



    function updateUnreadTabCount() {

        const unread = $('.ainy-notification-card.unread').length;

        const $badge = $('[data-tab-count="unread"]');

        $badge.text(unread > 0 ? String(unread) : '').attr('data-count', unread);

        if (unread > 0) {

            $badge.attr('aria-label', '未読' + unread + '件');

        } else {

            $badge.removeAttr('aria-label');

        }

    }



    function updateActionsPanel(filter) {

        const mode = filter || 'unread';

        const isUnread = mode === 'unread';

        const $unreadPanel = $('#ainy-notifications-actions-unread');

        const $readPanel = $('#ainy-notifications-actions-read');

        $unreadPanel.toggleClass('is-hidden', !isUnread);

        $readPanel.toggleClass('is-hidden', isUnread);

        if ($readPanel.length) {

            $readPanel.prop('hidden', isUnread);

        }

        if ($unreadPanel.length) {

            $unreadPanel.prop('hidden', !isUnread);

        }

    }



    function applyFilter(filter) {

        const mode = filter || 'unread';

        const $page = $('.ainy-notifications-page');



        $page.removeClass('filter-unread filter-read').addClass(mode === 'read' ? 'filter-read' : 'filter-unread');



        $('.ainy-notifications-group').each(function () {

            const $group = $(this);

            const visibleSelector = mode === 'read' ? '.ainy-notification-card.read' : '.ainy-notification-card.unread';

            const visible = $group.find(visibleSelector).length > 0;

            $group.toggle(visible);

        });



        updateActionsPanel(mode);

    }



    function markCardRead($card) {

        $card.removeClass('unread').addClass('read');

        $card.find('.ainy-notification-card__dot').remove();

        updateUnreadTabCount();

    }



    function normalizeLinkUrl(url) {

        if (!url || url === '#' || url === window.location.href) {

            return '';

        }

        return url;

    }



    function markAsRead($card, onComplete) {

        const postId = $card.data('id');

        const cfg = window.ainyNotificationsList || {};



        if (!postId || $card.hasClass('read')) {

            if (typeof onComplete === 'function') {

                onComplete(true);

            }

            return;

        }



        $.ajax({

            url: cfg.restMarkRead || '',

            method: 'POST',

            contentType: 'application/json',

            data: JSON.stringify({ id: Number(postId) }),

            timeout: 8000,

            beforeSend: function (xhr) {

                if (cfg.restNonce) {

                    xhr.setRequestHeader('X-WP-Nonce', cfg.restNonce);

                }

            },

            success: function () {

                markCardRead($card);

                const activeFilter = $('.ainy-notifications-filter-tabs button.active').data('filter') || 'unread';

                applyFilter(activeFilter);

                if (typeof onComplete === 'function') {

                    onComplete(true);

                }

            },

            error: function (xhr) {

                console.error('[NOTIFICATIONS] mark-read failed', xhr);

                if (typeof onComplete === 'function') {

                    onComplete(false);

                }

            }

        });

    }



    function markReadAndNavigate($card, url) {

        const normalizedUrl = normalizeLinkUrl(url);



        function doNavigate() {

            if (normalizedUrl) {

                window.location.assign(normalizedUrl);

            }

        }



        if ($card.hasClass('read')) {

            doNavigate();

            return;

        }



        markAsRead($card, function () {

            doNavigate();

        });

    }



    function getCardDetail($card) {

        const $meta = $card.find('.ainy-notification-card__meta');

        const title = $.trim($meta.find('h3').text());

        const $time = $meta.find('time');

        const message = $.trim($card.find('.ainy-notification-card__message-source').text());



        return {

            title: title,

            timeLabel: $.trim($time.text()),

            datetime: $time.attr('datetime') || '',

            message: message

        };

    }



    function openDetailModal($card) {

        const $modal = $('#ainy-notification-detail-modal');

        if (!$modal.length) {

            return;

        }



        const detail = getCardDetail($card);

        const cfg = window.ainyNotificationsList || {};



        $('#ainy-notification-detail-title').text(detail.title || '');

        const $timeEl = $('#ainy-notification-detail-time');

        $timeEl.text(detail.timeLabel || '');

        if (detail.datetime) {

            $timeEl.attr('datetime', detail.datetime);

        } else {

            $timeEl.removeAttr('datetime');

        }



        const $body = $('#ainy-notification-detail-body');

        $body.empty();

        if (detail.message) {

            $body.text(detail.message);

        } else if (detail.title) {

            $body.text('詳細の本文がありません。');

        } else {

            $body.text('');

        }



        detailModalLastFocus = document.activeElement;

        $modal.removeAttr('hidden').attr('aria-hidden', 'false').addClass('is-open');

        document.body.classList.add('ainy-notification-detail-modal-open');



        const $closeBtn = $modal.find('.ainy-notification-detail-modal__close').first();

        if ($closeBtn.length) {

            $closeBtn.trigger('focus');

        }



        markAsRead($card);

    }



    function closeDetailModal() {

        const $modal = $('#ainy-notification-detail-modal');

        if (!$modal.length || !$modal.hasClass('is-open')) {

            return;

        }



        $modal.removeClass('is-open').attr('aria-hidden', 'true').attr('hidden', 'hidden');

        document.body.classList.remove('ainy-notification-detail-modal-open');



        if (detailModalLastFocus && typeof detailModalLastFocus.focus === 'function') {

            detailModalLastFocus.focus();

        }

        detailModalLastFocus = null;

    }



    $(function () {

        const cfg = window.ainyNotificationsList || {};

        if (!cfg.restMarkRead) {

            return;

        }



        $('.ainy-notifications-filter-tabs button').on('click', function () {

            const filter = $(this).data('filter') || 'unread';

            clearFocusedNotificationCard();

            $('.ainy-notifications-filter-tabs button').removeClass('active').attr('aria-selected', 'false');

            $(this).addClass('active').attr('aria-selected', 'true');

            applyFilter(filter);

        });



        clearFocusedNotificationCard();

        applyFilter($('.ainy-notifications-filter-tabs button.active').data('filter') || 'unread');



        function onCardActivate($card) {

            const url = normalizeLinkUrl($card.attr('data-link') || '');

            if (url) {

                markReadAndNavigate($card, url);

                return;

            }

            openDetailModal($card);

        }



        $('.ainy-notification-card').on('click', function (e) {

            if ($(e.target).closest('button, a').length) {

                return;

            }

            onCardActivate($(this));

        });



        $('.ainy-notification-card').on('keydown', function (e) {

            if (e.key === 'Enter' || e.key === ' ') {

                e.preventDefault();

                onCardActivate($(this));

            }

        });



        $(document).on('click', '[data-close-detail]', function () {

            closeDetailModal();

        });



        $(document).on('keydown', function (e) {

            if (e.key === 'Escape' && $('#ainy-notification-detail-modal').hasClass('is-open')) {

                e.preventDefault();

                closeDetailModal();

            }

        });



        $('#ainy-mark-all-read').on('click', function () {

            const unreadItems = $('.ainy-notification-card.unread');

            const ids = unreadItems.map(function () {

                return $(this).data('id');

            }).get().filter(Boolean);



            if (ids.length === 0) {

                window.alert(cfg.i18n?.noUnread || '既読にする通知がありません。');

                return;

            }



            $.ajax({

                url: cfg.restMarkRead,

                method: 'POST',

                data: { ids: ids },

                beforeSend: function (xhr) {

                    if (cfg.restNonce) {

                        xhr.setRequestHeader('X-WP-Nonce', cfg.restNonce);

                    }

                },

                success: function () {

                    unreadItems.each(function () {

                        markCardRead($(this));

                    });

                    applyFilter($('.ainy-notifications-filter-tabs button.active').data('filter') || 'unread');

                    window.alert(cfg.i18n?.markedAll || 'すべて既読にしました。');

                },

                error: function (xhr) {

                    console.error('[NOTIFICATIONS] mark-all failed', xhr);

                    window.alert(cfg.i18n?.error || 'エラーが発生しました。');

                }

            });

        });



        $('#ainy-delete-read').on('click', function () {

            if (!window.confirm(cfg.i18n?.confirmDelete || '既読の通知をすべて削除しますか？')) {

                return;

            }



            const readItems = $('.ainy-notification-card.read');

            const ids = readItems.map(function () {

                return parseInt($(this).data('id'), 10);

            }).get().filter(function (id) {

                return !isNaN(id) && id > 0;

            });



            if (ids.length === 0) {

                window.alert(cfg.i18n?.noReadToDelete || '削除する既読通知がありません。');

                return;

            }



            $.ajax({

                url: cfg.restDelete || '',

                method: 'POST',

                contentType: 'application/json',

                data: JSON.stringify({ ids: ids }),

                timeout: 15000,

                beforeSend: function (xhr) {

                    if (cfg.restNonce) {

                        xhr.setRequestHeader('X-WP-Nonce', cfg.restNonce);

                    }

                },

                success: function (res) {

                    if (res && res.success) {

                        readItems.fadeOut(300, function () {

                            $(this).remove();

                            updateUnreadTabCount();

                            applyFilter($('.ainy-notifications-filter-tabs button.active').data('filter') || 'unread');

                        });

                        window.alert(res.message || cfg.i18n?.deleted || '既読通知を削除しました。');

                    } else {

                        window.alert(cfg.i18n?.deleteFailed || '削除に失敗しました。');

                    }

                },

                error: function (xhr) {

                    console.error('[NOTIFICATIONS] delete failed', xhr);

                    window.alert(cfg.i18n?.deleteFailed || '削除に失敗しました。');

                }

            });

        });



        updateUnreadTabCount();

    });

}(jQuery));


