<?php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/partials/user_context.php';
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

$search = $_GET['search'] ?? '';

$sql = "
SELECT 
    f.FacultyID,
    CONCAT(fu.FirstName, ' ', fu.LastName) AS FacultyName,
    GROUP_CONCAT(d.DeptName ORDER BY d.DeptName SEPARATOR ', ') AS DeptNames,
    GROUP_CONCAT(DISTINCT d.Phone ORDER BY d.DeptName SEPARATOR ', ') AS Phone,
    fu.Email, f.OfficeID, f.Ranking
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


?>


<!DOCTYPE html>
<html lang="en">
<?php $nu_title = 'Faculty Directory'; require __DIR__ . '/partials/head.php'; ?>
<body>
  <?php $nu_page = 'Faculty Directory'; $nu_crumb = ['viewDirectory.php', '← Back to Directory']; require __DIR__ . '/partials/header.php'; ?>

  <main id="main" tabindex="-1" class="page">
    <section class="hero card">
      <div class="card-head between">
        <div>
          <h2 class="card-title">View All Faculty</h2>
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
            <th scope="col">Faculty Name</th>
            <th scope="col">Email</th>
            <th scope="col">Office Location</th>
            <th scope="col">Department</th>
            <th scope="col">Dept Phone</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($faculty)): ?>
            <?php foreach ($faculty as $f): ?>
              <tr>

                <?php if ($userRole === 'admin'): ?>
                <td><a href="faculty_profile.php?facultyID=<?= urlencode($f['FacultyID']) ?>">
                      <?= htmlspecialchars($f['FacultyName']) ?> </a></td>
                <?php else: ?> <td><?= htmlspecialchars($f['FacultyName']) ?></td>
              <?php endif; ?>
                <td>
                  <a href="mailto:<?= htmlspecialchars($f['Email']) ?>">
                    <?= htmlspecialchars($f['Email']) ?>
                  </a>
                </td>
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
