/**
 * マッチボード（page-match-board-own.php）
 * aiduniteMatchBoardConfig / aiduniteMatchBoardPage は wp_localize_script で注入
 */
(function () {
  'use strict';

(function(){
  var cfg = window.aiduniteMatchBoardConfig || {};
  var tabRecruitBtn = document.getElementById('market-tab-recruit-btn');
  var tabMyBtn = document.getElementById('market-tab-my-btn');
  var tabCompletedBtn = document.getElementById('market-tab-completed-btn');
  var panelRecruit = document.getElementById('market-tab-recruit');
  var panelMy = document.getElementById('market-tab-my');
  var panelCompleted = document.getElementById('market-tab-completed');
  function hideAllPanels() {
    if (panelRecruit) panelRecruit.hidden = true;
    if (panelMy) panelMy.hidden = true;
    if (panelCompleted) panelCompleted.hidden = true;
    if (tabRecruitBtn) { tabRecruitBtn.classList.remove('active'); tabRecruitBtn.setAttribute('aria-selected', 'false'); }
    if (tabMyBtn) { tabMyBtn.classList.remove('active'); tabMyBtn.setAttribute('aria-selected', 'false'); }
    if (tabCompletedBtn) { tabCompletedBtn.classList.remove('active'); tabCompletedBtn.setAttribute('aria-selected', 'false'); }
  }
  function dispatchTabShown(tab) {
    try {
      window.dispatchEvent(new CustomEvent('aidunite:match-board-tab-shown', { detail: { tab: tab } }));
    } catch (e) {
      /* ignore */
    }
  }
  var activeTab = 'recruit';
  function showRecruitTab() {
    hideAllPanels();
    if (panelRecruit) panelRecruit.hidden = false;
    if (tabRecruitBtn) { tabRecruitBtn.classList.add('active'); tabRecruitBtn.setAttribute('aria-selected', 'true'); }
    activeTab = 'recruit';
    dispatchTabShown('recruit');
  }
  function showMyTab() {
    hideAllPanels();
    if (panelMy) panelMy.hidden = false;
    if (tabMyBtn) { tabMyBtn.classList.add('active'); tabMyBtn.setAttribute('aria-selected', 'true'); }
    activeTab = 'my';
    dispatchTabShown('my');
  }
  function showCompletedTab() {
    hideAllPanels();
    if (panelCompleted) panelCompleted.hidden = false;
    if (tabCompletedBtn) { tabCompletedBtn.classList.add('active'); tabCompletedBtn.setAttribute('aria-selected', 'true'); }
    activeTab = 'completed';
    dispatchTabShown('completed');
  }
  window.aiduniteMatchBoardTabs = {
    showRecruitTab: showRecruitTab,
    showMyTab: showMyTab,
    showCompletedTab: showCompletedTab,
    getActiveTab: function () { return activeTab; },
  };
  if (tabRecruitBtn) tabRecruitBtn.addEventListener('click', showRecruitTab);
  if (tabMyBtn) tabMyBtn.addEventListener('click', showMyTab);
  if (tabCompletedBtn) tabCompletedBtn.addEventListener('click', showCompletedTab);

  (function initMarketTabFromQuery() {
    try {
      var params = new URLSearchParams(window.location.search);
      if (params.get('market_tab') === 'my') {
        showMyTab();
      } else if (params.get('market_tab') === 'completed') {
        showCompletedTab();
      } else if (cfg.initialTab === 'my') {
        showMyTab();
      } else if (cfg.initialTab === 'completed') {
        showCompletedTab();
      } else {
        showRecruitTab();
      }
      var hlRaw = params.get('highlight_schedule');
      if (hlRaw) {
        var hl = String(hlRaw).replace(/[^0-9]/g, '');
        if (hl) {
          window.setTimeout(function() {
            var el = document.querySelector('.market-axis-my-block[data-schedule-id="' + hl + '"]');
            if (el) {
              el.scrollIntoView({ behavior: 'smooth', block: 'start' });
              el.classList.add('market-axis-debug-highlight');
              window.setTimeout(function() {
                el.classList.remove('market-axis-debug-highlight');
              }, 4000);
            }
          }, 100);
        }
      }
    } catch (e) {}
  })();

  var quickBtns = document.querySelectorAll('.market-axis-quick-btn');
  if (quickBtns.length) {
    quickBtns.forEach(function(btn) {
      btn.addEventListener('click', function() {
        var q = btn.getAttribute('data-quick') || 'all';
        var params = new URLSearchParams(window.location.search);
        if (q === 'all') {
          params.delete('quick');
        } else {
          params.set('quick', q);
        }
        params.delete('mb_paged');
        params.delete('paged');
        var qs = params.toString();
        window.location.search = qs ? ('?' + qs) : window.location.pathname;
      });
    });
  }
  var myQuickBtns = document.querySelectorAll('.market-axis-my-quick-btn');
  if (myQuickBtns.length) {
    myQuickBtns.forEach(function(btn) {
      btn.addEventListener('click', function() {
        var q = btn.getAttribute('data-my-quick') || 'all';
        var params = new URLSearchParams(window.location.search);
        params.set('market_tab', 'my');
        if (q === 'all') {
          params.delete('my_quick');
        } else {
          params.set('my_quick', q);
        }
        params.delete('mb_paged');
        params.delete('paged');
        var qs = params.toString();
        window.location.search = qs ? ('?' + qs) : window.location.pathname;
      });
    });
  }
  var inviteModal = document.getElementById('matchBoardInviteModal');
  var inviteInput = document.getElementById('market-axis-invite-url-input');
  var inviteSummary = document.getElementById('market-axis-invite-summary');
  var inviteExpires = document.getElementById('market-axis-invite-expires');
  var inviteCopyBtn = document.getElementById('market-axis-invite-copy');
  var container = document.querySelector('.page-match-board-own');
  var restUrl = container ? (container.getAttribute('data-rest-url') || '').replace(/\/$/, '') : '';
  var restNonce = container ? container.getAttribute('data-wp-rest-nonce') : '';
  function openBoardInviteModal() {
    if (inviteModal) {
      inviteModal.setAttribute('aria-hidden', 'false');
      document.body.style.overflow = 'hidden';
    }
  }
  function closeBoardInviteModal() {
    if (inviteModal) {
      inviteModal.setAttribute('aria-hidden', 'true');
      document.body.style.overflow = '';
      document.documentElement.style.overflow = '';
    }
  }
  document.addEventListener('click', function(e) {
    var inviteBtn = e.target.closest('.market-axis-invite-btn:not([disabled])');
    if (inviteBtn && restUrl && restNonce) {
      var scheduleId = inviteBtn.getAttribute('data-schedule-id');
      if (!scheduleId) return;
      inviteBtn.disabled = true;
      inviteSummary.textContent = '取得中...';
      if (inviteInput) inviteInput.value = '';
      if (inviteExpires) inviteExpires.textContent = '';
      fetch(restUrl + '/generate-invite-url', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': restNonce },
        body: JSON.stringify({ schedule_id: parseInt(scheduleId, 10) }),
        credentials: 'same-origin'
      }).then(function(r) { return r.json(); }).then(function(data) {
        inviteBtn.disabled = false;
        if (data && data.success && data.invite_url) {
          if (inviteSummary) inviteSummary.textContent = (data.schedule_date || '') + ' ' + (data.schedule_start || '') + '–' + (data.schedule_end || '') + ' 会場: ' + (data.venue_opponent_label || '');
          if (inviteInput) inviteInput.value = data.invite_url;
          if (inviteExpires) inviteExpires.textContent = '有効期限: ' + (data.expires_at || '72時間');
          openBoardInviteModal();
          if (inviteCopyBtn) inviteCopyBtn.onclick = function() {
            inviteInput.select();
            document.execCommand('copy');
            inviteCopyBtn.textContent = 'コピーしました';
            setTimeout(function() { inviteCopyBtn.textContent = 'コピー'; }, 2000);
          };
        } else {
          inviteSummary.textContent = data && data.message ? data.message : '招待URLの生成に失敗しました';
        }
      }).catch(function() {
        inviteBtn.disabled = false;
        if (inviteSummary) inviteSummary.textContent = '通信エラーです';
      });
      return;
    }
    if (e.target.closest('[data-close-board-invite="1"]')) { closeBoardInviteModal(); return; }
  });
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && inviteModal && inviteModal.getAttribute('aria-hidden') === 'false') closeBoardInviteModal();
  });
})();

(function () {
  function closeAllMyKebabs() {
    document.querySelectorAll('[data-market-axis-my-kebab].is-open').forEach(function (kebab) {
      kebab.classList.remove('is-open');
      var trigger = kebab.querySelector('[data-market-axis-my-kebab-trigger]');
      var menu = kebab.querySelector('.market-axis-my-kebab__menu');
      if (trigger) trigger.setAttribute('aria-expanded', 'false');
      if (menu) menu.hidden = true;
    });
  }

  function openMyKebab(kebab) {
    closeAllMyKebabs();
    kebab.classList.add('is-open');
    var trigger = kebab.querySelector('[data-market-axis-my-kebab-trigger]');
    var menu = kebab.querySelector('.market-axis-my-kebab__menu');
    if (trigger) trigger.setAttribute('aria-expanded', 'true');
    if (menu) menu.hidden = false;
  }

  document.addEventListener('click', function (e) {
    var trigger = e.target.closest('[data-market-axis-my-kebab-trigger]');
    if (trigger) {
      e.preventDefault();
      e.stopPropagation();
      var kebab = trigger.closest('[data-market-axis-my-kebab]');
      if (!kebab) return;
      if (kebab.classList.contains('is-open')) {
        closeAllMyKebabs();
      } else {
        openMyKebab(kebab);
      }
      return;
    }
    if (e.target.closest('.market-axis-my-kebab__item:not([disabled])')) {
      closeAllMyKebabs();
      return;
    }
    if (!e.target.closest('[data-market-axis-my-kebab]')) {
      closeAllMyKebabs();
    }
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeAllMyKebabs();
  });
})();

(function(){
  var container = document.querySelector('.page-match-board-own');
  var nonce = (typeof aiduniteMatchBoardPage !== 'undefined' && aiduniteMatchBoardPage.matchNonce) || '';
  var ajaxUrl = (typeof aiduniteMatchBoardPage !== 'undefined' && aiduniteMatchBoardPage.ajaxUrl) || '';

  function resolveAjaxNonce() {
    // 1) サーバー埋め込み値（最優先）
    if (nonce && String(nonce).trim() !== '') return String(nonce).trim();

    // 2) ルート要素の data 属性
    if (container) {
      var attr = container.getAttribute('data-au-match-nonce');
      if (attr && String(attr).trim() !== '') return String(attr).trim();
    }

    // 3) hidden input（互換）
    var hidden = document.getElementById('au_match_nonce');
    if (hidden && hidden.value && String(hidden.value).trim() !== '') return String(hidden.value).trim();

    return '';
  }

  function openCancelModal(payload) {
    var m = document.getElementById('matchCancelModal');
    if (!m) return;
    var teamEl = m.querySelector('[data-cancel-team]');
    if (teamEl) teamEl.textContent = payload.teamName || '—';
    var titleEl = m.querySelector('#matchCancelTitle');
    var msgEl = m.querySelector('.match-modal__message');
    var submitBtn = m.querySelector('#matchCancelSubmit');
    var isEst = payload.cancelType === 'established';
    var scope = payload.cancelScope || 'pair';
    var isDissolve = isEst && scope === 'dissolve';
    var isHostPair = isEst && scope === 'pair' && payload.isHostPairCancel;
    if (titleEl) {
      titleEl.textContent = isDissolve ? 'ゲームを解散しますか？' : (isEst ? (isHostPair ? 'キャンセルしますか？' : '確定をキャンセルしますか？') : '申請をキャンセルしますか？');
    }
    if (msgEl) {
      if (isDissolve) {
        msgEl.textContent = 'この日程の試合をすべてキャンセルし、募集を初期状態に戻します。参加していた全チームに通知が送られます。';
      } else if (isEst) {
        msgEl.textContent = (payload.teamName || '—') + (isHostPair ? ' との試合確定のみを取り消します。他の確定チームはそのまま残ります。' : ' との試合確定を取り消します。相手に通知が送られます。');
      } else {
        msgEl.textContent = (payload.teamName || '—') + ' への申請を取り下げます。相手に通知が送られます。';
      }
    }
    if (submitBtn) submitBtn.textContent = isDissolve ? 'ゲームを解散する' : 'キャンセルする';
    m.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
    m._payload = { requestId: payload.requestId, cancelScope: scope };
  }

  function closeCancelModal() {
    var m = document.getElementById('matchCancelModal');
    if (m) { m.setAttribute('aria-hidden', 'true'); m._payload = null; }
    document.body.style.overflow = '';
    document.documentElement.style.overflow = '';
  }

  function sendStatusUpdate(requestId, status, submitBtn, doneLabel, cancelScope) {
    var requestKey = String(requestId) + ':' + String(status) + ':' + String(cancelScope || 'pair');
    window.__aiduniteBoardStatusInFlight = window.__aiduniteBoardStatusInFlight || {};
    if (window.__aiduniteBoardStatusInFlight[requestKey]) {
      return;
    }
    window.__aiduniteBoardStatusInFlight[requestKey] = true;
    if (!requestId) {
      window.__aiduniteBoardStatusInFlight[requestKey] = false;
      aiduniteToast('対象の申請IDを取得できませんでした。ページを再読み込みしてください。', 'error');
      return;
    }
    var currentNonce = resolveAjaxNonce();
    if (!currentNonce) {
      window.__aiduniteBoardStatusInFlight[requestKey] = false;
      aiduniteToast('認証情報の読み込みに失敗しました。ページを再読み込みしてください。', 'error');
      return;
    }
    var fd = new FormData();
    fd.append('action', 'au_update_match_request_status');
    fd.append('security', currentNonce);
    fd.append('request_id', String(requestId));
    fd.append('status', status);
    if (status === 'canceled') {
      fd.append('cancel_scope', cancelScope || 'pair');
    }
    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.textContent = '送信中...';
    }
    fetch(ajaxUrl, { method: 'POST', body: fd })
      .then(function(r) {
        return r.text().then(function(txt) {
          var json = null;
          try { json = JSON.parse(txt); } catch (e) {}
          return { ok: r.ok, status: r.status, statusText: r.statusText, json: json, text: txt };
        });
      })
      .then(function(res) {
        var json = res && res.json ? res.json : null;
        if (json && json.success) {
          if (submitBtn) submitBtn.textContent = doneLabel || '完了';
          var data = json.data || {};
          if (status === 'accepted' && data.show_onboarding_bot_chat_modal
              && typeof window.aiduniteShowOnboardingBotChatModal === 'function') {
            window.aiduniteShowOnboardingBotChatModal(data.chat_url || '');
            return;
          }
          setTimeout(function() { location.reload(); }, 400);
          return;
        }
        window.__aiduniteBoardStatusInFlight[requestKey] = false;
        if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = submitBtn.getAttribute('data-original-text') || '送信'; }
        var errMsg = '';
        if (json && json.data) {
          errMsg = (typeof json.data === 'string') ? json.data : (json.data.message || '');
        }
        if (!errMsg && json && json.message) {
          errMsg = String(json.message);
        }
        if (!errMsg && json && json.data && json.data.code) {
          errMsg = 'エラーコード: ' + String(json.data.code);
        }
        if (!errMsg && res && res.text) {
          errMsg = String(res.text).replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
        }
        if (!errMsg) {
          var httpLabel = (res && res.status) ? ('HTTP ' + String(res.status) + (res.statusText ? (' ' + res.statusText) : '')) : '';
          errMsg = httpLabel ? ('処理に失敗しました（' + httpLabel + '）') : '処理に失敗しました';
        }
        aiduniteToast(errMsg, 'error');
      })
      .catch(function() {
        window.__aiduniteBoardStatusInFlight[requestKey] = false;
        if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = submitBtn.getAttribute('data-original-text') || '送信'; }
        aiduniteToast('通信エラーが発生しました', 'error');
      });
  }

  document.addEventListener('click', function(e) {
    var openCancel = e.target.closest('.au-open-cancel-modal');
    if (openCancel) {
      var cancelScope = openCancel.getAttribute('data-cancel-scope') || 'pair';
      openCancelModal({
        requestId: openCancel.getAttribute('data-request-id'),
        teamName: openCancel.getAttribute('data-team-name'),
        cancelType: openCancel.getAttribute('data-cancel-type') || 'apply',
        cancelScope: cancelScope,
        isHostPairCancel: openCancel.getAttribute('data-host-pair-cancel') === '1'
      });
      return;
    }
    if (e.target.closest('[data-close-cancel-modal="1"]')) { closeCancelModal(); return; }
    var cancelSubmit = e.target.closest('#matchCancelSubmit');
    if (cancelSubmit) {
      var m = document.getElementById('matchCancelModal');
      if (m && m._payload && m._payload.requestId) {
        var orig = cancelSubmit.getAttribute('data-original-text');
        if (!orig) { cancelSubmit.setAttribute('data-original-text', cancelSubmit.textContent); }
        sendStatusUpdate(m._payload.requestId, 'canceled', cancelSubmit, 'キャンセルしました', m._payload.cancelScope || 'pair');
      } else {
        aiduniteToast('キャンセル対象を取得できませんでした。ページを再読み込みしてお試しください。', 'error');
      }
      return;
    }
  });

  document.addEventListener('keydown', function(e) {
    if (e.key !== 'Escape') return;
    var cancelM = document.getElementById('matchCancelModal');
    if (cancelM && cancelM.getAttribute('aria-hidden') === 'false') { closeCancelModal(); }
  });
})();
})();
