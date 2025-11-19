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
    $programID = $_POST['programID'] ?? '';
    $programCode = $_POST['program_code'] ?? '';
    $programName = $_POST['program_name'] ?? '';
    $degreeType = $_POST['degree_type'] ?? '';
    $deptID = $_POST['deptID'] ?? '';
    $creditsRequired = $_POST['req_cred_num'] ?? '';
    $status = $_POST['prog_stat'] ?? '';

    $mysqli->begin_transaction();

   
       $sql= "INSERT INTO Program
            (ProgramID, ProgramCode, ProgramName, DegreeLevel, DeptID, CreditsRequired, Status)
            VALUES (?, ?, ?, ?, NULL, 30, 'ACTIVE')";
          $stmt = $mysqli->prepare($sql);
          $stmt->bind_param(
            "isssiis",
            $programID, $programCode, $programName, $degreeType, $deptID, $creditsRequired, $status
        );

        if (stmt -> execute()) {
            echo "alert('Program.$programName. created ✅');";
        } else {
            echo "alert('Could not create Program');";
        }
        break;
      }
        /*case 'updateProgram':
          if ($programID && $programCode && $deptID){
        $sql= "UPDATE Program SET
            ProgramCode = ?, ProgramName = ?, DegreeLevel = ?, DeptID = ?, CreditsRequired = ?, Status = ?
            WHERE ProgramID = ? AND ProgramCode = ? AND DeptID = ?";
          }
          $stmt = $mysqli->prepare($sql);
          $stmt->bind_param(
            "sssisii",
            $programCode, $programName, $degreeType, $deptID, $creditsRequired, $status, $programID
        );
        break;
        
        case 'deleteProgram':
          if ($programID && $programCode && $deptID){
        $sql= "DELETE FROM Program WHERE ProgramID = ? AND ProgramCode = ? AND DeptID = ?"
        }
        $stmt = $mysqli->prepare($sql);
        $stmt -> bind_param(
          "isi",
          $programID, $programCode, $deptID
        );
      }
        break;
    }

    switch ($programReqAction) {
      case 'createProgramReq':
        $sql = "INSERT INTO ProgramRequirement (ProgramID, CourseID, RequirementType, Notes)
        VALUES (?, ?, ?, ?)";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param(
            "isss",
            $programID, $courseID, $requirementType, $notes
        );
    break;

    case 'updateProgramReq':
      if ($programID && $courseID){
      $sql = "UPDATE ProgramRequirement SET RequirementType = ?, Notes = ?
      WHERE ProgramID = ? AND CourseID = ?";
    }
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param(
        "sssi",
        $requirementType, $notes, $programID, $courseID
    );
    break;

    case 'deleteProgramReq':
      if ($programID && $courseID){
        $sql = "DELETE FROM ProgramRequirement WHERE ProgramID = ? AND CourseID = ?";
      }
      $stmt = $mysqli->prepare($sql);
      $stmt->bind_param(
          "is",
          $programID, $courseID
      );
      break;
    }*/
?>

<body>
    <header>
      <h1>🎓 Northport University Admin</h1>
      <input type="text" placeholder="Search courses, people, etc..." />

      <form action="logout.php" method="post" style="display:inline;">
        <button type="submit" class="logout-btn">Logout</button>
      </form>
    </header>

    <main>

        <h3>Create Course</h3>

        <div class="top-actions">
          <a href="javascript:history.back()" title="Back to Dashboard">← Back to Dashboard</a>
        </div>
        
        <section>
          
          <!-- CREATE Program FORM -->
          <div id = "create-program">
          <form id="CreateProgram" method="POST" action="">
            <label for="programID">Program ID:</label>
            <select id="programID" name="programID">
                <option value="">-- Select Program ID --</option>
            </select><br>

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
            <input type="int" id="deptID" name="deptID" required><br>

            <label for="req_cred_num">Required Credits:</label>
            <input type="int" id="req_cred_num" name="req_cred_num" required><br>

            <label for="status">Program Status:</label>
            <input type="text" id="prog_stat" name="prog_stat" required><br>

            <button type="submit" id = "submit">Create Program</button>
         </form>
      </div>

      <div id = "program-requirement">
            <form id = "programRequirementMenu">
            <label for="reqID">Program Requirement ID:</label>
            <input type = "int" select id="reqID" name="reqID" required><br>

            <label for="courseID">Program Course ID:</label>
            <input type = "int" select id="courseID" name="courseID" required><br>

            <label for="req_type">Requirement Type:</label>
            <select id="req_type" name="req_type" required>
              <option value="">-- Select Requirement Type --</option>
              <option value="core">Core</option>
              <option value="elective">Elective</option>
              <option value="capstone">Capstone</option>
            </select><br>

            <label for="notes">Program Course Notes:</label>
            <input type = "text" select id="notes" name="notes" required><br>

            <button type="submit" name = "program_action" value ="create">Create Program Requirements</button>
            <button type="submit" name = "program_action" value ="update">Update Program Requirements</button>
            <button type="submit" name = "program_action" value ="delete">Delete Program Requirements</button>
             </form>
          </div>
</body>
</main>

 <footer>© 2025 Northport University • All rights reserved</footer>

 <script>
    fetch('get_programs.php')
    .then(response => response.json())
    .then(data => {
      const programSelect = document.getElementById('programID');
      const selectedProgram = new URLSearchParams(window.location.search).get('programID');

      data.forEach(prog =>{
        const opt = document.createElement('option');
        opt.value = prog.ProgramID;
        opt.textContent = prog.ProgramName;
        if (prog.ProgramID === selectedProgram) opt.selected = true;
        programSelect.appendChild(opt);
      });
    })
    .catch(err => console.error('Error loading programs:', err));
    document.getElementById("CreateProgram").addEventListener("submit", (e) => {
      console.log("Program form submitted ✅");
    });
</script>