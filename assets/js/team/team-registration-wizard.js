(function () {
  'use strict';

  var config = window.aiduniteTeamRegistration || {};
  var chipIcons = config.chipIcons && typeof config.chipIcons === 'object' ? config.chipIcons : {};
  var form = document.getElementById('team-registration-form');
  if (!form) {
    return;
  }

  var page = document.querySelector('.team-registration-page');
  var progressRoot = document.querySelector('[data-team-reg-progress]');
  var scopeRadios = form.querySelectorAll('input[name="registration_scope_radio"]');
  var scopeHidden = document.getElementById('registration_scope');
  var genderHidden = document.getElementById('team_gender_option');
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
    syncCommonHeadVisibility();
    syncRegistrationLegacyFields();
    applyGenderTheme();
  }

  function syncCommonHeadVisibility() {
    var isBoth = scope === 'both';
    var commonHead = document.querySelector('[data-team-reg-common-head]');
    if (commonHead) {
      commonHead.hidden = !isBoth;
    }
    var nameHint = document.querySelector('[data-team-name-hint]');
    if (nameHint) {
      nameHint.hidden = !isBoth;
    }
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

  function syncRegistrationLegacyFields() {
    if (legacyLogoHidden && scope === 'male' && logoApis.male) {
      legacyLogoHidden.value = logoApis.male.getUrl();
    }
    if (legacyLogoHidden && scope === 'female' && logoApis.female) {
      legacyLogoHidden.value = logoApis.female.getUrl();
    }
  }

  function syncDualRegistrationLogoFields(postData) {
    if (scope !== 'both') {
      return;
    }
    if (logoApis.male) {
      postData.set('team_male_logo', logoApis.male.getUrl());
    }
    if (logoApis.female) {
      postData.set('team_female_logo', logoApis.female.getUrl());
    }
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

  function escapeHtml(text) {
    var div = document.createElement('div');
    div.textContent = text == null ? '' : String(text);
    return div.innerHTML;
  }

  function formatMultilineHtml(text) {
    return escapeHtml(text).replace(/\r\n|\r|\n/g, '<br>');
  }

  function chipValue(value) {
    var v = value == null ? '' : String(value).trim();
    return v === '' || v === '—' ? '' : v;
  }

  function chipIcon(key) {
    return chipIcons[key] || '';
  }

  function renderInfoChip(icon, label, value, solo) {
    var v = chipValue(value);
    if (!v) {
      return '';
    }
    var cls = 'team-info-chip' + (solo ? ' team-info-chip--solo' : '');
    return (
      '<article class="' +
      cls +
      '">' +
      '<span class="team-info-chip__icon" aria-hidden="true">' +
      icon +
      '</span>' +
      '<span class="team-info-chip__label">' +
      escapeHtml(label) +
      '</span>' +
      '<span class="team-info-chip__value">' +
      escapeHtml(v) +
      '</span>' +
      '</article>'
    );
  }

  function renderIntroChip(icon, label, text) {
    var v = chipValue(text);
    if (!v) {
      return '';
    }
    return (
      '<article class="team-info-chip team-info-chip--solo team-info-chip--intro">' +
      '<span class="team-info-chip__icon" aria-hidden="true">' +
      icon +
      '</span>' +
      '<span class="team-info-chip__label">' +
      escapeHtml(label) +
      '</span>' +
      '<div class="team-info-chip__prose">' +
      formatMultilineHtml(v) +
      '</div>' +
      '</article>'
    );
  }

  function renderConfirmHero(teamName, sport, logoApi, genderLabel) {
    var logoUrl = logoApi && logoApi.getUrl ? logoApi.getUrl() : '';
    var logoHtml = logoUrl
      ? '<img class="team-public-profile__logo" src="' +
        escapeHtml(logoUrl) +
        '" alt="" width="120" height="120" />'
      : '<div class="team-public-profile__logo team-public-profile__logo--placeholder" aria-hidden="true"></div>';
    var sportLine = chipValue(sport);
    if (genderLabel) {
      sportLine = genderLabel + (sportLine ? ' · ' + sportLine : '');
    }
    return (
      '<header class="team-public-profile__hero team-reg-confirm-hero">' +
      logoHtml +
      '<div class="team-public-profile__hero-text">' +
      '<h3 class="team-public-profile__title">' +
      escapeHtml(chipValue(teamName) || '—') +
      '</h3>' +
      (sportLine
        ? '<p class="team-public-profile__sport">' + escapeHtml(sportLine) + '</p>'
        : '') +
      '</div>' +
      '</header>'
    );
  }

  function renderBasicChips() {
    var chips = '';
    if (scope === 'both') {
      chips += renderInfoChip(chipIcon('base_name'), 'ベース名', fieldText('team_name_base'));
    }
    chips += renderInfoChip(chipIcon('category'), '年代', fieldText('team_category'));
    chips += renderInfoChip(chipIcon('type'), '所属', fieldText('team_type'));
    chips += renderInfoChip(chipIcon('location'), '都道府県', getActivityRegionLabel());
    chips += renderInfoChip(chipIcon('website'), '公式サイト', fieldText('team_website'), true);
    chips += renderInfoChip(chipIcon('sns'), 'サイト', fieldText('team_sns_url'), true);
    if (!chips) {
      return '';
    }
    return (
      '<section class="team-public-profile__section team-public-profile__section--cards team-reg-confirm-section" aria-label="基本情報">' +
      '<h3 class="team-public-profile__section-heading">基本情報</h3>' +
      '<div class="team-info-chips">' +
      chips +
      '</div>' +
      '</section>'
    );
  }

  function renderSummary() {
    if (!summaryRoot) {
      return;
    }
    var sport = fieldText('sport_type');
    var html = '<div class="team-reg-confirm-profile">';

    if (scope === 'male' || scope === 'both') {
      html += renderConfirmHero(
        fieldText('team_male_name'),
        sport,
        logoApis.male,
        scope === 'both' ? '男子' : ''
      );
    }
    if (scope === 'female' || scope === 'both') {
      html += renderConfirmHero(
        fieldText('team_female_name'),
        sport,
        logoApis.female,
        scope === 'both' ? '女子' : ''
      );
    }

    html += renderBasicChips();

    var introHtml = '';
    if (scope === 'male' || scope === 'both') {
      introHtml += renderIntroChip(
        chipIcon('intro'),
        scope === 'both' ? '男子チームの紹介' : '紹介',
        fieldText('team_male_description')
      );
    }
    if (scope === 'female' || scope === 'both') {
      introHtml += renderIntroChip(
        chipIcon('intro'),
        scope === 'both' ? '女子チームの紹介' : '紹介',
        fieldText('team_female_description')
      );
    }
    if (introHtml) {
      html +=
        '<div class="team-info-chips team-info-chips--intro team-reg-confirm-intro">' +
        introHtml +
        '</div>';
    }

    html += '</div>';
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
    aiduniteToast(message, type || 'info');
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
        notify('申請するチームの性別を選択してください。', 'error');
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
    syncRegistrationLegacyFields();
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
    syncDualRegistrationLogoFields(postData);

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
          submitBtn.textContent = 'チームを申請する';
        }
      });
  });

  buildStepSequence();
  showStepByIndex(0);
})();
