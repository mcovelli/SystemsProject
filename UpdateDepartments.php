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

    $sql = "UPDATE Department SET DeptName = ?, DeptEmail = ?, DeptPhone = ?, RoomID = ?, ChairID = ? WHERE DeptID = ?";

    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("ssssss", $DeptName, $DeptEmail, $DeptPhone, $RoomID, $ChairID, $DeptID);
    if ($stmt->execute()) {
        echo "<script>alert('$DeptName created ✅');</script>";
    } else {
        echo "<script>alert('Could not create department');</script>";
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
<title>Create Departments</title>
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
      <span class="pill">Create Departments</span>
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
                  <h1 class="card-title">Update Department</h1>
                </div>
            </div>
                <div id = "update-section-department">
                    <form id = "UpdateDepartment" method = "POST" action = "">
                      <label for = "deptID" hidden>Department ID: </label>
                            <input type = "hidden" id = "deptID" name="deptID" placeholder="ex. MATH"><br>
                        <label for="deptName">Department Name: </label>
                             <input type = "text" id="deptName" name="deptName" placeholder="ex. Mathematics"><br>

                        <label for="deptEmail">Department Email: </label>
                             <input type = "email" id="deptEmail" name="deptEmail" placeholder="ex. math@university.edu"><br>

                        <label for="deptPhone">Department Phone: </label>
                             <input type = "tel" id="deptPhone" name="deptPhone" placeholder="ex. (555) 123-4567"><br>

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

</body>


<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>

<script>
     lucide.createIcons();

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


    document.getElementById("UpdateDepartment").addEventListener("submit", (e) => {
    console.log("Form submitted");
});
</script>
