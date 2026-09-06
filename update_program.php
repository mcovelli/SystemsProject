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
    $DegreeLevel = $_POST['degreeLevel'] ?? '';
    $DeptName = $_POST['dept'] ?? '';
    $CreditsRequired = $_POST['creditsRequired'] ?? 0;
    $Status = 'ACTIVE';

    $ProgramCode = $DegreeLevel . strtoupper(substr($DeptName, 0, 2));
    $ProgramName = $DegreeLevel . ' in ' . $DeptName;

    // Convert DeptName -> DeptID
    $sql = "SELECT DeptID FROM Department WHERE DeptName = ?";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("s", $DeptName);
    $stmt->execute();
    $dept = $stmt->get_result()->fetch_assoc();
    $DeptID = $dept['DeptID'];

    $mysqli->begin_transaction();

    $sql = "INSERT INTO Program(ProgramCode, ProgramName, DegreeLevel, DeptID, CreditsRequired, Status) VALUES (?,?,?,?,?,?)";

    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("sssiis", $ProgramCode, $ProgramName, $DegreeLevel, $DeptID, $CreditsRequired, $Status);

    if ($stmt->execute()) {
        $mysqli->commit();
        echo "<script>alert('Program created successfully');</script>";
    } else {
        $mysqli->rollback();
        echo "<script>alert('Error creating program');</script>";
    }
    $mysqli->commit();
}

?>

<!doctype html>
<html lang="en">
<?php $nu_title = 'Update Graduate Program'; require __DIR__ . '/partials/head.php'; ?>
  <body>
    <?php $nu_crumb = ['update_admin_dashboard.php', '← Back to Dashboard']; $nu_bell = false; require __DIR__ . '/partials/header.php'; ?>

    <main id="main" tabindex="-1" class="page">
        <section class="hero card">
            <div class="card-head between">
                <div>
                  <h1 class="card-title">Update Program</h1>
                </div>
            </div>
                <div id = "create-section-program">
                  <form id = "UpdateProgram" method = "POST" action = "">
                    <label for="degreeLevel">Degree Level: </label>
                      <select name="degreeLevel" id="degreeLevel" required>
                        <option value="">-</option>
                      </select><br>
                    <label for="dept">Department: </label>
                       <select name="dept" id="dept" required>
                          <option value="">-</option>
                       </select><br>
                   <label for="creditsRequired">Credits Required: </label>
                      <input type = "number" id="creditsRequired" name="creditsRequired" required><br>
                  <label for="programName">Program Name:</label>
                    <input type="hidden" id="programNameHidden" name="programName">
                      <input type="text" id="programName" name="programName" disabled><br>
                    <label for="programCode">Program Code:</label>
                    <input type="hidden" id="programCodeHidden" name="programCode">
                      <input type="text" id="programCode" disabled><br>

                    <button type="submit" id = "submit">Submit</button>
                  </form>
                </div>
        </section>
    </main>
</body>

 <?php require __DIR__ . '/partials/footer.php'; ?>

  <script>
    // Immediately create Lucide icons
    lucide.createIcons();

    // Populate the year in the footer
    document.getElementById('year').textContent = new Date().getFullYear();

    // Fetch departments from get_grad_degree_level.php
    fetch('get_grad_degree_level.php')
    .then(response => response.json())
    .then(data => {
        const degreeLevelSelect = document.getElementById('degreeLevel');
        const selectedDegreeLevel = new URLSearchParams(window.location.search).get('degreeLevel');

    data.forEach(degreeLevel => {
        const opt = document.createElement('option');
        opt.value = degreeLevel
        opt.textContent = degreeLevel
        degreeLevelSelect.appendChild(opt);
        });
    })
    .catch(err => console.error('Error loading degree levels:', err));

    // Fetch departments from get_grad_degree_level.php
    fetch('get_grad_degree_level.php')
    .then(response => response.json())
    .then(data => {
        const degreeLevelSelect = document.getElementById('degreeLevel');
        const selectedDegreeLevel = new URLSearchParams(window.location.search).get('degreeLevel');

    data.forEach(degreeLevel => {
        const opt = document.createElement('option');
        opt.value = degreeLevel
        opt.textContent = degreeLevel
        degreeLevelSelect.appendChild(opt);
        });
    })
    .catch(err => console.error('Error loading degree levels:', err));

    // Fetch departments from get_departments.php
    fetch('get_departments.php')
    .then(response => response.json())
    .then(data => {
        const deptSelect = document.getElementById('dept');
        const selectedDept = new URLSearchParams(window.location.search).get('dept');

    data.forEach(dept => {
        const opt = document.createElement('option');
        opt.value = dept.name;
        opt.textContent = dept.name;
        deptSelect.appendChild(opt);
        });
    })
    .catch(err => console.error('Error loading semesters:', err));

    document.getElementById("CreateProgram").addEventListener("submit", (e) => {
    console.log("Form submitted");
  });
</script>