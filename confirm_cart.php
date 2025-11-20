<?php
session_start();
require_once __DIR__ . '/config.php';

// Allow student or update-admin
if (
    !isset($_SESSION['user_id']) ||
    (
        ($_SESSION['role'] ?? '') !== 'student' &&
        !(($_SESSION['role'] ?? '') === 'admin' && ($_SESSION['admin_type'] ?? '') === 'update')
    )
) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id'];
$cart = $_SESSION['cart'] ?? [];

if (empty($cart)) {
    die("Your cart is empty.");
}

$mysqli = get_db();
$mysqli->set_charset('utf8mb4');

// Prepare statements
$check = $mysqli->prepare("SELECT 1 FROM StudentEnrollment WHERE StudentID = ? AND CRN = ?");
$getCourse = $mysqli->prepare("SELECT SemesterID, CourseID, AvailableSeats FROM CourseSection WHERE CRN = ?");

$insertEnroll = $mysqli->prepare("
    INSERT INTO StudentEnrollment (StudentID, SemesterID, CRN, CourseID, Status, EnrollmentDate)
    VALUES (?, ?, ?, ?, ?, CURRENT_DATE())
");

$insertHistory = $mysqli->prepare("
    INSERT INTO StudentHistory (StudentID, CRN, SemesterID, CourseID)
    VALUES (?, ?, ?, ?)
");

$updateSeats = $mysqli->prepare("UPDATE CourseSection SET AvailableSeats = AvailableSeats - 1 WHERE CRN = ?");

$enrolled = [];
$waitlisted = [];

foreach ($cart as $item) {
    // Each $item should be an array with keys ['crn', 'courseID']
    $crn = is_array($item) ? ($item['crn'] ?? null) : $item;
    $courseId = is_array($item) ? ($item['courseID'] ?? null) : '';

    if (empty($crn) || !is_numeric($crn)) continue;

    // 1️⃣ Prevent duplicates
    $check->bind_param('ii', $userId, $crn);
    $check->execute();
    $check->store_result();
    if ($check->num_rows > 0) continue; // already enrolled

    // 2️⃣ Lookup SemesterID, CourseID, and AvailableSeats
    $getCourse->bind_param('i', $crn);
    $getCourse->execute();
    $res = $getCourse->get_result();
    $course = $res->fetch_assoc();
    if (!$course) continue;

    $semesterId = $course['SemesterID'];
    $courseId   = $courseId ?: $course['CourseID']; // fallback
    $available  = (int)$course['AvailableSeats'];

    // 3️⃣ Decide enrollment status
    if ($available > 0) {
        $status = 'ENROLLED';
        $insertEnroll->bind_param('isiss', $userId, $semesterId, $crn, $courseId, $status);
        $insertEnroll->execute();
        $updateSeats->bind_param('i', $crn);
        $updateSeats->execute();
        $enrolled[] = $crn;
    } else {
        $status = 'WAITLIST';
        $insertEnroll->bind_param('isiss', $userId, $semesterId, $crn, $courseId, $status);
        $insertEnroll->execute();
        $waitlisted[] = $crn;
    }

    // Always add to StudentHistory
    $insertHistory->bind_param('iiss', $userId, $crn, $semesterId, $courseId);
    $insertHistory->execute();
    }

// Close all
$check->close();
$getCourse->close();
$insertEnroll->close();
$insertHistory->close();
$updateSeats->close();
unset($_SESSION['cart']);

// Determine dashboard redirect
$userRole = strtolower($_SESSION['role'] ?? '');
switch ($userRole) {
    case 'student':
        $dashboard = 'student_dashboard.php';
        break;
    case 'faculty':
        $dashboard = 'faculty_dashboard.php';
        break;
    case 'admin':
        $dashboard = (($_SESSION['admin_type'] ?? '') === 'update')
            ? 'update_admin_dashboard.php'
            : 'view_admin_dashboard.php';
        break;
    case 'statstaff':
        $dashboard = 'statstaff_dashboard.php';
        break;
    default:
        $dashboard = 'login.html';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Enrollment Confirmation</title>
  <style>
    body { font-family: system-ui, sans-serif; background: #f8fafc; color: #0f172a; margin: 0; padding: 2rem; }
    .card { background: white; padding: 2rem; border-radius: 12px; max-width: 600px; margin: 2rem auto; box-shadow: 0 6px 20px rgba(0,0,0,.1); }
    a { display: inline-block; margin-top: 1rem; text-decoration: none; color: #2563eb; font-weight: 600; }
    a:hover { text-decoration: underline; }
  </style>
</head>
<body>
  <div class="card">
    <h2>Enrollment Summary</h2>

    <?php if (!empty($enrolled)): ?>
      <p><strong>Successfully Enrolled:</strong></p>
      <ul>
        <?php foreach ($enrolled as $crn): ?>
          <li>CRN <?= htmlspecialchars($crn) ?></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <?php if (!empty($waitlisted)): ?>
      <p><strong>Waitlisted:</strong></p>
      <ul>
        <?php foreach ($waitlisted as $crn): ?>
          <li>CRN <?= htmlspecialchars($crn) ?></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <?php if (empty($enrolled) && empty($waitlisted)): ?>
      <p>Not Enrolled</p>
        <ul>
            <li>CRN <?= htmlspecialchars($crn) ?></li>
        </ul>
    <?php endif; ?>

    <a href="<?= htmlspecialchars($dashboard) ?>">← Back to Dashboard</a>
  </div>
</body>
</html>