<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/config.php';

// Ensure only logged‑in students or update admin can view
if (
    !isset($_SESSION['user_id']) ||
    (
        ($_SESSION['role'] ?? '') !== 'student' &&
        !(($_SESSION['role'] ?? '') === 'admin' && ($_SESSION['admin_type'] ?? '') === 'update')
    )
) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id'];

$mysqli = get_db();
$mysqli->set_charset('utf8mb4');

$userRole = strtolower($_SESSION['role'] ?? '');
switch ($userRole) {

    case 'student' :
      $dashboard = 'student_dashboard.php';
      break;

    case 'admin':
        // if you have update/view admin types:
        if (($_SESSION['admin_type'] ?? '') === 'update') {
            $dashboard = 'update_admin_dashboard.php';
        } else {
            $dashboard = 'login.html';
        }
        break;
    default:
        $dashboard = 'login.html'; // fallback
}

$selectedDept = $_GET['dept'] ?? ($_POST['dept'] ?? '');
$selectedSemester = $_GET['Semester'] ?? ($_GET['semester'] ?? ($_POST['Semester'] ?? ($_POST['semester'] ?? '')));

$conditions = [];
$params = [];
$types = "";

// Fetch student's degree level and credit cap for filtering and limits
$studentLevel = null;
$maxCredits = 0;

if (!empty($_SESSION['user_id'])) {
    $stmt = $mysqli->prepare("
        SELECT 
            s.StudentType,
            COALESCE(ftug.MaxCredits, ptug.MaxCredits, ftg.MaxCredits, ptg.MaxCredits) AS MaxCredits
        FROM Student s
        LEFT JOIN FullTimeUG ftug   ON s.StudentID = ftug.StudentID
        LEFT JOIN PartTimeUG ptug   ON s.StudentID = ptug.StudentID
        LEFT JOIN FullTimeGrad ftg  ON s.StudentID = ftg.StudentID
        LEFT JOIN PartTimeGrad ptg  ON s.StudentID = ptg.StudentID
        WHERE s.StudentID = ?
        LIMIT 1
    ");
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $studentLevel = $row['StudentType']; // 'Undergraduate' or 'Graduate'
        $maxCredits   = (int)($row['MaxCredits'] ?? 15);
    }
    $stmt->close();

    // Normalize student level to match Course.CourseType
if (!empty($studentLevel)) {
    if (stripos($studentLevel, 'under') !== false) {
        $studentLevel = 'UNDERGRAD';
    } elseif (stripos($studentLevel, 'grad') !== false) {
        $studentLevel = 'GRAD';
    } else {
        $studentLevel = '';
    }
}
}

if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

if (!empty($studentLevel)) {
    $conditions[] = " c.CourseType = ?";
    $params[] = $studentLevel;
    $types .= "s";
}

$sql = "
SELECT 
    cs.CRN,
    cs.CourseID,
    cs.CourseSectionNo,
    c.CourseName,
    CONCAT(fu.FirstName, ' ', fu.LastName) AS Professor,
    GROUP_CONCAT(DISTINCT d.DayOfWeek ORDER BY d.DayID SEPARATOR '/') AS DayOfWeek,
    MIN(DATE_FORMAT(p.StartTime, '%l:%i %p')) AS StartTime,
    MAX(DATE_FORMAT(p.EndTime, '%l:%i %p'))   AS EndTime,
    cs.RoomID,
    cs.SemesterID,
    cs.AvailableSeats,
    dep.DeptName,
    c.CourseType,
    c.Credits
FROM CourseSection cs
JOIN Course c         ON cs.CourseID   = c.CourseID
JOIN Users fu         ON cs.FacultyID  = fu.UserID
JOIN Department dep   ON c.DeptID      = dep.DeptID
JOIN Semester s       ON cs.SemesterID = s.SemesterID
JOIN TimeSlot ts      ON cs.TimeSlotID = ts.TS_ID
JOIN TimeSlotDay tsd  ON ts.TS_ID      = tsd.TS_ID
JOIN Day d            ON tsd.DayID     = d.DayID
JOIN TimeSlotPeriod tsp ON ts.TS_ID     = tsp.TS_ID
JOIN Period p         ON tsp.PeriodID  = p.PeriodID
";

if (empty($selectedSemester)) {
    $coursesections = [];
    $availableCount = 0;
} else {
    $conditions[] = " cs.SemesterID = ?";
    $params[] = $selectedSemester;
    $types .= "s";

    if (!empty($selectedDept)) {
        $conditions[] = " dep.DeptName = ?";
        $params[] = $selectedDept;
        $types .= "s";
    }

    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(" AND ", $conditions);
    }

    $sql .= "
      GROUP BY cs.CRN, cs.CourseID, cs.CourseSectionNo, c.CourseName,
                cs.RoomID, cs.SemesterID, cs.AvailableSeats, dep.DeptName,
                c.CourseType, c.Credits
      ORDER BY cs.SemesterID, cs.CourseID";

    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    $coursesections = $res->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $availableCount = count($coursesections);
}

$totalCredits = 0;

if (!empty($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $crn = is_array($item) ? ($item['crn'] ?? null) : $item;
        if (!$crn) continue;

        // Lookup credit value from the Course table
        $creditStmt = $mysqli->prepare("
            SELECT c.Credits
            FROM CourseSection cs
            JOIN Course c ON cs.CourseID = c.CourseID
            WHERE cs.CRN = ?
        ");
        $creditStmt->bind_param('i', $crn);
        $creditStmt->execute();
        $result = $creditStmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $totalCredits += (int)$row['Credits'];
        }
        $creditStmt->close();
    }
}


// Fetch schedule entries for the selected semester
$schedule = [];
if ($selectedSemester) {
      $sched_sql = "
        SELECT 
            se.CRN,
            se.SemesterID,
            c.CourseName,
            GROUP_CONCAT(DISTINCT d.DayOfWeek ORDER BY d.DayID SEPARATOR '/') AS Days,
            MIN(DATE_FORMAT(p.StartTime, '%l:%i %p')) AS StartTime,
            MAX(DATE_FORMAT(p.EndTime, '%l:%i %p'))   AS EndTime,
            cs.RoomID
        FROM StudentEnrollment se
        JOIN CourseSection cs ON se.CRN = cs.CRN
        JOIN Course c ON cs.CourseID = c.CourseID
        JOIN TimeSlot ts ON cs.TimeSlotID = ts.TS_ID
        JOIN TimeSlotPeriod tsp ON ts.TS_ID = tsp.TS_ID
        JOIN TimeSlotDay tsd ON ts.TS_ID = tsd.TS_ID
        JOIN Period p ON tsp.PeriodID = p.PeriodID
        JOIN Day d ON tsd.DayID = d.DayID
        WHERE se.StudentID = ? 
          AND se.SemesterID = ?
          AND se.Status IN ('ENROLLED', 'IN-PROGRESS', 'PLANNED')
        GROUP BY se.CRN, c.CourseName, cs.RoomID
        ORDER BY MIN(p.StartTime)
    ";
    $sched_stmt = $mysqli->prepare($sched_sql);
    $sched_stmt->bind_param('is', $userId, $selectedSemester);
    $sched_stmt->execute();
    $sched_result = $sched_stmt->get_result();
    $schedule = $sched_result->fetch_all(MYSQLI_ASSOC);
    $sched_stmt->close();
}


$onHold = false;
$studentHolds = [];

$hold_sql = "
SELECT 
    sh.HoldID, 
    h.HoldType
  FROM StudentHold sh
  JOIN Hold h ON sh.HoldID = h.HoldID
  WHERE sh.StudentID = ?
";

$hold_stmt = $mysqli->prepare($hold_sql);
$hold_stmt->bind_param('i', $userId);
$hold_stmt->execute();
$hold_res = $hold_stmt->get_result();
$studentHolds = $hold_res->fetch_all(MYSQLI_ASSOC);
$hold_stmt->close();

if (!empty($studentHolds)){
  $onHold = true;
}

?>

<!doctype html>
<html lang="en" data-theme="light">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Northport University — Add / Drop Courses (Single File)</title>
  <link rel="stylesheet" href="./viewstyles.css" />
</head>
<body>
  <header class="topbar">
    <div class="brand">
      <div class="logo">NU</div>
      <h1>Northport University</h1>
      <span class="pill">Add / Drop</span>
    </div>
    <div class="top-actions">
      <div class="search" style="width: min(360px, 40vw)">
        <i class="search-icon" data-lucide="search"></i>
        <input id="q" type="text" placeholder="Search code, title, instructor…" />
      </div>
      <button class="btn outline" id="themeToggle" title="Toggle theme">🌙</button>
      <a href="<?= htmlspecialchars($dashboard) ?>" aria-label="Back to Dashboard">← Back to Dashboard</a></div>
    </div>
  </header>


  <main class="page">
    <?php if (!empty($_SESSION['error_message'])): ?>
      <div class="alert error" style="background:#fee2e2;color:#991b1b;padding:10px;border-radius:6px;margin-bottom:10px;">
        <?= htmlspecialchars($_SESSION['error_message']) ?>
      </div>
      <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>
    <section class="hero card">
      <div class="card-head between">
        <div>
          <h2 class="card-title">Add / Drop - <span id="heroSem"><?= htmlspecialchars($selectedSemester) ?></span></h2>
          <div class="sub muted">Pick a term, search or filter, then build your cart. Conflicts and credits update live.</div>
        </div>
        <div class="row gap">
          <span class="badge">Student Portal</span>
          <span class="badge">Registration</span>
        </div>
      </div>
      <div style="margin-top:12px">
        <div>
          <strong id="heroCredits"><?= htmlspecialchars($totalCredits) ?></strong> /
          <span class="muted"><?= htmlspecialchars($maxCredits) ?></span>
        </div>
        <div class="progress">
          <span id="progBar" style="width:<?= min(100, ($totalCredits / max(1, $maxCredits)) * 100) ?>%"></span>
        </div>
    </section>

    <section class="card">
      <div class="card-head">
        <div>
          <div class="card-title">Registration</div>
          <div class="sub muted">Choose a semester, then add/drop eligible sections.</div>
        </div>
      </div>
      <div class="controls" role="region" aria-label="Registration controls">


        <form>
          <label for="dept">Department:</label>
          <select name="dept" id="dept">
            <option value="">-- All Departments --</option>
          </select>

          <label for="Semester">Semester:</label>
          <select name="Semester" id="Semester">
          </select>

          <button type="submit">Apply Filters</button>
        </form>
      </div>
    </section>

    <section class="columns">
      <!-- LEFT: Available sections -->
      <div class="card">
        <div class="card-title">
          Available Sections (<span id="availCount"><?= htmlspecialchars($availableCount) ?></span>)
        </div>
        <div class="table-wrap table-scroll" style="max-height:420px;overflow:auto">
          <table class="table" role="table" aria-describedby="availCaption">
            <caption id="availCaption" class="sr-only" style="position:absolute;left:-9999px;">Available course sections</caption>
            <thead>
              <tr>
                <th class="w-90">CRN</th>
                <th>Course</th>
                <th>Days</th>
                <th>Time</th>
                <th>Location</th>
                <th>Professor</th>
                <th>Available Seats</th>
              </tr>
            </thead>
            <tbody id="availBody">
              <?php if (empty($coursesections)): ?>
                <tr><td colspan="5">No courses sections available for this semester.</td></tr>
              <?php else: ?> 
                <?php foreach ($coursesections as $row): ?>
                  <tr>
                    <td><?= htmlspecialchars($row['CRN']) ?></td>
                    <td><?= htmlspecialchars($row['CourseID']) ?> — <?= htmlspecialchars($row['CourseName']) ?></td>
                    <td><?= htmlspecialchars(is_array($row['DayOfWeek']) ? implode('/', $row['DayOfWeek']) : ($row['DayOfWeek'] ?? '')) ?></td>
                    <td><?= htmlspecialchars($row['StartTime'] . ' – ' . $row['EndTime']) ?></td>
                    <td><?= htmlspecialchars($row['RoomID']) ?></td>
                    <td><?= htmlspecialchars($row['Professor']) ?></td>
                    <td><?= htmlspecialchars($row['AvailableSeats']) ?></td>
                    <td>
                      <form method="POST" action="add_to_cart.php" style="display:inline;">
                        <input type="hidden" name="crn" value="<?= htmlspecialchars($row['CRN']) ?>">
                        <input type="hidden" name="courseID" value="<?= htmlspecialchars($row['CourseID']) ?>">
                        <input type="hidden" name="semester" value="<?= htmlspecialchars($row['SemesterID']) ?>">
                        <input type="hidden" name="dept" value="<?= htmlspecialchars($selectedDept) ?>">
                        <button type="submit" class="btn gradient">Add</button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- RIGHT: stack Cart + Chosen boxes -->
      
      <div class="stack">
        <!-- Cart -->
        <aside class="card">
          <div class="card-head between">
            <div>
              <div class="card-title">Cart</div>
              <div class="sub muted">Decide which cart items you intend to take</div>
              
          <div class="subtotal">
            <div class="muted">Credits this term</div>
            <div><strong id="creditTotal"><?= htmlspecialchars($totalCredits) ?></strong></div>
          </div>
          <div class="table-wrap">
            <table class="table" role="table" aria-describedby="cartCaption">
              <caption id="cartCaption" class="sr-only" style="position:absolute;left:-9999px;">My selected sections</caption>
              <thead>
                <tr>
                  <th>CRN</th>
                  <th>Course ID</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody id="cartBody">
                <?php foreach ($_SESSION['cart'] as $item): ?>
                    <?php
                      if (!is_array($item)) {
                        $crn = $item;
                        $courseID = '';
                      } else {
                        $crn = $item['crn'] ?? '';
                        $courseID = $item['courseID'] ?? '';
                      }
                    ?>
                    <tr>
                      <td><?= htmlspecialchars((string)$crn) ?></td>
                      <td><?= htmlspecialchars((string)$courseID) ?></td>
                      <td>
                        <form method="POST" action="remove_from_cart.php" style="display:inline;">
                        <input type="hidden" name="crn" value="<?= htmlspecialchars((string)$crn) ?>">
                        <input type="hidden" name="semester" value="<?= htmlspecialchars($selectedSemester) ?>">
                        <input type="hidden" name="dept" value="<?= htmlspecialchars($selectedDept) ?>">
                        <button type="submit" class="btn outline">x</button>
                      </form>
                      </td>
                    </tr>
                <?php endforeach; ?>
              </tbody>
            </table>

            <form action="confirm_cart.php" method="POST" style="margin-top:1em;">
              <?php if ($onHold): ?>
                  <button disabled class="disabled-btn">Register (Hold on Account)</button>
              <?php else: ?>
                  <button class="btn-primary">Register</button>
              <?php endif; ?>
            </form>

          </div>
          <div id="conflictBox" class="sub muted" style="margin-top:8px"></div>
        </aside>
      </div>
    </section>


    <!-- Student Schedule -->
    <section>
      <aside class="card">
        <div class="card-title">Drop course sections from <?= htmlspecialchars($selectedSemester) ?> </div><br>
          <div class="card-head between">
           <div class="stack">
              <table>
                <thead>
                  <tr>
                    <th class="w-90">CRN</th>
                    <th>Course</th>
                    <th>Days</th>
                    <th>Time</th>
                    <th>Location</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody id="studentScheduleBody">
                  <?php if (empty($schedule)): ?>
                    <tr><td colspan="6">No courses scheduled for this semester.</td></tr>
                  <?php else: ?>
                    <?php foreach ($schedule as $row): ?>

                    <tr>
                        <td><?= htmlspecialchars((string)$row['CRN']); ?></td>
                        <td><?= htmlspecialchars((string)$row['CourseName']); ?></td>

                        <?php
                        // Always define safely
                        $dayStr = isset($row['Days']) && $row['Days'] !== '' ? $row['Days'] : '—';
                        ?>
                        <td><?= htmlspecialchars($dayStr); ?></td>

                        <?php
                        $start = $row['StartTime'] ?? '';
                        $end   = $row['EndTime'] ?? '';
                        $timeStr = ($start !== '' || $end !== '') ? trim("$start – $end") : 'TBA';
                        ?>
                        <td><?= htmlspecialchars($timeStr); ?></td>

                        <td><?= htmlspecialchars((string)$row['RoomID']); ?></td>

                        <td>
                            <form method="POST" action="drop_course.php">
                                <input type="hidden" name="crn" value="<?= htmlspecialchars((string)$row['CRN']); ?>">
                                <input type="hidden" name="semester" value="<?= htmlspecialchars((string)$row['SemesterID']); ?>">
                                <input type="hidden" name="dept" value="<?= htmlspecialchars((string)$selectedDept); ?>">
                                <button type="submit" class="btn outline">Drop</button>
                            </form>
                        </td>
                    </tr>

                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </aside>
    </section>
  </main>

  <!-- View Course Modal (inline) -->
  <div id="courseScrim" class="modal-scrim" aria-hidden="true">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="courseTitle">
      <div class="modal-head">
        <div class="modal-title" id="courseTitle">Course</div>
        <button class="btn outline" id="courseClose" aria-label="Close">✕</button>
      </div>
      <div class="modal-body" id="courseBody"><!-- filled by JS --></div>
      <div class="modal-foot">
        <button class="btn outline" id="modalAddBtn">Add to Cart</button>
        <button class="btn" id="modalCloseBtn">Close</button>
      </div>
    </div>
  </div>
  <footer class="footer">© <span id="year"></span> Northport University</footer>

  <script>
    // Fetch departments from get_departments.php
      fetch('get_departments.php')
        .then(response => response.json())
        .then(data => {
          const deptSelect = document.getElementById('dept');
          const selectedDept = new URLSearchParams(window.location.search).get('dept');

          data.forEach(name => {
            const opt = document.createElement('option');
            opt.value = name.name;
            opt.textContent = name.name;
            if (name === selectedDept) opt.selected = true;
            deptSelect.appendChild(opt);
          });
        })
        .catch(err => console.error('Error loading departments:', err));

    // Fetch Semesters from get_semesters.php
      fetch('get_open_semesters.php')
        .then(res => res.json())
        .then(data => {
          const semesterSelect = document.getElementById('Semester');
          data.forEach(row => {
            const opt = document.createElement('option');
            opt.value = row.SemesterID;
            opt.textContent = `${row.SemesterName} ${row.Year}`;
            semesterSelect.appendChild(opt);
          });
        });
    
  // ===== Theme toggle =====
    (function(){
      const root = document.documentElement;
      const btn = document.getElementById('themeToggle');
      const getStored = () => localStorage.getItem('nu-theme');
      const apply = (t) => { root.setAttribute('data-theme', t); if(btn) btn.textContent = t==='dark' ? '☀️' : '🌙'; };
      const preferred = getStored() || root.getAttribute('data-theme') || 'light';
      apply(preferred);
      if(btn){
        btn.addEventListener('click', ()=>{
          const next = (root.getAttribute('data-theme')==='dark') ? 'light' : 'dark';
          apply(next); localStorage.setItem('nu-theme', next);
        });
      }
    })();
  </script>

  <script>
  document.addEventListener('DOMContentLoaded', () => {
    const total = parseInt(document.getElementById('heroCredits').textContent) || 0;
    const bar = document.getElementById('progBar');
    bar.style.transition = 'width 0.6s ease';
    bar.style.width = Math.min(100, (total / 12) * 100) + '%';
  });
  </script>
  <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
  <script> if(window.lucide) lucide.createIcons(); </script>
  
</body>
</html>
