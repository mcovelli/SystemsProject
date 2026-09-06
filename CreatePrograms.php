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
    $programName = $_POST['program_name'] ?? '';
    $degreeType = $_POST['degree_type'] ?? '';
    $deptID = $_POST['deptID'] ?? NULL;
    $creditsRequired = $_POST['req_cred_num'] ?? 30;
    $status = $_POST['prog_stat'] ?? 'ACTIVE';

    $prefix = strtoupper(substr($programName, 0, 3));

    $programCode = strtoupper($degreeType . $prefix);

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
        
?>


<!DOCTYPE html>
<html lang="en">
<?php $nu_title = 'Create Programs'; require __DIR__ . '/partials/head.php'; ?>
<body>
  <?php $nu_page = 'Create Programs'; $nu_crumb = ['createDirectory.php', '← Back to Directory']; $nu_bell = false; require __DIR__ . '/partials/header.php'; ?>

    <main>

        <h3>Create Program</h3>
        
        <section>
          
          <!-- CREATE Program FORM -->
          <div id = "create-program">
          <form id="CreateProgram" method="POST" action="">

            <label for="program_name">Program Name:</label>
            <input type = "text" id="program_name" name="program_name" required><br>

            <label for="degree_type">Degree Level:</label>
            <select id="degree_type" name="degree_type" required>
              <option value="">-- Select Degree Level --</option>
            </select><br>

            <label for="dept">Department: </label>
              <select name="deptID" id="deptID">
                <option value="">-- All Departments --</option>
              </select><br>

            <label for="req_cred_num">Required Credits:</label>
            <input type="number" id="req_cred_num" name="req_cred_num" required><br>

            <label for="status">Program Status:</label>
            <input type="text" id="prog_stat" name="prog_stat" required><br>

            <button type="submit" id = "submit">Create Program</button>
         </form>
      </div>
</body>
</main>

<?php require __DIR__ . '/partials/footer.php'; ?>


<script>
      // Immediately create Lucide icons
    lucide.createIcons();

    // Populate the year in the footer
    document.getElementById('year').textContent = new Date().getFullYear();

    // get grade program type
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

    // Fetch departments from get_departments.php
    fetch('get_departments.php')
    .then(response => response.json())
    .then(data => {
        const deptSelect = document.getElementById('deptID');
        const selectedDept = new URLSearchParams(window.location.search).get('deptID');

    data.forEach(name => {
        const opt = document.createElement('option');
        opt.value = name.id;
        opt.textContent = name.name;
        if (name === selectedDept) opt.selected = true;
        deptSelect.appendChild(opt);
        });
    })
    .catch(err => console.error('Error loading departments:', err));

    document.getElementById("CreateProgram").addEventListener("submit", (e) => {
      console.log("Program form submitted ✅");
    });
</script>