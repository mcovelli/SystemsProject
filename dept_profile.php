<?php
session_start();
require_once __DIR__ . '/config.php';
ini_set('display_errors', 1);
error_reporting(E_ALL);


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

$mysqli = get_db();
$mysqli->set_charset('utf8mb4');

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

$deptId = isset($_GET['deptID']) ? intval($_GET['deptID']) : 0;
if ($deptId <= 0) {
    die("Invalid Department ID");
}


$sql = "
    SELECT 
        d.DeptID,
        d.DeptName,
        d.Email,
        d.Phone,
        d.RoomID,
        d.ChairID,
        CONCAT(u.FirstName, ' ', u.LastName) AS ChairName
    FROM Department d
    JOIN Users u ON d.ChairID = u.UserID
    WHERE d.DeptID = ?
    LIMIT 1
";

$stmt = $mysqli->prepare($sql);
$stmt->bind_param("i", $deptId);
$stmt->execute();
$dept = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$dept) {
    die("Department not found.");
}

$deptName = $dept['DeptName'];

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
<title>Department Profile</title>
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
      <span class="pill">Department Profile</span>
    </div>
    <div class="top-actions">
      <div class="search">
        <i class="search-icon" data-lucide="search"></i>
        <input type="text" placeholder="Search courses, people, anything…" />
      </div>
      <button class="icon-btn" aria-label="Notifications" a href = announcements.php><i data-lucide="bell"></i></button>
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
          <h2 class="card-title"><?= htmlspecialchars($deptName) ?> Profile</h2>
        </div>
      </div>
    </div>
  </section>

  <section>
      <p><strong>Chair: </strong><?php echo htmlspecialchars($dept['ChairName']) ?> </p>
      <p><strong>Office: </strong><?php echo htmlspecialchars($dept['RoomID']) ?> </p>
      <p><strong>Email: </strong><a href="mailto:<?php echo htmlspecialchars($dept['Email']) ?> "><?php echo htmlspecialchars($dept['Email']) ?> </a></p>
      <p><strong>Phone: </strong><?php echo htmlspecialchars($dept['Phone']) ?> </p>



  
  </section>
  <br>
  </main>
  <footer class="footer">© <span id="year"></span> Northport University</footer>

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
    document.getElementById('year').textContent = new Date().getFullYear();
    </script>
  </body>
</html>
