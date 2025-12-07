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
        $studentType === 'grad' ||
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
          g.ProgramID
        FROM Student s
        JOIN Graduate g ON s.StudentID = g.StudentID
        JOIN Users u ON s.StudentID = u.UserID
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
          g.ProgramID
        FROM Users u
        JOIN Student s ON u.UserID = s.StudentID
        LEFT JOIN Graduate g ON s.StudentID = g.StudentID
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

if (isset($_POST['declareProgram'])) {
    $ProgramID = $_POST['programID'] ?? '';
    $StudentID = $_POST['studentID'] ?? '';
    $DateOfDeclaration = date('Y-m-d');

    $ok = true;
    $mysqli->begin_transaction();

    if ($ProgramID === "") {

        // Remove grad program
        $stmt = $mysqli->prepare("UPDATE Graduate SET ProgramID = NULL WHERE StudentID = ?");
        $stmt->bind_param("i", $StudentID);
        if (!$stmt->execute()) $ok = false;
        $stmt->close();

        // Remove major from Student table
        $stmt = $mysqli->prepare("UPDATE Student SET MajorID = NULL WHERE StudentID = ?");
        $stmt->bind_param("i", $StudentID);
        if (!$stmt->execute()) $ok = false;
        $stmt->close();

        if ($ok) {
            $mysqli->commit();
            echo "<script>alert('Program Removed ✅');</script>";
        } else {
            $mysqli->rollback();
            echo "<script>alert('Could Not Remove Program');</script>";
        }

        return;
    }

    // Update Graduate table
    $stmt = $mysqli->prepare("UPDATE Graduate SET ProgramID = ? WHERE StudentID = ?");
    $stmt->bind_param("ii", $ProgramID, $StudentID);
    if (!$stmt->execute()) $ok = false;
    $stmt->close();

    // Update Student table (MajorID mirrors ProgramID)
    $stmt = $mysqli->prepare("UPDATE Student SET MajorID = ? WHERE StudentID = ?");
    $stmt->bind_param("ii", $ProgramID, $StudentID);
    if (!$stmt->execute()) $ok = false;
    $stmt->close();

    $dept_stmt = $mysqli->prepare("SELECT DeptID FROM Program WHERE ProgramID = ? LIMIT 1");
    $dept_stmt->bind_param("i", $ProgramID);
    $dept_stmt->execute();
    $dept_res = $dept_stmt->get_result()->fetch_assoc();
    $dept_stmt->close();

    if (!empty($dept_res['DeptID'])) {
        $deptID = $dept_res['DeptID'];

        $g_stmt = $mysqli->prepare("UPDATE Graduate SET DeptID = ? WHERE StudentID = ?");
        $g_stmt->bind_param("ii", $deptID, $StudentID);
        if (!$g_stmt->execute()) $ok = false;
        $g_stmt->close();
    }

    if ($ok) {
        $mysqli->commit();
        echo "<script>alert('Program Declared ✅');</script>";
    } else {
        $mysqli->rollback();
        echo "<script>alert('Could Not Declare Program');</script>";
    }
}

?>

<!doctype html>
<html lang="en" data-theme="light">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Declare Program• Northport University</title>
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
      <span class="pill">Declare Program</span>
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
    <h2>Declare Program: <?php echo htmlspecialchars($loadedStudent['StudentName'] . " - " . $loadedStudent['StudentID']); ?></h2>

    <form method="POST">

        <!-- STUDENT TABLE FIELDS -->
        <div class="section-card">
            <h3>Basic Information</h3>

            <div class="field-block">
                <label>Student ID (read only): </label>
                <input type="text" name="studentID" readonly value="<?php echo $loadedStudent['StudentID']; ?>">
            </div>

            <div class="field-block">
                <label for = "programID" required>Program: </label>
                  <select name="programID" id ="programID">
                    <option value="">-- Undeclared --</option>
                  </select>
            </div>

            <div class="field-block">
                <label>Date of Declaration</label>
                <input type="text" name="DateOfDeclaration" readonly 
                  value="<?php echo htmlspecialchars(date('Y-m-d')); ?>">
            </div>

            <div style="margin-top: 20px;">
                <button type="submit" name="declareProgram">Save Changes</button>
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

    // Fetch Programs from get_Programs.php
    const currentProgram = "<?php echo $loadedStudent['ProgramID']; ?>";

    fetch(`get_programs.php?current=${currentProgram}`)
    .then(response => response.json())
    .then(data => {
        const programSelect = document.getElementById('programID');

    data.forEach(program => {
        const opt = document.createElement('option');
        opt.value = program.id;
        opt.textContent = program.name;

        if (program.id == currentProgram) opt.selected = true;

        programSelect.appendChild(opt);
        });
    })
    .catch(err => console.error('Error loading Programs:', err));

</script>
</body>
</html>