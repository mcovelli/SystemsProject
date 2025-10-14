<?php
session_start();
require_once __DIR__ . '/config.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: /SystemsProject/login.html");
    exit;
}

$userId = $_SESSION['user_id'];

$mysqli = new mysqli("127.0.0.1", "root", "Marvelman190!", "University", 3306);
$mysqli->set_charset('utf8mb4');

$sql = "SELECT UserID, FirstName, LastName, Email, UserType, Status, DOB
        FROM Users WHERE UserID = ? LIMIT 1";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$res = $stmt->get_result();
$statstaff = $res->fetch_assoc();
$stmt->close();

if (!$statstaff) {
    echo "<p>Profile not found for your account.</p>";
    exit;
}
?>
<!doctype html>
<html>
<head><meta charset="utf-8"><title>StatStaff Dashboard</title></head>
<body>
  <h1>Welcome, <?php echo htmlspecialchars(
    $statstaff['FirstName'] . ' ' . $statstaff['LastName'] . ' (' . $statstaff['UserType'] . ')'); ?></h1>
  <p>Email: <?php echo htmlspecialchars($statstaff['Email']); ?></p>
  <p>Status: <?php echo htmlspecialchars($statstaff['Status']); ?></p>
  <p><a href="/SystemsProject/logout.php">Log out</a></p>
</body>
</html>