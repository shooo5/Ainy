/**
 * page-admin-match-feedback-list.php
 */
(function () {
  'use strict';
  var cfg = typeof aidunitePage_admin_match_feedback_list !== 'undefined' ? aidunitePage_admin_match_feedback_list : {};

(function () {
    function toggleDetails(feedbackId, open) {
        var row = document.getElementById('mfl-details-' + feedbackId);
        var btn = document.querySelector('.admin-mfl-details-toggle[data-feedback-id="' + feedbackId + '"]');
        if (!row) return;
        var show = typeof open === 'boolean' ? open : row.hasAttribute('hidden');
        if (show) {
            row.removeAttribute('hidden');
            if (btn) btn.setAttribute('aria-expanded', 'true');
        } else {
            row.setAttribute('hidden', 'hidden');
            if (btn) btn.setAttribute('aria-expanded', 'false');
        }
    }
    document.querySelectorAll('.admin-mfl-details-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            toggleDetails(btn.getAttribute('data-feedback-id'));
        });
    });
    document.querySelectorAll('.admin-mfl-details-close').forEach(function (btn) {
        btn.addEventListener('click', function () {
            toggleDetails(btn.getAttribute('data-feedback-id'), false);
        });
    });
})();
})();
