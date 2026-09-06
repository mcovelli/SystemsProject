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
        FROM Minor
        WHERE MinorName LIKE CONCAT('%', ?, '%')
           OR MinorID   LIKE CONCAT('%', ?, '%')
    ");
    $stmt->bind_param("ss", $searchId, $searchId);
    $stmt->execute();
    $loadedProgram = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$userId = $_SESSION['user_id'];


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
    $DeptId = $_POST['deptID'];
    $name = $_POST['name'];
    $creditsNeeded = $_POST['creditsNeeded'];

    $mysqli->begin_transaction();

    $stmt = $mysqli->prepare("
        UPDATE Minor 
        SET DeptID = ?, MinorName = ?, CreditsNeeded = ?
        WHERE MinorID = ?
    ");
    $stmt->bind_param("isii", $DeptId, $name, $creditsNeeded, $ID);

    if ($stmt->execute()) {
    $mysqli->commit();
    $_SESSION['update_success'] = true;
    $_SESSION['success_message'] = 'Minor updated successfully!';
    header("Location: UpdateMinors.php");
    exit;
} else {
    $mysqli->rollback();
    $_SESSION['update_success'] = false;
    $_SESSION['success_message'] = 'Update failed: ' . $stmt->error;
    header("Location: UpdateMinors.php");
    exit;
}
}

?>


<!DOCTYPE html>
<html lang="en">
<?php $nu_title = 'Update Minor'; require __DIR__ . '/partials/head.php'; ?>
<body>
  <?php $nu_page = 'Update Minor'; $nu_crumb = ['createDirectory.php', '← Back to Directory']; $nu_bell = false; require __DIR__ . '/partials/header.php'; ?>

    <main class="page">
        <section class="hero card">
            <div class="card-head between">
                <div>
                  <h1 class="card-title">Update Minor</h1>
                </div>
            </div>

            <section class="hero card">
                <h2>Search Minor </h2>

                <form method="POST" style="margin-top: 10px;">
                    <label>Search by Name or ID</label>
                    <input type="text" name="searchID" required placeholder="ex. MATH or Mathematics">
                    <button type="submit" name="searchPrograms">Search</button>
                </form>
            </section>

            <!-- IF Minor LOADED, DISPLAY FORM -->
            <?php if (!empty($loadedProgram)) : ?>
                <div id = "update-section-department">
                    <form id = "UpdateProgram" method = "POST" action = "">
                      <label for = "ID" readonly>Minor ID (read only):</label>
                            <input type="text" id="ID" name="ID" readonly
                               value="<?= htmlspecialchars($loadedProgram['MinorID'] ?? '') ?>"
                               placeholder="ex. 1"><br>

                        <div class = "field-block">
                            <label for="name">Program Name: </label>
                            <input type="text" id="name" name="name"
                               value="<?= htmlspecialchars($loadedProgram['MinorName'] ?? '') ?>"
                               placeholder="ex. Mathematics"><br>
                        </div>

                        <label for ="deptID">Dept Name: </label>
                            <select name="deptID" id="deptID">
                                <option value="">-- Select Department --</option>
                            </select><br>

                            <label for="creditsNeeded">Credits Needed: </label>
                                 
                                <input type="number" id="creditsNeeded" name="creditsNeeded"
                                       value="<?= htmlspecialchars($loadedProgram['CreditsNeeded'] ?? '') ?>"
                                       placeholder="96"><br>

                            <div style="margin-top: 20px;">
                                <button type="submit" name="UpdateProgram">Save Changes</button>
                            </div>
                    </form>
                </div>
        </section>
        <?php endif; ?>
    </main>

    <?php require __DIR__ . '/partials/footer.php'; ?>
    <div id="toast" class="toast hidden"></div>


  <script>
    // Immediately create Lucide icons
    lucide.createIcons();

    // Populate the year in the footer
    document.getElementById('year').textContent = new Date().getFullYear();

// Fetch dept from get_departments.php
    const currentDept = <?= json_encode($loadedProgram['DeptID'] ?? '') ?>;

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

function showToast(message, type = "success") {
  const toast = document.getElementById("toast");
  toast.textContent = message;

  toast.classList.remove("hidden", "error");
  if (type === "error") toast.classList.add("error");

  setTimeout(() => toast.classList.add("show"), 50);

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