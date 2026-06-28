/**
 * 保護者サインアップ（page-guardian-signup.php）
 */
(function () {
  'use strict';

  function showFieldError(input, message) {
    if (!input) return;
    input.classList.add('error');
    var errorSpan = input.parentElement && input.parentElement.querySelector('.field-error');
    if (errorSpan) {
      errorSpan.textContent = message;
      errorSpan.classList.add('is-visible');
    }
  }

  function clearFieldError(input) {
    if (!input) return;
    input.classList.remove('error');
    var errorSpan = input.parentElement && input.parentElement.querySelector('.field-error');
    if (errorSpan) {
      errorSpan.textContent = '';
      errorSpan.classList.remove('is-visible');
    }
  }

  function init() {
    var form = document.getElementById('guardian-signup-form');
    if (!form || form.getAttribute('data-password-required') !== '1') {
      return;
    }

    var password = document.getElementById('password');
    var passwordConfirm = document.getElementById('password_confirm');

    function validatePasswordMatch() {
      if (!password || !passwordConfirm) return true;
      if (password.value && passwordConfirm.value && password.value !== passwordConfirm.value) {
        showFieldError(passwordConfirm, 'パスワードが一致しません');
        return false;
      }
      clearFieldError(passwordConfirm);
      return true;
    }

    if (password) password.addEventListener('input', validatePasswordMatch);
    if (passwordConfirm) passwordConfirm.addEventListener('input', validatePasswordMatch);

    document.querySelectorAll('.guardian-flow-password-toggle').forEach(function (button) {
      button.addEventListener('click', function () {
        var target = document.getElementById(this.dataset.target);
        var showIcon = this.querySelector('.guardian-flow-toggle-icon--show');
        var hideIcon = this.querySelector('.guardian-flow-toggle-icon--hide');
        if (!target || !showIcon || !hideIcon) return;

        var isVisible = target.type === 'text';
        target.type = isVisible ? 'password' : 'text';
        showIcon.hidden = !isVisible;
        hideIcon.hidden = isVisible;
        this.setAttribute('aria-pressed', String(!isVisible));
        this.setAttribute('aria-label', isVisible ? 'パスワードを表示' : 'パスワードを非表示');
      });
    });

    form.addEventListener('submit', function (e) {
      document.querySelectorAll('.field-error').forEach(function (err) {
        err.textContent = '';
        err.classList.remove('is-visible');
      });
      document.querySelectorAll('.guardian-flow-input.error').forEach(function (input) {
        input.classList.remove('error');
      });

      var hasError = false;
      ['parent_name_sei', 'parent_name_mei', 'parent_kana_sei', 'parent_kana_mei', 'parent_email'].forEach(function (id) {
        var el = document.getElementById(id);
        if (!el || !el.value.trim()) {
          showFieldError(el, '入力してください');
          hasError = true;
        }
      });

      if (password && (!password.value.trim() || password.value.length < 8)) {
        showFieldError(password, 'パスワードは8文字以上で入力してください');
        hasError = true;
      }
      if (!validatePasswordMatch()) hasError = true;

      var agreeTerms = form.querySelector('input[name="agree_terms"]');
      if (agreeTerms && !agreeTerms.checked) {
        hasError = true;
        alert('利用規約およびプライバシーポリシーに同意してください');
      }

      if (hasError) e.preventDefault();
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
