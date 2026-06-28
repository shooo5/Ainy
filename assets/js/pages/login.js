/**
 * ログインページ（page-login.php）
 * パスワード表示切替のみ。装飾アニメは login.css。
 */
(function () {
  'use strict';

  function initPasswordToggles() {
    document.querySelectorAll('.login-password-toggle').forEach(function (button) {
      button.addEventListener('click', function () {
        var targetId = this.dataset.target;
        var target = targetId ? document.getElementById(targetId) : null;
        var showIcon = this.querySelector('.login-toggle-icon--show');
        var hideIcon = this.querySelector('.login-toggle-icon--hide');

        if (!target || !showIcon || !hideIcon) {
          return;
        }

        var isVisible = target.type === 'text';
        target.type = isVisible ? 'password' : 'text';
        showIcon.hidden = !isVisible;
        hideIcon.hidden = isVisible;
        this.setAttribute('aria-pressed', String(!isVisible));
        this.setAttribute('aria-label', isVisible ? 'パスワードを表示' : 'パスワードを非表示');
      });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initPasswordToggles);
  } else {
    initPasswordToggles();
  }
})();
