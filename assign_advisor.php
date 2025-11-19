<?php
session_start();
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['user_id']) || 
  ($_SESSION['role'] ?? '') !== 'admin' ||
($_SESSION['admin_type'] ?? '') !== 'update') {
    redirect(PROJECT_ROOT . "/login.html");
}

$userId = $_SESSION['user_id'];

$mysqli = get_db();
$mysqli->set_charset('utf8mb4');

$sql = "SELECT u.UserID, u.FirstName, u.LastName, u.Email, u.UserType, u.Status, u.DOB, a.SecurityType, a.AdminID
        FROM Users u JOIN Admin a ON u.UserID = a.AdminID WHERE UserID = ? LIMIT 1";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$res = $stmt->get_result();
$admin = $res->fetch_assoc();
$stmt->close();

$student_sql = "SELECT CONCAT(su.FirstName, ' ', su.LastName) AS StudentName, s.StudentID
        FROM Student s LEFT JOIN Advisor a ON s.StudentID = a.StudentID
        JOIN Users su ON s.StudentID = su.UserID";
$student_stmt = $mysqli->prepare($student_sql);
$student_stmt->execute();
$student_res = $student_stmt->get_result();
$student = $student_res->fetch_assoc();
$student_stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $FacultyID = $_POST['facultyID'] ?? '';
    $StudentID = $_POST['studentID'] ?? '';
    $DOA = date('Y-m-d');
    $Status = 'ACTIVE';
    $AssignedBy = $userId;

$mysqli->begin_transaction();

  $sql = "INSERT INTO Advisor (FacultyID, StudentID, DOA, Status, AssignedBy) VALUES (?, ?, CURRENT_DATE(), ?, ?)";
  $stmt = $mysqli->prepare($sql);
  $stmt->bind_param("iisi", $FacultyID, $StudentID, $Status, $AssignedBy);
        
  if ($stmt->execute()) {
    echo "alert('Advisor Assigned ✅');";
  } else {
    echo "alert('Could not assign advisor');";
        }
  
$mysqli->commit();
}

?>

<!doctype html>
<html lang="en" data-theme="light">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Admin Dashboard • Northport University</title>
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
      <span class="pill">Admin Portal</span>
    </div>
    <div class="top-actions">
      <div class="search">
        <i class="search-icon" data-lucide="search"></i>
        <input type="text" placeholder="Search courses, people, anything…" />
      </div>
      <button class="icon-btn" aria-label="Notifications"><i data-lucide="bell"></i></button>
      <button id="themeToggle" class="icon-btn" aria-label="Toggle theme"><i data-lucide="moon"></i></button>
      <div class="divider"></div>
      <div class="user">
        <img class="avatar" src="https://i.pravatar.cc/64?img=20" alt="avatar" />
        <div class="user-meta">
          <div class="name"><?php echo htmlspecialchars($admin['FirstName'] . ' ' . $admin['LastName']); ?></div>
          <div class="sub"><?php echo htmlspecialchars($admin['SecurityType']); ?></div>
        </div>
        <div class="header-left">
          <div class="dropdown">
            <button>☰ Menu</button>
            <div class="dropdown-content">
              <a href="admin_profile.php">Profile</a>
              <a href="createDirectory.php">Create Directory</a>
              <a href="viewDirectory.php">View Directory</a>
              <a href="logout.php">Logout</a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </header>

  <div>
    <form name = "assignStudent" method = "POST" action = "">
        <label for = "studentID" required>
        <select name = "studentID" id = "studentID">
            <option value="">-- Select Student --</option>
            <?php while ($row = $student_res->fetch_assoc()): ?>
                <option value="<?= $row['StudentID'] ?>">
                    <?= htmlspecialchars($row['StudentName']) ?>
                </option>
            <?php endwhile; ?>
        </select>
    </form>

</body>
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

</script>
</html>