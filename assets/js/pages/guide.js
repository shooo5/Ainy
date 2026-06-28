/**
 * page-guide.php
 */
(function () {
  'use strict';
  var cfg = typeof aidunitePage_guide !== 'undefined' ? aidunitePage_guide : {};


// 機能別タブ切り替え機能
function showFeatureTab(tabName) {
  // すべての機能タブボタンからactiveクラスを削除
  document.querySelectorAll('.feature-tab-btn').forEach(btn => {
    btn.classList.remove('active');
  });

  // すべての機能タブペインを非表示
  document.querySelectorAll('.feature-tab-pane').forEach(pane => {
    pane.classList.remove('active');
  });

  // クリックされたタブをアクティブにする
  event.target.classList.add('active');
  document.getElementById(tabName).classList.add('active');
}
})();
