/**
 * 試合後モジュール：impression / CTA クリックログ
 */
(function () {
  'use strict';

  var cfg = window.aidunitePostMatchModulesConfig || {};
  var state = window.aidunitePostMatchModules || {};

  function postInteraction(moduleId, action) {
    if (!cfg.restBase || !cfg.nonce) {
      return Promise.resolve();
    }
    var url = cfg.restBase.replace(/\/$/, '') + '/post-match-modules/' + moduleId + '/interactions';
    return fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': cfg.nonce,
      },
      body: JSON.stringify({
        action: action,
        team_id: state.teamId || 0,
        match_id: state.matchId || 0,
      }),
    }).catch(function () {
      /* ログ失敗は UI を止めない */
    });
  }

  function logImpressions() {
    var cards = document.querySelectorAll('.post-match-module[data-module-id]');
    cards.forEach(function (card) {
      if (card.getAttribute('data-impression-logged') === '1') {
        return;
      }
      card.setAttribute('data-impression-logged', '1');
      var id = parseInt(card.getAttribute('data-module-id'), 10);
      if (id > 0) {
        postInteraction(id, 'impression');
      }
    });
  }

  function bindCta() {
    document.querySelectorAll('.post-match-module__cta-link').forEach(function (link) {
      link.addEventListener('click', function () {
        var card = link.closest('.post-match-module');
        if (!card) {
          return;
        }
        var id = parseInt(card.getAttribute('data-module-id'), 10);
        if (id > 0) {
          postInteraction(id, 'cta_click');
        }
      });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      logImpressions();
      bindCta();
    });
  } else {
    logImpressions();
    bindCta();
  }
})();
