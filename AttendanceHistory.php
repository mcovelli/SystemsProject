<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/config.php';

$mysqli = get_db();
$mysqli->set_charset('utf8mb4');

if (
    !isset($_SESSION['user_id']) ||
    (
        ($_SESSION['role'] ?? '') !== 'faculty' &&
        ($_SESSION['role'] ?? '') !== 'admin' &&
        ($_SESSION['role'] ?? '') !== 'student')
    ) {
    header('Location: login.php');
    exit;
}
$userId = $_SESSION['user_id'];
$role = strtolower(trim($_SESSION['role'] ?? ''));

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

$studentID = NULL;
$studentName = NULL;


if (($role === 'admin' || $role === 'faculty') && !empty($_GET['studentID'])) {
    // Admin/faculty can view a specific student
    $studentID = (int)$_GET['studentID'];

} elseif ($role === 'student') {
    // Student always views their own attendance
    $studentID = $userId;

} elseif ($role === 'admin') {
    // Admin with no studentID -> send back to directory
    redirect(PROJECT_ROOT . "/viewDirectory.php");

} else {
    // Shouldn't get here, but safety net
    redirect(PROJECT_ROOT . "/login.html");
}

$search = $_GET['search'] ?? '';

$sql = "
SELECT 
    csa.CRN, 
    csa.CourseID, 
    csa.StudentID, 
    csa.AttendanceDate, 
    csa.PresentAbsent, 
    CONCAT(su.FirstName, ' ', su.LastName) AS StudentName,
    cs.SemesterID
FROM CourseSectionAttendance csa
JOIN CourseSection cs ON csa.CRN = cs.CRN
JOIN Users su ON csa.StudentID = su.UserID
WHERE csa.StudentID = ?
";
$params = [$studentID];
$types = "i";

if (!empty($search)) {
    $sql .= "
    AND (
        su.FirstName LIKE CONCAT('%', ?, '%')
        OR su.LastName LIKE CONCAT('%', ?, '%')
        OR csa.CRN LIKE CONCAT('%', ?, '%')
        OR csa.CourseID LIKE CONCAT('%', ?, '%')
        OR csa.AttendanceDate LIKE CONCAT('%', ?, '%')
        OR csa.PresentAbsent LIKE CONCAT('%', ?, '%')
        OR cs.SemesterID LIKE CONCAT('%', ?, '%')
    )
    ";
    $types .= "sssssss";
    array_push($params, $search, $search, $search, $search, $search, $search, $search);
}

$sql .= " ORDER BY csa.CRN DESC";

$stmt = $mysqli->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$attendance = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (!empty($attendance)) {
    $studentName = $attendance[0]['StudentName'];
} else {
    $studentName = "Unknown Student";
}

switch ($role) {
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
        $dashboard = 'login.html';
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
<title>Attendance History</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="./styles.css" />
<style>
  .scroll-box {
    max-height: 450px;
    overflow-y: auto;
    overflow-x: hidden;
    border: 1px solid #ccc;

    background: #ffffff;
    scrollbar-width: thin;
    }

    .scroll-box::-webkit-scrollbar {
        width: 6px;
    }

    .scroll-box::-webkit-scrollbar-thumb {
        background: #888;
        border-radius: 4px;
    }

    .scroll-box::-webkit-scrollbar-thumb:hover {
        background: #555;
    }

    .crn-header {
    position: sticky;
    top: 0;
    background: #dfe6ff;
    z-index: 10;
    font-weight: bold;
    padding: 8px;
    border-bottom: 2px solid #b5c3ff;
}

.section-labels {
    position: sticky;
    top: 32px;
    background: #f7f7f7;
    z-index: 9;
}
</style>
</head>
<body>
  <header class="topbar">
    <div class="brand">
      <div class="logo"><i data-lucide="graduation-cap"></i></div>
      <h1>Northport University</h1>
      <span class="pill">Attendance History</span>
    </div>
    <div class="top-actions">
      <button id="themeToggle" class="icon-btn" aria-label="Toggle theme"><i data-lucide="moon"></i></button>
      <div class="divider"></div>
      <div class="crumb">
      <a href="<?= htmlspecialchars($profile) ?>" aria-label="Back to Profile">
        ← Back to Profile
      </a>
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
        <div class="table-wrap"><div><strong>StudentID: </strong><?= htmlspecialchars($studentID) ?></div>
            <div><strong>Student Name: </strong><?= htmlspecialchars($studentName) ?></div>

        <form method="GET" style="margin-bottom: 20px;">
          <input type="hidden" name="studentID" value="<?= htmlspecialchars($studentID) ?>">
          <label for="search">Search:</label>
          <input type="text" id="search" name="search"
           placeholder="Search course name or semester..."
           value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">

          <button type="submit">Search</button>
      </form>
      <div class="scroll-box">
      <table border="1" cellpadding="5" cellspacing="0">
        <tbody>
          <?php
            $lastCRN = null;

            foreach ($attendance as $a):

                // When a new CRN starts, print the header row ONE time
                if ($lastCRN !== $a['CRN']): ?>

                    <!-- Spacer between different courses -->
                    <?php if ($lastCRN !== null): ?>
                        <tr><td colspan="4" style="height:12px; background:#e9e9e9;"></td></tr>
                    <?php endif; ?>
                    <tr class = "crn-header" style="background:#dfe6ff; font-weight:bold;">
                        <td colspan="4">
                            CRN: <?= htmlspecialchars($a['CRN']) ?> —
                            Course: <?= htmlspecialchars($a['CourseID']) ?>
                        </td>
                    </tr>

                    <tr class="section-labels" style="background:#f7f7f7;">
                        <th>Date</th>
                        <th>Present/Absent</th>
                        <th>Semester</th>
                    </tr>

                <?php endif; ?>

                <!-- Row for each attendance record -->
                <tr>
                    <td><?= htmlspecialchars($a['AttendanceDate']) ?></td>
                    <td><?= htmlspecialchars($a['PresentAbsent']) ?></td>
                    <td><?= htmlspecialchars($a['SemesterID']) ?></td>
                </tr>

            <?php
                $lastCRN = $a['CRN'];
            endforeach;
          ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</section>
</main>
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