<?php
session_start();
require_once __DIR__ . '/config.php';
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['user_id']) || 
  ($_SESSION['role'] ?? '') !== 'admin' ||
($_SESSION['admin_type'] ?? '') !== 'update') {
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $minGrade        = $_POST['minGrade'] ?? 'C';
    $courseID        = $_POST['courseID'] ?? null;
    $prereqCourseIDs = $_POST['prereqCourseIDs'] ?? [];

    if (empty($courseID)) {
        throw new Exception("No course selected.");
    }

    preg_match('/(\d+)/', $courseID, $course_match);
    $course_num = (int)($course_match[1] ?? 0);

    foreach ($prereqCourseIDs as $prereqID) {
        preg_match('/(\d+)/', $prereqID, $prereq_match);
        $prereq_num = (int)($prereq_match[1] ?? 0);

        if ($prereq_num <= $course_num) {
            echo "<script>
                    alert('Invalid prerequisite: $prereqID must be a higher-level course than $courseID.');
                    window.history.back();
                  </script>";
            exit;
        }
    }

    $mysqli->begin_transaction();

    try {
        if (empty($prereqCourseIDs)) {
            $del = $mysqli->prepare(
                "DELETE FROM CoursePrerequisite WHERE CourseID = ?"
            );
            $del->bind_param("s", $courseID);
            $del->execute();
            $del->close();

        } else {
            $placeholders = implode(',', array_fill(0, count($prereqCourseIDs), '?'));

            $deleteSql = "DELETE FROM CoursePrerequisite
                          WHERE CourseID = ?
                          AND PrerequisiteCourseID NOT IN ($placeholders)";

            $deleteStmt = $mysqli->prepare($deleteSql);

            $types  = 's' . str_repeat('s', count($prereqCourseIDs));
            $params = array_merge([$courseID], $prereqCourseIDs);

            $deleteStmt->bind_param($types, ...$params);
            $deleteStmt->execute();
            $deleteStmt->close();

            $sql = "INSERT INTO CoursePrerequisite
                    (CourseID, PrerequisiteCourseID, MinGradeRequired, DecisionDate)
                    VALUES (?, ?, ?, CURRENT_DATE())
                    ON DUPLICATE KEY UPDATE 
                        MinGradeRequired = VALUES(MinGradeRequired),
                        DecisionDate     = VALUES(DecisionDate)";

            $stmt = $mysqli->prepare($sql);

            foreach ($prereqCourseIDs as $prereqID) {
                $stmt->bind_param("sss", $courseID, $prereqID, $minGrade);
                $stmt->execute();
            }

            $stmt->close();
        }

        $mysqli->commit();
        echo "<script>alert('Prerequisites saved.');</script>";

    } catch (Exception $e) {
        $mysqli->rollback();
        die("PHP Exception: " . $e->getMessage());
    }
}


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
<title>Update Prerequisites</title>
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
      <span class="pill">Update Prerequisites</span>
    </div>
    <div class="top-actions">
      <button id="themeToggle" class="icon-btn" aria-label="Toggle theme"><i data-lucide="moon"></i></button>
      <div class="divider"></div>
      <div class="crumb"><a href="createDirectory.php" aria-label="Back to Directory">← Back to Directory</a></div>
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
              <h2 class="card-title">Update Prerequisites</h2>
            </div>
          </div>
       </section>

    <div id = "create-prereq">
        <form id = "prereqForm" method = "POST">
            <label for="courseID">Select Course to create Prerequisites for:</label>
            <select id="courseID" name="courseID" required>
                    <option value="">-- Select --</option>
                </select>

                    <!-- Department Filter -->
                    <div id="departmentFilterContainer" style="margin-top: 20px;">
                        <label for="deptFilter">Filter by Department:</label>
                        <select id="deptFilter" multiple size="5" style="width: 200px;">
                        </select>
                    </div>

                    <!-- Course Table -->
                    <div id="courseTableContainer" style="margin-top: 20px;">
                        <label>Select Courses as Prerequisites:</label>

                        <div class="course-table-container">
                          <table class="course-table" id="courseTable">
                            <thead>
                              <tr>
                                <th>Select</th>
                                <th>Course ID</th>
                                <th>Course Name</th>
                                <th>Dept</th>
                                <th>Credits</th>
                                <th>Level</th>
                              </tr>
                            </thead>
                            <tbody></tbody>
                          </table>
                        </div>
                    </div><br>

                    <button type="submit" id = "submit">Submit</button>
        </form>
    </div>

<footer class="footer">© <span id="year"></span> Northport University • All rights reserved</footer>

 <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>

<script>
  // Icons
  lucide.createIcons();

  // Year
  document.getElementById('year').textContent = new Date().getFullYear();

  // Theme toggle
  const themeToggle = document.getElementById('themeToggle');
  themeToggle.addEventListener('click', () => {
    const root = document.documentElement;
    const current = root.getAttribute('data-theme') || 'light';
    root.setAttribute('data-theme', current === 'light' ? 'dark' : 'light');
    themeToggle.querySelector('i').setAttribute('data-lucide', current === 'light' ? 'sun' : 'moon');
    if (window.lucide) lucide.createIcons();
  });

  fetch('get_courses.php')
    .then(response => response.json())
    .then(data => {
      const courseSelect = document.getElementById('courseID');
      const selectedCourse = new URLSearchParams(window.location.search).get('courseID');

      data.forEach(course => {
        const opt = document.createElement('option');
        opt.value = course.courseID;
        opt.textContent = `${course.courseID} — ${course.courseName}`;
        if (course.courseID == selectedCourse) opt.selected = true;
        courseSelect.appendChild(opt);
      });
    })
    .catch(err => console.error('Error loading courses:', err));
</script>

<script>
document.addEventListener("DOMContentLoaded", () => {
    const courseSelect    = document.getElementById('courseID'); 
    const courseTableBody = document.querySelector("#courseTable tbody");
    const deptFilter      = document.getElementById("deptFilter");

    let ALL_COURSES = [];

    fetch("get_courses.php")
        .then(r => r.json())
        .then(data => {
            ALL_COURSES = data;

            const departments = [...new Set(data.map(c => c.deptName))];
            deptFilter.insertAdjacentHTML("beforeend",
                `<option value="__ALL__">-- All Departments --</option>`
            );
            departments.forEach(d => {
                deptFilter.insertAdjacentHTML("beforeend",
                    `<option value="${d}">${d}</option>`
                );
            });

            updateCourseTable();
        });

    function updateCourseTable() {
        const selectedDepartments = Array.from(deptFilter.selectedOptions).map(o => o.value);

        let filtered = [...ALL_COURSES];
        const selectedCourseID = courseSelect.value;

        if (selectedCourseID) {
          filtered = filtered.filter(c => c.courseID !== selectedCourseID);
        }

        if (!selectedDepartments.includes("__ALL__") && selectedDepartments.length > 0) {
            filtered = filtered.filter(c => selectedDepartments.includes(c.deptName));
        }

        courseTableBody.innerHTML = "";
        filtered.forEach(c => {
            courseTableBody.insertAdjacentHTML("beforeend", `
                <tr>
                    <td><input type="checkbox" name="prereqCourseIDs[]" value="${c.courseID}"></td>
                    <td>${c.courseID}</td>
                    <td>${c.courseName}</td>
                    <td>${c.deptName}</td>
                    <td>${c.credits}</td>
                    <td>${c.level}</td>
                </tr>
            `);
        });

        applyExistingPrereqs();
    }

    deptFilter.addEventListener("change", updateCourseTable);

    courseSelect.addEventListener('change', () => {
        applyExistingPrereqs(true);
    });

    function applyExistingPrereqs(clearFirst = false) {
        const courseID = courseSelect.value;
        if (!courseID) return;

        if (clearFirst) {
            document
                .querySelectorAll('#courseTable tbody input[type="checkbox"]')
                .forEach(cb => cb.checked = false);
        }

        fetch('get_prereqs.php?courseID=' + encodeURIComponent(courseID))
            .then(r => r.json())
            .then(prereqs => {
                prereqs.forEach(p => {
                    const pid = p.prereqCourseID;
                    const cb = document.querySelector(
                        `#courseTable tbody input[type="checkbox"][value="${pid}"]`
                    );
                    if (cb) {
                        cb.checked = true;
                    }
                });
            })
            .catch(err => console.error('Error loading existing prereqs:', err));
    }
});
</script>
</main>
</body>
</html>

 