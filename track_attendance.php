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

$mysqli = get_db();
$mysqli->set_charset('utf8mb4');

$userId = $_SESSION['user_id'];
$selectedCRN = $_GET['crn'] ?? null;
$selectedCourseID = null;

if ($selectedCRN) {
    $cid_sql = "SELECT CourseID FROM CourseSection WHERE CRN = ? LIMIT 1";
    $cid_stmt = $mysqli->prepare($cid_sql);
    $cid_stmt->bind_param("i", $selectedCRN);
    $cid_stmt->execute();
    $cid_result = $cid_stmt->get_result()->fetch_assoc();
    $selectedCourseID = $cid_result['CourseID'] ?? null;
    $cid_stmt->close();
}

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

// Fetch current semester
$sem_sql = "
    SELECT SemesterID, SemesterName, Year 
    FROM Semester 
    WHERE CURDATE() BETWEEN StartDate AND EndDate 
    LIMIT 1
";
$sem_stmt = $mysqli->prepare($sem_sql);
$sem_stmt->execute();
$sem_result = $sem_stmt->get_result();
$current = $sem_result->fetch_assoc();
$sem_stmt->close();

$selectedSemester = $current['SemesterID'] ?? null;

// Fetch schedule for courses taught
$schedule = [];

if ($selectedSemester !== null) {

    $courses_sql = "
        SELECT 
            cs.CRN,
            c.CourseName,
            GROUP_CONCAT(DISTINCT d.DayOfWeek ORDER BY d.DayID SEPARATOR '/') AS Days,
            DATE_FORMAT(MIN(p.StartTime), '%l:%i %p') AS StartTime,
            DATE_FORMAT(MAX(p.EndTime), '%l:%i %p') AS EndTime,
            cs.RoomID,
            cs.CourseID
        FROM CourseSection cs
        JOIN Course c ON cs.CourseID = c.CourseID
        JOIN Semester s ON cs.SemesterID = s.SemesterID
        LEFT JOIN TimeSlot ts ON cs.TimeSlotID = ts.TS_ID
        LEFT JOIN TimeSlotDay tsd ON ts.TS_ID = tsd.TS_ID
        LEFT JOIN Day d ON tsd.DayID = d.DayID
        LEFT JOIN TimeSlotPeriod tsp ON ts.TS_ID = tsp.TS_ID
        LEFT JOIN Period p ON tsp.PeriodID = p.PeriodID
        WHERE cs.FacultyID = ?
          AND cs.SemesterID = ?
        GROUP BY cs.CRN, c.CourseName, cs.RoomID
        ORDER BY cs.CRN, MIN(p.StartTime);
    ";

    $courses_stmt = $mysqli->prepare($courses_sql);
    $courses_stmt->bind_param("is", $userId, $selectedSemester);
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
            se.StudentID,
            se.CRN,
            CONCAT(u.FirstName, ' ', u.LastName) AS StudentName,
            c.CourseName,
            cs.CourseID
        FROM StudentEnrollment se
        JOIN Users u ON se.StudentID = u.UserID
        JOIN CourseSection cs ON se.CRN = cs.CRN
        JOIN Course c ON cs.CourseID = c.CourseID
        WHERE cs.FacultyID = ?
          AND cs.SemesterID = ?
          AND cs.CRN = ?
        ORDER BY u.LastName, u.FirstName;
    ";
    $roster_stmt = $mysqli->prepare($roster_sql);
    $roster_stmt->bind_param("iss", $userId, $selectedSemester, $selectedCRN);
    $roster_stmt->execute();
    $roster_result = $roster_stmt->get_result();
    $roster = $roster_result->fetch_all(MYSQLI_ASSOC);
    $roster_stmt->close();
}

$fac_stmt = $mysqli->prepare("SELECT OfficeID, Ranking FROM Faculty WHERE FacultyID = ? LIMIT 1");
$fac_stmt->bind_param('i', $userId);
$fac_stmt->execute();
$fac = $fac_stmt->get_result()->fetch_assoc();
$fac_stmt->close();
$office    = $fac['OfficeID'] ?? 'N/A';
$ranking   = $fac['Ranking'] ?? 'Faculty';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $studentIDs = $_POST['studentID'];
    $crns = $_POST['crn'];
    $courseIDs = $_POST['courseID'];
    $dates = $_POST['attendanceDate'];
    $statuses = $_POST['status'];

    $mysqli->begin_transaction();

    $sql = "INSERT INTO CourseSectionAttendance
            (StudentID, CRN, CourseID, AttendanceDate, PresentAbsent)
            VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE
        PresentAbsent = VALUES(PresentAbsent),
        AttendanceDate = VALUES(AttendanceDate)";

    $stmt = $mysqli->prepare($sql);

    for ($i = 0; $i < count($studentIDs); $i++) {
        if ($statuses[$i] === "") continue;

        $stmt->bind_param(
            "iisss",
            $studentIDs[$i],
            $crns[$i],
            $courseIDs[$i],
            $dates[$i],
            $statuses[$i]
        );

        $stmt->execute();
    }

    $mysqli->commit();

    echo "<script>alert('Attendance Submitted ✅');</script>";
}


$initials = substr($user['FirstName'], 0, 1) . substr($user['LastName'], 0, 1);
?>

<!doctype html>
<html lang="en" data-theme="light">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Attendance • Northport University</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="./stylesGrade.css" />
</head>
<body>
  <header class="topbar">
    <div class="brand">
      <div class="logo"><i data-lucide="graduation-cap"></i></div>
      <h1>Northport University</h1>
      <span class="pill">Attendance Portal</span>
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
          <div class="menu">
            <button>☰ Menu</button>
            <div class="menu-content">
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

    
       <div class="card" style="margin-top:16px; margin-left:10px; margin-right:10px">
        <div class="card-head"><div>Track Attendance</div></div>
        <div class = "card-body">
          <div class="controls" style="margin-bottom:16px; margin-left:10px; margin-right:10px">
            <div class = "label">
            <label for ="teacher-courses">
              <form method="GET">
              <div>Select Course:</div>
              <select name= "crn">
                <option value = "">---</option>
                  <?php foreach ($schedule as $row): ?>
                  <option value="<?= $row['CRN'] ?>"> <?= htmlspecialchars($row['CourseID'] . ' - ' . $row['CRN']) ?>
                  </option>
                <?php endforeach; ?>
            </select>
              </label>
            </div>
              <div class = "label" style="margin-top:16px">
            <button id = "selectButton">Choose Course</button>
        </form>
            </div>
           </div>
          </div>
        </div>
      </div>

      <div id = "attendance-chart" class="card" style="margin-top:16px; margin-left:10px; margin-right:10px">
      <div class="card-head"><div>Attendance Chart: <?= $selectedCourseID . ' - ' . $selectedCRN ?> </div></div>
        <div class="card-body">
        <div class = "table-wrap">
          <form method="POST">
          <table id = "daily-schedule">
              <?php
                $startOfWeek = strtotime('monday this week');

                  $days = [];
                  for ($i = 0; $i < 5; $i++) {
                      $timestamp = strtotime("+$i day", $startOfWeek);
                      $days[] = [
                          'label'        => date('D', $timestamp),       // Mon
                          'display'      => date('M j', $timestamp),     // Jan 27
                          'mysql'        => date('Y-m-d', $timestamp)    // 2025-01-27
                      ];
                  }
                ?>

                <thead>
                    <tr>
                        <th>Student Name</th>
                        <?php foreach ($days as $d): ?>
                            <th><?= $d['label'] ?> (<?= $d['display'] ?>)</th>
                        <?php endforeach; ?>
                    </tr>
                </thead>

                <tbody>
                  <?php foreach ($roster as $r): ?>
                      <tr>
                          <td>
                              <?= htmlspecialchars($r['StudentName']) ?>
                              <input type="hidden" name="studentID[]" value="<?= $r['StudentID'] ?>">
                              <input type="hidden" name="crn[]" value="<?= $r['CRN'] ?>">
                              <input type="hidden" name="courseID[]" value="<?= $r['CourseID'] ?>">
                          </td>

                          <?php foreach ($days as $d): ?>
                              <td>
                                  <select name="status[]">
                                      <option value="">---</option>
                                      <option value="Present">Present</option>
                                      <option value="Absent">Absent</option>
                                  </select>

                                  <input type="hidden" name="attendanceDate[]" value="<?= $d['mysql'] ?>">
                              </td>
                          <?php endforeach; ?>

                      </tr>
                  <?php endforeach; ?>
                  </tbody>
              </table>
                <button type="submit" class="btn" style="margin-top:16px;">
                  Submit Attendance
                </button>
            </form>
          </div>
        </div>
      </div>  
      </div>
    </main>
  </div>

    <footer class="footer">© <span id="year"></span> Northport University • All rights reserved</footer>

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
  </script>
</body>
</html>