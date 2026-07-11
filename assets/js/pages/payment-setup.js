/**
 * 契約・お支払いページ（page-payment-setup.php）
 */
(function ($) {
  'use strict';

  var cfg = typeof aidunitePaymentSetupPage !== 'undefined' ? aidunitePaymentSetupPage : {};

  function getToastIconHtml(type) {
    var iconMap = { success: 'check_circle', error: 'brightness_alert', info: 'info' };
    if (typeof AidUniteThemeIcons !== 'undefined') {
      return AidUniteThemeIcons.html(iconMap[type] || 'info', 28);
    }
    return '';
  }

  function getBtnIconHtml(basename) {
    if (typeof AidUniteThemeIcons !== 'undefined') {
      return '<span class="btn-icon" aria-hidden="true">' + AidUniteThemeIcons.html(basename, 20) + '</span>';
    }
    return '';
  }

  function escapeHtml(value) {
    return String(value == null ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function showCenterToast(message, type) {
    type = type || 'success';
    var existingToast = document.querySelector('.payment-setup-toast');
    if (existingToast) {
      existingToast.remove();
    }

    var toast = document.createElement('div');
    toast.className = 'payment-setup-toast';
    toast.innerHTML =
      '<div class="payment-setup-toast-card">' +
      '<div class="payment-setup-toast-icon">' + getToastIconHtml(type) + '</div>' +
      '<div class="payment-setup-toast-message">' + escapeHtml(message) + '</div>' +
      '</div>';

    document.body.appendChild(toast);

    setTimeout(function () {
      if (toast.parentElement) {
        toast.classList.add('is-leaving');
        setTimeout(function () {
          if (toast.parentElement) {
            toast.remove();
          }
        }, 300);
      }
    }, 3000);
  }

  function startStripeCheckout(button) {
    var checkoutUrl = cfg.checkoutPageUrl || '/payment-checkout/';
    var useSavedCard = !!cfg.canStartSubscriptionWithSavedCard;

    if (!useSavedCard) {
      window.location.href = checkoutUrl;
      return;
    }

    var $button = button && button.jquery ? button : $(button);
    var originalHtml = $button.length ? $button.html() : '';
    if ($button.length) {
      $button.prop('disabled', true).html(getBtnIconHtml('hourglass_empty') + ' 処理中...');
    }

    $.ajax({
      url: cfg.ajaxUrl || '',
      type: 'POST',
      data: {
        action: 'aidunite_start_platform_team_subscription',
        nonce: cfg.paymentNonce || '',
        team_id: cfg.teamId || 0
      },
      success: function (response) {
        if (response.success && response.data && response.data.mode === 'instant') {
          showCenterToast('このチームの契約を開始しました', 'success');
          window.setTimeout(function () {
            window.location.href = (cfg.paymentSetupUrl || window.location.pathname) + '?payment=success';
          }, 800);
          return;
        }
        var errorMessage = response.data && response.data.message
          ? response.data.message
          : '契約の開始に失敗しました';
        if (response.data && response.data.error_code === 'no_saved_card') {
          window.location.href = checkoutUrl;
          return;
        }
        showCenterToast(errorMessage, 'error');
        if ($button.length) {
          $button.prop('disabled', false).html(originalHtml);
        }
      },
      error: function (xhr) {
        var errorMessage = '通信エラーが発生しました';
        if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
          errorMessage = xhr.responseJSON.data.message;
        }
        showCenterToast(errorMessage, 'error');
        if ($button.length) {
          $button.prop('disabled', false).html(originalHtml);
        }
      }
    });
  }

  function savePlanSelection(button, planId) {
    button.prop('disabled', true);

    $.ajax({
      url: cfg.ajaxUrl || '',
      type: 'POST',
      data: {
        action: 'aidunite_save_plan_selection',
        plan_id: planId,
        nonce: cfg.planSelectionNonce || ''
      },
      success: function (response) {
        if (response.success) {
          showCenterToast('プランを変更しました', 'success');
          setTimeout(function () {
            window.location.reload();
          }, 1000);
          return;
        }
        showCenterToast((response.data && response.data.message) || 'エラーが発生しました', 'error');
        button.prop('disabled', false);
      },
      error: function () {
        showCenterToast('通信エラーが発生しました', 'error');
        button.prop('disabled', false);
      }
    });
  }

  function confirmPlanChange(message, onConfirm) {
    if (typeof showConfirmModal === 'function') {
      showConfirmModal({
        title: 'プラン変更の確認',
        message: message,
        confirmLabel: '変更する',
        cancelLabel: 'キャンセル',
        confirmVariant: 'primary',
        onConfirm: onConfirm
      });
      return;
    }

    console.warn('[AidUnite] showConfirmModal unavailable:', message);
  }

  if (cfg.features && cfg.features.planChange) {
    $(document).on('click', '.js-payment-plan-select', function () {
      var button = $(this);
      var planId = button.data('plan-id');
      var productPlan = String(button.data('product-plan') || '');
      var currentPlan = String(cfg.currentProductPlan || 'match');

      if (!planId) {
        showCenterToast('プラン情報の取得に失敗しました', 'error');
        return;
      }

      if (productPlan === currentPlan) {
        return;
      }

      if (currentPlan === 'club' && productPlan === 'match') {
        showCenterToast('ClubプランからMatchプランへの変更は、サポートまでお問い合わせください', 'info');
        return;
      }

      var confirmMessage = productPlan === 'club'
        ? 'Clubプランに変更しますか？月額料金の計算方法が変わります。'
        : 'Matchプランに変更しますか？';

      confirmPlanChange(confirmMessage, function () {
        savePlanSelection(button, planId);
      });
    });
  }

  $(document).on('click', '.js-start-stripe-checkout', function () {
    startStripeCheckout($(this));
  });

  $(document).on('click', '.js-start-stripe-checkout-new-card', function () {
    var url = cfg.checkoutNewCardUrl
      || ((cfg.checkoutPageUrl || '/payment-checkout/').replace(/\/?$/, '/') + '?new_card=1');
    window.location.href = url;
  });

  function startBillingPortal(button) {
    var originalText = button.html();
    button.prop('disabled', true).html(getBtnIconHtml('hourglass_empty') + ' 処理中...');

    $.ajax({
      url: cfg.ajaxUrl || '',
      type: 'POST',
      data: {
        action: 'aidunite_create_billing_portal',
        nonce: cfg.paymentNonce || '',
        return_url: window.location.href
      },
      success: function (response) {
        var payload = response.data && response.data.data ? response.data.data : response.data;
        if (response.success && payload && payload.url) {
          window.location.href = payload.url;
          return;
        }
        var errorMessage = response.data && response.data.message ? response.data.message : 'ポータルを開けませんでした';
        showCenterToast(errorMessage, 'error');
        button.prop('disabled', false).html(originalText);
      },
      error: function (xhr) {
        var errorMessage = '通信エラーが発生しました';
        if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
          errorMessage = xhr.responseJSON.data.message;
        }
        showCenterToast(errorMessage, 'error');
        button.prop('disabled', false).html(originalText);
      }
    });
  }

  $(document).on('click', '.js-stripe-billing-portal', function () {
    startBillingPortal($(this));
  });

  function startPaymentExitCancel(button) {
    var completeLabel = cfg.cancelCompleteAtLabel || '';
    var message = 'このチームの契約を解約しますか？';
    if (completeLabel !== '') {
      message += '\n' + completeLabel + 'までご利用いただけます。';
    }

    var runCancel = function () {
      var $button = button && button.jquery ? button : $(button);
      var originalHtml = $button.length ? $button.html() : '';
      if ($button.length) {
        $button.prop('disabled', true).html(getBtnIconHtml('hourglass_empty') + ' 処理中...');
      }

      $.ajax({
        url: (cfg.restBase || '/wp-json/aidunite/v1/') + 'payment-exit/cancel',
        type: 'POST',
        contentType: 'application/json',
        beforeSend: function (xhr) {
          if (cfg.restNonce) {
            xhr.setRequestHeader('X-WP-Nonce', cfg.restNonce);
          }
        },
        data: JSON.stringify({
          team_id: cfg.teamId || 0
        }),
        success: function (response) {
          var msg = response.message || (response.data && response.data.message) || '解約手続きを受け付けました';
          showCenterToast(msg, 'success');
          window.setTimeout(function () {
            window.location.reload();
          }, 900);
        },
        error: function (xhr) {
          var errorMessage = '解約手続きに失敗しました';
          if (xhr.responseJSON && xhr.responseJSON.message) {
            errorMessage = xhr.responseJSON.message;
          } else if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
            errorMessage = xhr.responseJSON.data.message;
          }
          showCenterToast(errorMessage, 'error');
          if ($button.length) {
            $button.prop('disabled', false).html(originalHtml);
          }
        }
      });
    };

    var openConfirm = function () {
      if (typeof showConfirmModal === 'function') {
        showConfirmModal({
          title: '契約解約の確認',
          message: message,
          confirmLabel: '解約する',
          cancelLabel: 'キャンセル',
          confirmVariant: 'danger',
          onConfirm: runCancel
        });
        return;
      }

      if (window.confirm(message)) {
        runCancel();
      }
    };

    var teamId = cfg.teamId || 0;
    if (!teamId) {
      openConfirm();
      return;
    }

    $.ajax({
      url: (cfg.restBase || '/wp-json/aidunite/v1/') + 'payment-exit/evaluate?team_id=' + encodeURIComponent(teamId),
      type: 'GET',
      beforeSend: function (xhr) {
        if (cfg.restNonce) {
          xhr.setRequestHeader('X-WP-Nonce', cfg.restNonce);
        }
      },
      success: function (response) {
        if (response && response.gates && response.gates.can_start === false) {
          var gateMessage = (response.gates.messages && response.gates.messages[0])
            || '翌月以降の試合を先にキャンセルしてください。';
          showCenterToast(gateMessage, 'error');
          return;
        }
        openConfirm();
      },
      error: function () {
        showCenterToast('解約可否の確認に失敗しました。ページを再読み込みしてお試しください。', 'error');
      }
    });
  }

  $(document).on('click', '.js-payment-exit-cancel', function () {
    startPaymentExitCancel($(this));
  });
})(jQuery);
