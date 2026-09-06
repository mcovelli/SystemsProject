<?php
/* ============================================================================
   partials/header.php — the topbar.
   ----------------------------------------------------------------------------
   Optional variables, all set before the require:

       $nu_page    string   the pill beside the wordmark      (default: none)
       $nu_crumb   array    ['href.php', 'Back to Directory'] (default: none)
       $nu_search  bool     show the search box               (default: true)
       $nu_bell    bool     show the notifications button     (default: true)

   Replaces 64 hand-copied topbars. Every one of them closed two <div>s it had
   never opened, which is where 52 of the 73 pages got their unbalanced markup
   and why the avatar, user name and menu sat outside .top-actions and pushed
   the page into a horizontal scroll.

   $dashboard and $profile come from partials/user_context.php, which 35 pages
   each carried their own copy of. A page that needs them before the header
   renders can require that file directly.
   ============================================================================ */

require_once __DIR__ . '/user_context.php';

/* Pages that already queried Users pass $user; the rest fall back to what
   login.php put in the session, so no page needs a query just for the header. */
$nu_first = $user['FirstName'] ?? $_SESSION['first_name'] ?? '';
$nu_last  = $user['LastName']  ?? $_SESSION['last_name']  ?? '';
$nu_type  = $user['UserType']  ?? $_SESSION['user_type']  ?? '';

$nu_initials = strtoupper(substr($nu_first, 0, 1) . substr($nu_last, 0, 1));
if (trim($nu_initials) === '') {
    $nu_initials = 'NU';
}

$nu_search = $nu_search ?? true;
$nu_bell   = $nu_bell   ?? true;

/* A crumb pointing back at the viewer's own dashboard or profile cannot be
   written literally, and resolving the role in the page just to build one link
   is what this file exists to avoid. '@dashboard' and '@profile' stand in. */
if (!empty($nu_crumb[0])) {
    if ($nu_crumb[0] === '@dashboard') { $nu_crumb[0] = $dashboard; }
    elseif ($nu_crumb[0] === '@profile') { $nu_crumb[0] = $profile; }
}
?>
<header class="topbar">
  <div class="brand">
    <div class="logo"><i data-lucide="graduation-cap"></i></div>
    <h1>Northport University</h1>
    <?php if (!empty($nu_page)): ?>
      <span class="pill"><?= htmlspecialchars($nu_page, ENT_QUOTES) ?></span>
    <?php endif; ?>
  </div>

  <div class="top-actions">
    <?php if ($nu_search): ?>
      <div class="search">
        <i class="search-icon" data-lucide="search"></i>
        <label class="sr-only" for="nu-search">Search</label>
        <input type="text" id="nu-search" placeholder="Search courses, people, anything…">
      </div>
    <?php endif; ?>

    <?php if ($nu_bell): ?>
      <a class="icon-btn" href="announcements.php" aria-label="Announcements"><i data-lucide="bell"></i></a>
    <?php endif; ?>

    <button type="button" id="themeToggle" class="icon-btn" aria-label="Toggle theme">
      <i data-lucide="moon"></i>
    </button>

    <?php if (!empty($nu_crumb)): ?>
      <div class="divider"></div>
      <div class="crumb">
        <a href="<?= htmlspecialchars($nu_crumb[0], ENT_QUOTES) ?>"><?= htmlspecialchars($nu_crumb[1], ENT_QUOTES) ?></a>
      </div>
    <?php endif; ?>

    <div class="divider"></div>

    <div class="user">
      <div class="avatar" aria-hidden="true"><?= htmlspecialchars($nu_initials, ENT_QUOTES) ?></div>
      <?php if ($nu_type !== ''): ?>
        <div class="user-meta"><div class="name"><?= htmlspecialchars($nu_type, ENT_QUOTES) ?></div></div>
      <?php endif; ?>
      <div class="menu">
        <button type="button" aria-haspopup="true">☰ Menu</button>
        <div class="menu-content">
          <a href="<?= htmlspecialchars($dashboard, ENT_QUOTES) ?>">Dashboard</a>
          <a href="<?= htmlspecialchars($profile, ENT_QUOTES) ?>">Profile</a>
          <a href="logout.php">Logout</a>
        </div>
      </div>
    </div>
  </div>
</header>
