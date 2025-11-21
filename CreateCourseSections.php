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
    $CourseId = $_POST['courseID'] ?? '';
    $CourseSectionNo = $_POST['courseSectionNo'] ?? '';
    $FacultyId = $_POST['facultyID'] ?? '';
    $TimeSlotId = $_POST['timeSlotID'] ?? '';
    $SemesterId = $_POST['semesterID'] ?? '';
    $RoomId = $_POST['roomID'] ?? '';

        $sql = "
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

        $stmt = $mysqli->prepare($sql);
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
  $stmt->bind_param("siiisssi", $CourseId, $CourseSectionNo, $FacultyId, $TimeSlotId, $RoomId, $Year, $SemesterId, $AvailableSeats);
        
  if ($stmt->execute()) {
    echo "alert('$CourseId. created ✅');";
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
<title>Program Directory</title>
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
      <span class="pill">Program Directory</span>
    </div>
    <div class="top-actions">
      <div class="search">
        <i class="search-icon" data-lucide="search"></i>
        <input type="text" placeholder="Search courses, people, anything…" />
      </div>
      <button class="icon-btn" aria-label="Notifications"><i data-lucide="bell"></i></button>
      <button id="themeToggle" class="icon-btn" aria-label="Toggle theme"><i data-lucide="moon"></i></button>
      <div class="divider"></div>
      <div class="crumb"><a href="createDirectory.php" aria-label="Back to Directory">← Back to Directory</a></div>
    </div>

    <div class="avatar" aria-hidden="true"><span id="initials"><?php echo $initials ?: 'NU'; ?></span></div>
        <div class="user-meta"><div class="name"><?php echo htmlspecialchars($user['UserType']) ?></div></div>
        <div class="dropdown">
          <button>☰ Menu</button>
          <div class="dropdown-content">
            <a href="<?= htmlspecialchars($dashboard) ?>">Dashboard</a>
            <a href="<?= htmlspecialchars($dashboard) ?>">Profile</a>
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
                        <label for="courseID">Course ID: </label>
                             <select name="courseID" id="courseID">
                                </select><br>

                        <label for="courseSectionNo">Course Section No.: </label>
                             <input type = "number" id="courseSectionNo" name="courseSectionNo" required placeholder="ex. 1"><br>

                        <label for="facultyID">Faculty: </label>
                             <select name="facultyID" id="facultyID">
                                <option><-- Unassigned --></option>
                                </select><br>

                        <label for="roomID">Room: </label>
                             <select name="roomID" id="roomID">
                                <option><-- Unassigned --></option>
                                </select><br>

                        <label for="timeSlotID">Time Slot: </label>
                             <select name="timeSlotID" id="timeSlotID">
                                </select><br>

                        <label for="semesterID">Semester: </label>
                             <select name="semesterID" id="semesterID">
                                </select><br>

                        <button type="submit" id = "submit">Submit</button>
                    </form>
                </div>
        </section>
    </main>

<body>
</body>

<script>

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


// Fetch classrooms from get_classrooms.php
    fetch('get_classrooms.php')
    .then(response => response.json())
    .then(data => {
        const roomSelect = document.getElementById('roomID');
        const selectedRoom = new URLSearchParams(window.location.search).get('roomID');

    data.forEach(room => {
        const opt = document.createElement('option');
        opt.value = room.id;
        opt.textContent = `${room.id} — ${room.type}`;
        if (String(room.id) === selectedRoom) opt.selectedRoom = true;
        roomSelect.appendChild(opt);
        });
    })
    .catch(err => console.error('Error loading Rooms:', err));

    // Fetch courses from get_course.php
    fetch('get_courses.php')
    .then(response => response.json())
    .then(data => {
        const courseSelect = document.getElementById('courseID');
        const selectedCourse = new URLSearchParams(window.location.search).get('courseID');

    data.forEach(course => {
        const opt = document.createElement('option');
        opt.value = course.id;
        opt.textContent = course.id;
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
        opt.textContent = semester.SemesterID;
        semesterSelect.appendChild(opt);
        });
    })
    .catch(err => console.error('Error loading semesters:', err));

    document.getElementById("CreateCourseSection").addEventListener("submit", (e) => {
    console.log("Form submitted");
});
</script>
