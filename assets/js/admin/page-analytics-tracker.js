/**
 * 軽量ページ分析（PV・滞在・行動イベント）
 */
(function () {
  'use strict';

  var cfg = window.aidunitePageAnalytics;
  if (!cfg || !cfg.restUrl || !cfg.nonce || !cfg.pageKey) {
    return;
  }

  var pageKey = cfg.pageKey;
  var maxDwell = parseInt(cfg.maxDwellSec, 10) || 1800;
  var startedAt = Date.now();
  var viewSent = false;

  function storageKey() {
    return 'aidunite_pv_' + pageKey;
  }

  function getSessionId() {
    if (cfg.sessionId) {
      return cfg.sessionId;
    }
    try {
      var k = 'aidunite_sid';
      var sid = sessionStorage.getItem(k);
      if (!sid) {
        sid = 's_' + Date.now() + '_' + Math.random().toString(36).slice(2, 10);
        sessionStorage.setItem(k, sid);
      }
      return sid;
    } catch (e) {
      return '';
    }
  }

  function post(endpoint, body) {
    return fetch(cfg.restUrl + endpoint, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': cfg.nonce,
      },
      credentials: 'same-origin',
      body: JSON.stringify(body),
      keepalive: true,
    }).catch(function () {
      return null;
    });
  }

  function postEvent(eventType, extra) {
    var payload = {
      event_type: eventType,
      page_key: pageKey,
      team_id: parseInt(cfg.teamId, 10) || 0,
      user_id: parseInt(cfg.userId, 10) || 0,
      session_id: getSessionId(),
      referrer_page: cfg.referrerPage || '',
      target_key: '',
      duration_sec: 0,
    };
    if (extra) {
      Object.keys(extra).forEach(function (k) {
        payload[k] = extra[k];
      });
    }
    return post('event', payload);
  }

  function sendPageView() {
    if (viewSent) {
      return;
    }
    try {
      if (sessionStorage.getItem(storageKey()) === '1') {
        return;
      }
      sessionStorage.setItem(storageKey(), '1');
    } catch (e) {
      /* sessionStorage 不可時は送信続行 */
    }
    viewSent = true;
    post('page-view', { page_key: pageKey });
    postEvent('view');
  }

  function sendPageLeave() {
    var elapsed = Math.round((Date.now() - startedAt) / 1000);
    if (elapsed < 1) {
      return;
    }
    if (elapsed > maxDwell) {
      elapsed = maxDwell;
    }
    post('page-leave', { page_key: pageKey, elapsed_sec: elapsed });
    postEvent('leave', { duration_sec: elapsed });
  }

  document.addEventListener(
    'click',
    function (ev) {
      var el = ev.target;
      if (!el || !el.closest) {
        return;
      }
      var btn = el.closest('[data-analytics-target]');
      if (!btn) {
        return;
      }
      var target = btn.getAttribute('data-analytics-target') || '';
      if (!target) {
        return;
      }
      var type = btn.getAttribute('data-analytics-event') || 'click';
      postEvent(type, { target_key: target });
    },
    true
  );

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', sendPageView);
  } else {
    sendPageView();
  }

  document.addEventListener('visibilitychange', function () {
    if (document.visibilityState === 'hidden') {
      sendPageLeave();
    }
  });

  window.addEventListener('pagehide', sendPageLeave);
})();
