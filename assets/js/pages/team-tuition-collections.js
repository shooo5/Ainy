/**
 * page-team-tuition-collections.php
 */
(function () {
  'use strict';

  var cfg = typeof aidunitePage_team_tuition_collections !== 'undefined'
    ? aidunitePage_team_tuition_collections
    : {};

  var state = {
    filter: cfg.defaultFilter || 'action_needed',
    showAll: false,
  };

  var FILTER_LABELS = {
    action_needed: '要対応の保護者',
    all: 'すべての保護者',
    paid: '支払い済みの保護者',
    pending_billing: '請求前の保護者',
    overdue: '未払いの保護者',
    failed: '決済失敗の保護者',
    not_registered: 'カード未登録の保護者',
  };

  var FOLLOWUP_TYPES = ['overdue', 'failed', 'not_registered'];

  function $(selector, root) {
    return (root || document).querySelector(selector);
  }

  function $all(selector, root) {
    return Array.prototype.slice.call((root || document).querySelectorAll(selector));
  }

  function showFeedback(message, isError) {
    var el = document.getElementById('team-tuition-copy-feedback');
    if (!el) {
      return;
    }
    el.textContent = message;
    el.hidden = false;
    el.classList.toggle('is-error', !!isError);
  }

  function rowMatchesFilter(row) {
    var status = row.getAttribute('data-month-status') || '';
    var needsAction = row.getAttribute('data-needs-action') === '1';

    if (state.showAll && state.filter === 'action_needed') {
      return true;
    }
    if (state.filter === 'all') {
      return true;
    }
    if (state.filter === 'action_needed') {
      return needsAction;
    }
    return status === state.filter;
  }

  function getVisibleRows() {
    return $all('.team-tuition-collections-parent-row').filter(function (row) {
      return !row.hidden;
    });
  }

  function updateListTitle(visibleCount) {
    var titleEl = document.getElementById('team-tuition-parents-list-title');
    if (!titleEl) {
      return;
    }
    var label = FILTER_LABELS[state.filter] || '保護者';
    if (state.showAll && state.filter === 'action_needed') {
      label = 'すべての保護者';
    }
    titleEl.textContent = label + '（' + visibleCount + '名）';
  }

  function updateFilterCards() {
    $all('.team-tuition-collections-filter').forEach(function (card) {
      var cardFilter = card.getAttribute('data-filter');
      var active = !state.showAll && state.filter !== 'action_needed' && cardFilter === state.filter;
      card.classList.toggle('is-active', active);
      card.setAttribute('aria-pressed', active ? 'true' : 'false');
    });
  }

  function updateShowAllButton() {
    var btn = document.getElementById('team-tuition-show-all');
    if (!btn) {
      return;
    }
    var expanded = state.showAll && state.filter === 'action_needed';
    btn.setAttribute('aria-pressed', expanded ? 'true' : 'false');
    btn.textContent = expanded ? '要対応のみ表示' : 'すべて表示';
  }

  function updateFollowupBlocks() {
    var typesToShow = [];
    if (state.filter === 'action_needed') {
      typesToShow = FOLLOWUP_TYPES.filter(function (type) {
        return $all('.team-tuition-collections-parent-row').some(function (row) {
          return row.getAttribute('data-month-status') === type && rowMatchesFilter(row);
        });
      });
      if (state.showAll) {
        typesToShow = FOLLOWUP_TYPES.filter(function (type) {
          return $all('.team-tuition-collections-parent-row').some(function (row) {
            return row.getAttribute('data-month-status') === type;
          });
        });
      }
    } else if (FOLLOWUP_TYPES.indexOf(state.filter) !== -1) {
      typesToShow = [state.filter];
    }

    $all('.team-tuition-followup-block').forEach(function (block) {
      var type = block.getAttribute('data-followup-type');
      block.hidden = typesToShow.indexOf(type) === -1;
    });
  }

  function applyFilter() {
    var rows = $all('.team-tuition-collections-parent-row');
    var visibleCount = 0;
    rows.forEach(function (row) {
      var visible = rowMatchesFilter(row);
      row.hidden = !visible;
      if (visible) {
        visibleCount++;
      }
    });

    var emptyEl = document.getElementById('team-tuition-filter-empty');
    if (emptyEl) {
      emptyEl.hidden = visibleCount > 0 || rows.length === 0;
    }

    updateListTitle(visibleCount);
    updateFilterCards();
    updateShowAllButton();
    updateFollowupBlocks();
  }

  function setFilter(filter) {
    if (state.filter === filter && filter !== 'action_needed') {
      state.filter = 'action_needed';
      state.showAll = false;
    } else {
      state.filter = filter;
      state.showAll = false;
    }
    applyFilter();
  }

  function toggleShowAll() {
    if (state.filter !== 'action_needed') {
      state.filter = 'action_needed';
    }
    state.showAll = !state.showAll;
    applyFilter();
  }

  function copyText(text) {
    if (!text) {
      showFeedback(cfg.copyErrorLabel || 'コピーに失敗しました。', true);
      return;
    }
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(function () {
        showFeedback(cfg.copySuccessLabel || '案内文をコピーしました', false);
      }).catch(function () {
        showFeedback(cfg.copyErrorLabel || 'コピーに失敗しました。', true);
      });
      return;
    }
    showFeedback(cfg.copyErrorLabel || 'コピーに失敗しました。', true);
  }

  function copyFollowup(type) {
    var block = $('.team-tuition-followup-block[data-followup-type="' + type + '"]');
    var textarea = block ? block.querySelector('.team-tuition-followup-textarea') : null;
    var messages = cfg.followupMessages || {};
    copyText(textarea ? textarea.value : (messages[type] || ''));
  }

  function csvEscape(value) {
    var str = String(value == null ? '' : value);
    if (str.indexOf('"') !== -1 || str.indexOf(',') !== -1 || str.indexOf('\n') !== -1) {
      return '"' + str.replace(/"/g, '""') + '"';
    }
    return str;
  }

  function downloadCsv() {
    var rows = getVisibleRows();
    if (!rows.length) {
      showFeedback('出力する行がありません。', true);
      return;
    }
    var header = ['保護者名', 'メール', 'お子さま', '今月の状態'];
    var lines = [header.join(',')];
    rows.forEach(function (row) {
      lines.push([
        csvEscape(row.getAttribute('data-parent-name')),
        csvEscape(row.getAttribute('data-parent-email')),
        csvEscape(row.getAttribute('data-child-summary')),
        csvEscape(row.getAttribute('data-status-label')),
      ].join(','));
    });

    var blob = new Blob(['\uFEFF' + lines.join('\n')], { type: 'text/csv;charset=utf-8;' });
    var url = URL.createObjectURL(blob);
    var link = document.createElement('a');
    var month = (cfg.monthLabel || 'tuition').replace(/\s/g, '');
    link.href = url;
    link.download = 'tuition-collections-' + month + '.csv';
    link.click();
    URL.revokeObjectURL(url);
    showFeedback('CSVをダウンロードしました', false);
  }

  document.addEventListener('DOMContentLoaded', function () {
    $all('.team-tuition-collections-filter').forEach(function (card) {
      card.addEventListener('click', function () {
        setFilter(card.getAttribute('data-filter') || 'all');
      });
    });

    var showAllBtn = document.getElementById('team-tuition-show-all');
    if (showAllBtn) {
      showAllBtn.addEventListener('click', toggleShowAll);
    }

    var csvBtn = document.getElementById('team-tuition-export-csv');
    if (csvBtn) {
      csvBtn.addEventListener('click', downloadCsv);
    }

    $all('.team-tuition-copy-followup').forEach(function (btn) {
      btn.addEventListener('click', function () {
        copyFollowup(btn.getAttribute('data-followup-type') || '');
      });
    });

    applyFilter();
  });
})();
