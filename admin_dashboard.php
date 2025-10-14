<?php
session_start();
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: " . PROJECT_ROOT . "/login.html");
    exit;
}

$userId = $_SESSION['user_id'];
$mysqli = get_db();

$sql = "SELECT UserID, FirstName, LastName, Email, UserType, Status, DOB
        FROM Users WHERE UserID = ? LIMIT 1";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$res = $stmt->get_result();
$admin = $res->fetch_assoc();
$stmt->close();

if (!$admin) {
    echo "<p>Profile not found for your account.</p>";
    exit;
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Admin Dashboard</title>
</head>
<body>
  <h1>Welcome, <?= htmlspecialchars($admin['FirstName'] . ' ' . $admin['LastName']) ?> (<?= htmlspecialchars($admin['UserType']) ?>)</h1>
  <p>Email: <?= htmlspecialchars($admin['Email']) ?></p>
  <p>Status: <?= htmlspecialchars($admin['Status']) ?></p>
  <p><a href="<?= PROJECT_ROOT ?>/logout.php">Log out</a></p>
</body>
</html>