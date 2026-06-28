/**
 * preview-mode-banner — 折りたたみ + ロール切替（管理者）
 */
(function () {
  'use strict';
  var cfg = typeof aidunitePreviewModeBanner !== 'undefined' ? aidunitePreviewModeBanner : {};
  var STORAGE_KEY = 'aidunite_preview_banner_collapsed';
  var banner = document.getElementById('preview-mode-banner');
  var body = document.getElementById('preview-mode-banner-body');
  var toggleLabel = document.getElementById('preview-mode-banner-toggle-label');
  var header = document.getElementById('preview-mode-banner-toggle');

  if (!banner || !body) {
    return;
  }

  var collapsed = sessionStorage.getItem(STORAGE_KEY) === '1';
  if (collapsed) {
    banner.classList.add('is-collapsed');
    if (toggleLabel) {
      toggleLabel.textContent = '▶ 開く';
    }
    if (header) {
      header.setAttribute('aria-expanded', 'false');
    }
  } else {
    body.style.maxHeight = body.scrollHeight + 'px';
  }

  function setExpanded(expanded) {
    if (expanded) {
      banner.classList.remove('is-collapsed');
      body.style.maxHeight = body.scrollHeight + 'px';
      if (toggleLabel) {
        toggleLabel.textContent = '▼ 折りたたむ';
      }
      if (header) {
        header.setAttribute('aria-expanded', 'true');
      }
      sessionStorage.removeItem(STORAGE_KEY);
    } else {
      banner.classList.add('is-collapsed');
      body.style.maxHeight = '0';
      if (toggleLabel) {
        toggleLabel.textContent = '▶ 開く';
      }
      if (header) {
        header.setAttribute('aria-expanded', 'false');
      }
      sessionStorage.setItem(STORAGE_KEY, '1');
    }
  }

  function toggle() {
    setExpanded(banner.classList.contains('is-collapsed'));
  }

  if (header) {
    header.addEventListener('click', toggle);
    header.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        toggle();
      }
    });
  }

  var select = document.getElementById('preview-mode-select');
  if (select && cfg.baseUrl) {
    select.addEventListener('change', function () {
      var mode = this.value;
      if (!mode) {
        return;
      }
      var base = String(cfg.baseUrl);
      var path = base.split('?')[0].replace(/\/?$/, '');
      window.location.href = path + '?mode=' + encodeURIComponent(mode);
    });
  }
})();
