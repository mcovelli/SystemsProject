<?php
session_start();
require_once __DIR__ . '/config.php';
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    redirect(PROJECT_ROOT . "/login.html");
}

$mysqli = get_db();
$mysqli->set_charset('utf8mb4');

// Fetch admin security type
$adminCheck = $mysqli->prepare("
    SELECT SecurityType 
    FROM Admin 
    WHERE AdminID = ? LIMIT 1
");
$adminCheck->bind_param("i", $_SESSION['user_id']);
$adminCheck->execute();
$adminType = $adminCheck->get_result()->fetch_assoc()['SecurityType'] ?? null;
$adminCheck->close();

if ($adminType !== 'UPDATE') {
    die("<h2 style='color:red;'>Access Denied: You are not an UpdateAdmin.</h2>");
}

$loadedCourse = null;

if (isset($_POST['searchCourse'])) {
    $searchId = $_POST['searchID'];

    // Load Course table
    $stmt = $mysqli->prepare("SELECT * FROM Course c JOIN Department d ON c.DeptID = d.DeptID WHERE CourseID = ?");
    $stmt->bind_param("s", $searchId);
    $stmt->execute();
    $loadedCourse = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$userId = $_SESSION['user_id'];

$usersql = "SELECT UserID, FirstName, LastName, Email, UserType, Status, DOB
        FROM Users WHERE UserID = ? LIMIT 1";
$userstmt = $mysqli->prepare($usersql);
$userstmt->bind_param("i", $userId);
$userstmt->execute();
$userres = $userstmt->get_result();
$user = $userres->fetch_assoc();
$userstmt->close();

if (isset($_POST['updateCourse'])) {
    $CourseId   = $_POST['courseID'];
    $CourseName = $_POST['courseName'];
    $DeptId     = $_POST['deptID'];
    $CourseDesc = $_POST['courseDesc'];
    $Credits    = $_POST['credits'];
    $CourseType = $_POST['courseType'];

    $mysqli->begin_transaction();

    $sql = "UPDATE Course 
            SET CourseID = ?, CourseName = ?, DeptID = ?, Course_Desc = ?, Credits = ?, CourseType = ?
            WHERE CourseID = ?";

    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("ssssiss",
        $CourseId,
        $CourseName,
        $DeptId,
        $CourseDesc,
        $Credits,
        $CourseType,
        $CourseId
    );

    $stmt->execute();
    $mysqli->commit();

    $_SESSION['update_success'] = true;
}

$userRole = strtolower($_SESSION['role'] ?? '');
switch ($userRole) {
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
    default:
        $dashboard = 'login.html'; // fallback
        $profile = 'login.html';
}

$initials = substr($user['FirstName'], 0, 1) . substr($user['LastName'], 0, 1);
?>


<!doctype html>
<html lang="en" data-theme="light">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Update Courses • Northport University</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

<link rel="stylesheet" href="./stylesGrade.css" />
<style>
/* Inline enhancements */
.field-block {
    margin-bottom: 12px;
}
label {
    font-weight: 600;
    display: block;
    margin-bottom: 3px;
}
input[type=text], input[type=date], select {
    width: 280px;
    padding: 6px;
    border-radius: 6px;
    border: 1px solid #ccc;
}
.multiselect {
    height: 120px;
    width: 300px;
    padding: 6px;
}
.section-card {
    border: 1px solid #ddd;
    padding: 15px;
    border-radius: 10px;
    margin-top: 10px;
    background: var(--card-bg);
}

.toast {
    position: fixed;
    top: 20px;
    right: 20px;
    background: #28a745;
    color: white;
    padding: 12px 18px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 16px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    opacity: 0;
    transform: translateY(-15px);
    transition: opacity 0.3s ease, transform 0.3s ease;
    z-index: 9999;
}

.toast.show {
    opacity: 1;
    transform: translateY(0);
}

.toast.hidden {
    display: none;
}
</style>
</head>

<body>

<header class="topbar">
    <div class="brand">
      <div class="logo"><i data-lucide="graduation-cap"></i></div>
      <h1>Northport University</h1>
      <span class="pill">Update Courses</span>
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

<div id="toast" class="toast hidden">Course updated successfully!</div>

<?php if (!empty($successMsg)): ?>
    <script>
        showToast("✅ Course updated successfully!");
    </script>
<?php endif; ?>

<main class="page">

<!-- SEARCH Course CARD -->
<section class="hero card">
    <div class="card-head">
        <h2>Search for Course to Update</h2>
    </div>

    <form method="POST" style="margin-top: 10px;">
        <div class="field-block">
            <label>CourseID</label>
            <input type="text" name="searchID" required placeholder="Enter CourseID...">
        </div>

        <button type="submit" name="searchCourse" class="btn">Search</button>
    </form>
</section>


<!-- IF Course LOADED, DISPLAY FORM -->
<?php if (!empty($loadedCourse)) : ?>

<section class="hero card" style="margin-top: 20px;">
    <h2>Update Course: <?php echo htmlspecialchars($loadedCourse['CourseName'] . " - " . $loadedCourse['CourseID']); ?></h2>

    <form method="POST">

        <input type="hidden" name="CourseID" value="<?php echo $loadedCourse['CourseID']; ?>">
        <input type="hidden" name="CourseType" value="<?php echo $loadedCourse['CourseType']; ?>">

        <!-- COURSE TABLE FIELDS -->
        <div class="section-card">
            <h3>Basic Information</h3>

            <div class="field-block">
                <label>CourseID</label>
                <input type="text" name="courseID" value="<?php echo $loadedCourse['CourseID']; ?>">
            </div>

            <div class="field-block">
                <label>Course Name</label>
                <input type="text" name="courseName" value="<?php echo $loadedCourse['CourseName']; ?>" >
            </div>

            <div class="field-block">
                <label for ="deptID">Department: </label>
                                <select name="deptID" id="deptID">
                                    <option value="<?php echo $loadedCourse['DeptID']; ?>"><?php echo $loadedCourse['DeptName']; ?></option>
                                </select><br>
            </div>

            <div class="field-block">
                <label>Course Description</label>
                <textarea name="courseDesc" rows="4" cols="50"><?php echo $loadedCourse['Course_Desc']; ?></textarea>
            </div>

            <div class="field-block">
                <label>Credits</label>
                <input type="number" name="credits" value="<?php echo $loadedCourse['Credits']; ?>">
            </div>

            <div class="field-block">
                <label for="courseType">Course Type:</label>
                  <select name="courseType" id="courseType">
                    <option value="<?php echo $loadedCourse['CourseType']; ?>"><?php echo $loadedCourse['CourseType']; ?></option>
                  </select>
            </div>

            <div style="margin-top: 20px;">
                <button type="submit" name="updateCourse">Save Changes</button>
            </div>

    </form>

</section>

<?php endif; ?>


</main>

<footer class="footer">
    © <span id="year"></span> Northport University • All rights reserved
</footer>

<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
<script>
lucide.createIcons();
document.getElementById('year').textContent = new Date().getFullYear();
</script>

<script>
/* ============================================================================
   THEME TOGGLE
============================================================================ */
const themeToggle = document.getElementById('themeToggle');
themeToggle.addEventListener('click', () => {
    const root = document.documentElement;
    const cur = root.getAttribute('data-theme') || 'light';
    root.setAttribute('data-theme', cur === 'light' ? 'dark' : 'light');
    themeToggle.querySelector('i').setAttribute('data-lucide', cur === 'light' ? 'sun' : 'moon');
    lucide.createIcons();
});

// Fetch faculty from get_departments.php
    fetch('get_departments.php')
    .then(response => response.json())
    .then(data => {
        const deptSelect = document.getElementById('deptID');
        const selectedDept = new URLSearchParams(window.location.search).get('deptID');

    data.forEach(dept => {
        const opt = document.createElement('option');
        opt.value = dept.id;
        opt.textContent = dept.name;
        deptSelect.appendChild(opt);
        });
    })
    .catch(err => console.error('Error loading faculty:', err));

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


function showToast(message) {
    const toast = document.getElementById("toast");
    toast.textContent = message;
    toast.classList.remove("hidden");

    // Trigger animation
    setTimeout(() => {
        toast.classList.add("show");
    }, 100);

    // Hide after 3 seconds
    setTimeout(() => {
        toast.classList.remove("show");
        setTimeout(() => toast.classList.add("hidden"), 300);
    }, 3000);
}
</script>

<?php if (!empty($_SESSION['update_success'])): ?>
<script>
document.addEventListener("DOMContentLoaded", () => {
    showToast("Course updated successfully!");
});
</script>
<?php unset($_SESSION['update_success']); ?>
<?php endif; ?>
</body>
</html>