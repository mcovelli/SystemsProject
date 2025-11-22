<?php
session_start();
require_once __DIR__ . '/config.php';
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['user_id']) || 
  ($_SESSION['role'] ?? '') !== 'admin' ||
($_SESSION['admin_type'] ?? '') !== 'update') {
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $programID = $_POST['programID'] ?? '';
    $programCode = $_POST['program_code'] ?? '';
    $programName = $_POST['program_name'] ?? '';
    $degreeType = $_POST['degree_type'] ?? '';
    $deptID = $_POST['deptID'] ?? NULL;
    $creditsRequired = $_POST['req_cred_num'] ?? 30;
    $status = $_POST['prog_stat'] ?? 'ACTIVE';

    $mysqli->begin_transaction();

   
       $sql= "INSERT INTO Program
            (ProgramCode, ProgramName, DegreeLevel, DeptID, CreditsRequired, Status)
            VALUES (?, ?, ?, ?, ?, ?)";

          $stmt = $mysqli->prepare($sql);
          $stmt->bind_param(
            "sssiis",
            $programCode, $programName, $degreeType, $deptID, $creditsRequired, $status
        );

        if ($stmt -> execute()) {
            $mysqli->commit();
            echo "<script>alert('Program $programName created ✅');</script>";
        } else {
            $mysqli->rollback();
            echo "<script>alert('Could not create Program');</script>";
        }
      }
        
$initials = substr($user['FirstName'], 0, 1) . substr($user['LastName'], 0, 1);
?>


<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Create Programs</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="./styles.css" />
</head>
<body>
  <header class="topbar">
    <div class="brand">
      <div class="logo"><i data-lucide="graduation-cap"></i></div>
      <h1>Northport University</h1>
      <span class="pill">Create Programs</span>
    </div>
    <div class="top-actions">
      <div class="search">
        <i class="search-icon" data-lucide="search"></i>
        <input type="text" placeholder="Search courses, people, anything…" />
      </div>
      <button class="icon-btn" aria-label="Notifications"><i data-lucide="bell"></i></button>
      <button id="themeToggle" class="icon-btn" aria-label="Toggle theme"><i data-lucide="moon"></i></button>
      <div class="divider"></div>
      <div class="crumb"><a href="createDirectory.php" aria-label="Back to Directory">← Back to Directory</a></div>
    </div>

    <div class="avatar" aria-hidden="true"><span id="initials"><?php echo $initials ?: 'NU'; ?></span></div>
        <div class="user-meta"><div class="name"><?php echo htmlspecialchars($user['UserType']) ?></div></div>
        <div class="dropdown">
          <button>☰ Menu</button>
          <div class="dropdown-content">
            <a href="<?= htmlspecialchars($dashboard) ?>">Dashboard</a>
            <a href="<?= htmlspecialchars($profile) ?>">Profile</a>
            <a href="logout.php">Logout</a>
          </div>
        </div>
      </div>
    </div>
  </header>

    <main>

        <h3>Create</h3>

        <div class="top-actions">
          <a href="javascript:history.back()" title="Back to Dashboard">← Back to Dashboard</a>
        </div>
        
        <section>
          
          <!-- CREATE Program FORM -->
          <div id = "create-program">
          <form id="CreateProgram" method="POST" action="">
            <label for="program_id">Program ID:</label>
            <input type = "text" select id="program_id" name="program_id" required><br>

            <label for="program_code">Program Code:</label>
            <input type = "text" select id="program_code" name="program_code" required><br>

            <label for="program_name">Program Name:</label>
            <input type = "text" select id="program_name" name="program_name" required><br>

            <label for="degree_type">Degree Level:</label>
            <select id="degree_type" name="degree_type" required>
              <option value="">-- Select Degree Level --</option>
              <option value="phd">Ph.D.</option>
              <option value="ma">Master of Arts</option>
              <option value="ms">Master of Sciences</option>
            </select><br>

            <label for="deptID">Department ID:</label>
            <input type="number" id="deptID" name="deptID" required><br>

            <label for="req_cred_num">Required Credits:</label>
            <input type="number" id="req_cred_num" name="req_cred_num" required><br>

            <label for="status">Program Status:</label>
            <input type="text" id="prog_stat" name="prog_stat" required><br>

            <button type="submit" id = "submit">Create Program</button>
         </form>
      </div>
</body>
</main>

 <footer>© 2025 Northport University • All rights reserved</footer>

 <script>
    fetch('get_grad_degree_level.php')
    .then(response => response.json())
    .then(data => {
      const programSelect = document.getElementById('degree_type');
      const selectedProgram = new URLSearchParams(window.location.search).get('degree_type');

      data.forEach(prog =>{
        const opt = document.createElement('option');
        opt.value = prog.degreelevel;
        opt.textContent = prog.degreelevel;
        if (prog.degreelevel === selectedProgram) opt.selected = true;
        programSelect.appendChild(opt);
      });
    })
    .catch(err => console.error('Error loading programs:', err));
    document.getElementById("CreateProgram").addEventListener("submit", (e) => {
      console.log("Program form submitted ✅");
    });
</script>