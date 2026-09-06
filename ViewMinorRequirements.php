<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['user_id'])) {
    redirect(PROJECT_ROOT . "/login.html");
}

$userId = $_SESSION['user_id'];
$minorID = $_GET['minorID'] ?? null;
$minorName = $_GET['MinorName'] ?? 'Minor';
if (!$minorID) die("No minorID provided.");

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

$minor_requirement_sql = "SELECT mnr.MinorID, mn.MinorName, mnr.CourseID, mnr.RequirementType, c.Course_Desc, c.CourseName, c.Credits
                       FROM MinorRequirement mnr
                       JOIN Minor mn ON mnr.MinorID = mn.MinorID
                       JOIN Course c ON mnr.CourseID = c.CourseID
                       WHERE mnr.MinorID = ?";

$minor_requirement_stmt = $mysqli->prepare($minor_requirement_sql);
$minor_requirement_stmt->bind_param("i", $minorID);
$minor_requirement_stmt->execute();
$minor_requirement_res = $minor_requirement_stmt->get_result();
$minor_requirement = $minor_requirement_res->fetch_all(MYSQLI_ASSOC);
$minor_requirement_stmt->close();


?>


<!DOCTYPE html>
<html lang="en">
<?php $nu_title = 'Minor Requirements'; require __DIR__ . '/partials/head.php'; ?>
<body>
  <?php $nu_page = 'Minor Requirements'; $nu_crumb = ['viewDirectory.php', '← Back to Directory']; require __DIR__ . '/partials/header.php'; ?>

  <main id="main" tabindex="-1" class="page">
    <section class="hero card">
      <div class="card-head between">
        <div>
          <h2 class="card-title">View <?= htmlspecialchars($minorName) ?> Requirements</h2>
        </div>
      </div>
    </section>
  </main>

    <section>
      <div class="hero card">
        <table id="majorRequirementsTable" cellpadding="10" cellspacing="50">
                    <thead><tr><th scope="col">MinorID</th><th scope="col">MinorName</th><th scope="col">CourseID</th><th scope="col">CourseName</th><th scope="col">Description</th><th scope="col">Course Type</th><th scope="col">Credits</th></tr></thead>
              <?php if (!empty($minor_requirement)): ?>
                <?php foreach ($minor_requirement as $mnr): ?>
                  <tr>
                    <td><?= htmlspecialchars($mnr['MinorID']) ?> </td>
                    <td><?= htmlspecialchars($mnr['MinorName']) ?></td>
                    <td><?= htmlspecialchars($mnr['CourseID']) ?></td>
                    <td><?= htmlspecialchars($mnr['CourseName']) ?></td>
                    <td><?= htmlspecialchars($mnr['Course_Desc']) ?></td>
                    <td><?= htmlspecialchars($mnr['RequirementType']) ?></td>
                    <td><?= htmlspecialchars($mnr['Credits']) ?></td>
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
