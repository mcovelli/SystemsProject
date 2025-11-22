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
    $DeptId = $_POST['deptID'] ?? '';
    $majorName = $_POST['major_name'] ?? '';
    $majorCreditsNeeded = $_POST['major_credits_needed'] ?? '';
    $minorName = $_POST['minor_name'] ?? '';
    $minorCreditsNeeded = $_POST['minor_credits_needed'] ?? '';
}

$mysqli->begin_transaction();

$majorOrMinor = $_POST['majorOrMinor'] ?? '';

switch ($majorOrMinor){
    case 'major':
        $sql = "DELETE FROM Major WHERE DeptID = (SELECT DeptID FROM Department WHERE DeptName = ? ) AND MajorName = ?";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param("ss", $DeptId, $majorName);
        
        if ($stmt->execute()) {
            echo "alert('$majorName deleted ✅');";
        } else {
            echo "alert('Could not delete Major');";
        }
    break;

    case("minor"):
        $sql = "DELETE FROM Minor WHERE DeptID = (SELECT DeptID FROM Department WHERE DeptName = ? ) AND MinorName = ?";
            $stmt = $mysqli->prepare($sql);
            $stmt->bind_param("ss", $DeptId, $minorName);

            if ($stmt->execute()) {
                $mysqli->commit();
                echo "alert('Minor $minorName deleted ✅');";
            } else {
                $mysqli->rollback();
                echo "alert('Could not delete Minor');";
            }
        break;
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
<title>Create Majors and Minors</title>
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
      <span class="pill">Delete Majors and Minors</span>
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

    <main class="page">
        <section class="hero card">
            <div class="card-head between">
                <div>
                  <h1 class="card-title">Delete Major/Minor</h1>
                </div>
            </div>
                <div id = "delete-section-majorminor">
                    <form id = "DeleteMajorMinor" method = "POST" action = "">
                        <label for="majorOrMinor">Select Major/Minor: </label>
                        <select id="majorOrMinor" name="majorOrMinor" required>
                            <option value="">-- Select --</option>
                            <option value="major">Major</option>
                            <option value="minor">Minor</option>
                        </select>
                        <br>
                        <label for="dept">Department: </label>
                             <select name="deptID" id="deptID">
                                <option value="">-- All Departments --</option>
                                </select><br>
                        <?php $type = $_POST['majorOrMinor'] ?? ''; ?>
                        <label id="typeLabel" for ="major_name"><?php echo htmlspecialchars($type) ?></label>
                        <?= $type === 'major' ? 'Major ' : ($type === 'minor' ? 'Minor ' : 'Name:') ?>
                            <input type = "text" id="major_name" name="major_name" required><br>
                        <label for = "major_credits_needed">Credits Needed:</label>
                            <input type = "number" id = "major_credits_needed" name = "major_credits_needed" required><br>

                        <button type="submit" id = "submit">Submit</button>
                    </form>
                </div>
        </section>
    </main>

<body>
</body>

<script>

    document.getElementById('majorOrMinor').addEventListener('change', function() {
    const type = this.value; // 'major' or 'minor'
    const label = document.getElementById('typeLabel');

        if (type === 'major') {
            label.textContent = "Major ";
        } else if (type === 'minor') {
            label.textContent = "Minor ";
        } else {
            label.textContent = "";
        }
    });

    // Fetch departments from get_departments.php

    document.getElementById("DeleteMajorMinor").addEventListener("submit", (e) => {
    console.log("Form submitted");
});
</script>
