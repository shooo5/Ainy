/**
 * page-admin-competition-event.php — 運営向け大会作成・編集
 */
(function () {
  'use strict';

  var cfg = typeof aidunitePage_admin_competition_event !== 'undefined'
    ? aidunitePage_admin_competition_event
    : {};

  function restBase() {
    return (cfg.restUrl || '/wp-json/aidunite/v1/').replace(/\/?$/, '/');
  }

  function restHeaders() {
    return {
      'Content-Type': 'application/json',
      'X-WP-Nonce': cfg.nonce || (typeof wpApiSettings !== 'undefined' ? wpApiSettings.nonce : '')
    };
  }

  function parseJsonResponse(res) {
    return res.json().then(function (data) {
      if (!res.ok) {
        var message = (data && data.message) ? data.message : 'リクエストに失敗しました';
        if (data && data.data && data.data.status === 403) {
          message = '権限がありません';
        }
        if (data && data.code) {
          message = data.message || data.code;
        }
        throw new Error(message);
      }
      return data;
    });
  }

  function roundEntryFeeAmount(value) {
    var amount = parseInt(value, 10);
    if (isNaN(amount) || amount < 0) {
      return 0;
    }
    return Math.round(amount / 500) * 500;
  }

  function normalizePostalPayload(value) {
    if (typeof aiduniteNormalizePostalDigits === 'function') {
      return aiduniteNormalizePostalDigits(value);
    }
    return String(value || '').replace(/\D/g, '').slice(0, 7);
  }

  function collectPayload(form) {
    var feeRequired = form.querySelector('#competition-entry-fee-required');
    var capacityRaw = form.querySelector('[name="capacity_teams"]').value;
    var feeRaw = form.querySelector('[name="entry_fee_amount"]').value;
    var postalInput = form.querySelector('[name="venue_postal_code"]');

    return {
      title: form.querySelector('[name="title"]').value.trim(),
      event_kind: form.querySelector('[name="event_kind"]').value,
      approval_mode: form.querySelector('[name="approval_mode"]').value,
      date_start: form.querySelector('[name="date_start"]').value,
      date_end: form.querySelector('[name="date_end"]').value || form.querySelector('[name="date_start"]').value,
      venue_name: form.querySelector('[name="venue_name"]').value.trim(),
      venue_postal_code: postalInput ? normalizePostalPayload(postalInput.value) : '',
      venue_address: form.querySelector('[name="venue_address"]').value.trim(),
      application_deadline: form.querySelector('[name="application_deadline"]').value,
      capacity_teams: capacityRaw === '' ? 0 : parseInt(capacityRaw, 10),
      entry_fee_amount: feeRaw === '' ? 0 : roundEntryFeeAmount(feeRaw),
      entry_fee_required: !!(feeRequired && feeRequired.checked),
      entry_fee_currency: 'JPY',
      visibility: 'admin_only',
      status: 'draft',
      eligibility: {
        age_group: form.querySelector('[name="eligibility_age_group"]').value,
        gender: form.querySelector('[name="eligibility_gender"]').value,
        level: form.querySelector('[name="eligibility_level"]').value,
        notes: form.querySelector('[name="eligibility_notes"]').value.trim()
      }
    };
  }

  function saveEvent(form, submitBtn) {
    var payload = collectPayload(form);
    if (!payload.title) {
      aiduniteToast('大会名を入力してください。', 'warning');
      return Promise.reject(new Error('validation'));
    }
    if (!payload.date_start) {
      aiduniteToast('開催日を入力してください。', 'warning');
      return Promise.reject(new Error('validation'));
    }

    var eventId = parseInt(cfg.eventId, 10) || 0;
    var isNew = !!cfg.isNew || eventId < 1;
    var url = restBase() + 'competition/events';
    var method = 'POST';

    if (!isNew) {
      url += '/' + eventId;
      method = 'PATCH';
      delete payload.status;
    }

    return aiduniteWithButtonLoading(submitBtn, function () {
      return fetch(url, {
        method: method,
        headers: restHeaders(),
        body: JSON.stringify(payload)
      })
        .then(parseJsonResponse)
        .then(function (body) {
          var data = body.data || body;
          var savedId = data.event_id || (data.payload && data.payload.id) || eventId;
          aiduniteToast(isNew ? '下書きを保存しました。' : '保存しました。', 'success');
          if (isNew && savedId) {
            var base = (cfg.editUrlBase || '/admin-competition-event').replace(/\/$/, '');
            window.location.href = base + '?event_id=' + savedId;
          }
        });
    }, '保存中...');
  }

  function publishEvent(publishBtn) {
    var eventId = parseInt(cfg.eventId, 10) || 0;
    if (eventId < 1) {
      aiduniteToast('先に下書きを保存してください。', 'warning');
      return;
    }

    aiduniteConfirm({
      message: '募集を開始します。チームを招待できる状態になります。よろしいですか？',
      confirmLabel: '募集を開始',
      onConfirm: function () {
        aiduniteWithButtonLoading(publishBtn, function () {
          return fetch(restBase() + 'competition/events/' + eventId + '/publish', {
            method: 'POST',
            headers: restHeaders(),
            body: JSON.stringify({})
          })
            .then(parseJsonResponse)
            .then(function () {
              aiduniteToast('募集を開始しました。', 'success');
              window.location.reload();
            })
            .catch(function (err) {
              if (err.message !== 'validation') {
                aiduniteToast(err.message || '公開に失敗しました。', 'error');
              }
            });
        }, '公開中...');
      }
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('admin-competition-event-form');
    if (!form) {
      return;
    }

    var saveBtn = document.getElementById('competition-save-btn');
    var publishBtn = document.getElementById('competition-publish-btn');

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      saveEvent(form, saveBtn).catch(function (err) {
        if (err.message !== 'validation') {
          aiduniteToast(err.message || '保存に失敗しました。', 'error');
        }
      });
    });

    if (publishBtn) {
      publishBtn.addEventListener('click', function () {
        var saveFirst = saveEvent(form, saveBtn);
        if (saveFirst && typeof saveFirst.then === 'function') {
          saveFirst.then(function () {
            publishEvent(publishBtn);
          }).catch(function (err) {
            console.debug('admin-competition-event: save before publish failed', err);
          });
        } else {
          publishEvent(publishBtn);
        }
      });
    }

    initEntriesPanel();
    initFixturesPanel();
    initVenuePostalLookup();
    initEntryFeeRounding();
  });

  function initVenuePostalLookup() {
    if (typeof aiduniteBindPostalCodeLookup !== 'function') {
      return;
    }
    aiduniteBindPostalCodeLookup({
      postalInputId: 'competition-venue-postal',
      addressInputId: 'competition-venue-address',
      searchButtonId: 'competition-venue-postal-search',
      onSuccess: function () {
        if (typeof aiduniteToast === 'function') {
          aiduniteToast('住所を入力しました。番地・建物名を追記してください。', 'success');
        }
      }
    });
  }

  function initEntryFeeRounding() {
    var feeInput = document.getElementById('competition-entry-fee');
    if (!feeInput) {
      return;
    }
    feeInput.addEventListener('blur', function () {
      if (feeInput.value === '') {
        return;
      }
      feeInput.value = String(roundEntryFeeAmount(feeInput.value));
    });
  }

  function eventId() {
    return parseInt(cfg.eventId, 10) || 0;
  }

  function entryBadgeClass(status) {
    var map = {
      invited: 'admin-competition-entry--invited',
      applied: 'admin-competition-entry--applied',
      confirmed: 'admin-competition-entry--confirmed',
      declined: 'admin-competition-entry--declined',
      withdrawn: 'admin-competition-entry--withdrawn',
      waitlisted: 'admin-competition-entry--waitlisted'
    };
    return map[status] || 'admin-competition-entry--invited';
  }

  function formatDateTime(value) {
    if (!value) {
      return '—';
    }
    var d = new Date(value.replace(' ', 'T'));
    if (isNaN(d.getTime())) {
      return value;
    }
    var y = d.getFullYear();
    var m = String(d.getMonth() + 1).padStart(2, '0');
    var day = String(d.getDate()).padStart(2, '0');
    var h = String(d.getHours()).padStart(2, '0');
    var min = String(d.getMinutes()).padStart(2, '0');
    return y + '/' + m + '/' + day + ' ' + h + ':' + min;
  }

  function paymentSelectHtml(entry) {
    var payment = entry.payment || {};
    if (!payment.required) {
      return '<span class="admin-competition-table__muted">—</span>';
    }
    var labels = cfg.paymentStatusLabels || {};
    var current = payment.status || 'unpaid';
    var entryId = safeIntId(entry.id);
    if (!entryId) {
      return '<span class="admin-competition-table__muted">—</span>';
    }
    var html = '<select class="form-control admin-competition-payment-select" data-entry-id="' + entryId + '" aria-label="入金状態">';
    Object.keys(labels).forEach(function (key) {
      if (key === 'refunded') {
        return;
      }
      html += '<option value="' + escapeHtml(key) + '"' + (key === current ? ' selected' : '') + '>' + escapeHtml(labels[key]) + '</option>';
    });
    html += '</select>';
    return html;
  }

  function entryRowHtml(entry) {
    var team = entry.team || {};
    var actions = entry.actions || {};
    var entryId = safeIntId(entry.id);
    var approveBtn = actions.can_approve && entryId
      ? '<button type="button" class="btn btn-primary btn-sm js-competition-approve-entry" data-entry-id="' + entryId + '">承認</button>'
      : '<span class="admin-competition-table__muted">—</span>';

    return '<tr data-entry-id="' + entryId + '">' +
      '<td>' + escapeHtml(team.team_name || '—') + '</td>' +
      '<td><span class="admin-competition-entry-status ' + entryBadgeClass(entry.status) + '">' +
        escapeHtml(entry.status_label || entry.status || '') + '</span></td>' +
      '<td>' + paymentSelectHtml(entry) + '</td>' +
      '<td>' + escapeHtml(formatDateTime(entry.responded_at)) + '</td>' +
      '<td class="admin-competition-entries-table__actions">' + approveBtn + '</td>' +
      '</tr>';
  }

  function escapeHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function setCompetitionHtml(el, html) {
    if (!el) {
      return;
    }
    el.replaceChildren();
    if (!html) {
      return;
    }
    var template = document.createElement('template');
    template.innerHTML = html;
    el.appendChild(template.content);
  }

  function safeIntId(id) {
    var n = parseInt(id, 10);
    return (isNaN(n) || n < 1) ? 0 : n;
  }

  function updateEntriesSummary(items) {
    var summaryEl = document.getElementById('competition-entries-summary');
    if (!summaryEl) {
      return;
    }
    var confirmed = 0;
    var invited = 0;
    var applied = 0;
    items.forEach(function (entry) {
      if (entry.status === 'confirmed') {
        confirmed++;
      } else if (entry.status === 'invited') {
        invited++;
      } else if (entry.status === 'applied') {
        applied++;
      }
    });
    summaryEl.textContent = '確定 ' + confirmed + ' / 招待中 ' + invited + ' / 承認待ち ' + applied;
  }

  function renderEntriesTable(items) {
    var wrap = document.getElementById('competition-entries-table-wrap');
    if (!wrap) {
      return;
    }

    updateEntriesSummary(items);

    if (!items.length) {
      setCompetitionHtml(wrap, '<p class="admin-competition-entries__empty" id="competition-entries-empty">' +
        'まだ参加チームがありません。上の検索からチームを招待してください。</p>');
      return;
    }

    var rows = items.map(entryRowHtml).join('');
    setCompetitionHtml(wrap,
      '<table class="admin-competition-table admin-competition-entries-table">' +
      '<thead><tr>' +
      '<th scope="col">チーム</th><th scope="col">参加状態</th><th scope="col">入金</th>' +
      '<th scope="col">回答日時</th><th scope="col">操作</th>' +
      '</tr></thead><tbody id="competition-entries-tbody">' + rows + '</tbody></table>');

    bindEntriesTableEvents();
  }

  function reloadEntries() {
    var id = eventId();
    if (id < 1) {
      return Promise.resolve();
    }
    return fetch(restBase() + 'competition/events/' + id + '/entries', {
      method: 'GET',
      headers: restHeaders()
    })
      .then(parseJsonResponse)
      .then(function (body) {
        var data = body.data || body;
        renderEntriesTable(data.items || []);
      });
  }

  function bindEntriesTableEvents() {
    document.querySelectorAll('.js-competition-approve-entry').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var entryId = parseInt(btn.getAttribute('data-entry-id'), 10);
        if (entryId < 1) {
          return;
        }
        aiduniteConfirm({
          message: 'このチームの参加を承認しますか？確定後、スケジュールが生成されます。',
          confirmLabel: '承認する',
          onConfirm: function () {
            aiduniteWithButtonLoading(btn, function () {
              return fetch(restBase() + 'competition/entries/' + entryId + '/approve', {
                method: 'POST',
                headers: restHeaders(),
                body: JSON.stringify({})
              })
                .then(parseJsonResponse)
                .then(function () {
                  aiduniteToast('参加を承認しました。', 'success');
                  return reloadEntries();
                });
            }, '承認中...');
          }
        });
      });
    });

    document.querySelectorAll('.admin-competition-payment-select').forEach(function (select) {
      select.addEventListener('change', function () {
        var entryId = parseInt(select.getAttribute('data-entry-id'), 10);
        var paymentStatus = select.value;
        if (entryId < 1) {
          return;
        }
        select.disabled = true;
        fetch(restBase() + 'competition/entries/' + entryId + '/payment', {
          method: 'PATCH',
          headers: restHeaders(),
          body: JSON.stringify({ payment_status: paymentStatus })
        })
          .then(parseJsonResponse)
          .then(function () {
            aiduniteToast('入金状態を更新しました。', 'success');
          })
          .catch(function (err) {
            aiduniteToast(err.message || '更新に失敗しました。', 'error');
            return reloadEntries();
          })
          .finally(function () {
            select.disabled = false;
          });
      });
    });
  }

  function renderCandidates(items) {
    var panel = document.getElementById('competition-team-candidates');
    var list = document.getElementById('competition-team-candidates-list');
    var empty = document.getElementById('competition-invite-empty');
    var inviteBtn = document.getElementById('competition-invite-btn');
    if (!panel || !list) {
      return;
    }

    list.replaceChildren();
    if (!items.length) {
      panel.hidden = true;
      if (empty) {
        empty.hidden = false;
      }
      if (inviteBtn) {
        inviteBtn.disabled = true;
      }
      return;
    }

    if (empty) {
      empty.hidden = true;
    }
    panel.hidden = false;

    items.forEach(function (team) {
      var teamId = safeIntId(team.id);
      if (!teamId) {
        return;
      }
      var label = document.createElement('label');
      label.className = 'admin-competition-invite__item';
      var checkbox = document.createElement('input');
      checkbox.type = 'checkbox';
      checkbox.className = 'admin-competition-invite__check';
      checkbox.value = String(teamId);
      var name = document.createElement('span');
      name.className = 'admin-competition-invite__team-name';
      name.textContent = team.name || '';
      var meta = document.createElement('span');
      meta.className = 'admin-competition-invite__team-meta';
      meta.textContent = (team.category || '—') + ' · ' + (team.gender_label || '—');
      label.appendChild(checkbox);
      label.appendChild(name);
      label.appendChild(meta);
      list.appendChild(label);
    });

    list.querySelectorAll('.admin-competition-invite__check').forEach(function (box) {
      box.addEventListener('change', updateInviteButtonState);
    });
    updateInviteButtonState();
  }

  function updateInviteButtonState() {
    var inviteBtn = document.getElementById('competition-invite-btn');
    if (!inviteBtn) {
      return;
    }
    var checked = document.querySelectorAll('.admin-competition-invite__check:checked');
    inviteBtn.disabled = checked.length === 0;
  }

  function searchTeams(query) {
    var id = eventId();
    if (id < 1) {
      return Promise.resolve();
    }
    var url = restBase() + 'competition/events/' + id + '/invite-candidates?q=' + encodeURIComponent(query || '');
    return fetch(url, { method: 'GET', headers: restHeaders() })
      .then(parseJsonResponse)
      .then(function (body) {
        var data = body.data || body;
        renderCandidates(data.items || []);
      })
      .catch(function (err) {
        aiduniteToast(err.message || '検索に失敗しました。', 'error');
      });
  }

  function inviteSelectedTeams(inviteBtn) {
    var id = eventId();
    var teamIds = [];
    document.querySelectorAll('.admin-competition-invite__check:checked').forEach(function (box) {
      teamIds.push(parseInt(box.value, 10));
    });
    if (!teamIds.length || id < 1) {
      aiduniteToast('招待するチームを選択してください。', 'warning');
      return;
    }

    aiduniteWithButtonLoading(inviteBtn, function () {
      return fetch(restBase() + 'competition/events/' + id + '/invite', {
        method: 'POST',
        headers: restHeaders(),
        body: JSON.stringify({ team_ids: teamIds })
      })
        .then(parseJsonResponse)
        .then(function (body) {
          var data = body.data || body;
          var count = (data.entries || []).length;
          aiduniteToast(count > 0 ? count + ' チームを招待しました。' : '招待できる新規チームがありませんでした。', count > 0 ? 'success' : 'warning');
          var searchInput = document.getElementById('competition-team-search');
          if (searchInput) {
            searchInput.value = '';
          }
          document.getElementById('competition-team-candidates').hidden = true;
          return reloadEntries();
        });
    }, '招待中...');
  }

  function initEntriesPanel() {
    var panel = document.getElementById('admin-competition-entries');
    if (!panel || eventId() < 1) {
      return;
    }

    bindEntriesTableEvents();

    var searchBtn = document.getElementById('competition-team-search-btn');
    var searchInput = document.getElementById('competition-team-search');
    var inviteBtn = document.getElementById('competition-invite-btn');

    if (searchBtn) {
      searchBtn.addEventListener('click', function () {
        var q = searchInput ? searchInput.value.trim() : '';
        searchTeams(q);
      });
    }
    if (searchInput) {
      searchInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
          e.preventDefault();
          searchTeams(searchInput.value.trim());
        }
      });
    }
    if (inviteBtn) {
      inviteBtn.addEventListener('click', function () {
        inviteSelectedTeams(inviteBtn);
      });
    }
  }

  function fixtureStatusLabel(status) {
    var labels = cfg.fixtureStatusLabels || {};
    return labels[status] || status;
  }

  function toDatetimeLocal(scheduled) {
    if (!scheduled) {
      return '';
    }
    return String(scheduled).substr(0, 16).replace(' ', 'T');
  }

  function toScheduledAt(datetimeLocal) {
    if (!datetimeLocal) {
      return '';
    }
    var v = String(datetimeLocal).trim();
    if (v === '') {
      return '';
    }
    return v.replace('T', ' ') + (v.length === 16 ? ':00' : '');
  }

  function collectGeneratorConfig() {
    var templateEl = document.getElementById('generator-template');
    var modeEl = document.getElementById('generator-mode');
    var backToBack = document.getElementById('constraint-back-to-back');
    var sameRegion = document.getElementById('constraint-same-region');
    return {
      template_id: templateEl ? templateEl.value : (cfg.currentTemplateId || ''),
      generator_mode: modeEl ? modeEl.value : 'skeleton',
      constraints: {
        avoid_back_to_back: !!(backToBack && backToBack.checked),
        avoid_same_region_round_1: (sameRegion && sameRegion.checked) ? 'best_effort' : 'off'
      }
    };
  }

  function saveGeneratorConfig(showToast) {
    var id = eventId();
    if (id < 1) {
      return Promise.reject(new Error('validation'));
    }
    return fetch(restBase() + 'competition/events/' + id, {
      method: 'PATCH',
      headers: restHeaders(),
      body: JSON.stringify({ generator_config: collectGeneratorConfig() })
    })
      .then(parseJsonResponse)
      .then(function () {
        if (showToast) {
          aiduniteToast('組み合わせ設定を保存しました。', 'success');
        }
      });
  }

  function loadGeneratorTemplates() {
    var select = document.getElementById('generator-template');
    if (!select) {
      return Promise.resolve();
    }
    return fetch(restBase() + 'competition/generator/templates', {
      method: 'GET',
      headers: restHeaders()
    })
      .then(parseJsonResponse)
      .then(function (body) {
        var data = body.data || body;
        var items = (data.items || []).filter(function (tpl) {
          return tpl.has_fixture;
        });
        var current = cfg.currentTemplateId || '';
        select.replaceChildren();
        items.forEach(function (tpl) {
          var id = tpl.template_id || '';
          var option = document.createElement('option');
          option.value = id;
          var label = tpl.name || id;
          if (tpl.recommended) {
            label += '（推奨）';
          }
          option.textContent = label;
          if (id === current) {
            option.selected = true;
          }
          select.appendChild(option);
        });
        if (!select.value && items.length) {
          select.value = items[0].template_id || '';
        }
      })
      .catch(function (err) {
        select.replaceChildren();
        var failOption = document.createElement('option');
        failOption.value = '';
        failOption.textContent = 'テンプレート取得失敗';
        select.appendChild(failOption);
        aiduniteToast(err.message || 'テンプレートの取得に失敗しました。', 'error');
      });
  }

  function renderBlocksList(items) {
    var list = document.getElementById('competition-blocks-list');
    if (!list) {
      return;
    }
    if (!items.length) {
      setCompetitionHtml(list, '<p class="admin-competition-table__muted" id="competition-blocks-empty">ブロック未登録</p>');
      return;
    }
    var rows = items.map(function (block) {
      var courts = (block.courts || []).join(', ');
      return '<li>' + escapeHtml(block.date || '') + ' ' +
        escapeHtml(block.start_time || '') + '〜' + escapeHtml(block.end_time || '') +
        ' — コート ' + escapeHtml(courts || '—') + '</li>';
    }).join('');
    setCompetitionHtml(list, '<ul class="admin-competition-blocks-list__items">' + rows + '</ul>');
  }

  function reloadBlocks() {
    var id = eventId();
    if (id < 1) {
      return Promise.resolve();
    }
    return fetch(restBase() + 'competition/events/' + id + '/blocks', {
      method: 'GET',
      headers: restHeaders()
    })
      .then(parseJsonResponse)
      .then(function (body) {
        var data = body.data || body;
        renderBlocksList(data.items || []);
      });
  }

  function submitBlockForm(form, btn) {
    var id = eventId();
    if (id < 1) {
      return Promise.reject(new Error('validation'));
    }
    var courtsRaw = form.querySelector('[name="courts"]');
    var courts = (courtsRaw ? courtsRaw.value : '')
      .split(',')
      .map(function (c) { return c.trim(); })
      .filter(function (c) { return c !== ''; });
    var payload = {
      block_date: form.querySelector('[name="block_date"]').value,
      start_time: form.querySelector('[name="start_time"]').value,
      end_time: form.querySelector('[name="end_time"]').value,
      courts: courts,
      venue_name: form.querySelector('[name="venue_name"]').value.trim()
    };
    if (!payload.block_date) {
      aiduniteToast('ブロックの日付を入力してください。', 'warning');
      return Promise.reject(new Error('validation'));
    }
    return aiduniteWithButtonLoading(btn, function () {
      return fetch(restBase() + 'competition/events/' + id + '/blocks', {
        method: 'POST',
        headers: restHeaders(),
        body: JSON.stringify(payload)
      })
        .then(parseJsonResponse)
        .then(function () {
          aiduniteToast('日程ブロックを追加しました。', 'success');
          return reloadBlocks();
        });
    }, '追加中...');
  }

  function showGeneratorWarnings(warnings) {
    var el = document.getElementById('competition-generator-warnings');
    if (!el) {
      return;
    }
    if (!warnings || !warnings.length) {
      el.hidden = true;
      el.replaceChildren();
      return;
    }
    el.hidden = false;
    el.replaceChildren();
    var strong = document.createElement('strong');
    strong.textContent = '警告';
    var ul = document.createElement('ul');
    warnings.forEach(function (w) {
      var li = document.createElement('li');
      li.textContent = String(w || '');
      ul.appendChild(li);
    });
    el.appendChild(strong);
    el.appendChild(ul);
  }

  function fixtureRowHtml(fixture) {
    var fixtureId = safeIntId(fixture.id);
    var teamA = fixture.team_a || {};
    var teamB = fixture.team_b || {};
    var score = fixture.score || {};
    var fstatus = fixture.status || 'scheduled';
    var canResult = teamA.team_name && teamB.team_name && fstatus === 'scheduled';
    var datetimeLocal = toDatetimeLocal(fixture.scheduled_at || '');
    var scoreHtml;
    if (canResult) {
      scoreHtml =
        '<input type="number" min="0" class="form-control admin-competition-score-a" data-fixture-id="' + fixtureId + '" ' +
        'value="' + escapeHtml(score.a !== null && score.a !== undefined ? String(score.a) : '') + '" aria-label="スコアA">' +
        '<span>-</span>' +
        '<input type="number" min="0" class="form-control admin-competition-score-b" data-fixture-id="' + fixtureId + '" ' +
        'value="' + escapeHtml(score.b !== null && score.b !== undefined ? String(score.b) : '') + '" aria-label="スコアB">';
    } else if (fstatus === 'finished') {
      scoreHtml = escapeHtml(String(score.a ?? '') + ' - ' + String(score.b ?? ''));
    } else {
      scoreHtml = '<span class="admin-competition-table__muted">—</span>';
    }
    var resultBtn = canResult
      ? '<button type="button" class="btn btn-primary btn-sm js-competition-save-result" data-fixture-id="' + fixtureId + '">結果</button>'
      : '';
    return '<tr data-fixture-id="' + fixtureId + '">' +
      '<td>R' + (fixture.round || 0) + '-' + (fixture.match_index || 0) + '</td>' +
      '<td class="admin-competition-fixtures-table__matchup">' +
      escapeHtml(teamA.team_name || '未定') +
      '<span class="admin-competition-fixtures-table__vs">vs</span>' +
      escapeHtml(teamB.team_name || '未定') + '</td>' +
      '<td><input type="text" class="form-control admin-competition-fixture-court" data-fixture-id="' + fixtureId + '" ' +
      'value="' + escapeHtml(fixture.court_label || '') + '" aria-label="コート"></td>' +
      '<td><input type="datetime-local" class="form-control admin-competition-fixture-time" data-fixture-id="' + fixtureId + '" ' +
      'value="' + escapeHtml(datetimeLocal) + '" aria-label="開始時刻"></td>' +
      '<td>' + escapeHtml(fixtureStatusLabel(fstatus)) + '</td>' +
      '<td class="admin-competition-fixtures-table__score">' + scoreHtml + '</td>' +
      '<td class="admin-competition-fixtures-table__actions">' +
      '<button type="button" class="btn btn-secondary btn-sm js-competition-save-schedule" data-fixture-id="' + fixtureId + '">日程保存</button>' +
      resultBtn + '</td></tr>';
  }

  function renderFixturesTable(items) {
    var wrap = document.getElementById('competition-fixtures-table-wrap');
    if (!wrap) {
      return;
    }
    if (!items.length) {
      setCompetitionHtml(wrap, '<p class="admin-competition-entries__empty" id="competition-fixtures-empty">' +
        '試合表はまだありません。上の「プレビュー」または「試合表を確定」で作成してください。</p>');
      return;
    }
    setCompetitionHtml(wrap,
      '<table class="admin-competition-table admin-competition-fixtures-table">' +
      '<thead><tr><th scope="col">ラウンド</th><th scope="col">対戦</th><th scope="col">コート</th>' +
      '<th scope="col">開始</th><th scope="col">状態</th><th scope="col">結果</th><th scope="col">操作</th></tr></thead>' +
      '<tbody id="competition-fixtures-tbody">' + items.map(fixtureRowHtml).join('') + '</tbody></table>');
    bindFixturesTableEvents();
  }

  function reloadFixtures() {
    var id = eventId();
    if (id < 1) {
      return Promise.resolve();
    }
    return fetch(restBase() + 'competition/events/' + id + '/fixtures', {
      method: 'GET',
      headers: restHeaders()
    })
      .then(parseJsonResponse)
      .then(function (body) {
        var data = body.data || body;
        renderFixturesTable(data.items || []);
      });
  }

  function reloadStandings() {
    var id = eventId();
    if (id < 1) {
      return Promise.resolve();
    }
    return fetch(restBase() + 'competition/events/' + id + '/standings', {
      method: 'GET',
      headers: restHeaders()
    })
      .then(parseJsonResponse)
      .then(function (body) {
        var data = body.data || body;
        renderStandings(data);
      });
  }

  function renderStandings(payload) {
    var wrap = document.getElementById('competition-standings-wrap');
    if (!wrap) {
      return;
    }
    var items = payload.items || [];
    if (!items.length) {
      setCompetitionHtml(wrap, '<p class="admin-competition-table__muted" id="competition-standings-empty">' +
        '決勝結果が入力されると表示されます。</p>');
      return;
    }
    var rows = items.map(function (row) {
      var team = row.team || {};
      return '<li><span class="admin-competition-standings-list__rank">' + escapeHtml(String(row.rank || 0)) + '位</span> ' +
        escapeHtml(team.team_name || '—') + '</li>';
    }).join('');
    setCompetitionHtml(wrap, '<ol class="admin-competition-standings-list" id="competition-standings-list">' + rows + '</ol>');
  }

  function bindFixturesTableEvents() {
    document.querySelectorAll('.js-competition-save-schedule').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var fixtureId = parseInt(btn.getAttribute('data-fixture-id'), 10);
        if (fixtureId < 1) {
          return;
        }
        var row = btn.closest('tr');
        if (!row) {
          return;
        }
        var courtInput = row.querySelector('.admin-competition-fixture-court');
        var timeInput = row.querySelector('.admin-competition-fixture-time');
        aiduniteWithButtonLoading(btn, function () {
          return fetch(restBase() + 'competition/fixtures/' + fixtureId, {
            method: 'PATCH',
            headers: restHeaders(),
            body: JSON.stringify({
              court_label: courtInput ? courtInput.value.trim() : '',
              scheduled_at: toScheduledAt(timeInput ? timeInput.value : '')
            })
          })
            .then(parseJsonResponse)
            .then(function () {
              aiduniteToast('試合日程を更新しました。', 'success');
            });
        }, '保存中...');
      });
    });

    document.querySelectorAll('.js-competition-save-result').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var fixtureId = parseInt(btn.getAttribute('data-fixture-id'), 10);
        if (fixtureId < 1) {
          return;
        }
        var row = btn.closest('tr');
        if (!row) {
          return;
        }
        var scoreA = row.querySelector('.admin-competition-score-a');
        var scoreB = row.querySelector('.admin-competition-score-b');
        var a = scoreA ? parseInt(scoreA.value, 10) : NaN;
        var b = scoreB ? parseInt(scoreB.value, 10) : NaN;
        if (isNaN(a) || isNaN(b)) {
          aiduniteToast('スコアを入力してください。', 'warning');
          return;
        }
        if (a === b) {
          aiduniteToast('同点は入力できません。', 'warning');
          return;
        }
        aiduniteWithButtonLoading(btn, function () {
          return fetch(restBase() + 'competition/fixtures/' + fixtureId + '/result', {
            method: 'POST',
            headers: restHeaders(),
            body: JSON.stringify({ score_a: a, score_b: b })
          })
            .then(parseJsonResponse)
            .then(function () {
              aiduniteToast('試合結果を保存しました。', 'success');
              return Promise.all([reloadFixtures(), reloadStandings()]);
            });
        }, '保存中...');
      });
    });
  }

  function runGenerateFixtures(preview, btn) {
    var id = eventId();
    if (id < 1) {
      return Promise.reject(new Error('validation'));
    }
    var modeEl = document.getElementById('generator-mode');
    var notifyEl = document.getElementById('competition-notify-fixtures');
    var generatorMode = modeEl ? modeEl.value : 'skeleton';

    function doGenerate() {
      return saveGeneratorConfig(false).then(function () {
        return aiduniteWithButtonLoading(btn, function () {
          return fetch(restBase() + 'competition/events/' + id + '/fixtures/generate', {
            method: 'POST',
            headers: restHeaders(),
            body: JSON.stringify({
              generator_mode: generatorMode,
              preview: !!preview,
              notify: !!(notifyEl && notifyEl.checked && !preview),
              replace_existing: true
            })
          })
            .then(parseJsonResponse)
            .then(function (body) {
              var data = body.data || body;
              showGeneratorWarnings(data.warnings || []);
              if (preview) {
                renderFixturesTable(data.fixtures || []);
                aiduniteToast('プレビューを表示しました（未保存）。確定で保存してください。', 'success');
              } else {
                aiduniteToast('試合表を確定しました。', 'success');
                return reloadFixtures();
              }
            });
        }, preview ? 'プレビュー中...' : '確定中...');
      });
    }

    if (preview) {
      return doGenerate();
    }

    return new Promise(function (resolve, reject) {
      aiduniteConfirm({
        message: '試合表を確定します。既存の試合表は置き換えられます。よろしいですか？',
        confirmLabel: '確定する',
        onConfirm: function () {
          doGenerate().then(resolve).catch(reject);
        },
        onCancel: function () {
          reject(new Error('cancelled'));
        }
      });
    });
  }

  function initFixturesPanel() {
    var panel = document.getElementById('admin-competition-fixtures');
    if (!panel || eventId() < 1 || !cfg.hasFixture) {
      return;
    }
    if (cfg.eventStatus === 'draft') {
      return;
    }

    loadGeneratorTemplates();
    bindFixturesTableEvents();

    var blockForm = document.getElementById('competition-block-form');
    var blockBtn = document.getElementById('competition-block-add-btn');
    if (blockForm) {
      blockForm.addEventListener('submit', function (e) {
        e.preventDefault();
        submitBlockForm(blockForm, blockBtn).catch(function (err) {
          if (err.message !== 'validation' && err.message !== 'cancelled') {
            aiduniteToast(err.message || 'ブロック追加に失敗しました。', 'error');
          }
        });
      });
    }

    var saveGenBtn = document.getElementById('competition-save-generator-btn');
    if (saveGenBtn) {
      saveGenBtn.addEventListener('click', function () {
        aiduniteWithButtonLoading(saveGenBtn, function () {
          return saveGeneratorConfig(true);
        }, '保存中...').catch(function (err) {
          aiduniteToast(err.message || '保存に失敗しました。', 'error');
        });
      });
    }

    var previewBtn = document.getElementById('competition-preview-fixtures-btn');
    if (previewBtn) {
      previewBtn.addEventListener('click', function () {
        runGenerateFixtures(true, previewBtn).catch(function (err) {
          if (err.message !== 'validation' && err.message !== 'cancelled') {
            aiduniteToast(err.message || 'プレビューに失敗しました。', 'error');
          }
        });
      });
    }

    var generateBtn = document.getElementById('competition-generate-fixtures-btn');
    if (generateBtn) {
      generateBtn.addEventListener('click', function () {
        runGenerateFixtures(false, generateBtn).catch(function (err) {
          if (err.message !== 'validation' && err.message !== 'cancelled') {
            aiduniteToast(err.message || '確定に失敗しました。', 'error');
          }
        });
      });
    }
  }
})();
