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
    $courses_stmt->bind_param("is", $userId, $selectedSemester);
    $courses_stmt->execute();
    $courses_result = $courses_stmt->get_result();
    $schedule = $courses_result->fetch_all(MYSQLI_ASSOC);
    $courses_stmt->close();
}

$fac_stmt = $mysqli->prepare("SELECT OfficeID, Ranking FROM Faculty WHERE FacultyID = ? LIMIT 1");
$fac_stmt->bind_param('i', $userId);
$fac_stmt->execute();
$fac = $fac_stmt->get_result()->fetch_assoc();
$fac_stmt->close();
$office    = $fac['OfficeID'] ?? 'N/A';
$ranking   = $fac['Ranking'] ?? 'Faculty';

$studentId = $_GET['studentID'] ?? '';
    $crn = $_GET['crn'] ?? '';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $grade = $_POST['grade'] ?? '';
    $studentId = $_POST['studentID'] ?? '';
    $crn = $_POST['crn'] ?? '';
    $courseId = $_POST['courseID'] ?? '';
    $semester = $_POST['semesterID'] ?? '';

$mysqli->begin_transaction();

  $sql = "UPDATE StudentEnrollment SET Grade = ?, Status = 'COMPLETED' WHERE StudentID=? AND CRN =?";
  $stmt = $mysqli->prepare($sql);
  $stmt->bind_param("sii", $grade, $studentId, $crn );
  $stmt->execute();
    
$sqlSH = "INSERT INTO StudentHistory (StudentID, CRN, SemesterID, Grade, CourseID) VALUES (?, ?, ?, ?, ?)";
  $stmtSH = $mysqli->prepare($sqlSH);
  $stmtSH->bind_param("iisss", $studentId, $crn, $semester, $grade, $courseId);
  $stmtSH->execute();

$mysqli->commit();
echo "<script>alert('Grade Submitted ✅');</script>";
}

$initials = substr($user['FirstName'], 0, 1) . substr($user['LastName'], 0, 1);
?>

<!doctype html>
<html lang="en" data-theme="light">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Grading • Northport University</title>
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
      <span class="pill">Grading Portal</span>
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

  <section>
    <div class="card">
        <h1>Current Semester Grades</h1>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th class="w-90">CRN</th>
                <th>Course</th>
                <th>Days</th>
                <th>Time</th>
                <th>Location</th>
              </tr>
            </thead>
            <tbody>
                <?php foreach ($schedule as $row): ?>

                <?php
                    $crn = $row['CRN'];

                    $roster_sql = "
                        SELECT 
                            u.FirstName, 
                            u.LastName,
                            se.StudentID,
                            se.Grade
                        FROM StudentEnrollment se
                        JOIN Users u ON se.StudentID = u.UserID
                        JOIN CourseSection cs ON se.CRN = cs.CRN
                        WHERE cs.FacultyID = ?
                          AND cs.SemesterID = ?
                          AND cs.CRN = ?
                        ORDER BY u.LastName, u.FirstName
                    ";
                    $stmt = $mysqli->prepare($roster_sql);
                    $stmt->bind_param("isi", $userId, $selectedSemester, $crn);
                    $stmt->execute();
                    $roster = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    $stmt->close();
                ?>

                <tr class="cs" data-crn="<?= $crn ?>">
                    <td><?= htmlspecialchars($row['CRN']) ?></td>
                    <td><?= htmlspecialchars($row['CourseName']) ?></td>
                    <td><?= htmlspecialchars($row['Days']) ?></td>
                    <td>
                        <?php 
                            $time = trim(($row['StartTime'] ?? '') . ' – ' . ($row['EndTime'] ?? ''));
                            echo htmlspecialchars($time ?: 'TBA');
                        ?>
                    </td>
                    <td><?= htmlspecialchars($row['RoomID']) ?></td>
                </tr>

                <tr id="roster-<?= $crn ?>" class="roster">
                    <td colspan="5">

                            <?php if (empty($roster)): ?>
                                No students enrolled.
                            <?php else: ?>

                            <table class="inner-roster">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Grade</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($roster as $r): ?>
                                        <?php $name = trim(($r['FirstName'] ?? '') . ' ' . ($r['LastName'] ?? '')) ?: '—'; ?>
                                        <tr>
                                            <td>
                                                <a href="student_profile.php?studentID=<?= urlencode($r['StudentID']) ?>">
                                                    <?= htmlspecialchars($name) ?>
                                                </a>
                                            </td>
                                            <td>
                                                <form method="POST">
                                                <input type="hidden" name="studentID" value="<?= $r['StudentID'] ?>">
                                                <input type="hidden" name="crn" value="<?= $crn ?>">
                                                <input type="hidden" name="courseID" value="<?= $row['CourseID']?>">
                                                <input type="hidden" name="semesterID" value="<?= $selectedSemester ?>">
                                                <select name="grade" onchange="this.form.submit()">
                                                        <option value=""><?php $grade ?></option>
                                                        <option value="A">A</option>
                                                        <option value="A-">A-</option>
                                                        <option value="B+">B+</option>
                                                        <option value="B">B</option>
                                                        <option value="B-">B-</option>
                                                        <option value="C+">C+</option>
                                                        <option value="C">C</option>
                                                        <option value="C-">C-</option>
                                                        <option value="D+">D+</option>
                                                        <option value="D">D</option>
                                                        <option value="D-">D-</option>
                                                        <option value="F">F</option>
                                                    </select>
                                                    <button type="submit">Submit</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>

                            <?php endif; ?>

                        </div>

                    </td>
                </tr>

            <?php endforeach; ?>
                </tbody>
          </table>
        </div>

  
    </section>

      <footer class="footer">
    © <span id="year"></span> Northport University • All rights reserved • <a href="#" class="link">Privacy</a>
  </footer>

  <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
  <script>
    // Create icons on load
    lucide.createIcons();
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

    // Toggle roster rows
       document.querySelectorAll(".cs").forEach(row => {
        row.addEventListener("click", () => {
            const crn = row.dataset.crn;
            const id = "roster-" + crn;
            const rosterRow = document.getElementById(id);

            console.log("CRN:", JSON.stringify(crn));
            console.log("Looking for ID:", id);
            console.log("Found row?", rosterRow);
            
            rosterRow?.classList.toggle("open");
        });
    });

</script>

</body>
</html>