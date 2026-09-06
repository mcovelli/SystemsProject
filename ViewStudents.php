<?php
session_start();
require_once __DIR__ . '/config.php';
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['user_id']) || 
  ($_SESSION['role'] ?? '') !== 'admin' &&
($_SESSION['admin_type'] ?? '') !== 'view') {
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
$selectedMajor = $_GET['major'] ?? '';
$selectedMinor = $_GET['minor'] ?? '';

#Pulls data from database for student info
$sql = "SELECT CONCAT(su.FirstName, ' ', su.LastName) AS StudentName,
              s.StudentType,
              s.StudentID,
              su.Email,
              sm.MajorID,
              smn.MinorID,
              CASE 
                WHEN s.StudentType = 'Graduate' THEN p.ProgramName
                ELSE m.MajorName
              END AS MajorName,
              CASE 
                WHEN s.StudentType = 'Graduate' THEN 'N/A'
                  ELSE mn.MinorName
                END AS MinorName,
              CONCAT(fu.FirstName, ' ', fu.LastName) AS AdvisorName

        FROM Student s
        JOIN Users su ON s.StudentID = su.UserID
        LEFT JOIN StudentMajor sm ON s.StudentID = sm.StudentID
        LEFT JOIN StudentMinor smn ON s.StudentID = smn.StudentID
        LEFT JOIN Major m ON m.MajorID = sm.MajorID
        LEFT JOIN Minor mn ON mn.MinorID = smn.MinorID
        LEFT JOIN Graduate g ON s.StudentID = g.StudentID
        LEFT JOIN Program p ON g.ProgramID = p.ProgramID
        LEFT JOIN Advisor a ON s.StudentID = a.StudentID
        LEFT JOIN Department dm ON m.DeptID = dm.DeptID
        LEFT JOIN Users fu ON a.FacultyID = fu.UserID";

$conditions = [];
$params = [];
$types = "";

if (!empty($selectedMajor)) {
    $conditions[] = "m.MajorName = ?";
    $params[] = $selectedMajor;
    $types .= "s";
}

if (!empty($selectedMinor)) {
    $conditions[] = "mn.MinorName = ?";
    $params[] = $selectedMinor;
    $types .= "s";
}

if(!empty($conditions)) {
  $sql .= " WHERE " . implode(" AND ", $conditions);
}

$stmt = $mysqli->prepare($sql);
if (!empty($params)) {
    $bind = [];
    $bind[] = $types;

    foreach ($params as $key => $value) {
        $bind[] = &$params[$key];
    }

    call_user_func_array([$stmt, 'bind_param'], $bind);
}

$stmt->execute();
$res = $stmt->get_result();
$student = $res->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$search = $_GET['search'] ?? '';

$sql = "SELECT 
        s.StudentID,
        CONCAT(u.FirstName, ' ', u.LastName) AS StudentName,
        CASE
          WHEN s.StudentType = 'Graduate' THEN ANY_VALUE(p.ProgramName)
            ELSE GROUP_CONCAT(DISTINCT m.MajorName ORDER BY m.MajorName SEPARATOR ', ')
          END AS MajorName,
        CASE
          WHEN s.StudentType = 'Graduate' THEN 'N/A'
            ELSE ANY_VALUE(mn.MinorName)
          END AS MinorName,
        u.Email,
        s.StudentType,
        CONCAT(ANY_VALUE(fu.FirstName), ' ', ANY_VALUE(fu.LastName)) AS AdvisorName
    FROM Student s
    JOIN Users u ON s.StudentID = u.UserID
    LEFT JOIN Advisor a ON s.StudentID = a.StudentID
    LEFT JOIN Users fu ON a.FacultyID = fu.UserID
    LEFT JOIN StudentMajor sm ON s.StudentID = sm.StudentID
    LEFT JOIN StudentMinor smn ON s.StudentID = smn.StudentID
    LEFT JOIN Major m ON sm.MajorID = m.MajorID
    LEFT JOIN Minor mn ON smn.MinorID = mn.MinorID
    LEFT JOIN Graduate g ON s.StudentID = g.StudentID
    LEFT JOIN Program p ON g.ProgramID = p.ProgramID";

if (!empty($search)) {
    $sql .= " 
    GROUP BY s.StudentID, u.FirstName, u.LastName
    HAVING 
        s.StudentID LIKE CONCAT('%', ?, '%')
        OR
        u.FirstName LIKE CONCAT('%', ?, '%')
        OR
        u.LastName LIKE CONCAT('%', ?, '%')
        OR
        MajorName LIKE CONCAT('%', ?, '%')
        OR
        MinorName LIKE CONCAT('%', ?, '%')
        OR
        s.StudentType = ?
    ";
} else {
    $sql .= "
    GROUP BY s.StudentID, u.FirstName, u.LastName, u.Email
    ";
}

$sql .= " ORDER BY s.StudentID ASC";


$stmt = $mysqli->prepare($sql);

if (!empty($search)) {
    $stmt->bind_param("ssssss", $search, $search, $search, $search, $search, $search);
}

$stmt->execute();
$res = $stmt->get_result();
$student_search = $res->fetch_all(MYSQLI_ASSOC);
$stmt->close();



?>


<!DOCTYPE html>
<html lang="en">
<?php $nu_title = 'Student Directory'; require __DIR__ . '/partials/head.php'; ?>
<body>
  <?php $nu_page = 'Student Directory'; $nu_crumb = ['viewDirectory.php', '← Back to Directory']; require __DIR__ . '/partials/header.php'; ?>

  <main class="page">
    <section class="hero card">
      <div class="card-head between">
        <div>
          <h2 class="card-title">View Students</h2>
          <div class="sub muted">Filter By Student Type, Major/Minor, Name</div>
        </div>
      </div>
      <form method="GET" id="filterForm" style="margin-bottom: 20px;">
        <label for="search">Search:</label>
        <input type="text" id="search" name="search"
         placeholder="Search name or department..."
         value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">

        <button type="submit">Search</button>
      </form>
      <p>Click StudentID for students profile</p>

    <div class="table-wrap">
      <table border="1" cellpadding="5" cellspacing="0">
        <thead>
          <tr>
            <th>Student Type</th>
            <th>Student ID</th>
            <th>Student Name</th>
            <th>Email</th>
            <th>Major</th>
            <th>Minor</th>
            <th>Advisor</th>
          </tr>
        </thead>
        <tbody>
          <?php 
            $rows = !empty($search) ? $student_search : $student;

            if (!empty($rows)):
                foreach ($rows as $s):
          ?>
              <tr>
                <td><?= htmlspecialchars($s['StudentType']) ?></td>
                <td><?= htmlspecialchars($s['StudentID'])?></td>
                <td><a href="student_profile.php?studentID=<?= urlencode($s['StudentID']) ?>">
                      <?= htmlspecialchars($s['StudentName']) ?> </a></td>
                <td><?= htmlspecialchars($s['Email']) ?></td>
                <td><?= $s['MajorName'] ? htmlspecialchars($s['MajorName']) : 'Undeclared' ?></td>
                <td><?= $s['MinorName'] ? htmlspecialchars($s['MinorName']) : 'Undeclared'  ?></td>
                <td><?= $s['AdvisorName'] ?htmlspecialchars($s['AdvisorName']) : 'Unassigned'  ?></td>
              </tr>
            <?php endforeach; ?>
              <?php else: ?>
                  <tr><td colspan="7">No student found.</td></tr>
            <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
<?php require __DIR__ . '/partials/footer.php'; ?>
  <script>
    // Immediately create Lucide icons
    lucide.createIcons();

    // Populate the year in the footer
    document.getElementById('year').textContent = new Date().getFullYear();

        // Fetch majors
      fetch('get_majors.php')
        .then(response => response.json())
        .then(data => {
          const majorSelect = document.getElementById('major');
          const selectedMajor = new URLSearchParams(window.location.search).get('major');

          data.forEach(item => {
            const opt = document.createElement('option');
            opt.value = item.name;
            opt.textContent = item.name;
            if (item.name === selectedMajor) opt.selected = true;
            majorSelect.appendChild(opt);
        });
        })
        .catch(err => console.error('Error loading majors:', err));

          // Fetch minors
      fetch('get_minors.php')
        .then(response => response.json())
        .then(data => {
          const minorSelect = document.getElementById('minor');
          const selectedMinor = new URLSearchParams(window.location.search).get('minor');

          data.forEach(item => {
            const opt = document.createElement('option');
            opt.value = item.name;
            opt.textContent = item.name;
            if (item.name === selectedMinor) opt.selected = true;
            minorSelect.appendChild(opt);
        });
        })
        .catch(err => console.error('Error loading minors:', err));
    </script>
  </body>
</html>