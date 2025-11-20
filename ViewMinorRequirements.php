<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['user_id'])) {
    redirect(PROJECT_ROOT . "/login.html");
}

$userId = $_SESSION['user_id'];
$minorID = $_GET['minorID'] ?? null;
if (!$minorID) die("No minorID provided.");

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

$minor_requirement_sql = "SELECT mnr.MinorID, mn.MinorName, mnr.CourseID, mnr.RequirementDescription, mnr.RequirementType, mnr.CreditsRequired
                       FROM MinorRequirement mnr
                       JOIN Minor mn ON mnr.MinorID = mn.MinorID
                       WHERE mnr.MinorID = ?";

$minor_requirement_stmt = $mysqli->prepare($minor_requirement_sql);
$minor_requirement_stmt->bind_param("i", $minorID);
$minor_requirement_stmt->execute();
$minor_requirement_res = $minor_requirement_stmt->get_result();
$minor_requirement = $minor_requirement_res->fetch_all(MYSQLI_ASSOC);
$minor_requirement_stmt->close();

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
          <thead><tr><th>MinorID</th><th>MinorName</th><th>CourseID</th><th>Description</th><th>Type</th><th>Credits</th></tr></thead>
            <tbody id="majorRequirementsBody">
              <?php if (!empty($minor_requirement)): ?>
                <?php foreach ($minor_requirement as $mnr): ?>
                  <tr>
                    <td><?= htmlspecialchars($mnr['MinorID']) ?> </td>
                    <td><?= htmlspecialchars($mnr['MinorName']) ?></td>
                    <td><?= htmlspecialchars($mnr['CourseID']) ?></td>
                    <td><?= htmlspecialchars($mnr['RequirementDescription']) ?></td>
                    <td><?= htmlspecialchars($mnr['RequirementType']) ?></td>
                    <td><?= htmlspecialchars($mnr['CreditsRequired']) ?></td>
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
