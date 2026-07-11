/**
 * チーム管理（page-team-management.php）
 */
(function() {
    const selectAll = document.getElementById('select-all-teams');
    const bulkBtn = document.getElementById('team-bulk-delete-btn');
    const countEl = document.getElementById('team-bulk-selected-count');

    function parseTeamIds(raw) {
        if (!raw) {
            return [];
        }
        return String(raw).split(',').map(function(id) {
            return id.trim();
        }).filter(Boolean);
    }

    function getTeamCheckboxes() {
        return document.querySelectorAll('.team-bulk-checkbox');
    }

    function getGroupCheckboxes() {
        return document.querySelectorAll('.team-bulk-group-checkbox');
    }

    function getLeaderBlockFromNode(node) {
        return node ? node.closest('.team-leader-block') : null;
    }

    function getCheckboxesInLeaderSection(sectionOrGroupCb) {
        const block = getLeaderBlockFromNode(sectionOrGroupCb);
        return block ? Array.from(block.querySelectorAll('.team-bulk-checkbox')) : [];
    }

    function getSelectedTeamIdSet() {
        const ids = new Set();
        getGroupCheckboxes().forEach(function(groupCb) {
            if (!groupCb.checked) {
                return;
            }
            parseTeamIds(groupCb.getAttribute('data-team-ids')).forEach(function(id) {
                ids.add(id);
            });
        });
        getTeamCheckboxes().forEach(function(box) {
            if (!box.checked) {
                return;
            }
            const block = getLeaderBlockFromNode(box);
            const groupCb = block ? block.querySelector('.team-bulk-group-checkbox') : null;
            if (groupCb && groupCb.checked) {
                return;
            }
            ids.add(box.value || box.getAttribute('data-team-id'));
        });
        return ids;
    }

    function syncLeaderGroupCheckboxState(groupCb) {
        const section = groupCb.closest('.team-management-leader-group');
        const allIds = parseTeamIds(groupCb.getAttribute('data-team-ids'));
        const visible = getCheckboxesInLeaderSection(section);
        let checkedVisible = 0;
        visible.forEach(function(box) {
            if (box.checked) {
                checkedVisible++;
            }
        });
        const total = allIds.length;
        const selectedViaGroup = groupCb.checked;
        if (selectedViaGroup) {
            groupCb.checked = true;
            groupCb.indeterminate = false;
        } else if (checkedVisible === 0) {
            groupCb.checked = false;
            groupCb.indeterminate = false;
        } else if (checkedVisible === visible.length && visible.length === total) {
            groupCb.checked = true;
            groupCb.indeterminate = false;
        } else {
            groupCb.checked = false;
            groupCb.indeterminate = checkedVisible > 0;
        }
        if (section) {
            section.classList.toggle('is-group-selected', groupCb.checked || groupCb.indeterminate);
        }
    }

    function updateTeamBulkUi() {
        const boxes = getTeamCheckboxes();
        const selectedIds = getSelectedTeamIdSet();
        if (countEl) {
            countEl.textContent = selectedIds.size + '件選択';
        }
        if (bulkBtn) {
            bulkBtn.disabled = selectedIds.size === 0;
        }
        if (selectAll && boxes.length > 0) {
            const visibleChecked = document.querySelectorAll('.team-bulk-checkbox:checked').length;
            const anyGroupChecked = Array.from(getGroupCheckboxes()).some(function(gcb) {
                return gcb.checked;
            });
            selectAll.checked = !anyGroupChecked && visibleChecked === boxes.length;
            selectAll.indeterminate = !selectAll.checked && (visibleChecked > 0 || selectedIds.size > visibleChecked);
        }
        boxes.forEach(function(box) {
            const card = box.closest('.team-card');
            if (card) {
                const id = box.value || box.getAttribute('data-team-id');
                card.classList.toggle('is-selected', selectedIds.has(id));
            }
        });
        getGroupCheckboxes().forEach(syncLeaderGroupCheckboxState);
    }

    if (selectAll) {
        selectAll.addEventListener('change', function() {
            getGroupCheckboxes().forEach(function(groupCb) {
                groupCb.checked = false;
                groupCb.indeterminate = false;
            });
            getTeamCheckboxes().forEach(function(box) {
                box.checked = selectAll.checked;
            });
            updateTeamBulkUi();
        });
    }

    getGroupCheckboxes().forEach(function(groupCb) {
        groupCb.addEventListener('change', function() {
            const section = groupCb.closest('.team-management-leader-group');
            const checked = groupCb.checked;
            getCheckboxesInLeaderSection(section).forEach(function(box) {
                box.checked = checked;
            });
            updateTeamBulkUi();
        });
    });

    getTeamCheckboxes().forEach(function(box) {
        box.addEventListener('change', function() {
            const block = getLeaderBlockFromNode(box);
            const groupCb = block ? block.querySelector('.team-bulk-group-checkbox') : null;
            if (groupCb && !box.checked) {
                groupCb.checked = false;
            }
            updateTeamBulkUi();
        });
    });

    updateTeamBulkUi();

    const bulkForm = document.getElementById('team-bulk-delete-form');
    if (bulkForm) {
        bulkForm.addEventListener('submit', function(e) {
            if (bulkForm.getAttribute('data-aidunite-bulk-approved') === '1') {
                bulkForm.removeAttribute('data-aidunite-bulk-approved');
                return;
            }
            e.preventDefault();
            bulkForm.querySelectorAll('input.js-team-bulk-hidden-id').forEach(function(el) {
                el.remove();
            });
            const selectedIds = getSelectedTeamIdSet();
            if (selectedIds.size === 0) {
                aiduniteToast('削除するチームを選択してください。', 'warning');
                return;
            }
            selectedIds.forEach(function(id) {
                const hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = 'team_ids[]';
                hidden.value = id;
                hidden.className = 'js-team-bulk-hidden-id';
                bulkForm.appendChild(hidden);
            });
            const n = selectedIds.size;
            aiduniteConfirm({
                message: '選択した ' + n + ' 件のチームを削除しますか？\n各チームのメンバー所属・スケジュール・通知・試合ログも削除されます。\nこの操作は取り消せません。',
                confirmLabel: '削除する',
                onConfirm: function() {
                    bulkForm.setAttribute('data-aidunite-bulk-approved', '1');
                    bulkForm.submit();
                }
            });
        });
    }

    if (typeof window.loadingSpinnerManager !== 'undefined' && typeof window.loadingSpinnerManager.hideAllSpinners === 'function') {
        window.loadingSpinnerManager.hideAllSpinners();
    }
})();

