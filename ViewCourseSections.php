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

$userRole = strtolower($_SESSION['role'] ?? '');
switch ($userRole) {
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
<title>Course Section Directory</title>
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
      <span class="pill">Course Section Directory</span>
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
    </main>
    <footer class="footer">© <span id="year"></span> Northport University • All rights reserved</footer>

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

          data.forEach(type => {
            const opt = document.createElement('option');
            opt.value = type.type;
            opt.textContent = type.type;
            if (type === selectedType) opt.selected = true;
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
