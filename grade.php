<?php
session_start();
require_once __DIR__ . '/config.php';

// Allow faculty or update-admin
if (
    !isset($_SESSION['user_id']) ||
    (
        ($_SESSION['role'] ?? '') !== 'faculty' &&
        !(($_SESSION['role'] ?? '') === 'admin' && ($_SESSION['admin_type'] ?? '') === 'update')
    )
) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id'];


$mysqli = get_db();
$mysqli->set_charset('utf8mb4');

// Fetch user and faculty info
$user_stmt = $mysqli->prepare("SELECT FirstName, LastName, Email, DOB FROM Users WHERE UserID = ? LIMIT 1");
$user_stmt->bind_param('i', $userId);
$user_stmt->execute();
$user = $user_stmt->get_result()->fetch_assoc();
$user_stmt->close();

if (!$user) {
    echo "<p>Faculty member not found.</p>";
    exit;
}

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

<!doctype html>
<html lang="en" data-theme="light">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Faculty Dashboard • Northport University</title>
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
      <span class="pill">Faculty Portal</span>
    </div>
    <div class="top-actions">
      <div class="search">
        <i class="search-icon" data-lucide="search"></i>
        <input type="text" placeholder="Search courses, people, anything…" />
      </div>
      <button class="icon-btn" aria-label="Notifications"><i data-lucide="bell"></i></button>
      <button id="themeToggle" class="icon-btn" aria-label="Toggle theme"><i data-lucide="moon"></i></button>
      <div class="divider"></div>
      <div class="user">
        <div class="avatar" aria-hidden="true"><span id="initials"><?php echo $initials ?: 'NU'; ?></span></div>
        <div class="user-meta">
          <div class="name"><?php echo htmlspecialchars($user['FirstName'] . ' ' . $user['LastName']); ?></div>
          <div class="sub"><?php echo htmlspecialchars($ranking); ?></div>
        </div>
        <div class="header-left">
          <div class="dropdown">
            <button>☰ Menu</button>
            <div class="dropdown-content">
              <a href="faculty_profile.php">Profile</a>
              <a href="ViewAdvisees.php">Advisees</a>
              <a href="ViewRoster.php">Rosters</a>
              <a href="viewDirectory.php">View Directory</a>
              <a href="logout.php">Logout</a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </header>




?>