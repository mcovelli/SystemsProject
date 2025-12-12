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

// Week dates (Mon–Fri)
$startOfWeek = strtotime('monday this week');
$days = [];
for ($i = 0; $i < 5; $i++) {
    $timestamp = strtotime("+$i day", $startOfWeek);
    $days[] = [
        'label'   => date('D', $timestamp),
        'display' => date('M j', $timestamp),
        'mysql'   => date('Y-m-d', $timestamp)
    ];
}

// Fetch roster
$roster = [];
if ($selectedSemester && $selectedCRN) {
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
    $roster_stmt->bind_param("isi", $userId, $selectedSemester, $selectedCRN);
    $roster_stmt->execute();
    $roster_result = $roster_stmt->get_result();
    $roster = $roster_result->fetch_all(MYSQLI_ASSOC);
    $roster_stmt->close();
}

// Existing attendance for this week
$existingAttendance = [];
if (!empty($roster) && !empty($days) && $selectedCRN) {
    $studentIds = array_column($roster, 'StudentID');
    $weekDates  = array_column($days, 'mysql');

    $placeholdersStud  = implode(',', array_fill(0, count($studentIds), '?'));
    $placeholdersDates = implode(',', array_fill(0, count($weekDates), '?'));

    $sql_att = "
        SELECT StudentID, AttendanceDate, PresentAbsent
        FROM CourseSectionAttendance
        WHERE CRN = ?
          AND StudentID IN ($placeholdersStud)
          AND AttendanceDate IN ($placeholdersDates)
    ";

    $types     = "i" . str_repeat('i', count($studentIds)) . str_repeat('s', count($weekDates));
    $stmt_att  = $mysqli->prepare($sql_att);
    $bindVals  = array_merge([(int)$selectedCRN], $studentIds, $weekDates);

    $stmt_att->bind_param($types, ...$bindVals);
    $stmt_att->execute();
    $res_att = $stmt_att->get_result();

    while ($row = $res_att->fetch_assoc()) {
        $existingAttendance[$row['StudentID']][$row['AttendanceDate']] = $row['PresentAbsent'];
    }
    $stmt_att->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $crn       = (int)($_POST['crn'] ?? 0);
    $courseID  = $_POST['courseID'] ?? '';
    $statusMap = $_POST['status'] ?? [];

    if ($crn <= 0 || $courseID === '' || !is_array($statusMap)) {
        echo "<script>alert('Invalid submission.'); window.history.back();</script>";
        exit;
    }

    // Fetch class start time
    $timeSql = "
        SELECT MIN(p.StartTime) AS ClassStart
        FROM CourseSection cs
        JOIN TimeSlot ts ON cs.TimeSlotID = ts.TS_ID
        JOIN TimeSlotPeriod tsp ON ts.TS_ID = tsp.TS_ID
        JOIN Period p ON tsp.PeriodID = p.PeriodID
        WHERE cs.CRN = ?
    ";
    $timeStmt = $mysqli->prepare($timeSql);
    $timeStmt->bind_param('i', $crn);
    $timeStmt->execute();
    $timeRes = $timeStmt->get_result()->fetch_assoc();
    $timeStmt->close();

    if (!empty($timeRes['ClassStart'])) {
        $now        = new DateTimeImmutable('now');
        $todayStr   = $now->format('Y-m-d');
        $classStart = new DateTimeImmutable($todayStr . ' ' . $timeRes['ClassStart']);

        foreach ($statusMap as $studentID => $byDate) {
            if (!is_array($byDate)) continue;

            foreach ($byDate as $dateStr => $presentAbsent) {
                if ($presentAbsent === '') continue;

                // Block future dates (anything after today)
                if ($dateStr > $todayStr) {
                    echo "<script>alert('You cannot submit attendance for future dates.'); window.history.back();</script>";
                    exit;
                }

                // Block “today” before class start
                if ($dateStr === $todayStr && $now < $classStart) {
                    echo "<script>alert('You cannot submit attendance for today before class start time (" .
                         $classStart->format('g:i A') . ").'); window.history.back();</script>";
                    exit;
                }
            }
        }
    }

    $mysqli->begin_transaction();

    try {
        $sql = "
            INSERT INTO CourseSectionAttendance
              (StudentID, CRN, CourseID, AttendanceDate, PresentAbsent)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
              PresentAbsent = VALUES(PresentAbsent)
        ";
        $stmt = $mysqli->prepare($sql);

        foreach ($statusMap as $studentID => $byDate) {
            if (!is_array($byDate)) continue;

            $studentID = (int)$studentID;

            foreach ($byDate as $dateStr => $presentAbsent) {
                if ($presentAbsent === '') continue;

                $stmt->bind_param("iisss", $studentID, $crn, $courseID, $dateStr, $presentAbsent);
                $stmt->execute();
            }
        }

        $stmt->close();
        $mysqli->commit();

        echo "<script>alert('Attendance Submitted ✅');</script>";

    } catch (mysqli_sql_exception $e) {
        $mysqli->rollback();
        echo "<script>alert('Error saving attendance: " . htmlspecialchars($e->getMessage()) . "'); window.history.back();</script>";
        exit;
    }
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
          <div class="sub"><?php echo htmlspecialchars('Faculty'); ?></div>
        </div>
        <div class="header-left">
          <div class="menu">
            <button>☰ Menu</button>
            <div class="menu-content">
              <a href="faculty_dashboard.php">Dashboard</a>
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
    <div class="card-body">
      <div class="controls" style="margin-bottom:16px; margin-left:10px; margin-right:10px">
        <form method="GET">
          <div class="label">
            <label>
              <div>Select Course:</div>
              <select name="crn">
                <option value="">---</option>
                <?php foreach ($schedule as $row): ?>
                  <option value="<?= $row['CRN'] ?>" <?= ($selectedCRN == $row['CRN']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($row['CourseID'] . ' - ' . $row['CRN']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </label>
          </div>
          <div class="label" style="margin-top:16px">
            <button type="submit" id="selectButton">Choose Course</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <?php if ($selectedCRN && !empty($roster)): ?>
  <div id="attendance-chart" class="card" style="margin-top:16px; margin-left:10px; margin-right:10px">
    <div class="card-head">
      <div>Attendance Chart: <?= htmlspecialchars($selectedCourseID . ' - CRN: ' . $selectedCRN) ?></div>
    </div>
    <div class="card-body">
      <div class="table-wrap">
        <form method="POST">
          <!-- single hidden inputs (not per-row) -->
          <input type="hidden" name="crn" value="<?= htmlspecialchars($selectedCRN) ?>">
          <input type="hidden" name="courseID" value="<?= htmlspecialchars($selectedCourseID) ?>">

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
              <?php foreach ($roster as $r): ?>
                <tr>
                  <td><a href="student_profile.php?studentID=<?= urlencode($r['StudentID']) ?>">
                      <?= htmlspecialchars($r['StudentName']) ?> </a></td>

                  <?php foreach ($days as $d): ?>
                    <td>
                      <?php $val = $existingAttendance[$r['StudentID']][$d['mysql']] ?? ""; ?>
                      <select name="status[<?= (int)$r['StudentID'] ?>][<?= htmlspecialchars($d['mysql']) ?>]">
                        <option value="" <?= $val === "" ? "selected" : "" ?>>---</option>
                        <option value="PRESENT" <?= $val === "PRESENT" ? "selected" : "" ?>>Present</option>
                        <option value="ABSENT" <?= $val === "ABSENT" ? "selected" : "" ?>>Absent</option>
                      </select>
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
  <?php elseif ($selectedCRN && empty($roster)): ?>
    <div class="card" style="margin-top:16px; margin-left:10px; margin-right:10px">
      <div class="card-body">
        <p>No students enrolled in this course section.</p>
      </div>
    </div>
  <?php endif; ?>

  <footer class="footer">© <span id="year"></span> Northport University • All rights reserved</footer>

  <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
  <script>
    lucide.createIcons();
    document.getElementById('year').textContent = new Date().getFullYear();

    const themeToggle = document.getElementById('themeToggle');
    themeToggle.addEventListener('click', () => {
      const root = document.documentElement;
      const current = root.getAttribute('data-theme') || 'light';
      root.setAttribute('data-theme', current === 'light' ? 'dark' : 'light');
      themeToggle.querySelector('i').setAttribute('data-lucide', current === 'light' ? 'sun' : 'moon');
      lucide.createIcons();
    });
  </script>
</body>
</html>