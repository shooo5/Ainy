/**
 * page-parent-payment.php
 */
(function ($) {
  'use strict';
  var cfg = typeof aidunitePage_parent_payment !== 'undefined' ? aidunitePage_parent_payment : {};

$(function () {
    // 支払い履歴を読み込む
    function loadPaymentHistory() {
        $.ajax({
            url: (cfg.ajaxUrl || ''),
            type: 'POST',
            data: {
                action: 'aidunite_get_parent_payment_history',
                team_id: parseInt(String(cfg.teamId || 0), 10),
                nonce: (cfg.paymentNonce || '')
            },
            success: function(response) {
                if (response.success && response.data.history) {
                    displayPaymentHistory(response.data.history);
                } else {
                    $('#payment-history-list').html('<p class="parent-payment-history__empty">お支払い履歴がありません</p>');
                }
            },
            error: function() {
                $('#payment-history-list').html('<p class="parent-payment-history__empty">履歴の読み込みに失敗しました</p>');
            }
        });
    }

    function displayPaymentHistory(history) {
        if (history.length === 0) {
            $('#payment-history-list').html('<p class="parent-payment-history__empty">お支払い履歴がありません</p>');
            return;
        }

        let html = '<table class="payment-history-table">';
        html += '<thead><tr><th>支払い日</th><th>金額</th><th>ステータス</th></tr></thead>';
        html += '<tbody>';

        history.forEach(function(item) {
            html += '<tr>';
            html += '<td>' + item.payment_date + '</td>';
            html += '<td>¥' + item.amount.toLocaleString() + '</td>';
            html += '<td>' + item.status + '</td>';
            html += '</tr>';
        });

        html += '</tbody></table>';
        $('#payment-history-list').html(html);
    }

    // Stripe Connect Checkout開始
    $('#start-stripe-connect-checkout').on('click', function() {
        if (cfg.checkoutPending) {
            return;
        }

        const button = $(this);
        const defaultLabel = String(cfg.checkoutButtonLabel || '支払い方法を登録する');
        button.prop('disabled', true).text('処理中...');

        $.ajax({
            url: (cfg.ajaxUrl || ''),
            type: 'POST',
            data: {
                action: 'aidunite_create_connect_checkout',
                team_id: parseInt(String(cfg.teamId || 0), 10),
                nonce: (cfg.paymentNonce || '')
            },
            success: function(response) {
                var payload = response.data && response.data.data ? response.data.data : response.data;
                if (response.success && payload && payload.url) {
                    window.location.href = payload.url;
                } else {
                    aiduniteToast((response.data && response.data.message) || 'エラーが発生しました', 'error');
                    button.prop('disabled', false).text(defaultLabel);
                }
            },
            error: function() {
                aiduniteToast('通信エラーが発生しました', 'error');
                button.prop('disabled', false).text(defaultLabel);
            }
        });
    });

    // 月謝サブスクリプション解約
    $('#cancel-stripe-connect-subscription').on('click', function() {
        const button = $(this);
        aiduniteConfirm({
            message: '月謝の自動支払いを停止しますか？\n当月分の請求タイミングなどはチーム代表者にご確認ください。',
            onConfirm: function() {
                button.prop('disabled', true).text('処理中...');

                $.ajax({
                    url: (cfg.ajaxUrl || ''),
                    type: 'POST',
                    data: {
                        action: 'aidunite_cancel_connect_subscription',
                        team_id: parseInt(String(cfg.teamId || 0), 10),
                        nonce: (cfg.paymentNonce || '')
                    },
                    success: function(response) {
                        if (response.success) {
                            aiduniteToast(response.data.message || '月謝の解約リクエストを受け付けました。', 'success');
                            button.prop('disabled', false).text('月謝を停止する');
                            loadPaymentHistory();
                        } else {
                            aiduniteToast(response.data.message || 'エラーが発生しました', 'error');
                            button.prop('disabled', false).text('月謝を停止する');
                        }
                    },
                    error: function() {
                        aiduniteToast('通信エラーが発生しました', 'error');
                        button.prop('disabled', false).text('月謝を停止する');
                    }
                });
            }
        });
    });

    // 初回読み込み
    loadPaymentHistory();
});
})(jQuery);
