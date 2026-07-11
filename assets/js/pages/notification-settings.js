/**
 * 通知設定ページ（page-notification-settings.php）
 */
(function () {
  'use strict';

  function setTestResultWithIcon(el, basename, message) {
    if (!el) {
      return;
    }
    el.textContent = '';
    if (typeof AidUniteThemeIcons !== 'undefined') {
      var iconWrap = document.createElement('span');
      iconWrap.className = 'notification-test-result__icon';
      iconWrap.innerHTML = AidUniteThemeIcons.html(basename, 16);
      el.appendChild(iconWrap);
      el.appendChild(document.createTextNode(' '));
    }
    el.appendChild(document.createTextNode(String(message || '')));
  }

  function initEmailToggle() {
    var cb = document.getElementById('email_notifications');
    if (!cb) {
      return;
    }
    var sr = document.getElementById('email-notifications-state-sr');
    function sync() {
      cb.setAttribute('aria-checked', cb.checked ? 'true' : 'false');
      if (sr) {
        sr.textContent = cb.checked ? 'オン' : 'オフ';
      }
    }
    cb.addEventListener('change', sync);
    sync();
  }

  function initAdminTestSend() {
    var cfg = typeof aiduniteNotificationSettings !== 'undefined' ? aiduniteNotificationSettings : {};
    if (!cfg.canAdminTest) {
      return;
    }
    var testSendBtn = document.getElementById('notification-test-send-btn');
    var testResultEl = document.getElementById('notification-test-result');
    if (!testSendBtn || !testResultEl) {
      return;
    }

    testSendBtn.addEventListener('click', function () {
      testSendBtn.disabled = true;
      testResultEl.textContent = '送信中...';
      testResultEl.classList.remove('is-success', 'is-error');

      fetch(cfg.testUrl || '', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': cfg.restNonce || '',
        },
        body: JSON.stringify({}),
      })
        .then(function (res) {
          return res.json();
        })
        .then(function (data) {
          testSendBtn.disabled = false;
          if (data.success) {
            var mail = data.mail || {};
            var mailFailed = mail.attempted && mail.ok === false;
            setTestResultWithIcon(testResultEl, 'check', data.message || '送信しました');
            testResultEl.classList.toggle('is-error', mailFailed);
            testResultEl.classList.toggle('is-success', !mailFailed);
            if (typeof showToastNotification !== 'undefined') {
              showToastNotification(data.message || 'テスト通知を送信しました', mailFailed ? 'error' : 'success');
            }
          } else {
            setTestResultWithIcon(testResultEl, 'brightness_alert', data.message || '失敗');
            testResultEl.classList.add('is-error');
            if (typeof showToastNotification !== 'undefined') {
              showToastNotification(data.message || '送信に失敗しました', 'error');
            }
          }
        })
        .catch(function () {
          testSendBtn.disabled = false;
          setTestResultWithIcon(testResultEl, 'brightness_alert', 'エラー');
          testResultEl.classList.add('is-error');
          if (typeof aiduniteToast === 'function') {
            aiduniteToast('通信エラーが発生しました', 'error');
          }
        });
    });
  }

  function initSaveToast() {
    var cfg = typeof aiduniteNotificationSettings !== 'undefined' ? aiduniteNotificationSettings : {};
    if (!cfg.settingsSaved) {
      return;
    }
    if (typeof showToastNotification === 'function') {
      showToastNotification('設定を保存しました', 'success');
    }
    if (window.history && window.history.replaceState) {
      var url = new URL(window.location.href);
      url.searchParams.delete('settings_saved');
      window.history.replaceState({}, '', url.pathname + url.search + url.hash);
    }
  }

  function init() {
    initEmailToggle();
    initSaveToast();
    initAdminTestSend();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
