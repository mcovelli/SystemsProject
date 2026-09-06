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
    $deptId = $_POST['deptID'] ?? '';
    $creditsNeeded = $_POST['credits_needed'] ?? '';
    $name = $_POST['name'] ?? '';
}

$mysqli->begin_transaction();

$majorOrMinor = $_POST['majorOrMinor'] ?? '';

switch ($majorOrMinor){
    case 'major':
        $sql = "INSERT INTO Major (DeptID, MajorName, CreditsNeeded) VALUES (?, ?, ?)";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param("isi", $deptId, $name, $creditsNeeded);
        
        if ($stmt->execute()) {
            $mysqli->commit();
            echo "alert('$name. major created ✅');";
        } else {
            echo "alert('Could not create Major');";
        }
    break;

    case 'minor':
        $sql = "INSERT INTO Minor (DeptID, MinorName, CreditsNeeded) VALUES (?, ?, ?)";
            $stmt = $mysqli->prepare($sql);
            $stmt->bind_param("isi", $deptId, $name, $creditsNeeded);

            if ($stmt->execute()) {
                $mysqli->commit();
                echo "alert('$name. minor created ✅');";
            } else {
                $mysqli->rollback();
                echo "alert('Could not create Minor');";
            }
        break;
}

?>


<!DOCTYPE html>
<html lang="en">
<?php $nu_title = 'Create Majors and Minors'; require __DIR__ . '/partials/head.php'; ?>
<body>
  <?php $nu_page = 'Create Majors and Minors'; $nu_crumb = ['createDirectory.php', '← Back to Directory']; $nu_bell = false; require __DIR__ . '/partials/header.php'; ?>

    <main id="main" tabindex="-1" class="page">
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
                        <label for="deptID">Department: </label>
                             <select name="deptID" id="deptID">
                                <option value="">-- All Departments --</option>
                                </select><br>
                        <label for="name" id="typeLabel">Name: </label>
                        <input type = "text" id="name" name="name" required><br>
                        <label for="credits_needed" id="Credits">Credits Needed: </label>
                        <input type = "number" id="credits_needed" name="credits_needed" required><br>
                        

                        <button type="submit" id = "submit">Submit</button>
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

    document.getElementById("CreateMajorMinor").addEventListener("submit", (e) => {
    console.log("Form submitted");
});
</script>

</body>
</html>