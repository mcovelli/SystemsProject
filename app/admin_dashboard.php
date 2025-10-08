<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit;
}

$userId = $_SESSION['user_id'];

$mysqli = new mysqli("127.0.0.1", "phpuser", "SystemsFall2025!", "University", 3306);
$mysqli->set_charset('utf8mb4');

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
<head><meta charset="utf-8"><title>admin Dashboard</title></head>
<body>
  <h1>Welcome, <?php echo htmlspecialchars(
    $admin['FirstName'] . ' ' . $admin['LastName'] . ' (' . $admin['UserType'] . ')'); ?></h1>
  <p>Email: <?php echo htmlspecialchars($admin['Email']); ?></p>
  <p>Status: <?php echo htmlspecialchars($admin['Status']); ?></p>
  <p><a href="logout.php">Log out</a></p>
</body>
</html>