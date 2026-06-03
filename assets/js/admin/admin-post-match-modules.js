/**
 * 試合後モジュール管理：フロー全体プレビュー（①〜④）・一覧モーダル
 */
(function () {
  'use strict';

  var cfg = window.aiduniteAdminPmmConfig || {};
  var debounceTimer = null;
  var previewRequestSeq = 0;
  var previewAbortController = null;
  var listPreviewAbortController = null;

  function restHeaders() {
    return {
      'Content-Type': 'application/json',
      'X-WP-Nonce': cfg.nonce || '',
    };
  }

  function collectFormData(form) {
    if (!form) {
      return {};
    }
    var activeCheckbox = form.querySelector('[name="is_active"]');
    var noPeriod = form.querySelector('[name="no_period"]');
    var useNoPeriod = noPeriod ? noPeriod.checked : false;
    return {
      module_id: form.querySelector('[name="module_id"]')?.value || '0',
      title: form.querySelector('[name="title"]')?.value || '',
      module_type: form.querySelector('[name="module_type"]')?.value || 'notice',
      slot_key: form.querySelector('[name="slot_key"]')?.value || 'after_match_feedback',
      is_active: activeCheckbox ? activeCheckbox.checked : true,
      priority: form.querySelector('[name="priority"]')?.value || '10',
      no_period: useNoPeriod,
      start_at: useNoPeriod ? '' : form.querySelector('[name="start_at"]')?.value || '',
      end_at: useNoPeriod ? '' : form.querySelector('[name="end_at"]')?.value || '',
      description: form.querySelector('[name="description"]')?.value || '',
      banner_image_id: form.querySelector('[name="banner_image_id"]')?.value || '0',
      banner_external_url: form.querySelector('[name="banner_external_url"]')?.value || '',
      cta_label: form.querySelector('[name="cta_label"]')?.value || '',
      cta_url: form.querySelector('[name="cta_url"]')?.value || '',
      target_sport: form.querySelector('[name="target_sport"]')?.value || '',
      target_region: form.querySelector('[name="target_region"]')?.value || '',
    };
  }

  function syncPeriodFieldsDisabled(form) {
    if (!form) {
      return;
    }
    var noPeriod = form.querySelector('[name="no_period"]');
    var row = form.querySelector('#pmm_period_fields');
    if (!noPeriod || !row) {
      return;
    }
    var disabled = noPeriod.checked;
    row.querySelectorAll('input').forEach(function (input) {
      input.disabled = disabled;
    });
    row.classList.toggle('is-disabled', disabled);
  }

  function parsePreviewResponse(res) {
    return res.text().then(function (text) {
      var json = null;
      try {
        json = text ? JSON.parse(text) : null;
      } catch (e) {
        throw new Error('HTTP ' + res.status + '（サーバー応答が JSON ではありません）');
      }
      if (!res.ok) {
        var msg =
          (json && json.message) ||
          (json && json.data && json.data.message) ||
          'HTTP ' + res.status;
        throw new Error(String(msg));
      }
      if (!json || !json.success || !json.data || typeof json.data.html !== 'string') {
        throw new Error('プレビューデータの形式が不正です');
      }
      return json.data.html;
    });
  }

  function fetchPreviewHtml(url, options) {
    var opts = Object.assign(
      {
        credentials: 'same-origin',
      },
      options || {}
    );
    return fetch(url, opts).then(parsePreviewResponse);
  }

  function setPreviewStatus(message, isError) {
    var el = document.getElementById('admin-pmm-preview-status');
    var retryBtn = document.querySelector('.admin-pmm-preview-retry-btn');
    if (!el) {
      return;
    }
    el.textContent = message || '';
    el.classList.toggle('is-error', !!isError);
    el.hidden = !message;
    if (retryBtn) {
      retryBtn.hidden = !isError;
    }
  }

  function getToggleState(root) {
    if (!root) {
      return null;
    }
    var state = { sections: {}, modules: {} };
    root.querySelectorAll('[data-preview-section-toggle]').forEach(function (el) {
      var key = el.getAttribute('data-preview-section-toggle');
      if (key) {
        state.sections[key] = el.checked;
      }
    });
    root.querySelectorAll('[data-module-card-toggle]').forEach(function (el) {
      var id = el.getAttribute('data-module-card-toggle');
      if (id === 'editing-current') {
        return;
      }
      if (id !== null && id !== '') {
        state.modules[id] = el.checked;
      }
    });
    return state;
  }

  function applySectionVisibility(container, sectionNum, visible) {
    var block = container.querySelector('[data-flow-section="' + sectionNum + '"]');
    if (block) {
      block.classList.toggle('is-hidden', !visible);
    }
  }

  function applyModuleVisibility(container, moduleId, visible) {
    var wrap = container.querySelector('[data-module-wrap-id="' + moduleId + '"]');
    if (wrap) {
      wrap.classList.toggle('is-hidden', !visible);
    }
  }

  function applyToggleState(root, state) {
    if (!root || !state) {
      return;
    }
    root.querySelectorAll('[data-preview-section-toggle]').forEach(function (el) {
      var key = el.getAttribute('data-preview-section-toggle');
      if (key && Object.prototype.hasOwnProperty.call(state.sections, key)) {
        el.checked = state.sections[key];
        applySectionVisibility(root, key, state.sections[key]);
      }
    });
    root.querySelectorAll('[data-module-card-toggle]').forEach(function (el) {
      var id = el.getAttribute('data-module-card-toggle');
      if (id === 'editing-current') {
        el.checked = true;
        applyModuleVisibility(root, id, true);
        return;
      }
      if (id !== null && id !== '' && Object.prototype.hasOwnProperty.call(state.modules, id)) {
        el.checked = state.modules[id];
        applyModuleVisibility(root, id, state.modules[id]);
      }
    });
    ensureEditingModuleWrapVisible(root);
  }

  function ensureEditingModuleWrapVisible(root) {
    if (!root) {
      return;
    }
    root.querySelectorAll('[data-module-wrap-editing="1"]').forEach(function (wrap) {
      wrap.classList.remove('is-hidden');
    });
  }

  function bindPreviewInteractions(root) {
    if (!root) {
      return;
    }
    root.querySelectorAll('[data-preview-section-toggle]').forEach(function (input) {
      input.addEventListener('change', function () {
        var section = input.getAttribute('data-preview-section-toggle');
        applySectionVisibility(root, section, input.checked);
      });
    });
    root.querySelectorAll('[data-module-card-toggle]').forEach(function (input) {
      input.addEventListener('change', function () {
        var id = input.getAttribute('data-module-card-toggle');
        applyModuleVisibility(root, id, input.checked);
      });
    });
  }

  function setPreviewRoot(html) {
    var root = document.getElementById('admin-pmm-preview-root');
    if (!root) {
      return;
    }
    var prevState = getToggleState(root);
    root.innerHTML = html;
    applyToggleState(root, prevState);
    bindPreviewInteractions(root);
    if (window.aiduniteInitMatchFeedbackStarRatings) {
      window.aiduniteInitMatchFeedbackStarRatings(root);
    }
  }

  function refreshLivePreview() {
    var form = document.getElementById('admin-pmm-form');
    var root = document.getElementById('admin-pmm-preview-root');
    if (!form || !root || !cfg.restBase) {
      return;
    }

    if (previewAbortController) {
      previewAbortController.abort();
    }
    previewAbortController = new AbortController();
    var reqId = ++previewRequestSeq;

    setPreviewStatus('プレビューを更新しています…', false);

    var url = cfg.restBase.replace(/\/$/, '') + '/post-match-modules/preview';
    fetchPreviewHtml(url, {
      method: 'POST',
      headers: restHeaders(),
      body: JSON.stringify(collectFormData(form)),
      signal: previewAbortController.signal,
    })
      .then(function (html) {
        if (reqId !== previewRequestSeq) {
          return;
        }
        setPreviewStatus('', false);
        setPreviewRoot(html);
        syncBannerPreviewFromFields();
      })
      .catch(function (err) {
        if (reqId !== previewRequestSeq) {
          return;
        }
        if (err && err.name === 'AbortError') {
          return;
        }
        var msg = err && err.message ? err.message : '通信エラー';
        setPreviewStatus('プレビューの更新に失敗しました（' + msg + '）。表示は前回の内容のままです。', true);
      });
  }

  function debouncedRefresh() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(refreshLivePreview, 750);
  }

  function setBannerPreview(url, attachmentId) {
    var img = document.getElementById('admin-pmm-banner-preview-img');
    var empty = document.getElementById('admin-pmm-banner-preview-empty');
    var hidden = document.getElementById('banner_image_id');
    var removeBtn = document.getElementById('admin-pmm-banner-remove');
    var id = parseInt(attachmentId, 10) || 0;
    var hasImage = id > 0 && url;

    if (hidden) {
      hidden.value = String(id);
      hidden.dispatchEvent(new Event('input', { bubbles: true }));
      hidden.dispatchEvent(new Event('change', { bubbles: true }));
    }
    if (img) {
      if (hasImage) {
        img.src = url;
        img.hidden = false;
      } else {
        img.removeAttribute('src');
        img.hidden = true;
      }
    }
    if (empty) {
      empty.hidden = !!hasImage;
    }
    if (removeBtn) {
      removeBtn.disabled = !hasImage;
    }
  }

  function syncBannerPreviewFromFields() {
    var hidden = document.getElementById('banner_image_id');
    var external = document.getElementById('banner_external_url');
    var id = hidden ? parseInt(hidden.value, 10) || 0 : 0;
    if (id > 0 && cfg.mediaRestBase && cfg.nonce) {
      var mediaUrl = cfg.mediaRestBase.replace(/\/$/, '') + '/' + id;
      fetch(mediaUrl, {
        credentials: 'same-origin',
        headers: { 'X-WP-Nonce': cfg.nonce },
      })
        .then(function (res) {
          return res.ok ? res.json() : null;
        })
        .then(function (data) {
          if (!data) {
            return;
          }
          var url =
            (data.media_details &&
              data.media_details.sizes &&
              data.media_details.sizes.medium &&
              data.media_details.sizes.medium.source_url) ||
            data.source_url ||
            '';
          if (url) {
            setBannerPreview(url, id);
          }
        })
        .catch(function () {
          /* 左プレビューは選択直後のURLを優先 */
        });
      return;
    }
    var extUrl = external ? String(external.value || '').trim() : '';
    if (extUrl.indexOf('https://') === 0 || extUrl.indexOf('http://') === 0) {
      setBannerPreview(extUrl, 0);
    } else if (!extUrl) {
      setBannerPreview('', 0);
    }
  }

  function bindBannerMediaPicker() {
    var selectBtn = document.getElementById('admin-pmm-banner-select');
    var removeBtn = document.getElementById('admin-pmm-banner-remove');
    var externalInput = document.getElementById('banner_external_url');
    if (!selectBtn) {
      return;
    }

    var mediaFrame = null;

    selectBtn.addEventListener('click', function (e) {
      e.preventDefault();
      if (typeof wp === 'undefined' || !wp.media) {
        window.alert(cfg.mediaUnavailable || '画像選択を利用できません。');
        return;
      }

      if (mediaFrame) {
        mediaFrame.open();
        return;
      }

      mediaFrame = wp.media({
        title: cfg.mediaFrameTitle || 'バナー画像を選択',
        button: { text: cfg.mediaFrameButton || 'この画像を使う' },
        library: { type: 'image' },
        multiple: false,
      });

      mediaFrame.on('select', function () {
        var attachment = mediaFrame.state().get('selection').first().toJSON();
        var url =
          (attachment.sizes && attachment.sizes.medium && attachment.sizes.medium.url) ||
          attachment.url ||
          '';
        setBannerPreview(url, attachment.id);
      });

      mediaFrame.open();
    });

    if (removeBtn) {
      removeBtn.addEventListener('click', function (e) {
        e.preventDefault();
        setBannerPreview('', 0);
        syncBannerPreviewFromFields();
      });
    }

    if (externalInput) {
      externalInput.addEventListener('input', syncBannerPreviewFromFields);
      externalInput.addEventListener('change', syncBannerPreviewFromFields);
    }
  }

  function bindLivePreview() {
    var form = document.getElementById('admin-pmm-form');
    if (!form) {
      return;
    }
    syncPeriodFieldsDisabled(form);
    var noPeriod = form.querySelector('[name="no_period"]');
    if (noPeriod) {
      noPeriod.addEventListener('change', function () {
        syncPeriodFieldsDisabled(form);
        debouncedRefresh();
      });
    }
    form.addEventListener('input', debouncedRefresh);
    form.addEventListener('change', debouncedRefresh);
    var root = document.getElementById('admin-pmm-preview-root');
    if (root) {
      bindPreviewInteractions(root);
    }
  }

  function openModal() {
    var modal = document.getElementById('admin-pmm-modal');
    if (!modal) {
      return;
    }
    modal.hidden = false;
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('admin-pmm-modal-open');
  }

  function closeModal() {
    var modal = document.getElementById('admin-pmm-modal');
    if (!modal) {
      return;
    }
    modal.hidden = true;
    modal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('admin-pmm-modal-open');
  }

  function openListPreview(moduleId) {
    var body = document.getElementById('admin-pmm-modal-body');
    if (!body || !cfg.restBase) {
      return;
    }
    if (listPreviewAbortController) {
      listPreviewAbortController.abort();
    }
    listPreviewAbortController = new AbortController();

    body.innerHTML = '<p class="admin-pmm-preview-loading">読み込み中…</p>';
    openModal();
    var url = cfg.restBase.replace(/\/$/, '') + '/post-match-modules/' + moduleId + '/preview';
    fetchPreviewHtml(url, {
      method: 'GET',
      headers: restHeaders(),
      signal: listPreviewAbortController.signal,
    })
      .then(function (html) {
        body.innerHTML = html;
        bindPreviewInteractions(body);
      })
      .catch(function (err) {
        if (err && err.name === 'AbortError') {
          return;
        }
        var msg = err && err.message ? err.message : '通信エラー';
        body.innerHTML =
          '<p class="admin-pmm-preview-error">プレビューを読み込めませんでした（' +
          msg +
          '）。ページを再読み込みするか、しばらく待ってから再度お試しください。</p>';
      });
  }

  function bindListPreview() {
    document.querySelectorAll('.admin-pmm-preview-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var id = parseInt(btn.getAttribute('data-module-id'), 10);
        if (id > 0) {
          openListPreview(id);
        }
      });
    });

    document.querySelectorAll('.admin-pmm-preview-retry-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        refreshLivePreview();
      });
    });

    document.querySelectorAll('[data-admin-pmm-modal-close]').forEach(function (el) {
      el.addEventListener('click', closeModal);
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') {
        closeModal();
      }
    });
  }

  function init() {
    bindBannerMediaPicker();
    bindLivePreview();
    bindListPreview();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
