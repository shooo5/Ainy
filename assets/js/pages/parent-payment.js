/**
 * page-parent-payment.php
 */
(function ($) {
  'use strict';
  var cfg = typeof aidunitePage_parent_payment !== 'undefined' ? aidunitePage_parent_payment : {};

  function setHistoryEmptyMessage(message) {
    var container = document.getElementById('payment-history-list');
    if (!container) {
      return;
    }
    container.textContent = '';
    var paragraph = document.createElement('p');
    paragraph.className = 'parent-payment-history__empty';
    paragraph.textContent = message;
    container.appendChild(paragraph);
  }

  function sanitizeStatusClass(status) {
    var normalized = String(status || '').toLowerCase().replace(/[^a-z0-9_-]/g, '');
    return normalized ? ' parent-payment-history__status--' + normalized : '';
  }

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
                    displayPaymentHistory(response.data.history, response.data.show_status);
                } else {
                    setHistoryEmptyMessage('お支払い履歴がありません');
                }
            },
            error: function() {
                setHistoryEmptyMessage('履歴の読み込みに失敗しました');
            }
        });
    }

    function displayPaymentHistory(history, showStatus) {
        var container = document.getElementById('payment-history-list');
        if (!container) {
            return;
        }

        if (!history.length) {
            setHistoryEmptyMessage('お支払い履歴がありません');
            return;
        }

        var shouldShowStatus = !!showStatus || history.some(function (item) {
            return item.status && item.status !== 'paid';
        });

        var table = document.createElement('table');
        table.className = 'payment-history-table';

        var thead = document.createElement('thead');
        var headerRow = document.createElement('tr');
        ['支払い日', '金額'].forEach(function (label) {
            var th = document.createElement('th');
            th.textContent = label;
            headerRow.appendChild(th);
        });
        if (shouldShowStatus) {
            var statusHeader = document.createElement('th');
            statusHeader.textContent = 'ステータス';
            headerRow.appendChild(statusHeader);
        }
        thead.appendChild(headerRow);
        table.appendChild(thead);

        var tbody = document.createElement('tbody');
        history.forEach(function(item) {
            var row = document.createElement('tr');

            var dateCell = document.createElement('td');
            dateCell.textContent = String(item.payment_date || '');
            row.appendChild(dateCell);

            var amountCell = document.createElement('td');
            var amount = Number(item.amount || 0);
            amountCell.textContent = '¥' + amount.toLocaleString();
            row.appendChild(amountCell);

            if (shouldShowStatus) {
                var statusCell = document.createElement('td');
                var statusSpan = document.createElement('span');
                statusSpan.className = 'parent-payment-history__status' + sanitizeStatusClass(item.status);
                statusSpan.textContent = String(item.status_label || item.status || '');
                statusCell.appendChild(statusSpan);
                row.appendChild(statusCell);
            }

            tbody.appendChild(row);
        });

        table.appendChild(tbody);
        container.textContent = '';
        container.appendChild(table);
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
