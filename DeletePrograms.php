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

/* Programs are retired, not removed. Graduate students keep the link to
   their program and its course requirements stay attached, so a retired
   program can be brought back exactly as it was. Retired programs are
   simply not offered on the declaration screens. */
$statusMessage = '';
$statusOk = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $programID = $_POST['programID'] ?? '';
    $action    = $_POST['action'] ?? '';

    if (!in_array($action, ['retire', 'reactivate'], true)) {
        $statusMessage = 'Choose Retire or Reactivate.';
    } elseif (!ctype_digit((string)$programID)) {
        $statusMessage = 'Choose a program.';
    } else {
        $newStatus = $action === 'reactivate' ? 'ACTIVE' : 'INACTIVE';

        $lookup = $mysqli->prepare(
            "SELECT ProgramName, Status FROM Program WHERE ProgramID = ? LIMIT 1"
        );
        $lookup->bind_param("i", $programID);
        $lookup->execute();
        $target = $lookup->get_result()->fetch_assoc();
        $lookup->close();

        if (!$target) {
            $statusMessage = 'That program no longer exists.';
        } elseif ($target['Status'] === $newStatus) {
            $statusMessage = $target['ProgramName'] . ' is already '
                . ($newStatus === 'ACTIVE' ? 'active.' : 'retired.');
        } else {
            $mysqli->begin_transaction();

            $stmt = $mysqli->prepare("UPDATE Program SET Status = ? WHERE ProgramID = ?");
            $stmt->bind_param("si", $newStatus, $programID);

            if ($stmt->execute() && $stmt->affected_rows === 1) {
                $mysqli->commit();

                $held = $mysqli->prepare("SELECT COUNT(*) AS n FROM Graduate WHERE ProgramID = ?");
                $held->bind_param("i", $programID);
                $held->execute();
                $count = (int)($held->get_result()->fetch_assoc()['n'] ?? 0);
                $held->close();

                $statusMessage = $newStatus === 'ACTIVE'
                    ? $target['ProgramName'] . ' reactivated. Students can be enrolled in it again.'
                    : $target['ProgramName'] . " retired. It is no longer offered; $count enrolled "
                      . ($count === 1 ? 'student keeps' : 'students keep') . ' their place.';
                $statusOk = true;
            } else {
                $mysqli->rollback();
                $statusMessage = 'Could not change that program.';
            }
            $stmt->close();
        }
    }
}

$userRole = strtolower($_SESSION['role'] ?? '');
$adminType = $_SESSION['admin_type'] ?? '';

switch ($userRole) {

    case 'admin':
        if ($adminType === 'update') {
            $dashboard = 'update_admin_dashboard.php';
            $profile   = 'admin_profile.php';
        } else {
            $dashboard = 'login.html';
            $profile   = 'login.html';
        }
        break;

    default:
        $dashboard = 'login.html';
        $profile   = 'login.html';
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
<title>Program Status</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="./assets/css/tokens.css" />
  <link rel="stylesheet" href="./assets/css/base.css" />
  <link rel="stylesheet" href="./assets/css/layouts.css" />
  <link rel="stylesheet" href="./assets/css/components.css" />
</head>
<body>
  <header class="topbar">
    <div class="brand">
      <div class="logo"><i data-lucide="graduation-cap"></i></div>
      <h1>Northport University</h1>
      <span class="pill">Program Status</span>
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

    <main>

        <h3>Program Status</h3>
        <p class="muted">Retiring stops a program being offered to new students while keeping
           enrolled students and its course requirements, so it can be brought back unchanged.
           Retired programs are listed here, and only here.</p>

        <section>

          <div id = "delete-program">
            <?php if ($statusMessage !== ''): ?>
              <p class="status-note <?= $statusOk ? 'ok' : 'warn' ?>" role="status">
                <?= htmlspecialchars($statusMessage) ?>
              </p>
            <?php endif; ?>
            <form id = "DeleteProgram" method = "POST" action = "">
                      <label for="programID">Program: </label>
                             <select name="programID" id="programID" required>
                              <option value="">--SELECT--</option>
                                </select><br>

            <button type="submit" name="action" value="retire">Retire</button>
            <button type="submit" name="action" value="reactivate">Reactivate</button>
         </form>
      </div>
        </section>
</main>
<footer class="footer">© <span id="year"></span> Northport University • All rights reserved</footer>
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

     // Fetch programs from get_programs.php
    // all=1 so retired programs are listed too -- without them there
    // would be nothing to reactivate.
    fetch('get_programs.php?all=1')
    .then(response => response.json())
    .then(data => {
        const programSelect = document.getElementById('programID');

    data.forEach(program => {
        const opt = document.createElement('option');
        const retired = program.status === 'INACTIVE';
        opt.value = program.id;
        opt.textContent = retired ? `${program.name} — retired` : program.name;
        if (retired) opt.className = 'retired';
        programSelect.appendChild(opt);
        });
    })
    .catch(err => console.error('Error loading programs:', err));

    // Delete programs
    document.getElementById("DeleteProgram").addEventListener("submit", (e) => {
      console.log("Program form submitted ✅");
    });
</script>
</body>
</html>

