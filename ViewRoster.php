<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/config.php';

if (
    !isset($_SESSION['user_id']) || (
        ($_SESSION['role'] ?? '') !== 'faculty' &&
            ($_SESSION['role'] ?? '') !== 'admin')
    )
{
    redirect(PROJECT_ROOT . "/login.html");
}

$userId = $_SESSION['user_id'];

$mysqli = get_db();
$mysqli->set_charset('utf8mb4');

$sql = "SELECT UserID, FirstName, LastName, Email, UserType, Status, DOB
        FROM Users WHERE UserID = ? LIMIT 1";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$res = $stmt->get_result();
$user = $res->fetch_assoc();
$stmt->close();

$roster_sql = "
  SELECT 
    u.FirstName, u.LastName, se.StudentID,
    c.CourseName, 
    GROUP_CONCAT(DISTINCT d.DayOfWeek ORDER BY d.DayID SEPARATOR '/') AS Days,
    DATE_FORMAT(MIN(p.StartTime), '%l:%i %p') AS StartTime,
    DATE_FORMAT(MAX(p.EndTime), '%l:%i %p')   AS EndTime,
    cs.RoomID
  FROM StudentEnrollment se
  JOIN Users u ON se.StudentID = u.UserID
  JOIN CourseSection cs ON se.CRN = cs.CRN
  JOIN Course c ON cs.CourseID = c.CourseID
  JOIN Semester s ON cs.SemesterID = s.SemesterID
  JOIN TimeSlot ts ON cs.TimeSlotID = ts.TS_ID
  JOIN TimeSlotDay tsd ON ts.TS_ID = tsd.TS_ID
  JOIN Day d ON tsd.DayID = d.DayID
  JOIN TimeSlotPeriod tsp ON ts.TS_ID = tsp.TS_ID
  JOIN Period p ON tsp.PeriodID = p.PeriodID
  WHERE cs.FacultyID = ?
    AND CURRENT_DATE() BETWEEN s.StartDate AND s.EndDate
  GROUP BY se.StudentID, cs.CRN, c.CourseName, cs.RoomID
  ORDER BY c.CourseName, u.LastName, u.FirstName;
";
$roster_stmt = $mysqli->prepare($roster_sql);
$roster_stmt->bind_param('i', $userId);
$roster_stmt->execute();
$roster_result = $roster_stmt->get_result();
$roster = $roster_result->fetch_all(MYSQLI_ASSOC);
$roster_stmt->close();

$userRole = strtolower($_SESSION['role'] ?? '');
switch ($userRole) {
    case 'faculty':
        $dashboard = 'faculty_dashboard.php';
        break;
    case 'admin':
        // if you have update/view admin types:
        if (($_SESSION['admin_type'] ?? '') === 'update') {
            $dashboard = 'update_admin_dashboard.php';
        } else {
            $dashboard = 'view_admin_dashboard.php';
        }
        break;
    default:
        $dashboard = 'login.html'; // fallback
}


?>

<!doctype html>
<html lang="en" data-theme="light">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Northport University — View Class Roster</title>
  <link rel="stylesheet" href="./viewstyles.css" />
</head>
<body>
  <header class="topbar">
    <div class="brand">
      <div class="logo">NU</div>
      <h1>Northport University</h1>
    </div>
    <div class="top-actions">
      <div class="search" style="width: min(360px, 40vw)">
        <i class="search-icon" data-lucide="search"></i>
        <input id="q" type="text" placeholder="Search code, title, instructor…" />
      </div>
      <button class="btn outline" id="themeToggle" title="Toggle theme">🌙</button>
      <div class="crumb">
        <a href="<?= htmlspecialchars($dashboard) ?>" aria-label="Back to Dashboard">← Back to Dashboard</a>
      </div>
  </header>

  <main class="page">
    <section class="hero card">
      <div class="card-head between">
        <div>
          <h2 class="card-title">View Class Roster</h2>
        </div>
      </div>


      <div class="card-head between" style="margin-top:24px">
          <div class="card-title">Student Roster</div>
        </div>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Student Name</th>
                <th>Course</th>
                <th>Days</th>
                <th>Time</th>
                <th>Location</th>
              </tr>
            </thead>
            <tbody>
              <tbody id="adviseesBody">
              <?php if (!empty($roster)): ?>
                <?php foreach ($roster as $r): ?>
                  <?php
                    $name = trim(($r['FirstName'] ?? '') . ' ' . ($r['LastName'] ?? '')) ?: '—';
                    $course = $r['CourseName'] ?? ' — ';
                    $days = $r['Days'] ?? ' — ';
                    $start = $r['StartTime'] ?? '';
                    $end   = $r['EndTime'] ?? '';
                    $timeStr = trim($start . ($start && $end ? ' – ' : '') . $end);
                    if ($timeStr === '') $timeStr = 'TBA';
                    $room = $r['RoomID'] ?? ' — ';
                  ?>
                  <tr>
                    <td><a href="student_profile.php?studentID=<?= urlencode($r['StudentID']) ?>">
                      <?= htmlspecialchars($name) ?> </a></td>
                    <td><?= htmlspecialchars($course) ?></td>
                    <td><?= htmlspecialchars($days) ?></td>
                    <td><?= htmlspecialchars($timeStr) ?></td>
                    <td><?= htmlspecialchars($room) ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr><td colspan="6">No roster found.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

</body>
</html>