<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['user_id'])) {
    redirect(PROJECT_ROOT . "/login.html");
}

$userId = $_SESSION['user_id'];
$majorID = $_GET['majorID'] ?? null;
$majorName = $_GET['MajorName'] ?? 'Major';
if (!$majorID) die("No majorID provided.");

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

$major_requirement_sql = "SELECT mr.MajorID, m.MajorName, mr.CourseID, mr.RequirementDescription, mr.RequirementType, mr.CreditsRequired
                       FROM MajorRequirement mr
                       JOIN Major m ON mr.MajorID = m.MajorID
                       WHERE mr.MajorID = ?";

$major_requirement_stmt = $mysqli->prepare($major_requirement_sql);
$major_requirement_stmt->bind_param("i", $majorID);
$major_requirement_stmt->execute();
$major_requirement_res = $major_requirement_stmt->get_result();
$major_requirement = $major_requirement_res->fetch_all(MYSQLI_ASSOC);
$major_requirement_stmt->close();

$userRole = strtolower($_SESSION['role'] ?? '');
switch ($userRole) {
    case 'faculty':
        $dashboard = 'faculty_dashboard.php';
        $profile = 'faculty_profile.php';
        break;
    case 'admin':
        if (($_SESSION['admin_type'] ?? '') === 'update') {
            $dashboard = 'update_admin_dashboard.php';
            $profile = 'admin_profile.php';
        } else {
            $dashboard = 'view_admin_dashboard.php';
            $profile = 'admin_profile.php';
        }
        break;
    case 'student':
        $dashboard = 'student_dashboard.php';
        $profile = 'student_profile.php';
        break;
    case 'statstaff':
        $dashboard = 'statstaff_dashboard.php';
        $profile = 'admin_profile.php';
        break;
    default:
        $dashboard = 'login.html'; // fallback
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
<title>Major Requirements</title>
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
      <span class="pill">Major Requirements</span>
    </div>
    <div class="top-actions">
      <div class="search">
        <i class="search-icon" data-lucide="search"></i>
        <input type="text" placeholder="Search courses, people, anything…" />
      </div>
      <button class="icon-btn" aria-label="Notifications" a href = "announcements.php"><i data-lucide="bell"></i></button>
      <button id="themeToggle" class="icon-btn" aria-label="Toggle theme"><i data-lucide="moon"></i></button>
      <div class="divider"></div>
      <div class="crumb"><a href="viewDirectory.php" aria-label="Back to Directory">← Back to Directory</a></div>
    </div>

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

  <main class="page">
    <section class="hero card">
      <div class="card-head between">
        <div>
          <h2 class="card-title">View <?= htmlspecialchars($majorName) ?>  Requirements</h2>
        </div>
      </div>
    </section>
  </main>

    <section>
      <div class="hero card">
        <table id="majorRequirementsTable" cellpadding="10" cellspacing="50">
          <thead><tr><th>MajorID</th><th>MajorName</th><th>CourseID</th><th>Description</th><th>Type</th><th>Credits</th></tr></thead>
            <tbody id="majorRequirementsBody">
              <?php if (!empty($major_requirement)): ?>
                <?php foreach ($major_requirement as $mr): ?>
                  <tr>
                    <td><?= htmlspecialchars($mr['MajorID']) ?> </td>
                    <td><?= htmlspecialchars($mr['MajorName']) ?></td>
                    <td><?= htmlspecialchars($mr['CourseID']) ?></td>
                    <td><?= htmlspecialchars($mr['RequirementDescription']) ?></td>
                    <td><?= htmlspecialchars($mr['RequirementType']) ?></td>
                    <td><?= htmlspecialchars($mr['CreditsRequired']) ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr><td colspan="6">No Requirements found.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
      </div>
    </section>
<footer class="footer">© <span id="year"></span> Northport University • All rights reserved</footer>
</body>
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
</script>
</html>
