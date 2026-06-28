/**
 * 保護者招待: QRコード描画（qrcode.js / davidshimjs-qrcodejs）
 */
(function () {
  'use strict';

  function initGuardianQrPanel() {
    var wrap = document.getElementById('guardian-qr-canvas-wrap');
    if (!wrap || typeof QRCode === 'undefined') {
      return;
    }

    var url = (wrap.getAttribute('data-qr-url') || '').trim();
    if (url === '') {
      return;
    }

    wrap.innerHTML = '';

    try {
      /* global QRCode */
      new QRCode(wrap, {
        text: url,
        width: 200,
        height: 200,
        colorDark: '#1e2a44',
        colorLight: '#ffffff',
        correctLevel: QRCode.CorrectLevel.M,
      });

      var rendered = wrap.querySelector('img, canvas');
      if (rendered) {
        rendered.setAttribute('role', 'img');
        rendered.setAttribute('aria-label', '参加用QRコード');
      }
    } catch (err) {
      wrap.textContent = 'QRコードを表示できませんでした';
    }
  }

  function initCopyQrUrlButton() {
    var copyBtn = document.getElementById('copy-qr-url-btn');
    var input = document.getElementById('qr_signup_url');
    if (!copyBtn || !input) {
      return;
    }
    copyBtn.addEventListener('click', function () {
      input.select();
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(input.value).catch(function () {
          document.execCommand('copy');
        });
      } else {
        document.execCommand('copy');
      }
      copyBtn.textContent = 'コピーしました';
    });
  }

  function init() {
    initGuardianQrPanel();
    initCopyQrUrlButton();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
}());
