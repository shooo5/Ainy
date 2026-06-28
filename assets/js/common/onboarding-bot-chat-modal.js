/**
 * オンボーディング・練習相手チーム成立後モーダル（共有）
 */
(function () {
    'use strict';

    var cfg = window.aiduniteOnboardingBotModal || {};
    var restNonce = cfg.restNonce || (window.wpApiSettings && window.wpApiSettings.nonce) || '';
    var mypageUrl = cfg.mypageUrl || '/mypage/';
    var recruitUrl = cfg.recruitUrl || (mypageUrl.replace(/\/?$/, '/') + '?open_recruit=1');
    var pendingChatUrl = '';

    function getModal() {
        return document.getElementById('mypage-onboarding-bot-chat-modal');
    }

    function showOnboardingBotChatModal(chatUrl) {
        var modal = getModal();
        if (!modal) {
            return;
        }
        if (typeof chatUrl === 'string' && chatUrl !== '') {
            pendingChatUrl = chatUrl;
        }
        modal.removeAttribute('hidden');
        modal.classList.add('is-open');
        document.body.classList.add('mypage-onboarding-bot-modal-open');
        var closeBtn = document.getElementById('mypage-onboarding-bot-chat-modal-close');
        if (closeBtn) {
            closeBtn.focus();
        }
    }

    function markModalShown() {
        return fetch('/wp-json/aidunite/v1/onboarding-bot/chat-modal-shown', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': restNonce },
        }).catch(function () {
            return null;
        });
    }

    function navigateAfterClose(mode) {
        if (mode === 'close' || mode === 'later') {
            window.location.href = recruitUrl;
            return;
        }
        if (mode === 'chat' && pendingChatUrl) {
            window.location.href = pendingChatUrl;
            return;
        }
        window.location.href = recruitUrl;
    }

    function closeOnboardingBotChatModal(mode) {
        var modal = getModal();
        if (!modal) {
            return;
        }
        modal.setAttribute('hidden', 'hidden');
        modal.classList.remove('is-open');
        document.body.classList.remove('mypage-onboarding-bot-modal-open');
        markModalShown().finally(function () {
            navigateAfterClose(mode || 'close');
        });
    }

    function initOnboardingBotChatModal() {
        var modal = getModal();
        if (!modal) {
            return;
        }

        pendingChatUrl = cfg.chatUrl || pendingChatUrl;

        var closeBtn = document.getElementById('mypage-onboarding-bot-chat-modal-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', function () {
                closeOnboardingBotChatModal('close');
            });
        }
        modal.querySelectorAll('[data-onboarding-bot-modal-close]').forEach(function (el) {
            el.addEventListener('click', function () {
                var mode = el.getAttribute('data-onboarding-bot-modal-close') || 'close';
                closeOnboardingBotChatModal(mode);
            });
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && modal.classList.contains('is-open')) {
                closeOnboardingBotChatModal('close');
            }
        });

        if (cfg.showOnboardingBotChatModal === '1') {
            showOnboardingBotChatModal(pendingChatUrl);
        }
    }

    window.aiduniteShowOnboardingBotChatModal = showOnboardingBotChatModal;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initOnboardingBotChatModal);
    } else {
        initOnboardingBotChatModal();
    }
})();
