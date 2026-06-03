/**
 * メールアドレス入力欄で全角を半角に自動変換（入力時の半角強制）
 * input[type="email"] および data-email-halfwidth が付いた要素に適用
 */
(function () {
  'use strict';

  function toHalfWidth(str) {
    if (typeof str !== 'string') return str;
    return str
      .replace(/[\uFF10-\uFF19]/g, function (c) { return String.fromCharCode(c.charCodeAt(0) - 0xFEE0); }) // ０-９
      .replace(/[\uFF21-\uFF3A]/g, function (c) { return String.fromCharCode(c.charCodeAt(0) - 0xFEE0); }) // Ａ-Ｚ
      .replace(/[\uFF41-\uFF5A]/g, function (c) { return String.fromCharCode(c.charCodeAt(0) - 0xFEE0); }) // ａ-ｚ
      .replace(/\u3000/g, ' ')   // 全角スペース
      .replace(/\uFF20/g, '@')  // ＠
      .replace(/\uFF0E/g, '.')  // ．
      .replace(/\uFF0D/g, '-') // －
      .replace(/\uFF3F/g, '_') // ＿
      .replace(/\uFF0B/g, '+'); // ＋
  }

  function applyHalfWidth(el) {
    if (!el || !el.value) return;
    var normalized = toHalfWidth(el.value);
    if (normalized !== el.value) {
      el.value = normalized;
      el.dispatchEvent(new Event('input', { bubbles: true }));
    }
  }

  function init() {
    var selector = 'input[type="email"], input[data-email-halfwidth="true"]';
    var inputs = document.querySelectorAll(selector);
    inputs.forEach(function (input) {
      input.addEventListener('input', function () { applyHalfWidth(this); });
      input.addEventListener('blur', function () { applyHalfWidth(this); });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  // 動的追加されたメール欄用に初期化関数を公開
  window.aiduniteEmailHalfwidthInit = init;
})();
