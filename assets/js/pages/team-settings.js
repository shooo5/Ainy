/**
 * チーム設定: 共有用 URL コピー
 */
(function () {
  'use strict';

  function copyText(text) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
      return navigator.clipboard.writeText(text);
    }
    return new Promise(function (resolve, reject) {
      var ta = document.createElement('textarea');
      ta.value = text;
      ta.setAttribute('readonly', '');
      ta.style.position = 'fixed';
      ta.style.left = '-9999px';
      document.body.appendChild(ta);
      ta.select();
      try {
        var ok = document.execCommand('copy');
        document.body.removeChild(ta);
        if (ok) {
          resolve();
        } else {
          reject(new Error('copy_failed'));
        }
      } catch (e) {
        document.body.removeChild(ta);
        reject(e);
      }
    });
  }

  function initPublicUrlCopy() {
    document.querySelectorAll('[data-copy-target]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var id = btn.getAttribute('data-copy-target');
        var el = id ? document.getElementById(id) : null;
        if (!el) {
          return;
        }
        var text = (el.value || el.textContent || '').trim();
        if (!text) {
          return;
        }
        var label = btn.textContent;
        copyText(text)
          .then(function () {
            btn.textContent = 'コピーしました';
            setTimeout(function () {
              btn.textContent = label;
            }, 2000);
          })
          .catch(function () {
            if (el.select) {
              el.select();
              el.setSelectionRange(0, text.length);
            }
            aiduniteToast('コピーに失敗しました。URLを選択して手動でコピーしてください。', 'warning');
          });
      });
    });
  }

  function initTeamLogoUpload() {
    if (typeof AiduniteTeamLogoUpload === 'undefined') {
      return;
    }
    var config = window.aiduniteTeamSettings || {};
    var hidden = document.getElementById('team_logo');
    var initialUrl = hidden ? hidden.value.trim() : '';
    var offsetXEl = document.getElementById('team_logo_offset_x');
    var offsetYEl = document.getElementById('team_logo_offset_y');
    var zoomEl = document.getElementById('team_logo_zoom');
    var crop = {
      x: offsetXEl ? parseInt(offsetXEl.value, 10) || 0 : 0,
      y: offsetYEl ? parseInt(offsetYEl.value, 10) || 0 : 0,
      zoom: zoomEl ? parseInt(zoomEl.value, 10) || 100 : 100,
    };
    var logoGlobal =
      typeof aiduniteTeamLogoUploadConfig !== 'undefined' ? aiduniteTeamLogoUploadConfig : {};
    AiduniteTeamLogoUpload.init({
      variant: 'settings',
      initialUrl: initialUrl,
      initialCrop: crop,
      config: {
        ajaxUrl: config.ajaxUrl || logoGlobal.ajaxUrl,
        nonce: config.nonce || logoGlobal.nonce,
        nonceField: config.nonceField || logoGlobal.nonceField || 'aidunite_team_settings_nonce',
        uploadAction: config.uploadAction || logoGlobal.uploadAction || 'team_settings_logo_upload',
        logoMaxBytes: config.logoMaxBytes || logoGlobal.logoMaxBytes,
        logoMaxLabel: config.logoMaxLabel || logoGlobal.logoMaxLabel,
      },
    });
  }

  function init() {
    initPublicUrlCopy();
    initTeamLogoUpload();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
