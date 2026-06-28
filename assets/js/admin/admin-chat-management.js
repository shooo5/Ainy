/**
 * functions/admin/admin-menu.php — チャット管理 全選択
 */
(function () {
  'use strict';
  var selectAll = document.getElementById('select_all_rooms');
  if (!selectAll) {
    return;
  }
  selectAll.addEventListener('change', function () {
    var checked = !!selectAll.checked;
    document.querySelectorAll('.room-select-checkbox').forEach(function (cb) {
      cb.checked = checked;
    });
  });
})();
