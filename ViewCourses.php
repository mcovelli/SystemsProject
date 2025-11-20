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
$selectedCourseType = $_GET['courseType'] ?? '';

$sql = "SELECT c.CourseID, c.CourseName, c.Course_Desc, c.Credits, c.CourseType, d.DeptName FROM Course c JOIN Department d ON c.DeptID = d.DeptID ";

$conditions = [];
$params = [];
$types = "";

if (!empty($selectedCourseType)) {
    $conditions[] = " c.CourseType = ?";
    $params[] = $selectedCourseType;
    $types .= "s";
}

if (!empty($selectedDept)) {
    $conditions[] = " d.DeptName = ?";
    $params[] = $selectedDept;
    $types .= "s";
}

if (!empty($conditions)) {
    $sql .= " WHERE " . implode(" AND ", $conditions);
}

$stmt = $mysqli->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

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
  </header>

  <main class="page">
    <section class="hero card">
      <div class="card-head between">
        <div>
          <h2 class="card-title">View All Courses</h2>
          <div class="sub muted">Filter By Department or Course Type</div>
        </div>
      </div>
      <div style="margin-top:12px">
        <form method="GET" id="filterForm" style="margin-bottom: 20px;">
          <label for="dept">Department:</label>
          <select name="dept" id="dept">
            <option value="">-- All Departments --</option>
          </select>

          <label for="courseType">Course Type:</label>
          <select name="courseType" id="courseType">
            <option value="">-- All Course Types --</option>
          </select>

          <button type="submit">Apply Filters</button>
        </form>
    </div>

      <div class="table-wrap">
        <table id="coursesTable" border="1" cellpadding="5" cellspacing="0">
          <thead><tr><th>Course ID</th><th>Course Name</th><th>Department</th><th>Course Description</th><th>Credits</th><th>Course Type</th></tr></thead>
            <tbody id="coursesBody">
              <?php if (!empty($courses)): ?>
                <?php foreach ($courses as $c): ?>
                  <tr>
                    <td><?= htmlspecialchars($c['CourseID']) ?></td>
                    <td><?= htmlspecialchars($c['CourseName']) ?></td>
                    <td><?= htmlspecialchars($c['DeptName']) ?></td>
                    <td><?= htmlspecialchars($c['Course_Desc']) ?></td>
                    <td><?= htmlspecialchars($c['Credits']) ?></td>
                    <td><?= htmlspecialchars($c['CourseType']) ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                  <tr><td colspan="6">No courses found.</td></tr>
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

        // Fetch cousetypes from get_coursetype.php
      fetch('get_coursetype.php')
        .then(response => response.json())
        .then(data => {
          const courseTypeSelect = document.getElementById('courseType');
          const selectedType = new URLSearchParams(window.location.search).get('courseType');

          data.forEach(name => {
            const opt = document.createElement('option');
            opt.value = name;
            opt.textContent = name;
            if (name === selectedType) opt.selected = true;
            courseTypeSelect.appendChild(opt);
          });
        })
        .catch(err => console.error('Error loading course types:', err));

    </script>

  </body>
</html>
