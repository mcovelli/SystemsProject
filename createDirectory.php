<?php
session_start();
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['user_id']) || 
  ($_SESSION['role'] ?? '') !== 'admin' ||
($_SESSION['admin_type'] ?? '') !== 'update') {
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
        $dashboard = 'login.html'; // fallback
}

// Placeholder quick links, tasks, announcements and messages
$quickLinks = [
    ['label' => 'Create User', 'href' => 'CreateUsers.php',       'icon' => 'file-text'],
    ['label' => 'Create Courses',      'href' => 'CreateCourses.php',           'icon' => 'book'],
    ['label' => 'Create Sections',   'href' => 'CreateCourseSections.php',                      'icon' => 'book-open'],
    ['label' => 'Create Departments',     'href' => 'CreateDepartments.php',                      'icon' => 'mail'],
    ['label' => 'Create Majors/Minors',      'href' => 'CreateMajorsMinors.php',                      'icon' => 'check'],
    ['label' => 'Create Programs',      'href' => 'CreatePrograms.php',                      'icon' => 'brain'],
    ['label' => 'Create Requirements',      'href' => 'CreateRequirements.php',                      'icon' => 'list']
];

$updateLinks = [
  ['label' => 'Update User', 'href' => 'UpdateUsers.php',       'icon' => 'file-text'],
    ['label' => 'Update Courses',      'href' => 'UpdateCourses.php',           'icon' => 'book'],
    ['label' => 'Update Sections',   'href' => 'UpdateCourseSections.php',                      'icon' => 'book-open'],
    ['label' => 'Update Departments',     'href' => 'UpdateDepartments.php',                      'icon' => 'mail'],
    ['label' => 'Update Majors/Minors',      'href' => 'UpdateMajorsMinors.php',                      'icon' => 'check'],
    ['label' => 'Update Programs',      'href' => 'UpdatePrograms.php',                      'icon' => 'brain'],
    ['label' => 'Update Requirements',      'href' => 'UpdateRequirements.php',                      'icon' => 'list']

];

$deleteLinks = [
  ['label' => 'Delete User', 'href' => 'DeleteUsers.php',       'icon' => 'file-text'],
    ['label' => 'Delete Courses',      'href' => 'DeleteCourses.php',           'icon' => 'book'],
    ['label' => 'Delete Sections',   'href' => 'DeleteCourseSections.php',                      'icon' => 'book-open'],
    ['label' => 'Delete Departments',     'href' => 'DeleteDepartments.php',                      'icon' => 'mail'],
    ['label' => 'Delete Majors/Minors',      'href' => 'DeleteMajorsMinors.php',                      'icon' => 'check'],
    ['label' => 'Delete Programs',      'href' => 'DeletePrograms.php',                      'icon' => 'brain'],
    ['label' => 'Delete Requirements',      'href' => 'DeleteRequirements.php',                      'icon' => 'list']

];

$otherLinks = [
  ['label' => 'Assign Advisor', 'href' => 'assign_advisor.php',       'icon' => 'file-text'],
    ['label' => 'Declare Major',      'href' => 'DeclareMajor.php',           'icon' => 'book'],
    ['label' => 'Declare Minor',   'href' => 'DeclareMinor.php',                      'icon' => 'book-open'],
    ['label' => 'Declare Program',     'href' => 'DeclareProgram.php',                      'icon' => 'mail']

];

$initials = substr($admin['FirstName'], 0, 1) . substr($admin['LastName'], 0, 1);
?>


<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Create Directory</title>
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
      <span class="pill">Create Directory</span>
    </div>
    <div class="top-actions">
      <div class="search">
        <i class="search-icon" data-lucide="search"></i>
        <input type="text" placeholder="Search courses, people, anything…" />
      </div>
      <button class="icon-btn" aria-label="Notifications" a href="announcements.php"><i data-lucide="bell"></i></button>
      <button id="themeToggle" class="icon-btn" aria-label="Toggle theme"><i data-lucide="moon"></i></button>
      <div class="divider"></div>
    </div>

    <div class="avatar" aria-hidden="true"><span id="initials"><?php echo $initials ?: 'NU'; ?></span></div>
        <div class="user-meta"><div class="name"><?php echo htmlspecialchars($admin['UserType']) ?></div></div>
        <div class="dropdown">
          <button>☰ Menu</button>
          <div class="dropdown-content">
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
        <div class="card-title">Create Actions</div>
        <div class="quick-grid" id="adminQuickLinks"></div>
      </div>
  </main>

    <main class="container">
      <div class="card">
        <div class="card-title">Update Actions</div>
        <div class="quick-grid" id="adminUpdateLinks"></div>
      </div>
  </main>

  <main class="container">
      <div class="card">
        <div class="card-title">Delete Actions</div>
        <div class="quick-grid" id="adminDeleteLinks"></div>
      </div>
  </main>

   <main class="container">
      <div class="card">
        <div class="card-title">Other Actions</div>
        <div class="quick-grid" id="adminOtherLinks"></div>
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

    // Insert update links
    const updateLinks = <?php echo json_encode($updateLinks); ?>;
    const ulContainer = document.getElementById('adminUpdateLinks');
    updateLinks.forEach(link => {
      const div = document.createElement('div');
      div.className = 'ul';
      div.addEventListener('click', () => {
        if (link.href) window.location.href = link.href;
      });
      const icon = document.createElement('i');
      icon.setAttribute('data-lucide', link.icon);
      const span = document.createElement('span');
      span.textContent = link.label;
      div.appendChild(icon);
      div.appendChild(span);
      ulContainer.appendChild(div);
    });
    lucide.createIcons();

    // Insert delete links
    const deleteLinks = <?php echo json_encode($deleteLinks); ?>;
    const dlContainer = document.getElementById('adminDeleteLinks');
    deleteLinks.forEach(link => {
      const div = document.createElement('div');
      div.className = 'dl';
      div.addEventListener('click', () => {
        if (link.href) window.location.href = link.href;
      });
      const icon = document.createElement('i');
      icon.setAttribute('data-lucide', link.icon);
      const span = document.createElement('span');
      span.textContent = link.label;
      div.appendChild(icon);
      div.appendChild(span);
      dlContainer.appendChild(div);
    });
    lucide.createIcons();

    // Insert other links
    const otherLinks = <?php echo json_encode($otherLinks); ?>;
    const olContainer = document.getElementById('adminOtherLinks');
    otherLinks.forEach(link => {
      const div = document.createElement('div');
      div.className = 'ol';
      div.addEventListener('click', () => {
        if (link.href) window.location.href = link.href;
      });
      const icon = document.createElement('i');
      icon.setAttribute('data-lucide', link.icon);
      const span = document.createElement('span');
      span.textContent = link.label;
      div.appendChild(icon);
      div.appendChild(span);
      olContainer.appendChild(div);
    });
    lucide.createIcons();

  </script>
</body>
</html>
