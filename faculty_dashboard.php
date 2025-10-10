<?php
declare(strict_types=1);
session_start();

/* Safer mysqli error handling to Apache log */
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/* ---- Auth & role gate ---- */
if (!isset($_SESSION['user_id'])) {
    header('Location: /SystemsProject/login.html');
    exit;
}
$uid = (int)$_SESSION['user_id'];

/* Optional but recommended: only allow faculty here */
if (isset($_SESSION['role']) && strtolower((string)$_SESSION['role']) !== 'faculty') {
    // send them to the correct dashboard if you want:
    header('Location: /SystemsProject/' . $_SESSION['role'] . '_dashboard.php');
    exit;
}

try {
    /* ---- DB ---- */
    $mysqli = new mysqli("127.0.0.1", "phpuser", "SystemsFall2025!", "University", 3306);
    $mysqli->set_charset('utf8mb4');

    $sql = "SELECT UserID, FirstName, LastName, Email, UserType, Status, DOB
            FROM Users WHERE UserID = ? LIMIT 1";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $faculty = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$faculty) {
        http_response_code(404);
        echo "<p>Profile not found for your account.</p>";
        exit;
    }
} catch (Throwable $e) {
    error_log("[FACULTY_DASH] 500: " . $e->getMessage());
    http_response_code(500);
    echo "<p>Server error. Please try again later.</p>";
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Faculty Dashboard</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>
  <h1>
    Welcome,
    <?php
      echo htmlspecialchars($faculty['FirstName'] . ' ' . $faculty['LastName'], ENT_QUOTES);
      echo ' (' . htmlspecialchars($faculty['UserType'], ENT_QUOTES) . ')';
    ?>
  </h1>
  <p>Email: <?php echo htmlspecialchars($faculty['Email'], ENT_QUOTES); ?></p>
  <p>Status: <?php echo htmlspecialchars($faculty['Status'], ENT_QUOTES); ?></p>
  <p><a href="/SystemsProject/logout.php">Log out</a></p>
</body>
</html>