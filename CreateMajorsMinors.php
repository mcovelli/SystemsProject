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
<title>Create Majors and Minors</title>
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
      <span class="pill">Create Majors and Minors</span>
    </div>
    <div class="top-actions">
      <div class="search">
        <i class="search-icon" data-lucide="search"></i>
        <input type="text" placeholder="Search courses, people, anything…" />
      </div>
      <button class="icon-btn" aria-label="Notifications" a href="announcements.php"><i data-lucide="bell"></i></button>
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
                        <label for="dept">Department: </label>
                             <select name="deptID" id="deptID">
                                <option value="">-- All Departments --</option>
                                </select><br>
                        <label id="typeLabel" for ="major_name">Name: </label>
                        <input type = "text" id="name" name="name" required><br>
                        <label id="Credits" for ="credits_needed">Credits Needed: </label>
                        <input type = "number" id="credits_needed" name="credits_needed" required><br>
                        

                        <button type="submit" id = "submit">Submit</button>
                    </form>
                </div>
        </section>
    </main>
<footer class="footer">© <span id="year"></span> Northport University</footer>
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
      if (window.lucide) lucide.createIcons();
    });

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