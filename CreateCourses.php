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

$mysqli = get_db();
$mysqli->set_charset('utf8mb4');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $CourseId = $_POST['courseID'] ?? '';
    $CourseName = $_POST['courseName'] ?? '';
    $DeptId = $_POST['dept'] ?? '';
    $CourseDesc = $_POST['courseDesc'] ?? '';
    $Credits = $_POST['credits'] ?? '';
    $CourseType = $_POST['courseType'] ?? '';

$mysqli->begin_transaction();

  $sql = "INSERT INTO Course (CourseID, CourseName, DeptID, Course_Desc, Credits, CourseType) VALUES (?, ?, (SELECT DeptID FROM Department WHERE DeptName = ?), ?, ?, ?)";
  $stmt = $mysqli->prepare($sql);
  $stmt->bind_param("ssssis", $CourseId, $CourseName, $DeptId, $CourseDesc, $Credits, $CourseType);
        
  if ($stmt->execute()) {
    echo "alert('$CourseName. created ✅');";
  } else {
    echo "alert('Could not create course');";
        }
  
$mysqli->commit();
}

?>
<!doctype html>
<html lang="en" data-theme="light">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Northport University — Create Course</title>
  <style>
    .hidden {
      display: none;
    }
  </style>
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
      <div class="crumb"><a href="update_admin_dashboard.php" aria-label="Back to Dashboard">← Back to Dashboard</a></div>
    </div>
  </header>

    <main class="page">
        <section class="hero card">
            <div class="card-head between">
                <div>
                  <h1 class="card-title">Create Course</h1>
                </div>
            </div>
                <div id = "create-section-course">
                    <form id = "CreateCourse" method = "POST" action = "">
                        <label for="courseID">Course ID: </label>
                        <input type = "text" id="courseID" name="courseID" required placeholder = "ex. BIOL 100">
                        <br>

                        <label for="courseName">Course Name: </label>
                             <input type = "text" id="courseName" name="courseName" required placeholder="ex. Biology Foundations">

                        <label for="dept">Department: </label>
                             <select name="dept" id="dept">
                                </select><br>

                        <label for ="courseDesc">Course Description: </label>
                            <input type = "text" id="courseDesc" name="courseDesc" required placeholder="Introductory course with essential concepts and skills."><br>

                        <label for = "credits">Credits Needed:</label>
                            <input type = "number" id = "credits" name = "credits" required placeholder="ex. 3"><br>

                        <label for="courseType">Course Type: </label>
                             <select name="courseType" id="courseType">
                                </select><br>

                        <button type="submit" id = "submit">Submit</button>
                    </form>
                </div>
        </section>
    </main>
</body>

<script>

    // Fetch departments from get_departments.php
    fetch('get_departments.php')
    .then(response => response.json())
    .then(data => {
        const deptSelect = document.getElementById('dept');
        const selected = new URLSearchParams(window.location.search).get('dept');

    data.forEach(name => {
        const opt = document.createElement('option');
        opt.value = name.name;
        opt.textContent = name.name;
        deptSelect.appendChild(opt);
        });
    })
    .catch(err => console.error('Error loading departments:', err));

    // Fetch course type from get_coursetype.php
    fetch('get_coursetype.php')
    .then(response => response.json())
    .then(data => {
        const courseTypeSelect = document.getElementById('courseType');
        const selectedCourseType = new URLSearchParams(window.location.search).get('courseType');

    data.forEach(type => {
        const opt = document.createElement('option');
        opt.value = type;
        opt.textContent = type;
        courseTypeSelect.appendChild(opt);
        });
    })
    .catch(err => console.error('Error loading Course Types:', err));

    document.getElementById("CreateCourse").addEventListener("submit", (e) => {
    console.log("Form submitted");
});
</script>
</html>
