<?php
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once __DIR__ . '/config.php';

// Clear any old session
session_unset();
session_destroy();
session_start();

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Only allow POST requests
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

    // Check credentials
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
        header('Location: ' . PROJECT_ROOT . '/login.html?err=invalid');
        exit;
    }

    if (strtoupper($user['Status']) !== 'ACTIVE') {
        header('Location: ' . PROJECT_ROOT . '/login.html?err=inactive');
        exit;
    }

    if (!password_verify($password, $user['Password'])) {
        header('Location: ' . PROJECT_ROOT . '/login.html?err=invalid');
        exit;
    }

    // ✅ SUCCESS
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['UserID'];
    $_SESSION['role'] = strtolower($user['UserType']);
    $_SESSION['first_name'] = $user['FirstName'];
    $_SESSION['last_name'] = $user['LastName'];

    switch ($_SESSION['role']) {
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
    // Show the exact error message for debugging
    echo "<pre style='color:red; font-weight:bold;'>LOGIN ERROR: " . htmlspecialchars($e->getMessage()) . "</pre>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    exit;
}
?>