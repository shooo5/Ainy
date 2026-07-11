/**
 * 新規会員登録フォーム（/member-register）
 */
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('tunageru-register-form');
    const submitButton = document.getElementById('submit-button');
    const password = document.getElementById('password');
    const passwordConfirm = document.getElementById('password_confirm');
    const strengthFill = document.getElementById('strength-fill');
    const strengthText = document.getElementById('strength-text');
    const matchText = document.getElementById('password-match');

    if (!form || !submitButton || !password || !passwordConfirm) {
      return;
    }

    function markFieldError(input, hasError) {
      if (!input) {
        return;
      }
      input.classList.toggle('is-error', hasError);
    }

    function validateForm() {
      const errors = [];
      const fields = {
        last_name: form.querySelector('[name="last_name"]'),
        first_name: form.querySelector('[name="first_name"]'),
        user_email: form.querySelector('[name="user_email"]'),
        password: password,
        password_confirm: passwordConfirm,
      };

      Object.values(fields).forEach(function (field) {
        markFieldError(field, false);
      });

      if (!fields.last_name.value.trim()) {
        errors.push('姓は必須です');
        markFieldError(fields.last_name, true);
      }
      if (!fields.first_name.value.trim()) {
        errors.push('名は必須です');
        markFieldError(fields.first_name, true);
      }
      if (!fields.user_email.value.trim()) {
        errors.push('メールアドレスは必須です');
        markFieldError(fields.user_email, true);
      }
      if (password.value.length < 8) {
        errors.push('パスワードは8文字以上で入力してください');
        markFieldError(password, true);
      }
      if (password.value !== passwordConfirm.value) {
        errors.push('パスワードが一致しません');
        markFieldError(password, true);
        markFieldError(passwordConfirm, true);
      }
      if (!form.querySelector('[name="agree_terms"]').checked) {
        errors.push('利用規約およびプライバシーポリシーに同意してください');
      }

      if (typeof AidUniteFormUtils !== 'undefined' && errors.length === 0) {
        return AidUniteFormUtils.validateForm(form, {
          required: ['last_name', 'first_name', 'user_email', 'password', 'password_confirm', 'agree_terms'],
          email: ['user_email'],
          minLength: { password: 8 },
        });
      }

      return { valid: errors.length === 0, errors: errors };
    }

    function checkPasswordStrength(value) {
      if (!strengthFill || !strengthText) {
        return;
      }

      let score = 0;
      if (value.length >= 8) score += 1;
      if (/[a-z]/.test(value)) score += 1;
      if (/[A-Z]/.test(value)) score += 1;
      if (/[0-9]/.test(value)) score += 1;

      const levels = [
        { width: '0%', label: 'パスワード強度', className: '' },
        { width: '33%', label: '弱い', className: 'weak' },
        { width: '66%', label: '普通', className: 'medium' },
        { width: '100%', label: '強い', className: 'strong' },
      ];
      const level = value.length === 0 ? levels[0] : levels[Math.min(score, 3)];

      strengthFill.style.width = level.width;
      strengthFill.className = 'member-register-strength-fill' + (level.className ? ' ' + level.className : '');
      strengthText.textContent = level.label;
    }

    function validatePasswordMatch() {
      if (!matchText) {
        return;
      }

      if (!password.value && !passwordConfirm.value) {
        matchText.textContent = '';
        matchText.className = 'member-register-password-match';
        return;
      }

      if (passwordConfirm.value && password.value !== passwordConfirm.value) {
        matchText.textContent = 'パスワードが一致しません';
        matchText.className = 'member-register-password-match is-error';
        return;
      }

      if (passwordConfirm.value && password.value === passwordConfirm.value) {
        matchText.textContent = 'パスワードが一致しています';
        matchText.className = 'member-register-password-match is-success';
      }
    }

    password.addEventListener('input', function () {
      checkPasswordStrength(this.value);
      validatePasswordMatch();
    });

    passwordConfirm.addEventListener('input', validatePasswordMatch);

    document.querySelectorAll('.member-register-password-toggle').forEach(function (button) {
      button.addEventListener('click', function () {
        const target = document.getElementById(this.dataset.target);
        const showIcon = this.querySelector('.member-register-toggle-icon--show');
        const hideIcon = this.querySelector('.member-register-toggle-icon--hide');
        if (!target || !showIcon || !hideIcon) {
          return;
        }

        const isVisible = target.type === 'text';
        target.type = isVisible ? 'password' : 'text';
        showIcon.hidden = !isVisible;
        hideIcon.hidden = isVisible;
        this.setAttribute('aria-pressed', String(!isVisible));
        this.setAttribute('aria-label', isVisible ? 'パスワードを表示' : 'パスワードを非表示');
      });
    });

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      const validation = validateForm();

      if (!validation.valid) {
        if (typeof AidUniteFormUtils !== 'undefined') {
          AidUniteFormUtils.showFormErrors(form, validation.errors);
        } else {
          aiduniteToast(validation.errors.join('\n'), 'error');
        }
        form.classList.add('member-register-form--shake');
        setTimeout(function () {
          form.classList.remove('member-register-form--shake');
        }, 500);
        return;
      }

      submitButton.disabled = true;
      const submitText = submitButton.querySelector('.member-register-submit-text');
      if (submitText) {
        submitText.textContent = '登録中...';
      }
      form.submit();
    });
  });
})();
