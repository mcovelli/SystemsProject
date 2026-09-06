/* ============================================================================
   app.js — the behaviour every page shares.
   ----------------------------------------------------------------------------
   Replaces the copy of this script that used to sit at the bottom of 61 pages.
   The old copies never persisted anything, so the theme reset to light on
   every navigation.

   The theme is applied earlier than this file runs, by the small inline script
   in partials/head.php -- that has to be blocking and in <head> or the page
   paints light before switching, which is worse than not having a toggle.
   This file only handles the button, the stored value and the icon.
   ========================================================================== */
(function () {
  'use strict';

  var STORE = 'nu-theme';
  var root  = document.documentElement;

  function systemTheme() {
    return window.matchMedia &&
           window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
  }

  function currentTheme() {
    return root.getAttribute('data-theme') || systemTheme();
  }

  /* Lucide swaps each <i data-lucide> for an <svg>, so the original element is
     gone by the time the button is clicked. Rewriting the button's contents and
     re-running createIcons is the one approach that survives that. */
  function paintToggleIcon(btn, theme) {
    if (!btn) return;
    btn.innerHTML = '<i data-lucide="' + (theme === 'dark' ? 'sun' : 'moon') + '"></i>';
    if (window.lucide) window.lucide.createIcons();
  }

  function applyTheme(theme, btn) {
    root.setAttribute('data-theme', theme);
    try { localStorage.setItem(STORE, theme); } catch (e) { /* private mode */ }
    paintToggleIcon(btn, theme);
  }

  /* The ☰ Menu and the profile dropdown open on hover in CSS, which is fine
     with a mouse and useless with a keyboard or a touchscreen -- and it shuts
     the moment the pointer strays. Clicking the button pins it open until you
     click away or press Escape. */
  function initMenus() {
    var menus = document.querySelectorAll('.menu, .dropdown');
    if (!menus.length) return;

    /* Tells the stylesheet to drop its :focus-within fallback, which is only
       there for the case where this file never ran. */
    document.documentElement.classList.add('js-menus');

    function panelOf(menu) {
      return menu.querySelector('.menu-content, .dropdown-content');
    }

    function close(menu) {
      var panel = panelOf(menu);
      if (panel) panel.classList.remove('open');
      var btn = menu.querySelector('button');
      if (btn) btn.setAttribute('aria-expanded', 'false');
    }

    function closeAll(except) {
      menus.forEach(function (m) { if (m !== except) close(m); });
    }

    menus.forEach(function (menu) {
      var btn = menu.querySelector('button');
      var panel = panelOf(menu);
      if (!btn || !panel) return;

      btn.setAttribute('aria-expanded', 'false');

      btn.addEventListener('click', function (e) {
        e.stopPropagation();
        var willOpen = !panel.classList.contains('open');
        closeAll(menu);
        panel.classList.toggle('open', willOpen);
        btn.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
      });

      /* Clicks on the links inside must still navigate. */
      panel.addEventListener('click', function (e) { e.stopPropagation(); });
    });

    document.addEventListener('click', function () { closeAll(null); });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') closeAll(null);
    });
  }

  function init() {
    var btn = document.getElementById('themeToggle');

    if (btn) {
      paintToggleIcon(btn, currentTheme());
      btn.addEventListener('click', function () {
        applyTheme(currentTheme() === 'dark' ? 'light' : 'dark', btn);
      });
    }

    /* Follow the OS while the viewer has expressed no preference of their own. */
    if (window.matchMedia) {
      var mq = window.matchMedia('(prefers-color-scheme: dark)');
      var onChange = function () {
        var stored = null;
        try { stored = localStorage.getItem(STORE); } catch (e) {}
        if (!stored) paintToggleIcon(btn, systemTheme());
      };
      if (mq.addEventListener) mq.addEventListener('change', onChange);
      else if (mq.addListener) mq.addListener(onChange);
    }

    initMenus();

    document.querySelectorAll('#year').forEach(function (el) {
      el.textContent = new Date().getFullYear();
    });

    if (window.lucide) window.lucide.createIcons();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
