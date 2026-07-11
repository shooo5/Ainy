/**
 * page-admin-user-list.php
 */
(function () {
  'use strict';
  var cfg = typeof aidunitePage_admin_user_list !== 'undefined' ? aidunitePage_admin_user_list : {};

(function() {
    const selectAll = document.getElementById('select-all-users');
    const bulkDeleteBtn = document.getElementById('user-bulk-delete-btn');
    const bulkCountEl = document.getElementById('user-bulk-selected-count');

    function getUserCheckboxes() {
        return document.querySelectorAll('.user-checkbox');
    }

    function updateUserBulkUi() {
        const boxes = getUserCheckboxes();
        const checked = document.querySelectorAll('.user-checkbox:checked');
        if (bulkCountEl) {
            bulkCountEl.textContent = checked.length + '件選択';
        }
        if (bulkDeleteBtn) {
            bulkDeleteBtn.disabled = checked.length === 0;
        }
        if (selectAll && boxes.length > 0) {
            selectAll.checked = boxes.length === checked.length;
            selectAll.indeterminate = checked.length > 0 && checked.length < boxes.length;
        }
    }

    if (selectAll) {
        selectAll.addEventListener('change', function() {
            getUserCheckboxes().forEach(function(box) {
                box.checked = selectAll.checked;
            });
            updateUserBulkUi();
        });
    }

    getUserCheckboxes().forEach(function(box) {
        box.addEventListener('change', updateUserBulkUi);
    });

    updateUserBulkUi();

    function injectUserIdsToForm(form) {
        if (!form) {
            return;
        }
        form.querySelectorAll('input.js-user-bulk-hidden-id').forEach(function(el) {
            el.remove();
        });
        document.querySelectorAll('.user-checkbox:checked').forEach(function(box) {
            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'user_ids[]';
            hidden.value = box.value;
            hidden.className = 'js-user-bulk-hidden-id';
            form.appendChild(hidden);
        });
    }

    function bindBulkFormConfirm(form, buildMessage) {
        if (!form) return;
        form.addEventListener('submit', function(e) {
            if (form.getAttribute('data-aidunite-bulk-approved') === '1') {
                form.removeAttribute('data-aidunite-bulk-approved');
                return;
            }
            e.preventDefault();
            injectUserIdsToForm(form);
            const checkedBoxes = document.querySelectorAll('.user-checkbox:checked');
            if (checkedBoxes.length === 0) {
                aiduniteToast('操作対象のユーザーを選択してください。', 'warning');
                return;
            }
            const msg = buildMessage(checkedBoxes.length);
            if (!msg) return;
            aiduniteConfirm({
                message: msg,
                confirmLabel: '実行する',
                confirmVariant: form.id === 'bulk-delete-users-form' ? 'danger' : 'primary',
                onConfirm: function() {
                    form.setAttribute('data-aidunite-bulk-approved', '1');
                    form.submit();
                }
            });
        });
    }

    bindBulkFormConfirm(document.getElementById('bulk-actions-form'), function(n) {
        const bulkRole = document.getElementById('bulk_role').value;
        if (!bulkRole) {
            aiduniteToast('ロールを選択してください。', 'warning');
            return '';
        }
        return '選択された ' + n + ' 件のユーザーのロールを ' + bulkRole + ' に変更しますか？';
    });

    bindBulkFormConfirm(document.getElementById('bulk-delete-users-form'), function(n) {
        return '選択された ' + n + ' 件のユーザーを削除しますか？\nこの操作は取り消せません。';
    });
})();

// 詳細表示のトグル
function toggleDetails(userId) {
    const details = document.getElementById('details-' + userId);
    const toggleText = document.getElementById('toggle-text-' + userId);

    if (details.classList.contains('active')) {
        details.classList.remove('active');
        toggleText.textContent = '詳細表示';
    } else {
        details.classList.add('active');
        toggleText.textContent = '閉じる';
    }
}
})();
