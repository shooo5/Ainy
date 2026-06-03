/**
 * UIアニメーションライブラリの使用例
 * 各ページで参考にしてください
 */

// ページ読み込み完了後に実行
document.addEventListener('DOMContentLoaded', function() {

  // 例1: スケジュール登録ボタン
  const scheduleSubmitBtn = document.querySelector('#schedule-form button[type="submit"]');
  if (scheduleSubmitBtn) {
    // 既にpage-schedule-edit.phpで実装済み
    console.log('スケジュール登録ボタンにアニメーションが適用されています');
  }

  // 例2: 通知送信ボタン
  const notificationBtns = document.querySelectorAll('.notification-send-btn');
  notificationBtns.forEach(btn => {
    btn.addEventListener('click', function(e) {
      e.preventDefault();

      const loading = showLoadingAnimation(btn, '通知送信中...');

      // 通知送信処理（例）
      setTimeout(() => {
        loading.hide();
        playNotificationAnimation(btn);
      }, 1500);
    });
  });

  // 例3: 承認ボタン
  const approvalBtns = document.querySelectorAll('.approval-btn');
  approvalBtns.forEach(btn => {
    btn.addEventListener('click', function(e) {
      e.preventDefault();

      const loading = showLoadingAnimation(btn, '承認処理中...');

      // 承認処理（例）
      setTimeout(() => {
        loading.hide();
        playApprovalAnimation(btn);
      }, 1000);
    });
  });

  // 例4: 削除ボタン
  const deleteBtns = document.querySelectorAll('.delete-btn');
  deleteBtns.forEach(btn => {
    btn.addEventListener('click', function(e) {
      e.preventDefault();

      if (confirm('本当に削除しますか？')) {
        const loading = showLoadingAnimation(btn, '削除中...');

        // 削除処理（例）
        setTimeout(() => {
          loading.hide();
          playDeleteAnimation(btn);
        }, 800);
      }
    });
  });

  // 例5: 試合成立ボタン
  const matchSuccessBtns = document.querySelectorAll('.match-success-btn');
  matchSuccessBtns.forEach(btn => {
    btn.addEventListener('click', function(e) {
      e.preventDefault();

      const loading = showLoadingAnimation(btn, '試合成立処理中...');

      // 試合成立処理（例）
      setTimeout(() => {
        loading.hide();
        playMatchSuccessAnimation(btn);
      }, 1200);
    });
  });

  // 例6: カスタムアニメーション
  const customBtns = document.querySelectorAll('.custom-animation-btn');
  customBtns.forEach(btn => {
    btn.addEventListener('click', function(e) {
      e.preventDefault();

      // カスタムオプションでバスケットボールアニメーション
      playBallBounceAnimation(btn, {
        message: 'カスタムメッセージ！',
        ballSize: 80,
        bounceHeight: 150,
        showMessage: true
      });
    });
  });
});

/**
 * 使用可能なアニメーション関数一覧
 *
 * 1. playBallBounceAnimation(target, options)
 *    - バスケットボールのバウンスアニメーション
 *    - options: { message, ballSize, bounceHeight, showMessage }
 *
 * 2. playNotificationAnimation(target)
 *    - 通知送信用アニメーション
 *
 * 3. playApprovalAnimation(target)
 *    - 承認用アニメーション（チェックマーク）
 *
 * 4. playDeleteAnimation(target)
 *    - 削除用アニメーション（ゴミ箱）
 *
 * 5. playMatchSuccessAnimation(target)
 *    - 試合成立用アニメーション（トロフィー + 星）
 *
 * 6. showLoadingAnimation(target, message)
 *    - ローディングアニメーション
 *    - 戻り値: { hide() } メソッドを持つオブジェクト
 *
 * 使用例:
 *
 * // 基本的な使用
 * playBallBounceAnimation('#my-button');
 *
 * // カスタムオプション付き
 * playBallBounceAnimation('#my-button', {
 *   message: '完了しました！',
 *   ballSize: 70,
 *   bounceHeight: 120
 * });
 *
 * // ローディング + アニメーション
 * const loading = showLoadingAnimation('#my-button', '処理中...');
 *
 * // 処理完了後
 * loading.hide();
 * playBallBounceAnimation('#my-button');
 */
