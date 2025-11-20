<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/config.php';

$mysqli = get_db();
$mysqli->set_charset('utf8mb4');

$userID = $_SESSION['user_id'];

$role = strtolower($_SESSION['role'] ?? '');

// If not logged in
if (!$role) {
    redirect('login.php');
}

// ADMIN → viewing any faculty profile
if ($role === 'admin') {
    if (isset($_GET['studentID'])) {
        $studentID = intval($_GET['studentID']);
    } else {
        redirect('student_profile.php'); // Or wherever you want admin to go
    }
}
// FACULTY → always view their own profile
elseif ($role === 'student') {
    $studentID = $_SESSION['user_id'];
}
// ANYONE ELSE → no access
else {
    redirect('login.php');
}

$mysqli = get_db();
$mysqli->set_charset('utf8mb4');

$sql = "SELECT UserID, FirstName, LastName, Email, UserType, Status, DOB, HouseNumber, Street, City, State, ZIP, PhoneNumber
        FROM Users WHERE UserID = ? LIMIT 1";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param("i", $studentID);
$stmt->execute();
$res = $stmt->get_result();
$student = $res->fetch_assoc();
$stmt->close();

// What kind of student is this?
$stype_sql = "SELECT StudentType FROM Student WHERE StudentID = ? LIMIT 1";
$stype_stmt = $mysqli->prepare($stype_sql);
$stype_stmt->bind_param('i', $studentID);
$stype_stmt->execute();
$stype = $stype_stmt->get_result()->fetch_assoc();
$stype_stmt->close();

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
    $prog_stmt->bind_param('i', $studentID);
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
    $major_stmt->bind_param('i', $studentID);
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
    $minor_stmt->bind_param('i', $studentID);
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
$courses_stmt->bind_param('i', $studentID);
$courses_stmt->execute();
$courses_result = $courses_stmt->get_result();
$courses = $courses_result->fetch_all(MYSQLI_ASSOC);
$courses_stmt->close();

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

// Fetch schedule entries for the selected semester
$schedule = [];
if ($selectedSemester) {
    $sched_sql = "
        SELECT 
            se.CRN,
            c.CourseName,
            GROUP_CONCAT(DISTINCT d.DayOfWeek ORDER BY d.DayID SEPARATOR '/') AS Days,
            MIN(DATE_FORMAT(p.StartTime, '%l:%i %p')) AS StartTime,
            MAX(DATE_FORMAT(p.EndTime, '%l:%i %p'))   AS EndTime,
            cs.RoomID, se.Grade,
            CONCAT(fu.FirstName, ' ', fu.LastName) AS Professor
        FROM StudentEnrollment se
        JOIN CourseSection cs ON se.CRN = cs.CRN
        JOIN Users fu ON cs.FacultyID = fu.UserID
        JOIN Course c ON cs.CourseID = c.CourseID
        JOIN TimeSlot ts ON cs.TimeSlotID = ts.TS_ID
        JOIN TimeSlotPeriod tsp ON ts.TS_ID = tsp.TS_ID
        JOIN TimeSlotDay tsd ON ts.TS_ID = tsd.TS_ID
        JOIN Period p ON tsp.PeriodID = p.PeriodID
        JOIN Day d ON tsd.DayID = d.DayID
        WHERE se.StudentID = ? 
          AND se.SemesterID = ?
        GROUP BY se.CRN, c.CourseName, cs.RoomID
        ORDER BY MIN(p.StartTime)
    ";
    $sched_stmt = $mysqli->prepare($sched_sql);
    $sched_stmt->bind_param('is', $studentID, $selectedSemester);
    $sched_stmt->execute();
    $sched_result = $sched_stmt->get_result();
    $schedule = $sched_result->fetch_all(MYSQLI_ASSOC);
    $sched_stmt->close();
}

// Degree progress summary
// Degree progress summary (fix key name)
$progress_sql = "
  SELECT Credits_Completed, Credits_Remaining, CumulativeGPA, Courses_Taken, Courses_Needed
  FROM DegreeAudit
  WHERE StudentID = ?
";
$progress_stmt = $mysqli->prepare($progress_sql);
$progress_stmt->bind_param('i', $studentID);
$progress_stmt->execute();
$progress = $progress_stmt->get_result()->fetch_assoc();
$progress_stmt->close();

$gpa           = $progress['CumulativeGPA'] ?? 0.00;
$creditsEarned = (int)($progress['Credits_Completed'] ?? 0);
$coursesTaken  = $progress['Courses_Taken'] ?? null;  // <-- was 'CoursesTaken'

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
$advisor_stmt->bind_param('i', $studentID);
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
$credits_stmt->bind_param('i', $studentID);
$credits_stmt->execute();
$credits_result = $credits_stmt->get_result()->fetch_assoc();
$credits_stmt->close();

$semesterCredits = (int)($credits_result['TotalCredits'] ?? 0);

// Determine back dashboard
$userRole = strtolower($_SESSION['role'] ?? '');
switch ($userRole) {
    case 'student':  $dashboard = 'student_dashboard.php'; break;
    case 'faculty':  $dashboard = 'faculty_dashboard.php'; break;
    case 'admin':
        $dashboard = ($_SESSION['admin_type'] ?? '') === 'update'
            ? 'update_admin_dashboard.php'
            : 'view_admin_dashboard.php';
        break;
    default: $dashboard = 'login.php';
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
    :root{
      --bg:#f7f8fb; --card:#ffffff; --text:#1f2937; --muted:#6b7280;
      --primary:#0b1d39; --accent:#4f46e5; --line:#e5e7eb;
      --radius:14px;
    }
    *{box-sizing:border-box}
    body{
      margin:0; font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;
      background:var(--bg); color:var(--text);
    }
    header{
      position:sticky; top:0; z-index:10;
      background:var(--card); border-bottom:1px solid var(--line);
    }
    .wrap{max-width:1100px; margin:0 auto; padding:18px 16px;}
    .topbar{display:flex; align-items:center; gap:12px; justify-content:space-between}
    .brand{display:flex; align-items:center; gap:10px; font-weight:700; color:var(--primary)}
    .brand .logo{width:36px; height:36px; border-radius:50%; background:var(--primary); display:grid; place-items:center; color:#fff; font-size:14px}
    .top-actions a{display:inline-flex; align-items:center; gap:8px; text-decoration:none; background:var(--primary); color:#fff; padding:10px 14px; border-radius:10px}
    main .grid{display:grid; grid-template-columns:320px 1fr; gap:20px; padding:24px 16px}
    @media (max-width:880px){ main .grid{grid-template-columns:1fr} }
    .card{
      background:var(--card); border:1px solid var(--line); border-radius:var(--radius);
      box-shadow:0 1px 2px rgba(0,0,0,.03); padding:18px;
    }
    .profile{
      display:flex; flex-direction:column; align-items:center; text-align:center; gap:12px
    }
    .avatar{
      width:120px; height:120px; border-radius:50%; object-fit:cover; border:3px solid var(--accent);
      background:#ddd;
    }
    .name{font-size:1.3rem; font-weight:700}
    .muted{color:var(--muted)}
    .chips{display:flex; flex-wrap:wrap; gap:8px; justify-content:center}
    .chip{padding:6px 10px; border-radius:999px; background:#f2f4f7; border:1px solid var(--line); font-size:.9rem}
    .btn-row{display:flex; gap:10px; flex-wrap:wrap; justify-content:center}
    .btn{border:1px solid var(--line); background:#fff; padding:10px 12px; border-radius:10px; cursor:pointer}
    .btn.primary{background:var(--accent); color:#fff; border-color:var(--accent)}
    .section{display:grid; gap:14px}
    .section h2{margin:0; font-size:1.05rem}
    .kv{display:grid; grid-template-columns:180px 1fr; gap:8px; padding:10px 0; border-bottom:1px dashed var(--line)}
    .kv:last-child{border-bottom:0}
    /* --- CONTACT INFO FIX --- */
    .section .kv div:last-child {
      word-break: break-word;         /* allow breaking long words or emails */
      overflow-wrap: anywhere;        /* force wrap for long strings */
      white-space: normal;            /* allow wrapping on small screens */
      max-width: 100%;                /* prevent overflow beyond card edge */
    }

    .section .kv {
      align-items: start;             /* aligns label and value top-aligned */
      gap: 12px;                      /* add breathing room */
    }

    .section .label {
      font-weight: 600;
      color: var(--muted);
      min-width: 100px;               /* keeps labels consistent width */
      word-wrap: normal;
    }

    #address, #email, #phone {
      display: block;
      line-height: 1.4;
    }

    @media (max-width: 600px) {
      .kv {
        grid-template-columns: 1fr;   /* stack label/value on mobile */
      }
      .section .label {
        min-width: auto;
      }
    }
    .label{color:var(--muted)}
    .two{display:grid; grid-template-columns:1fr 1fr; gap:14px}
    @media (max-width:620px){ .kv{grid-template-columns:1fr} .two{grid-template-columns:1fr} }
    .links a{display:inline-block; margin-right:10px; color:var(--primary); text-decoration:none}
    .links a:hover{text-decoration:underline}
    footer{padding:24px 16px; text-align:center; color:var(--muted)}

    /* Overlay background */
    .popup-overlay {
      position: fixed;
      inset: 0;
      background: rgba(0, 0, 0, 0.4);
      backdrop-filter: blur(4px);
      display: none;
      justify-content: center;
      align-items: center;
      z-index: 9999;
    }

    /* Card style popup */
    .popup-card {
      background: var(--panel, #fff);
      color: var(--text, #111);
      padding: 24px 28px;
      border-radius: 14px;
      width: 90%;
      max-width: 480px;
      box-shadow: 0 12px 28px rgba(0, 0, 0, 0.25);
      animation: slideUp 0.25s ease-out;
      position: relative;
    }

    /* Fade/slide animation */
    @keyframes slideUp {
      from { opacity: 0; transform: translateY(40px); }
      to { opacity: 1; transform: translateY(0); }
    }

    /* Close button */
    .popup-card .close-btn {
      position: absolute;
      top: 10px;
      right: 16px;
      font-size: 22px;
      color: var(--muted, #777);
      cursor: pointer;
      transition: color 0.2s ease;
    }
    .popup-card .close-btn:hover {
      color: var(--primary, #4f46e5);
    }

    /* Form layout */
    .popup-card .form-grid {
      display: grid;
      gap: 14px;
      margin-top: 20px;
    }
    .popup-card .form-grid label {
      display: flex;
      flex-direction: column;
      text-align: left;
      font-size: 14px;
      color: var(--muted, #666);
    }
    .popup-card .form-grid input,
    .popup-card .form-grid textarea {
      margin-top: 6px;
      padding: 8px 10px;
      border-radius: 8px;
      border: 1px solid var(--border, #ccc);
      background: var(--panel, #fff);
      color: var(--text, #111);
      font-size: 14px;
    }
    .popup-card .form-grid input:focus,
    .popup-card .form-grid textarea:focus {
      outline: 2px solid var(--primary, #4f46e5);
    }

  </style>
</head>
<body>
  <header>
    <div class="wrap topbar">
      <div class="brand">
        <div class="logo">NU</div>
        <div>Northport University</div>
      </div>
      <div class="top-actions">
        <a href="<?= htmlspecialchars($dashboard) ?>">← Back to Dashboard</a>
      </div>
    </div>
  </header>


  <!-- Edit Profile Popup -->
<?php if ($userRole === 'student'): ?>
  <div id="editProfilePopup" class="popup-overlay">
    <div class="popup-card">
      <span class="close-btn" onclick="closePopup()">&times;</span>
      <h2>Edit Profile</h2>

      <form id="editProfileForm" method="post" action="update_profile.php" class="form-grid">
        <label>Phone Number
          <input type="text" name="phone" value="<?= htmlspecialchars($student['PhoneNumber'] ?? '') ?>" required>
        </label>

        <label>House Number
          <input type="text" name="house" value="<?= htmlspecialchars($student['HouseNumber'] ?? '') ?>">
        </label>

        <label>Street
          <input type="text" name="street" value="<?= htmlspecialchars($student['Street'] ?? '') ?>">
        </label>

        <label>City
          <input type="text" name="city" value="<?= htmlspecialchars($student['City'] ?? '') ?>">
        </label>

        <label>State
          <input type="text" name="state" value="<?= htmlspecialchars($student['State'] ?? '') ?>">
        </label>

        <label>ZIP
          <input type="text" name="zip" value="<?= htmlspecialchars($student['ZIP'] ?? '') ?>">
        </label>

        <label>Bio
          <textarea name="bio" rows="3"><?= htmlspecialchars($_SESSION['bio'] ?? '') ?></textarea>
        </label>


        <div class="btn-row">
          <button type="submit" class="btn primary">Save Changes</button>
          <button type="button" class="btn outline" onclick="closePopup()">Cancel</button>
        </div>
      </form>
    </div>
  </div>
<?php endif; ?>

  <main>
    <?php if (isset($_GET['saved'])): ?>
      <div style="background:#d1fae5;color:#065f46;padding:10px;border-radius:8px;margin:10px 0;text-align:center;">
        ✅ Profile updated successfully!
      </div>
    <?php endif; ?>

    <div class="wrap grid">
      <!-- LEFT: Profile Card -->
      <aside class="card profile">
        <!-- Replace with student photo or dynamic src in PHP -->
        <img class="avatar" src="N/A" alt="User photo">
        <div class="name" id="studentName"><?php echo htmlspecialchars(
    $student['FirstName'] . ' ' . $student['LastName']); ?></div>
        <div class="muted" id="studentId">Student ID: <?php echo htmlspecialchars(
    $student['UserID']); ?></div>
        <div class="chips">
          <span class="chip" id="majorChip"><?= htmlspecialchars($majorName ?? 'Undeclared') ?></span>
          <span class="chip" id="classYearChip">Class of <?= htmlspecialchars($gradYear) ?></span>
          <span class="chip" id="gpaChip">GPA: <?= htmlspecialchars($gpa) ?></span>
        </div>
      <?php if ($userRole === 'student'): ?>
        <div class="btn-row">
          <button class="btn primary" id="editProfileBtn" onclick="openPopup()">Edit Profile</button>
          <button class="btn" id="changePhotoBtn">Change Photo</button>
        </div>
      <?php endif; ?>
        <div class="section" style="width:100%; margin-top:10px">
      <h2>Contact</h2>

      <div class="kv">
        <div class="label">Email</div>
        <div id="email"><?= htmlspecialchars($student['Email']) ?></div>
      </div>

      <div class="kv">
        <div class="label">Phone</div>
        <div id="phone"><?= htmlspecialchars($student['PhoneNumber']) ?></div>
      </div>

      <div class="kv">
        <div class="label">Address</div>
        <div id="address">
          <?= htmlspecialchars($student['HouseNumber'] . ' ' . $student['Street']) ?><br>
          <?= htmlspecialchars($student['City'] . ', ' . $student['State'] . ' ' . $student['ZIP']) ?>
        </div>
      </div>
    </div>
      </aside>

      <!-- RIGHT: Details -->
      <section class="card">
        <div class="section">
          <h2>Academic Information</h2>
          <div class="two">
            <?php if ($isGrad): ?>
              <div class="kv">
                <div class="label">Program</div>
                <div id="program"><?= htmlspecialchars($majorName ?? 'Graduate Program') ?></div>
              </div>
              <div class="kv">
                <div class="label">Minor</div>
                <div id="minor">N/A</div>
              </div>
            <?php else: ?>
              <div class="kv">
                <div class="label">Major</div>
                <div id="major"><?= htmlspecialchars($majorName ?? 'Undeclared') ?></div>
              </div>
              <div class="kv">
                <div class="label">Minor</div>
                <div id="minor"><?= htmlspecialchars($minorName ?? 'Undeclared') ?></div>
              </div>
            <?php endif; ?>

            <div class="kv">
              <div class="label">Advisor</div>
              <div id="advisor"><?= htmlspecialchars($advisorName ?? 'Not Assigned') ?></div>
            </div>

            <div class="kv"><div class="label">Standing</div><div id="standing"><?php echo htmlspecialchars($standing); ?></div></div>
            <div class="kv"><div class="label">Credits Completed</div><div id="credits"><?= $creditsEarned ?>/<?= $totalCreditsNeededMajor ?> completed</div></div>
            <div class="kv"><div class="label">Expected Graduation</div><div id="gradDate"><?= htmlspecialchars($expectedGraduation) ?></div></div>
          </div>
        </div>

        <div class="section">
          <h2>Bio</h2>
          <div class="kv">
            <div class="label">About</div>
            <div id="about">
              <?= htmlspecialchars($_SESSION['bio'] ?? 'No bio added yet.') ?>
            </div>
          </div>
        </div>

        <?php if ($userRole === 'student'): ?>
          <div class="section">
            <h2>Links</h2>
            <div class="links" id="links">
              <a href="transcript.php">Transcript</a>
              <a href="degree_audit.php">Degree Audit</a>
              <a href="inbox.php">Messages</a>
              <a href="bursar.php">Billing</a>
            </div>
          </div>
        <?php endif; ?>
        <br>
      <div class="card">
        <div class="card-head between">
          <div class="card-title">Semester Schedule</div>
          <div class="row gap">
            <button class="btn outline"><i data-lucide="calendar-days"></i> Open Calendar</button>
            <button class="btn">Add Event</button>
          </div>
        </div>
        <div class="table-wrap">
          <form method="get" class="semester-selector" style="margin-bottom:10px">
            <?php if (isset($_GET['studentID'])): ?>
                <input type="hidden" name="studentID" value="<?= htmlspecialchars($_GET['studentID']) ?>">
            <?php endif; ?>

            <label for="semester" style="margin-right:6px">View Semester:</label>
            <select name="semester" id="semester" onchange="this.form.submit()">
              <option value="">Current Semester</option>
              <?php foreach ($semesters as $sem): ?>
                <option value="<?php echo htmlspecialchars($sem['SemesterID']); ?>" <?php echo ($selectedSemester == $sem['SemesterID']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($sem['SemesterName'] . ' ' . $sem['Year']); ?></option>
              <?php endforeach; ?>
            </select>
          </form>
          <table>
            <thead>
              <tr>
                <th class="w-90">CRN</th>
                <th>Course</th>
                <th>Days</th>
                <th>Time</th>
                <th>Location</th>
                <th>Professor</th>
                <th>Grade</th>
              </tr>
            </thead>
            <tbody id="studentScheduleBody">
              <?php if (empty($schedule)): ?>
                <tr><td colspan="5">No courses scheduled for this semester.</td></tr>
              <?php else: ?>
                <?php foreach ($schedule as $row): ?>
                  <tr>
                    <td><?= htmlspecialchars($row['CRN']) ?></td>
                    <td><?= htmlspecialchars($row['CourseName']) ?></td>

                    <?php
                      // Handle combined days like "Mon/Wed" or "Tue/Thu"
                      $dayStr = (string)($row['DayOfWeek'] ?? $row['Days'] ?? '');
                      $dayStr = $dayStr === '' ? '—' : $dayStr;
                    ?>
                    <td><?= htmlspecialchars($dayStr) ?></td>

                    <?php
                      // Handle time display
                      $start = $row['StartTime'] ?? '';
                      $end   = $row['EndTime']   ?? '';
                      $timeStr = trim($start . ($start && $end ? ' – ' : '') . $end);
                      $timeStr = $timeStr === '' ? 'TBA' : $timeStr;
                    ?>
                    <td><?= htmlspecialchars($timeStr) ?></td>

                    <td><?= htmlspecialchars($row['RoomID'] ?? 'TBA') ?></td>
                    <td><?= htmlspecialchars($row['Professor'] ?? 'TBA') ?></td>
                    <td><?= htmlspecialchars($row['Grade'] ?? 'TBA') ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      </section>
    </div>

    <footer>© <span id="year"></span> Northport University</footer>
  </main>

  <script>
    document.getElementById('year').textContent = new Date().getFullYear();

    // Placeholder button handlers
    document.getElementById('changePhotoBtn').addEventListener('click', () => {
      alert('Later: open file picker and upload new photo');
    });

  function openPopup() {
    const popup = document.getElementById('editProfilePopup');
    popup.style.display = 'flex';
  }

  function closePopup() {
    const popup = document.getElementById('editProfilePopup');
    popup.style.display = 'none';
  }

  // Close when clicking outside
  window.addEventListener('click', (event) => {
    const popup = document.getElementById('editProfilePopup');
    if (event.target === popup) closePopup();
  });
  </script>


</body>
</html>
