<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/config.php';

$mysqli = get_db();
$mysqli->set_charset('utf8mb4');

$userID = $_SESSION['user_id'];

$role = strtolower($_SESSION['role'] ?? '');

// Determine back dashboard
$userRole = strtolower($_SESSION['role'] ?? '');
switch ($userRole) {
    case 'student':  $dashboard = 'student_dashboard.php'; break;
    case 'faculty':  $dashboard = 'faculty_dashboard.php'; break;
    case 'admin':
        $dashboard = ($_SESSION['admin_type'] ?? '') === 'update'
            ? 'update_admin_dashboard.php'
            : 'view_admin_dashboard.php';
        break;
    default: $dashboard = 'login.php';
}

// If not logged in
if (!$role) {
    redirect('login.php');
}

if ($role === 'admin') {

    // Admin with ?facultyId=  → view that faculty's profile
    if (isset($_GET['facultyID'])) {
        $facultyId = intval($_GET['facultyID']);
    }
    else {
        redirect('login.php');
    }
}

elseif ($role === 'faculty') {

    // Faculty can only access their own profile
    $facultyId = $_SESSION['user_id'];
}

else {
    redirect($dashboard);
}

$mysqli = get_db();
$mysqli->set_charset('utf8mb4');

// Fetch user info
$u_stmt = $mysqli->prepare("SELECT UserID, FirstName, LastName, Email, UserType, Status, DOB, HouseNumber, Street, City, State, ZIP, PhoneNumber
        FROM Users WHERE UserID = ? LIMIT 1");
$u_stmt->bind_param('i', $facultyId);
$u_stmt->execute();
$user = $u_stmt->get_result()->fetch_assoc();
$u_stmt->close();

$initials = substr($user['FirstName'], 0, 1) . substr($user['LastName'], 0, 1);

if (!$user) {
    echo "<p>Faculty member not found.</p>";
    exit;
}

// Fetch faculty details: ranking and office
$fac_stmt = $mysqli->prepare("SELECT OfficeID, Ranking FROM Faculty WHERE FacultyID = ? LIMIT 1");
$fac_stmt->bind_param('i', $facultyId);
$fac_stmt->execute();
$fac = $fac_stmt->get_result()->fetch_assoc();
$fac_stmt->close();

$office = $fac['OfficeID'] ?? 'N/A';
$ranking = $fac['Ranking'] ?? 'Faculty';

// Fetch courses taught by faculty (current semester or all)
$courses_sql = "
  SELECT cs.CRN, c.CourseName, GROUP_CONCAT(DISTINCT d.DayOfWeek ORDER BY d.DayID SEPARATOR '/') AS Days,
            MIN(DATE_FORMAT(p.StartTime, '%l:%i %p')) AS StartTime,
            MAX(DATE_FORMAT(p.EndTime, '%l:%i %p'))   AS EndTime, cs.RoomID
  FROM CourseSection cs
  JOIN Course c ON cs.CourseID = c.CourseID
  JOIN TimeSlot ts ON cs.TimeSlotID = ts.TS_ID
  JOIN TimeSlotDay tsd ON ts.TS_ID = tsd.TS_ID
  JOIN Day d ON tsd.DayID = d.DayID
  JOIN TimeSlotPeriod tsp ON ts.TS_ID = tsp.TS_ID
  JOIN Period p ON tsp.PeriodID = p.PeriodID
  WHERE cs.FacultyID = ?
  ORDER BY p.StartTime
";
$courses_stmt = $mysqli->prepare($courses_sql);
$courses_stmt->bind_param('i', $facultyId);
$courses_stmt->execute();
$courses_result = $courses_stmt->get_result();
$course = $courses_result->fetch_all(MYSQLI_ASSOC);
$courses_stmt->close();

// Fetch advisees
$advisees_sql = "
  SELECT u.FirstName, u.LastName, m.MajorName
  FROM Advisor a
  JOIN Users u ON a.StudentID = u.UserID
  LEFT JOIN StudentMajor sm ON a.StudentID = sm.StudentID
  LEFT JOIN Major m ON sm.MajorID = m.MajorID
  WHERE a.FacultyID = ?
";
$adv_stmt = $mysqli->prepare($advisees_sql);
$adv_stmt->bind_param('i', $facultyId);
$adv_stmt->execute();
$advisees_result = $adv_stmt->get_result();
$advisees = $advisees_result->fetch_all(MYSQLI_ASSOC);
$adv_stmt->close();

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
  <?php if ($userRole === 'faculty'): ?>
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
<?php endif; ?>

  <main>
    <div class="wrap grid">
      <!-- LEFT: Profile Card -->
      <aside class="card profile">
        <div class="avatar" aria-hidden="true"><span id="initials"><?php echo $initials ?: 'NU'; ?></span></div>
        <div class="name" id="facultyName"><?php echo htmlspecialchars($user['FirstName'] . ' ' . $user['LastName']); ?></div>
        <div class="muted" id="facultyTitle"><?php echo htmlspecialchars($ranking); ?></div>
        <div class="chips">
          <span class="chip" id="research1">N/A</span>
          <span class="chip" id="research2">N/A</span>
        </div>
        <div class="btn-row">
          <a class="btn primary" href="mailto:<?php echo htmlspecialchars($user['Email']); ?>">Email</a>
          <?php if ($userRole === 'faculty'): ?>
          <a class="btn primary" id="editProfileBtn" onclick="openPopup()">Edit Profile</a>
        <?php endif; ?>
          <a class="btn primary" href="#office-hours">Office Hours</a>
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
          <div class="kv">
            <div class="label">Biography</div>
            <div id="bio">Dedicated faculty member at Northport University. Please contact via email for office hours.</div>
          </div>
          <div class="kv">
            <div class="label">Roles</div>
            <div id="roles"><?php echo htmlspecialchars($ranking); ?></div>
          </div>
        </div>

        <div class="section" id="office-hours">
          <h2>Office Hours</h2>
          <div class="two">
            <div class="kv"><div class="label">Monday</div><div id="ohMon">By appointment</div></div>
            <div class="kv"><div class="label">Wednesday</div><div id="ohWed">By appointment</div></div>
            <div class="kv"><div class="label">Friday</div><div id="ohFri">By appointment</div></div>
            <div class="kv"><div class="label">Modality</div><div id="ohMode">In‑person/virtual</div></div>
          </div>
        </div>

        <div class="section">
          <h2>Courses Taught</h2>
          <table aria-label="Courses taught">
            <thead>
              <tr>
                <th>CRN</th><th>Course</th><th>Days</th><th>Time</th><th>Location</th>
              </tr>
            </thead>
            <tbody id="courseTable">
              <?php if (empty($courses)): ?>
                <tr><td colspan="5">No courses assigned.</td></tr>
              <?php else: ?>
                <?php foreach ($courses as $c): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($c['CRN']); ?></td>
                    <td><?php echo htmlspecialchars($c['CourseName']); ?></td>
                    <td><?php echo htmlspecialchars($c['Days']); ?></td>
                    <td><?php echo htmlspecialchars($c['StartTime'] . ' – ' . $c['EndTime']); ?></td>
                    <td><?php echo htmlspecialchars($c['RoomID']); ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <div class="section">
          <h2>Advising</h2>
          <table aria-label="Advisees">
            <thead><tr><th>Name</th><th>Program</th></tr></thead>
            <tbody id="advisees">
              <?php if (empty($advisees)): ?>
                <tr><td colspan="2">No advisees assigned.</td></tr>
              <?php else: ?>
                <?php foreach ($advisees as $adv): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($adv['FirstName'] . ' ' . $adv['LastName']); ?></td>
                    <td><?php echo htmlspecialchars($adv['MajorName'] ?? 'Program'); ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <div class="section">
          <h2>Links</h2>
          <div class="links" id="links">
            <a href="#"></a>
          </div>
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