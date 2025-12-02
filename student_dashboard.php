<?php
// Student Dashboard: display a rich portal using Northport dashboard design.
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/config.php';

// Ensure only logged‑in students can view
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'student') {
    redirect('login.php');
}

$userId = $_SESSION['user_id'];

$mysqli = get_db();
$mysqli->set_charset('utf8mb4');

// Fetch basic student information
$user_stmt = $mysqli->prepare(
    "SELECT UserID, FirstName, LastName, Email, UserType, Status, DOB
     FROM Users WHERE UserID = ? LIMIT 1"
);
$user_stmt->bind_param("i", $userId);
$user_stmt->execute();
$resUser = $user_stmt->get_result();
$student = $resUser->fetch_assoc();
$user_stmt->close();

if (!$student) {
    echo "<p>Profile not found for your account.</p>";
    exit;
}

// What kind of student is this?
$stype_sql = "SELECT StudentType FROM Student WHERE StudentID = ? LIMIT 1";
$stype_stmt = $mysqli->prepare($stype_sql);
$stype_stmt->bind_param('i', $userId);
$stype_stmt->execute();
$stype = $stype_stmt->get_result()->fetch_assoc();
$stype_stmt->close();

// Determine if grad or undergrad
$isGrad = (strcasecmp($stype['StudentType'] ?? '', 'Graduate') === 0);

// Initialize defaults
$majorName = 'Undeclared';
$minorName = 'Undeclared';
$totalCreditsNeeded = 0;
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
        $totalCreditsNeeded = (int)($prog['CreditsRequired'] ?? 0);
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

    $totalCreditsNeeded = (int)($major['CreditsNeeded'] ?? 0);
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

// Degree audit summary (credits & GPA)
$progress_sql = "
    SELECT Credits_Completed, Credits_Remaining, CumulativeGPA
    FROM DegreeAudit
    WHERE StudentID = ?
";
$progress_stmt = $mysqli->prepare($progress_sql);
$progress_stmt->bind_param('i', $userId);
$progress_stmt->execute();
$progress = $progress_stmt->get_result()->fetch_assoc();
$progress_stmt->close();

$gpa           = $progress['CumulativeGPA'] ?? 0.00;
$creditsEarned = $progress['Credits_Completed'] ?? 0;
$creditsRemaining = max($totalCreditsNeeded - $creditsEarned, 0);
$percentComplete = $totalCreditsNeeded > 0 ? round(($creditsEarned / $totalCreditsNeeded) * 100, 1) : 0;

// Determine academic standing based on GPA
$standing = ($gpa >= 3.0) ? 'Good Standing' : 'Needs Improvement';

// Compute credits currently registered (in‑progress)
$credits_sql = "
    SELECT SUM(c.Credits) AS TotalCredits
    FROM StudentEnrollment se
    JOIN CourseSection cs ON se.CRN = cs.CRN
    JOIN Course c ON cs.CourseID = c.CourseID
    WHERE se.StudentID = ? AND se.Status IN('ENROLLED', 'IN-PROGRESS')
";
$credits_stmt = $mysqli->prepare($credits_sql);
$credits_stmt->bind_param('i', $userId);
$credits_stmt->execute();
$credits_result = $credits_stmt->get_result()->fetch_assoc();
$credits_stmt->close();
$semesterCredits = (int)($credits_result['TotalCredits'] ?? 0);

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
            cs.RoomID,
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
    $sched_stmt->bind_param('is', $userId, $selectedSemester);
    $sched_stmt->execute();
    $sched_result = $sched_stmt->get_result();
    $schedule = $sched_result->fetch_all(MYSQLI_ASSOC);
    $sched_stmt->close();
}

$message_sql = "
    SELECT a.Title, a.Message, a.DatePosted, c.CourseName
    FROM CourseAnnouncements a
    JOIN CourseSection cs ON a.CRN = cs.CRN
    JOIN Course c ON cs.CourseID = c.CourseID
    JOIN StudentEnrollment se ON se.CRN = cs.CRN
    WHERE se.StudentID = ?
    ORDER BY a.DatePosted DESC
    LIMIT 10
";
$message_stmt = $mysqli->prepare($message_sql);
$message_stmt->bind_param('i', $userId);
$message_stmt->execute();
$message_res = $message_stmt->get_result();

// Placeholder quick links, tasks, announcements and messages
$quickLinks = [
    ['label' => 'Profile',      'href' => 'student_profile.php',    'icon' => 'user'],
    ['label' => 'Degree Audit', 'href' => 'degree_audit.php',       'icon' => 'file-text'],
    ['label' => 'Add/Drop',      'href' => 'Add_Drop_courses.php',           'icon' => 'book'],
    ['label' => 'Transcript',   'href' => 'transcript.php',                      'icon' => 'book-open'],
    ['label' => 'Messages',     'href' => 'messages.php',                      'icon' => 'mail'],
    ['label' => 'View Directory',      'href' => 'viewDirectory.php',                      'icon' => 'credit-card'],
];

$tasks = [
];


$messages = [
    ['from' => 'Dr. Lee',    'subject' => 'Project Update',      'time' => 'Oct 10', 'preview' => 'Please send me your latest project status by Friday.'],
    ['from' => 'Bursar',     'subject' => 'Payment Reminder',    'time' => 'Oct 08', 'preview' => 'Your tuition payment is due Oct 25.'],
];


?><!doctype html>
<html lang="en" data-theme="light">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Northport University — Student Dashboard</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <!-- Use unified dashboard styles -->
  <link rel="stylesheet" href="./styles.css" />
</head>
<body>
  <header class="topbar">
    <div class="brand">
      <div class="logo"><i data-lucide="graduation-cap"></i></div>
      <h1>Northport University</h1>
      <span class="pill">Student Portal</span>
      <!-- Display welcome name inline -->
      <h3 style="margin-left:16px; font-size:14px; font-weight:500; color:var(--muted)">Welcome, <?php echo htmlspecialchars($student['FirstName'] . ' ' . $student['LastName']); ?></h3>
    </div>
    <div class="top-actions">
      <div class="search">
        <i class="search-icon" data-lucide="search"></i>
        <input type="text" placeholder="Search courses, people, anything…" />
      </div>
      <button id="themeToggle" class="icon-btn" aria-label="Toggle theme"><i data-lucide="moon"></i></button>
      <div class="divider"></div>
      <div class="user">
        <!-- For now, use placeholder avatar -->
        <img class="avatar" src="https://i.pravatar.cc/64?img=15" alt="avatar" />
        <div class="user-meta">
          <div class="name"><?php echo htmlspecialchars($student['FirstName'] . ' ' . $student['LastName']); ?></div>
          <div class="sub"><?php echo htmlspecialchars($majorName); ?></div>
        </div>
        <div class="header-left">
          <div class="menu">
            <button>☰ Menu</button>
            <div class="menu-content">
              <a href="student_profile.php">Profile</a>
              <a href="degree_audit.php">Degree Audit</a>
              <a href="viewDirectory.php">View Directory</a>
              <a href="logout.php">Logout</a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </header>

  <main class="container">
    <section class="left">
      <div class="stats">
        <div class="card stat">
          <div class="card-head">
            <div class="muted">Cumulative GPA</div>
            <i data-lucide="line-chart"></i>
          </div>
          <div class="stat-value"><?php echo number_format($gpa, 2); ?></div>
          <div class="sub muted">Standing: <?php echo htmlspecialchars($standing); ?></div>
        </div>

        <div class="card stat">
          <div class="card-head">
            <div class="muted">Credits (This Term)</div>
            <i data-lucide="clipboard-list"></i>
          </div>
          <div class="stat-value"><?php echo htmlspecialchars($semesterCredits); ?></div>
          <div class="sub muted">
            <?php
              if ($selectedSemester) {
                  foreach ($semesters as $sem) {
                      if ($sem['SemesterID'] == $selectedSemester) {
                          echo htmlspecialchars($sem['SemesterName'] . ' ' . $sem['Year']);
                          break;
                      }
                  }
              } else {
                  echo 'Current Semester';
              }
            ?>
          </div>
          <div class="sub muted"><?php echo $creditsEarned; ?>/<?php echo $totalCreditsNeeded; ?> completed</div>
        </div>

         <div class="grid-two sm-one">
        <div class="card">
          <div class="card-title">Recent Messages</div>
              <?php
              $list = $mysqli->prepare("
                  SELECT 
                      c.CopyID,
                      c.MessageID,
                      m.Title,
                      m.Message,
                      m.DatePosted,
                      m.SenderEmail,
                      m.RecipientEmail
                  FROM MessageCopies c
                  JOIN Messages m ON m.MessageID = c.MessageID
                  WHERE c.OwnerEmail = ?
                    AND c.Folder = ?
                    AND c.IsDeleted = 0
                  ORDER BY m.DatePosted DESC
              ");
              $list->bind_param("ss", $userEmail, $folderSQL);
              $list->execute();
              $list_res = $list->get_result();

              if ($list_res->num_rows > 0):
              ?>
                <ul style="list-style:none; padding:0; margin:0;">
                  <?php while ($m = $list_res->fetch_assoc()): ?>
                    <li style="border-bottom:1px solid var(--line); padding:10px 0;">
                      <strong><?= htmlspecialchars($m['Title']) ?></strong>
                      <span style="color:var(--muted);"> — <?= htmlspecialchars($m['Email']) ?></span>
                      <div style="margin-top:4px;"><?= nl2br(htmlspecialchars($m['Message'])) ?></div>
                      <small style="color:var(--muted);">Posted <?= htmlspecialchars($m['DatePosted']) ?></small>
                    </li>
                  <?php endwhile; ?>
                </ul>
                <div style="text-align:right; margin-top:10px;">
                  <a href="messages.php" class="btn outline">View All Messages →</a>
                </div>
              <?php else: ?>
                <p>No recent Messages.</p>
              <?php endif;
              $list->close();
              ?>
            </div>
        </div>

        <div class="card stat">
          <div class="card-head">
            <div class="muted">Holds</div>
            <i data-lucide="triangle-alert"></i>
          </div>
          <div class="stat-value">0</div>
          <div class="sub muted">All clear</div>
        </div>
      </div>

      <div class="grid-two">
        <div class="card">
          <div class="card-title">Degree Progress</div>
          <div class="row between small muted">
            <span><?php echo htmlspecialchars($majorName); ?> • <?php echo htmlspecialchars($totalCreditsNeeded); ?> Credits</span>
            <span><?php echo htmlspecialchars($percentComplete); ?>% complete</span>
          </div>
          <div class="progress">
            <div class="bar" style="width:<?php echo $percentComplete; ?>%"></div>
          </div>
          <div class="badges">
            <span class="badge">Credits Earned: <?php echo $creditsEarned; ?></span>
            <span class="badge">Credits Remaining: <?php echo $creditsRemaining; ?></span>
            <span class="badge">GPA: <?php echo number_format($gpa, 2); ?></span>
          </div>
          <div class="row gap">
            <button onclick="location.href='degree_audit.php'" class="btn">View Degree Plan</button>
            <button onclick="location.href='mailto:<?php echo isset($advisorEmail) ? $advisorEmail : ''; ?>'" class="btn outline">Email Advisor</button>
          </div>
        </div>

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
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <aside class="right">
      <div class="card quick-links-card">
          <div class="card-title">Application</div>
          <div class="quick-grid" id="studentQuickLinks"></div>
      </div>

      <div class="tabs">
        <div class="tabs-list">
          <button class="tab active" data-tab="tasks">To‑Dos</button>
          <button class="tab" data-tab="announcements">Announcements</button>
        </div>
        <div class="tab-panels">
          <div class="tab-panel active" id="panel-tasks">
            <div class="card">
              <div class="card-title">Upcoming Deadlines</div>
              <div id="studentTasksList" class="vstack gap"></div>
              <div class="pt-8">
                <button class="btn"><i data-lucide="clipboard-list"></i> View All Tasks</button>
              </div>
            </div>
          </div>
          <div class="tab-panel" id="panel-announcements">
            <div class="card">
              <div class="card-title">Recent Announcements</div>
              <?php
              $recent = $mysqli->prepare("
                  SELECT a.Title, a.Message, a.DatePosted, c.CourseName
                  FROM CourseAnnouncements a
                  JOIN CourseSection cs ON a.CRN = cs.CRN
                  JOIN Course c ON cs.CourseID = c.CourseID
                  JOIN StudentEnrollment se ON se.CRN = cs.CRN
                  WHERE se.StudentID = ?
                  ORDER BY a.DatePosted DESC
                  LIMIT 3
              ");
              $recent->bind_param('i', $userId);
              $recent->execute();
              $res = $recent->get_result();

              if ($res->num_rows > 0):
              ?>
                <ul style="list-style:none; padding:0; margin:0;">
                  <?php while ($a = $res->fetch_assoc()): ?>
                    <li style="border-bottom:1px solid var(--line); padding:10px 0;">
                      <strong><?= htmlspecialchars($a['Title']) ?></strong>
                      <span style="color:var(--muted);"> — <?= htmlspecialchars($a['CourseName']) ?></span>
                      <div style="margin-top:4px;"><?= nl2br(htmlspecialchars($a['Message'])) ?></div>
                      <small style="color:var(--muted);">Posted <?= htmlspecialchars($a['DatePosted']) ?></small>
                    </li>
                  <?php endwhile; ?>
                </ul>
                <div style="text-align:right; margin-top:10px;">
                  <a href="announcements.php" class="btn outline">View All Announcements →</a>
                </div>
              <?php else: ?>
                <p>No recent announcements.</p>
              <?php endif;
              $recent->close();
              ?>
            </div>
          </div>
        </div>
      </div>
      </div>
    </aside>
  </main>

  <footer class="footer">
    © <span id="year"></span> Northport University • All rights reserved • <a href="#" class="link">Privacy</a>
  </footer>

  <!-- Chart.js for GPA line chart -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
  <!-- Lucide icons -->
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

    // Tab switching
    document.querySelectorAll('.tabs .tab').forEach(tab => {
      tab.addEventListener('click', () => {
        document.querySelectorAll('.tabs .tab').forEach(t => t.classList.remove('active'));
        tab.classList.add('active');
        const target = tab.getAttribute('data-tab');
        document.querySelectorAll('.tab-panel').forEach(panel => {
          panel.classList.toggle('active', panel.id === 'panel-' + target);
        });
      });
    });

    // Insert quick links
    const quickLinks = <?php echo json_encode($quickLinks); ?>;
    const qlContainer = document.getElementById('studentQuickLinks');
    quickLinks.forEach(link => {
      const div = document.createElement('div');
      div.className = 'ql';
      div.addEventListener('click', () => {
        if (link.href) window.location.href = link.href;
      });
      const icon = document.createElement('i');
      icon.setAttribute('data-lucide', link.icon);
      const span = document.createElement('span');
      span.textContent = link.label;
      div.appendChild(icon);
      div.appendChild(span);
      qlContainer.appendChild(div);
    });
    lucide.createIcons();

    // Insert tasks
    const tasks = <?php echo json_encode($tasks); ?>;
    const taskList = document.getElementById('studentTasksList');
    tasks.forEach(task => {
      const item = document.createElement('div');
      item.className = 'row between small';
      item.innerHTML = '<span>' + task.title + '</span><span class="muted">' + task.due + '</span>';
      taskList.appendChild(item);
    });


    // Messages
    const messages = <?php echo json_encode($messages); ?>;
    const msgList = document.getElementById('studentMsgList');
    messages.forEach(msg => {
      const item = document.createElement('div');
      item.className = 'row between small';
      item.innerHTML = '<span><strong>' + msg.from + ':</strong> ' + msg.subject + '</span><span class="muted">' + msg.time + '</span>';
      msgList.appendChild(item);
    });

    // GPA Trend Chart
    const ctx = document.getElementById('gpaChart').getContext('2d');
    new Chart(ctx, {
      type: 'line',
      data: {
        labels: ['Term 1','Term 2','Current'],
        datasets: [{
          label: 'GPA',
          data: [3.2, 3.4, <?php echo json_encode($gpa); ?>],
          borderColor: getComputedStyle(document.documentElement).getPropertyValue('--primary') || '#4f46e5',
          borderWidth: 2,
          fill: false,
          tension: 0.3
        }]
      },
      options: {
        scales: {
          y: {
            min: 0,
            max: 4.0,
            ticks: { stepSize: 0.5 }
          }
        },
        plugins: {
          legend: { display: false }
        }
      }
    });
  </script>
</body>
</html>