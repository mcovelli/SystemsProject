<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/config.php';

error_reporting(E_ALL);
ini_set('display_errors', '1');

if (!isset($_SESSION['user_id'])) {
    redirect(PROJECT_ROOT . "/login.html");
}

$userId = $_SESSION['user_id'];
$phone  = trim($_POST['phone'] ?? '');
$house  = trim($_POST['house'] ?? '');
$street = trim($_POST['street'] ?? '');
$city   = trim($_POST['city'] ?? '');
$state  = trim($_POST['state'] ?? '');
$zip    = trim($_POST['zip'] ?? '');
$bio    = trim($_POST['bio'] ?? '');

$mysqli = get_db();
$mysqli->set_charset('utf8mb4');

$userRole = strtolower($_SESSION['role'] ?? '');
switch ($userRole) {
    case 'student':
        $profile = 'student_profile.php';
        break;
    case 'faculty':
        $profile = 'faculty_profile.php';
        break;
    case 'admin':
        $profile = 'admin_profile.php';
        break;
    case 'statstaff':
        $profile = 'statstaff_profile.php';
        break;
    default:
        $profile = 'login.html'; // fallback
}

try {
    $sql = "UPDATE Users
            SET PhoneNumber = ?, HouseNumber = ?, Street = ?, City = ?, State = ?, ZIP = ?
            WHERE UserID = ?";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('ssssssi', $phone, $house, $street, $city, $state, $zip, $userId);
    $stmt->execute();
    $stmt->close();

    // Store bio in session
    $_SESSION['bio'] = $bio;

    header("Location: $profile");
    exit;
} catch (Exception $e) {
    echo "<h2>Profile update failed:</h2><pre>{$e->getMessage()}</pre>";
}