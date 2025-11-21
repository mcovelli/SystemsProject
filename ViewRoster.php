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
$facultyId = $userId;

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

// Fetch all semesters for the schedule dropdown
$sem_sql = "SELECT SemesterID, SemesterName, Year FROM Semester ORDER BY Year DESC, SemesterName DESC";
$sem_stmt = $mysqli->prepare($sem_sql);
$sem_stmt->execute();
$sem_result = $sem_stmt->get_result();
$semesters = $sem_result->fetch_all(MYSQLI_ASSOC);
$sem_stmt->close();

// Determine which semester to show for the schedule
$selectedSemester = isset($_GET['semester']) && $_GET['semester'] !== '' ? $_GET['semester'] : null;
if ($selectedSemester === null) {
    // Auto‑select current semester if available
    $auto_sql = "SELECT SemesterID FROM Semester WHERE CURDATE() BETWEEN StartDate AND EndDate LIMIT 1";
    $auto_res = $mysqli->query($auto_sql);
    if ($auto_row = $auto_res->fetch_assoc()) {
        $selectedSemester = $auto_row['SemesterID'];
    }
}

// Fetch schedule for courses taught
$schedule = [];
if ($selectedSemester) {
$courses_sql = "
      SELECT 
        cs.CRN,
        c.CourseName,
        GROUP_CONCAT(DISTINCT d.DayOfWeek ORDER BY d.DayID SEPARATOR '/') AS Days,
        DATE_FORMAT(MIN(p.StartTime), '%l:%i %p') AS StartTime,
        DATE_FORMAT(MAX(p.EndTime), '%l:%i %p')   AS EndTime,
        cs.RoomID
      FROM CourseSection cs
      JOIN Course c ON cs.CourseID = c.CourseID
      JOIN Semester s ON cs.SemesterID = s.SemesterID
      JOIN TimeSlot ts ON cs.TimeSlotID = ts.TS_ID
      JOIN TimeSlotDay tsd ON ts.TS_ID = tsd.TS_ID
      JOIN Day d ON tsd.DayID = d.DayID
      JOIN TimeSlotPeriod tsp ON ts.TS_ID = tsp.TS_ID
      JOIN Period p ON tsp.PeriodID = p.PeriodID
      WHERE cs.FacultyID = ?
        AND cs.SemesterID = ?
      GROUP BY cs.CRN, c.CourseName, cs.RoomID
      ORDER BY cs.CRN, MIN(p.StartTime);
    ";
    $courses_stmt = $mysqli->prepare($courses_sql);
    $courses_stmt->bind_param('is', $facultyId, $selectedSemester);
    $courses_stmt->execute();
    $courses_result = $courses_stmt->get_result();
    $schedule = $courses_result->fetch_all(MYSQLI_ASSOC);
    $courses_stmt->close();
}

// Fetch roster (students enrolled in faculty's sections)
$roster = [];
if ($selectedSemester) {
    $roster_sql = "
      SELECT 
        u.FirstName, 
        u.LastName, 
        c.CourseName, 
        GROUP_CONCAT(DISTINCT d.DayOfWeek ORDER BY d.DayID SEPARATOR '/') AS Days,
        DATE_FORMAT(MIN(p.StartTime), '%l:%i %p') AS StartTime,
        DATE_FORMAT(MAX(p.EndTime), '%l:%i %p')   AS EndTime,
        cs.RoomID,
        se.Grade
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
        AND cs.SemesterID = ?
      GROUP BY se.StudentID, cs.CRN, c.CourseName, cs.RoomID
      ORDER BY c.CourseName, u.LastName, u.FirstName;
    ";
    $roster_stmt = $mysqli->prepare($roster_sql);
    $roster_stmt->bind_param('is', $facultyId, $selectedSemester);
    $roster_stmt->execute();
    $roster_result = $roster_stmt->get_result();
    $roster = $roster_result->fetch_all(MYSQLI_ASSOC);
    $roster_stmt->close();
}

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


$initials = substr($user['FirstName'], 0, 1) . substr($user['LastName'], 0, 1);
?>


<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Class Roster</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="./styles.css" />
</head>
<body>
  <header class="topbar">
    <div class="brand">
      <div class="logo"><i data-lucide="graduation-cap"></i></div>
      <h1>Northport University</h1>
      <span class="pill">Class Roster</span>
    </div>
    <div class="top-actions">
      <div class="search">
        <i class="search-icon" data-lucide="search"></i>
        <input type="text" placeholder="Search courses, people, anything…" />
      </div>
      <button class="icon-btn" aria-label="Notifications"><i data-lucide="bell"></i></button>
      <button id="themeToggle" class="icon-btn" aria-label="Toggle theme"><i data-lucide="moon"></i></button>
      <div class="divider"></div>
      <div class="crumb"><a href="viewDirectory.php" aria-label="Back to Directory">← Back to Directory</a></div>
    </div>

    <div class="avatar" aria-hidden="true"><span id="initials"><?php echo $initials ?: 'NU'; ?></span></div>
        <div class="user-meta"><div class="name"><?php echo htmlspecialchars($user['UserType']) ?></div></div>
        <div class="dropdown">
          <button>☰ Menu</button>
          <div class="dropdown-content">
            <a href="<?= htmlspecialchars($dashboard) ?>">Dashboard</a>
            <a href="<?= htmlspecialchars($profile) ?>">Profile</a>
            <a href="logout.php">Logout</a>
          </div>
        </div>
      </div>
    </div>
  </header>

  <main class="page">
    <section class="hero card">
      <div class="card-head between">
        <div>
          
          <form method="get" class="semester-selector" style="margin-bottom:10px">
            <label for="semester" style="margin-right:6px">View Semester:</label>
            <select name="semester" id="semester" onchange="this.form.submit()">
              <option value="">Current Semester</option>
              <?php foreach ($semesters as $sem): ?>
                <option value="<?php echo htmlspecialchars($sem['SemesterID']); ?>" <?php echo ($selectedSemester == $sem['SemesterID']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($sem['SemesterName'] . ' ' . $sem['Year']); ?></option>
              <?php endforeach; ?>
            </select>
          </form>

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
                <th>Grade</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($roster)): ?>
                <tr><td colspan="5">No students enrolled.</td></tr>
              <?php else: ?>
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
                    $grade = $r['Grade'] ?? ' TBA ';
                  ?>
                  <tr>
                    <td><?= htmlspecialchars($name) ?></td>
                    <td><?= htmlspecialchars($course) ?></td>
                    <td><?= htmlspecialchars($days) ?></td>
                    <td><?= htmlspecialchars($timeStr) ?></td>
                    <td><?= htmlspecialchars($room) ?></td>
                    <td><?= htmlspecialchars($grade) ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

</body>
</html>