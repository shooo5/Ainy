/**
 * マッチ申請一覧（page-match-requests.php）
 */
(function ($) {
  'use strict';

  var cfg = typeof aiduniteMatchRequestsPage !== 'undefined' ? aiduniteMatchRequestsPage : {};

  $(function () {
    $('.match-approve-btn, .match-reject-btn').on('click', function () {
      var row = $(this).closest('tr');
      var requestId = row.data('request-id');
      var isApprove = $(this).hasClass('match-approve-btn');
      var status = isApprove ? 'accepted' : 'rejected';
      var actionLabel = isApprove ? '承認済み' : '拒否済み';

      $.ajax({
        url: cfg.ajaxUrl || '',
        method: 'POST',
        data: {
          action: 'au_update_match_request_status',
          security: cfg.matchNonce || '',
          request_id: requestId,
          status: status,
        },
        success: function (res) {
          if (res && res.success) {
            if (typeof aiduniteToast === 'function') {
              aiduniteToast('申請を' + actionLabel + 'しました', 'success');
            }
            row.find('.status-badge')
              .removeClass('status-pending')
              .addClass(isApprove ? 'status-accepted' : 'status-rejected')
              .text(actionLabel);
            row.find('.match-approve-btn, .match-reject-btn').remove();
          } else if (typeof aiduniteToast === 'function') {
            aiduniteToast((res && res.data) || '更新に失敗しました', 'error');
          }
        },
        error: function () {
          if (typeof aiduniteToast === 'function') {
            aiduniteToast('更新に失敗しました', 'error');
          }
        },
      });
    });
  });
})(jQuery);
