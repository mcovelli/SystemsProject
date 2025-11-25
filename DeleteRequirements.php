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
    $majorID = $_POST['majorID'] ?? '';
    $majorCourseID = $_POST['major_courseID'] ?? ''; 
    $minorID = $_POST['minorID'] ?? '';
    $minorCourseID = $_POST['minor_courseID'] ?? '';
    $programID = $_POST['programID'] ?? '';
    $programCourseID = $_POST['program_courseID'] ?? '';

    $mysqli -> begin_transaction();
    $createReqAction = $_POST['create_req_action'] ?? '';

    switch ($createReqAction){
    case "DeleteMajorRequirement":
    if ($stmt -> num_rows > 0 ){
    $sql = "DELETE FROM MajorRequirement
            WHERE MajorID = ? AND CourseID = (SELECT CourseID FROM Courses WHERE CourseID = ?)";
       $stmt = $mysqli->prepare($sql);
        $stmt->bind_param(
            "is",
            $majorID, $majorCourseID
        );
        if ($stmt->execute()) {
            echo "alert('Major requirement deleted ✅');";
        } else {
            echo "alert('Could not delete Major Requirement');";
        }
    }
    break;

    case "DeleteMinorRequirement":
    if ($stmt -> num_rows > 0 ){
    $sql = "DELETE FROM MinorRequirement
            WHERE MinorID = ? AND CourseID = (SELECT CourseID FROM Courses WHERE CourseID = ?)";
       $stmt = $mysqli->prepare($sql);
        $stmt->bind_param(
            "is",
            $minorID, $minorCourseID
        );
        if ($stmt->execute()) {
            echo "alert('Minor requirement deleted ✅');";
        } else {
            echo "alert('Could not delete Minor Requirement');";
        }
    }
    break;

    case "DeleteProgramRequirement":
        if ($stmt -> num_rows > 0 ){
        $sql = "DELETE FROM ProgramRequirement
                WHERE ProgramID = ? AND CourseID = (SELECT CourseID FROM Courses WHERE CourseID = ?)";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param(
            "is",
            $programID, $programCourseID
        );
        if ($stmt->execute()) {
            echo "alert('Program requirement deleted ✅');";
        } else {
            echo "alert('Could not delete Program Requirement');";
        }
    }
    break;
    }
    $mysqli->commit();
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
<title>Create Requirements</title>
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
      <span class="pill">Create Requirements</span>
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
              <h2 class="card-title">Create Major/Minor</h2>
            </div>
          </div>
       </section>

<div class="top-actions">
          <a href="javascript:history.back()" title="Back to Dashboard">← Back to Dashboard</a>
        </div>

       <h3>Create Requirements</h3>

    <label for="requirementSelection">Select Type of Requirement to Create:</label>
    <select id="requirementSelection" name="requirementSelection" required>
            <option value="">-- Select --</option>
            <option value="majorRequirement">Major Requirement</option>
            <option value="minorRequirement">Minor Requirement</option>
            <option value="programRequirement">Program Requirement</option>
    </select><br>

<section id = "major-req-menu" class = "hero card hidden">
 <div id = "major-req-form">
            <form id = "MajorReqForm" method = "POST" action = "">
            <label for ="majorID">Major ID:</label>
             <select id="majorID" name="majorID">
                <option value="">-- Select Major ID --</option>
            </select><br>
            <label for = "major_courseID">Course ID:</label>
            <input type = "text" id="major_courseID" name="major_courseID" required><br>

            <button type="submit" name = "major_req_action" value ="delete">Delete Major Requirement</button>
            </form>
        </div>
</section>

<section id = "minor-req-menu" class = "hero card hidden">
        <div id = "minor-req-form">
            <form id = "MinorReqForm" method = "POST" action = "">
            <label for ="minorID">Minor ID:</label>
            <select id="minorID" name="minorID">
                <option value="">-- Select Minor ID --</option>
            </select><br>
            <label for = "minor_courseID">Course ID:</label>
            <input type = "text" id="minor_courseID" name="minor_courseID" required><br>
            <label for ="minor_req_description">Requirement Description:</label>
            <button type="submit" name = "minor_req_action" value ="delete">Delete Minor Requirement</button>
            </form>
        </div>
</section>


<section id = "program-req-menu" class = "hero card hidden">
<div id = "program-requirement">
            <form id = "programRequirementForm" method = "POST" action = "">
            <label for="programID">Program Requirement ID:</label>
            <select id="programID" name="programID">
                <option value="">-- Select Program ID --</option>
            </select><br>

            <label for="program_courseID">Program Course ID:</label>
            <select id="program_courseID" name="program_courseID">
                <option value="">-- Select Program Course ID --</option>
            </select><br>


            <button type="submit" name = "program_action" value ="delete">Delete Program Requirements</button>
             </form>
          </div>
</section>
</body>
</main>

 <footer>© 2025 Northport University • All rights reserved</footer>

 <script  script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js">
     lucide.createIcons();

    requirementSelection.addEventListener("change", function() => {
        const value = this.value;

        if (value === "majorRequirement"){
            major-req-menu.style.display = "block";
            minor-req-menu.style.display = "none";
            program-req-menu.style.display = "none";
            fetch('get_majorrequirements.php')
            .then(response => response.json())
            .then(data =>){
               const majorRequirementSelect = document.getElementById('majorID');
              const selectedMajorRequirement = new URLSearchParams(window.location.search).get('majorID');
              
              data.forEach(major => {
                const opt = document.createElement('option');
                opt.value = major.majorid;
                opt.textContent = major.majorid;
                if (major.majorid === selectedMajorRequirement) opt.selected = true;
                majorRequirementSelect.appendChild(opt);
              })
               .catch(err => console.error('Error loading departments:', err));

                document.getElementById("MajorReqForm").addEventListener("submit", (e) => {
                console.log("Form submitted");
            })
           }
        } else if (value === "minorRequirement"){
        major-req-menu.style.display = "none";
           minor-req-menu.style.display = "block";
           program-req-menu.style.display = "none";
           fetch('get_minorrequirements.php')
            .then(response => response.json())
            .then(data =>){
               const minorRequirementSelect = document.getElementById('minorID');
              const selectedMinorRequirement = new URLSearchParams(window.location.search).get('minorID');

              data.forEach(minor => {
                const opt = document.createElement('option');
                opt.value = minor.minorid;
                opt.textContent = minor.minorid;
                if (minor.minorid === selectedMinorRequirement) opt.selected = true;
                minorRequirementSelect.appendChild(opt);
              })
               .catch(err => console.error('Error loading departments:', err));

                document.getElementById("MinorReqForm").addEventListener("submit", (e) => {
                console.log("Form submitted");
            })
           }
        } else if (value === "programRequirement"){
           major-req-menu.style.display = "none";
           minor-req-menu.style.display = "none";
           program-req-menu.style.display = "block";
            fetch('get_programrequirements.php')
            .then(response => response.json())
            .then(data =>){
               const programRequirementSelect = document.getElementById('programID');
              const selectedProgramRequirement = new URLSearchParams(window.location.search).get('programID');

              data.forEach(program => {
                const opt = document.createElement('option');
                opt.value = program.programid;
                opt.textContent = program.programid;
                if (program.programid === selectedProgramRequirement) opt.selected = true;
                programRequirementSelect.appendChild(opt);
              })
               .catch(err => console.error('Error loading departments:', err));

                document.getElementById("ProgramReqForm").addEventListener("submit", (e) => {
                console.log("Form submitted");
            })
           }
        } else{
            major-req-menu.style.display = "none";
            minor-req-menu.style.display = "none";
            program-req-menu.style.display = "none";
        }
    }
    );

</script>