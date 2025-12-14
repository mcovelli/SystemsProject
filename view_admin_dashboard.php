<?php
session_start();
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['user_id']) || 
  ($_SESSION['role'] ?? '') !== 'admin' ||
($_SESSION['admin_type'] ?? '') !== 'view') {
    redirect(PROJECT_ROOT . "/login.html");
}

$userId = $_SESSION['user_id'];

$mysqli = get_db();
$mysqli->set_charset('utf8mb4');

$sql = "SELECT u.UserID, u.FirstName, u.LastName, u.Email, u.UserType, u.Status, u.DOB, a.SecurityType, a.AdminID
        FROM Users u JOIN Admin a ON u.UserID = a.AdminID WHERE UserID = ? LIMIT 1";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$res = $stmt->get_result();
$admin = $res->fetch_assoc();
$stmt->close();

if (!$admin) {
    echo "<p>Profile not found for your account.</p>";
    exit;
}

// Placeholder quick links, tasks, announcements and messages
$quickLinks = [
    ['label' => 'Profile',      'href' => 'admin_profile.php',    'icon' => 'user'],
    ['label' => 'Messages',     'href' => 'messages.php',                      'icon' => 'mail'],
    ['label' => 'View Directory', 'href' => 'viewDirectory.php',       'icon' => 'file-text'],
    ['label' => 'Announcements',      'href' => 'send_announcement.php',                'icon' => 'megaphone'],
];

?>

<!doctype html>
<html lang="en" data-theme="light">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Admin Dashboard • Northport University</title>
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
      <span class="pill">Admin Portal</span>
    </div>
    <div class="top-actions">
      <div class="search">
        <i class="search-icon" data-lucide="search"></i>
        <input type="text" placeholder="Search courses, people, anything…" />
      </div>
      <div id="notificationArea" class="notification-area"></div>
      <button id="themeToggle" class="icon-btn" aria-label="Toggle theme"><i data-lucide="moon"></i></button>
      <div class="divider"></div>
      <div class="user">
        <img class="avatar" src="https://i.pravatar.cc/64?img=20" alt="avatar" />
        <div class="user-meta">
          <div class="name"><?php echo htmlspecialchars($admin['FirstName'] . ' ' . $admin['LastName']); ?></div>
          <div class="sub"><?php echo htmlspecialchars($admin['SecurityType']); ?></div>
        </div>
        <div class="header-left">
          <div class="menu">
            <button>☰ Menu</button>
            <div class="menu-content">
              <a href="admin_profile.php">Profile</a>
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
            <div class="muted">Admin ID</div>
            <i data-lucide="id-card"></i>
          </div>
          <div class="stat-value"><?php echo htmlspecialchars($admin['AdminID']); ?></div>
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
            <div class="muted">Security Level</div>
            <i data-lucide="user"></i>
          </div>
          <div class="stat-value"><?php echo htmlspecialchars($admin['SecurityType']); ?></div>
        </div>

        <div class="card stat">
          <div class="card-head">
            <div class="muted">Date of Birth</div>
            <i data-lucide="calendar"></i>
          </div>
          <div class="stat-value"><?php echo $admin['DOB'] ? date('m/d/Y', strtotime($admin['DOB'])) : 'N/A'; ?></div>
        </div>

      </div>
    </section>

    <aside class="right">
      <div class="card">
        <div class="card-title">Quick Actions</div>
        <div class="quick-grid" id="adminQuickLinks"></div>
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
              <div id="adminTasksList" class="vstack gap"></div>
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
                  WHERE a.TargetGroup IN ('ALL', 'ADMINS')

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
    </aside>
  </main>

<footer class="footer">© <span id="year"></span> Northport University • All rights reserved</footer>

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

    // Insert quick links
    const quickLinks = <?php echo json_encode($quickLinks); ?>;
    const qlContainer = document.getElementById('adminQuickLinks');
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

  </script>
</body>
</html>