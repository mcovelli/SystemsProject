<?php
session_start();
require_once __DIR__ . '/config.php';

$role         = $_SESSION['role'] ?? '';
$studentType  = $_SESSION['student_type'] ?? '';
$adminType    = $_SESSION['admin_type'] ?? '';

if (
    !isset($_SESSION['user_id']) ||
    !(
        $role === 'student' ||
        $studentType === 'undergrad' ||
        ($role === 'admin' && $adminType === 'update')
    )
) {
    redirect(PROJECT_ROOT . "/login.html");
}

$isStudent = ($_SESSION['role'] ?? '') === 'student';

$userId = $_SESSION['user_id'];

$mysqli = get_db();
$mysqli->set_charset('utf8mb4');

$loadedStudent = null;

if ($isStudent) {

    $searchId = $_SESSION['user_id'];

    $stmt = $mysqli->prepare("SELECT 
          s.StudentID, 
          CONCAT(u.FirstName, ' ', u.LastName) AS StudentName,
          sm.MinorID
        FROM Student s
        JOIN Users u ON s.StudentID = u.UserID
        LEFT JOIN StudentMinor sm ON s.StudentID = sm.StudentID
        LEFT JOIN Minor m ON sm.MinorID = m.MinorID
        WHERE s.StudentID = ?
        ORDER BY s.StudentID ASC");

    $stmt->bind_param("i", $searchId);
    $stmt->execute();
    $loadedStudent = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} else if (isset($_POST['searchStudent'])) {
    $searchId = $_POST['searchID'];

    // Load Student table
    $stmt = $mysqli->prepare("SELECT 
          s.StudentID, 
          CONCAT(u.FirstName, ' ', u.LastName) AS StudentName,
          smn.MinorID
        FROM Student s
        JOIN Users u ON s.StudentID = u.UserID
        LEFT JOIN StudentMinor smn ON s.StudentID = smn.StudentID
        LEFT JOIN Minor mn ON smn.MinorID = mn.MinorID
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

if (isset($_POST['declareMinor'])) {
    $MinorID   = $_POST['MinorID'] ?? '';
    $StudentID = (int)($_POST['studentID'] ?? 0);

    $mysqli->begin_transaction();

    // Count majors for this student
    $major_count_stmt = $mysqli->prepare("SELECT COUNT(*) AS cnt FROM StudentMajor WHERE StudentID = ?");
    $major_count_stmt->bind_param("i", $StudentID);
    $major_count_stmt->execute();
    $major_count = (int)($major_count_stmt->get_result()->fetch_assoc()['cnt'] ?? 0);
    $major_count_stmt->close();

    if ($major_count >= 2) {
        $mysqli->rollback();
        echo "<script>alert('Cannot declare a minor when 2 majors are declared.');</script>";
        header('Location: DeclareMinor.php');
        exit;
    }

    if ($MinorID === "") {
        // Remove minor
        $stmt = $mysqli->prepare("DELETE FROM StudentMinor WHERE StudentID = ?");
        $stmt->bind_param("i", $StudentID);
        $stmt->execute();
        $stmt->close();

        $stmt = $mysqli->prepare("UPDATE Student SET MinorID = NULL WHERE StudentID = ?");
        $stmt->bind_param("i", $StudentID);

        if ($stmt->execute()) {
            $mysqli->commit();
            echo "<script>alert('Minor Removed ✅');</script>";
        } else {
            $mysqli->rollback();
            echo "<script>alert('Could Not Remove Minor');</script>";
        }
        $stmt->close();

    } else {
        $MinorID = (int)$MinorID;

        // Declare/update minor
        $stmt = $mysqli->prepare("
            INSERT INTO StudentMinor (StudentID, MinorID, DateOfDeclaration)
            VALUES (?, ?, CURRENT_DATE())
            ON DUPLICATE KEY UPDATE
                MinorID = VALUES(MinorID),
                DateOfDeclaration = CURRENT_DATE()
        ");
        $stmt->bind_param("ii", $StudentID, $MinorID);
        $stmt->execute();
        $stmt->close();

        $stmt = $mysqli->prepare("UPDATE Student SET MinorID = ? WHERE StudentID = ?");
        $stmt->bind_param("ii", $MinorID, $StudentID);

        if ($stmt->execute()) {
            $mysqli->commit();
            echo "<script>alert('Minor Declared ✅');</script>";
        } else {
            $mysqli->rollback();
            echo "<script>alert('Could Not Declare Minor');</script>";
        }
        $stmt->close();
    }
}

$userRole = strtolower($_SESSION['role'] ?? '');
switch ($userRole) {
    case 'student':
        $dashboard = 'student_dashboard.php';
        $profile = 'student_profile.php';
        break;
    case 'faculty':
        $dashboard = 'faculty_dashboard.php';
        $profile = 'faculty_profile.php';
        break;
    case 'admin':
        // if you have update/view admin types:
        if (($_SESSION['admin_type'] ?? '') === 'update') {
            $dashboard = 'update_admin_dashboard.php';
            $profile = 'admin_profile.php';
        } else {
            $dashboard = 'view_admin_dashboard.php';
            $profile = 'admin_profile.php';
        }
        break;
    case 'statstaff':
        $dashboard = 'statstaff_dashboard.php';
        $profile = 'staff_profile.php';
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
  <title>Declare Minor • Northport University</title>
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
      <span class="pill">Declare Minor</span>
    </div>
    <div class="top-actions">
      <div class="search">
        <i class="search-icon" data-lucide="search"></i>
        <input type="text" placeholder="Search courses, people, anything…" />
      </div>
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
    <h2>Declare Minor: <?php echo htmlspecialchars($loadedStudent['StudentName'] . " - " . $loadedStudent['StudentID']); ?></h2>

    <form method="POST">

        <!-- STUDENT TABLE FIELDS -->
        <div class="section-card">
            <h3>Basic Information</h3>

            <div class="field-block">
                <label>Student ID (read only): </label>
                <input type="text" name="studentID" readonly value="<?php echo $loadedStudent['StudentID']; ?>">
            </div>

            <div class="field-block">
                <label for = "MinorID" required>Minor: </label>
                  <select name="MinorID" id ="MinorID">
                    <option value="">-- Undeclared --</option>
                  </select>
            </div>

            <div class="field-block">
                <label>Date of Declaration</label>
                <input type="text" name="DateOfDeclaration" readonly 
                  value="<?php echo htmlspecialchars(date('Y-m-d')); ?>">
            </div>

            <div style="margin-top: 20px;">
                <button type="submit" name="declareMinor">Save Changes</button>
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

    // Fetch Minors from get_Minors.php
    const currentMinor = "<?php echo $loadedStudent['MinorID']; ?>";

    fetch(`get_minors.php?current=${currentMinor}`)
    .then(response => response.json())
    .then(data => {
        const MinorSelect = document.getElementById('MinorID');

    data.forEach(Minor => {
        const opt = document.createElement('option');
        opt.value = Minor.id;
        opt.textContent = Minor.name;

        if (Minor.id == currentMinor) opt.selected = true;

        MinorSelect.appendChild(opt);
        });
    })
    .catch(err => console.error('Error loading Minors:', err));

</script>
</body>
</html>