/**
 * @deprecated 2026-05 テンプレートに #confirm-match-button が存在しないため本番未使用。
 * マッチ操作は page-match-detail.php 内インラインスクリプト等で実装。新規利用禁止。
 */
// 共通ローディング関数を読み込み
if (typeof showLoadingWithTextChange === 'undefined') {
  // 共通関数が読み込まれていない場合は、このファイル内の関数を使用
  // 共通関数が読み込まれている場合は、それを使用
}

function initMatchActions() {
  /*--------------------------------------------------------------
    No.1 confirmMatch() → マッチ申請ボタン処理（自動マッチ or 通常申請）
  --------------------------------------------------------------*/
  // テスト環境ではglobal.documentを優先的に使用
  const doc = (typeof global !== 'undefined' && global.document) ? global.document : document;
  const confirmButton = doc.getElementById('confirm-match-button');
  if (confirmButton) {
    confirmButton.addEventListener('click', async () => {
      if (!confirm('この内容でマッチ申請しますか？')) return;

      const myTeamId = doc.getElementById('my_team_id')?.value;
      const myScheduleId = doc.getElementById('my_schedule_id')?.value;
      const otherTeamId = doc.getElementById('other_team_id')?.value;
      const otherScheduleId = doc.getElementById('other_schedule_id')?.value;
      const existingRequestId = doc.getElementById('match_request_id')?.value;

      let matchRequestId = existingRequestId;

      // 🔁 match_request が未作成 → 新規作成（通常申請 or 自動マッチ）
      if (!matchRequestId) {
        const bodyData = {
          my_team_id: myTeamId,
          other_team_id: otherTeamId,
          other_schedule_id: otherScheduleId
        };

        // ⛳ 自動マッチや将来拡張用（存在する場合のみ追加）
        if (myScheduleId) {
          bodyData.my_schedule_id = myScheduleId;
        }

        const createRes = await fetch('/wp-json/aidunite/v1/match-request', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': window.wpApiSettings?.nonce || ''
          },
          body: JSON.stringify(bodyData)
        });

        const createResult = await createRes.json().catch(() => ({}));

        if (!createRes.ok || !createResult.success || !createResult.match_request_id) {
          const createErr = createResult.message || (createResult.code ? createResult.message : '') || '申請作成に失敗しました';
          if (typeof showToastNotification !== 'undefined') {
            showToastNotification(createErr, 'error');
          } else {
            alert(`エラー：${createErr}`);
          }
          return;
        }

        matchRequestId = createResult.match_request_id;
      }

      // ✅ submit 処理（既存 or 作成済の match_request に対して）
      const submitRes = await fetch('/wp-json/aidunite/v1/update-match-status', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': window.wpApiSettings?.nonce || ''
        },
        body: JSON.stringify({
          match_id: matchRequestId,
          action: 'submit'
        })
      });

      const submitResult = await submitRes.json().catch(() => ({}));
      if (submitRes.ok && submitResult.success) {
        if (typeof showToastNotification !== 'undefined') {
          showToastNotification(submitResult.message || '申請が完了しました！', 'success');
        } else {
          alert(submitResult.message || '申請が完了しました！');
        }
        if (typeof location !== 'undefined' && location.reload) {
        location.reload();
        }
      } else {
        const submitErr = submitResult.message || submitResult.data?.message || '申請に失敗しました';
        if (typeof showToastNotification !== 'undefined') {
          showToastNotification(submitErr, 'error');
        } else {
          alert(`エラー：${submitErr}`);
        }
      }
    });
  }

  /*--------------------------------------------------------------
    No.2 matchActionHandler() → 承認・拒否・キャンセルボタン処理
  --------------------------------------------------------------*/
  // テスト環境ではglobal.documentを優先的に使用（再評価）
  const doc2 = (typeof global !== 'undefined' && global.document) ? global.document : document;
  doc2.querySelectorAll('.match-action-button').forEach(button => {
    button.addEventListener('click', async () => {
      const matchId = button.dataset.matchId;
      const action = button.dataset.action;

      const confirmMsg = {
        approve: 'このマッチを承認しますか？',
        reject: 'このマッチを拒否しますか？',
        cancel: 'このマッチ申請をキャンセルしますか？'
      }[action] || 'よろしいですか？';

      if (!confirm(confirmMsg)) return;

      // ローディングアニメーション開始
      const processingTexts = ['実行中...', '完了しました'];
      showLoadingWithTextChange(processingTexts, async () => {
        const res = await fetch('/wp-json/aidunite/v1/update-match-status', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': window.wpApiSettings?.nonce || ''
          },
          body: JSON.stringify({ match_id: matchId, action })
        });

        const result = await res.json().catch(() => ({}));
        if (res.ok && result.success) {
          if (result.show_onboarding_bot_chat_modal
              && typeof window.aiduniteShowOnboardingBotChatModal === 'function') {
            window.aiduniteShowOnboardingBotChatModal(result.chat_url || '');
            return;
          }
          if (result.redirect_url) {
            if (typeof location !== 'undefined' && location.assign) {
              location.href = result.redirect_url;
            }
            return;
          }
          if (result.fallback_url) {
            if (typeof location !== 'undefined' && location.assign) {
              location.href = result.fallback_url;
            }
            return;
          }
          if (typeof location !== 'undefined' && location.reload) {
            location.reload();
          }
        } else {
          const errMsg = result.message || result.data?.message || '処理に失敗しました';
          console.error(`エラー：${errMsg}`);
          if (typeof showToastNotification !== 'undefined') {
            showToastNotification(errMsg, 'error');
          } else {
            alert(errMsg);
          }
          if (typeof location !== 'undefined' && location.reload) {
            location.reload();
          }
        }
      });
    });
  });

}

// テスト用にグローバルに公開（テスト環境でのみ使用）
// Jest環境ではglobal.jestが存在するが、念のためtypeof global !== 'undefined'もチェック
if (typeof global !== 'undefined') {
  global.initMatchActions = initMatchActions;
}

// DOMContentLoadedイベントで初期化
// 重複読み込み対策: 既に初期化済みの場合はスキップ
if (!window._matchActionsInitialized) {
// テスト環境ではglobal.documentを優先的に使用
const docForEvent = (typeof global !== 'undefined' && global.document) ? global.document : document;
    if (docForEvent && typeof docForEvent.addEventListener === 'function') {
docForEvent.addEventListener('DOMContentLoaded', function() {
  initMatchActions();
});
    }
    window._matchActionsInitialized = true;
}
