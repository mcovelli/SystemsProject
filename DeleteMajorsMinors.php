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

/* Majors and minors are retired, not removed. Students keep their
   declarations and the requirements stay attached, so a retired
   programme can be brought back exactly as it was. Retired entries are
   simply not offered on the declaration screens. */
$statusMessage = '';
$statusOk = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ID           = $_POST['id'] ?? '';
    $majorOrMinor = $_POST['majorOrMinor'] ?? '';
    $action       = $_POST['action'] ?? '';

    $isMajor   = $majorOrMinor === 'major';
    $table     = $isMajor ? 'Major'   : 'Minor';
    $keyColumn = $isMajor ? 'MajorID' : 'MinorID';
    $nameColumn= $isMajor ? 'MajorName' : 'MinorName';

    if (!in_array($majorOrMinor, ['major', 'minor'], true)) {
        $statusMessage = 'Choose Major or Minor.';
    } elseif (!in_array($action, ['retire', 'reactivate'], true)) {
        $statusMessage = 'Choose Retire or Reactivate.';
    } elseif (!ctype_digit((string)$ID)) {
        $statusMessage = 'Choose a ' . $majorOrMinor . '.';
    } else {
        $newStatus = $action === 'reactivate' ? 'ACTIVE' : 'INACTIVE';

        $lookup = $mysqli->prepare(
            "SELECT `$nameColumn` AS name, Status FROM `$table` WHERE `$keyColumn` = ? LIMIT 1"
        );
        $lookup->bind_param("i", $ID);
        $lookup->execute();
        $target = $lookup->get_result()->fetch_assoc();
        $lookup->close();

        if (!$target) {
            $statusMessage = "That $majorOrMinor no longer exists.";
        } elseif ($target['Status'] === $newStatus) {
            $statusMessage = $target['name'] . ' is already '
                . ($newStatus === 'ACTIVE' ? 'active.' : 'retired.');
        } else {
            $mysqli->begin_transaction();

            $stmt = $mysqli->prepare("UPDATE `$table` SET Status = ? WHERE `$keyColumn` = ?");
            $stmt->bind_param("si", $newStatus, $ID);

            if ($stmt->execute() && $stmt->affected_rows === 1) {
                $mysqli->commit();

                $held = $mysqli->prepare(
                    $isMajor
                        ? "SELECT COUNT(*) AS n FROM StudentMajor WHERE MajorID = ?"
                        : "SELECT COUNT(*) AS n FROM StudentMinor WHERE MinorID = ?"
                );
                $held->bind_param("i", $ID);
                $held->execute();
                $count = (int)($held->get_result()->fetch_assoc()['n'] ?? 0);
                $held->close();

                $statusMessage = $newStatus === 'ACTIVE'
                    ? $target['name'] . ' reactivated. Students can declare it again.'
                    : $target['name'] . " retired. It is no longer offered; $count existing "
                      . ($count === 1 ? 'declaration is' : 'declarations are') . ' kept.';
                $statusOk = true;
            } else {
                $mysqli->rollback();
                $statusMessage = "Could not change that $majorOrMinor.";
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
<title>Major and Minor Status</title>
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
      <span class="pill">Major and Minor Status</span>
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
                  <h1 class="card-title">Major and Minor Status</h1>
                  <p class="muted">Retiring stops a programme being offered to new students while
                     keeping existing declarations and its course requirements, so it can be
                     brought back unchanged. Retired entries are listed here, and only here.</p>
                </div>
            </div>
                <div id = "delete-section-majorminor">
                    <?php if ($statusMessage !== ''): ?>
                      <p class="status-note <?= $statusOk ? 'ok' : 'warn' ?>" role="status">
                        <?= htmlspecialchars($statusMessage) ?>
                      </p>
                    <?php endif; ?>
                    <form id = "DeleteMajorMinor" method = "POST" action = "">
                        <label for="majorOrMinor">Select Major/Minor: </label>
                            <select id="majorOrMinor" name="majorOrMinor" required>
                                <option value="">-- Select --</option>
                                <option value="major">Major</option>
                                <option value="minor">Minor</option>
                            </select>

                            <br>

                            <label id="typeLabel" for="id">Name:</label>
                            <select id="id" name="id" required>
                                <option value="">-- Select --</option>
                            </select>
                        <button type="submit" name="action" value="retire">Retire</button>
                        <button type="submit" name="action" value="reactivate">Reactivate</button>
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

    document.getElementById("majorOrMinor").addEventListener("change", function () {
        const type = this.value; 
        const select = document.getElementById("id");
        const label = document.getElementById("typeLabel");

        label.textContent = type === "major" ? "Major:" :
                            type === "minor" ? "Minor:" :
                            "Name:";

        select.innerHTML = '<option value="">-- Select --</option>';

        if (!type) return;

        // all=1 so retired entries are listed too -- without them there
        // would be nothing to reactivate.
        const url = (type === "major" ? "get_majors.php" : "get_minors.php") + "?all=1";

        fetch(url)
            .then(res => res.json())
            .then(data => {
                data.forEach(item => {
                    const opt = document.createElement("option");
                    const retired = item.status === "INACTIVE";
                    opt.value = item.id;
                    opt.textContent = retired ? `${item.name} — retired` : item.name;
                    if (retired) opt.className = "retired";
                    select.appendChild(opt);
                });
            })
            .catch(err => console.error(`Error loading ${type}s:`, err));
    });
    

    // Fetch departments from get_departments.php

    document.getElementById("DeleteMajorMinor").addEventListener("submit", (e) => {
    console.log("Form submitted");
});
</script>
</body>
</html>
