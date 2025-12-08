<?php
// Faculty Dashboard: detailed portal for faculty members.
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/config.php';

// Only allow logged‑in faculty
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'faculty') {
    redirect('login.php');
}

$facultyId = $_SESSION['user_id'];

$mysqli = get_db();
$mysqli->set_charset('utf8mb4');

// Fetch user and faculty info
$user_stmt = $mysqli->prepare("SELECT FirstName, LastName, Email, DOB FROM Users WHERE UserID = ? LIMIT 1");
$user_stmt->bind_param('i', $facultyId);
$user_stmt->execute();
$user = $user_stmt->get_result()->fetch_assoc();
$user_stmt->close();

if (!$user) {
    echo "<p>Faculty member not found.</p>";
    exit;
}

$fac_stmt = $mysqli->prepare("SELECT OfficeID, Ranking FROM Faculty WHERE FacultyID = ? LIMIT 1");
$fac_stmt->bind_param('i', $facultyId);
$fac_stmt->execute();
$fac = $fac_stmt->get_result()->fetch_assoc();
$fac_stmt->close();
$office    = $fac['OfficeID'] ?? 'N/A';
$ranking   = $fac['Ranking'] ?? 'Faculty';

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

// Count courses taught by this faculty member
$courseCount_stmt = $mysqli->prepare("SELECT COUNT(DISTINCT CRN) AS Count FROM CourseSection WHERE FacultyID = ?");
$courseCount_stmt->bind_param('i', $facultyId);
$courseCount_stmt->execute();
$courseCountRes = $courseCount_stmt->get_result()->fetch_assoc();
$courseCount_stmt->close();
$courseCount = (int)($courseCountRes['Count'] ?? 0);

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
$roster = [];
if ($selectedSemester) {
    $roster_sql = "
      SELECT 
        u.FirstName, 
        u.LastName, 
        c.CourseName, 
        GROUP_CONCAT(DISTINCT d.DayOfWeek ORDER BY d.DayID SEPARATOR '/') AS Days,
        DATE_FORMAT(MIN(p.StartTime), '%l:%i %p') AS StartTime,
        DATE_FORMAT(MAX(p.EndTime), '%l:%i %p')   AS EndTime,
        cs.RoomID
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
      WHERE cs.FacultyID = ?
        AND cs.SemesterID = ?
      GROUP BY se.StudentID, cs.CRN, c.CourseName, cs.RoomID
      ORDER BY c.CourseName, u.LastName, u.FirstName;
    ";
    $roster_stmt = $mysqli->prepare($roster_sql);
    $roster_stmt->bind_param('is', $facultyId, $selectedSemester);
    $roster_stmt->execute();
    $roster_result = $roster_stmt->get_result();
    $roster = $roster_result->fetch_all(MYSQLI_ASSOC);
    $roster_stmt->close();
}

$courseCount = 0;
$courseCount = count($schedule);

// Placeholder tasks, announcements and messages for faculty
$tasks = [
    
];
$announcements = [
  
];
$messages = [
   
];

// Quick actions for faculty
$quickLinks = [
    ['label' => 'Profile',    'href' => 'faculty_profile.php', 'icon' => 'user'],
    ['label' => 'Advisees',   'href' => 'ViewAdvisees.php',                'icon' => 'users'],
    ['label' => 'Attendance', 'href' => 'track_attendance.php',                'icon' => 'apple'],
    ['label' => 'Gradebook',  'href' => 'grade.php',                'icon' => 'check-circle'],
    ['label' => 'Messages',     'href' => 'messages.php',                      'icon' => 'mail'],
    ['label' => 'Announcements',      'href' => 'send_announcement.php',                'icon' => 'megaphone'],
    ['label' => 'Logout',     'href' => 'logout.php',       'icon' => 'log-out']
];

$initials = substr($user['FirstName'], 0, 1) . substr($user['LastName'], 0, 1);

?>

<!doctype html>
<html lang="en" data-theme="light">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Faculty Dashboard • Northport University</title>
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
      <span class="pill">Faculty Portal</span>
    </div>
    <div class="top-actions">
      <div class="search">
        <i class="search-icon" data-lucide="search"></i>
        <input type="text" placeholder="Search courses, people, anything…" />
      </div>
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

  <main class="container">
    <section class="left">
      <div class="stats">
        <div class="card stat">
          <div class="card-head">
            <div class="muted">Number of Courses (All)</div>
            <i data-lucide="pencil"></i>
            <span id="courseCount"><?= htmlspecialchars($courseCount) ?></span>
          </div>
          <div class="stat-value"><?php echo $courseCount; ?></div>
        </div>

        <div class="card stat">
          <div class="card-head">
            <div class="muted">Faculty ID</div>
            <i data-lucide="id-card"></i>
          </div>
          <div class="stat-value"><?php echo htmlspecialchars($facultyId); ?></div>
        </div>

        <div class="card stat">
          <div class="card-head">
            <div class="muted">Unread Messages</div>
            <i data-lucide="inbox"></i>
          </div>
          <div class="stat-value"><?php echo count($messages); ?></div>
        </div>

        <div class="card stat">
          <div class="card-head">
            <div class="muted">Position</div>
            <i data-lucide="user"></i>
          </div>
          <div class="stat-value"><?php echo htmlspecialchars($ranking); ?></div>
        </div>

        <div class="card stat">
          <div class="card-head">
            <div class="muted">Date of Birth</div>
            <i data-lucide="calendar"></i>
          </div>
          <div class="stat-value"><?php echo $user['DOB'] ? date('m/d/Y', strtotime($user['DOB'])) : 'N/A'; ?></div>
        </div>

        <div class="card stat">
          <div class="card-head">
            <div class="muted">Office Location</div>
            <i data-lucide="map-pin"></i>
          </div>
          <div class="stat-value"><?php echo htmlspecialchars($office); ?></div>
        </div>
      </div>

      <div class="card">
          <form method="get" class="semester-selector" style="margin-bottom:10px">
            <label for="semester" style="margin-right:6px">View Semester:</label>
            <select name="semester" id="semester" onchange="this.form.submit()">
              <option value="">Current Semester</option>
              <?php foreach ($semesters as $sem): ?>
                <option value="<?php echo htmlspecialchars($sem['SemesterID']); ?>" <?php echo ($selectedSemester == $sem['SemesterID']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($sem['SemesterName'] . ' ' . $sem['Year']); ?></option>
              <?php endforeach; ?>
            </select>
          </form>
        <div class="card-head between">
          <div class="card-title">Schedule</div>
          <div class="row gap">
            <button class="btn outline"><i data-lucide="calendar-days"></i> Open Calendar</button>
            <button class="btn">Add Course</button>
          </div>
        </div>
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
              <?php if (empty($schedule)): ?>
                <tr><td colspan="5">Professor Out On Leave</td></tr>
              <?php else: ?>
                <?php foreach ($schedule as $row): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($row['CRN'] ?? ' - '); ?></td>
                    <td><?php echo htmlspecialchars($row['CourseName'] ?? ' - '); ?></td>
                    <td><?php echo htmlspecialchars($row['Days'] ?? ' - '); ?></td>
                    <td><?php echo htmlspecialchars($row['StartTime'] . ' – ' . $row['EndTime'] ?? ' - '); ?></td>
                    <td><?php echo htmlspecialchars($row['RoomID'] ?? ' - '); ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

  
    </section>

    <aside class="right">
      <div class="card">
        <div class="card-title">Quick Actions</div>
        <div class="quick-grid" id="facultyQuickLinks"></div>
      </div>

      <div class="tabs">
        <div class="tabs-list">
          <button class="tab active" data-tab="tasks">To-Dos</button>
          <button class="tab" data-tab="announcements">Announcements</button>
        </div>
        <div class="tab-panels">
          <div class="tab-panel active" id="panel-tasks">
            <div class="card">
              <div class="card-title">Upcoming Deadlines</div>
              <div id="facultyTasksList" class="vstack gap"></div>
              <div class="pt-8">
                <button class="btn"><i data-lucide="clipboard-list"></i> View All Tasks</button>
              </div>
            </div>
          </div>
          <div class="tab-panel" id="panel-announcements">
            <div class="card">
              <div class="card-title">Recent Announcements (Faculty & Admin)</div>
              <?php
              // --- COMBINED SQL QUERY using UNION ---
              $recent = $mysqli->prepare("
                  -- 1. Announcements from Admin
                  SELECT
                      a.Title,
                      a.Message,
                      a.DatePosted,
                      'System Announcement' AS CourseName, -- Placeholder for Admin
                      'Admin' AS SenderType                -- Type for identification
                  FROM AdminAnnouncements a
                  WHERE a.TargetGroup IN ('ALL', 'STUDENTS')

                  UNION ALL

                  -- 2. Announcements from Faculty (Course Specific)
                  SELECT
                      ca.Title,
                      ca.Message,
                      ca.DatePosted,
                      c.CourseName,                      -- Course context
                      'Faculty' AS SenderType            -- Type for identification
                  FROM CourseAnnouncements ca
                  JOIN CourseSection cs ON ca.CRN = cs.CRN
                  JOIN Course c ON cs.CourseID = c.CourseID
                  -- Filter by courses the student is enrolled in (assuming this is still desired)
                  JOIN StudentEnrollment se ON se.CRN = cs.CRN
                  WHERE se.StudentID = ? 
                  
                  ORDER BY DatePosted DESC
                  LIMIT 3
              ");
              
              // The CourseAnnouncements part still needs the StudentID to filter by enrollment
              // If you want *all* Faculty announcements (not just enrolled courses), remove the last JOIN/WHERE clauses and the bind_param.
              $recent->bind_param('i', $userId); 
              $recent->execute();
              $res = $recent->get_result();

              if ($res->num_rows > 0):
              ?>
                <ul style="list-style:none; padding:0; margin:0;">
                  <?php while ($a = $res->fetch_assoc()): ?>
                    <li style="border-bottom:1px solid var(--line); padding:10px 0;">
                      <strong><?= htmlspecialchars($a['Title']) ?></strong>
                      <span style="color:var(--muted);"> 
                        — <?= htmlspecialchars($a['CourseName'] . " (" . $a['SenderType'] . ")") ?>
                      </span>
                      <div style="margin-top:4px;"><?= nl2br(htmlspecialchars($a['Message'])) ?></div>
                      <small style="color:var(--muted);">Posted <?= htmlspecialchars($a['DatePosted']) ?></small>
                    </li>
                  <?php endwhile; ?>
                </ul>
                <div style="text-align:right; margin-top:10px;">
                  <a href="announcements.php" class="btn outline">View All Announcements →</a>
                </div>
              <?php else: ?>
                <p>No recent announcements from faculty or administration.</p>
              <?php endif;
              $recent->close();
              ?>
            </div>
          </div>
        </div>
      </div>

      <div class="grid-two sm-one">
        <div class="card">
          <div class="card-title">Recent Messages</div>
              <?php
              $recent_messages = $mysqli->prepare("
                  SELECT m.Title, m.Message, m.DatePosted, su.Email
                  FROM Messages m
                  JOIN Users su ON m.SenderEmail = su.Email
                  WHERE m.RecipientEmail = (SELECT Email FROM Users WHERE UserID = ?)
                  ORDER BY m.DatePosted DESC
              ");
              $recent_messages->bind_param('i', $userId);
              $recent_messages->execute();
              $res_messages = $recent_messages->get_result();

              if ($res_messages->num_rows > 0):
              ?>
                <ul style="list-style:none; padding:0; margin:0;">
                  <?php while ($m = $res_messages->fetch_assoc()): ?>
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
              $recent_messages->close();
              ?>
            </div>
        </div>
    </aside>
  </main>

  <footer class="footer">
    © <span id="year"></span> Northport University • All rights reserved • <a href="#" class="link">Privacy</a>
  </footer>

  <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
  <script>
    // Create icons on load
    lucide.createIcons();
    // Set footer year
    document.getElementById('year').textContent = new Date().getFullYear();
    // Theme toggle
    const themeToggle = document.getElementById('themeToggle');
    themeToggle.addEventListener('click', () => {
      const root = document.documentElement;
      const current = root.getAttribute('data-theme') || 'light';
      root.setAttribute('data-theme', current === 'light' ? 'dark' : 'light');
      themeToggle.querySelector('i').setAttribute('data-lucide', current === 'light' ? 'sun' : 'moon');
      lucide.createIcons();
    });
    // Tabs
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
    // Quick links injection
    const qlinks = <?php echo json_encode($quickLinks); ?>;
    const qlContainer = document.getElementById('facultyQuickLinks');
    qlinks.forEach(link => {
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

    // Announcements
    const announcements = <?php echo json_encode($announcements); ?>;
    const annList = document.getElementById('facultyAnnList');
    announcements.forEach(ann => {
      const item = document.createElement('div');
      item.className = 'vstack';
      const title = document.createElement('strong');
      title.textContent = ann.title;
      const body = document.createElement('span');
      body.className = 'muted';
      body.textContent = ann.body;
      const date = document.createElement('span');
      date.className = 'muted small';
      date.textContent = ann.date;
      item.appendChild(title);
      item.appendChild(body);
      item.appendChild(date);
      annList.appendChild(item);
    });
    // Messages
    const msgs = <?php echo json_encode($messages); ?>;
    const msgList = document.getElementById('facultyMsgList');
    msgs.forEach(msg => {
      const item = document.createElement('div');
      item.className = 'row between small';
      item.innerHTML = '<span><strong>' + msg.from + ':</strong> ' + msg.subject + '</span><span class="muted">' + msg.time + '</span>';
      msgList.appendChild(item);
    });
  </script>
</body>
</html>
