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

$loadedStudent = null;
$searchId = $_SESSION['user_id'];

if (isset($_POST['searchStudent'])) {
    $searchId = $_POST['searchID'];

    // Load Student table
    $stmt = $mysqli->prepare("SELECT 
          s.StudentID, 
          CONCAT(u.FirstName, ' ', u.LastName) AS StudentName 
        FROM Student s
        JOIN Users u ON s.StudentID = u.UserID
        LEFT JOIN StudentHold sh ON s.StudentID = sh.StudentID
        WHERE s.StudentID = ?
        ORDER BY s.StudentID ASC");
    $stmt->bind_param("i", $searchId);
    $stmt->execute();
    $loadedStudent = $stmt->get_result()->fetch_assoc();
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

if (isset($_POST['declareMajor'])) {
    $MajorID = $_POST['majorID'] ?? '';
    $StudentID = $_POST['studentID'] ?? '';
    $DateOfDeclaration = date('Y-m-d');

$mysqli->begin_transaction();

  $sql = "
    INSERT INTO StudentMajor (StudentID, MajorID, DateOfDeclaration)
    VALUES (?, ?, CURRENT_DATE())
    ON DUPLICATE KEY UPDATE
        MajorID = VALUES(MajorID),
        DateOfDeclaration = CURRENT_DATE()
    ";
  $stmt = $mysqli->prepare($sql);
  $stmt->bind_param("ii", $StudentID, $MajorID);
  $stmt->execute();

  $sql = "UPDATE Student SET MajorID = ? WHERE StudentID = ?";
  $stmt = $mysqli->prepare($sql);
  $stmt->bind_param("ii", $MajorID, $StudentID);
  $stmt->execute();

if ($stmt->execute()) {
    $mysqli->commit();
    echo "<script>alert('Major Declared ✅');</script>";
} else {
    $mysqli->rollback();
    echo "<script>alert('Could Not Declare Major');</script>";
}
}

$userRole = strtolower($_SESSION['role'] ?? '');
switch ($userRole) {
    case 'student':
        $dashboard = 'student_dashboard.php';
        $profile = 'student_profile.php';
        break;
    case 'admin':
        if (($_SESSION['admin_type'] ?? '') === 'update') {
            $dashboard = 'update_admin_dashboard.php';
            $profile = 'admin_profile.php';
        } else {
            $dashboard = 'view_admin_dashboard.php';
            $profile = 'admin_profile.php';
        }
        break;
    default:
        $dashboard = 'login.html';
        $profile = 'login.html';
}

?>

<!doctype html>
<html lang="en" data-theme="light">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Declare Major • Northport University</title>
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
      <span class="pill">Declare Major</span>
    </div>
    <div class="top-actions">
      <button id="themeToggle" class="icon-btn" aria-label="Toggle theme"><i data-lucide="moon"></i></button>
      <div class="divider"></div>
      <div class="user">
        <img class="avatar" src="https://i.pravatar.cc/64?img=20" alt="avatar" />
        <div class="user-meta">
          <div class="name"><?php echo htmlspecialchars($user['FirstName'] . ' ' . $user['LastName']); ?></div>
        </div>
        <div class="header-left">
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
    </div>
  </header>

  <!-- SEARCH Student CARD -->
<?php if (!$isStudent): ?>
<section class="hero card">
    <h2>Search for Student</h2>

    <form method="POST">
        <label>Student</label>
        <input type="text" name="searchID" required placeholder="Enter StudentID...">
        <button type="submit" name="searchStudent">Search</button>
    </form>
</section>
<?php endif; ?>


<!-- IF Student LOADED, DISPLAY FORM -->
<?php if (!empty($loadedStudent)) : ?>

<section class="hero card" style="margin-top: 20px;">
    <h2>Declare Major: <?php echo htmlspecialchars($loadedStudent['StudentName'] . " - " . $loadedStudent['StudentID']); ?></h2>

    <form method="POST">

        <!-- STUDENT TABLE FIELDS -->
        <div class="section-card">
            <h3>Basic Information</h3>

            <div class="field-block">
                <label>Student ID (read only): </label>
                <input type="text" name="studentID" readonly value="<?php echo $loadedStudent['StudentID']; ?>">
            </div>

            <div class="field-block">
                <label for = "majorID" required>Major: </label>
                  <select name="majorID" id ="majorID">
                    <option value="">-- Select Major--</option>
                  </select>
            </div>

            <div class="field-block">
                <label>Date of Declaration</label>
                <input type="text" name="DateOfDeclaration" readonly 
                  value="<?php echo htmlspecialchars(date('Y-m-d')); ?>">
            </div>

            <div style="margin-top: 20px;">
                <button type="submit" name="declareMajor">Save Changes</button>
            </div>

    </form>

</section>

<?php endif; ?>

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

    // Fetch faculty from get_faculty.php
    fetch('get_holds.php')
    .then(response => response.json())
    .then(data => {
        const holdSelect = document.getElementById('holdID');
        const selectedHold = new URLSearchParams(window.location.search).get('holdID');

    data.forEach(hold => {
        const opt = document.createElement('option');
        opt.value = hold.id;
        opt.textContent = hold.type
        holdSelect.appendChild(opt);
        });
    })
    .catch(err => console.error('Error loading Holds:', err));


    document.getElementById("CreateDepartment").addEventListener("submit", (e) => {
    console.log("Form submitted");
});
</script>

</body>
</html>

