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

#Fetch section from database
$usersql = "SELECT UserID, FirstName, LastName, Email, UserType, Status, DOB
        FROM Users WHERE UserID = ? LIMIT 1";
$userstmt = $mysqli->prepare($usersql);
$userstmt->bind_param("i", $userId);
$userstmt->execute();
$userres = $userstmt->get_result();
$user = $userres->fetch_assoc();
$userstmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $CourseId = $_POST['courseID'] ?? '';
    $CourseName = $_POST['courseName'] ?? '';
    $DeptId = $_POST['dept'] ?? '';
    $CourseDesc = $_POST['courseDesc'] ?? '';
    $Credits = $_POST['credits'] ?? '';
    $CourseType = $_POST['courseType'] ?? '';

$mysqli->begin_transaction();
#inserts data into database
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
<title>Create Courses</title>
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
      <span class="pill">Create Courses</span>
    </div>
    <div class="top-actions">
      <div class="search">
        <i class="search-icon" data-lucide="search"></i>
        <input type="text" placeholder="Search courses, people, anything…" />
      </div>
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
                  <h1 class="card-title">Create Course</h1>
                </div>
            </div>
                <div id = "create-section-course">
                    <form id = "CreateCourse" method = "POST" action = "">
                        <label for="courseID">Course ID: </label>
                        <input type = "text" id="courseID" name="courseID" required placeholder = "ex. BIOL 100">
                        <br>

                        <label for="courseName">Course Name: </label>
                             <input type = "text" id="courseName" name="courseName" required placeholder="ex. Biology Foundations"><br>

                        <label for="dept">Department: </label>
                             <select name="dept" id="dept">
                                </select><br>

                        <label for ="courseDesc">Course Description: </label>
                            <input type = "text" id="courseDesc" name="courseDesc" required placeholder="Introductory course with essential concepts and skills."><br>

                        <label for = "credits">Credits Needed:</label>
                            <input type = "number" id = "credits" name = "credits" required placeholder="ex. 3"><br>

                        <label for="courseType">Course Type: </label>
                             <select name="courseType" id="courseType" required>
                                </select><br>

                        <button type="submit" id = "submit">Submit</button>
                    </form>
                </div>
        </section>
    </main>
    <footer class="footer">© <span id="year"></span> Northport University</footer>
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
      if (window.lucide) lucide.createIcons();
    });

    // Fetch departments from get_departments.php
    fetch('get_departments.php')
    .then(response => response.json())
    .then(data => {
        const deptSelect = document.getElementById('dept');
        const selected = new URLSearchParams(window.location.search).get('dept');

    data.forEach(name => {
        const opt = document.createElement('option');
        opt.value = name.id;
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
        opt.value = type.type;
        opt.textContent = type.type;
        courseTypeSelect.appendChild(opt);
        });
    })
    .catch(err => console.error('Error loading Course Types:', err));

    document.getElementById("CreateCourse").addEventListener("submit", (e) => {
    console.log("Form submitted");
});
</script>
</body>
</html>
