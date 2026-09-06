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
    $DeptID = $_POST['deptID'] ?? '';
    
    $mysqli->begin_transaction();

    $sql = "Delete FROM Department Where DeptID = ?";

    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("s", $DeptID);
    if ($stmt->execute()) {
        echo "<script>alert('$DeptID deleted ✅');</script>";
    } else {
        echo "<script>alert('Could not delete department');</script>";
    }

    $mysqli->commit();
} 

?>


<!DOCTYPE html>
<html lang="en">
<?php $nu_title = 'Create Departments'; require __DIR__ . '/partials/head.php'; ?>
<body>
  <?php $nu_page = 'Department Status'; $nu_crumb = ['createDirectory.php', '← Back to Directory']; $nu_bell = false; require __DIR__ . '/partials/header.php'; ?>

    <main id="main" tabindex="-1" class="page">
        <section class="hero card">
            <div class="card-head between">
                <div>
                  <h1 class="card-title">Delete Department</h1>
                </div>
            </div>
                <div id = "delete-section-department">
                    <form id = "DeleteDepartment" method = "POST" action = "">
                      <label for="deptID">Department: </label>
                             <select name="deptID" id="deptID" required>
                              <option>--SELECT--</option>
                                </select><br>
                        
                        <button type="submit" id = "submit">Delete</button>
                    </form>
                </div>
        </section>
    </main>
<?php require __DIR__ . '/partials/footer.php'; ?>

<script>
      // Immediately create Lucide icons
    lucide.createIcons();

    // Populate the year in the footer
    document.getElementById('year').textContent = new Date().getFullYear();

    // Fetch departments from get_departments.php
    fetch('get_departments.php')
    .then(response => response.json())
    .then(data => {
        const deptSelect = document.getElementById('deptID');
        const selected = new URLSearchParams(window.location.search).get('deptID');

    data.forEach(name => {
        const opt = document.createElement('option');
        opt.value = name.id;
        opt.textContent = name.name;
        deptSelect.appendChild(opt);
        });
    })
    .catch(err => console.error('Error loading departments:', err));

    // Delete departments
    document.getElementById("DeleteDepartment").addEventListener("submit", (e) => {
    console.log("Form submitted");
});
</script>

</body>
</html>