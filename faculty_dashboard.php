<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . PROJECT_ROOT . '/login.html');
    exit;
}

$uid = (int)$_SESSION['user_id'];

$mysqli = new mysqli("127.0.0.1", "phpuser", "SystemsFall2025!", "University", 3306);
$mysqli->set_charset('utf8mb4');

$sql = "SELECT UserID, FirstName, LastName, Email, UserType, Status, DOB
        FROM Users WHERE UserID = ? LIMIT 1";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$res = $stmt->get_result();
$faculty = $res->fetch_assoc();
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
  <h1>Welcome, <?php echo htmlspecialchars(
    $faculty['FirstName'] . ' ' . $faculty['LastName'] . ' (' . $faculty['UserType'] . ')'); ?></h1>
  <p>Email: <?php echo htmlspecialchars($faculty['Email']); ?></p>
  <p>Status: <?php echo htmlspecialchars($faculty['Status']); ?></p>
  <p><a href="logout.php">Log out</a></p>
</body>
</html>