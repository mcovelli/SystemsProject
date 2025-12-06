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

$loadedProgram = null;

if (isset($_POST['searchPrograms'])) {
    $searchId = $_POST['searchID'];

    // Load Dept table
    $stmt = $mysqli->prepare("
        SELECT * 
        FROM Major
        WHERE MajorName LIKE CONCAT('%', ?, '%')
           OR MajorID   LIKE CONCAT('%', ?, '%')
    ");
    $stmt->bind_param("si", $searchId, $searchId);
    $stmt->execute();
    $loadedProgram = $stmt->get_result()->fetch_assoc();
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

if (isset($_POST['UpdateProgram'])) {

    $ID = $_POST['ID'];
    $DeptId = $_POST['deptID'];
    $name = $_POST['name'];
    $creditsNeeded = $_POST['creditsNeeded'];

    $stmt = $mysqli->prepare("
        UPDATE Major 
        SET DeptID = ?, MajorName = ?, CreditsNeeded = ?
        WHERE MajorID = ?
    ");
    $stmt->bind_param("isii", $DeptId, $name, $creditsNeeded, $ID);

    if ($stmt->execute()) {
    $_SESSION['update_success'] = true;
    header("Location: UpdateMajors.php");
    exit;
}

    $_SESSION['update_success'] = true;
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
<title>Update Major</title>
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
      <span class="pill">Update Major</span>
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
                  <h1 class="card-title">Update Major</h1>
                </div>
            </div>

            <section class="hero card">
                <h2>Search Major</h2>

                <form method="POST" style="margin-top: 10px;">
                    <label>Search by Name or ID</label>
                    <input type="text" name="searchID" required placeholder="ex. MATH or Mathematics">
                    <button type="submit" name="searchPrograms">Search</button>
                </form>
            </section>

            <!-- IF Major LOADED, DISPLAY FORM -->
            <?php if (!empty($loadedProgram)) : ?>
                <div id = "update-section-department">
                    <form id = "UpdateProgram" method = "POST" action = "">
                      <label for = "ID" readonly>Major ID (read only):</label>
                            <input type = "text" id = "ID" name="ID" readonly placeholder="ex. 1"><br>

                        <div class = "field-block">
                            <label for="name">Program Name: </label>
                            <input type = "text" id="name" name="name" placeholder="ex. Mathematics"><br>
                        </div>

                        <label for ="deptID">Dept Name: </label>
                            <select name="deptID" id="deptID">
                                <option value="">-- Select Department --</option>
                            </select><br>

                        <label for="creditsNeeded">Credits Needed: </label>
                            <input type = "number" id="creditsNeeded" name="creditsNeeded" placeholder="96"><br>

                        <div style="margin-top: 20px;">
                            <button type="submit" name="UpdateProgram">Save Changes</button>
                        </div>
                    </form>
                </div>
        </section>
        <?php endif; ?>
    </main>

    <footer class="footer">© <span id="year"></span> Northport University</footer>
    <div id="toast" class="toast hidden"></div>

<?php if (!empty($loadedProgram)): ?>
    <script>
    document.addEventListener("DOMContentLoaded", () => {
        document.getElementById("ID").value = "<?php echo $loadedProgram['MajorID'] ?? ''; ?>";
        document.getElementById("deptID").value = "<?php echo $loadedProgram['DeptID'] ?? ''; ?>";
        document.getElementById("name").value = "<?php echo $loadedProgram['MajorName'] ?? ''; ?>";
        document.getElementById("creditsNeeded").value = "<?php echo $loadedProgram['CreditsNeeded'] ?? ''; ?>";
    });
    </script>
    <?php endif; ?>


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

    // Fetch dept from get_departments.php
    const currentDept = "<?php echo $loadedProgram['DeptID']; ?>";

    fetch(`get_departments.php?current=${currentDept}`)
    .then(response => response.json())
    .then(data => {
        const deptSelect = document.getElementById('deptID');

    data.forEach(dept => {
        const opt = document.createElement('option');
        opt.value = dept.id;
        opt.textContent = dept.name;

        if (dept.id == currentDept) opt.selected = true;

        deptSelect.appendChild(opt);
        });
    })
    .catch(err => console.error('Error loading departments:', err));

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

// Show success toast if update was successful
<?php if (!empty($_SESSION['update_success'])): ?>
    showToast("✅ Major updated successfully!");
    <?php unset($_SESSION['update_success']); ?>
<?php endif; ?>
</script>
</body>
</html>