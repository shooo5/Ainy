/**
 * チーム設定: 代表者譲渡 UI
 */
(function () {
  'use strict';

  const btn = document.getElementById('leader-transfer-submit');
  if (!btn || typeof aiduniteTeamLeaderTransfer === 'undefined') {
    return;
  }

  const cfg = aiduniteTeamLeaderTransfer;
  const msgEl = document.getElementById('leader-transfer-message');
  const checkoutDays = parseInt(String(cfg.checkoutDeadlineDays || '14'), 10) || 14;

  btn.addEventListener('click', function () {
    const target = document.getElementById('leader-transfer-target');
    const simultaneous = document.getElementById('leader-transfer-simultaneous');
    const toUserId = target ? parseInt(target.value, 10) : 0;

    if (!toUserId) {
      window.alert('譲渡先を選択してください。');
      return;
    }
    if (!window.confirm('代表者を譲渡します。よろしいですか？')) {
      return;
    }

    btn.disabled = true;

    fetch(cfg.restUrl || '/wp-json/aidunite/v1/team-leader/transfer', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': String(cfg.restNonce || ''),
      },
      body: JSON.stringify({
        team_id: parseInt(String(cfg.teamId || '0'), 10),
        new_leader_user_id: toUserId,
        simultaneous_handoff: !!(simultaneous && simultaneous.checked),
      }),
    })
      .then(function (res) {
        return res.json().then(function (data) {
          return { ok: res.ok, data: data };
        });
      })
      .then(function (result) {
        const message =
          result.data && result.data.data && result.data.data.mode === 'checkout_required'
            ? '譲渡しました。新代表が' + checkoutDays + '日以内に Checkout する必要があります。'
            : '代表者の譲渡が完了しました。';

        if (result.ok && result.data && result.data.success) {
          if (msgEl) {
            msgEl.textContent = message;
            msgEl.style.display = 'block';
          }
          window.setTimeout(function () {
            window.location.reload();
          }, 1500);
          return;
        }

        const err =
          result.data && result.data.message ? result.data.message : '譲渡に失敗しました。';
        window.alert(err);
        btn.disabled = false;
      })
      .catch(function () {
        window.alert('通信エラーが発生しました。');
        btn.disabled = false;
      });
  });
})();
