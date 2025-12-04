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
    $CourseID = $_POST['courseID'] ?? '';
    $CourseSectionNo = $_POST['courseSectionNo'] ?? '';
    $FacultyId = $_POST['facultyID'] ?? '';
    $TimeSlotId = $_POST['timeSlotID'] ?? '';
    $SemesterId = $_POST['semesterID'] ?? '';
    $RoomId = $_POST['roomID'] ?? '';

        $roomSql = "
            SELECT 
                CASE 
                    WHEN r.RoomType = 'Lecture' THEN l.NumSeats
                    WHEN r.RoomType = 'Lab' THEN b.NumWorkStations
                    ELSE 0
                END AS AvailableSeats
            FROM Room r
            LEFT JOIN Lecture l ON r.RoomID = l.LectureID
            LEFT JOIN Lab b ON r.RoomID = b.LabID
            WHERE r.RoomID = ?
        ";

        $stmt = $mysqli->prepare($roomSql);
        $stmt->bind_param("i", $RoomId);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();

    $AvailableSeats = $row['AvailableSeats'] ?? 0;
    $Year = substr($SemesterId, -4);
    $Status = $_POST['status'] ?? 'PLANNED';

$mysqli->begin_transaction();

  $sql = "INSERT INTO CourseSection (CourseID, CourseSectionNo, FacultyID, TimeSlotID, RoomID, Year, SemesterID, AvailableSeats, Status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'PLANNED')";
  $stmt = $mysqli->prepare($sql);
  $stmt->bind_param("siiisssi", $CourseID, $CourseSectionNo, $FacultyId, $TimeSlotId, $RoomId, $Year, $SemesterId, $AvailableSeats);
        
  if ($stmt->execute()) {
    echo "alert('$CourseID. created ✅');";
  } else {
    echo "alert('Could not create course section');";
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
<title>Create Course Sections</title>
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
      <span class="pill">Create Course Sections</span>
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
                  <h1 class="card-title">Create Course Section</h1>
                </div>
            </div>
                <div id = "create-section-course">
                    <form id = "CreateCourseSection" method = "POST" action = "">
                      <label for="courseSectionNo" hidden>Course Section No.: </label>
                      <input type = "hidden" id="courseSectionNo" name="courseSectionNo" required placeholder="ex. 1"><br>

                        <label for="courseID">Course ID: </label>
                             <select name="courseID" id="courseID">
                                </select><br>

                        <label for="timeSlotID">Time Slot: </label>
                             <select name="timeSlotID" id="timeSlotID">
                                <option><-- Unassigned --></option>
                                </select><br>

                        <label for="facultyID">Faculty: </label>
                             <select name="facultyID" id="facultyID">
                                <option><-- Unassigned --></option>
                                </select><br>

                        <label for="roomID">Room: </label>
                             <select name="roomID" id="roomID">
                                <option><-- Unassigned --></option>
                                </select><br>

                        <label for="semesterID">Semester: </label>
                             <select name="semesterID" id="semesterID">
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

    // Fetch faculty from get_faculty.php
    fetch('get_faculty.php')
    .then(response => response.json())
    .then(data => {
        const facultySelect = document.getElementById('facultyID');
        const selectedFaculty = new URLSearchParams(window.location.search).get('facultyID');

    data.forEach(faculty => {
        const opt = document.createElement('option');
        opt.value = faculty.FacultyID;
        opt.textContent = faculty.FacultyID + ' - ' + faculty.FacultyName + ' - ' + faculty.DeptNames;
        facultySelect.appendChild(opt);
        });
    })
    .catch(err => console.error('Error loading faculty:', err));


const ts = document.getElementById('timeSlotID');
const sem = document.getElementById('semesterID');
const yr = () => document.getElementById('semesterID').value.slice(-4);

// Load rooms when timeslot or semester changes
function loadRooms() {
    const timeSlotID = ts.value;
    const semesterID = sem.value;
    const year = yr();

    if (!timeSlotID || !semesterID) return;

    fetch(`get_classrooms.php?timeSlotID=${timeSlotID}&semester=${semesterID}&year=${year}`)
        .then(r => r.json())
        .then(data => {
            const roomSelect = document.getElementById('roomID');
            roomSelect.innerHTML = "<option><-- Unassigned --></option>";

            data.forEach(room => {
                const opt = document.createElement('option');
                opt.value = room.id;
                opt.textContent = `${room.id} — ${room.type}`;
                roomSelect.appendChild(opt);
            });
        });
}

ts.addEventListener("change", loadRooms);
sem.addEventListener("change", loadRooms);

    // Fetch courses from get_course.php
    fetch('get_courses.php')
    .then(response => response.json())
    .then(data => {
        const courseSelect = document.getElementById('courseID');
        const selectedCourse = new URLSearchParams(window.location.search).get('courseID');

    data.forEach(course => {
        const opt = document.createElement('option');
        opt.value = course.courseID;
        opt.textContent = course.courseName;
        courseSelect.appendChild(opt);
        });
    })
    .catch(err => console.error('Error loading courses:', err));

    // Fetch timeslots from get_timeslots.php
    fetch('get_timeslots.php')
    .then(response => response.json())
    .then(data => {
        const timeSelect = document.getElementById('timeSlotID');
        const selectedTime = new URLSearchParams(window.location.search).get('timeSlotID');

    data.forEach(time => {
        const opt = document.createElement('option');
        opt.value = time.id;
        opt.textContent = time.label;
        timeSelect.appendChild(opt);
        });
    })
    .catch(err => console.error('Error loading times:', err));

    // Fetch semesters from get_semesters.php
    fetch('get_semesters.php')
    .then(response => response.json())
    .then(data => {
        const semesterSelect = document.getElementById('semesterID');
        const selectedSemester = new URLSearchParams(window.location.search).get('semesterID');

    data.forEach(semester => {
        const opt = document.createElement('option');
        opt.value = semester.SemesterID;
        opt.textContent = semester.SemesterName + ' ' + semester.Year;
        semesterSelect.appendChild(opt);
        });
    })
    .catch(err => console.error('Error loading semesters:', err));

    document.getElementById("CreateCourseSection").addEventListener("submit", (e) => {
    console.log("Form submitted");
});
</script>

</body>
</html>