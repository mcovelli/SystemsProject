<?php
session_start();
require_once __DIR__ . '/config.php';
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['user_id']) || 
    ($_SESSION['role'] ?? '') !== 'admin') {
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
    UserID,
    CONCAT(FirstName, ' ', LastName) AS Name,
    CONCAT(HouseNumber, ' ', Street, ' ', City, ' ', State, ' ', Zip) AS Address,
    PhoneNumber,
    Email,
    Gender,
    DOB,
    UserType,
    Status
FROM Users
";

if (!empty($search)) {
    $sql .= "
    WHERE (
    FirstName LIKE CONCAT('%', ?, '%')
    OR LastName LIKE CONCAT('%', ?, '%')
    OR UserType LIKE CONCAT('%', ?, '%')
    OR UserID LIKE CONCAT('%', ?, '%')
)
    ";
}

$sql .= " ORDER BY UserID ASC";

$stmt = $mysqli->prepare($sql);

if (!empty($search)) {
    $stmt->bind_param("ssss", $search, $search, $search, $search);
}

$stmt->execute();
$res = $stmt->get_result();
$users = $res->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$userRole = strtolower($_SESSION['role'] ?? '');
switch ($userRole) {
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
    default:
        $dashboard = 'login.html';
        $profile = 'admin_profile.php';
        break;
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
<title>User Directory</title>
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
      <span class="pill">User Directory</span>
    </div>
    <div class="top-actions">
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
          <h2 class="card-title">View All Users</h2>
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
            <th>ID</th>
            <th>Name</th>
            <th>Address</th>
            <th>Phone</th>
            <th>Email</th>
            <th>Date of Birth</th>
            <th>Gender</th>
            <th>Type</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($users)): ?>
            <?php foreach ($users as $u): ?>
              <tr>
                <td><?= htmlspecialchars($u['UserID']) ?></td>
                <td><?= htmlspecialchars($u['Name']) ?></td>
                <td><?= htmlspecialchars($u['Address']) ?></td>
                <td><?= htmlspecialchars($u['PhoneNumber']) ?></td>
                <td><?= htmlspecialchars($u['Email']) ?></td>
                <td><?= htmlspecialchars($u['DOB']) ?></td>
                <td><?= htmlspecialchars($u['Gender']) ?></td>
                <td><?= htmlspecialchars($u['UserType']) ?></td>
                <td><?= htmlspecialchars($u['Status']) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="5">No Users found.</td></tr>
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
    </script>
  </body>
</html>
