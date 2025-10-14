<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/config.php';

// Clear any old session
session_unset();
session_destroy();
session_start();

/* Make mysqli throw readable errors we can log */
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function fail_500(string $msg): never {
  error_log("[LOGIN] 500: " . $msg);
  http_response_code(500);
  exit; // keep body empty in prod; check error_log for details
}

/* ------------------ DB CONFIG ------------------ */
$DB_HOST = "127.0.0.1";
$DB_PORT = 3306;
$DB_USER = "phpuser";
$DB_PASS = "SystemsFall2025!";
$DB_NAME = "University";

try {
  /* ------------------ CONNECT ------------------ */
  $mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, $DB_PORT);
  $mysqli->set_charset('utf8mb4');

  /* ------------------ ONLY ALLOW POST ------------------ */
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.html');
    exit;
}

// Sanitize input
$email = trim($_POST['email'] ?? '');
$password = trim($_POST['password'] ?? '');

if ($email === '' || $password === '') {
    header('Location: ' . PROJECT_ROOT . '/login.html?err=empty');
    exit;
}

try {
    $mysqli = get_db();

    // Join Login + Users to check both credentials and active status
    $stmt = $mysqli->prepare("
        SELECT l.UserID, l.Password, u.UserType, u.Status, u.FirstName, u.LastName
        FROM Login l
        INNER JOIN Users u ON l.UserID = u.UserID
        WHERE u.Email = ?
        LIMIT 1
    ");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $res = $stmt->get_result();
    $user = $res->fetch_assoc();
    $stmt->close();

    if (!$user) {
        // No matching email
        header('Location: ' . PROJECT_ROOT . '/login.html?err=invalid');
        exit;
    }

    // Check account status
    if (strtoupper($user['Status']) !== 'ACTIVE') {
        header('Location: ' . PROJECT_ROOT . '/login.html?err=inactive');
        exit;
    }

    // Verify password
    if (!password_verify($password, $user['Password'])) {
        header('Location: ' . PROJECT_ROOT . '/login.html?err=invalid');
        exit;
    }

    // ✅ SUCCESS: Initialize session
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['UserID'];
    $_SESSION['role'] = strtolower($user['UserType']);
    $_SESSION['first_name'] = $user['FirstName'];
    $_SESSION['last_name'] = $user['LastName'];

    // Redirect by role
    $role = strtolower($user['UserType']);
    switch ($role) {
        case 'student':
            header('Location: ' . PROJECT_ROOT . '/student_dashboard.php');
            break;
        case 'faculty':
            header('Location: ' . PROJECT_ROOT . '/faculty_dashboard.php');
            break;
        case 'admin':
            header('Location: ' . PROJECT_ROOT . '/admin_dashboard.php');
            break;
        case 'statstaff':
            header('Location: ' . PROJECT_ROOT . '/statstaff_dashboard.php');
            break;
        default:
            header('Location: ' . PROJECT_ROOT . '/login.html?err=role');
            break;
    }
    exit;

} catch (Throwable $e) {
    error_log('[LOGIN ERROR] ' . $e->getMessage());
    http_response_code(500);
    echo '<p>Server error. Please try again later.</p>';
    exit;
}
?>