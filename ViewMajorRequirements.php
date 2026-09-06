<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['user_id'])) {
    redirect(PROJECT_ROOT . "/login.html");
}

$userId = $_SESSION['user_id'];
$majorID = $_GET['majorID'] ?? null;
$majorName = $_GET['MajorName'] ?? 'Major';
if (!$majorID) die("No majorID provided.");

$mysqli = get_db();
$mysqli->set_charset('utf8mb4');

$sql = "SELECT UserID, FirstName, LastName, Email, UserType, Status, DOB
        FROM Users WHERE UserID = ? LIMIT 1";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$res = $stmt->get_result();
$user = $res->fetch_assoc();
$stmt->close();

$major_requirement_sql = "SELECT mr.MajorID, m.MajorName, mr.CourseID, mr.RequirementType, c.Course_Desc, c.CourseName, c.Credits
                       FROM MajorRequirement mr
                       JOIN Major m ON mr.MajorID = m.MajorID
                       JOIN Course c ON mr.CourseID = c.CourseID
                       WHERE mr.MajorID = ?";

$major_requirement_stmt = $mysqli->prepare($major_requirement_sql);
$major_requirement_stmt->bind_param("i", $majorID);
$major_requirement_stmt->execute();
$major_requirement_res = $major_requirement_stmt->get_result();
$major_requirement = $major_requirement_res->fetch_all(MYSQLI_ASSOC);
$major_requirement_stmt->close();


?>


<!DOCTYPE html>
<html lang="en">
<?php $nu_title = 'Major Requirements'; require __DIR__ . '/partials/head.php'; ?>
<body>
  <?php $nu_page = 'Major Requirements'; $nu_crumb = ['viewDirectory.php', '← Back to Directory']; require __DIR__ . '/partials/header.php'; ?>

  <main id="main" tabindex="-1" class="page">
    <section class="hero card">
      <div class="card-head between">
        <div>
          <h2 class="card-title">View <?= htmlspecialchars($majorName) ?>  Requirements</h2>
        </div>
      </div>
    </section>
  </main>

    <section>
      <div class="hero card">
        <table id="majorRequirementsTable" cellpadding="10" cellspacing="50">
          <thead><tr><th scope="col">MajorID</th><th scope="col">MajorName</th><th scope="col">CourseID</th><th scope="col">CourseName</th><th scope="col">Description</th><th scope="col">Course Type</th><th scope="col">Credits</th></tr></thead>
            <tbody id="majorRequirementsBody">
              <?php if (!empty($major_requirement)): ?>
                <?php foreach ($major_requirement as $mr): ?>
                  <tr>
                    <td><?= htmlspecialchars($mr['MajorID']) ?> </td>
                    <td><?= htmlspecialchars($mr['MajorName']) ?></td>
                    <td><?= htmlspecialchars($mr['CourseID']) ?></td>
                    <td><?= htmlspecialchars($mr['CourseName']) ?></td>
                    <td><?= htmlspecialchars($mr['Course_Desc']) ?></td>
                    <td><?= htmlspecialchars($mr['RequirementType']) ?></td>
                    <td><?= htmlspecialchars($mr['Credits']) ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr><td colspan="6">No Requirements found.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
      </div>
    </section>
<?php require __DIR__ . '/partials/footer.php'; ?>
</body>
  <script>
    // Immediately create Lucide icons
    lucide.createIcons();

    // Populate the year in the footer
    document.getElementById('year').textContent = new Date().getFullYear();

</script>
</html>
