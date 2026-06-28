/**
 * page-team-payment-management.php
 */
(function ($) {
  'use strict';
  var cfg = typeof aidunitePage_team_payment_management !== 'undefined' ? aidunitePage_team_payment_management : {};

$(function () {
    // 月謝トグルに応じて金額入力ブロックをON/OFF（グレーアウト）
    function aiduniteUpdateTuitionUI() {
        const enabled = $('#team_tuition_enabled_toggle').is(':checked');
        const $section = $('#tuition-amount-section');
        const $input = $('#team_monthly_fee');
        const $status = $('#tuition-status-text');

        if (enabled) {
            $section.removeClass('tuition-amount-disabled');
            $input.prop('disabled', false);
            $status.text('有効');
        } else {
            $section.addClass('tuition-amount-disabled');
            $input.prop('disabled', true);
            $status.text('無効');
        }
    }

    aiduniteUpdateTuitionUI();
    $('#team_tuition_enabled_toggle').on('change', aiduniteUpdateTuitionUI);

    $('#aidunite-start-connect-onboarding').on('click', function() {
        const $btn = $(this);
        const $msg = $('#aidunite-connect-onboarding-message');

        $btn.prop('disabled', true).text('処理中...');
        $msg.removeClass('error success').text('');

        $.ajax({
            url: (cfg.ajaxUrl || ''),
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'aidunite_create_connect_onboarding_link',
                team_id: parseInt(String(cfg.teamId || 0), 10),
                nonce: cfg.paymentNonce || ''
            }
        }).done(function(response) {
            var payload = response.data && response.data.data ? response.data.data : response.data;
            if (response.success && payload && payload.url) {
                window.location.href = payload.url;
            } else {
                const msg = (response.data && response.data.message) ? response.data.message : 'エラーが発生しました。';
                $msg.addClass('error').text(msg);
                $btn.prop('disabled', false).text('Stripe連携を開始');
            }
        }).fail(function(xhr) {
            var ajaxMessage = '通信エラーが発生しました。時間をおいて再度お試しください。';
            if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                ajaxMessage = xhr.responseJSON.data.message;
            }
            $msg.addClass('error').text(ajaxMessage);
            $btn.prop('disabled', false).text('Stripe連携を開始');
        });
    });
});
})(jQuery);
