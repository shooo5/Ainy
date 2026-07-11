/**
 * ヘッダー: 操作中チームを REST で保存し、成功時に再読込または公開プロフィールへ遷移。
 * window.aiduniteOperatingTeam は wp_localize_script で供給（enabled=false のときは何もしない）。
 */
(function () {
  'use strict';

  function isTeamPublicProfilePage() {
    return !!document.querySelector('.team-public-profile');
  }

  function isTeamSettingsPage() {
    return document.body.classList.contains('page-team-settings');
  }

  function isMatchDetailPage() {
    if (document.body.classList.contains('page-match-detail')) {
      return true;
    }
    if (document.querySelector('.page-match-detail, .match-detail-page')) {
      return true;
    }
    return /\/match-detail\/?/i.test(window.location.pathname || '');
  }

  function resolveRedirectAfterSwitch(nextTeamId) {
    var cfg = window.aiduniteOperatingTeam || {};
    if (isMatchDetailPage() && cfg.matchBoardUrl) {
      return cfg.matchBoardUrl;
    }
    if (isTeamSettingsPage() && cfg.teamSettingsUrl) {
      return cfg.teamSettingsUrl;
    }
    if (!isTeamPublicProfilePage()) {
      return null;
    }
    var urls = cfg.teamPublicProfileUrls || {};
    var key = String(nextTeamId);
    if (urls[key]) {
      return urls[key];
    }
    var params = new URLSearchParams(window.location.search);
    if (params.has('team_id') || params.has('id')) {
      var next = new URL(window.location.href);
      if (params.has('team_id')) {
        next.searchParams.set('team_id', String(nextTeamId));
      }
      if (params.has('id')) {
        next.searchParams.set('id', String(nextTeamId));
      }
      return next.toString();
    }
    return null;
  }

  function init() {
    var cfg = window.aiduniteOperatingTeam || {};
    if (!cfg.enabled) {
      return;
    }
    var selects = document.querySelectorAll('[data-aidunite-operating-team-select]');
    if (!selects.length) {
      return;
    }
    var initial = String(cfg.currentTeamId || '');

    function bindSelect(sel) {
      sel.addEventListener('change', function () {
        var nextId = parseInt(sel.value, 10);
        if (!nextId || String(nextId) === initial) {
          return;
        }
        selects.forEach(function (s) {
          s.disabled = true;
        });
        fetch(cfg.restUrlSetCurrent, {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': cfg.nonce || ''
          },
          body: JSON.stringify({ team_id: nextId })
        })
          .then(function (res) {
            return res.json().then(function (body) {
              if (!res.ok) {
                var msg =
                  body && body.message
                    ? body.message
                    : '切り替えに失敗しました。';
                throw new Error(msg);
              }
              var redirect = resolveRedirectAfterSwitch(nextId);
              if (redirect) {
                window.location.href = redirect;
                return;
              }
              window.location.reload();
            });
          })
          .catch(function (err) {
            aiduniteToast(err.message || '切り替えに失敗しました。', 'error');
            selects.forEach(function (s) {
              s.value = initial;
              s.disabled = false;
            });
          });
      });
    }

    selects.forEach(bindSelect);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
