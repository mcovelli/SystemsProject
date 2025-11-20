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
        break;
    case 'admin':
        if (($_SESSION['admin_type'] ?? '') === 'update') {
            $dashboard = 'update_admin_dashboard.php';
        } else {
            $dashboard = 'view_admin_dashboard.php';
        }
        break;
    default:
        $dashboard = 'login.html'; // fallback
}


?>

<!doctype html>
<html lang="en" data-theme="light">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Northport University — Advisee List</title>
  <link rel="stylesheet" href="./viewstyles.css" />
</head>
<body>
  <header class="topbar">
    <div class="brand">
      <div class="logo">NU</div>
      <h1>Northport University</h1>
    </div>
    <div class="top-actions">
      <div class="search" style="width: min(360px, 40vw)">
        <i class="search-icon" data-lucide="search"></i>
        <input id="q" type="text" placeholder="Search code, title, instructor…" />
      </div>
      <button class="btn outline" id="themeToggle" title="Toggle theme">🌙</button>
      <div class="crumb"><a href="<?= htmlspecialchars($dashboard) ?>" aria-label="Back to Dashboard">← Back to Dashboard</a></div>
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
                    <td><?= htmlspecialchars($a['MajorName']) ?? 'Undeclared' ?></td>
                    <td><?= htmlspecialchars($a['MinorName']) ?? 'Undeclared' ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr><td colspan="6">No Advisees found.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
      </div>
    </section>

</body>
</html>
