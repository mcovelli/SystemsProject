<?php
// Stat Staff profile page: shows a stat staff member's information
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/config.php';

$role = strtolower($_SESSION['role'] ?? '');

// If not logged in
if (!$role) {
    redirect('login.php');
}

// ADMIN → viewing any statstaff profile
if ($role === 'admin') {
    if (isset($_GET['statstaffID'])) {
        $staffId = intval($_GET['statstaffID']);
    } else {
        redirect('statstaff_profile.php');
    }
}
// Statstaff → always view their own profile
elseif ($role === 'statstaff') {
    $staffId = $_SESSION['user_id'];
}
// ANYONE ELSE → no access
else {
    redirect('login.php');
}

$mysqli = get_db();
$mysqli->set_charset('utf8mb4');

// Fetch user info
$u_stmt = $mysqli->prepare("SELECT UserID, FirstName, LastName, Email, UserType, Status, DOB, HouseNumber, Street, City, State, ZIP, PhoneNumber
        FROM Users WHERE UserID = ? LIMIT 1");
$u_stmt->bind_param('i', $staffId);
$u_stmt->execute();
$user = $u_stmt->get_result()->fetch_assoc();
$u_stmt->close();

$initials = substr($user['FirstName'], 0, 1) . substr($user['LastName'], 0, 1);

if (!$user) {
    echo "<p>Stat Staff member not found.</p>";
    exit;
}

$userRole = strtolower($_SESSION['role'] ?? '');
switch ($userRole) {
    case 'admin':
        // if you have update/view admin types:
        if (($_SESSION['admin_type'] ?? '') === 'update') {
            $dashboard = 'update_admin_dashboard.php';
        } else {
            $dashboard = 'view_admin_dashboard.php';
        }
        break;
    case 'statstaff':
        $dashboard = 'statstaff_dashboard.php';
        break;
    default:
        $dashboard = 'login.html'; // fallback
}


?>

<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Admin Profile • Northport University</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="./profilestyles.css">
</head>
<body>
  <header>
    <div class="wrap topbar">
      <div class="brand">
        <div class="logo">NU</div>
        <div>Northport University</div>
      </div>
      <div class="dropdown">
          <button>☰ Menu</button>
          <div class="dropdown-content">
            <a href="<?= htmlspecialchars($dashboard) ?>">Dashboard</a>
            <a href="verify_identity.php">Reset Password</a>
            <a href="logout.php">Logout</a>
          </div>
        </div>
    </div>
  </header>

  <!-- Edit Profile Popup -->
  <div id="editProfilePopup" class="popup-overlay">
    <div class="popup-card">
      <span class="close-btn" onclick="closePopup()">&times;</span>
      <h2>Edit Profile</h2>

      <form id="editProfileForm" method="post" action="update_profile.php" class="form-grid">
        <label>Phone Number
          <input type="text" name="phone" value="<?= htmlspecialchars($user['PhoneNumber'] ?? '') ?>" required>
        </label>

        <label>House Number
          <input type="text" name="house" value="<?= htmlspecialchars($user['HouseNumber'] ?? '') ?>">
        </label>

        <label>Street
          <input type="text" name="street" value="<?= htmlspecialchars($user['Street'] ?? '') ?>">
        </label>

        <label>City
          <input type="text" name="city" value="<?= htmlspecialchars($user['City'] ?? '') ?>">
        </label>

        <label>State
          <input type="text" name="state" value="<?= htmlspecialchars($user['State'] ?? '') ?>">
        </label>

        <label>ZIP
          <input type="text" name="zip" value="<?= htmlspecialchars($user['ZIP'] ?? '') ?>">
        </label>

        <label>Bio
          <textarea name="bio" rows="3"><?= htmlspecialchars($_SESSION['bio'] ?? '') ?></textarea>
        </label>

        <div class="btn-row">
          <button type="submit" class="btn primary">Save Changes</button>
          <button type="button" class="btn outline" onclick="closePopup()">Cancel</button>
        </div>
      </form>
    </div>
  </div>

  <main>
    <div class="wrap grid">
      <!-- LEFT: Profile Card -->
      <aside class="card profile">
        <div class="avatar" aria-hidden="true"><span id="initials"><?php echo $initials ?: 'NU'; ?></span></div>
        <div class="name" id="facultyName"><?php echo htmlspecialchars($user['FirstName'] . ' ' . $user['LastName']); ?></div>

        <div class="chips">
          <span class="chip" id="research1">N/A</span>
          <span class="chip" id="research2">N/A</span>
        </div>
        <div class="btn-row">
          <button class="btn primary" href="mailto:<?php echo htmlspecialchars($user['Email']); ?>">Email</button>
          <button class="btn primary" id="editProfileBtn" onclick="openPopup()">Edit Profile</button>
          <button class="btn primary" href="#office-hours">Office Hours</button>
        </div>
        <div class="section" style="width:100%; margin-top:10px">
          <h2>Contact</h2>

          <div class="kv">
            <div class="label">Email</div>
            <div id="email"><?= htmlspecialchars($user['Email']) ?></div>
          </div>

          <div class="kv">
            <div class="label">Phone</div>
            <div id="phone"><?= htmlspecialchars($user['PhoneNumber']) ?></div>
          </div>

          <div class="kv">
            <div class="label">Address</div>
            <div id="address">
              <?= htmlspecialchars($user['HouseNumber'] . ' ' . $user['Street']) ?><br>
              <?= htmlspecialchars($user['City'] . ', ' . $user['State'] . ' ' . $user['ZIP']) ?>
            </div>
          </div>
        </div>
      </aside>

      <!-- RIGHT: Details -->
      <section class="card">
        <div class="section">
          <h2>About</h2>
          <div class="section">
            <h2>Links</h2>
            <div class="links" id="links">
              <a href="messages.php">Messages</a>
              <a href="verify_identity.php">Reset Password</a>
            </div>

      </section>
    </div>
  </main>

  <footer>© <span id="year"></span> Northport University</footer>

  <script>
    // Minimal JS for year and initials fallback
    document.getElementById('year').textContent = new Date().getFullYear();

  function openPopup() {
    const popup = document.getElementById('editProfilePopup');
    popup.style.display = 'flex';
  }

  function closePopup() {
    const popup = document.getElementById('editProfilePopup');
    popup.style.display = 'none';
  }

  // Close when clicking outside
  window.addEventListener('click', (event) => {
    const popup = document.getElementById('editProfilePopup');
    if (event.target === popup) closePopup();
  });
  </script>
</body>
</html>