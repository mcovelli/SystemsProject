<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/config.php';

$mysqli = get_db();
$mysqli->set_charset('utf8mb4');

// If admin is viewing another student
if (isset($_GET['studentID']) && ($_SESSION['role'] ?? '') === 'admin' && ($_SESSION['role'] ?? '') === 'faculty') {
    $studentID = intval($_GET['studentID']);
}
// If student
else {
    if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'student') {
        redirect('login.php');
    }
    $studentID = $_SESSION['user_id'];
}

$mysqli = get_db();
$mysqli->set_charset('utf8mb4');

$sql = "SELECT UserID, FirstName, LastName, Email, UserType, Status, DOB
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
  JOIN CourseSection cs ON se.CRN = cs.CRN
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

  // Fetch student's current schedule
$sched_sql = "
  SELECT se.CRN, c.CourseName, d.DayOfWeek, p.StartTime, p.EndTime, cs.RoomID, c.Credits
  FROM StudentEnrollment se
  JOIN CourseSection cs ON se.CRN = cs.CRN
  JOIN Course c ON cs.CourseID = c.CourseID
  JOIN TimeSlot ts ON cs.TimeSlotID = ts.TS_ID
  JOIN TimeSlotPeriod tsp ON ts.TS_ID = tsp.TS_ID
  JOIN TimeSlotDay tsd ON ts.TS_ID = tsd.TS_ID
  JOIN Period p ON tsp.PeriodID = p.PeriodID
  JOIN Day d ON tsd.DayID = d.DayID
  JOIN Room r ON cs.RoomID = r.RoomID
  WHERE se.StudentID = ? AND se.Status = 'IN-PROGRESS'
";
$sched_stmt = $mysqli->prepare($sched_sql);
$sched_stmt->bind_param('i', $studentID);
$sched_stmt->execute();
$sched_result = $sched_stmt->get_result();
$schedule = $sched_result->fetch_all(MYSQLI_ASSOC);
$sched_stmt->close();

// Run Degree Audit
try {
    $audit_stmt = $mysqli->prepare("CALL RunDegreeAudit(?)");
    $audit_stmt->bind_param('i', $studentID);
    $audit_stmt->execute();
    $audit_stmt->close();

    // MySQLi needs to advance to the next result set after a procedure call
    while ($mysqli->more_results() && $mysqli->next_result()) {
        $mysqli->use_result();
    }
} catch (Throwable $e) {
    error_log("Degree Audit procedure failed for StudentID {$studentID}: " . $e->getMessage());
}

// Degree progress summary
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

$gpa             = $progress['CumulativeGPA'] ?? 0.00;
$creditsEarned   = $progress['Credits_Completed'] ?? 0;
$coursesTaken = $progress['Courses_Taken'] ?? NULL;
$coursesNeeded = $progress['Courses_Needed'] ?? NULL;

$creditsRemaining = max($totalCreditsNeededMajor - $creditsEarned, 0);
$percent = $totalCreditsNeededMajor > 0 
    ? round(($creditsEarned / $totalCreditsNeededMajor) * 100, 1)
    : 0;

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


?>

<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Degree Audit • Northport University</title>
  <link rel="stylesheet" href="./styles.css" />
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
  <style>
    /* Minimal, content-first layout that leans on your styles.css tokens */
    :root{--gap:16px; --text:#111827}
    body{font-family:Inter,system-ui,Arial,sans-serif; color:var(--text); background:linear-gradient(135deg,#4f46e5 0%, #3b82f6 100%); min-height:100svh;}
    .page{max-width:1100px;margin:24px auto;padding:0 16px}
    .header{display:flex;align-items:center;justify-content:space-between;gap:var(--gap);margin-bottom:var(--gap)}
    .title h1{font-size:1.5rem;margin:0;color:#fff}
    .title small{display:block;color:rgba(255,255,255,.92)}
    .actions{display:flex;gap:8px;flex-wrap:wrap}
    .btn{border:1px solid var(--border,#e5e7eb);padding:8px 12px;border-radius:8px;background:#fff;cursor:pointer;color:var(--text)}
    .btn.primary{background:#111827;color:#fff;border-color:#111827}
    .btn.ghost{background:transparent;color:#fff;border-color:rgba(255,255,255,.6)}
    .grid{display:grid;gap:var(--gap)}
    .grid.kpi{grid-template-columns:repeat(4,minmax(0,1fr))}
    @media (max-width:800px){.grid.kpi{grid-template-columns:repeat(2,1fr)}}
    .card{border:1px solid var(--border,#e5e7eb);border-radius:12px;background:#fff;color:var(--text)}
    .card .card-head{display:flex;align-items:baseline;justify-content:space-between;padding:14px 16px;border-bottom:1px solid var(--border,#e5e7eb)}
    .card .card-body{padding:16px}
    /* Ensure text is not gray by default */
    .muted{color:inherit}
    thead th{font-size:.85rem;color:inherit;font-weight:600}
    .stat{display:flex;flex-direction:column;gap:4px}
    .stat .big{font-size:1.75rem;font-weight:700}
    .progress{height:8px;background:#f3f4f6;border-radius:999px;overflow:hidden}
    .progress .bar{height:100%;background:#6366f1;width:0}
    .field-row{display:flex;gap:8px;flex-wrap:wrap}
    .field-row > *{flex:1 1 220px}
    select,input[type="text"]{width:100%;border:1px solid var(--border,#e5e7eb);border-radius:8px;padding:8px 12px;background:#fff;color:var(--text)}
    table{width:100%;border-collapse:separate;border-spacing:0}
    th,td{padding:10px 12px;border-bottom:1px solid var(--border,#e5e7eb);text-align:left}
    tbody tr:hover{background:#fafafa}
    .badge{font-size:.75rem;padding:2px 8px;border-radius:999px;border:1px solid var(--border,#e5e7eb)}
    .ok{background:#ecfdf5;border-color:#10b981;color:#065f46}
    .warn{background:#fffbeb;border-color:#f59e0b;color:#92400e}
    .bad{background:#fef2f2;border-color:#ef4444;color:#991b1b}
    .accordion details{border-top:1px solid var(--border,#e5e7eb)}
    .accordion summary{cursor:pointer;list-style:none;padding:12px 0;font-weight:600}
    .accordion summary::-webkit-details-marker{display:none}
    tbody tr:hover{background:#f8fafc}
    .footer-note{ text-align:center; margin:24px 0; opacity:.9; color:var(--text) }
  </style>
</head>
<body>
    <header>
    
    <?php
      $role = $_SESSION['role'] ?? '';

      switch ($role) {
          case 'faculty':
              $dashboard = 'faculty_dashboard.php';
              break;
          case 'admin':
              $dashboard = 'admin_dashboard.php';
              break;
          case 'staff':
              $dashboard = 'staff_dashboard.php';
              break;
          default:
              $dashboard = 'student_dashboard.php';
              break;
      }
    ?>
    <button class="btn">
      <a href="<?= htmlspecialchars($dashboard) ?>">← Back to Dashboard</a>
    </button>

    <h1>Northport University Degree Audit</h1>
  </header>

  <div class="page">
    <!-- Header -->
    <div class="header">
      <div class="title">
        <h1>Degree Audit</h1>
        <small>Program: <span id="programName"><?= $majorName ?></span> • Catalog <span id="catalogYear">2024–2025</span></small>
      </div>
      <div class="actions">
        <button class="btn ghost" id="printBtn">Print</button>
      </div>
    </div>

    <!-- KPIs -->
    <div class="grid kpi">
      <div class="card"><div class="card-body stat"><span>Credits</span><span class="big">
        <span id="earned"><?= $creditsEarned ?></span> /
        <span id="required"><?= $totalCreditsNeededMajor ?></span>
      </span>
      <small>Remaining <strong id="remaining"><?= $creditsRemaining ?></strong></small></div></div>
      <div class="card"><div class="card-body stat"><span>Progress</span><span class="big" id="pct"><?= $percent ?></span><div class="progress"><div class="bar" id="pctBar"></div></div></div></div>
      <div class="card"><div class="card-body stat"><span>Cumulative GPA</span><span class="big" id="gpa"><?= $gpa ?></span><small><?= htmlspecialchars($standing) ?></small></div></div>
      <div class="card">
        <div class="card-title">Advisor Information</div>
          <p><strong><?= htmlspecialchars($advisorName) ?></strong></p>
          <p>Office: <?= htmlspecialchars($advisorOffice) ?></p>
          <p>Email: <a href="mailto:<?= htmlspecialchars($advisorEmail) ?>"><?= htmlspecialchars($advisorEmail) ?></a></p>
      </div>
    </div>

    <!-- Overview chart -->
    <div class="card" style="margin-top:16px">
      <div class="card-head"><div>Requirement Overview</div></div>
      <div class="card-body" style="height:260px"><canvas id="reqChart"></canvas></div>
    </div>

    <!-- Accordion requirements -->
    <div class="card" style="margin-top:16px">
      <div class="card-head"><div>Courses Needed</div><div></div></div>
      <div class="card-body">
        <div class="table-wrap">
          <table id="coursesTable">
            <thead><tr><th>Course</th></tr></thead>
             <tbody>
              <?php
                // Convert Courses_Needed string into lines
                $raw = trim($coursesNeeded ?? '');

                if ($raw === '') {
                    $neededLines = [];
                } else {
                    // Remove any header lines like --- Major Courses Still Needed ---
                    $raw = preg_replace('/^---.*?---/m', '', $raw);

                    // Split by comma, semicolon, OR newline OR multiple spaces
                    $neededLines = preg_split("/,|;|\r\n|\r|\n|\s{2,}/", $raw);

                    // Clean blank entries
                    $neededLines = array_filter(array_map('trim', $neededLines));
                }
              ?>
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
    </div>

    <!-- Course table -->
    <div class="card" style="margin-top:16px">
      <div class="card-head"><div>Courses (Completed & In‑Progress)</div><div>Most recent first</div></div>
      <div class="card-body">
        <div class="table-wrap">
          <table id="coursesTable">
            <thead><tr><th>Term</th><th>Course</th><th>Title</th><th>Credits</th><th>Grade</th><th>Status</th></tr></thead>
            <tbody id="coursesBody">
              <?php if (!empty($courses)): ?>
                <?php foreach ($courses as $c): ?>
                  <tr>
                    <td><?= htmlspecialchars($c['SemesterID']) ?></td>
                    <td><?= htmlspecialchars($c['CourseID']) ?></td>
                    <td><?= htmlspecialchars($c['CourseName']) ?></td>
                    <td><?= htmlspecialchars($c['Credits']) ?></td>
                    <td><?= htmlspecialchars($c['Grade'] ?? ($c['Status'] === 'IN-PROGRESS' ? 'IP' : '')) ?></td>
                    <td><?= htmlspecialchars($c['Status']) ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr><td colspan="6">No courses found.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <footer class="footer-note">© <span id="year"></span> Northport University</footer>
  </div>

  <script>
    // ------- Populate header/KPIs from PHP -------
    const gpa = parseFloat(document.getElementById('gpa').textContent);
    const earned = parseInt(document.getElementById('earned').textContent);
    const required = parseInt(document.getElementById('required').textContent);
    const remaining = parseInt(document.getElementById('remaining').textContent);
    const pct = parseFloat(document.getElementById('pct').textContent);

    document.getElementById('gpa').textContent = gpa.toFixed(2);
    document.getElementById('earned').textContent = earned;
    document.getElementById('required').textContent = required;
    document.getElementById('remaining').textContent = remaining;
    document.getElementById('pct').textContent = pct + '%';
    document.getElementById('pctBar').style.width = pct + '%';
    document.getElementById('year').textContent = new Date().getFullYear();
   
    // ------- Chart -------
    new Chart(document.getElementById('reqChart'), {
      type: 'bar',
      data: {
        labels: ['Earned', 'Remaining'],
        datasets: [{
          data: [earned, remaining],
          backgroundColor: ['#4f46e5', '#e5e7eb']
        }]
      },
      options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true } }
      }
    });

    // ------- Requirement accordion -------
    const reqRoot = document.getElementById('reqAccordion');
    function makeReqLine(r){
      const row = document.createElement('div');
      const badgeClass = r.status==='met' ? 'ok' : r.status==='ip' ? 'warn' : 'bad';
      row.style.display='grid';
      row.style.gridTemplateColumns='1fr auto auto auto auto';
      row.style.gap='12px';
      row.style.padding='10px 0';
      row.innerHTML = `
        <div><strong>${r.code}</strong> — ${r.title}</div>
        <div>Needed: ${r.needed}</div>
        <div>Applied: ${r.applied}</div>
        <div><span class="badge ${badgeClass}">${r.status.toUpperCase()}</span></div>
      `;
      return row;
    }
    audit.groups.forEach(g=>{
      const det = document.createElement('details');
      det.open = true;
      const sum = document.createElement('summary');
      sum.textContent = `${g.name} • ${g.earned + g.inProgress}/${g.needed} satisfied`;
      det.appendChild(sum);
      g.requirements.forEach(r=>det.appendChild(makeReqLine(r)));
      reqRoot.appendChild(det);
    });

    // ------- Courses table -------
    let showIP = true; renderCourses(showIP);
    document.getElementById('toggleIP').addEventListener('click', ()=>{ showIP = !showIP; renderCourses(showIP); });

    // ------- Filters & search -------
    const groupFilter = document.getElementById('groupFilter');
    const statusFilter = document.getElementById('statusFilter');
    const searchInput = document.getElementById('searchInput');

    function applyFilters(){
      // Collapse/expand lines by simple text filtering inside accordion
      const q = searchInput.value.trim().toLowerCase();
      const gVal = groupFilter.value;
      const sVal = statusFilter.value;
      [...reqRoot.querySelectorAll('details')].forEach(d=>{
        const groupName = d.querySelector('summary').textContent.split('•')[0].trim();
        d.style.display = (gVal==='all' || groupName===gVal) ? '' : 'none';
        [...d.querySelectorAll('div')].forEach(line=>{
          if(!line.querySelector) return;
          const text = line.textContent.toLowerCase();
          // crude status tagging by text presence
          const status = text.includes('met') ? 'met' : (text.includes('in‑progress') ? 'ip' : 'unmet');
          const matchesStatus = sVal==='all' || status===sVal;
          const matchesSearch = !q || text.includes(q);
          line.style.display = (matchesStatus && matchesSearch) ? '' : 'none';
        });
      });
    }
    groupFilter.addEventListener('change', applyFilters);
    statusFilter.addEventListener('change', applyFilters);
    searchInput.addEventListener('input', applyFilters);
    document.getElementById('clearFilters').addEventListener('click', ()=>{
      groupFilter.value='all'; statusFilter.value='all'; searchInput.value=''; applyFilters();
    });

    // ------- Print  -------
    document.getElementById('printBtn').addEventListener('click', ()=>window.print());

    // ------- What‑If (stub) -------
    document.getElementById('runWhatIf').addEventListener('click', ()=>{
      alert('What‑If run complete (demo). Replace with backend call.');
    });
  </script>
</body>
</html>
