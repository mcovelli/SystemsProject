<?php
session_start();
require_once __DIR__ . '/config.php';
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'admin' ||
    (
        ($_SESSION['admin_type'] ?? '') !== 'view' &&
        ($_SESSION['admin_type'] ?? '') !== 'update'
    )
) {
    redirect(PROJECT_ROOT . "/login.html");
    exit;
}

$mysqli = get_db();
$mysqli->set_charset('utf8mb4');

$search = $_GET['search'] ?? '';

$sql = "
SELECT 
    f.FacultyID,
    CONCAT(fu.FirstName, ' ', fu.LastName) AS FacultyName,
    GROUP_CONCAT(d.DeptName ORDER BY d.DeptName SEPARATOR ', ') AS DeptNames,
    fd.DeptID, d.Phone, d.Email, f.OfficeID
FROM Faculty f
JOIN Users fu ON f.FacultyID = fu.UserID
JOIN Faculty_Dept fd ON f.FacultyID = fd.FacultyID
JOIN Department d ON fd.DeptID = d.DeptID
";

if (!empty($search)) {
    $sql .= " 
    GROUP BY f.FacultyID, fu.FirstName, fu.LastName, fu.Email, f.OfficeID
    HAVING 
        FacultyName LIKE CONCAT('%', ?, '%')
        OR DeptNames LIKE CONCAT('%', ?, '%')
    ";
} else {
    $sql .= "
    GROUP BY f.FacultyID, fu.FirstName, fu.LastName, fu.Email, f.OfficeID
    ";
}

$sql .= " ORDER BY f.FacultyID ASC";


$stmt = $mysqli->prepare($sql);

if (!empty($search)) {
    $stmt->bind_param("ss", $search, $search);
}

$stmt->execute();
$res = $stmt->get_result();
$faculty = $res->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$userRole = strtolower($_SESSION['role'] ?? '');
switch ($userRole) {
    case 'student':
        $dashboard = 'student_dashboard.php';
        break;
    case 'faculty':
        $dashboard = 'faculty_dashboard.php';
        break;
    case 'admin':
        // if you have update/view admin types:
        if (($_SESSION['admin_type'] ?? '') === 'update') {
            $dashboard = 'update_admin_dashboard.php';
        } else {
            $dashboard = 'view_admin_dashboard.php';
        }
        break;
    case 'statstaff':
        $dashboard = 'statstaff_dashboard.php';
        break;
    default:
        $dashboard = 'login.html'; // fallback
}

?>

<!doctype html>
<html lang="en" data-theme="light">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Northport University — View Faculty</title>
  <link rel="stylesheet" href="./viewstyles.css">
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
      <div class="crumb">
        <a href="<?= htmlspecialchars($dashboard) ?>" aria-label="Back to Dashboard">← Back to Dashboard</a>
      </div>
    </div>
  </header>

  <main class="page">
    <section class="hero card">
      <div class="card-head between">
        <div>
          <h2 class="card-title">View All Course Sections</h2>
        </div>
      </div>

      <form method="GET" style="margin-bottom: 20px;">
        <label for="search">Search:</label>
        <input type="text" id="search" name="search"
         placeholder="Search name or department..."
         value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">

        <button type="submit">Search</button>
      </form>
  
    <div class="table-wrap">
      <table border="1" cellpadding="5" cellspacing="0">
        <thead>
          <tr>
            <th>Faculty Name</th>
            <th>Email</th>
            <th>Office Location</th>
            <th>Department</th>
            <th>Dept Phone</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($faculty)): ?>
            <?php foreach ($faculty as $f): ?>
              <tr>
                <td><?= htmlspecialchars($f['FacultyName']) ?></td>
                <td><?= htmlspecialchars($f['Email']) ?></td>
                <td><?= htmlspecialchars($f['OfficeID']) ?></td>
                <td><?= htmlspecialchars($f['DeptNames']) ?></td>
                <td><?= htmlspecialchars($f['Phone']) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="5">No faculty found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>

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
            if (name === selected) opt.selected = true;
            deptSelect.appendChild(opt);
          });
        })
    </script>
  </body>
</html>
