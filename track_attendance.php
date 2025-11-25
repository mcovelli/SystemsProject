<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'faculty'){
    redirect(PROJECT_ROOT . "/login.html");
}

$userId = $_SESSION['user_id'];

$mysqli = get_db();
$mysqli->set_charset('utf8mb4');

$sql = "SELECT UserID, FirstName, LastName, Email, UserType, Status, DOB
        FROM Users WHERE UserID = ? LIMIT 1";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$res = $stmt->get_result();
$student = $res->fetch_assoc();
$stmt->close();

$courses_sql = "
  SELECT 
      s.SemesterID,
      cs.CourseID,
      c.CourseName,
      c.Credits,
      se.Grade,
      se.Status
  FROM StudentEnrollment se
  JOIN CourseSection cs ON se.CRN = cs.CRN 
  AND se.SemesterID = cs.SemesterID
  JOIN Course c ON cs.CourseID = c.CourseID
  JOIN Semester s ON se.SemesterID = s.SemesterID
  WHERE se.StudentID = ? 
  ORDER BY s.Year DESC, s.SemesterName DESC
";
$courses_stmt = $mysqli->prepare($courses_sql);
$courses_stmt->bind_param('i', $userId);
$courses_stmt->execute();
$courses_result = $courses_stmt->get_result();
$courses = $courses_result->fetch_all(MYSQLI_ASSOC);
$courses_stmt->close();

$student_sql = "
SELECT
  csa.StudentID,
  csa.CRN,
  csa.CourseID,
  csa.AttendanceDate,
  c.CourseName,
  cs.SemesterID,
  cs.CourseSectionNo,
  cs.TimeSlotID,
  s.StudentType,
  se.Status,
  se.Grade,
  u.UGStudentType,
  g.GradStudentType
FROM CourseSectionAttendance csa
JOIN Course c 
    ON c.CourseID = csa.CourseID
JOIN CourseSection cs 
    ON cs.CourseID = csa.CourseID
   AND cs.CRN = csa.CRN
JOIN Student s 
    ON s.StudentID = csa.StudentID
JOIN StudentEnrollment se 
    ON se.StudentID = csa.StudentID
   AND se.CRN = csa.CRN
   AND se.CourseID = csa.CourseID
JOIN TimeSlot ts 
    ON cs.TimeSlotID = ts.TS_ID
JOIN TimeSlotPeriod tsp 
    ON ts.TS_ID = tsp.TS_ID
JOIN TimeSlotDay tsd 
    ON ts.TS_ID = tsd.TS_ID
JOIN Period p 
    ON tsp.PeriodID = p.PeriodID
JOIN Day d 
    ON tsd.DayID = d.DayID
LEFT JOIN Undergraduate u 
    ON u.StudentID = csa.StudentID
LEFT JOIN Graduate g 
    ON g.StudentID = csa.StudentID
WHERE csa.StudentID = ?
ORDER BY csa.StudentID
";

$student_stmt = $mysqli->prepare($student_sql);
$student_stmt->bind_param('i', $userId);
$student_stmt->execute();
$student_result = $student_stmt->get_result();
$student = $student_result->fetch_all(MYSQLI_ASSOC);
$student_stmt->close();

$location_sql = "
SELECT 
r.RoomID,
r.RoomNo,
r.BuildingID
FROM Room r
JOIN Building b on b.BuildingID = r.BuildingID
JOIN CourseSection cs on cs.RoomID = r.RoomID
";

$location_stmt = $mysqli->prepare($location_sql);
$location_stmt->execute();
$location_result = $location_stmt->get_result();
$location = $location_result->fetch_all(MYSQLI_ASSOC);
$location_stmt->close();

$userRole = strtolower($_SESSION['role'] ?? '');
switch ($userRole) {
    case 'student':  $dashboard = 'student_dashboard.php'; break;
    case 'faculty':  $dashboard = 'faculty_dashboard.php'; break;
    case 'admin':
        $dashboard = ($_SESSION['admin_type'] ?? '') === 'update'
            ? 'update_admin_dashboard.php'
            : 'view_admin_dashboard.php';
        break;
    default: $dashboard = 'login.php';
}

?>


<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Track Attendance | Northport University</title>
<link rel="stylesheet" href="track_attendance.css"/>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
</head>
<body>
  <div class = "page">
   <header class="topbar">
    <button class="btn" id="exportBtn"><p><a href="<?= htmlspecialchars($dashboard) ?>">← Back to Dashboard</a></p>
    </button>
    <div class="brand">
    <div class="logo"><i data-lucide="graduation-cap"></i></div>
    <h1>Northport University</h1>
    </div>
    </header>

    
       <div class="card" style="margin-top:16px; margin-left:10px; margin-right:10px">
        <div class="card-head"><div>Track Attendance</div></div>
        <div class = "card-body">
          <div class="controls" style="margin-bottom:16px; margin-left:10px; margin-right:10px">
            <div class = "label">
            <label for="attendanceDate">
              <div>Choose Date:</div>
              <input type = "date" id = "attendanceDate" name = "attendanceDate">
            </label>
            </div>
            <div class = "label">
            <label for ="teacher-courses">
              <div>Select Course:</div>
              <select id = "teacher-courses">
                <option value = "Intro to Programming">Intro to Programming</option>
                <option value = "C++">C++</option>
                <option value = "Web Development">Web Development</option>
            </select>
              </label>
            </div>
              <div class = "label" style="margin-top:16px">
            <button id = "selectButton">Choose Date & Course</button>
            </div>
           </div>
          </div>
        </div>
      </div>

      <div id = "attendance-chart" class="card" style="margin-top:16px; margin-left:10px; margin-right:10px">
      <div class="card-head"><div>Attendance Chart</div></div>
        <div class="card-body">
        <div class = "table-wrap">
        <table id = "daily-schedule">
            <thead>
                <tr>
                    <th>Attendance Number</th>
                    <th>Student Name</th>
                    <th>Status</th>
                    <th>Comments</th>
                </tr>
            </thead>
            <tbody id = "attendance"></tbody>
            <tfoot id = "student-count">
            </tfoot>
            </table>
          </div>
        </div>
      </div>  
      </div>
    </main>
  </div>
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


    const courseList = {
      'Intro to Programming': {
        course_id: 'CS1101',
        daysOfWeek: ['Monday', 'Wednesday'],
        section: 1,
        attendanceRecords: [
        {
          date: "2025-10-20",
          roster: [
          {attn_number: 1, studentname: 'Javier Alejandro', status: 'Present', comments: 'Attendance is perfect'},
          {attn_number: 2, studentname: 'Rajan Bhowmick', status: 'Absent', comments: 'Called in sick'},
          {attn_number: 3, studentname: 'William Chen', status: 'Late', comments: 'Had to speak with another professor'},
          {attn_number: 4, studentname: 'Puneet Khan', status: 'Present', comments: 'First day back after grandma died'}
        ]
        },
        {
        date: "2025-10-22",
        roster: [
          {attn_number: 1, studentname: 'Javier Alejandro', status: 'Present', comments: ''},
          {attn_number: 2, studentname: 'Rajan Bhowmick', status: 'Late', comments: 'Got caught up in something that wasn&#39;t his fault'},
          {attn_number: 3, studentname: 'William Chen', status: 'Absent', comments: 'Got sick so he will take test next class'},
          {attn_number: 4, studentname: 'Puneet Khan', status: 'Present', comments: 'Feeling much better'}
        ]
      }
    ]
    },
      'C++': {
        course_id: 'CS2300',
        section: 2,
        daysOfWeek: ['Tuesday, Thursday'],
        attendanceRecords: [
        {
        date: "2025-10-21",
        roster: [
          {attn_number: 1, studentname: 'Terrell Webster', status: 'Absent', comments: 'Terry had to leave school early for something personal.'},
          {attn_number: 2, studentname: 'Saif Hassan', status: 'Present', comments: ''},
          {attn_number: 3, studentname: 'Garrett Kim', status: 'Late', comments: 'Got into a traffic jam on the way to school'},
          {attn_number: 4, studentname: 'Mersal George', status: 'Present', comments: ''}
        ]
        },
        {
        date:'2025-10-23',
        roster: [
          {attn_number: 1, studentname: 'Terrell Webster', status: 'Present', comments: 'Even though it was review day last class, Terry still managed to finish the quiz first!'},
          {attn_number: 2, studentname: 'Saif Hassan', status: 'Absent', comments: 'Called in sick.'},
          {attn_number: 3, studentname: 'Garrett Kim', status: 'Present', comments: 'Still doing very well in this course.'},
          {attn_number: 4, studentname: 'Mersal George', status: 'Present', comments: 'Quiz was somewhat challenging for her.'}
        ]
        }
      ]
    },
    'Web Development': {
      course_id: 'CS2300',
        section: 1,
        daysOfWeek: ['Tuesday, Thursday'],
        attendanceRecords: [
        {
        date: '2025-10-21',
        roster: [
          {attn_number: 1, studentname: 'Kyle Guarino', status: 'Late', comments: 'Was on his way from an event held earlier on campus.'},
          {attn_number: 2, studentname: 'Zehra Singh', status: 'Absent', comments: 'First day she has missed this semester.'},
          {attn_number: 3, studentname: 'Owen Li', status: 'Present', comments: ''},
          {attn_number: 4, studentname: 'Raul Velez', status: 'Present', comments: ''}
        ]
        },
        {
        date: '2025-10-23',
        roster:[
          {attn_number: 1, studentname: 'Kyle Guarino', status: 'Present', comments: 'Had to leave class early for something personal.'},
          {attn_number: 2, studentname: 'Zehra Singh', status: 'Present', comments: ''},
          {attn_number: 3, studentname: 'Owen Li', status: 'Late', comments: 'Had to speak with his previous professor briefly'},
          {attn_number: 4, studentname: 'Raul Velez', status: 'Present', comments: ''}
        ]
      }
    ]
    }
  }

  function studentRow (s){
    const student = document.createElement('tr');
      student.style.display='grid';
      student.style.gridTemplateColumns='1fr auto auto auto auto';
      student.style.gap='12px';
      student.style.padding='10px 0';
      student.innerHTML = `
        <td><strong>${s.attn_number}</strong></td>
        <td>${s.studentname}</td>
        <td>${s.status}</td>
        <td>${s.comments}</td>`;
      return student;
    }

    function showAttendanceByCourse (courseName, dateOfCourse){
      const attendanceChart = document.getElementById("attendance");
      attendanceChart.innerHTML = " ";

      const course = courseList[courseName];
      if(!course){
        attendanceChart.innerHTML = `<tr><td colspan = "4">Course not found</td></tr>`;
        return;
      } 

      const record = course.attendanceRecords.find(r => r.date === dateValue);
      if(!record){
        attendanceChart.innerHTML = `<tr><td colspan = "4">Record not found.</td></tr>`;
        return;
      }

    let presentStudentNumber = 0;
    let lateStudentNumber = 0;
    let absentStudentNumber = 0;
    record.roster.forEach(s => {
    attendanceChart.appendChild(studentRow(s));
    if (s.status === 'Present') presentStudentNumber++;
    if (s.status === 'Late') lateStudentNumber++;
    if (s.status === 'Absent') absentStudentNumber++;
    })
    document.getElementById("student-count").innerHTML = 
           ` <tr><td colspan = "12">Present: ${presentStudentNumber} | Late : ${lateStudentNumber} | Absent: ${absentStudentNumber}</td></tr> `;
    }

    function selectDateAndCourse(){
      const courseName = document.getElementById('teacher-courses').value;
      const dateOfCourse = document.getElementById('attendanceDate').value;
      showAttendanceByCourse(courseName, dateOfCourse);
    }

    document.getElementById("selectButton").addEventListener("click", selectDateAndCourse);
  </script>
  <footer class="footer">© <span id="year"></span> Northport University • All rights reserved</footer>
</body>
</html>