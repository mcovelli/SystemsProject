<?php
session_start();
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['user_id'])) {
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
$user = $res->fetch_assoc();
$stmt->close();

$userRole = strtolower($_SESSION['role'] ?? '');
switch ($userRole) {
    case 'student':
        $dashboard = 'student_dashboard.php';
        $profile = 'student_profile.php';
        break;
    case 'faculty':
        $dashboard = 'faculty_dashboard.php';
        $profile = 'faculty_profile.php';
        break;
    case 'admin':
        // if you have update/view admin types:
        if (($_SESSION['admin_type'] ?? '') === 'update') {
            $dashboard = 'update_admin_dashboard.php';
            $profile = 'admin_profile.php';
        } else {
            $dashboard = 'view_admin_dashboard.php';
            $profile = 'admin_profile.php';
        }
        break;
    case 'statstaff':
        $dashboard = 'statstaff_dashboard.php';
        $profile = 'statstaff_profile.php';
        break;
    default:
        $dashboard = 'login.html';
        $profile = 'login.html';
}

// Placeholder quick links, tasks, announcements and messages
$quickLinks = [
    ['label' => 'View Faculty', 'href' => 'ViewFaculty.php',       'icon' => 'file-text'],
    ['label' => 'View Courses',      'href' => 'ViewCourses.php',           'icon' => 'book'],
    ['label' => 'View Sections',   'href' => 'ViewCourseSections.php',                      'icon' => 'book-open'],
    ['label' => 'View Departments',     'href' => 'ViewDepartments.php',                      'icon' => 'mail'],
    ['label' => 'View Programs',      'href' => 'ViewPrograms.php',                      'icon' => 'brain'],
    ['label' => 'View Majors',      'href' => 'ViewMajors.php',                      'icon' => 'brain'],
    ['label' => 'View Minors',      'href' => 'ViewMinors.php',                      'icon' => 'brain']
];


if ($userRole === 'admin') {

    $quickLinks[] = [
        'label' => 'View Students',
        'href'  => 'ViewStudents.php',
        'icon'  => 'pencil'
    ];

    $quickLinks[] = [
        'label' => 'View All Users',
        'href'  => 'ViewUsers.php',
        'icon'  => 'user'
    ];
}

$initials = substr($user['FirstName'], 0, 1) . substr($user['LastName'], 0, 1);
?>


<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>View Directory</title>
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
      <span class="pill">View Directory</span>
    </div>
    <div class="top-actions">
      <div class="search">
        <i class="search-icon" data-lucide="search"></i>
        <input type="text" placeholder="Search courses, people, anything…" />
      </div>
      <button class="icon-btn" aria-label="Notifications" a href = "announcements.php"><i data-lucide="bell"></i></button>
      <button id="themeToggle" class="icon-btn" aria-label="Toggle theme"><i data-lucide="moon"></i></button>
      <div class="divider"></div>

    <div class="avatar" aria-hidden="true"><span id="initials"><?php echo $initials ?: 'NU'; ?></span></div>
        <div class="user-meta"><div class="name"><?php echo htmlspecialchars($user['UserType']) ?></div></div>
        <div class="menu">
          <button>☰ Menu</button>
          <div class="menu-content">
            <a href="<?= htmlspecialchars($dashboard) ?>">Dashboard</a>
            <a href="<?= htmlspecialchars($profile) ?>">Profile</a>
            <a href="logout.php">Logout</a>
          </div>
        </div>
      </div>
    </div>
  </header>

  <main class="container">
      <div class="card">
        <div class="card-title">Quick Actions</div>
        <div class="quick-grid" id="adminQuickLinks"></div>
      </div>
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
