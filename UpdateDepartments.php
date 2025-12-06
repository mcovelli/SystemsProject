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

$loadedDepartments = null;

if (isset($_POST['searchDepartments'])) {
    $searchId = $_POST['searchID'];

    // Load Dept table
    $stmt = $mysqli->prepare("
        SELECT d.DeptID, d.DeptName, d.ChairID, CONCAT(fu.FirstName, ' ', substr(fu.MiddleName, 1, 1), '. ', fu.LastName) AS ChairName, d.RoomID, d.Phone, d.Email
        FROM Department d
        JOIN Users fu ON d.ChairID = fu.UserID
        WHERE DeptName LIKE CONCAT('%', ?, '%')
           OR DeptID   LIKE CONCAT('%', ?, '%')
        LIMIT 1
    ");
    $stmt->bind_param("ss", $searchId, $searchId);
    $stmt->execute();
    $loadedDepartments = $stmt->get_result()->fetch_assoc();
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

if (isset($_POST['UpdateDepartment'])) {
    $DeptID = $_POST['deptID'] ?? '';
    $DeptName = $_POST['deptName'] ?? '';
    $DeptEmail = $_POST['deptEmail'] ?? '';
    $DeptPhone = $_POST['deptPhone'] ?? '';
    $RoomID = $_POST['roomID'] ?? '';
    $ChairID = $_POST['chairID'] ?? '';

    $mysqli->begin_transaction();

    $sql = "UPDATE Department SET DeptName = ?, Email = ?, Phone = ?, RoomID = ?, ChairID = ? WHERE DeptID = ?";

    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("ssssss", $DeptName, $DeptEmail, $DeptPhone, $RoomID, $ChairID, $DeptID);
    if ($stmt->execute()) {
        echo "<script>alert('$DeptName updated ✅');</script>";
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

  <style>
/* Inline enhancements */
.field-block {
    margin-bottom: 12px;
}
label {
    font-weight: 600;
    display: block;
    margin-bottom: 3px;
}
input[type=text], input[type=date], select {
    width: 280px;
    padding: 6px;
    border-radius: 6px;
    border: 1px solid #ccc;
}
.multiselect {
    height: 120px;
    width: 300px;
    padding: 6px;
}
.section-card {
    border: 1px solid #ddd;
    padding: 15px;
    border-radius: 10px;
    margin-top: 10px;
    background: var(--card-bg);
}

.toast {
    position: fixed;
    top: 20px;
    right: 20px;
    background: #28a745;
    color: white;
    padding: 12px 18px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 16px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    opacity: 0;
    transform: translateY(-15px);
    transition: opacity 0.3s ease, transform 0.3s ease;
    z-index: 9999;
}

.toast.show {
    opacity: 1;
    transform: translateY(0);
}

.toast.hidden {
    display: none;
}
</style>

<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Update Departments</title>
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
      <span class="pill">Update Departments</span>
    </div>
    <div class="top-actions">
      <div class="search">
        <i class="search-icon" data-lucide="search"></i>
        <input type="text" placeholder="Search courses, people, anything…" />
      </div>
      <button id="themeToggle" class="icon-btn" aria-label="Toggle theme"><i data-lucide="moon"></i></button>
      <div class="divider"></div>
      <div class="crumb"><a href="createDirectory.php" aria-label="Back to Directory">← Back to Directory</a></div>
    </div>

    <div class="avatar" aria-hidden="true"><span id="initials"><?php echo $initials ?: 'NU'; ?></span></div>
        <div class="user-meta"><div class="name"><?php echo htmlspecialchars($user['UserType']) ?></div></div>
        <div class="menu">
          <button>☰ Menu</button>
          <div class="menu-content">
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

            <section class="hero card">
                <h2>Search Department</h2>

                <form method="POST" style="margin-top: 10px;">
                    <label>Search by Dept Name or Dept ID</label>
                    <input type="text" name="searchID" required placeholder="ex. MATH or Mathematics">
                    <button type="submit" name="searchDepartments">Search</button>
                </form>
            </section>

            <!-- IF Course LOADED, DISPLAY FORM -->
            <?php if (!empty($loadedDepartments)) : ?>
                <div id = "update-section-department">
                    <form id = "UpdateDepartment" method = "POST" action = "">
                      <label for = "deptID" hidden></label>
                            <input type = "hidden" id = "deptID" name="deptID" placeholder="ex. MATH"><br>
                        <div class = "field-block">
                            <label for="deptName">Department Name: </label>
                                 <input type = "text" id="deptName" name="deptName" placeholder="ex. Mathematics"><br>
                        </div>

                            <label for="deptEmail">Department Email: </label>
                                 <input type = "email" id="deptEmail" name="deptEmail" placeholder="ex. math@university.edu"><br>

                            <label for="deptPhone">Department Phone: </label>
                                 <input type = "tel" id="deptPhone" name="deptPhone" placeholder="ex. (555) 123-4567"><br>

                            <label for ="roomID">Room ID: </label>
                                <select name="roomID" id="roomID">
                                    <option value="<?php echo $loadedDepartments['RoomID']; ?>"><?php echo $loadedDepartments['RoomID']; ?></option>
                                </select><br>

                            <label for = "chairID">Chair:</label>
                                <select name="chairID" id="chairID">
                                    <option value="<?php echo $loadedDepartments['ChairID']; ?>"><?php echo $loadedDepartments['ChairName']; ?></option>
                                </select><br>

                            <div style="margin-top: 20px;">
                                <button type="submit" name="UpdateDepartment">Save Changes</button>
                            </div>
                    </form>
                </div>
        </section>
        <?php endif; ?>
    </main>

    <?php if (!empty($loadedDepartments)): ?>
    <script>
    document.addEventListener("DOMContentLoaded", () => {
        document.getElementById("deptID").value = "<?php echo $loadedDepartments['DeptID']; ?>";
        document.getElementById("deptName").value = "<?php echo $loadedDepartments['DeptName']; ?>";
        document.getElementById("deptEmail").value = "<?php echo $loadedDepartments['Email']; ?>";
        document.getElementById("deptPhone").value = "<?php echo $loadedDepartments['Phone']; ?>";
        document.getElementById("roomID").value = "<?php echo $loadedDepartments['RoomID']; ?>";
        document.getElementById("chairID").value = "<?php echo $loadedDepartments['ChairID']; ?>";
    });
    </script>
    <?php endif; ?>

    <footer class="footer">© <span id="year"></span> Northport University</footer>
    <div id="toast" class="toast hidden"></div>


<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
  <script>
    // Immediately create Lucide icons
    lucide.createIcons();

    // Populate the year in the footer
    document.getElementById('year').textContent = new Date().getFullYear();

    // Theme toggle
    const themeToggle = document.getElementById('themeToggle');
    themeToggle.addEventListener('click', () => {
      const root = document.documentElement;
      const current = root.getAttribute('data-theme') || 'light';
      root.setAttribute('data-theme', current === 'light' ? 'dark' : 'light');
      // Swap the icon
      themeToggle.querySelector('i').setAttribute('data-lucide', current === 'light' ? 'sun' : 'moon');
      lucide.createIcons();
    });


     // Fetch offices from get_offices.php
    const currentRoom = "<?php echo isset($loadedDepartments['RoomID']) ? $loadedDepartments['RoomID'] : ''; ?>";

    fetch(`get_offices.php?current=${currentRoom}`)
    .then(response => response.json())
    .then(data => {
        const roomSelect = document.getElementById('roomID');
        const selectedRoom = new URLSearchParams(window.location.search).get('roomID');
        roomSelect.innerHTML = "";

    data.forEach(room => {
        const opt = document.createElement('option');
        opt.value = room.id;
        opt.textContent = `${room.id}`;
        if (String(room.id) === String(currentRoom)) opt.selected = true;
        roomSelect.appendChild(opt);
        });
    })
    .catch(err => console.error('Error loading Rooms:', err));

      // Fetch faculty from get_faculty.php
    const currentFac = "<?php echo isset($loadedDepartments['ChairID']) ? $loadedDepartments['ChairID'] : ''; ?>";

    fetch(`get_faculty.php?current=${currentFac}`)
        .then(response => response.json())
        .then(data => {
            const facultySelect = document.getElementById('chairID');
            facultySelect.innerHTML = "";

            data.forEach(faculty => {
                const opt = document.createElement('option');
                opt.value = faculty.FacultyID;
                opt.textContent = `${faculty.FacultyID} — ${faculty.FacultyName} — ${faculty.DeptNames}`;
                if (String(faculty.FacultyID) == currentFac) opt.selected = true;
                facultySelect.appendChild(opt);
            });
        })
        .catch(err => console.error('Error loading available faculty:', err));


    document.getElementById("UpdateDepartment").addEventListener("submit", (e) => {
    console.log("Form submitted");
});

function showToast(message) {
    const toast = document.getElementById("toast");
    toast.textContent = message;
    toast.classList.remove("hidden");

    // Trigger animation
    setTimeout(() => {
        toast.classList.add("show");
    }, 100);

    // Hide after 3 seconds
    setTimeout(() => {
        toast.classList.remove("show");
        setTimeout(() => toast.classList.add("hidden"), 300);
    }, 3000);
}
</script>

<?php if (!empty($_SESSION['update_success'])): ?>
<script>
document.addEventListener("DOMContentLoaded", () => {
    showToast("Course Section updated successfully!");
});
</script>
<?php unset($_SESSION['update_success']); ?>
<?php endif; ?>
</body>
</html>