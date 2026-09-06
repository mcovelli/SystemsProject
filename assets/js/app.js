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
