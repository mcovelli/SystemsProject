<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/config.php';

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
$role = strtolower($_SESSION['role'] ?? '');

$mysqli = get_db();
$mysqli->set_charset('utf8mb4');

$usersql = "SELECT UserID, FirstName, LastName, Email, UserType, Status, DOB
        FROM Users WHERE UserID = ? LIMIT 1";
$userstmt = $mysqli->prepare($usersql);
$userstmt->bind_param("i", $userId);
$userstmt->execute();
$userres = $userstmt->get_result();
$user = $userres->fetch_assoc();
$userstmt->close();

if ($role === 'admin' && isset($_GET['facultyID']) && !empty($_GET['facultyID'])) {
    // Admin viewing a specific faculty member's roster
    $facultyId = intval($_GET['facultyID']);
} elseif ($role === 'faculty') {
    // Faculty viewing their own roster
    $facultyId = $userId;
} elseif ($role === 'admin') {
    // Admin viewing roster without specific faculty - redirect to directory
    redirect(PROJECT_ROOT . "/viewDirectory.php");
} else {
    // Not logged in as student, faculty, or admin
    redirect(PROJECT_ROOT . "/login.html");
}

$selectedCRN = $_GET['crn'] ?? null;

$days = [];
if ($selectedCRN){
    $dates_sql = "SELECT DISTINCT AttendanceDate
    FROM CourseSectionAttendance
    WHERE CRN = ? ORDER BY AttendanceDate ASC";
    $dates_stmt = $mysqli->prepare($dates_sql);
    $dates_stmt->bind_param("i", $selectedCRN);
    $dates_stmt->execute();
    $dates_res = $dates_stmt->get_result();
    while ($date_row = $dates_res->fetch_assoc()) {
        $date = $date_row['AttendanceDate'];
        $days[] = [
            'label' => date('D', strtotime($date)),
            'display' => date('m j', strtotime($date)),
            'date' => $date
        ];
    }
}

$selectedCourseID = null;
if ($selectedCRN) {
    $cid_sql = "SELECT CourseID FROM CourseSection WHERE CRN = ? LIMIT 1";
    $cid_stmt = $mysqli->prepare($cid_sql);
    $cid_stmt->bind_param("i", $selectedCRN);
    $cid_stmt->execute();
    $cid_res = $cid_stmt->get_result();
    $cid_row = $cid_res->fetch_assoc();
    $selectedCourseID = $cid_row['CourseID'];
    $cid_stmt->close();
}

$courses_sql = "SELECT cs.CRN, cs.CourseID, c.CourseName
    FROM CourseSection cs
    JOIN Course c ON cs.CourseID = c.CourseID
    WHERE cs.FacultyID = ?";
$courses_stmt = $mysqli->prepare($courses_sql);
$courses_stmt->bind_param("i", $facultyId);
$courses_stmt->execute();
$courses_res = $courses_stmt->get_result();
$courses = $courses_res->fetch_all(MYSQLI_ASSOC);
$courses_stmt->close();


$roster = [];

if ($selectedCRN){
    $roster_sql = "SELECT
    csa.AttendanceDate,
    csa.PresentAbsent,
    s.StudentID,
    CONCAT(u.FirstName, ' ', u.LastName) AS StudentName,
    cs.CRN,
    cs.CourseID,
    cs.FacultyID
    FROM CourseSectionAttendance csa
    JOIN Student s ON csa.StudentID = s.StudentID
    JOIN Users u ON s.StudentID = u.UserID
    JOIN CourseSection cs ON csa.CRN = cs.CRN
    WHERE csa.CRN = ?
    ORDER BY u.LastName, u.FirstName, csa.AttendanceDate ASC";
    $roster_stmt = $mysqli->prepare($roster_sql);
    $roster_stmt->bind_param("i", $selectedCRN);
    $roster_stmt->execute();
    $roster_res = $roster_stmt->get_result();
    $attendance = [];

    while ($row = $roster_res->fetch_assoc()) {
        $studentID = $row['StudentID'];
        $date = $row['AttendanceDate'];
        if (!isset($attendance[$studentID])) {
            $attendance[$studentID] = [
                'StudentID' => $studentID,
                'StudentName' => $row['StudentName'],
                'CRN' => $row['CRN'],
                'CourseID' => $row['CourseID'],
                'FacultyID' => $row['FacultyID'],
            ];
        }
        $attendance[$studentID][$date] = $row;
    }
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
      <span class="pill">Attendance History</span>
    </div>
    <div class="top-actions">
      <div class="search">
        <i class="search-icon" data-lucide="search"></i>
        <input type="text" placeholder="Search courses, people, anything…" />
      </div>
      <button class="icon-btn" aria-label="Notifications" a href = "announcements.php"><i data-lucide="bell"></i></button>
      <button id="themeToggle" class="icon-btn" aria-label="Toggle theme"><i data-lucide="moon"></i></button>
      <div class="divider"></div>
      <div class="crumb"><a href="viewDirectory.php" aria-label="Back to Directory">← Back to Directory</a></div>
    </div>

    <div class="avatar" aria-hidden="true"><span id="initials"><?php echo $initials ?: 'NU'; ?></span></div>
        <div class="user-meta"><div class="name"><?php echo htmlspecialchars($user['UserType']) ?></div></div>
        <div class="menu">
          <button>☰ Menu</button>
          <div class="menu-content">
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
            <?php if ($role === 'admin' && isset($_GET['facultyID'])): ?>
              <input type="hidden" name="facultyID" value="<?= htmlspecialchars($_GET['facultyID']) ?>">
            <?php endif; ?>
            <?php if ($selectedCRN): ?>
              <input type="hidden" name="crn" value="<?= htmlspecialchars($selectedCRN) ?>">
            <?php endif; ?>
            
            <?php if ($role === 'faculty'): ?>
              <label for="semester" style="margin-right:6px">View Semester:</label>
              <select name="semester" id="semester" onchange="this.form.submit()">
                <option value="">Current Semester</option>
                <?php foreach ($semesters as $sem): ?>
                  <option value="<?php echo htmlspecialchars($sem['SemesterID']); ?>" <?php echo ($selectedSemester == $sem['SemesterID']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($sem['SemesterName'] . ' ' . $sem['Year']); ?></option>
                <?php endforeach; ?>
              </select>
            <?php endif; ?>
          </form>

          <h2 class="card-title">
            View Class Roster
            <?php if ($selectedCRN): ?>
              <span style="color: #666; font-size: 0.9em;"> - CRN: <?= htmlspecialchars($selectedCRN)?></span>
            <?php endif; ?>
          </h2>
        </div>
      </div>


      <div class="card-head between" style="margin-top:24px">
          <div class="card-title">Student Roster</div>
        </div>
        <div class="table-wrap">

<?php if ($selectedCRN && !empty($roster)): ?>
  <div id="attendance-chart" class="card" style="margin-top:16px; margin-left:10px; margin-right:10px">
    <div class="card-head">
      <div>Attendance Chart: <?= htmlspecialchars($selectedCourseID . ' - CRN: ' . $selectedCRN) ?></div>
    </div>
    <div class="card-body">
      <div class="table-wrap">
        <form method="POST">
          <table id="daily-schedule">
            <thead>
              <tr>
                <th>Student Name</th>
                <?php foreach ($days as $d): ?>
                  <th><?= $d['label'] ?> (<?= $d['display'] ?>)</th>
                <?php endforeach; ?>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($attendance as $student): ?>
                <tr>
                  <td><?= htmlspecialchars($student['StudentName']) ?></td>
                    <?php foreach ($days as $d): 
                        $studentPresence = $student[$d['date']]['PresentAbsent'] ?? 'N/A';
                    ?>
                    <td><?= htmlspecialchars($studentPresence) ?></td>
                    <?php endforeach; ?>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
<footer class="footer">© <span id="year"></span> Northport University • All rights reserved</footer>
</body>
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
  <script>
    // Immediately create Lucide icons
    lucide.createIcons();

    // Populate the year in the footer
    document.getElementById('year').textContent = new Date().getFullYear();

    // Theme toggle
    const themeToggle = document.getElementById('themeToggle');
    themeToggle.addEventListener('click', () => {
      const root = document.documentElement;
      const current = root.getAttribute('data-theme') || 'light';
      root.setAttribute('data-theme', current === 'light' ? 'dark' : 'light');
      // Swap the icon
      themeToggle.querySelector('i').setAttribute('data-lucide', current === 'light' ? 'sun' : 'moon');
      lucide.createIcons();
    });

    fetch('get_semesters.php')
        .then(response => response.json())
        .then(data => {
          const semesterSelect = document.getElementById('Semester');
          const selectedSemester = new URLSearchParams(window.location.search).get('Semester');

          data.forEach(id => {
            const opt = document.createElement('option');
            opt.value = id.SemesterID;
            opt.textContent = id.SemesterID;
            if (name === selectedSemester) opt.selected = true;
            semesterSelect.appendChild(opt);
          });
        })
        .catch(err => console.error('Error loading semesters:', err));
    
  </script>
</html>