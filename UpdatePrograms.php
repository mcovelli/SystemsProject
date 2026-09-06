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
$error_message = '';

$mysqli = get_db();
$mysqli->set_charset('utf8mb4');

// Fetch admin security type
$adminCheck = $mysqli->prepare("
    SELECT SecurityType 
    FROM Admin 
    WHERE AdminID = ? LIMIT 1
");
$adminCheck->bind_param("i", $_SESSION['user_id']);
$adminCheck->execute();
$adminType = $adminCheck->get_result()->fetch_assoc()['SecurityType'] ?? null;
$adminCheck->close();

if ($adminType !== 'UPDATE') {
    die("<h2 style='color:red;'>Access Denied: You are not an UpdateAdmin.</h2>");
}

$loadedProgram = null;

if (isset($_POST['searchPrograms'])) {
    $searchId = $_POST['searchID'];

    // Load Dept table
    $stmt = $mysqli->prepare("
        SELECT * 
        FROM Program
        WHERE ProgramName LIKE CONCAT('%', ?, '%')
           OR ProgramID   LIKE CONCAT('%', ?, '%')
    ");
    $stmt->bind_param("si", $searchId, $searchId);
    $stmt->execute();
    $loadedProgram = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$userId = $_SESSION['user_id'];
$error_message = '';


$usersql = "SELECT UserID, FirstName, LastName, Email, UserType, Status, DOB
        FROM Users WHERE UserID = ? LIMIT 1";
$userstmt = $mysqli->prepare($usersql);
$userstmt->bind_param("i", $userId);
$userstmt->execute();
$userres = $userstmt->get_result();
$user = $userres->fetch_assoc();
$userstmt->close();

if (isset($_POST['UpdateProgram'])) {

    $ID = $_POST['ID'];
    $Code = $_POST['code'];
    $name = $_POST['name'];
    $degreeLevel = $_POST['degreeLevel'];
    $DeptId = $_POST['deptID'];
    $creditsRequired = $_POST['creditsRequired'];
    $Status = $_POST['status'];

    $stmt = $mysqli->prepare("
        UPDATE Program 
        SET DeptID = ?, ProgramName = ?, ProgramCode = ?, CreditsRequired = ?, DegreeLevel = ?, Status = ?
        WHERE ProgramID = ?
    ");
    $stmt->bind_param("ississi", $DeptId, $name, $Code, $creditsRequired, $degreeLevel, $Status, $ID);

if ($stmt->execute()) {
    $mysqli->commit();
    $_SESSION['update_success'] = true;
    $_SESSION['success_message'] = 'Program updated successfully!';
    header("Location: UpdatePrograms.php");
    exit;
} else {
    $mysqli->rollback();
    $_SESSION['update_success'] = false;
    $_SESSION['success_message'] = 'Major could not be updated';
    header("Location: UpdatePrograms.php");
    exit;
}

}
?>


<!DOCTYPE html>
<html lang="en">
<?php $nu_title = 'Update Program'; require __DIR__ . '/partials/head.php'; ?>
<body>
  <?php $nu_page = 'Update Program'; $nu_crumb = ['createDirectory.php', '← Back to Directory']; $nu_bell = false; require __DIR__ . '/partials/header.php'; ?>

    <main id="main" tabindex="-1" class="page">
        <section class="hero card">
            <div class="card-head between">
                <div>
                  <h1 class="card-title">Update Program</h1>
                </div>
            </div>

            <section class="hero card">
                <h2>Search Program</h2>

                <form method="POST" style="margin-top: 10px;">
                    <label for="search-by-name-or-id">Search by Name or ID</label>
                    <input id="search-by-name-or-id" type="text" name="searchID" required placeholder="ex. MATH or Mathematics">
                    <button type="submit" name="searchPrograms">Search</button>
                </form>
            </section>

            <!-- IF Program LOADED, DISPLAY FORM -->
            <?php if (!empty($loadedProgram)) : ?>
                <div id = "update-section-department">
                    <form id = "UpdateProgram" method = "POST" action = "">
                      <label for="ID" readonly>Program ID (read only):</label>
                            <input type = "text" id = "ID" name="ID" readonly placeholder="ex. 1"><br>

                      <div class = "field-block">
                            <label for="code">Program Code: </label>
                            <input type = "text" id="code" name="code" placeholder="ex. PHDMATH"><br>
                        </div>

                        <div class = "field-block">
                            <label for="name">Program Name: </label>
                            <input type = "text" id="name" name="name" placeholder="ex. Ph.D. in Mathematics"><br>
                        </div>

                        <div class = "field-block">
                            <label for="degreeLevel">Degree Level: </label>
                            <select name="degreeLevel" id="degreeLevel">
                                <option value="">-- Select Degree Level --</option>
                            </select><br>
                        </div>

                        <div class = "field-block">
                            <label for="deptID">Dept Name: </label>
                            <select name="deptID" id="deptID">
                                <option value="">-- Select Department --</option>
                            </select><br>
                        </div>

                        <div class = "field-block">
                            <label for="creditsNeeded">Credits Required: </label>
                                 <input type = "number" id="creditsRequired" name="creditsRequired" placeholder="96"><br>
                          </div>

                          <div class = "field-block">
                            <label for="status">Status: </label>
                            <select name="status" id="status" required>
                                <option value="">-- Select Status --</option>
                                <option value="ACTIVE">ACTIVE</option>
                                <option value="INACTIVE">INACTIVE</option>
                            </select><br>
                        </div>

                            <div style="margin-top: 20px;">
                                <button type="submit" name="UpdateProgram">Save Changes</button>
                            </div>
                    </form>
                </div>
        <?php endif; ?>
        <?php /* This </section> closes the outer .hero card, which opens before the
                 conditional -- so leaving it inside meant the card never closed on
                 the search screen, and everything after it landed inside the card. */ ?>
        </section>
    </main>

    <?php require __DIR__ . '/partials/footer.php'; ?>
    <div id="toast" class="toast hidden"></div>


<?php if (!empty($loadedProgram)): ?>
    <script>
    document.addEventListener("DOMContentLoaded", () => {
        document.getElementById("ID").value = "<?php echo $loadedProgram['ProgramID'] ?? ''; ?>";
        document.getElementById("code").value = "<?php echo $loadedProgram['ProgramCode'] ?? '';
         ?>";
         document.getElementById("degreeLevel").value = "<?php echo $loadedProgram['DegreeLevel'] ?? ''; ?>";
        document.getElementById("deptID").value = "<?php echo $loadedProgram['DeptID'] ?? ''; ?>";
        document.getElementById("name").value = "<?php echo $loadedProgram['ProgramName'] ?? ''; ?>";
        document.getElementById("ID").value = "<?php echo $loadedProgram['ProgramID'] ?? ''; ?>";
        document.getElementById("creditsRequired").value = "<?php echo $loadedProgram['CreditsRequired'] ?? ''; ?>";
        document.getElementById("status").value = "<?php echo $loadedProgram['Status'] ?? ''; ?>";
    });
    </script>
    <?php endif; ?>


  <script>
    // Immediately create Lucide icons
    lucide.createIcons();

    // Populate the year in the footer
    document.getElementById('year').textContent = new Date().getFullYear();

    // Fetch dept from get_departments.php
    const currentDept = "<?php echo $loadedProgram['DeptID'] ?? ''; ?>";

    fetch(`get_departments.php?current=${currentDept}`)
    .then(response => response.json())
    .then(data => {
        const deptSelect = document.getElementById('deptID');

    data.forEach(dept => {
        const opt = document.createElement('option');
        opt.value = dept.id;
        opt.textContent = dept.name;

        if (dept.id == currentDept) opt.selected = true;
        
        deptSelect.appendChild(opt);
        });
    })
    .catch(err => console.error('Error loading departments:', err));

  // Fetch degreeLevel from get_grad_degree_level.php
    const currentDegree = "<?php echo $loadedProgram['DegreeLevel'] ?? ''; ?>";

    fetch(`get_grad_degree_level.php?current=${currentDegree}`)
    .then(response => response.json())
    .then(data => {
        const degreeSelect = document.getElementById('degreeLevel');

    data.forEach(degree => {
        const opt = document.createElement('option');
        opt.value = degree.degreelevel;
        opt.textContent = degree.degreelevel;

        if (degree.degreelevel == currentDegree) opt.selected = true;
        
        degreeSelect.appendChild(opt);
        });
    })
    .catch(err => console.error('Error loading Grad Degree Levels:', err));

    function showToast(message) {
        const toast = document.getElementById("toast");
        toast.textContent = message;
        toast.classList.remove("hidden");

        setTimeout(() => {
            toast.classList.add("show");
        }, 100);

        setTimeout(() => {
            toast.classList.remove("show");
            setTimeout(() => toast.classList.add("hidden"), 300);
        }, 3000);
    }
</script>

<?php
$toastMsg = '';
$toastType = 'success';

if (!empty($_SESSION['success_message'])) {
  $toastMsg = $_SESSION['success_message'];
  $toastType = !empty($_SESSION['update_success']) ? 'success' : 'error';
  unset($_SESSION['success_message'], $_SESSION['update_success']);
} elseif (!empty($error_message)) {
  $toastMsg = $error_message;
  $toastType = 'error';
}
?>

<script>
document.addEventListener("DOMContentLoaded", () => {
  const msg = <?= json_encode($toastMsg) ?>;
  const type = <?= json_encode($toastType) ?>;
  if (msg) showToast(msg, type);
});
</script>
</body>
</html>