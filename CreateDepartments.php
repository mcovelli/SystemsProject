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
    $DeptName = $_POST['deptName'] ?? '';
    $DeptEmail = $_POST['deptEmail'] ?? '';
    $DeptPhone = $_POST['deptPhone'] ?? '';
    $RoomID = $_POST['roomID'] ?? '';
    $ChairID = $_POST['chairID'] ?? '';

    $mysqli->begin_transaction();

    $sql = "INSERT INTO Department (DeptName, Email, Phone, RoomID, ChairID) 
            VALUES (?, ?, ?, ?, ?)";

    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("sssss", $DeptName, $DeptEmail, $DeptPhone, $RoomID, $ChairID);
    if ($stmt->execute()) {
        echo "<script>alert('$DeptName created ✅');</script>";
    } else {
        echo "<script>alert('Could not create department');</script>";
    }

    $mysqli->commit();
} 

?>


<!DOCTYPE html>
<html lang="en">
<?php $nu_title = 'Create Departments'; require __DIR__ . '/partials/head.php'; ?>
<body>
  <?php $nu_page = 'Create Departments'; $nu_crumb = ['createDirectory.php', '← Back to Directory']; $nu_bell = false; require __DIR__ . '/partials/header.php'; ?>

    <main class="page">
        <section class="hero card">
            <div class="card-head between">
                <div>
                  <h1 class="card-title">Create Department</h1>
                </div>
            </div>
                <div id = "create-section-department">
                    <form id = "CreateDepartment" method = "POST" action = "">
                        <label for="deptName">Department Name: </label>
                             <input type = "text" id="deptName" name="deptName" required placeholder="ex. Mathematics"><br>

                        <label for="deptEmail">Department Email: </label>
                             <input type = "email" id="deptEmail" name="deptEmail" required placeholder="ex. math@university.edu"><br>

                        <label for="deptPhone">Department Phone: </label>
                             <input type = "tel" id="deptPhone" name="deptPhone" required placeholder="ex. (555) 123-4567"><br>

                        <label for ="roomID">Room ID: </label>
                            <select name="roomID" id="roomID">
                                <option value="">-- Select Office --</option>
                            </select><br>

                        <label for = "chairID">Chair:</label>
                            <select name="chairID" id="chairID">
                                <option value="">-- Select Chair --</option>
                            </select><br>

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

    // Fetch offices from get_offices.php
    fetch('get_offices.php')
    .then(response => response.json())
    .then(data => {
        const officeSelect = document.getElementById('roomID');
        const selectedOffice = new URLSearchParams(window.location.search).get('roomID');

    data.forEach(office => {
        const opt = document.createElement('option');
        opt.value = office.id;
        opt.textContent = office.id;
        officeSelect.appendChild(opt);
        });
    })
    .catch(err => console.error('Error loading offices:', err));

    // Fetch faculty from get_faculty.php
    fetch('get_faculty.php')
    .then(response => response.json())
    .then(data => {
        const officeSelect = document.getElementById('chairID');
        const selectedOffice = new URLSearchParams(window.location.search).get('chairID');

    data.forEach(faculty => {
        const opt = document.createElement('option');
        opt.value = faculty.FacultyID;
        opt.textContent = faculty.FacultyName + ' - ' + faculty.DeptNames;
        officeSelect.appendChild(opt);
        });
    })
    .catch(err => console.error('Error loading faculty:', err));


    document.getElementById("CreateDepartment").addEventListener("submit", (e) => {
    console.log("Form submitted");
});
</script>

</body>
</html>

