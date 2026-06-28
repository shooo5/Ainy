/**
 * タブ UI 共通コントローラ（.tab-navigation + .tab-btn[data-tab] + .tab-content）
 *
 * aiduniteInitTabGroup(navEl) — 単一グループ初期化
 * aiduniteInitTabs(root) — root 内の全グループ初期化（skip 属性を除く）
 */
(function initAiduniteTabController(global) {
    'use strict';

    if (!global) {
        return;
    }

    function getParentScope(navEl) {
        return navEl && navEl.parentElement ? navEl.parentElement : null;
    }

    function collectButtons(navEl) {
        return Array.prototype.slice.call(navEl.querySelectorAll('.tab-btn[data-tab]'));
    }

    function collectPanels(parent, tabIds) {
        var idSet = {};
        tabIds.forEach(function (id) {
            idSet[id] = true;
        });
        return Array.prototype.slice.call(parent.querySelectorAll('.tab-content')).filter(function (panel) {
            return panel.id && idSet[panel.id];
        });
    }

    function setPanelVisible(panel, isActive) {
        panel.classList.toggle('active', isActive);
        if (isActive) {
            panel.removeAttribute('hidden');
        } else {
            panel.setAttribute('hidden', '');
        }
    }

    function collectFilterButtons(navEl) {
        return Array.prototype.slice.call(navEl.querySelectorAll('button[role="tab"][data-filter]'));
    }

    function bindTabKeyboard(buttons, btn, activateByIndex) {
        btn.addEventListener('keydown', function (event) {
            var key = event.key;
            var currentIndex = buttons.indexOf(btn);
            var nextIndex = currentIndex;

            if (key === 'ArrowRight' || key === 'ArrowDown') {
                nextIndex = (currentIndex + 1) % buttons.length;
            } else if (key === 'ArrowLeft' || key === 'ArrowUp') {
                nextIndex = (currentIndex - 1 + buttons.length) % buttons.length;
            } else if (key === 'Home') {
                nextIndex = 0;
            } else if (key === 'End') {
                nextIndex = buttons.length - 1;
            } else {
                return;
            }

            event.preventDefault();
            activateByIndex(nextIndex, true);
        });
    }

    /**
     * フィルタ専用タブ（パネルなし・data-filter）— 通知一覧など
     * @param {HTMLElement} navEl
     * @param {{ onChange?: function(string): void }} [options]
     */
    function aiduniteInitFilterTabGroup(navEl, options) {
        if (!navEl || navEl.getAttribute('data-aidunite-tabs-bound') === '1') {
            return null;
        }

        var opts = options || {};
        var buttons = collectFilterButtons(navEl);
        if (!buttons.length) {
            return null;
        }

        navEl.setAttribute('data-aidunite-tabs-bound', '1');
        if (!navEl.getAttribute('role')) {
            navEl.setAttribute('role', 'tablist');
        }

        function activateFilter(filterValue, activateOpts) {
            if (!filterValue) {
                return;
            }

            buttons.forEach(function (btn) {
                var isActive = btn.getAttribute('data-filter') === filterValue;
                btn.classList.toggle('active', isActive);
                btn.setAttribute('aria-selected', isActive ? 'true' : 'false');
                btn.setAttribute('tabindex', isActive ? '0' : '-1');
            });

            if (activateOpts && activateOpts.focusButton) {
                var activeBtn = null;
                buttons.forEach(function (b) {
                    if (b.getAttribute('data-filter') === filterValue) {
                        activeBtn = b;
                    }
                });
                if (activeBtn) {
                    activeBtn.focus();
                }
            }

            if (typeof opts.onChange === 'function') {
                opts.onChange(filterValue);
            }

            navEl.dispatchEvent(new CustomEvent('aidunite-tab-filter', {
                bubbles: true,
                detail: { filter: filterValue }
            }));
        }

        function activateByIndex(index, focusButton) {
            var targetBtn = buttons[index];
            if (!targetBtn) {
                return;
            }
            activateFilter(targetBtn.getAttribute('data-filter'), { focusButton: focusButton });
        }

        buttons.forEach(function (btn) {
            btn.setAttribute('tabindex', btn.classList.contains('active') ? '0' : '-1');
            if (!btn.hasAttribute('aria-selected')) {
                btn.setAttribute('aria-selected', btn.classList.contains('active') ? 'true' : 'false');
            }

            btn.addEventListener('click', function () {
                activateFilter(btn.getAttribute('data-filter'), { focusButton: true });
            });

            bindTabKeyboard(buttons, btn, activateByIndex);
        });

        var initialBtn = null;
        buttons.forEach(function (b) {
            if (b.classList.contains('active')) {
                initialBtn = b;
            }
        });
        if (!initialBtn) {
            initialBtn = buttons[0];
        }

        if (initialBtn) {
            activateFilter(initialBtn.getAttribute('data-filter'), { focusButton: false });
        }

        return { activate: activateFilter, buttons: buttons };
    }

    function aiduniteInitTabGroup(navEl, options) {
        if (!navEl || navEl.getAttribute('data-aidunite-tabs-bound') === '1') {
            return null;
        }

        var opts = options || {};
        var parent = getParentScope(navEl);
        if (!parent) {
            return null;
        }

        var buttons = collectButtons(navEl);
        if (!buttons.length) {
            return null;
        }

        var tabIds = buttons.map(function (btn) {
            return btn.getAttribute('data-tab');
        }).filter(Boolean);

        var panels = collectPanels(parent, tabIds);
        if (!panels.length) {
            return null;
        }

        navEl.setAttribute('data-aidunite-tabs-bound', '1');
        if (!navEl.getAttribute('role')) {
            navEl.setAttribute('role', 'tablist');
        }

        buttons.forEach(function (btn, index) {
            var tabId = btn.getAttribute('data-tab');
            if (!btn.getAttribute('role')) {
                btn.setAttribute('role', 'tab');
            }
            if (!btn.getAttribute('aria-controls') && tabId) {
                btn.setAttribute('aria-controls', tabId);
            }
            btn.setAttribute('tabindex', btn.classList.contains('active') ? '0' : '-1');
            if (!btn.hasAttribute('aria-selected')) {
                btn.setAttribute('aria-selected', btn.classList.contains('active') ? 'true' : 'false');
            }

            panels.forEach(function (panel) {
                if (panel.id === tabId) {
                    panel.setAttribute('role', 'tabpanel');
                    if (!panel.getAttribute('aria-labelledby')) {
                        var labelledBy = btn.id || ('tab-btn-' + tabId);
                        if (!btn.id) {
                            btn.id = labelledBy;
                        }
                        panel.setAttribute('aria-labelledby', labelledBy);
                    }
                }
            });

            btn.addEventListener('click', function () {
                activate(tabId, { focusButton: true });
            });

            bindTabKeyboard(buttons, btn, function (index, focusButton) {
                var targetId = buttons[index].getAttribute('data-tab');
                activate(targetId, { focusButton: focusButton });
            });
        });

        function activate(tabId, activateOpts) {
            if (!tabId) {
                return;
            }

            buttons.forEach(function (btn) {
                var isActive = btn.getAttribute('data-tab') === tabId;
                btn.classList.toggle('active', isActive);
                btn.setAttribute('aria-selected', isActive ? 'true' : 'false');
                btn.setAttribute('tabindex', isActive ? '0' : '-1');
            });

            panels.forEach(function (panel) {
                setPanelVisible(panel, panel.id === tabId);
            });

            if (activateOpts && activateOpts.focusButton) {
                var activeBtn = buttons.find(function (b) {
                    return b.getAttribute('data-tab') === tabId;
                });
                if (activeBtn) {
                    activeBtn.focus();
                }
            }

            if (typeof opts.onChange === 'function') {
                opts.onChange(tabId);
            }
        }

        var initialBtn = buttons.find(function (b) {
            return b.classList.contains('active');
        }) || buttons[0];

        if (initialBtn) {
            var initialId = initialBtn.getAttribute('data-tab');
            activate(initialId, { focusButton: false });
        }

        return { activate: activate, buttons: buttons, panels: panels };
    }

    function aiduniteInitTabs(root) {
        var scope = root || (typeof document !== 'undefined' ? document : null);
        if (!scope || !scope.querySelectorAll) {
            return;
        }

        scope.querySelectorAll('.tab-navigation:not([data-aidunite-tabs="skip"]):not([data-aidunite-tabs="filter"])').forEach(function (navEl) {
            aiduniteInitTabGroup(navEl);
        });
    }

    global.aiduniteInitTabGroup = aiduniteInitTabGroup;
    global.aiduniteInitFilterTabGroup = aiduniteInitFilterTabGroup;
    global.aiduniteInitTabs = aiduniteInitTabs;

    if (typeof document !== 'undefined') {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function () {
                aiduniteInitTabs(document);
            });
        } else {
            aiduniteInitTabs(document);
        }
    }
})(typeof window !== 'undefined' ? window : null);
