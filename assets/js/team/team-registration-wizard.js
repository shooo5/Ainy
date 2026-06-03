(function () {
  'use strict';

  var config = window.aiduniteTeamRegistration || {};
  var form = document.getElementById('team-registration-form');
  if (!form) {
    return;
  }

  var page = document.querySelector('.team-registration-page');
  var progressRoot = document.querySelector('[data-team-reg-progress]');
  var scopeRadios = form.querySelectorAll('input[name="registration_scope_radio"]');
  var scopeHidden = document.getElementById('registration_scope');
  var genderHidden = document.getElementById('team_gender_option');
  var baseNameInput = document.getElementById('team_name_base');
  var maleNameInput = document.getElementById('team_male_name');
  var femaleNameInput = document.getElementById('team_female_name');
  var submitBtn = document.getElementById('team-reg-submit');
  var summaryRoot = document.getElementById('team-reg-summary-root');
  var legacyLogoHidden = document.getElementById('team_logo');

  var logoGlobal =
    typeof aiduniteTeamLogoUploadConfig !== 'undefined' ? aiduniteTeamLogoUploadConfig : {};
  var logoApis = {
    male: null,
    female: null,
    single: null,
  };

  if (typeof AiduniteTeamLogoUpload !== 'undefined') {
    var logoCfg = {
      ajaxUrl: config.ajaxUrl || logoGlobal.ajaxUrl,
      nonce: config.nonce || logoGlobal.nonce,
      nonceField: logoGlobal.nonceField || 'team_metabox_nonce',
      uploadAction: logoGlobal.uploadAction || 'team_registration_logo_upload',
      logoMaxBytes: config.logoMaxBytes || logoGlobal.logoMaxBytes,
      logoMaxLabel: config.logoMaxLabel || logoGlobal.logoMaxLabel,
    };
    logoApis.male = AiduniteTeamLogoUpload.init({ variant: 'registration_male', config: logoCfg });
    logoApis.female = AiduniteTeamLogoUpload.init({ variant: 'registration_female', config: logoCfg });
  }

  var scope = '';
  var stepSequence = [1, 2, 5];
  var currentIndex = 0;

  function getScope() {
    var checked = form.querySelector('input[name="registration_scope_radio"]:checked');
    return checked ? checked.value : '';
  }

  function buildStepSequence() {
    scope = getScope();
    if (scope === 'both') {
      stepSequence = [1, 2, 3, 4, 5];
    } else if (scope === 'male') {
      stepSequence = [1, 2, 3, 5];
    } else if (scope === 'female') {
      stepSequence = [1, 2, 4, 5];
    } else {
      stepSequence = [1, 2, 5];
    }
    if (scopeHidden) {
      scopeHidden.value = scope;
    }
    if (genderHidden) {
      genderHidden.value = scope === 'both' ? '' : scope;
    }
    syncProgressChrome();
    syncAutoTeamNames();
    applyGenderTheme();
  }

  function stepForProgressSlot(slotIdx) {
    if (slotIdx === 0) {
      return 1;
    }
    if (slotIdx === 1) {
      return 2;
    }
    if (slotIdx === 2) {
      return scope === 'female' ? 4 : 3;
    }
    if (slotIdx === 3) {
      return 4;
    }
    return 5;
  }

  function syncProgressChrome() {
    if (!progressRoot) {
      return;
    }
    var extraSlot = progressRoot.querySelector('[data-progress-slot="3"]');
    var extraConnector = progressRoot.querySelector('[data-progress-connector-extra]');
    if (extraSlot) {
      extraSlot.hidden = scope !== 'both';
    }
    if (extraConnector) {
      extraConnector.hidden = scope !== 'both';
    }
    var slot2Label = progressRoot.querySelector('[data-progress-slot="2"] [data-progress-label]');
    if (slot2Label) {
      if (scope === 'female') {
        slot2Label.textContent = '女子チーム';
      } else if (scope === 'both') {
        slot2Label.textContent = '男子チーム';
      } else {
        slot2Label.textContent = 'チーム詳細';
      }
    }
    progressRoot.querySelectorAll('[data-progress-slot]').forEach(function (slot) {
      var slotIdx = parseInt(slot.getAttribute('data-progress-slot'), 10);
      if (scope === 'both') {
        slot.hidden = false;
        return;
      }
      if (scope === 'male') {
        slot.hidden = slotIdx === 3;
        return;
      }
      if (scope === 'female') {
        slot.hidden = slotIdx === 3;
        return;
      }
      slot.hidden = slotIdx === 2 || slotIdx === 3;
    });
  }

  function updateProgressUI() {
    if (!progressRoot) {
      return;
    }
    var activeStep = currentStepNumber();
    var order = [];
    progressRoot.querySelectorAll('[data-progress-slot]').forEach(function (slot) {
      if (slot.hidden) {
        return;
      }
      order.push(stepForProgressSlot(parseInt(slot.getAttribute('data-progress-slot'), 10)));
    });
    var activeOrder = order.indexOf(activeStep);
    progressRoot.querySelectorAll('[data-progress-slot]').forEach(function (slot) {
      if (slot.hidden) {
        return;
      }
      var step = stepForProgressSlot(parseInt(slot.getAttribute('data-progress-slot'), 10));
      var idx = order.indexOf(step);
      slot.classList.remove('is-active', 'is-completed');
      if (idx < activeOrder) {
        slot.classList.add('is-completed');
      } else if (idx === activeOrder) {
        slot.classList.add('is-active');
      }
    });
  }

  function teamNameWithSuffix(base, suffix) {
    var b = String(base || '').trim();
    if (b === '') {
      return '';
    }
    if (b.endsWith(suffix)) {
      return b;
    }
    return b + ' ' + suffix;
  }

  function syncAutoTeamNames() {
    var base = baseNameInput ? baseNameInput.value.trim() : '';
    if (maleNameInput && (scope === 'male' || scope === 'both')) {
      if (!maleNameInput.dataset.userEdited || maleNameInput.dataset.userEdited !== '1') {
        maleNameInput.value = teamNameWithSuffix(base, '男子');
      }
    }
    if (femaleNameInput && (scope === 'female' || scope === 'both')) {
      if (!femaleNameInput.dataset.userEdited || femaleNameInput.dataset.userEdited !== '1') {
        femaleNameInput.value = teamNameWithSuffix(base, '女子');
      }
    }
    if (legacyLogoHidden && scope === 'male' && logoApis.male) {
      legacyLogoHidden.value = logoApis.male.getUrl();
    }
    if (legacyLogoHidden && scope === 'female' && logoApis.female) {
      legacyLogoHidden.value = logoApis.female.getUrl();
    }
  }

  if (maleNameInput) {
    maleNameInput.addEventListener('input', function () {
      maleNameInput.dataset.userEdited = '1';
    });
  }
  if (femaleNameInput) {
    femaleNameInput.addEventListener('input', function () {
      femaleNameInput.dataset.userEdited = '1';
    });
  }
  if (baseNameInput) {
    baseNameInput.addEventListener('input', syncAutoTeamNames);
  }

  scopeRadios.forEach(function (radio) {
    radio.addEventListener('change', function () {
      buildStepSequence();
    });
  });

  function applyGenderTheme() {
    if (!page) {
      return;
    }
    if (scope === 'male' || scope === 'female') {
      page.setAttribute('data-gender-theme', scope);
    } else {
      page.removeAttribute('data-gender-theme');
    }
  }

  function currentStepNumber() {
    return stepSequence[currentIndex] || 1;
  }

  function showStepByIndex(index) {
    currentIndex = Math.max(0, Math.min(index, stepSequence.length - 1));
    var stepNumber = currentStepNumber();

    form.querySelectorAll('.team-reg-step').forEach(function (step) {
      var num = parseInt(step.getAttribute('data-step'), 10);
      var active = num === stepNumber;
      step.classList.toggle('is-active', active);
      step.hidden = !active;
    });

    updateProgressUI();

    if (stepNumber === 5) {
      renderSummary();
    }

    var activeStep = form.querySelector('.team-reg-step.is-active');
    if (activeStep) {
      var first = activeStep.querySelector(
        'input:not([type="hidden"]):not([hidden]), select, textarea, button[data-action]'
      );
      if (first && typeof first.focus === 'function') {
        first.focus({ preventScroll: true });
      }
    }
  }

  function getActivityRegionLabel() {
    var pref = document.getElementById('activity_prefecture');
    var type = document.getElementById('activity_area_type');
    var parts = [];
    if (pref && pref.value) {
      parts.push(pref.value);
    }
    if (type && type.value === 'tokyo_ward') {
      var ward = document.getElementById('activity_area_ward');
      if (ward && ward.value) {
        parts.push(ward.value);
      }
    } else if (type && type.value === 'tokyo_city') {
      var city = document.getElementById('activity_area_city');
      if (city && city.value) {
        parts.push(city.value);
      }
    }
    return parts.length ? parts.join(' ') : '—';
  }

  function fieldText(id, fallback) {
    var el = document.getElementById(id);
    if (!el || !String(el.value).trim()) {
      return fallback || '—';
    }
    if (el.tagName === 'SELECT' && el.selectedIndex > 0) {
      return el.options[el.selectedIndex].text;
    }
    return el.value.trim();
  }

  function summaryBlock(title, rows, logoApi) {
    var html = '<section class="team-reg-summary-block"><h3 class="team-reg-summary-block__title">' + title + '</h3><dl class="team-reg-summary">';
    rows.forEach(function (row) {
      html +=
        '<div class="team-reg-summary__row"><dt>' +
        row.label +
        '</dt><dd>' +
        (row.value || '—') +
        '</dd></div>';
    });
    html += '</dl>';
    if (logoApi && logoApi.getUrl()) {
      html += '<p class="team-reg-summary-block__logo-note">ロゴ：設定済み</p>';
    }
    html += '</section>';
    return html;
  }

  function renderSummary() {
    if (!summaryRoot) {
      return;
    }
    var commonRows = [
      { label: 'スポーツ', value: fieldText('sport_type') },
      { label: '年代', value: fieldText('team_category') },
      { label: '所属', value: fieldText('team_type') },
      { label: '都道府県', value: getActivityRegionLabel() },
      { label: '公式サイト', value: fieldText('team_website') },
      { label: 'サイト', value: fieldText('team_sns_url') },
    ];
    var html = summaryBlock('共通', [
      { label: 'ベース名', value: fieldText('team_name_base') },
    ].concat(commonRows));

    if (scope === 'male' || scope === 'both') {
      html += summaryBlock(
        '男子チーム',
        [
          { label: 'チーム名', value: fieldText('team_male_name') },
          { label: '紹介文', value: fieldText('team_male_description') },
        ],
        logoApis.male
      );
    }
    if (scope === 'female' || scope === 'both') {
      html += summaryBlock(
        '女子チーム',
        [
          { label: 'チーム名', value: fieldText('team_female_name') },
          { label: '紹介文', value: fieldText('team_female_description') },
        ],
        logoApis.female
      );
    }
    summaryRoot.innerHTML = html;
  }

  function clearErrors(stepEl) {
    stepEl.querySelectorAll('.team-reg-field.is-error').forEach(function (field) {
      field.classList.remove('is-error');
    });
    stepEl.querySelectorAll('.team-reg-scope').forEach(function (field) {
      field.classList.remove('is-error');
    });
  }

  function validateStep(stepNumber) {
    var stepEl = form.querySelector('.team-reg-step[data-step="' + stepNumber + '"]');
    if (!stepEl) {
      return true;
    }
    clearErrors(stepEl);
    var valid = true;

    if (stepNumber === 1) {
      if (!getScope()) {
        valid = false;
        var scopeField = stepEl.querySelector('[data-team-reg-scope]');
        if (scopeField) {
          scopeField.classList.add('is-error');
        }
      }
    }

    var required = stepEl.querySelectorAll('[required]');
    required.forEach(function (input) {
      if (input.offsetParent === null && input.type !== 'hidden') {
        return;
      }
      if (!String(input.value).trim()) {
        valid = false;
        var field = input.closest('.team-reg-field') || input.closest('.team-reg-scope') || input.parentElement;
        if (field) {
          field.classList.add('is-error');
        }
        input.setAttribute('aria-invalid', 'true');
      } else {
        input.removeAttribute('aria-invalid');
      }
    });

    if (stepNumber === 3 && maleNameInput && !String(maleNameInput.value).trim()) {
      valid = false;
      maleNameInput.closest('.team-reg-field').classList.add('is-error');
    }
    if (stepNumber === 4 && femaleNameInput && !String(femaleNameInput.value).trim()) {
      valid = false;
      femaleNameInput.closest('.team-reg-field').classList.add('is-error');
    }

    if (!valid) {
      var firstInvalid = stepEl.querySelector('[aria-invalid="true"], .is-error input, .is-error select');
      if (firstInvalid && typeof firstInvalid.focus === 'function') {
        firstInvalid.focus();
      }
    }
    return valid;
  }

  function notify(message, type) {
    if (typeof showToastNotification === 'function') {
      showToastNotification(message, type);
    } else if (type === 'error') {
      window.alert(message);
    }
  }

  form.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-action]');
    if (!btn) {
      return;
    }
    var action = btn.getAttribute('data-action');
    if (action === 'next') {
      buildStepSequence();
      if (!validateStep(currentStepNumber())) {
        return;
      }
      if (currentIndex === 0 && !getScope()) {
        notify('申請するチームを選択してください。', 'error');
        return;
      }
      showStepByIndex(currentIndex + 1);
    } else if (action === 'prev') {
      showStepByIndex(currentIndex - 1);
    }
  });

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    buildStepSequence();
    syncAutoTeamNames();
    if (!validateStep(currentStepNumber())) {
      return;
    }

    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.textContent = '送信中…';
    }

    var formData = new FormData(form);
    var postData = new URLSearchParams();
    postData.append('action', 'team_registration');
    postData.append('team_metabox_nonce', config.nonce || '');
    postData.append('registration_scope', scope || getScope());

    formData.forEach(function (value, key) {
      if (key === 'registration_scope_radio') {
        return;
      }
      postData.append(key, value);
    });

    if (scope === 'male' && logoApis.male) {
      postData.set('team_logo', logoApis.male.getUrl());
    }
    if (scope === 'female' && logoApis.female) {
      postData.set('team_logo', logoApis.female.getUrl());
    }

    fetch(config.ajaxUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: postData,
      credentials: 'same-origin',
    })
      .then(function (response) {
        return response.json();
      })
      .then(function (data) {
        if (data && data.success) {
          var redirectUrl =
            (data && data.redirect_url) ||
            (data && data.data && data.data.redirect_url) ||
            config.completeUrl ||
            config.mypageUrl;
          if (redirectUrl) {
            window.location.href = redirectUrl;
            return;
          }
          notify(data.message || '申請を受け付けました。', 'success');
          return;
        }
        throw new Error((data && data.message) || '送信に失敗しました');
      })
      .catch(function (err) {
        notify(err.message || '送信に失敗しました', 'error');
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.textContent = 'チーム登録を申請する';
        }
      });
  });

  buildStepSequence();
  showStepByIndex(0);
})();
