<?php
declare(strict_types=1);

ob_start();
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/config.php'; // <-- defines get_db(), constants

// Fail closed if not POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /SystemsProject/login.html?err=method', true, 302);
    exit;
}

$email    = trim($_POST['email']    ?? '');
$password = trim($_POST['password'] ?? '');
if ($email === '' || $password === '') {
    header('Location: /SystemsProject/login.html?err=missing', true, 302);
    exit;
}

// Map role -> destination
function target_for_role(string $role): string {
    switch (strtolower($role)) {
        case 'student':   return '/SystemsProject/student_dashboard.php';
        case 'faculty':   return '/SystemsProject/faculty_dashboard.php';
        case 'admin':     return '/SystemsProject/admin_dashboard.php';
        case 'statstaff': return '/SystemsProject/statstaff_dashboard.php';
        default:          return '/SystemsProject/login.html?err=route';
    }
}

try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $db = get_db();

    // Adjust table/column names ONLY if your schema differs.
    // Expected columns: id (INT), email (VARCHAR), password (HASH or PLAINTEXT), role (VARCHAR), active (TINYINT)
    $sql = "SELECT id, email, password, role, COALESCE(active,1) AS active
            FROM Users
            WHERE email = ?
            LIMIT 1";

    $stmt = $db->prepare($sql);
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $res = $stmt->get_result();
    $user = $res->fetch_assoc();
    $stmt->close();

    if (!$user) {
        // No such email
        header('Location: /SystemsProject/login.html?err=auth', true, 302);
        exit;
    }

    if ((int)$user['active'] === 0) {
        header('Location: /SystemsProject/login.html?err=inactive', true, 302);
        exit;
    }

    $stored = (string)$user['password'];

    // Prefer password_verify(); fallback to plaintext equality if legacy DB
    $ok = password_verify($password, $stored) || hash_equals($stored, $password);

    if (!$ok) {
        header('Location: /SystemsProject/login.html?err=auth', true, 302);
        exit;
    }

    // Auth OK: establish session
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['email']   = (string)$user['email'];
    $_SESSION['role']    = (string)$user['role'];

    // Extra session hardening (optional but recommended)
    session_regenerate_id(true);

    $target = target_for_role($_SESSION['role'] ?? '');
    header('Location: ' . $target, true, 302);
    exit;

} catch (Throwable $e) {
    // Log server-side; show friendly redirect to user
    error_log('[LOGIN] Fatal: ' . $e->getMessage());
    header('Location: /SystemsProject/login.html?err=server', true, 302);
    exit;
}