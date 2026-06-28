(function () {

    'use strict';



    var cfg = window.aidunitePaymentFirstMatch || {};

    var teamId = parseInt(cfg.teamId, 10) || 0;

    var restNonce = cfg.restNonce || '';

    var isPreviewMode = cfg.previewMode === true || cfg.previewMode === '1';



    function escapeHtml(value) {

        return String(value || '')

            .replace(/&/g, '&amp;')

            .replace(/</g, '&lt;')

            .replace(/>/g, '&gt;')

            .replace(/"/g, '&quot;');

    }



    function postPromptAction(action) {

        if (isPreviewMode || !teamId || !restNonce) {

            return Promise.resolve();

        }



        return fetch('/wp-json/aidunite/v1/payment-exit/first-match-prompt', {

            method: 'POST',

            headers: {

                'Content-Type': 'application/json',

                'X-WP-Nonce': restNonce

            },

            body: JSON.stringify({ team_id: teamId, action: action })

        }).catch(function () {});

    }



    function buildModalHtml(prompt) {

        var laterLabel = prompt.later_label || 'あとで';

        var heroImageUrl = prompt.hero_image_url || (cfg.heroImageUrl || '');

        var giftIcon = prompt.gift_icon_html || '';

        var paymentUrl = escapeHtml(prompt.payment_setup_url || '/payment-setup');

        var bodyText = prompt.body_text || 'Ainyでは地域の試合機会を維持するための、月額2,000円（税込）でご利用いただけます。';

        var highlightText = prompt.highlight_text || 'お申し込み月と翌月は無料です。';

        var bodyHtml = escapeHtml(bodyText)

            .replace(/\n/g, '<br>')

            .replace(/月額2,000円（税込）/g, '<span class="payment-first-match-modal__body-price">月額2,000円（税込）</span>');

        var heroMarkup = heroImageUrl

            ? '<img class="payment-first-match-modal__hero-image" src="' + escapeHtml(heroImageUrl) + '" alt="" width="120" height="120" decoding="async" />'

            : '';



        return (

            '<div class="payment-first-match-modal" role="dialog" aria-modal="true" aria-labelledby="payment-first-match-modal-title">' +

            '<button type="button" class="payment-first-match-modal__close" data-action="later" aria-label="閉じる">' +

            '<span aria-hidden="true">&times;</span></button>' +

            '<div class="payment-first-match-modal__hero" aria-hidden="true">' +

            heroMarkup +

            '</div>' +

            '<h2 class="payment-first-match-modal__title" id="payment-first-match-modal-title">' +

            '試合成立<span class="payment-first-match-modal__title-accent">おめでとうございます！</span></h2>' +

            '<p class="payment-first-match-modal__subtitle">' +

            '<span class="payment-first-match-modal__subtitle-mark" aria-hidden="true">\\</span> ' +

            '最初の試合が成立しました。 ' +

            '<span class="payment-first-match-modal__subtitle-mark" aria-hidden="true">/</span></p>' +

            '<div class="payment-first-match-modal__steps-wrap">' +

            '<ol class="payment-first-match-steps" aria-label="利用開始の進捗">' +

            '<li class="payment-first-match-steps__item payment-first-match-steps__item--done">' +

            '<span class="payment-first-match-steps__dot" aria-hidden="true">✓</span>' +

            '<span class="payment-first-match-steps__label">チーム作成</span></li>' +

            '<li class="payment-first-match-steps__connector payment-first-match-steps__connector--done" aria-hidden="true"></li>' +

            '<li class="payment-first-match-steps__item payment-first-match-steps__item--done">' +

            '<span class="payment-first-match-steps__dot" aria-hidden="true">✓</span>' +

            '<span class="payment-first-match-steps__label">初めての試合成立</span></li>' +

            '<li class="payment-first-match-steps__connector payment-first-match-steps__connector--pending" aria-hidden="true"></li>' +

            '<li class="payment-first-match-steps__item payment-first-match-steps__item--current">' +

            '<span class="payment-first-match-steps__dot" aria-hidden="true"></span>' +

            '<span class="payment-first-match-steps__label">支払い設定<br>（2ヶ月無料）</span></li>' +

            '</ol></div>' +

            '<p class="payment-first-match-modal__body">' + bodyHtml + '</p>' +

            '<div class="payment-first-match-modal__highlight">' +

            '<span class="payment-first-match-modal__highlight-icon" aria-hidden="true">' + giftIcon + '</span>' +

            '<span>' + escapeHtml(highlightText) + '</span></div>' +

            '<div class="payment-first-match-modal__actions">' +

            '<button type="button" class="btn btn-secondary" data-action="later">' + escapeHtml(laterLabel) + '</button>' +

            '<a class="btn btn-primary" href="' + paymentUrl + '" data-action="payment-setup">お支払い設定へ &gt;</a>' +

            '</div></div>'

        );

    }



    function closeModal(overlay) {

        overlay.remove();

        document.body.classList.remove('payment-first-match-modal-open');

    }



    function showPrompt(prompt, options) {

        options = options || {};

        var preview = isPreviewMode || options.preview === true;



        if (!prompt) {

            prompt = cfg.previewPrompt || null;

        }

        if (!prompt) {

            return;

        }



        var overlay = document.createElement('div');

        overlay.className = 'payment-first-match-overlay';

        overlay.setAttribute('role', 'presentation');

        overlay.innerHTML = buildModalHtml(prompt);



        document.body.appendChild(overlay);

        document.body.classList.add('payment-first-match-modal-open');



        if (!preview) {

            postPromptAction('impression');

        }



        overlay.querySelectorAll('[data-action="later"]').forEach(function (btn) {

            btn.addEventListener('click', function () {

                if (preview) {

                    closeModal(overlay);

                    return;

                }

                postPromptAction('later').finally(function () {

                    closeModal(overlay);

                });

            });

        });



        var paymentLink = overlay.querySelector('[data-action="payment-setup"]');

        if (paymentLink) {

            paymentLink.addEventListener('click', function (event) {

                if (preview) {

                    event.preventDefault();

                    closeModal(overlay);

                    return;

                }

                postPromptAction('payment_setup_snooze');

            });

        }

    }



    window.aidunitePaymentFirstMatchModal = {

        show: showPrompt,

        buildHtml: buildModalHtml,

    };



    if (isPreviewMode) {

        return;

    }



    if (!teamId || !restNonce) {

        return;

    }



    fetch('/wp-json/aidunite/v1/payment-exit/first-match-prompt?team_id=' + teamId, {

        headers: { 'X-WP-Nonce': restNonce }

    })

        .then(function (res) {

            if (!res.ok) {

                return res.json().then(function (err) {

                    if (typeof console !== 'undefined' && console.warn) {

                        console.warn('first-match-prompt:', err && err.message ? err.message : res.status);

                    }

                });

            }

            return res.json();

        })

        .then(function (data) {

            if (data && data.prompt) {

                showPrompt(data.prompt);

            }

        })

        .catch(function () {});

})();


