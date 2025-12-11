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
$check = $mysqli->prepare("
    SELECT 1 
    FROM StudentEnrollment 
    WHERE StudentID = ? 
      AND CRN = ? 
      AND Status IN ('ENROLLED','IN-PROGRESS','PLANNED','WAITLIST')
");

$getCourse = $mysqli->prepare("
    SELECT SemesterID, CourseID, AvailableSeats 
    FROM CourseSection 
    WHERE CRN = ?
");

$insertEnroll = $mysqli->prepare("
    INSERT INTO StudentEnrollment (StudentID, SemesterID, CRN, CourseID, Status, EnrollmentDate)
    VALUES (?, ?, ?, ?, ?, CURRENT_DATE())
    ON DUPLICATE KEY UPDATE 
        Status = VALUES(Status),
        EnrollmentDate = VALUES(EnrollmentDate)
");

$updateSeats = $mysqli->prepare("
    UPDATE CourseSection 
    SET AvailableSeats = AvailableSeats - 1 
    WHERE CRN = ?
");

$checkPrior = $mysqli->prepare("
    SELECT 1
    FROM StudentHistory
    WHERE StudentID = ?
      AND CourseID = ?
      AND Grade IN ('A', 'A-', 'B+', 'B', 'B-', 'C+', 'C')
    LIMIT 1
");

$enrolled = [];
$waitlisted = [];
$errors = [];

foreach ($cart as $item) {

    $crn = is_array($item) ? ($item['crn'] ?? null) : $item;
    $courseIdFromCart = is_array($item) ? ($item['courseID'] ?? null) : '';

    if (empty($crn) || !is_numeric($crn)) continue;

    // Prevent duplicate enrollment
    $check->bind_param('ii', $userId, $crn);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        continue; // already enrolled or planned
    }

    // Lookup course data
    $getCourse->bind_param('i', $crn);
    $getCourse->execute();
    $course = $getCourse->get_result()->fetch_assoc();

    if (!$course) {
        $errors[] = "CRN $crn: Course not found.";
        continue;
    }

    $semesterId = $course['SemesterID'];
    $courseId = $courseIdFromCart ?: $course['CourseID'];
    $available = (int)$course['AvailableSeats'];

    $checkPrior->bind_param('ii', $userId, $courseId);
    $checkPrior->execute();
    $checkPrior->store_result();

    if ($checkPrior->num_rows > 0) {
        $errors[] = "CRN $crn: You have already completed this course with a grade of C or better.";
        continue;
    }

    // Determine status first
    if ($available > 0) {
        $status = 'ENROLLED';
    } else {
        $status = 'WAITLIST';
    }

    try {
        // Insert or update enrollment
        $insertEnroll->bind_param('isiss', $userId, $semesterId, $crn, $courseId, $status);
        $insertEnroll->execute();

        if ($status === 'ENROLLED') {
            // decrement available seats
            $updateSeats->bind_param('i', $crn);
            $updateSeats->execute();
            $enrolled[] = $crn;
        } else {
            $waitlisted[] = $crn;
        }

    } catch (mysqli_sql_exception $e) {
        $errors[] = "CRN {$crn}: " . $e->getMessage();
        continue;
    }
}

// Close statements
$check->close();
$getCourse->close();
$insertEnroll->close();
$updateSeats->close();
$checkPrior->close();
unset($_SESSION['cart']);

// Redirect dashboard
$userRole = strtolower($_SESSION['role'] ?? '');
switch ($userRole) {
    case 'student':  $dashboard = 'student_dashboard.php'; break;
    case 'faculty':  $dashboard = 'faculty_dashboard.php'; break;
    case 'admin':
        $dashboard = (($_SESSION['admin_type'] ?? '') === 'update')
                     ? 'update_admin_dashboard.php'
                     : 'view_admin_dashboard.php';
        break;
    case 'statstaff': $dashboard = 'statstaff_dashboard.php'; break;
    default: $dashboard = 'login.html';
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
    .error-box { background: #fee2e2; color: #991b1b; padding: 10px; border-radius: 6px; margin-bottom: 10px; }
    a:hover { text-decoration: underline; }
  </style>
</head>
<body>
  <div class="card">
    <h2>Enrollment Summary</h2>

    <?php if (!empty($errors)): ?>
      <div class="error-box">
        <strong>Errors:</strong>
        <ul>
          <?php foreach ($errors as $err): ?>
            <li><?= htmlspecialchars($err) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

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

    <?php if (empty($enrolled) && empty($waitlisted) && empty($errors)): ?>
      <p>No enrollment changes were made.</p>
    <?php endif; ?>

    <a href="<?= htmlspecialchars($dashboard) ?>">← Back to Dashboard</a>
  </div>
</body>
</html>