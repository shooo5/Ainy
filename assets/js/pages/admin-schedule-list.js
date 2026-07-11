/**
 * page-admin-schedule-list.php
 */
(function () {
  'use strict';
  var cfg = typeof aidunitePage_admin_schedule_list !== 'undefined' ? aidunitePage_admin_schedule_list : {};

(function() {
    const selectAllSchedules = document.getElementById('select-all-schedules');
    const bulkBtn = document.getElementById('schedule-bulk-delete-btn');
    const countEl = document.getElementById('schedule-bulk-selected-count');

    function getScheduleCheckboxes() {
        return document.querySelectorAll('.schedule-row-checkbox');
    }

    function updateScheduleBulkUi() {
        const boxes = getScheduleCheckboxes();
        const checked = document.querySelectorAll('.schedule-row-checkbox:checked');
        if (countEl) {
            countEl.textContent = checked.length + '件選択';
        }
        if (bulkBtn) {
            bulkBtn.disabled = checked.length === 0;
        }
        if (selectAllSchedules && boxes.length > 0) {
            selectAllSchedules.checked = boxes.length === checked.length;
            selectAllSchedules.indeterminate = checked.length > 0 && checked.length < boxes.length;
        }
    }

    if (selectAllSchedules) {
        selectAllSchedules.addEventListener('change', function() {
            getScheduleCheckboxes().forEach(function(box) {
                box.checked = selectAllSchedules.checked;
            });
            updateScheduleBulkUi();
        });
    }

    getScheduleCheckboxes().forEach(function(box) {
        box.addEventListener('change', updateScheduleBulkUi);
    });

    updateScheduleBulkUi();

    const bulkForm = document.getElementById('schedule-bulk-delete-form');
    if (bulkForm) {
        bulkForm.addEventListener('submit', function(e) {
            if (bulkForm.getAttribute('data-aidunite-bulk-approved') === '1') {
                bulkForm.removeAttribute('data-aidunite-bulk-approved');
                return;
            }
            e.preventDefault();
            bulkForm.querySelectorAll('input.js-schedule-bulk-hidden-id').forEach(function(el) {
                el.remove();
            });
            const checked = document.querySelectorAll('.schedule-row-checkbox:checked');
            if (checked.length === 0) {
                aiduniteToast('削除するスケジュールを選択してください。', 'warning');
                return;
            }
            const n = checked.length;
            aiduniteConfirm({
                message: '選択した ' + n + ' 件のスケジュールを削除しますか？\n申請中・成立済みマッチがある場合は削除できません。\nこの操作は取り消せません。',
                confirmLabel: '削除する',
                onConfirm: function() {
                    document.querySelectorAll('.schedule-row-checkbox:checked').forEach(function(box) {
                        const hidden = document.createElement('input');
                        hidden.type = 'hidden';
                        hidden.name = 'schedule_ids[]';
                        hidden.value = box.value;
                        hidden.className = 'js-schedule-bulk-hidden-id';
                        bulkForm.appendChild(hidden);
                    });
                    bulkForm.setAttribute('data-aidunite-bulk-approved', '1');
                    bulkForm.submit();
                }
            });
        });
    }

    function toggleScheduleDetails(scheduleId, open) {
        var row = document.getElementById('schedule-details-' + scheduleId);
        if (!row) {
            return;
        }
        var shouldOpen = typeof open === 'boolean' ? open : !row.classList.contains('is-open');
        row.classList.toggle('is-open', shouldOpen);
    }

    document.querySelectorAll('.details-toggle').forEach(function(link) {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            var id = this.getAttribute('data-schedule-id');
            if (id) {
                toggleScheduleDetails(id);
            }
        });
    });

    document.querySelectorAll('.schedule-details-close').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var id = this.getAttribute('data-schedule-id');
            if (id) {
                toggleScheduleDetails(id, false);
            }
        });
    });
})();
})();
