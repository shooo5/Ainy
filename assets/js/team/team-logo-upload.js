/**
 * チームロゴアップロード（作成申請・チーム設定共通）
 */
(function (global) {
  'use strict';

  var VARIANT_IDS = {
    registration: {
      file: 'team_logo_file',
      frame: 'team-logo-frame',
      media: 'team-logo-media',
      preview: 'team-logo-preview',
      placeholder: 'team-logo-placeholder',
      trigger: 'team-logo-trigger',
      remove: 'team-logo-remove',
      adjust: 'team-logo-adjust',
      zoomRange: 'team-logo-zoom-range',
      change: 'team-logo-change',
      hidden: 'team_logo',
      offsetX: 'team_logo_offset_x',
      offsetY: 'team_logo_offset_y',
      zoom: 'team_logo_zoom',
    },
    registration_male: {
      file: 'team_male_logo_file',
      frame: 'team-male-logo-frame',
      media: 'team-male-logo-media',
      preview: 'team-male-logo-preview',
      placeholder: 'team-male-logo-placeholder',
      trigger: 'team-male-logo-trigger',
      remove: 'team-male-logo-remove',
      adjust: 'team-male-logo-adjust',
      zoomRange: 'team-male-logo-zoom-range',
      change: 'team-male-logo-change',
      hidden: 'team_male_logo',
      offsetX: 'team_male_logo_offset_x',
      offsetY: 'team_male_logo_offset_y',
      zoom: 'team_male_logo_zoom',
    },
    registration_female: {
      file: 'team_female_logo_file',
      frame: 'team-female-logo-frame',
      media: 'team-female-logo-media',
      preview: 'team-female-logo-preview',
      placeholder: 'team-female-logo-placeholder',
      trigger: 'team-female-logo-trigger',
      remove: 'team-female-logo-remove',
      adjust: 'team-female-logo-adjust',
      zoomRange: 'team-female-logo-zoom-range',
      change: 'team-female-logo-change',
      hidden: 'team_female_logo',
      offsetX: 'team_female_logo_offset_x',
      offsetY: 'team_female_logo_offset_y',
      zoom: 'team_female_logo_zoom',
    },
    settings: {
      file: 'team_settings_logo_file',
      frame: 'team-settings-logo-frame',
      media: 'team-settings-logo-media',
      preview: 'team-settings-logo-preview',
      placeholder: 'team-settings-logo-placeholder',
      trigger: 'team-settings-logo-trigger',
      remove: 'team-settings-logo-remove',
      adjust: 'team-settings-logo-adjust',
      zoomRange: 'team-settings-logo-zoom-range',
      change: 'team-settings-logo-change',
      hidden: 'team_logo',
      offsetX: 'team_logo_offset_x',
      offsetY: 'team_logo_offset_y',
      zoom: 'team_logo_zoom',
    },
  };

  var allowedMime = ['image/jpeg', 'image/png', 'image/webp'];

  function notify(message, type) {
    if (typeof showToastNotification === 'function') {
      showToastNotification(message, type);
      return;
    }
    if (type === 'error') {
      window.alert(message);
    }
  }

  function mergeConfig(optionsConfig) {
    var base = typeof global.aiduniteTeamLogoUploadConfig === 'object' && global.aiduniteTeamLogoUploadConfig
      ? global.aiduniteTeamLogoUploadConfig
      : {};
    var extra = optionsConfig || {};
    var merged = {};
    Object.keys(base).forEach(function (key) {
      merged[key] = base[key];
    });
    Object.keys(extra).forEach(function (key) {
      merged[key] = extra[key];
    });
    return merged;
  }

  function isAllowedLogoFile(file) {
    if (!file) {
      return false;
    }
    if (file.type && allowedMime.indexOf(file.type) !== -1) {
      return true;
    }
    var name = (file.name || '').toLowerCase();
    return /\.(jpe?g|png|webp)$/i.test(name);
  }

  var LOGO_UPLOAD_MAX_DIMENSION = 1280;
  var LOGO_UPLOAD_COMPRESS_MIN_BYTES = 180 * 1024;

  function isLiveLinksHost() {
    var host = global.location && global.location.hostname ? global.location.hostname : '';
    return /\.localsite\.io$/i.test(host);
  }

  function maybeShowLiveLinksWarning(wrap) {
    if (!wrap || !isLiveLinksHost() || wrap.querySelector('.team-reg-logo-upload__live-links-note')) {
      return;
    }
    var note = document.createElement('p');
    note.className = 'team-reg-logo-upload__live-links-note';
    note.setAttribute('role', 'alert');
    note.textContent =
      'Live Links 経由ではロゴのアップロードが失敗することがあります。Local の「Open site」から開くローカル URL（例: aidunite-d.local）でお試しください。';
    var hint = wrap.querySelector('[data-team-logo-hint]');
    if (hint && hint.parentNode) {
      hint.parentNode.insertBefore(note, hint.nextSibling);
    } else {
      wrap.appendChild(note);
    }
  }

  function loadLogoImageSource(file) {
    return new Promise(function (resolve, reject) {
      if (global.createImageBitmap) {
        global
          .createImageBitmap(file, { imageOrientation: 'from-image' })
          .then(resolve)
          .catch(function () {
            loadLogoImageElement(file).then(resolve).catch(reject);
          });
        return;
      }
      loadLogoImageElement(file).then(resolve).catch(reject);
    });
  }

  function loadLogoImageElement(file) {
    return new Promise(function (resolve, reject) {
      var img = new Image();
      var objectUrl = URL.createObjectURL(file);
      img.onload = function () {
        URL.revokeObjectURL(objectUrl);
        resolve(img);
      };
      img.onerror = function () {
        URL.revokeObjectURL(objectUrl);
        reject(new Error('画像の読み込みに失敗しました'));
      };
      img.src = objectUrl;
    });
  }

  function getSourceDimensions(source) {
    if (source && typeof source.width === 'number' && typeof source.height === 'number') {
      return { width: source.width, height: source.height };
    }
    return {
      width: source.naturalWidth || 0,
      height: source.naturalHeight || 0,
    };
  }

  /**
   * スマホの高解像度 JPEG などを送信前に縮小（UPLOAD_ERR_PARTIAL 対策）
   */
  function compressLogoForUpload(file) {
    return new Promise(function (resolve) {
      if (!file || file.size < LOGO_UPLOAD_COMPRESS_MIN_BYTES) {
        resolve(file);
        return;
      }

      loadLogoImageSource(file)
        .then(function (source) {
          var dim = getSourceDimensions(source);
          var w = dim.width;
          var h = dim.height;
          if (w <= 0 || h <= 0) {
            if (source && typeof source.close === 'function') {
              source.close();
            }
            resolve(file);
            return;
          }

          var scale = Math.min(1, LOGO_UPLOAD_MAX_DIMENSION / Math.max(w, h));
          var canvas = document.createElement('canvas');
          canvas.width = Math.max(1, Math.round(w * scale));
          canvas.height = Math.max(1, Math.round(h * scale));
          var ctx = canvas.getContext('2d');
          if (!ctx) {
            if (source && typeof source.close === 'function') {
              source.close();
            }
            resolve(file);
            return;
          }

          ctx.drawImage(source, 0, 0, canvas.width, canvas.height);
          if (source && typeof source.close === 'function') {
            source.close();
          }

          var outType = 'image/jpeg';
          var ext = '.jpg';
          if (file.type === 'image/png' || /\.png$/i.test(file.name || '')) {
            outType = 'image/png';
            ext = '.png';
          } else if (file.type === 'image/webp' || /\.webp$/i.test(file.name || '')) {
            outType = 'image/webp';
            ext = '.webp';
          }

          canvas.toBlob(
            function (blob) {
              if (!blob) {
                resolve(file);
                return;
              }
              var base = (file.name || 'team-logo').replace(/\.[^.]+$/, '');
              resolve(
                new File([blob], base + ext, {
                  type: outType,
                  lastModified: Date.now(),
                })
              );
            },
            outType,
            outType === 'image/jpeg' ? 0.85 : undefined
          );
        })
        .catch(function () {
          resolve(file);
        });
    });
  }

  function parseUploadResponse(response, text) {
    var data = null;
    if (text) {
      try {
        data = JSON.parse(text);
      } catch (e) {
        throw new Error('サーバー応答の解析に失敗しました。ページを再読み込みしてお試しください。');
      }
    }
    if (!response.ok) {
      var errMsg =
        (data && data.message) ||
        (data && data.data && data.data.message) ||
        'アップロードに失敗しました（' + response.status + '）';
      throw new Error(errMsg);
    }
    return data;
  }

  function resolveUploadPayload(data) {
    if (!data) {
      return null;
    }
    var url = data.url || (data.data && data.data.url);
    if (data.success && url) {
      return {
        url: url,
        message: data.message || (data.data && data.data.message) || '',
      };
    }
    if (data.success === false && data.message) {
      throw new Error(data.message);
    }
    return null;
  }

  function init(options) {
    options = options || {};
    var variant = options.variant === 'settings' ? 'settings' : 'registration';
    if (options.variant && VARIANT_IDS[options.variant]) {
      variant = options.variant;
    }
    var ids = VARIANT_IDS[variant] || VARIANT_IDS.registration;
    var config = mergeConfig(options.config);

    var logoUploadWrapEarly = document.querySelector(
      '[data-team-logo-upload][data-variant="' + variant + '"]'
    );
    if (logoUploadWrapEarly && logoUploadWrapEarly.getAttribute('data-logo-initialized') === '1') {
      return null;
    }

    var logoHidden = document.getElementById(ids.hidden);
    var logoOffsetX = document.getElementById(ids.offsetX);
    var logoOffsetY = document.getElementById(ids.offsetY);
    var logoZoomHidden = document.getElementById(ids.zoom);
    var logoFileInput = document.getElementById(ids.file);
    var logoFrame = document.getElementById(ids.frame);
    var logoTrigger = document.getElementById(ids.trigger);
    var logoMedia = document.getElementById(ids.media);
    var logoPreview = document.getElementById(ids.preview);
    var logoPlaceholder = document.getElementById(ids.placeholder);
    var logoRemove = document.getElementById(ids.remove);
    var logoAdjust = document.getElementById(ids.adjust);
    var logoZoomRange = document.getElementById(ids.zoomRange);
    var logoChangeBtn = document.getElementById(ids.change);
    var logoUploadWrap = document.querySelector(
      '[data-team-logo-upload][data-variant="' + variant + '"]'
    );

    if (!logoFrame || !logoPreview) {
      return null;
    }

    var logoUploading = false;
    var logoFrameSize = 120;
    var logoCrop = {
      x: parseInt(options.initialCrop && options.initialCrop.x, 10) || 0,
      y: parseInt(options.initialCrop && options.initialCrop.y, 10) || 0,
      zoom: parseInt(options.initialCrop && options.initialCrop.zoom, 10) || 100,
    };
    var logoDrag = { active: false, startX: 0, startY: 0, originX: 0, originY: 0 };

    if (logoUploadWrap) {
      logoUploadWrap.setAttribute('data-logo-initialized', '1');
      var hintEl = logoUploadWrap.querySelector('[data-team-logo-hint]');
      if (hintEl && config.logoMaxLabel) {
        hintEl.textContent = 'JPG / PNG / WebP（' + config.logoMaxLabel + 'まで）';
      }
      maybeShowLiveLinksWarning(logoUploadWrap);
    }

    function syncLogoCropFields() {
      if (logoOffsetX) {
        logoOffsetX.value = String(Math.round(logoCrop.x));
      }
      if (logoOffsetY) {
        logoOffsetY.value = String(Math.round(logoCrop.y));
      }
      if (logoZoomHidden) {
        logoZoomHidden.value = String(Math.round(logoCrop.zoom));
      }
      if (logoZoomRange) {
        logoZoomRange.value = String(Math.round(logoCrop.zoom));
        logoZoomRange.setAttribute('aria-valuenow', String(Math.round(logoCrop.zoom)));
      }
    }

    function resetLogoCrop() {
      logoCrop.x = 0;
      logoCrop.y = 0;
      logoCrop.zoom = 100;
      syncLogoCropFields();
    }

    function clampLogoCrop() {
      if (!logoPreview || !logoPreview.naturalWidth) {
        return;
      }
      var w = logoPreview.offsetWidth;
      var h = logoPreview.offsetHeight;
      var maxX = Math.max(0, (w - logoFrameSize) / 2);
      var maxY = Math.max(0, (h - logoFrameSize) / 2);
      logoCrop.x = Math.min(maxX, Math.max(-maxX, logoCrop.x));
      logoCrop.y = Math.min(maxY, Math.max(-maxY, logoCrop.y));
      syncLogoCropFields();
    }

    function applyLogoCrop(imgEl, frameSize) {
      if (!imgEl || !imgEl.naturalWidth) {
        return;
      }
      var size = frameSize || logoFrameSize;
      var cover = Math.max(size / imgEl.naturalWidth, size / imgEl.naturalHeight);
      var scale = cover * (logoCrop.zoom / 100);
      imgEl.style.width = Math.round(imgEl.naturalWidth * scale) + 'px';
      imgEl.style.height = Math.round(imgEl.naturalHeight * scale) + 'px';
      var ratio = size / logoFrameSize;
      imgEl.style.transform =
        'translate(-50%, -50%) translate(' +
        Math.round(logoCrop.x * ratio) +
        'px, ' +
        Math.round(logoCrop.y * ratio) +
        'px)';
    }

    function refreshLogoTransform() {
      if (!logoPreview || !logoPreview.src) {
        return;
      }
      applyLogoCrop(logoPreview, logoFrameSize);
      clampLogoCrop();
      applyLogoCrop(logoPreview, logoFrameSize);
    }

    function onLogoImageReady() {
      refreshLogoTransform();
    }

    function setLogoPreview(url) {
      if (url) {
        logoPreview.onload = onLogoImageReady;
        logoPreview.src = url;
        if (logoMedia) {
          logoMedia.hidden = false;
        }
        if (logoPlaceholder) {
          logoPlaceholder.hidden = true;
        }
        if (logoTrigger) {
          logoTrigger.hidden = true;
        }
        logoFrame.classList.add('has-image');
        if (logoRemove) {
          logoRemove.hidden = false;
        }
        if (logoAdjust) {
          logoAdjust.hidden = false;
        }
        if (logoPreview.complete && logoPreview.naturalWidth) {
          onLogoImageReady();
        }
      } else {
        logoPreview.onload = null;
        logoPreview.removeAttribute('src');
        if (logoMedia) {
          logoMedia.hidden = true;
        }
        if (logoPlaceholder) {
          logoPlaceholder.hidden = false;
        }
        if (logoTrigger) {
          logoTrigger.hidden = false;
        }
        logoFrame.classList.remove('has-image');
        if (logoRemove) {
          logoRemove.hidden = true;
        }
        if (logoAdjust) {
          logoAdjust.hidden = true;
        }
        resetLogoCrop();
      }
    }

    function clearLogo() {
      if (logoHidden) {
        logoHidden.value = '';
      }
      if (logoFileInput) {
        logoFileInput.value = '';
      }
      resetLogoCrop();
      setLogoPreview('');
    }

    function sendLogoUpload(file) {
      var body = new FormData();
      body.append('action', config.uploadAction || 'team_registration_logo_upload');
      body.append(config.nonceField || 'team_metabox_nonce', config.nonce || '');
      body.append('team_logo_file', file);

      return fetch(config.ajaxUrl || '/wp-admin/admin-ajax.php', {
        method: 'POST',
        body: body,
        credentials: 'same-origin',
      })
        .then(function (response) {
          return response.text().then(function (text) {
            return parseUploadResponse(response, text);
          });
        })
        .then(function (data) {
          var payload = resolveUploadPayload(data);
          if (!payload || !payload.url) {
            throw new Error((data && data.message) || 'アップロードに失敗しました');
          }
          if (logoHidden) {
            logoHidden.value = payload.url;
          }
          resetLogoCrop();
          setLogoPreview(payload.url);
          notify(payload.message || 'ロゴをアップロードしました。', 'success');
        })
        .catch(function (err) {
          clearLogo();
          notify(err.message || 'アップロードに失敗しました', 'error');
        })
        .finally(function () {
          logoUploading = false;
          if (logoUploadWrap) {
            logoUploadWrap.classList.remove('is-uploading');
          }
        });
    }

    function uploadLogo(file) {
      if (!file || logoUploading) {
        return;
      }
      if (!isAllowedLogoFile(file)) {
        notify('JPG / PNG / WebP の画像を選択してください。', 'error');
        return;
      }
      var maxBytes = config.logoMaxBytes || 10 * 1024 * 1024;
      if (file.size > maxBytes) {
        var maxLabel = config.logoMaxLabel || Math.round(maxBytes / (1024 * 1024)) + 'MB';
        notify('ファイルサイズは' + maxLabel + '以下にしてください。', 'error');
        return;
      }

      logoUploading = true;
      if (logoUploadWrap) {
        logoUploadWrap.classList.add('is-uploading');
      }

      compressLogoForUpload(file)
        .then(function (readyFile) {
          if (readyFile.size > maxBytes) {
            throw new Error('ファイルサイズは' + (config.logoMaxLabel || '10MB') + '以下にしてください。');
          }
          return sendLogoUpload(readyFile);
        })
        .catch(function (err) {
          logoUploading = false;
          if (logoUploadWrap) {
            logoUploadWrap.classList.remove('is-uploading');
          }
          clearLogo();
          notify((err && err.message) || '画像の準備に失敗しました。', 'error');
        });
    }

    function openLogoFilePicker() {
      if (!logoUploading && logoFileInput) {
        logoFileInput.click();
      }
    }

    if (logoTrigger) {
      logoTrigger.addEventListener('click', openLogoFilePicker);
      logoTrigger.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          openLogoFilePicker();
        }
      });
    }

    if (logoChangeBtn) {
      logoChangeBtn.addEventListener('click', function (e) {
        e.preventDefault();
        openLogoFilePicker();
      });
    }

    if (logoFileInput) {
      logoFileInput.addEventListener('change', function () {
        var file = logoFileInput.files && logoFileInput.files[0];
        if (file) {
          uploadLogo(file);
        }
      });
    }

    if (logoZoomRange) {
      logoZoomRange.addEventListener('input', function () {
        logoCrop.zoom = parseInt(logoZoomRange.value, 10) || 100;
        syncLogoCropFields();
        refreshLogoTransform();
      });
    }

    if (logoMedia) {
      logoMedia.addEventListener('pointerdown', function (e) {
        if (!logoFrame.classList.contains('has-image') || logoUploading) {
          return;
        }
        logoDrag.active = true;
        logoDrag.startX = e.clientX;
        logoDrag.startY = e.clientY;
        logoDrag.originX = logoCrop.x;
        logoDrag.originY = logoCrop.y;
        logoMedia.classList.add('is-dragging');
        if (logoMedia.setPointerCapture && e.pointerId !== undefined) {
          logoMedia.setPointerCapture(e.pointerId);
        }
        e.preventDefault();
      });

      logoMedia.addEventListener('pointermove', function (e) {
        if (!logoDrag.active) {
          return;
        }
        logoCrop.x = logoDrag.originX + (e.clientX - logoDrag.startX);
        logoCrop.y = logoDrag.originY + (e.clientY - logoDrag.startY);
        clampLogoCrop();
        refreshLogoTransform();
      });

      function endLogoDrag(e) {
        if (!logoDrag.active) {
          return;
        }
        logoDrag.active = false;
        logoMedia.classList.remove('is-dragging');
        if (logoMedia.releasePointerCapture && e.pointerId !== undefined) {
          try {
            logoMedia.releasePointerCapture(e.pointerId);
          } catch (err) {
            /* noop */
          }
        }
        syncLogoCropFields();
      }

      logoMedia.addEventListener('pointerup', endLogoDrag);
      logoMedia.addEventListener('pointercancel', endLogoDrag);
    }

    if (logoRemove) {
      logoRemove.addEventListener('click', function (e) {
        e.stopPropagation();
        clearLogo();
      });
    }

    syncLogoCropFields();
    if (options.initialUrl) {
      if (logoHidden && !logoHidden.value) {
        logoHidden.value = options.initialUrl;
      }
      setLogoPreview(options.initialUrl);
    }

    return {
      getCrop: function () {
        return { x: logoCrop.x, y: logoCrop.y, zoom: logoCrop.zoom };
      },
      getUrl: function () {
        return logoHidden ? logoHidden.value.trim() : '';
      },
      applySummaryCrop: function (imgEl, frameSize) {
        applyLogoCrop(imgEl, frameSize);
      },
      refresh: refreshLogoTransform,
    };
  }

  global.AiduniteTeamLogoUpload = { init: init };
})(typeof window !== 'undefined' ? window : this);
