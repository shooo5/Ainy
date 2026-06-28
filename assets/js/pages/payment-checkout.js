/**
 * Embedded Checkout（/payment-checkout）
 */
(function ($) {
  'use strict';

  var cfg = typeof aidunitePaymentCheckoutPage !== 'undefined' ? aidunitePaymentCheckoutPage : {};
  var mountEl = document.getElementById('payment-checkout-embedded');
  var errorEl = document.getElementById('payment-checkout-error');

  function showError(message) {
    if (!errorEl) {
      return;
    }
    errorEl.hidden = false;
    errorEl.textContent = message;
  }

  function clearLoading() {
    if (!mountEl) {
      return;
    }
    var loading = mountEl.querySelector('.payment-checkout-loading');
    if (loading) {
      loading.remove();
    }
  }

  async function mountEmbeddedCheckout() {
    if (!mountEl) {
      return;
    }

    if (!cfg.publishableKey) {
      clearLoading();
      showError('Stripe の公開キーが未設定です。管理者に連絡してください。');
      return;
    }

    if (typeof Stripe !== 'function') {
      clearLoading();
      showError('決済フォームの読み込みに失敗しました。ページを再読み込みしてください。');
      return;
    }

    try {
      var response = await $.ajax({
        url: cfg.ajaxUrl || '',
        type: 'POST',
        data: {
          action: 'aidunite_create_checkout',
          nonce: cfg.paymentNonce || '',
          ui_mode: cfg.uiMode || 'embedded'
        }
      });

      if (!response || !response.success || !response.data || !response.data.client_secret) {
        var message = response && response.data && response.data.message
          ? response.data.message
          : '決済ページの準備に失敗しました。';
        clearLoading();
        showError(message);
        return;
      }

      clearLoading();

      var stripe = Stripe(cfg.publishableKey);
      var checkout = await stripe.initEmbeddedCheckout({
        clientSecret: response.data.client_secret
      });

      checkout.mount('#payment-checkout-embedded');
    } catch (xhrError) {
      clearLoading();
      var ajaxMessage = '通信エラーが発生しました。';
      if (xhrError && xhrError.responseJSON && xhrError.responseJSON.data && xhrError.responseJSON.data.message) {
        ajaxMessage = xhrError.responseJSON.data.message;
      }
      showError(ajaxMessage);
    }
  }

  $(function () {
    mountEmbeddedCheckout();
  });
}(jQuery));
