(function () {
  'use strict';

  var cfg = window.aiduniteMatchBoardConfig || {};
  var restUrl = cfg.restUrl || '';
  var restNonce = cfg.restNonce || '';
  var pendingSeenKeys = new Set();

  function markCardSeen(cardKey) {
    if (!cardKey || !restUrl) {
      return;
    }
    var url = restUrl.replace(/\/$/, '') + '/match-board/card-seen';
    var headers = {
      'Content-Type': 'application/json',
    };
    if (restNonce) {
      headers['X-WP-Nonce'] = restNonce;
    }

    fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: headers,
      body: JSON.stringify({ card_key: cardKey }),
      keepalive: true,
    }).catch(function () {
      /* 閲覧済み保存失敗は UI をブロックしない */
    });
  }

  function flushPendingSeen() {
    if (!pendingSeenKeys.size) {
      return;
    }
    pendingSeenKeys.forEach(function (cardKey) {
      markCardSeen(cardKey);
    });
    pendingSeenKeys.clear();
  }

  function queueCardSeen(cardKey, flushImmediately) {
    if (!cardKey) {
      return;
    }
    pendingSeenKeys.add(cardKey);
    if (flushImmediately) {
      flushPendingSeen();
    }
  }

  function isNavigationLink(el) {
    if (!el || el.tagName !== 'A') {
      return false;
    }
    var href = (el.getAttribute('href') || '').trim();
    return href !== '' && href !== '#' && href.indexOf('javascript:') !== 0;
  }

  document.addEventListener('click', function (event) {
    var target = event.target;
    if (!target || !target.closest) {
      return;
    }
    var row = target.closest('[data-board-card-key]');
    if (!row) {
      return;
    }
    var cardKey = row.getAttribute('data-board-card-key') || '';
    if (!cardKey) {
      return;
    }
    var link = target.closest('a[href]');
    if (!link || !isNavigationLink(link)) {
      return;
    }
    var opensNewTab = link.target === '_blank' || link.hasAttribute('download');
    queueCardSeen(cardKey, opensNewTab);
  });

  window.addEventListener('pagehide', flushPendingSeen);
  document.addEventListener('visibilitychange', function () {
    if (document.visibilityState === 'hidden') {
      flushPendingSeen();
    }
  });
})();
