/**
 * page-contact.php
 */
(function () {
  'use strict';
  var cfg = typeof aidunitePage_contact !== 'undefined' ? aidunitePage_contact : {};

  document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('contactForm');
    if (!form) {
      return;
    }

    form.addEventListener('submit', function (e) {
      e.preventDefault();

      var submitBtn = form.querySelector('button[type="submit"]');
      var payload = {
        name: form.querySelector('[name="name"]').value.trim(),
        email: form.querySelector('[name="email"]').value.trim(),
        subject: form.querySelector('[name="subject"]').value.trim(),
        category: form.querySelector('[name="category"]').value,
        message: form.querySelector('[name="message"]').value.trim()
      };

      var restBase = (cfg.restUrl || '/wp-json/aidunite/v1/').replace(/\/?$/, '/');

      aiduniteWithButtonLoading(submitBtn, function () {
        return fetch(restBase + 'contact', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': cfg.nonce || ''
          },
          body: JSON.stringify(payload)
        })
          .then(function (res) {
            return res.json().then(function (data) {
              if (!res.ok) {
                var msg = (data && data.message) ? data.message : '送信に失敗しました。';
                throw new Error(msg);
              }
              return data;
            });
          })
          .then(function (data) {
            aiduniteToast(data.message || 'お問い合わせありがとうございます。内容を確認の上、2-3営業日以内にご返信いたします。', 'success');
            form.reset();
          })
          .catch(function (err) {
            aiduniteToast(err.message || '送信に失敗しました。時間をおいて再度お試しください。', 'error');
          });
      }, '送信中...');
    });
  });
})();
