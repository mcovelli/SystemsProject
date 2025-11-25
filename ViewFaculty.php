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

$search = $_GET['search'] ?? '';

$sql = "
SELECT 
    f.FacultyID,
    CONCAT(fu.FirstName, ' ', fu.LastName) AS FacultyName,
    GROUP_CONCAT(d.DeptName ORDER BY d.DeptName SEPARATOR ', ') AS DeptNames,
    fd.DeptID, d.Phone, fu.Email, f.OfficeID, f.Ranking
FROM Faculty f
JOIN Users fu ON f.FacultyID = fu.UserID
JOIN Faculty_Dept fd ON f.FacultyID = fd.FacultyID
JOIN Department d ON fd.DeptID = d.DeptID
";

if (!empty($search)) {
    $sql .= " 
    GROUP BY f.FacultyID, fu.FirstName, fu.LastName, fu.Email, f.OfficeID
    HAVING 
        FacultyName LIKE CONCAT('%', ?, '%')
        OR DeptNames LIKE CONCAT('%', ?, '%')
    ";
} else {
    $sql .= "
    GROUP BY f.FacultyID, fu.FirstName, fu.LastName, fu.Email, f.OfficeID
    ";
}

$sql .= " ORDER BY f.FacultyID ASC";


$stmt = $mysqli->prepare($sql);

if (!empty($search)) {
    $stmt->bind_param("ss", $search, $search);
}

$stmt->execute();
$res = $stmt->get_result();
$faculty = $res->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$userRole = strtolower($_SESSION['role'] ?? '');
switch ($userRole) {
    case 'student':
        $dashboard = 'student_dashboard.php';
        break;
    case 'faculty':
        $dashboard = 'faculty_dashboard.php';
        break;
    case 'admin':
        // if you have update/view admin types:
        if (($_SESSION['admin_type'] ?? '') === 'update') {
            $dashboard = 'update_admin_dashboard.php';
        } else {
            $dashboard = 'view_admin_dashboard.php';
        }
        break;
    case 'statstaff':
        $dashboard = 'statstaff_dashboard.php';
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
<title>Faculty Directory</title>
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
      <span class="pill">Faculty Directory</span>
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

  <main class="page">
    <section class="hero card">
      <div class="card-head between">
        <div>
          <h2 class="card-title">View All Faculty</h2>
        </div>
      </div>

      <form method="GET" style="margin-bottom: 20px;">
        <label for="search">Search:</label>
        <input type="text" id="search" name="search"
         placeholder="Search name or department..."
         value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">

        <button type="submit">Search</button>
      </form>
  
    <div class="table-wrap">
      <table border="1" cellpadding="5" cellspacing="0">
        <thead>
          <tr>
            <th>Faculty Name</th>
            <th>Email</th>
            <th>Office Location</th>
            <th>Department</th>
            <th>Dept Phone</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($faculty)): ?>
            <?php foreach ($faculty as $f): ?>
              <tr>

                <?php if ($userRole === 'admin'): ?>
                <td><a href="faculty_profile.php?facultyID=<?= urlencode($f['FacultyID']) ?>">
                      <?= htmlspecialchars($f['FacultyName']) ?> </a></td>
                <?php else: ?> <td><?= htmlspecialchars($f['FacultyName']) ?></td>
              <?php endif; ?>
                <td>
                  <a href="mailto:<?= htmlspecialchars($f['Email']) ?>">
                    <?= htmlspecialchars($f['Email']) ?>
                  </a>
                </td>
                <td><?= htmlspecialchars($f['OfficeID']) ?></td>
                <td><?= htmlspecialchars($f['DeptNames']) ?></td>
                <td><?= htmlspecialchars($f['Phone']) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="5">No faculty found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
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
      lucide.createIcons();
    });
      // Fetch departments from get_departments.php
      fetch('get_departments.php')
        .then(response => response.json())
        .then(data => {
          const deptSelect = document.getElementById('dept');
          const selected = new URLSearchParams(window.location.search).get('dept');

          data.forEach(name => {
            const opt = document.createElement('option');
            opt.value = name.name;
            opt.textContent = name.name;
            if (name === selected) opt.selected = true;
            deptSelect.appendChild(opt);
          });
        })
    </script>
  </body>
</html>
