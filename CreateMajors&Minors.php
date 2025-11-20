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

$mysqli = get_db();
$mysqli->set_charset('utf8mb4');

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
        $sql = "INSERT INTO Major (DeptID, MajorName, CreditsNeeded) VALUES ((SELECT DeptID FROM Department WHERE DeptName = ?), ?, ?)";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param("ssi", $DeptId, $majorName, $majorCreditsNeeded);
        
        if ($stmt->execute()) {
            echo "alert('$majorName. created ✅');";
        } else {
            echo "alert('Could not create Major');";
        }
    break;

    case("minor"):
        $sql = "INSERT INTO Minor (DeptID, MinorName, CreditsNeeded) VALUES ((SELECT DeptID FROM Department WHERE DeptName = ?), ?, ?)";
            $stmt = $mysqli->prepare($sql);
            $stmt->bind_param("ssi", $DeptId, $minorName, $minorCreditsNeeded);

            if ($stmt->execute()) {
                $mysqli->commit();
                echo "alert('Minor.$minorName. created ✅');";
            } else {
                $mysqli->rollback();
                echo "alert('Could not create Minor');";
            }
        break;
}

/* //All of this should move into their own php documents. Theres a lot going on in this page. We should keep it to just creating a major or a minor.

    case 'updateMajor':
      if ($majorID && $majorName && $majorDeptId){
      $sql = "UPDATE Major SET CreditsNeeded = ? WHERE MajorID = ? AND DeptID = ? AND MajorName = ?";
      }
      $stmt = $mysqli->prepare($sql);
        $stmt->bind_param(
            "iiis",
           $majorCreditsNeeded, $majorID, $majorDeptId, $majorName
        );
        if ($stmt->execute()) {
            echo "alert('Major.$majorName. updated ✅');";
        } else {
            echo "alert('Could not update Major');";
        }
    break;
    
    case 'deleteMajor':
      if ($majorID && $majorName && $majorDeptId){
      $sql = "DELETE FROM Major WHERE MajorID = ?";
      }
      $stmt = $mysqli->prepare($sql);
      $stmt->bind_param("i",$majorID);
        if ($stmt->execute()) {
            echo "alert('Major.$majorName. deleted ✅');";

        } else {
            echo "alert('Could not delete Major');";
        }
    break;
}

    switch ($minorAction){
      $sql = "INSERT INTO Minor (DeptID, MinorName, CreditsNeeded) VALUES(SELECT MinorName FROM Minor WHERE MinorID = ? LIMIT 1)";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param("isi", $minorDeptId, $minorName, $minorCreditsNeeded);
        //$stmt->excecute();

        if ($stmt->execute()) {
            echo "alert('Minor.$minorName. created ✅');";
        } else {
            echo "alert('Could not create Minor');";
        }
    break;

    case 'updateMinor':
      if ($minorID && $minorName && $minorDeptId){
      $sql = "UPDATE Minor SET CreditsNeeded = ? WHERE MinorID = ?";
      }
      $stmt = $mysqli->prepare($sql);
        $stmt->bind_param(
            "iiis",
           $minorCreditsNeeded, $minorID, $minorDeptId, $minorName
        );
        if ($stmt->execute()) {
            echo "alert('Minor.$minorName. updated ✅');";
        } else {
            echo "alert('Could not update Minor');";
        }

    break;
    
    case 'deleteMinor':
      if ($minorID && $minorName && $minorDeptId){
      $sql = "DELETE FROM Minor WHERE MinorID = ?";
      }
      $stmt = $mysqli->prepare($sql);
      $stmt->bind_param(
            "i",
            $minorID
        );
        if ($stmt->execute()) {
            echo "alert('Minor.$minorName. deleted ✅');";
        } else {
            echo "alert('Could not delete Minor');";
        }
    break;
}
    switch ($minorReqAction){
    case "CreateMinorRequirement":
    $sql = "INSERT INTO MinorRequirement
            (MinorID, CourseID, RequirementDescription, RequirementType, CreditsRequired, SemesterLevel)
             VALUES (?, ?, ?, NULL, 3, NULL)";
       $stmt = $mysqli->prepare($sql);
        $stmt->bind_param(
            "isssii",
            $minorID, $minorCourseID, $minorRequirementDescription, $minorRequirementType, $minorCreditsRequired, $minorSemesterLevel
        );
    break;
    case "UpdateMinorRequirement":
        if ($minorID && $minorCourseID){
            $sql = "UPDATE MinorRequirement SET RequirementDescription = ?, RequirementType = ?,
            CreditsRequired = ?, SemesterLevel = ? WHERE MinorID = ? AND CourseID = ?";
        }
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param(
            "ssiiis",
            $minorRequirementDescription, $minorRequirementType, $minorCreditsRequired, $minorSemesterLevel, $minorID, $minorCourseID
        );
        break;
    case "DeleteMinorRequirement":
        if ($minorID && $minorCourseID){
            $sql = "DELETE FROM MinorRequirement WHERE MinorID = ? AND CourseID = ?";
        }
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param(
            "is",
            $minorID, $minorCourseID
        );
        break;
    }
*/
?>
<!doctype html>
<html lang="en" data-theme="light">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Northport University — Create Major and Minor</title>
  <style>
    .hidden {
      display: none;
    }
  </style>
  <link rel="stylesheet" href="./viewstyles.css" />
</head>
  <body>
    <header class="topbar">
    <div class="brand">
      <div class="logo">NU</div>
      <h1>Northport University</h1>
    </div>
    <div class="top-actions">
      <div class="search" style="width: min(360px, 40vw)">
        <i class="search-icon" data-lucide="search"></i>
        <input id="q" type="text" placeholder="Search code, title, instructor…" />
      </div>
      <button class="btn outline" id="themeToggle" title="Toggle theme">🌙</button>
      <div class="crumb"><a href="update_admin_dashboard.php" aria-label="Back to Dashboard">← Back to Dashboard</a></div>
    </div>
  </header>

    <main class="page">
        <section class="hero card">
            <div class="card-head between">
                <div>
                  <h1 class="card-title">Create Major/Minor</h1>
                </div>
            </div>
                <div id = "create-section-majorminor">
                    <form id = "CreateMajorMinor" method = "POST" action = "">
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
                        <label for ="major_name">Major Name: </label>
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

    // Fetch departments from get_departments.php
    fetch('get_departments.php')
    .then(response => response.json())
    .then(data => {
        const deptSelect = document.getElementById('deptID');
        const selectedDept = new URLSearchParams(window.location.search).get('deptID');

    data.forEach(name => {
        const opt = document.createElement('option');
        opt.value = name.name;
        opt.textContent = name.name;
        if (name === selectedDept) opt.selected = true;
        deptSelect.appendChild(opt);
        });
    })
    .catch(err => console.error('Error loading departments:', err));

    document.getElementById("CreateMajorMinor").addEventListener("submit", (e) => {
    console.log("Form submitted");
});
</script>
