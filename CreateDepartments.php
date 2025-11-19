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
    $DeptName = $_POST['deptName'] ?? '';
    $DeptEmail = $_POST['deptEmail'] ?? '';
    $DeptPhone = $_POST['deptPhone'] ?? '';
    $RoomID = $_POST['roomID'] ?? '';
    $ChairID = $_POST['chairID'] ?? '';

    $mysqli->begin_transaction();

    $sql = "INSERT INTO Department (DeptName, DeptEmail, DeptPhone, RoomID, ChairID) 
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
<!doctype html>
<html lang="en" data-theme="light">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Northport University — Create Course</title>
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

</body>


<script>

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
    .catch(err => console.error('Error loading offices:', err));


    document.getElementById("CreateDepartment").addEventListener("submit", (e) => {
    console.log("Form submitted");
});
</script>
