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

$loadedCourseSection = null;

if (isset($_POST['searchCourseSection'])) {
    $searchId = $_POST['searchID'];

    // Load Course table
    $stmt = $mysqli->prepare("SELECT * FROM CourseSection WHERE CRN = ?");
    $stmt->bind_param("i", $searchId);
    $stmt->execute();
    $loadedCourseSection = $stmt->get_result()->fetch_assoc();
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

if (isset($_POST['updateCourseSection'])) {

    $CRN        = $_POST['crn'];
    $CourseId   = $_POST['courseID'];
    $sectionNo  = $_POST['sectionNo'];
    $FacultyID  = $_POST['facultyID'];
    $TimeSlotID = $_POST['timeSlotID'];
    $RoomID     = $_POST['roomID'];
    $Year       = $_POST['year'];
    $Semester   = $_POST['semester'];
    $Status     = $_POST['status'];

    
    $mysqli->begin_transaction();

    $sql = "UPDATE CourseSection
            SET CourseID = ?, CourseSectionNo = ?, FacultyID = ?, TimeSlotID = ?, RoomID = ?, 
                Year = ?, SemesterID = ?, Status = ?
            WHERE CRN = ?";

    $stmt = $mysqli->prepare($sql);

    $stmt->bind_param(
        "siiisissi",
        $CourseId,
        $sectionNo,
        $FacultyID,
        $TimeSlotID,
        $RoomID,
        $Year,
        $Semester,
        $Status,
        $CRN
    );

if ($stmt->execute()) {
    $mysqli->commit();
    $_SESSION['update_success'] = true;
    header("Location: UpdateCourseSections.php");
    exit;
} else {
    $mysqli->rollback();
    $_SESSION['update_success'] = false;
}

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

<div id="toast" class="toast hidden">Course Section Updated Successfully!</div>

<main class="page">

<!-- SEARCH Course CARD -->
<section class="hero card">
    <div class="card-head">
        <h2>Search for Course to Update</h2>
    </div>

    <form method="POST" style="margin-top: 10px;">
        <div class="field-block">
            <label>CRN</label>
            <input type="text" name="searchID" required placeholder="Enter CRN...">
        </div>

        <button type="submit" name="searchCourseSection" class="btn">Search</button>
    </form>
</section>


<!-- IF Course LOADED, DISPLAY FORM -->
<?php if (!empty($loadedCourseSection)) : ?>

<section class="hero card" style="margin-top: 20px;">
    <h2>Update Course: <?php echo htmlspecialchars($loadedCourseSection['CRN'] . " - " . $loadedCourseSection['CourseID']); ?></h2>

    <form method="POST">

        <input type="hidden" name="CourseID" value="<?php echo $loadedCourseSection['CourseID']; ?>">
        <input type="hidden" name="CourseType" value="<?php echo $loadedCourseSection['CRN']; ?>">

        <!-- COURSE TABLE FIELDS -->
        <div class="section-card">
            <h3>Basic Information</h3>

            <div class="field-block">
                <label>CRN</label>
                <input type="number" name="crn" value="<?php echo $loadedCourseSection['CRN']; ?>" readonly>
            </div>

            <div class="field-block">
                <label>CourseID</label>
                <input type="text" name="courseID" value="<?php echo $loadedCourseSection['CourseID']; ?>">
            </div>

            <div class="field-block">
                <label>Course Section No.</label>
                <input type="text" name="sectionNo" value="<?php echo $loadedCourseSection['CourseSectionNo']; ?>">
            </div>

            <div class="field-block">
                <label>Faculty</label>
                <select name="facultyID" id="facultyID">
                                    <option value="<?php echo $loadedCourseSection['FacultyID']?>"><?php echo $loadedCourseSection['FacultyID']; ?></option>
                                </select><br>
            </div>

            <div class="field-block">
                <label>Time Slot</label>
                <select name="timeSlotID" id="timeSlotID">
                                    <option value="<?php echo $loadedCourseSection['TimeSlotID']; ?>"><?php echo $loadedCourseSection['TimeSlotID']; ?></option>
                                </select><br>
            </div>

            <div class="field-block">
                <label>Room</label>
                <select name="roomID" id="roomID">
                                    <option value="<?php echo $loadedCourseSection['RoomID']; ?>"><?php echo $loadedCourseSection['RoomID']; ?></option>
                                </select><br>
            </div>

            <div class="field-block">
                <label for ="year"></label>
                <input type="hidden" name="year" value="<?php echo $loadedCourseSection['Year']; ?>">
            </div>

            <div class="field-block">
                <label>Semester</label>
                <select name="semester" id="semester">
                                    <option value="<?php echo $loadedCourseSection['SemesterID']; ?>"><?php echo $loadedCourseSection['SemesterID']; ?></option>
                                </select><br>
            </div>

            <div class="field-block">
                <label>Status</label>
                <select name="status" id="status">
                                    <option value="<?php echo $loadedCourseSection['Status']; ?>"><?php echo $loadedCourseSection['Status']; ?></option>
                                    <option value="PLANNED">PLANNED</option>
                                    <option value="IN-PROGRESS">IN-PROGRESS</option>
                                    <option value="COMPLETED">COMPLETED</option>
                                    <option value="CANCELED">CANCELED</option>
                                </select><br>
            </div>

            <div style="margin-top: 20px;">
                <button type="submit" name="updateCourseSection">Save Changes</button>
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

// Fetch semesters from get_semesters.php
fetch('get_semesters.php')
    .then(response => response.json())
    .then(data => {
        const semesterSelect = document.getElementById('semester');
        semesterSelect.innerHTML = "";

        data.forEach(semester => {
            const opt = document.createElement('option');
            opt.value = semester.SemesterID;
            opt.textContent = semester.SemesterName + ' ' + semester.Year;
            // Store the year as a data attribute
            opt.setAttribute('data-year', semester.Year);
            if (String(semester.SemesterID) === "<?php echo $loadedCourseSection['SemesterID']; ?>") {
                opt.selected = true;
            }
            semesterSelect.appendChild(opt);
        });

        semesterSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const year = selectedOption.getAttribute('data-year');
            const yearInput = document.querySelector('input[name="year"]');
            if (year && yearInput) {
                yearInput.value = year;
            }
        });
    })
    .catch(err => console.error('Error loading semesters:', err));

      // Fetch faculty from get_faculty.php
    const timeSlotID = "<?php echo $loadedCourseSection['TimeSlotID']; ?>";
    const semester   = "<?php echo $loadedCourseSection['SemesterID']; ?>";
    const year       = "<?php echo $loadedCourseSection['Year']; ?>";
    const currentFac = "<?php echo $loadedCourseSection['FacultyID']; ?>";

    fetch(`get_available_faculty.php?timeSlotID=${timeSlotID}&semester=${semester}&year=${year}&current=${currentFac}`)
    .then(response => response.json())
    .then(data => {
        const facultySelect = document.getElementById('facultyID');
        facultySelect.innerHTML = "";

        data.forEach(faculty => {
            const opt = document.createElement('option');
            opt.value = faculty.FacultyID;
            opt.textContent = `${faculty.FacultyID} — ${faculty.FacultyName} — ${faculty.DeptNames}`;
            if (String(faculty.FacultyID) === currentFac) opt.selected = true;
            facultySelect.appendChild(opt);
        });
    })
    .catch(err => console.error('Error loading available faculty:', err));


        // Fetch timeslots from get_timeslots.php
    fetch('get_timeslots.php')
    .then(response => response.json())
    .then(data => {
        const timeSelect = document.getElementById('timeSlotID');
        const currentTime = "<?php echo $loadedCourseSection['TimeSlotID']; ?>";
        timeSelect.innerHTML = "";

    data.forEach(time => {
        const opt = document.createElement('option');
        opt.value = time.id;
        opt.textContent = time.label;
        if (String(time.id) === currentTime) opt.selected = true;
        timeSelect.appendChild(opt);
        });
    })
    .catch(err => console.error('Error loading times:', err));

    // Fetch classrooms from get_classrooms.php
    const room_timeSlotID = "<?php echo $loadedCourseSection['TimeSlotID']; ?>";
    const room_semester   = "<?php echo $loadedCourseSection['SemesterID']; ?>";
    const room_year       = "<?php echo $loadedCourseSection['Year']; ?>";
    const room_currentRoom= "<?php echo $loadedCourseSection['RoomID']; ?>";

    fetch(`get_classrooms.php?timeSlotID=${room_timeSlotID}&semester=${room_semester}&year=${room_year}&current=${room_currentRoom}`)
    .then(response => response.json())
    .then(data => {
        const roomSelect = document.getElementById('roomID');
        const selectedRoom = "<?php echo $loadedCourseSection['RoomID']; ?>";
        roomSelect.innerHTML = "";

        data.forEach(room => {
            const opt = document.createElement('option');
            opt.value = room.id;
            opt.textContent = `${room.id} — ${room.type}`;
            if (String(room.id) === String(room_currentRoom)) opt.selected = true;  // ← FIXED
            roomSelect.appendChild(opt);
        });
    })
    .catch(err => console.error('Error loading Rooms:', err));


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

// Show success toast if update was successful
<?php if (!empty($_SESSION['update_success'])): ?>
    showToast("✅ Course Section updated successfully!");
    <?php unset($_SESSION['update_success']); ?>
<?php endif; ?>
</script>
</body>
</html>