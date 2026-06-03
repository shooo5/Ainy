/**
 * お気に入りチーム トグルボタン
 * .favorite-team-btn をクリックで REST API の toggle を呼び、表示を更新する
 */
(function() {
    'use strict';

    function getRestConfig() {
        var root = typeof window.wp !== 'undefined' && window.wp.apiFetch && window.wp.apiFetch.getRootURL ? window.wp.apiFetch.getRootURL() : null;
        if (!root) {
            var el = document.querySelector('[data-wp-rest-url]');
            root = el ? el.getAttribute('data-wp-rest-url') : (window.aidunite_favorite_teams && window.aidunite_favorite_teams.rest_url) || '';
        }
        var nonce = (typeof window.wp !== 'undefined' && window.wp.apiFetch && window.wp.apiFetch.getNonce && window.wp.apiFetch.getNonce()) || (document.querySelector('[data-wp-rest-nonce]') && document.querySelector('[data-wp-rest-nonce]').getAttribute('data-wp-rest-nonce')) || (window.aidunite_favorite_teams && window.aidunite_favorite_teams.nonce) || '';
        return { root: root, nonce: nonce };
    }

    function init() {
        var btns = document.querySelectorAll('.favorite-team-btn');
        if (!btns.length) return;

        btns.forEach(function(btn) {
            if (btn.dataset.favoriteTeamsBound) return;
            btn.dataset.favoriteTeamsBound = '1';
            btn.addEventListener('click', function() {
                var teamId = btn.getAttribute('data-team-id');
                if (!teamId) return;
                var config = getRestConfig();
                if (!config.root || !config.nonce) {
                    if (typeof console !== 'undefined') console.warn('Favorite teams: REST URL or nonce not found.');
                    return;
                }
                var url = config.root.replace(/\/$/, '') + '/aidunite/v1/favorite-teams/toggle';
                btn.disabled = true;
                fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': config.nonce
                    },
                    body: JSON.stringify({ team_id: parseInt(teamId, 10) }),
                    credentials: 'same-origin'
                })
                .then(function(res) { return res.json(); })
                .then(function(data) {
                    if (data.success && typeof data.added !== 'undefined') {
                        btn.setAttribute('data-favorite', data.added ? '1' : '0');
                        if (btn.classList.contains('favorite-team-btn--inline')) {
                            btn.textContent = data.added ? '★' : '☆';
                            btn.setAttribute('aria-label', data.added ? 'お気に入りから削除' : 'お気に入りに追加');
                        } else {
                            btn.textContent = data.added ? '★ お気に入り' : '☆ お気に入りに追加';
                            btn.setAttribute('aria-label', data.added ? 'お気に入りから削除' : 'お気に入りに追加');
                        }
                    }
                })
                .catch(function() { })
                .then(function() { btn.disabled = false; });
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
