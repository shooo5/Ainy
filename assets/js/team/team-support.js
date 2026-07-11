/**
 * team-display-template.php — チーム応援
 */
(function () {
  'use strict';
  var cfg = typeof aiduniteTeamSupport !== 'undefined' ? aiduniteTeamSupport : {};

  window.supportTeam = function supportTeam(teamId) {
    if (typeof aiduniteConfirm !== 'function') {
      return;
    }
    aiduniteConfirm({
      message: 'このチームを応援しますか？',
      confirmLabel: '応援する',
      confirmVariant: 'primary',
      onConfirm: function () {
        var formData = new FormData();
        formData.append('action', 'support_team');
        formData.append('team_id', teamId);
        formData.append('nonce', cfg.nonce || '');

        fetch(cfg.ajaxUrl || '', {
          method: 'POST',
          body: formData,
        })
          .then(function (response) {
            return response.json();
          })
          .then(function (data) {
            if (data.success) {
              if (typeof aiduniteToast === 'function') {
                aiduniteToast('応援ありがとうございます！', 'success');
              }
              var btn = document.querySelector('.btn-support');
              if (btn) {
                btn.disabled = true;
                btn.textContent = '❤️ 応援済み';
              }
            } else if (typeof aiduniteToast === 'function') {
              aiduniteToast(data.message || 'エラーが発生しました。', 'error');
            }
          })
          .catch(function (error) {
            console.error('Error:', error);
            if (typeof aiduniteToast === 'function') {
              aiduniteToast('通信エラーが発生しました。', 'error');
            }
          });
      },
    });
  };
})();
