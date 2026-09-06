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
$role = strtolower($_SESSION['role'] ?? '');

if ($role === 'admin' && isset($_GET['facultyID']) && !empty($_GET['facultyID'])) {
    // Admin viewing a specific faculty member's roster
    $facultyId = intval($_GET['facultyID']);
} elseif ($role === 'faculty') {
    // Faculty viewing their own roster
    $facultyId = $userId;
} elseif ($role === 'admin') {
    // Admin viewing roster without specific faculty - redirect to directory
    redirect('viewDirectory.php');
} else {
    // Not logged in as faculty or admin
    redirect('login.php');
}

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

$selectedCRN = isset($_GET['crn']) && $_GET['crn'] !== '' ? $_GET['crn'] : null;

// If a CRN is provided, get its semester from the database
if ($selectedCRN) {
    $crn_sem_sql = "SELECT SemesterID FROM CourseSection WHERE CRN = ? LIMIT 1";
    $crn_sem_stmt = $mysqli->prepare($crn_sem_sql);
    $crn_sem_stmt->bind_param('i', $selectedCRN);
    $crn_sem_stmt->execute();
    $crn_sem_result = $crn_sem_stmt->get_result();
    if ($crn_sem_row = $crn_sem_result->fetch_assoc()) {
        $selectedSemester = $crn_sem_row['SemesterID'];
    }
    $crn_sem_stmt->close();
} else {
    // Only use dropdown/current semester if no CRN is specified
    $selectedSemester = isset($_GET['semester']) && $_GET['semester'] !== '' ? $_GET['semester'] : null;
    if ($selectedSemester === null) {
        // Auto‑select current semester if available
        $auto_sql = "SELECT SemesterID FROM Semester WHERE CURDATE() BETWEEN StartDate AND EndDate LIMIT 1";
        $auto_res = $mysqli->query($auto_sql);
        if ($auto_row = $auto_res->fetch_assoc()) {
            $selectedSemester = $auto_row['SemesterID'];
        }
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
$whereClauses = ["cs.FacultyID = ?"];

$params = [$facultyId];
$types = "i";

if ($selectedSemester) {
    $whereClauses[] = "cs.SemesterID = ?";
    $params[] = $selectedSemester;
    $types .= "s";
}

if ($selectedCRN) {
    $whereClauses[] = "cs.CRN = ?";
    $params[] = $selectedCRN;
    $types .= "i";
}

$whereClause = implode(" AND ", $whereClauses);

$roster_sql = "
  SELECT 
    u.FirstName, 
    u.LastName, 
    c.CourseName,
    cs.CRN,
    GROUP_CONCAT(DISTINCT d.DayOfWeek ORDER BY d.DayID SEPARATOR '/') AS Days,
    DATE_FORMAT(MIN(p.StartTime), '%l:%i %p') AS StartTime,
    DATE_FORMAT(MAX(p.EndTime), '%l:%i %p')   AS EndTime,
    cs.RoomID,
    se.Grade,
    se.StudentID
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
  WHERE $whereClause
  GROUP BY se.StudentID, se.SemesterID, se.CRN, c.CourseName, cs.RoomID
  ORDER BY c.CourseName, u.LastName, u.FirstName;
";

$roster_stmt = $mysqli->prepare($roster_sql);
$roster_stmt->bind_param($types, ...$params);
$roster_stmt->execute();

if ($roster_stmt->error) {
    echo "SQL Error: " . $roster_stmt->error . "<br>";
}

$roster_result = $roster_stmt->get_result();
$roster = $roster_result->fetch_all(MYSQLI_ASSOC);
$roster_stmt->close();



?>


<!DOCTYPE html>
<html lang="en">
<?php $nu_title = 'Class Roster'; require __DIR__ . '/partials/head.php'; ?>
<body>
  <?php $nu_page = 'Class Roster'; $nu_crumb = ['viewDirectory.php', '← Back to Directory']; require __DIR__ . '/partials/header.php'; ?>

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
                    <td><a href="student_profile.php?studentID=<?= urlencode($r['StudentID']) ?>">
                      <?= htmlspecialchars($name) ?> </a></td>
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
<?php require __DIR__ . '/partials/footer.php'; ?>
</body>
  <script>
    // Immediately create Lucide icons
    lucide.createIcons();

    // Populate the year in the footer
    document.getElementById('year').textContent = new Date().getFullYear();

  </script>
</html>