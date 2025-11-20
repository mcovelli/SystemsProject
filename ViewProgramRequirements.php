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
        break;
    case 'admin':
        if (($_SESSION['admin_type'] ?? '') === 'update') {
            $dashboard = 'update_admin_dashboard.php';
        } else {
            $dashboard = 'view_admin_dashboard.php';
        }
        break;
    case 'student':
        $dashboard = 'student_dashboard.php';
        break;
    case 'statstaff':
        $dashboard = 'statstaff_dashboard.php';
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
  <title>Northport University — Requirements</title>
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
          <h2 class="card-title">View Requirements</h2>
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

</body>
</html>
