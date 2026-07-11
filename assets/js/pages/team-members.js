(function () {
  'use strict';

  function notify(message, type, title) {
    if (typeof showToastNotification === 'function') {
      showToastNotification(message, type, { title: title || undefined });
      return;
    }
    window.alert(message);
  }

  function confirmDelete(title, message, onConfirm) {
    if (typeof showConfirmModal === 'function') {
      showConfirmModal({
        title: title,
        message: message,
        confirmLabel: '削除する',
        cancelLabel: 'キャンセル',
        confirmVariant: 'danger',
        onConfirm: onConfirm,
      });
      return;
    }
    if (window.confirm(message)) {
      onConfirm();
    }
  }

  function initDeleteButtons(ajaxUrl) {
    document.querySelectorAll('.delete-player-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        const playerId = this.dataset.playerId;
        const nonce = this.dataset.nonce;
        const row = this.closest('tr');
        const playerName =
          row && row.dataset.playerLabel ? row.dataset.playerLabel.trim() : 'この選手';

        confirmDelete(
          '削除の確認',
          '本当に「' + playerName + '」を削除しますか？\nこの操作は取り消せません。',
          function () {
            fetch(ajaxUrl, {
              method: 'POST',
              headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
              body: new URLSearchParams({
                action: 'delete_player_ajax',
                player_id: playerId,
                nonce: nonce,
              }),
            })
              .then(function (response) {
                return response.json();
              })
              .then(function (data) {
                if (data.success) {
                  notify('選手を削除しました', 'success', '削除完了');
                  if (row) {
                    row.style.opacity = '0';
                    setTimeout(function () {
                      if (row.parentElement) {
                        row.remove();
                      }
                    }, 300);
                  }
                } else {
                  notify(
                    data.data && data.data.message ? data.data.message : '削除に失敗しました',
                    'error',
                    '削除失敗'
                  );
                }
              })
              .catch(function () {
                notify('削除中にエラーが発生しました', 'error', '削除失敗');
              });
          }
        );
      });
    });
  }

  function initParentDetailModal() {
    const modal = document.getElementById('team-parent-detail-modal');
    if (!modal) {
      return;
    }

    const dialog = modal.querySelector('.team-parent-detail-modal__dialog');
    const body = modal.querySelector('[data-parent-detail-body]');
    let lastFocus = null;

    const detailFields = [
      { key: 'parent_name_sei', label: '性' },
      { key: 'parent_name_mei', label: '名' },
      { key: 'parent_kana_sei', label: 'フリガナ性' },
      { key: 'parent_kana_mei', label: 'フリガナ名' },
      { key: 'parent_email', label: 'メールアドレス' },
      { key: 'parent_phone', label: '電話番号' },
      { key: 'linked_child_summary', label: '紐付け選手' },
      { key: 'tuition_payment_label', label: '決済有無' },
      { key: 'invite_type_label', label: '経路' },
      { key: 'registered_at', label: '登録日' },
    ];

    function escapeHtml(text) {
      return String(text ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
    }

    function displayValue(value) {
      const text = String(value ?? '').trim();
      return text !== '' ? text : '—';
    }

    function closeModal() {
      modal.hidden = true;
      document.body.classList.remove('team-parent-detail-modal-open');
      if (lastFocus && typeof lastFocus.focus === 'function') {
        lastFocus.focus();
      }
      lastFocus = null;
    }

    function openModal(detail) {
      if (!body) {
        return;
      }

      body.innerHTML = detailFields
        .map(function (field) {
          return (
            '<div class="team-parent-detail-modal__row">' +
            '<dt>' + escapeHtml(field.label) + '</dt>' +
            '<dd>' + escapeHtml(displayValue(detail[field.key])) + '</dd>' +
            '</div>'
          );
        })
        .join('');

      lastFocus = document.activeElement;
      modal.hidden = false;
      document.body.classList.add('team-parent-detail-modal-open');
      if (dialog && typeof dialog.focus === 'function') {
        dialog.focus();
      }
    }

    document.querySelectorAll('[data-parent-detail-open]').forEach(function (button) {
      button.addEventListener('click', function () {
        const raw = button.getAttribute('data-parent-detail') || '{}';
        let detail = {};
        try {
          detail = JSON.parse(raw);
        } catch (error) {
          detail = {};
        }
        openModal(detail);
      });
    });

    modal.querySelectorAll('[data-parent-detail-close]').forEach(function (el) {
      el.addEventListener('click', closeModal);
    });

    document.addEventListener('keydown', function (event) {
      if (modal.hidden || event.key !== 'Escape') {
        return;
      }
      closeModal();
    });
  }

  function initExport() {
    const exportBtn = document.querySelector('[data-team-members-export]');
    const table = document.querySelector('[data-team-members-table]');
    if (!exportBtn || !table) {
      return;
    }

    exportBtn.addEventListener('click', function () {
      const rows = [];
      table.querySelectorAll('thead tr th').forEach(function (th) {
        if (!rows[0]) {
          rows[0] = [];
        }
        rows[0].push('"' + th.textContent.trim().replace(/"/g, '""') + '"');
      });
      table.querySelectorAll('tbody tr').forEach(function (tr, idx) {
        rows[idx + 1] = [];
        tr.querySelectorAll('td').forEach(function (td) {
          const text = td.textContent.trim().replace(/\s+/g, ' ');
          rows[idx + 1].push('"' + text.replace(/"/g, '""') + '"');
        });
      });
      const csv = rows.map(function (r) {
        return r.join(',');
      }).join('\n');
      const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
      const url = URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;
      link.download = 'team-members.csv';
      link.click();
      URL.revokeObjectURL(url);
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    const config = window.aiduniteTeamMembers || {};
    const ajaxUrl = config.ajaxUrl || '/wp-admin/admin-ajax.php';
    initDeleteButtons(ajaxUrl);
    initParentDetailModal();
    initExport();
  });
})();
