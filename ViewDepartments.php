<?php
session_start();
require_once __DIR__ . '/config.php';
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['user_id'])){
    redirect(PROJECT_ROOT . "/login.html");
}

$mysqli = get_db();
$mysqli->set_charset('utf8mb4');


$sql = "SELECT DeptID, DeptName, Email, Phone, RoomID, ChairID FROM Department";
$stmt = $mysqli->prepare($sql);
$stmt->execute();
$res = $stmt->get_result();
$courses = $res->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!doctype html>
<html lang="en" data-theme="light">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Northport University — View Courses</title>
  <link rel="stylesheet" href="./viewstyles.css">
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
      <div class="crumb"><a href="viewDirectory.php" aria-label="Back to Directory">← Back to Directory</a></div>
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
            <?php if (!empty($courses)): ?>
              <?php foreach ($courses as $c): ?>
                <tr>
                  <td><?= htmlspecialchars($c['DeptName']) ?></td>
                  <td><?= htmlspecialchars($c['Email']) ?></td>
                  <td><?= htmlspecialchars($c['Phone']) ?></td>
                  <td><?= htmlspecialchars($c['RoomID']) ?></td>
                  <td><?= htmlspecialchars($c['ChairID']) ?></td>
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
</html>
