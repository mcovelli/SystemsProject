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
    $majorCreditsRequired = $_POST['major_credits_required'] ?? 3;
    $majorRequirementDescription = $_POST['major_req_description'] ?? '';
    $majorRequirementType = $_POST['major_req_type'] ?? NULL;
    $majorSemesterLevel = $_POST['major_semester_level'] ?? NULL;
    $minorID = $_POST['minorID'] ?? '';
    $minorCourseID = $_POST['minor_courseID'] ?? '';
    $minorCreditsRequired = $_POST['minor_credits_required'] ?? 3;
    $minorRequirementDescription = $_POST['minor_req_description'] ?? '';
    $minorRequirementType = $_POST['minor_req_type'] ?? NULL;
    $minorSemesterLevel = $_POST['minor_semester_level'] ?? NULL;
    $requirementID = $_POST['requirementID'] ?? '';
    $programID = $_POST['programID'] ?? '';
    $programCourseID = $_POST['program_courseID'] ?? '';
    $requirementType = $_POST['req_type'] ?? '';
    $notes = $_POST['notes'] ?? '';

    $mysqli -> begin_transaction();
    $createReqAction = $_POST['create_req_action'] ?? '';

    switch ($createReqAction){
    case "CreateMajorRequirement":
    if ($stmt -> num_rows > 0 ){
    $sql = "UPDATE MajorRequirement
            SET MajorID = ?, CourseID = (SELECT CourseID FROM Courses WHERE CourseID = ?), RequirementDescription = ?, RequirementType = ?, CreditsRequired = ?, SemesterLevel = ?
            WHERE RequirementID = ?";
       $stmt = $mysqli->prepare($sql);
        $stmt->bind_param(
            "isssiii",
            $majorID, $majorCourseID, $majorRequirementDescription, $majorRequirementType, $majorCreditsRequired, $majorSemesterLevel, $requirementID
        );
        if ($stmt->execute()) {
            echo "alert('Major requirement created ✅');";
        } else {
            echo "alert('Could not create Major Requirement');";
        }
    }
    break;

    case "CreateMinorRequirement":
    if ($stmt -> num_rows > 0 ){
    $sql = "UPDATE MinorRequirement
            SET MinorID = ?, CourseID = (SELECT CourseID FROM Courses WHERE CourseID = ?), RequirementDescription = ?, RequirementType = ?, CreditsRequired = ?, SemesterLevel = ?
            WHERE RequirementID = ?";
       $stmt = $mysqli->prepare($sql);
        $stmt->bind_param(
            "isssiii",
            $minorID, $minorCourseID, $minorRequirementDescription, $minorRequirementType, $minorCreditsRequired, $minorSemesterLevel, $requirementID
        );
        if ($stmt->execute()) {
            echo "alert('Minor requirement created ✅');";
        } else {
            echo "alert('Could not create Minor Requirement');";
        }
    }
    break;

    case 'createProgramReq':
        if ($stmt -> num_rows > 0 ){
        $sql = "UPDATE ProgramRequirement
            SET ProgramID = ?, CourseID = (SELECT CourseID FROM Courses WHERE CourseID = ?), RequirementType = ?, Notes = ?
            WHERE RequirementID = ?";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param(
            "isssi",
            $programID, $programCourseID, $requirementType, $notes, $requirementID
        );
        if ($stmt->execute()) {
            echo "alert('Program requirement created ✅');";
        } else {
            echo "alert('Could not create Program Requirement');";
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
<title>Update Requirements</title>
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
      <span class="pill">Update Requirements</span>
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
              <h2 class="card-title">Update Requirements</h2>
            </div>
          </div>
       </section>

<div class="top-actions">
          <a href="javascript:history.back()" title="Back to Dashboard">← Back to Dashboard</a>
        </div>

       <h3>Update Requirements</h3>
    <label for="requirementSelection">Select Type of Requirement to Update:</label>
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
            <input type = "text" id="major_courseID" name="major_courseID"><br>
            <label for ="major_req_description">Requirement Description:</label>
            <input type = "text" id="major_req_description" name="major_req_description"><br>
            <label for="major_req_type">Requirement Type:</label>
            <select id="major_req_type" name="major_req_type" required>
              <option value="">-- Select Requirement Type --</option>
              <option value="core">Core</option>
              <option value="elective">Elective</option>
            </select><br>
            <label for = "major_credits_required">Credits Required:</label>
            <input type = "number" id = "major_credits_required" name = "major_credits_required"><br>
            <label for = "major_semester_level">Semester Level:</label>
            <input type = "number" id = "major_semester_level" name = "major_semester_level"><br>

            <button type="submit" name = "major_req_action" value ="update">Update Major Requirement</button>
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
            <input type = "text" id="minor_courseID" name="minor_courseID"><br>
            <label for ="minor_req_description">Requirement Description:</label>
            <input type = "text" id="minor_req_description" name="minor_req_description"><br>
            <label for="minor_req_type">Requirement Type:</label>
            <select id="minor_req_type" name="minor_req_type">
              <option value="">-- Select Requirement Type --</option>
              <option value="core">Core</option>
              <option value="elective">Elective</option>
            </select><br>
            <label for = "minor_credits_required">Credits Required:</label>
            <input type = "number" id = "minor_credits_required" name = "minor_credits_required"><br>
            <label for = "minor_semester_level">Semester Level:</label>
            <input type = "number" id = "minor_semester_level" name = "minor_semester_level"><br>

            <button type="submit" name = "minor_req_action" value ="update">Update Minor Requirement</button>
            </form>
        </div>
</section>

<section id = "program-req-menu" class = "hero card hidden">
<div id = "program-requirement">
            <form id = "programRequirementForm" method = "POST" action = "">
            <label for = "requirementID" hidden>Requirement ID:</label>
            <input type = "hidden" id = "requirementID" name="requirementID"><br>

            <label for="programID">Program Requirement ID:</label>
            <select id="programID" name="programID">
                <option value="">-- Select Program ID --</option>
            </select><br>

            <label for="program_courseID">Program Course ID:</label>
            <select id="program_courseID" name="program_courseID">
                <option value="">-- Select Program Course ID --</option>
            </select><br>

            <label for="req_type">Requirement Type:</label>
            <select id="req_type" name="req_type">
              <option value="">-- Select Requirement Type --</option>
              <option value="core">Core</option>
              <option value="elective">Elective</option>
              <option value="capstone">Capstone</option>
            </select><br>

            <label for="notes">Program Course Notes:</label>
            <input type = "text" select id="notes" name="notes"><br>

            <button type="submit" name = "program_action" value ="update">Update Program Requirements</button>
             </form>
          </div>
</section>
</body>
</main>

 <footer>© 2025 Northport University • All rights reserved</footer>

 <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>

<script>
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