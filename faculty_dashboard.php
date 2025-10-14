<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . PROJECT_ROOT . '/login.html');
    exit;
}

$uid = (int)$_SESSION['user_id'];

if (isset($_SESSION['role']) && strtolower($_SESSION['role']) !== 'faculty') {
    header('Location: ' . PROJECT_ROOT . '/' . $_SESSION['role'] . '_dashboard.php');
    exit;
}

try {
    $mysqli = get_db();
    $stmt = $mysqli->prepare("SELECT UserID, FirstName, LastName, Email, UserType, Status, DOB
                              FROM Users WHERE UserID = ? LIMIT 1");
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $faculty = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$faculty) {
        echo "<p>Profile not found.</p>";
        exit;
    }
} catch (Throwable $e) {
    error_log("[FACULTY_DASH] " . $e->getMessage());
    http_response_code(500);
    echo "<p>Server error.</p>";
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Faculty Dashboard</title>
</head>
<body>
  <h1>Welcome, <?= htmlspecialchars($faculty['FirstName'] . ' ' . $faculty['LastName']) ?> (<?= htmlspecialchars($faculty['UserType']) ?>)</h1>
  <p>Email: <?= htmlspecialchars($faculty['Email']) ?></p>
  <p>Status: <?= htmlspecialchars($faculty['Status']) ?></p>
  <p><a href="<?= PROJECT_ROOT ?>/logout.php">Log out</a></p>
</body>
</html>