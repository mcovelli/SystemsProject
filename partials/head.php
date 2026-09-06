<?php
/* ============================================================================
   partials/head.php — the shared <head>.

   Set $nu_title before requiring this. Everything else is fixed:

       <?php $nu_title = 'Major Directory'; require __DIR__ . '/partials/head.php'; ?>

   Replaces the hand-copied <head> on 73 pages, 39 of which carried the Google
   Fonts <link> four times over.
   ============================================================================ */
?>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars(($nu_title ?? 'Northport University') . ' | Northport University', ENT_QUOTES) ?></title>

<?php /* Blocking and first on purpose: the stored theme has to be on <html>
         before the first paint, or the page flashes light and then corrects
         itself. Everything else about the toggle lives in assets/js/app.js. */ ?>
<script>
(function () {
  try {
    var t = localStorage.getItem('nu-theme');
    if (t === 'dark' || t === 'light') {
      document.documentElement.setAttribute('data-theme', t);
    }
  } catch (e) { /* private mode: fall through to prefers-color-scheme */ }
})();
</script>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap">

<link rel="stylesheet" href="./assets/css/tokens.css">
<link rel="stylesheet" href="./assets/css/base.css">
<link rel="stylesheet" href="./assets/css/layouts.css">
<link rel="stylesheet" href="./assets/css/components.css">

<?php /* Not deferred. Pages run lucide.createIcons() from inline scripts of
         their own, and a deferred load would not be ready in time. */ ?>
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
<script src="./assets/js/app.js" defer></script>
</head>
