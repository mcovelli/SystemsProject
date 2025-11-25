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


$sql = "SELECT d.DeptID, d.DeptName, d.Email, d.Phone, d.RoomID, CONCAT(u.FirstName , ' ' , u.LastName) AS ChairName, d.ChairID FROM Department d JOIN Users u ON d.ChairID = u.UserID";
$stmt = $mysqli->prepare($sql);
$stmt->execute();
$res = $stmt->get_result();
$depts = $res->fetch_all(MYSQLI_ASSOC);
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
<title>Department Directory</title>
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
      <span class="pill">Department Directory</span>
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
          <h2 class="card-title">View All Departments</h2>
        </div>
      </div>
    </div>

      <div class="table-wrap">
      <table id="coursesTable" border ="1" cellpadding="5" cellspacing="0">
        <thead><tr><th>Department</th><th>Email</th><th>Phone #</th><th>Office Location</th><th>Dept Chair</th></tr></thead>
          <tbody id="coursesBody">
            <?php if (!empty($depts)): ?>
              <?php foreach ($depts as $d): ?>
                <tr>
                  <td><a href="dept_profile.php?deptID=<?= urlencode($d['DeptID']) ?>">
                      <?= htmlspecialchars($d['DeptName']) ?> </a></td>
                  <td>
                    <a href="mailto:<?= htmlspecialchars($d['Email']) ?>"><?= htmlspecialchars($d['Email']) ?></a>
                  </td>
                  <td><?= htmlspecialchars($d['Phone']) ?></td>
                  <td><?= htmlspecialchars($d['RoomID']) ?></td>
                  <?php if ($userRole === 'admin'): ?>
                  <td><a href="faculty_profile.php?facultyID=<?= urlencode($d['ChairID']) ?>">
                        <?= htmlspecialchars($d['ChairName']) ?> </a></td>
                  <?php else: ?> <td><?= htmlspecialchars($d['ChairName']) ?></td>
                <?php endif; ?>
                <td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr><td colspan="6">No courses found.</td></tr>
            <?php endif; ?>
              </tbody>
            </table>
        </div>
    </section>

    

  </body>

  <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>

     <script>
      lucide.createIcons();
</script>
</html>
