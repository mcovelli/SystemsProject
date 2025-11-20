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

$selectedDept = $_GET['dept'] ?? '';

$sql = "SELECT m.MajorID, m.MajorName, m.DeptID, d.DeptName, d.Email, m.CreditsNeeded FROM Major m JOIN Department d ON m.DeptID = d.DeptID ";

if (!empty($selectedDept)) {
    $sql .= " WHERE d.DeptName = ?";
}

$stmt = $mysqli->prepare($sql);
if (!empty($selectedDept)) {
    $stmt->bind_param("s", $selectedDept);
}

$stmt->execute();
$res = $stmt->get_result();
$majors = $res->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>


<!doctype html>
<html lang="en" data-theme="light">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Northport University — View Majors</title>
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
  </header>

 <main class="page">
    <section class="hero card">
      <div class="card-head between">
        <div>
          <h2 class="card-title">View All Majors</h2>
          <div class="sub muted">Filter By Department</div>
        </div>
      </div>
      <div style="margin-top:12px">
        <form method="GET" id="filterForm" style="margin-bottom: 20px;">
          <label for="dept">Department:</label>
          <select name="dept" id="dept">
            <option value="">-- All Departments --</option>
          </select>

          <button type="submit">Apply Filters</button>
        </form>
        <p>Click ID to pull up requirements</p>
    </div>

      <div class="table-wrap">
        <table id="majorsTable" border="1" cellpadding="5" cellspacing="0">
          <thead><tr><th>Major ID</th><th>Major Name</th><th>Department ID</th><th>Department Name</th><th>Credits Needed</th><th>Department Email</th></tr></thead>
            <tbody id="majorsBody">
              <?php if (!empty($majors)): ?>
                <?php foreach ($majors as $m): ?>
                  <tr>
                    <td><a href="ViewMajorRequirements.php?majorID=<?= urlencode($m['MajorID']) ?>&MajorName=<?= urlencode($m['MajorName']) ?>">
                      <?= htmlspecialchars($m['MajorID']) ?> </a></td>
                    <td><?= htmlspecialchars($m['MajorName']) ?></td>
                    <td><?= htmlspecialchars($m['DeptID']) ?></td>
                    <td><?= htmlspecialchars($m['DeptName']) ?></td>
                    <td><?= htmlspecialchars($m['CreditsNeeded']) ?></td>
                    <td><?= htmlspecialchars($m['Email']) ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                  <tr><td colspan="6">No majors found.</td></tr>
              <?php endif; ?>
              </tbody>
            </table>
        </div>
    </section>

    <script>
      // Fetch departments from get_departments.php
      fetch('get_departments.php')
        .then(response => response.json())
        .then(data => {
          const deptSelect = document.getElementById('dept');
          const selectedDept = new URLSearchParams(window.location.search).get('dept');

          data.forEach(name => {
            const opt = document.createElement('option');
            opt.value = name.name;
            opt.textContent = name.name;
            if (name === selectedDept) opt.selected = true;
            deptSelect.appendChild(opt);
          });
        })
        .catch(err => console.error('Error loading departments:', err));

    </script>

  </body>
</html>
