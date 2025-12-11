<?php
session_start();
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'statstaff') {
    redirect(PROJECT_ROOT . "/login.html");
}

$userId = $_SESSION['user_id'];

$mysqli = get_db();
$mysqli->set_charset('utf8mb4');

$sql = "SELECT UserID, FirstName, LastName, Email, UserType, Status, DOB
        FROM Users WHERE UserID = ? LIMIT 1";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$res = $stmt->get_result();
$statstaff = $res->fetch_assoc();
$stmt->close();

if (!$statstaff) {
    echo "<p>Profile not found for your account.</p>";
    exit;
}

//AVG Campus GPA
$gpa_sql = "SELECT AVG(CumulativeGPA) AS AvgGPA
        FROM DegreeAudit WHERE CumulativeGPA > 0";
$gpa_stmt = $mysqli->prepare($gpa_sql);
$gpa_stmt->execute();

$gpa_res = $gpa_stmt->get_result();
$gpa_row = $gpa_res->fetch_assoc();

$averageGPA = $gpa_row['AvgGPA'] ?? 0;

$gpa_stmt->close();


//Num male Students
$male_sql = "SELECT Count(*) AS Gender
        FROM Users WHERE Gender = 'M' AND UserType = 'Student'";
$male_stmt = $mysqli->prepare($male_sql);
$male_stmt->execute();

$male_res = $male_stmt->get_result();
$male_row = $male_res->fetch_assoc();

$numMaleStudents = $male_row['Gender'] ?? 'N/A';

$male_stmt->close();


//Num Female Students
$female_sql = "SELECT COUNT(*) AS Gender
        FROM Users WHERE Gender = 'F' AND UserType = 'Student'";
$female_stmt = $mysqli->prepare($female_sql);
$female_stmt->execute();

$female_res = $female_stmt->get_result();
$female_row = $female_res->fetch_assoc();

$numFemaleStudents = $female_row['Gender'] ?? 'N/A';

$female_stmt->close();


//Num Grad Students
$graduate_sql = "SELECT COUNT(*) AS StudentType
        FROM Student WHERE StudentType = 'Graduate'";
$graduate_stmt = $mysqli->prepare($graduate_sql);
$graduate_stmt->execute();

$graduate_res = $graduate_stmt->get_result();
$graduate_row = $graduate_res->fetch_assoc();

$numGradStudents = $graduate_row['StudentType'] ?? 'N/A';

$graduate_stmt->close();

//Num Undergrad Students
$undergraduate_sql = "SELECT COUNT(*) AS StudentType
        FROM Student WHERE StudentType = 'Undergraduate'";
$undergraduate_stmt = $mysqli->prepare($undergraduate_sql);
$undergraduate_stmt->execute();

$undergraduate_res = $undergraduate_stmt->get_result();
$undergraduate_row = $undergraduate_res->fetch_assoc();

$numUGStudents = $undergraduate_row['StudentType'] ?? 'N/A';

$undergraduate_stmt->close();

//Num Faculty
$faculty_sql = "SELECT COUNT(*) AS Faculty
        FROM Users WHERE UserType = 'Faculty'";
$faculty_stmt = $mysqli->prepare($faculty_sql);
$faculty_stmt->execute();

$faculty_res = $faculty_stmt->get_result();
$faculty_row = $faculty_res->fetch_assoc();

$numFaculty = $faculty_row['Faculty'] ?? 'N/A';

$faculty_stmt->close();

//Num Admin
$admin_sql = "SELECT COUNT(*) AS Admin
        FROM Users WHERE UserType = 'Admin'";
$admin_stmt = $mysqli->prepare($admin_sql);
$admin_stmt->execute();

$admin_res = $admin_stmt->get_result();
$admin_row = $admin_res->fetch_assoc();

$numAdmin = $admin_row['Admin'] ?? 'N/A';

$admin_stmt->close();

//Num Statstaff
$statstaff_sql = "SELECT COUNT(*) AS Stat
        FROM Users WHERE UserType = 'Statstaff'";
$statstaff_stmt = $mysqli->prepare($statstaff_sql);
$statstaff_stmt->execute();

$statstaff_res = $statstaff_stmt->get_result();
$statstaff_row = $statstaff_res->fetch_assoc();

$numStat = $statstaff_row['Stat'] ?? 'N/A';

$statstaff_stmt->close();

//Attendance
$attendance_sql = "SELECT
    SUM(CASE WHEN PresentAbsent = 'PRESENT' THEN 1 ELSE 0 END)
      / COUNT(*) AS attendance_rate
FROM CourseSectionAttendance
";
$attendance_stmt = $mysqli->prepare($attendance_sql);
$attendance_stmt->execute();

$attendance_res = $attendance_stmt->get_result();
$attendance_row = $attendance_res->fetch_assoc();

$attendance = $attendance_row['attendance_rate'] ?? 'N/A';

$attendance_stmt->close();



?>
<!doctype html>
<html lang="en" data-theme="light">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Northport University — Stat Staff Dashboard</title>
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
      <span class="pill">Staff Portal</span>
      <h3>Welcome, <?php echo htmlspecialchars(
    $statstaff['FirstName'] . ' ' . $statstaff['LastName']); ?></h3>
    </div>
    <div class="top-actions">
      <div class="search">
      <button id="themeToggle" class="icon-btn" aria-label="Toggle theme"><i data-lucide="moon"></i></button>
      <div class="divider"></div>
      <div class="user">
        <img class="avatar" src="https://i.pravatar.cc/64?img=12" alt="avatar" />
        <div class="user-meta">
          <div class="name"><?php echo htmlspecialchars(
            $statstaff['FirstName'] . ' ' . $statstaff['LastName']); ?></div>
          <div class="sub"></div>
        </div>
        <div class="header-left">
        <div class="menu">
      <button>☰ Menu</button>
        <div class="menu-content">
        <a href="statstaff_profile.php">Profile</a>
        <a href="messages.php">Messages</a>
        <a href="viewDirectory.php">View Directory</a>
        <a href="logout.php">Logout</a>
      </div>
  </div>
    </div>
  </header>

  <main class="container">

    <section class="left">
      <div class="stats">


        <div class="card stat">
          <div class="card-head">
            <div class="muted">Average GPA</div>
            <i data-lucide="clipboard-list"></i>
          </div>

          <div class="stat-value"><?= number_format($averageGPA, 2) ?></div>

          <div class="sub muted">Campus-wide average</div><br>

          <div class="muted">Attendance Rate</div>
          <div class="stat-value"><?= ($attendance * 100) . '%' ?></div>

          <div class="sub muted">Campus-wide average</div>
        </div>

        <div class="card stat">
          <div class="card-head">
            <div class="muted">Number of Male Students</div>
            <i data-lucide="clipboard-list"></i>
          </div>

          <div class="stat-value"><?= $numMaleStudents ?></div>

          <div class="sub muted">Campus-wide</div><br>

          <div class="muted">Number of Female Students</div>

          <div class="stat-value"><?= $numFemaleStudents ?></div>

          <div class="sub muted">Campus-wide</div>
        </div>

        <div class="card stat">
          <div class="card-head">
            <div class="muted">Number of Grad Students</div>
            <i data-lucide="clipboard-list"></i>
          </div>

          <div class="stat-value"><?= $numGradStudents ?></div>

          <div class="sub muted">Campus-wide</div><br>

          <div class="muted">Number of Undergraduate Students</div>

          <div class="stat-value"><?= $numUGStudents ?></div>

          <div class="sub muted">Campus-wide</div>
        </div>

        <div class="card stat">
          <div class="card-head">
            <div class="muted">Number of Faculty</div>
            <i data-lucide="clipboard-list"></i>
          </div>

          <div class="stat-value"><?= $numFaculty ?></div>

          <div class="sub muted">Campus-wide</div><br>

          <div class="muted">Number of Admin</div>

          <div class="stat-value"><?= $numAdmin ?></div>

          <div class="sub muted">Campus-wide</div>
        </div>
    </section>
    <br>
    <aside class="right">

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
      </div>
    </aside>
  </main>
  <footer class="footer">
    © <span id="year"></span> Northport University • All rights reserved • <a href="#" class="link">Privacy</a>
  </footer>
  </body>
  <script src = "https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
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
      if (window.lucide) lucide.createIcons();
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

    </script>
 </html>

  