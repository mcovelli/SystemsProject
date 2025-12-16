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

$userId = $_SESSION['user_id'];

$mysqli = get_db();
$mysqli->set_charset('utf8mb4');

$loadedStudent = null;
$studentHolds = [];

if (isset($_POST['searchStudent'])) {

    $searchId = trim($_POST['searchID']);

    $stmt = $mysqli->prepare("
        SELECT 
            s.StudentID,
            CONCAT(u.FirstName, ' ', u.LastName) AS StudentName
        FROM Student s
        JOIN Users u ON s.StudentID = u.UserID
        WHERE s.StudentID = ?
        LIMIT 1
    ");
    $stmt->bind_param("s", $searchId);
    $stmt->execute();
    $loadedStudent = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($loadedStudent) {
        $h_stmt = $mysqli->prepare("
            SELECT HoldID 
            FROM StudentHold 
            WHERE StudentID = ?
        ");
        $h_stmt->bind_param("s", $loadedStudent['StudentID']);
        $h_stmt->execute();
        $studentHolds = array_column($h_stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'HoldID');
        $h_stmt->close();
    }
}

// Fetch all available hold
$hold_stmt = $mysqli->prepare("SELECT HoldID, HoldType FROM Hold ORDER BY HoldType ASC");
$hold_stmt->execute();
$holds = $hold_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$hold_stmt->close();

$userId = $_SESSION['user_id'];

$usersql = "SELECT UserID, FirstName, LastName, Email, UserType, Status, DOB
        FROM Users WHERE UserID = ? LIMIT 1";
$userstmt = $mysqli->prepare($usersql);
$userstmt->bind_param("i", $userId);
$userstmt->execute();
$userres = $userstmt->get_result();
$user = $userres->fetch_assoc();
$userstmt->close();

if (isset($_POST['placeHold'])) {
    $StudentID = $_POST['studentID'] ?? '';
    $selectedHolds = $_POST['holdIDs'] ?? [];
    $selectedHolds = array_slice($selectedHolds, 0, 4);

    $mysqli->begin_transaction();
    $ok = true;
    
    $stmt = $mysqli->prepare("SELECT HoldID FROM StudentHold WHERE StudentID = ?");
    $stmt->bind_param("i", $StudentID);
    $stmt->execute();
    $existingHolds = array_column(
        $stmt->get_result()->fetch_all(MYSQLI_ASSOC),
        'HoldID'
    );
    $stmt->close();

    $toDelete = array_diff($existingHolds, $selectedHolds);

    if (!empty($toDelete)) {
        $del = $mysqli->prepare("DELETE FROM StudentHold WHERE StudentID = ? AND HoldID = ?");
        foreach ($toDelete as $mid) {
            if (
                !$del->bind_param("ii", $StudentID, $mid) ||
                !$del->execute()
            ) {
                $ok = false;
            }
        }
        $del->close();
    }

    $toInsert = array_diff($selectedHolds, $existingHolds);

    if (!empty($toInsert)) {
        $ins = $mysqli->prepare("
            INSERT INTO StudentHold (StudentID, HoldID, DateOfHold)
            VALUES (?, ?, CURRENT_DATE())
        ");
        foreach ($toInsert as $mid) {
            if (
                !$ins->bind_param("ii", $StudentID, $mid) ||
                !$ins->execute()
            ) {
                $ok = false;
            }
        }
        $ins->close();
    }

    if ($ok) {
        $mysqli->commit();
        echo "<script>alert('Hold Placed on Account ✅');</script>";
    } else {
        $mysqli->rollback();
        echo "<script>alert('Could Not Place Hold on Account');</script>";
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
  <title>Place Hold • Northport University</title>
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
      <span class="pill">Place Hold on Account</span>
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
<section class="hero card">
    <h2>Search for Student</h2>

    <form method="POST">
        <label>Student</label>
        <input type="text" name="searchID" required placeholder="Enter StudentID...">
        <button type="submit" name="searchStudent">Search</button>
    </form>
</section>


<!-- IF Student LOADED, DISPLAY FORM -->
<?php if (!empty($loadedStudent)) : ?>

<section class="hero card" style="margin-top: 20px;">
    <h2>Place Hold: <?php echo htmlspecialchars($loadedStudent['StudentName'] . " - " . $loadedStudent['StudentID']); ?></h2>

    <form method="POST">

        <!-- STUDENT TABLE FIELDS -->
        <div class="section-card">
            <h3>Basic Information</h3>

            <div class="field-block">
                <label>Student ID (read only): </label>
                <input type="text" name="studentID" readonly value="<?php echo $loadedStudent['StudentID']; ?>">
            </div>

            <label>Select hold(s):</label><br>

            <?php foreach ($holds as $h): ?>
                <label>
                    <input type="checkbox" name="holdIDs[]" value="<?= $h['HoldID'] ?>"
                           <?= in_array($h['HoldID'], $studentHolds) ? 'checked' : '' ?>>
                    <?= htmlspecialchars($h['HoldType']) ?>
                </label><br>
            <?php endforeach; ?>

            <div style="margin-top: 20px;">
                <button type="submit" name="placeHold">Save Changes</button>
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

    // Fetch holds from get_holds.php
    fetch('get_holds.php')
  .then(response => response.json())
  .then(data => {
    const holdSelect = document.getElementById('holdID');
    const selectedHold = new URLSearchParams(window.location.search).get('holdID');

    data.forEach(hold => {
      const opt = document.createElement('option');
      opt.value = hold.id;
      opt.textContent = hold.type

      if (type.id == selectedHold) {
        opt.selectedHold = true;
      }

      holdSelect.appendChild(opt);

</script>
</body>
</html>