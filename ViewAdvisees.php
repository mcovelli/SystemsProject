<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

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

## pulls data from database for advisee
$student_sql = "SELECT 
                  a.StudentID, 
                  CONCAT(u.FirstName, ' ', u.LastName) AS StudentName, 
                  COALESCE(ftug.Year, ftg.Year, ptug.Year, ptg.Year) AS Year, s.StudentType, u.Email, 
                  CASE 
                      WHEN s.StudentType = 'Graduate' THEN p.ProgramName
                      ELSE m.MajorName
                  END AS MajorName, 
                  CASE 
                      WHEN s.StudentType = 'Graduate' THEN 'N/A'
                      ELSE mn.MinorName
                  END AS MinorName
                FROM Advisor a JOIN Users u ON a.StudentID =  u.UserID
                JOIN Student s ON a.StudentID = s.StudentID
                LEFT JOIN FullTimeUG ftug ON s.StudentID = ftug.StudentID
                LEFT JOIN FullTimeGrad ftg ON s.StudentID = ftg.StudentID
                LEFT JOIN PartTimeUG ptug ON s.StudentID = ptug.StudentID
                LEFT JOIN PartTimeGrad ptg ON s.StudentID = ptg.StudentID
                LEFT JOIN StudentMajor sm ON a.StudentID = sm.StudentID
                LEFT JOIN Major m ON sm.MajorID = m.MajorID
                LEFT JOIN StudentMinor smn ON a.StudentID = smn.StudentID
                LEFT JOIN Minor mn ON smn.MinorID = mn.MinorID
                LEFT JOIN Graduate g ON s.StudentID = g.StudentID
                LEFT JOIN Program p ON g.ProgramID = p.ProgramID
                WHERE a.FacultyID = ?";

$student_stmt = $mysqli->prepare($student_sql);
$student_stmt->bind_param("i", $userId);
$student_stmt->execute();
$student_res = $student_stmt->get_result();
$advisee = $student_res->fetch_all(MYSQLI_ASSOC);
$student_stmt->close();

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
<title>Advisee Directory</title>
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
      <span class="pill">Advisee Directory</span>
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
          <h2 class="card-title">View All Advisees</h2>
        </div>
      </div>
    </section>
  </main>

    <section>
      <div class="hero card">
        <table id="adviseesTable" cellpadding="10" cellspacing="50">
          <thead><tr><th>StudentID</th><th>Student Name</th><th>Year</th><th>Type</th><th>Email</th><th>Major</th><th>Minor</th></tr></thead>
            <tbody id="adviseesBody">
              <?php if (!empty($advisee)): ?>
                <?php foreach ($advisee as $a): ?>
                  <tr>
                    <td>
                      <a href="student_profile.php?studentID=<?= urlencode($a['StudentID']) ?>">
                      <?= htmlspecialchars($a['StudentID']) ?> </a>
                    </td>
                    <td><?= htmlspecialchars($a['StudentName']) ?></td>
                    <td><?= htmlspecialchars($a['Year']) ?></td>
                    <td><?= htmlspecialchars($a['StudentType']) ?></td>
                    <td><a href="mailto:<?= htmlspecialchars($a['Email']) ?>"><?= htmlspecialchars($a['Email']) ?></a></td>
                    <td><?= htmlspecialchars($a['MajorName'] ?? 'Undeclared' )?></td>
                    <td><?= htmlspecialchars($a['MinorName'] ?? 'Undeclared' )?></td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr><td colspan="6">No Advisees found.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
      </div>
    </section>
    <footer class="footer">© <span id="year"></span> Northport University</footer>
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
