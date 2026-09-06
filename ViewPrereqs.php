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

$search = $_GET['search'] ?? '';

$sql = "
SELECT 
    CourseID,
    PrerequisiteCourseID,
    MinGradeRequired
FROM CoursePrerequisite
";

$types = "";
$params = [];

if (!empty($search)) {
    $sql .= "
    WHERE (
        CourseID LIKE CONCAT('%', ?, '%')
        OR PrerequisiteCourseID LIKE CONCAT('%', ?, '%')
        OR MinGradeRequired LIKE CONCAT('%', ?, '%')
    )
    ";
    $types .= "sss";
    $params = [$search, $search, $search];
}

$sql .= " ORDER BY CourseID ASC";

$stmt = $mysqli->prepare($sql);

if (!empty($search)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();


?>


<!DOCTYPE html>
<html lang="en">
<?php $nu_title = 'Prerequisite Directory'; require __DIR__ . '/partials/head.php'; ?>
<body>
  <?php $nu_page = 'Prerequisite Directory'; $nu_crumb = ['viewDirectory.php', '← Back to Directory']; $nu_search = false; $nu_bell = false; require __DIR__ . '/partials/header.php'; ?>

 <main id="main" tabindex="-1" class="page">
    <section class="hero card">
      <div class="card-head between">
        <div>
          <h2 class="card-title">View Prerequisites</h2>
          <div class="sub muted">Search By Course</div>
        </div>
      </div>
      <div style="margin-top:12px">
        <form method="GET" style="margin-bottom: 20px;">
          <label for="search">Search:</label>
          <input type="text" id="search" name="search"
           placeholder="Search name or department..."
           value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">

          <button type="submit">Search</button>
        </form>
    </div>

      <div class="table-wrap">
        <table id="majorsTable" border="2" cellpadding="5" cellspacing="0">
          <thead><tr><th scope="col">Course</th><th scope="col">Prerequisite Course</th><th scope="col">Minimum Grade</th></tr></thead>
            <tbody id="majorsBody">
              <?php if (!empty($results)): ?>
                <?php foreach ($results as $m): ?>
                  <tr>
                    <td><?= htmlspecialchars($m['CourseID']) ?></td>
                    <td><?= htmlspecialchars($m['PrerequisiteCourseID']) ?></td>
                    <td><?= htmlspecialchars($m['MinGradeRequired']) ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                  <tr><td colspan="6">No prerequisites found.</td></tr>
              <?php endif; ?>
              </tbody>
            </table>
        </div>
    </section>
    <?php require __DIR__ . '/partials/footer.php'; ?>
    </main>

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

    </script>

  </body>
</html>
