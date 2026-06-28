/**
 * Ainy ヘッダー・サイドメニュー制御
 * 見た目は CSS（ainy-header.css）。振る舞いのみ。
 */
(function () {
  'use strict';

  var BODY_OPEN_CLASS = 'ainy-side-menu-open';
  var DESKTOP_BREAKPOINT = 768;

  function getSideMenu() {
    return document.getElementById('sideMenu');
  }

  function getOverlay() {
    return document.querySelector('.ainy-side-menu-overlay');
  }

  function getHamburgers() {
    return document.querySelectorAll('.ainy-hamburger');
  }

  function setMenuOpen(isOpen) {
    var sideMenu = getSideMenu();
    var overlay = getOverlay();
    var hamburgers = getHamburgers();

    if (!sideMenu || !overlay) {
      return;
    }

    sideMenu.classList.toggle('open', isOpen);
    overlay.classList.toggle('show', isOpen);
    hamburgers.forEach(function (hamburger) {
      hamburger.classList.toggle('active', isOpen);
    });
    document.body.classList.toggle(BODY_OPEN_CLASS, isOpen);
  }

  function toggleSideMenu() {
    var sideMenu = getSideMenu();
    if (!sideMenu) {
      return;
    }
    setMenuOpen(!sideMenu.classList.contains('open'));
  }

  function openSideMenu() {
    setMenuOpen(true);
  }

  function closeSideMenu() {
    setMenuOpen(false);
  }

  function initSideMenu() {
    closeSideMenu();

    getHamburgers().forEach(function (hamburger) {
      hamburger.addEventListener('click', function (e) {
        e.preventDefault();
        toggleSideMenu();
      });
    });

    document.querySelectorAll('.ainy-bottom-nav-menu-trigger').forEach(function (trigger) {
      trigger.addEventListener('click', function (e) {
        e.preventDefault();
        openSideMenu();
      });
    });

    var overlay = getOverlay();
    if (overlay) {
      overlay.addEventListener('click', function () {
        closeSideMenu();
      });
    }

    var closeButton = document.querySelector('.ainy-side-menu-close');
    if (closeButton) {
      closeButton.addEventListener('click', function () {
        closeSideMenu();
      });
    }

    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') {
        closeSideMenu();
      }
    });

    window.addEventListener('resize', function () {
      if (window.innerWidth > DESKTOP_BREAKPOINT) {
        closeSideMenu();
      }
    });

    document.querySelectorAll('.ainy-side-nav-link').forEach(function (link) {
      link.addEventListener('click', function () {
        window.setTimeout(closeSideMenu, 100);
      });
    });
  }

  window.toggleSideMenu = toggleSideMenu;
  window.openSideMenu = openSideMenu;
  window.closeSideMenu = closeSideMenu;

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initSideMenu);
  } else {
    initSideMenu();
  }
})();
