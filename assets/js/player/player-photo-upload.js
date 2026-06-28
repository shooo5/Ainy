/**
 * 選手プロフィール写真（登録・編集）
 */
(function () {
  'use strict';

  var allowedMime = ['image/jpeg', 'image/png', 'image/webp'];
  var config = typeof AidUnitePlayerPhotoUpload !== 'undefined' ? AidUnitePlayerPhotoUpload : {};
  var maxBytes = parseInt(config.maxBytes, 10) || 10 * 1024 * 1024;
  var maxLabel = config.maxLabel || '10MB';

  function notify(message, type) {
    if (typeof aiduniteToast === 'function') {
      aiduniteToast(message, type || 'error');
      return;
    }
    window.alert(message);
  }

  function init(root) {
    var fileInput = root.querySelector('.player-photo-upload__file');
    var preview = root.querySelector('[data-player-photo-preview]');
    var frame = root.querySelector('[data-player-photo-frame]');
    var trigger = root.querySelector('[data-player-photo-trigger]');
    var changeBtn = root.querySelector('[data-player-photo-change]');
    var removeBtn = root.querySelector('[data-player-photo-remove]');
    var removeFlag = root.querySelector('[data-player-photo-remove-flag]');

    if (!fileInput || !preview || !frame || !removeFlag) {
      return;
    }

    function showImage(src) {
      preview.src = src;
      preview.hidden = false;
      frame.classList.add('has-image');
      if (trigger) {
        trigger.hidden = true;
      }
      if (changeBtn) {
        changeBtn.hidden = false;
      }
      if (removeBtn) {
        removeBtn.hidden = false;
      }
      removeFlag.value = '0';
    }

    function clearImage() {
      preview.src = '';
      preview.hidden = true;
      frame.classList.remove('has-image');
      if (trigger) {
        trigger.hidden = false;
      }
      if (changeBtn) {
        changeBtn.hidden = true;
      }
      if (removeBtn) {
        removeBtn.hidden = true;
      }
      fileInput.value = '';
      removeFlag.value = '1';
    }

    function openPicker() {
      fileInput.click();
    }

    if (trigger) {
      trigger.addEventListener('click', function (event) {
        event.stopPropagation();
      });
    }

    frame.addEventListener('click', function () {
      if (!frame.classList.contains('has-image')) {
        openPicker();
      }
    });

    fileInput.addEventListener('change', function () {
      var file = fileInput.files && fileInput.files[0];
      if (!file) {
        return;
      }
      if (allowedMime.indexOf(file.type) === -1) {
        notify('JPG / PNG / WebP の画像を選択してください。', 'error');
        fileInput.value = '';
        return;
      }
      if (file.size > maxBytes) {
        notify('ファイルサイズは' + maxLabel + '以下にしてください。', 'error');
        fileInput.value = '';
        return;
      }
      var reader = new FileReader();
      reader.onload = function (event) {
        if (event.target && typeof event.target.result === 'string') {
          showImage(event.target.result);
        }
      };
      reader.readAsDataURL(file);
    });

    if (removeBtn) {
      removeBtn.addEventListener('click', function (event) {
        event.preventDefault();
        event.stopPropagation();
        clearImage();
      });
    }
  }

  function boot() {
    document.querySelectorAll('[data-player-photo-upload]').forEach(init);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
