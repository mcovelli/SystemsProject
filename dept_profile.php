<?php
session_start();
require_once __DIR__ . '/config.php';
ini_set('display_errors', 1);
error_reporting(E_ALL);



$mysqli = get_db();
$mysqli->set_charset('utf8mb4');

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

$deptId = isset($_GET['deptID']) ? intval($_GET['deptID']) : 0;
if ($deptId <= 0) {
    die("Invalid Department ID");
}


$sql = "
    SELECT 
        d.DeptID,
        d.DeptName,
        d.Email,
        d.Phone,
        d.RoomID,
        d.ChairID,
        CONCAT(u.FirstName, ' ', u.LastName) AS ChairName
    FROM Department d
    JOIN Users u ON d.ChairID = u.UserID
    WHERE d.DeptID = ?
    LIMIT 1
";

$stmt = $mysqli->prepare($sql);
$stmt->bind_param("i", $deptId);
$stmt->execute();
$dept = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$dept) {
    die("Department not found.");
}

$deptName = $dept['DeptName'];

?>


<!DOCTYPE html>
<html lang="en">
<?php $nu_title = 'Department Profile'; require __DIR__ . '/partials/head.php'; ?>
<body>
  <?php $nu_page = 'Department Profile'; $nu_crumb = ['viewDirectory.php', '← Back to Directory']; require __DIR__ . '/partials/header.php'; ?>

  <main class="page">
    <section class="hero card">
      <div class="card-head between">
        <div>
          <h2 class="card-title"><?= htmlspecialchars($deptName) ?> Profile</h2>
        </div>
      </div>
  </section>

  <section>
      <p><strong>Chair: </strong><?php echo htmlspecialchars($dept['ChairName']) ?> </p>
      <p><strong>Office: </strong><?php echo htmlspecialchars($dept['RoomID']) ?> </p>
      <p><strong>Email: </strong><a href="mailto:<?php echo htmlspecialchars($dept['Email']) ?> "><?php echo htmlspecialchars($dept['Email']) ?> </a></p>
      <p><strong>Phone: </strong><?php echo htmlspecialchars($dept['Phone']) ?> </p>



  
  </section>
  <br>
  </main>
  <?php require __DIR__ . '/partials/footer.php'; ?>

  <script>
     // Immediately create Lucide icons
    lucide.createIcons();

    // Populate the year in the footer
    document.getElementById('year').textContent = new Date().getFullYear();

    document.getElementById('year').textContent = new Date().getFullYear();
    </script>
  </body>
</html>
