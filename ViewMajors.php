<?php
session_start();
require_once __DIR__ . '/config.php';
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['user_id'])){
    redirect(PROJECT_ROOT . "/login.html");
}

$userId = $_SESSION['user_id'];

$mysqli = get_db();
$mysqli->set_charset('utf8mb4');

$usersql = "SELECT UserID, FirstName, LastName, Email, UserType, Status, DOB
        FROM Users WHERE UserID = ? LIMIT 1";
$userstmt = $mysqli->prepare($usersql);
$userstmt->bind_param("i", $userId);
$userstmt->execute();
$userres = $userstmt->get_result();
$user = $userres->fetch_assoc();
$userstmt->close();

$selectedDept = $_GET['dept'] ?? '';

$sql = "SELECT m.MajorID, m.MajorName, m.DeptID, d.DeptName, d.Email, m.CreditsNeeded FROM Major m JOIN Department d ON m.DeptID = d.DeptID ";

if (!empty($selectedDept)) {
    $sql .= " WHERE d.DeptName = ?";
}

$stmt = $mysqli->prepare($sql);
if (!empty($selectedDept)) {
    $stmt->bind_param("s", $selectedDept);
}

$stmt->execute();
$res = $stmt->get_result();
$majors = $res->fetch_all(MYSQLI_ASSOC);
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
        $profile = 'admin_profile.php';
        break;
    default:
        $dashboard = 'login.html'; // fallback
        $profile = 'login.html';
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
<title>Major Directory</title>
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
      <span class="pill">Major Directory</span>
    </div>
    <div class="top-actions">
      <div class="search">
        <i class="search-icon" data-lucide="search"></i>
        <input type="text" placeholder="Search courses, people, anything…" />
      </div>
      <button class="icon-btn" aria-label="Notifications"><i data-lucide="bell"></i></button>
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
          <h2 class="card-title">View Majors</h2>
          <div class="sub muted">Filter By Department</div>
        </div>
      </div>
      <div style="margin-top:12px">
        <form method="GET" id="filterForm" style="margin-bottom: 20px;">
          <label for="dept">Department:</label>
          <select name="dept" id="dept">
            <option value="">-- All Departments --</option>
          </select>

          <button type="submit">Apply Filters</button>
        </form>
        <p>Click ID to pull up requirements</p>
    </div>

      <div class="table-wrap">
        <table id="majorsTable" border="1" cellpadding="5" cellspacing="0">
          <thead><tr><th>Major ID</th><th>Major Name</th><th>Department ID</th><th>Department Name</th><th>Credits Needed</th><th>Department Email</th></tr></thead>
            <tbody id="majorsBody">
              <?php if (!empty($majors)): ?>
                <?php foreach ($majors as $m): ?>
                  <tr>
                    <td><a href="ViewMajorRequirements.php?majorID=<?= urlencode($m['MajorID']) ?>&MajorName=<?= urlencode($m['MajorName']) ?>">
                      <?= htmlspecialchars($m['MajorID']) ?> </a></td>
                    <td><?= htmlspecialchars($m['MajorName']) ?></td>
                    <td><?= htmlspecialchars($m['DeptID']) ?></td>
                    <td><?= htmlspecialchars($m['DeptName']) ?></td>
                    <td><?= htmlspecialchars($m['CreditsNeeded']) ?></td>
                    <td><?= htmlspecialchars($m['Email']) ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                  <tr><td colspan="6">No majors found.</td></tr>
              <?php endif; ?>
              </tbody>
            </table>
        </div>
    </section>
    <footer class="footer">© <span id="year"></span> Northport University • All rights reserved</footer>
    </main>

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

    </script>

  </body>
</html>
