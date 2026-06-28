(function () {
    'use strict';

    function scrollToHighlightSchedule() {
        var root = document.querySelector('[data-highlight-schedule-id]');
        if (!root) {
            return;
        }
        var scheduleId = root.getAttribute('data-highlight-schedule-id');
        if (!scheduleId || scheduleId === '0') {
            return;
        }
        var target = document.getElementById('attendance-schedule-' + scheduleId);
        if (target) {
            target.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }

    function updateCounter(textarea) {
        var wrap = textarea.closest('.attendance-card__comment');
        if (!wrap) return;
        var countEl = wrap.querySelector('.attendance-card__comment-count');
        if (!countEl) return;
        countEl.textContent = String(textarea.value.length);
    }

    function syncChoiceSelection(form) {
        form.querySelectorAll('.attendance-choice').forEach(function (choice) {
            var input = choice.querySelector('.attendance-choice__input');
            if (!input) return;
            choice.classList.toggle('is-selected', input.checked);
        });
    }

    document.querySelectorAll('.attendance-form').forEach(function (form) {
        form.querySelectorAll('.attendance-choice__input').forEach(function (input) {
            input.addEventListener('change', function () {
                syncChoiceSelection(form);
            });
            input.addEventListener('click', function () {
                syncChoiceSelection(form);
            });
        });
        syncChoiceSelection(form);
    });

    document.querySelectorAll('.attendance-card__comment-input').forEach(function (textarea) {
        updateCounter(textarea);
        textarea.addEventListener('input', function () {
            updateCounter(textarea);
        });
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', scrollToHighlightSchedule);
    } else {
        scrollToHighlightSchedule();
    }
})();
