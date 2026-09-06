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

$sql = "SELECT cs.CRN, cs.CourseID, cs.CourseSectionNo, c.CourseName, CONCAT(fu.FirstName, ' ', fu.LastName) AS Professor, GROUP_CONCAT(DISTINCT day.DayOfWeek ORDER BY day.DayID SEPARATOR '/') AS Days,
            MIN(DATE_FORMAT(p.StartTime, '%l:%i %p')) AS StartTime,
            MAX(DATE_FORMAT(p.EndTime, '%l:%i %p'))   AS EndTime, 
            cs.RoomID, cs.SemesterID, cs.AvailableSeats, d.DeptName, c.CourseType
  FROM CourseSection cs 
  JOIN Course c ON cs.CourseID = c.CourseID
  JOIN Users fu ON cs.FacultyID = fu.UserID
  JOIN Department d ON c.DeptID = d.DeptID 
  JOIN TimeSlot ts ON cs.TimeSlotID = ts.TS_ID
  JOIN TimeSlotDay tsd ON ts.TS_ID = tsd.TS_ID
  JOIN Day day ON tsd.DayID = day.DayID
  JOIN TimeSlotPeriod tsp ON ts.TS_ID = tsp.TS_ID
  JOIN Period p ON tsp.PeriodID = p.PeriodID
  JOIN Semester s ON cs.SemesterID = s.SemesterID";


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

$sql .= "
  GROUP BY 
    cs.CRN,
    cs.CourseID,
    cs.CourseSectionNo,
    c.CourseName,
    Professor,
    cs.RoomID,
    cs.SemesterID,
    cs.AvailableSeats,
    d.DeptName,
    c.CourseType
  ORDER BY 
    cs.SemesterID,
    cs.CourseID,
    cs.CRN
";

$stmt = $mysqli->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$res = $stmt->get_result();
$courses = $res->fetch_all(MYSQLI_ASSOC);
$stmt->close();


$first = $user['FirstName'] ?? '';
$last  = $user['LastName'] ?? '';
$initials = ($first !== '' ? $first[0] : '') . ($last !== '' ? $last[0] : '');
if ($initials === '') { $initials = 'NU'; }
?>


<!DOCTYPE html>
<html lang="en">
<?php $nu_title = 'Course Section Directory'; require __DIR__ . '/partials/head.php'; ?>
<body>
  <?php $nu_page = 'Course Section Directory'; $nu_crumb = ['viewDirectory.php', '← Back to Directory']; require __DIR__ . '/partials/header.php'; ?>

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
        <thead><tr><th>CRN</th><th>Course ID</th><th>#</th><th>Course</th><th>Dept</th><th>Professor</th><th>Days</th><th>Time</th><th>Room</th><th>Semester</th><th># Seats</th><th>Course Type</th></tr></thead>
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
                  
                  <?php
                      // Handle combined days like "Mon/Wed" or "Tue/Thu"
                      $dayStr = (string)($c['DayOfWeek'] ?? $c['Days'] ?? '');
                      $dayStr = $dayStr === '' ? '—' : $dayStr;
                    ?>
                    <td><?= htmlspecialchars($dayStr) ?></td>

                    <?php
                      // Handle time display
                      $start = $c['StartTime'] ?? '';
                      $end   = $c['EndTime']   ?? '';
                      $timeStr = trim($start . ($start && $end ? ' – ' : '') . $end);
                      $timeStr = $timeStr === '' ? 'TBA' : $timeStr;
                    ?>
                    <td><?= htmlspecialchars($timeStr) ?></td>

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
    <?php require __DIR__ . '/partials/footer.php'; ?>

  <script>
    // Immediately create Lucide icons
    lucide.createIcons();

    // Populate the year in the footer
    document.getElementById('year').textContent = new Date().getFullYear();

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
