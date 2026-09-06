<?php
/* ============================================================================
   partials/user_context.php — who is signed in, and where their links go.
   ----------------------------------------------------------------------------
   Sets $userRole, $dashboard and $profile from the session.

   partials/header.php requires this itself, so a page only needs it directly
   when it reads one of those variables *before* the header is rendered --
   building a quick-links array, say, or a link inside a popup.

   Guarded, so requiring it twice costs nothing.
   ============================================================================ */

if (!isset($NU_CONTEXT_LOADED)) {
    $NU_CONTEXT_LOADED = true;

    $userRole = strtolower($_SESSION['role'] ?? '');

    switch ($userRole) {
        case 'student':
            $dashboard = 'student_dashboard.php';
            $profile   = 'student_profile.php';
            break;
        case 'faculty':
            $dashboard = 'faculty_dashboard.php';
            $profile   = 'faculty_profile.php';
            break;
        case 'admin':
            $dashboard = (($_SESSION['admin_type'] ?? '') === 'update')
                       ? 'update_admin_dashboard.php'
                       : 'view_admin_dashboard.php';
            $profile   = 'admin_profile.php';
            break;
        case 'statstaff':
            $dashboard = 'statstaff_dashboard.php';
            $profile   = 'admin_profile.php';
            break;
        default:
            $dashboard = 'login.html';
            $profile   = 'login.html';
    }
}
