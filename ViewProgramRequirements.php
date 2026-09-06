<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['user_id'])) {
    redirect(PROJECT_ROOT . "/login.html");
}

$userId = $_SESSION['user_id'];
$programID = $_GET['ProgramID'] ?? null;
$programName = $_GET['ProgramName'] ?? 'Program';
if (!$programID) die("No ProgramID provided.");

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

$program_requirement_sql = "SELECT pr.ProgramID, p.ProgramName, pr.CourseID, pr.RequirementType, p.CreditsRequired
                       FROM ProgramRequirement pr
                       JOIN Program p ON pr.ProgramID = p.ProgramID
                       WHERE pr.ProgramID = ?";

$program_requirement_stmt = $mysqli->prepare($program_requirement_sql);
$program_requirement_stmt->bind_param("i", $programID);
$program_requirement_stmt->execute();
$program_requirement_res = $program_requirement_stmt->get_result();
$program_requirement = $program_requirement_res->fetch_all(MYSQLI_ASSOC);
$program_requirement_stmt->close();



?>


<!DOCTYPE html>
<html lang="en">
<?php $nu_title = 'Program Requirements'; require __DIR__ . '/partials/head.php'; ?>
<body>
  <?php $nu_page = 'Program Requirements'; $nu_crumb = ['viewDirectory.php', '← Back to Directory']; require __DIR__ . '/partials/header.php'; ?>
  <main id="main" tabindex="-1" class="page">
    <section class="hero card">
      <div class="card-head between">
        <div>
          <h2 class="card-title">View <?= htmlspecialchars($programName) ?> Requirements</h2>
        </div>
      </div>
    </section>
  </main>

    <section>
      <div class="hero card">
        <table id="majorRequirementsTable" cellpadding="10" cellspacing="50">
          <thead><tr><th scope="col">ProgramID</th><th scope="col">ProgramName</th><th scope="col">CourseID</th><th scope="col">Type</th><th scope="col">Credits Required</th></tr></thead>
            <tbody id="majorRequirementsBody">
              <?php if (!empty($program_requirement)): ?>
                <?php foreach ($program_requirement as $pr): ?>
                  <tr>
                    <td><?= htmlspecialchars($pr['ProgramID']) ?> </td>
                    <td><?= htmlspecialchars($pr['ProgramName']) ?></td>
                    <td><?= htmlspecialchars($pr['CourseID']) ?></td>
                    <td><?= htmlspecialchars($pr['RequirementType']) ?></td>
                    <td><?= htmlspecialchars($pr['CreditsRequired']) ?></td>
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
