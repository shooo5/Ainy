/**
 * page-feedback.php
 */
(function () {
  'use strict';
  var cfg = typeof aidunitePage_feedback !== 'undefined' ? aidunitePage_feedback : {};

  document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('feedbackForm');
    var fileInput = document.getElementById('attachment');
    if (!form) {
      return;
    }

    if (fileInput) {
      fileInput.addEventListener('change', function () {
        var file = this.files[0];
        if (file && file.size > 5 * 1024 * 1024) {
          aiduniteToast('ファイルサイズは5MB以下にしてください。', 'warning');
          this.value = '';
        }
      });
    }

    form.addEventListener('submit', function (e) {
      e.preventDefault();

      if (fileInput && fileInput.files.length > 0) {
        aiduniteToast('添付ファイル付きの送信は準備中です。テキストのみ送信してください。', 'warning');
        return;
      }

      var submitBtn = form.querySelector('button[type="submit"]');
      var environment = [];
      form.querySelectorAll('[name="environment[]"]:checked').forEach(function (el) {
        environment.push(el.value);
      });

      var payload = {
        feedbackType: form.querySelector('[name="feedbackType"]').value,
        priority: form.querySelector('[name="priority"]').value,
        title: form.querySelector('[name="title"]').value.trim(),
        description: form.querySelector('[name="description"]').value.trim(),
        environment: environment,
        browser: form.querySelector('[name="browser"]').value,
        email: form.querySelector('[name="email"]').value.trim()
      };

      var restBase = (cfg.restUrl || '/wp-json/aidunite/v1/').replace(/\/?$/, '/');

      aiduniteWithButtonLoading(submitBtn, function () {
        return fetch(restBase + 'feedback', {
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
            aiduniteToast(data.message || 'フィードバックを送信しました。ご協力ありがとうございます。', 'success');
            form.reset();
          })
          .catch(function (err) {
            aiduniteToast(err.message || '送信に失敗しました。時間をおいて再度お試しください。', 'error');
          });
      }, '送信中...');
    });

    var requiredFields = form.querySelectorAll('[required]');
    requiredFields.forEach(function (field) {
      field.addEventListener('blur', function () {
        if (this.value.trim() === '') {
          this.classList.add('error');
          this.classList.remove('success');
        } else {
          this.classList.remove('error');
          this.classList.add('success');
        }
      });
    });
  });
})();
