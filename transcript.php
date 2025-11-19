<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['user_id'])) {
    redirect(PROJECT_ROOT . "/login.html");
}

$userId = $_SESSION['user_id'];

$mysqli = get_db();
$mysqli->set_charset('utf8mb4');

$sql = "SELECT UserID, FirstName, LastName, Email, UserType, Status, DOB, HouseNumber, Street, City, State, ZIP, PhoneNumber
        FROM Users WHERE UserID = ? LIMIT 1";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$res = $stmt->get_result();
$student = $res->fetch_assoc();
$stmt->close();

// What kind of student is this?
$stype_sql = "SELECT StudentType FROM Student WHERE StudentID = ? LIMIT 1";
$stype_stmt = $mysqli->prepare($stype_sql);
$stype_stmt->bind_param('i', $userId);
$stype_stmt->execute();
$stype = $stype_stmt->get_result()->fetch_assoc();
$stype_stmt->close();

$studentAddress = $student['HouseNumber'] . ' ' . $student['Street'];

// Determine if grad or undergrad
$isGrad = (strcasecmp($stype['StudentType'] ?? '', 'Graduate') === 0);

// Initialize defaults
$majorName = 'Undeclared';
$minorName = 'Undeclared';
$totalCreditsNeededMajor = 0;
$totalCreditsNeededMinor = 0;

if ($isGrad) {
    // Graduate: use Program table
    $prog_sql = "
      SELECT p.ProgramName, p.CreditsRequired
      FROM Graduate g
      JOIN Program p ON p.ProgramID = g.ProgramID
      WHERE g.StudentID = ?
      LIMIT 1";
    $prog_stmt = $mysqli->prepare($prog_sql);
    $prog_stmt->bind_param('i', $userId);
    $prog_stmt->execute();
    $prog = $prog_stmt->get_result()->fetch_assoc();
    $prog_stmt->close();

    if ($prog) {
        $majorName = $prog['ProgramName'] ?? 'Graduate Program';
        $totalCreditsNeededMajor = (int)($prog['CreditsRequired'] ?? 0);
    }
    $minorName = 'N/A';
} else {
    // Undergraduate: use Major/Minor tables
    $major_sql = "
      SELECT m.MajorName, m.CreditsNeeded
      FROM Major m
      JOIN StudentMajor sm ON m.MajorID = sm.MajorID
      JOIN Student s ON sm.StudentID = s.StudentID
      WHERE s.StudentID = ?
    ";
    $major_stmt = $mysqli->prepare($major_sql);
    $major_stmt->bind_param('i', $userId);
    $major_stmt->execute();
    $major = $major_stmt->get_result()->fetch_assoc();
    $major_stmt->close();

    $totalCreditsNeededMajor = (int)($major['CreditsNeeded'] ?? 0);
    $majorName = $major['MajorName'] ?? 'Undeclared';

    $minor_sql = "
      SELECT mn.MinorName, mn.CreditsNeeded
      FROM Minor mn
      JOIN StudentMinor smn ON mn.MinorID = smn.MinorID
      JOIN Student s ON smn.StudentID = s.StudentID
      WHERE s.StudentID = ?
    ";
    $minor_stmt = $mysqli->prepare($minor_sql);
    $minor_stmt->bind_param('i', $userId);
    $minor_stmt->execute();
    $minor = $minor_stmt->get_result()->fetch_assoc();
    $minor_stmt->close();

    $totalCreditsNeededMinor = (int)($minor['CreditsNeeded'] ?? 0);
    $minorName = $minor['MinorName'] ?? 'Undeclared';
}

// Fetch student's completed + in-progress courses
$courses_sql = "
  SELECT 
      s.SemesterID,
      c.CourseID,
      c.CourseName,
      c.Credits,
      se.Grade,
      se.Status
  FROM StudentEnrollment se
  JOIN CourseSection cs 
  ON se.CRN = cs.CRN 
  AND se.SemesterID = cs.SemesterID
  JOIN Course c ON cs.CourseID = c.CourseID
  JOIN Semester s ON se.SemesterID = s.SemesterID
  WHERE se.StudentID = ?
  ORDER BY s.Year DESC, s.SemesterName DESC
";
$courses_stmt = $mysqli->prepare($courses_sql);
$courses_stmt->bind_param('i', $userId);
$courses_stmt->execute();
$courses_result = $courses_stmt->get_result();
$courses = $courses_result->fetch_all(MYSQLI_ASSOC);
$courses_stmt->close();

// Degree progress summary
// Degree progress summary (fix key name)
$progress_sql = "
  SELECT Credits_Completed, Credits_Remaining, CumulativeGPA, Courses_Taken, Courses_Needed
  FROM DegreeAudit
  WHERE StudentID = ?
";
$progress_stmt = $mysqli->prepare($progress_sql);
$progress_stmt->bind_param('i', $userId);
$progress_stmt->execute();
$progress = $progress_stmt->get_result()->fetch_assoc();
$progress_stmt->close();

$gpa           = $progress['CumulativeGPA'] ?? 0.00;
$creditsEarned = (int)($progress['Credits_Completed'] ?? 0);
$coursesTaken  = $progress['Courses_Taken'] ?? null;
$coursesNeeded = $progress['Courses_Needed'] ?? NULL;

// progress & expected grad
$creditsRemaining = max($totalCreditsNeededMajor - $creditsEarned, 0);
$creditsPerTerm   = $isGrad ? 9 : 12;  // typical: 9 for grad FT, 12 for UG FT
$percent = $totalCreditsNeededMajor > 0
  ? round(($creditsEarned / $totalCreditsNeededMajor) * 100, 1)
  : 0;

$semestersRemaining = ($creditsPerTerm > 0)
  ? (int)ceil($creditsRemaining / $creditsPerTerm)
  : 0;
$currentYear = date('Y');
$currentMonth = date('n');
$isSpring = $currentMonth <= 6;
$currentTerm = $isSpring ? 'Spring' : 'Fall';

$gradYear = $currentYear;
$gradTerm = $currentTerm;

for ($i = 0; $i < $semestersRemaining; $i++) {
    if ($gradTerm === 'Spring') {
        $gradTerm = 'Fall';
    } else {
        $gradTerm = 'Spring';
        $gradYear++;
    }
}
$expectedGraduation = "$gradTerm $gradYear";

// Advisor info
$advisor_sql = "
  SELECT f.OfficeID, f.Ranking, u.FirstName, u.LastName, u.Email
  FROM Advisor a
  JOIN Faculty f ON a.FacultyID = f.FacultyID
  JOIN Users u ON f.FacultyID = u.UserID
  WHERE a.StudentID = ?
";
$advisor_stmt = $mysqli->prepare($advisor_sql);
$advisor_stmt->bind_param('i', $userId);
$advisor_stmt->execute();
$advisor = $advisor_stmt->get_result()->fetch_assoc();
$advisor_stmt->close();

if ($advisor) {
    $advisorName = trim(($advisor['Ranking'] ?? '') . ' ' . ($advisor['FirstName'] ?? '') . ' ' . ($advisor['LastName'] ?? ''));
    $advisorOffice = $advisor['OfficeID'] ?? 'N/A';
    $advisorEmail = $advisor['Email'] ?? 'N/A';
} else {
    $advisorName = 'Not Assigned';
    $advisorOffice = 'N/A';
    $advisorEmail = 'N/A';
}

$standing = ($gpa > 2.99) ? 'Good Standing' : 'Needs Improvement';


//Semester Credits
$credits_sql = "
  SELECT SUM(c.Credits) AS TotalCredits
  FROM StudentEnrollment se
  JOIN CourseSection cs ON se.CRN = cs.CRN
  JOIN Course c ON cs.CourseID = c.CourseID
  WHERE se.StudentID = ? AND se.Status = 'IN-PROGRESS'
";
$credits_stmt = $mysqli->prepare($credits_sql);
$credits_stmt->bind_param('i', $userId);
$credits_stmt->execute();
$credits_result = $credits_stmt->get_result()->fetch_assoc();
$credits_stmt->close();

$semesterCredits = (int)($credits_result['TotalCredits'] ?? 0);

if (!$student) {
    echo "<p>Profile not found for your account.</p>";
    exit;
}
?>

<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>User Profile • Northport University</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <!-- Optional Google Font (remove if you prefer system fonts) -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
  <style>
.page-layout {
  display: grid;
  grid-template-columns: 1fr 1fr; /* two equal columns */
  gap: 20px;
  width: 100%;
  max-width: 1100px;
  margin: 0 auto;
}

.top-row {
  display: contents; /* allow the two cards to occupy the grid columns */
}

.left-card {
  grid-column: 1; /* left */
  width: 100%;
}

.right-card {
  grid-column: 2; /* right */
  width: 100%;
}

.bottom-left-card {
  grid-column: 1; /* left */
  margin-top: 20px;
}
.bottom-right-card {
  grid-column: 2; /* right */
  margin-top: 20px;
}
  </style>
</head>
<body>
  <header>
    <div class="wrap topbar">
      <div class="brand">
        <div class="logo">NU</div>
        <div>Northport University</div>
      </div><br>
      <div class="top-actions">
      <a href="student_dashboard.php" title="Back to Dashboard">← Back to Dashboard</a>

      <div class="divider"></div>
    </div>
  </div>
  </header>

  <main>
    <br>
    <div class="page-layout">

      <div class="top-row">
        <div class="card left-card">
          <h2>Student Info</h2>
          <div><?php echo htmlspecialchars($student['FirstName'] . ' ' . $student['LastName']) ?></div>
          <p><div class="muted"><strong>Email: </strong><?php echo htmlspecialchars($student['Email']) ?></div></p>
          <p><div class="muted"><strong>Address: </strong><div> <?= htmlspecialchars($student['HouseNumber'] . ' ' . $student['Street']) ?><br>
          <?= htmlspecialchars($student['City'] . ', ' . $student['State'] . ' ' . $student['ZIP']) ?></div></div></p>
          <p><div class="muted"><strong>Phone:</strong> <?php echo htmlspecialchars($student['PhoneNumber']) ?></div></p>
        </div>

        <div class="card right-card">
          <h2>Academic Info</h2>
          <div class="muted"><strong>Cumulative GPA:</strong> <?php echo htmlspecialchars($gpa) ?></div>
          <div class="muted"><strong>Major:</strong> <?php echo htmlspecialchars($majorName) ?></div>
          <div class="muted"><strong>Minor:</strong> <?php echo htmlspecialchars($minorName) ?></div>
          <div class="muted"><strong>Advisor:</strong> <?php echo htmlspecialchars($advisorName) ?></div>
          <div class="muted"><strong>Standing:</strong> <?php echo htmlspecialchars($standing) ?></div>
        </div>
      </div>

      <div class="card bottom-left-card">
        <h2>Courses Taken</h2>

        <table>
            <thead>
              <tr>
                <th>Course</th>
                <th>Grade</th>
                <th>Semester</th>
              </tr>
            </thead>
            <tbody>
                <?php foreach ($courses as $row): ?>
                  <tr>
                    <td><?= htmlspecialchars($row['CourseName']) ?></td>
                    <td><?= htmlspecialchars($row['Grade'] ?? 'TBA') ?></td>
                    <td><?= htmlspecialchars($row['SemesterID'] ?? '') ?></td>
                  </tr>
                <?php endforeach; ?>
            </tbody>
          </table>

      </div>

      <div class="card bottom-right-card">
        <h2>Courses Needed</h2>

        <table>
            <thead>
              <tr>
                <th>Course</th>
              </tr>
            </thead>
              <?php
                // Convert Courses_Needed string into lines
                $neededLines = preg_split("/\r\n|\r|\n/", trim($coursesNeeded));

                // Remove header line like "--- Major Courses Still Needed ---"
                $neededLines = array_filter($neededLines, function($line) {
                    return trim($line) !== '' && strpos($line, '---') !== 0;
                });
                ?>
                <tbody>
                <?php if (!empty($neededLines)): ?>
                    <tr>
                        <td colspan="6">
                            <ul style="padding-left:20px;">
                                <?php foreach ($neededLines as $line): ?>
                                    <li><?= htmlspecialchars(trim($line)) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </td>
                    </tr>
                <?php else: ?>
                    <tr><td colspan="6">No courses needed.</td></tr>
                <?php endif; ?>
              </tbody>
          </table>

      </div>

    </div>

    <footer>© <span id="year"></span> Northport University</footer>
  </main>
</body>


<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
  <script>
    lucide.createIcons();

    // Populate the year in the footer
    document.getElementById('year').textContent = new Date().getFullYear();

  </script>


</body>
</html>
