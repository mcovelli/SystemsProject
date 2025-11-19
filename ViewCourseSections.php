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
$selectedSemester = $_GET['Semester'] ?? '';
$selectedCourseType = $_GET['courseType'] ?? '';

$conditions = [];
$params = [];
$types = "";

$sql = "SELECT cs.CRN, cs.CourseID, cs.CourseSectionNo, c.CourseName, CONCAT(fu.FirstName, ' ', fu.LastName) AS Professor, cs.TimeSlotID, cs.RoomID, cs.SemesterID, cs.AvailableSeats, d.DeptName, c.CourseType
  FROM CourseSection cs 
  JOIN Course c ON cs.CourseID = c.CourseID
  JOIN Users fu ON cs.FacultyID = fu.UserID
  JOIN Department d ON c.DeptID = d.DeptID 
  JOIN Semester s ON cs.SemesterID = s.SemesterID";

$stmt = $mysqli->prepare($sql);

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

if (!empty($selectedSemester)) {
    $conditions[] = " cs.SemesterID = ?";
    $params[] = $selectedSemester;
    $types .= "s";
}

if (!empty($conditions)) {
    $sql .= " WHERE " . implode(" AND ", $conditions);
}

$sql .= " ORDER BY cs.SemesterID, cs.CourseID";

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
  <title>Northport University — View Course Sections</title>
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
      <div class="crumb"><a href="viewDirectory.php" aria-label="Back to Directory">← Back to Directory</a></div>
    </div>
  </header>

  <main class="page">
    <section class="hero card">
      <div class="card-head between">
        <div>
          <h2 class="card-title">View All Course Sections</h2>
          <div class="sub muted">Filter By Department, Course Type or Semester</div>
        </div>
      </div>
      <div style="margin-top:12px">
        <form>
          <label for="dept">Department:</label>
          <select name="dept" id="dept">
            <option value="">-- All Departments --</option>
          </select>

          <label for="courseType">Course Type:</label>
          <select name="courseType" id="courseType">
            <option value="">-- All Course Types --</option>
          </select>

          <label for="Semester">Semester:</label>
          <select name="Semester" id="Semester">
            <option value="">-- All Semesters --</option>
          </select>

          <button type="submit">Apply Filters</button>
        </form>
    </div>

    <div class="table-wrap">
      <table id="coursesTable" border="1" cellpadding="5" cellspacing="0">
        <thead><tr><th>CRN</th><th>Course ID</th><th>Course Section #</th><th>Course Name</th><th>Dept Name</th><th>Professor</th><th>Time Slot</th><th>Room</th><th>Semester</th><th>Available Seats</th><th>Course Type</th></tr></thead>
          <tbody id="coursesBody">
            <?php if (!empty($courses)): ?>
              <?php foreach ($courses as $c): ?>
                <tr>
                  <td><?= htmlspecialchars($c['CRN']) ?></td>
                  <td><?= htmlspecialchars($c['CourseID']) ?></td>
                  <td><?= htmlspecialchars($c['CourseSectionNo']) ?></td>
                  <td><?= htmlspecialchars($c['CourseName']) ?></td>
                  <td><?= htmlspecialchars($c['DeptName']) ?></td>
                  <td><?= htmlspecialchars($c['Professor']) ?></td>
                  <td><?= htmlspecialchars($c['TimeSlotID']) ?></td>
                  <td><?= htmlspecialchars($c['RoomID']) ?></td>
                  <td><?= htmlspecialchars($c['SemesterID']) ?></td>
                  <td><?= htmlspecialchars($c['AvailableSeats']) ?></td>
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

        // Fetch Semesters from get_semesters.php
      fetch('get_semesters.php')
        .then(response => response.json())
        .then(data => {
          const semesterSelect = document.getElementById('Semester');
          const selectedSemester = new URLSearchParams(window.location.search).get('Semester');

          data.forEach(id => {
            const opt = document.createElement('option');
            opt.value = id.SemesterID;
            opt.textContent = id.SemesterID;
            if (name === selectedSemester) opt.selected = true;
            semesterSelect.appendChild(opt);
          });
        })
        .catch(err => console.error('Error loading semesters:', err));

    </script>

  </body>
</html>
