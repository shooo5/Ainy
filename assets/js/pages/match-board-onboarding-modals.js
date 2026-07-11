/**
 * 試合掲示板オンボーディングモーダル（申請状況 / 募集中 / ボット申請）
 */
(function () {
  'use strict';

  var cfg = window.aiduniteMatchBoardConfig || {};
  var onboarding = cfg.onboarding || {};
  if (!onboarding.active) {
    return;
  }

  var restUrl = (cfg.restUrl || '').replace(/\/$/, '');
  var restNonce = cfg.restNonce || '';
  var genderTheme = onboarding.genderTheme === 'male' || onboarding.genderTheme === 'female'
    ? onboarding.genderTheme
    : '';
  var state = {
    showMyIntro: !!onboarding.showMyIntro,
    showRecruitIntro: !!onboarding.showRecruitIntro,
    showBotApproveIntro: !!onboarding.showBotApproveIntro,
    firstRecruitScheduleId: parseInt(onboarding.firstRecruitScheduleId, 10) || 0,
    botScheduleId: parseInt(onboarding.botScheduleId, 10) || 0,
  };
  var queue = [];
  var currentModal = null;

  var MODAL_ICONS = {
    my_intro: 'info',
    recruit_intro: 'campaign',
    bot_arrived: 'mail',
    bot_approve: 'group',
  };

  function escapeHtml(str) {
    return String(str || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function modalIconHtml(iconName) {
    if (typeof AidUniteThemeIcons !== 'undefined' && AidUniteThemeIcons.html) {
      return AidUniteThemeIcons.html(iconName, 20);
    }
    return '';
  }

  function buildTitleHeadHtml(title, iconName) {
    return ''
      + '<header class="match-board-onboarding-modal__head">'
      + '<div class="match-board-onboarding-modal__head-title-wrap">'
      + '<div class="match-board-onboarding-modal__head-main">'
      + '<span class="match-board-onboarding-modal__icon" aria-hidden="true">' + modalIconHtml(iconName) + '</span>'
      + '<h2 class="match-board-onboarding-modal__title" id="match-board-onboarding-modal-title">' + escapeHtml(title) + '</h2>'
      + '</div>'
      + '<span class="match-board-onboarding-modal__head-accent" aria-hidden="true"></span>'
      + '</div>'
      + '</header>';
  }

  function getTabs() {
    return window.aiduniteMatchBoardTabs || {};
  }

  function markStepShown(step) {
    if (!restUrl || !restNonce) {
      return Promise.resolve();
    }
    return fetch(restUrl + '/onboarding-board/modal-shown', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': restNonce,
      },
      credentials: 'same-origin',
      body: JSON.stringify({ step: step }),
    }).then(function (r) {
      return r.json();
    }).then(function (data) {
      if (data && data.payload) {
        state.showMyIntro = !!data.payload.showMyIntro;
        state.showRecruitIntro = !!data.payload.showRecruitIntro;
        state.showBotApproveIntro = !!data.payload.showBotApproveIntro;
      }
    }).catch(function () {
      /* ignore */
    });
  }

  function closeModal() {
    if (!currentModal) {
      return;
    }
    currentModal.remove();
    currentModal = null;
    document.body.classList.remove('mypage-onboarding-bot-modal-open');
    document.body.classList.remove('match-board-onboarding-sheet-open');
  }

  function applyModalIconTheme(modal) {
    if (!modal) {
      return;
    }
    var iconWrap = modal.querySelector('.match-board-onboarding-modal__icon');
    if (!iconWrap) {
      return;
    }
    var accent = genderTheme === 'female' ? '#7c3aed' : (genderTheme === 'male' ? '#3b82f6' : '');
    if (!accent) {
      return;
    }
    iconWrap.style.color = accent;
    var icons = iconWrap.querySelectorAll('.aidunite-icon, svg, path');
    icons.forEach(function (el) {
      el.style.color = accent;
      var tag = el.tagName ? el.tagName.toLowerCase() : '';
      if (tag === 'svg' || tag === 'path') {
        el.setAttribute('fill', 'currentColor');
      }
    });
  }

  /**
   * @param {string} id
   * @param {string} title
   * @param {string} bodyHtml
   * @param {string} actionsHtml
   * @param {{ variant?: string, icon?: string }} options
   */
  function buildModal(id, title, bodyHtml, actionsHtml, options) {
    options = options || {};
    closeModal();
    var variant = options.variant || 'center';
    var iconName = options.icon || 'info';
    var modal = document.createElement('div');
    modal.id = id;
    modal.className = 'mypage-onboarding-bot-modal match-board-onboarding-modal'
      + (variant === 'sheet' ? ' match-board-onboarding-modal--sheet' : '');
    if (genderTheme) {
      modal.setAttribute('data-gender-theme', genderTheme);
    }
    modal.setAttribute('role', 'dialog');
    modal.setAttribute('aria-modal', 'true');
    modal.setAttribute('aria-labelledby', 'match-board-onboarding-modal-title');
    modal.innerHTML = ''
      + '<div class="mypage-onboarding-bot-modal__backdrop" data-board-onboarding-dismiss="1"></div>'
      + '<div class="mypage-onboarding-bot-modal__panel card">'
      + '<div class="card-body mypage-onboarding-bot-modal__body">'
      + buildTitleHeadHtml(title, iconName)
      + bodyHtml
      + '<div class="mypage-onboarding-bot-modal__actions">' + actionsHtml + '</div>'
      + '</div></div>';
    document.body.appendChild(modal);
    document.body.classList.add('mypage-onboarding-bot-modal-open');
    if (variant === 'sheet') {
      document.body.classList.add('match-board-onboarding-sheet-open');
    }
    currentModal = modal;
    applyModalIconTheme(modal);

    modal.querySelectorAll('[data-board-onboarding-dismiss]').forEach(function (el) {
      el.addEventListener('click', function (event) {
        event.preventDefault();
        closeModal();
        runQueue();
      });
    });

    document.addEventListener('keydown', function onEsc(event) {
      if (event.key !== 'Escape' || !currentModal) {
        return;
      }
      document.removeEventListener('keydown', onEsc);
      closeModal();
      runQueue();
    });

    return modal;
  }

  function myIntroBodyHtml() {
    return ''
      + '<p class="mypage-onboarding-bot-modal__text match-board-onboarding-modal__lead">'
      + '登録した募集・届いた申請・確定した試合は、<br>上の日程に表示されます。</p>'
      + '<ul class="match-board-onboarding-modal__notes">'
      + '<li><strong>招待コード</strong> — Ainy 未登録の相手にも試合を招待できます。</li>'
      + '<li><strong>編集</strong> — 登録した募集内容を修正できます。</li>'
      + '</ul>';
  }

  function recruitIntroBodyHtml() {
    return ''
      + '<p class="mypage-onboarding-bot-modal__text match-board-onboarding-modal__lead">'
      + '他チームの募集を探して申請できます。<br>条件が合うものは上位に表示されます。</p>';
  }

  function scrollToFirstMyBlock() {
    window.setTimeout(function () {
      var block = document.querySelector('.market-axis-my-block');
      if (block && typeof block.scrollIntoView === 'function') {
        block.scrollIntoView({ behavior: 'smooth', block: 'start' });
        block.classList.add('market-axis-debug-highlight');
        window.setTimeout(function () {
          block.classList.remove('market-axis-debug-highlight');
        }, 3500);
      }
    }, 120);
  }

  function showMyIntroModal() {
    scrollToFirstMyBlock();
    var modal = buildModal(
      'match-board-onboarding-my-intro',
      '申請状況の見方',
      myIntroBodyHtml(),
      '<button type="button" class="btn btn-primary" data-board-onboarding-action="my-to-recruit">募集中の試合を見る</button>',
      { variant: 'sheet', icon: MODAL_ICONS.my_intro }
    );
    var btn = modal.querySelector('[data-board-onboarding-action="my-to-recruit"]');
    if (btn) {
      btn.addEventListener('click', function () {
        state.showMyIntro = false;
        markStepShown('my_intro').finally(function () {
          closeModal();
          var tabs = getTabs();
          if (typeof tabs.showRecruitTab === 'function') {
            tabs.showRecruitTab();
          }
          enqueueForTab('recruit');
          runQueue();
        });
      });
    }
  }

  function goToMyTabForBotApprove() {
    var params = new URLSearchParams(window.location.search);
    params.set('market_tab', 'my');
    if (state.firstRecruitScheduleId > 0) {
      params.set('highlight_schedule', String(state.firstRecruitScheduleId));
    }
    var qs = params.toString();
    window.location.search = qs ? ('?' + qs) : window.location.pathname;
  }

  function showRecruitIntroModal() {
    var modal = buildModal(
      'match-board-onboarding-recruit-intro',
      '募集中の試合',
      recruitIntroBodyHtml(),
      '<button type="button" class="btn btn-primary" data-board-onboarding-action="recruit-done">OK</button>',
      { variant: 'sheet', icon: MODAL_ICONS.recruit_intro }
    );
    var btn = modal.querySelector('[data-board-onboarding-action="recruit-done"]');
    if (btn) {
      btn.addEventListener('click', function () {
        state.showRecruitIntro = false;
        markStepShown('recruit_intro').finally(function () {
          closeModal();
          showBotArrivedModal();
        });
      });
    }
  }

  function showBotArrivedModal() {
    var modal = buildModal(
      'match-board-onboarding-bot-arrived',
      '申請が届いたようです。',
      '<p class="mypage-onboarding-bot-modal__text match-board-onboarding-modal__lead">申請状況に確認しに行きましょう。</p>',
      '<button type="button" class="btn btn-primary" data-board-onboarding-action="bot-to-my">申請状況に行く</button>',
      { variant: 'center', icon: MODAL_ICONS.bot_arrived }
    );
    var btn = modal.querySelector('[data-board-onboarding-action="bot-to-my"]');
    if (btn) {
      btn.addEventListener('click', function () {
        markStepShown('bot_arrived').finally(function () {
          closeModal();
          goToMyTabForBotApprove();
        });
      });
    }
  }

  function scrollToBotPendingRow() {
    var sid = state.firstRecruitScheduleId;
    window.setTimeout(function () {
      var block = sid
        ? document.querySelector('.market-axis-my-block[data-schedule-id="' + sid + '"]')
        : null;
      var target = block
        ? (block.querySelector('.market-axis-my-slot-row .match-btn--primary')
          || block.querySelector('.market-axis-my-slot-row'))
        : document.querySelector('.market-axis-my-slot-row');
      if (target && typeof target.scrollIntoView === 'function') {
        target.scrollIntoView({ behavior: 'smooth', block: 'center' });
        var row = target.closest('.market-axis-my-slot-row') || target;
        var block = row.closest('.market-axis-my-block');
        row.classList.add('market-axis-debug-highlight');
        if (block) {
          block.classList.add('market-axis-debug-highlight');
        }
        window.setTimeout(function () {
          row.classList.remove('market-axis-debug-highlight');
          if (block) {
            block.classList.remove('market-axis-debug-highlight');
          }
        }, 5500);
      }
    }, 200);
  }

  function showBotApproveModal() {
    scrollToFirstMyBlock();
    var modal = buildModal(
      'match-board-onboarding-bot-approve',
      '練習相手チームから申請が届きました',
      '<p class="mypage-onboarding-bot-modal__text match-board-onboarding-modal__lead">'
      + '練習相手チームから申請が届いています。<strong>詳細を確認</strong>から承認してください。</p>',
      '<button type="button" class="btn btn-primary" data-board-onboarding-action="bot-approve-check">申請を確認する</button>',
      { variant: 'sheet', icon: MODAL_ICONS.bot_approve }
    );
    var btn = modal.querySelector('[data-board-onboarding-action="bot-approve-check"]');
    if (btn) {
      btn.addEventListener('click', function () {
        state.showBotApproveIntro = false;
        markStepShown('bot_approve').finally(function () {
          closeModal();
          scrollToBotPendingRow();
        });
      });
    }
  }

  function enqueueForTab(tab) {
    queue = [];
    if (tab === 'my') {
      if (state.showMyIntro) {
        queue.push(showMyIntroModal);
      } else if (state.showBotApproveIntro) {
        queue.push(showBotApproveModal);
      }
    } else if (tab === 'recruit') {
      if (state.showRecruitIntro) {
        queue.push(showRecruitIntroModal);
      }
    }
  }

  function runQueue() {
    if (currentModal || !queue.length) {
      return;
    }
    var next = queue.shift();
    if (typeof next === 'function') {
      next();
    }
  }

  function initForCurrentTab() {
    var tabs = getTabs();
    var tab = typeof tabs.getActiveTab === 'function' ? tabs.getActiveTab() : (cfg.initialTab || 'recruit');
    enqueueForTab(tab);
    runQueue();
  }

  window.addEventListener('aidunite:match-board-tab-shown', function (event) {
    var tab = event && event.detail ? event.detail.tab : '';
    if (!tab) {
      return;
    }
    enqueueForTab(tab);
    runQueue();
  });

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      window.setTimeout(initForCurrentTab, 80);
    });
  } else {
    window.setTimeout(initForCurrentTab, 80);
  }
})();
