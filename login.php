<?php
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1');
session_start();
require_once __DIR__ . '/config.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/* ---------- only POST ---------- */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(PROJECT_ROOT . '/login.html');
}

/* ---------- Regenerate session ID for security ---------- */
if (session_status() === PHP_SESSION_ACTIVE) {
    session_regenerate_id(true);
} else {
    session_start();
}

/* ---------- inputs ---------- */
$email    = trim($_POST['email'] ?? '');
$password = (string)($_POST['password'] ?? '');

if ($email === '' || $password === '') {
    redirect(PROJECT_ROOT . '/login.html?err=empty');
}

try {
    $mysqli = get_db();

    /* ---------- load user by email ---------- */
    $stmt = $mysqli->prepare("
        SELECT 
            u.UserID,
            u.Email,
            u.UserType,
            u.Status,
            u.FirstName,
            u.LastName,
            l.LoginID,
            l.Password AS PasswordHash
        FROM Users u
        INNER JOIN Login l ON l.LoginID = u.UserID
        WHERE u.Email = ?
        LIMIT 1
    ");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $res  = $stmt->get_result();
    $user = $res->fetch_assoc();
    $stmt->close();

    if (!$user) {
        redirect(PROJECT_ROOT . '/login.html?err=invalid');
    }

    if (strtoupper((string)$user['Status']) !== 'ACTIVE') {
        redirect(PROJECT_ROOT . '/login.html?err=inactive');
    }

    $stored = (string)($user['PasswordHash'] ?? '');
    $is_valid = false;

    /* ---------- bcrypt first ---------- */
    if ($stored !== '' && str_starts_with($stored, '$2y$')) {
        if (password_verify($password, $stored)) {
            $is_valid = true;

            // Rehash if needed
            if (password_needs_rehash($stored, PASSWORD_BCRYPT)) {
                $new = password_hash($password, PASSWORD_BCRYPT);
                $up  = $mysqli->prepare("UPDATE Login SET Password = ? WHERE LoginID = ?");
                $up->bind_param('si', $new, $user['LoginID']);
                $up->execute();
                $up->close();
            }
        }
    }
    /* ---------- fallback: legacy SHA-256 ---------- */
    elseif ($stored !== '' && hash('sha256', $password) === strtolower($stored)) {
        $is_valid = true;
        // Auto-upgrade to bcrypt
        $new = password_hash($password, PASSWORD_BCRYPT);
        $up  = $mysqli->prepare("UPDATE Login SET Password = ? WHERE LoginID = ?");
        $up->bind_param('si', $new, $user['LoginID']);
        $up->execute();
        $up->close();
    }

    if (!$is_valid) {
        redirect(PROJECT_ROOT . '/login.html?err=invalid');
    }

    /* ---------- session ---------- */
    session_regenerate_id(true);
    $_SESSION['login_id']    = (int)$user['LoginID'];
    $_SESSION['user_type']   = (string)$user['UserType'];
    $_SESSION['user_id']     = (int)$user['UserID'];
    $_SESSION['role']       = strtolower((string)$user['UserType']);
    $_SESSION['first_name'] = (string)$user['FirstName'];
    $_SESSION['last_name']  = (string)$user['LastName'];

    /* ---------- role routing ---------- */
    $role = $_SESSION['role'];
    switch ($role) {
        case 'student':
            redirect(PROJECT_ROOT . '/student_dashboard.php');
            break;
        case 'faculty':
            redirect(PROJECT_ROOT . '/faculty_dashboard.php');
            break;
        case 'admin':
            $userId = (int)$user['UserID'];

            // Check which subtype they are
            $stmt = $mysqli->prepare("SELECT SecurityType FROM Admin WHERE AdminID = ?");
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $sub = $stmt->get_result()->fetch_assoc();
           $_SESSION['admin_type'] = strtolower(trim($sub['SecurityType'] ?? 'view'));
            $stmt->close();

            if ($_SESSION['admin_type'] === 'update') {
                redirect(PROJECT_ROOT . '/update_admin_dashboard.php');
            } else {
                redirect(PROJECT_ROOT . '/view_admin_dashboard.php');
            }
            
            break;
        case 'statstaff':
            redirect(PROJECT_ROOT . '/statstaff_dashboard.php');
            break;
        default:
            redirect(PROJECT_ROOT . '/login.html?err=role');
            break;
    }

} catch (Throwable $e) {
    http_response_code(500);
    echo "<pre style='color:#b00;font-weight:700'>LOGIN ERROR: " . htmlspecialchars($e->getMessage()) . "</pre>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    exit;
}
?>