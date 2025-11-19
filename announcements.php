<?php
session_start();
require_once __DIR__ . '/config.php';
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'student') {
    redirect('login.php');
}
$userId = $_SESSION['user_id'];
$mysqli = get_db();
$mysqli->set_charset('utf8mb4');

$stmt = $mysqli->prepare("
    SELECT a.Title, a.Message, a.DatePosted, c.CourseName
    FROM CourseAnnouncements a
    JOIN CourseSection cs ON a.CRN = cs.CRN
    JOIN Course c ON cs.CourseID = c.CourseID
    JOIN StudentEnrollment se ON se.CRN = cs.CRN
    WHERE se.StudentID = ?
    ORDER BY a.DatePosted DESC
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$res = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang='en'>
<head>
  <meta charset='UTF-8'>
  <title>All Announcements</title>
  <link rel='stylesheet' href='styles.css'>
</head>
<body class='page'>
  <div class='wrap'>
    <h1>All Announcements</h1>
    <?php if ($res->num_rows > 0): ?>
      <?php while ($row = $res->fetch_assoc()): ?>
        <div class='announcement'>
          <h3><?= htmlspecialchars($row['Title']) ?> — <?= htmlspecialchars($row['CourseName']) ?></h3>
          <p><?= nl2br(htmlspecialchars($row['Message'])) ?></p>
          <small>Posted <?= htmlspecialchars($row['DatePosted']) ?></small>
        </div>
      <?php endwhile; ?>
    <?php else: ?>
      <p>No announcements available.</p>
    <?php endif; ?>
    <a href='student_dashboard.php' class='btn outline'>← Back to Dashboard</a>
  </div>
</body>
</html>