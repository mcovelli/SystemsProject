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


$sql = "SELECT d.DeptID, d.DeptName, d.Email, d.Phone, d.RoomID, CONCAT(u.FirstName , ' ' , u.LastName) AS ChairName, d.ChairID FROM Department d JOIN Users u ON d.ChairID = u.UserID";
$stmt = $mysqli->prepare($sql);
$stmt->execute();
$res = $stmt->get_result();
$depts = $res->fetch_all(MYSQLI_ASSOC);
$stmt->close();


?>


<!DOCTYPE html>
<html lang="en">
<?php $nu_title = 'Department Directory'; require __DIR__ . '/partials/head.php'; ?>
<body>
  <?php $nu_page = 'Department Directory'; $nu_crumb = ['viewDirectory.php', '← Back to Directory']; require __DIR__ . '/partials/header.php'; ?>

  <main class="page">
    <section class="hero card">
      <div class="card-head between">
        <div>
          <h2 class="card-title">View All Departments</h2>
        </div>
      </div>

      <div class="table-wrap">
      <table id="coursesTable" border ="1" cellpadding="5" cellspacing="0">
        <thead><tr><th>Department</th><th>Email</th><th>Phone #</th><th>Office Location</th><th>Dept Chair</th></tr></thead>
          <tbody id="coursesBody">
            <?php if (!empty($depts)): ?>
              <?php foreach ($depts as $d): ?>
                <tr>
                  <td><a href="dept_profile.php?deptID=<?= urlencode($d['DeptID']) ?>">
                      <?= htmlspecialchars($d['DeptName']) ?> </a></td>
                  <td>
                    <a href="mailto:<?= htmlspecialchars($d['Email']) ?>"><?= htmlspecialchars($d['Email']) ?></a>
                  </td>
                  <td><?= htmlspecialchars($d['Phone']) ?></td>
                  <td><?= htmlspecialchars($d['RoomID']) ?></td>
                  <?php if ($userRole === 'admin'): ?>
                  <td><a href="faculty_profile.php?facultyID=<?= urlencode($d['ChairID']) ?>">
                        <?= htmlspecialchars($d['ChairName']) ?> </a></td>
                  <?php else: ?> <td><?= htmlspecialchars($d['ChairName']) ?></td>
                <?php endif; ?>
                <td>
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

  </body>

  <script>
    // Immediately create Lucide icons
    lucide.createIcons();

    // Populate the year in the footer
    document.getElementById('year').textContent = new Date().getFullYear();

</script>
</html>
