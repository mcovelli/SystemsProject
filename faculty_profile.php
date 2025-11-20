<?php
// Faculty profile page: shows a faculty member's information and courses.
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/config.php';

// Only allow logged‑in faculty members

// If admin or faculty is viewing another student
$role = strtolower($_SESSION['role'] ?? '');

// If not logged in
if (!$role) {
    redirect('login.php');
}

// ADMIN → viewing any faculty profile
if ($role === 'admin') {
    if (isset($_GET['facultyID'])) {
        $facultyId = intval($_GET['facultyID']);
    } else {
        redirect('faculty_dashboard.php'); // Or wherever you want admin to go
    }
}
// FACULTY → always view their own profile
elseif ($role === 'faculty') {
    $facultyId = $_SESSION['user_id'];
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
$u_stmt->bind_param('i', $facultyId);
$u_stmt->execute();
$user = $u_stmt->get_result()->fetch_assoc();
$u_stmt->close();

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
$courses = $courses_result->fetch_all(MYSQLI_ASSOC);
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

// Determine initials for avatar fallback
$initials = '';
if (!empty($user['FirstName']) || !empty($user['LastName'])) {
    $initials = strtoupper(substr($user['FirstName'], 0, 1) . substr($user['LastName'], 0, 1));
}
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Faculty Profile • Northport University</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
  <style>
    :root{
      --bg:#f7f8fb; --card:#ffffff; --text:#1f2937; --muted:#6b7280;
      --primary:#0b1d39; --accent:#4f46e5; --line:#e5e7eb;
      --radius:14px;
    }
    *{box-sizing:border-box}
    body{
      margin:0; font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;
      background:var(--bg); color:var(--text);
    }
    header{
      position:sticky; top:0; z-index:10;
      background:var(--card); border-bottom:1px solid var(--line);
    }
    .wrap{max-width:1100px; margin:0 auto; padding:18px 16px;}
    .topbar{display:flex; align-items:center; gap:12px; justify-content:space-between}
    .brand{display:flex; align-items:center; gap:10px; font-weight:700; color:var(--primary)}
    .brand .logo{width:36px; height:36px; border-radius:50%; background:var(--primary); display:grid; place-items:center; color:#fff; font-size:14px}
    .top-actions a{display:inline-flex; align-items:center; gap:8px; text-decoration:none; background:var(--primary); color:#fff; padding:10px 14px; border-radius:10px}
    main .grid{display:grid; grid-template-columns:320px 1fr; gap:20px; padding:24px 16px}
    @media (max-width:880px){ main .grid{grid-template-columns:1fr} }
    .card{
      background:var(--card); border:1px solid var(--line); border-radius:var(--radius);
      box-shadow:0 1px 2px rgba(0,0,0,.03); padding:18px;
    }
    .profile{
      display:flex; flex-direction:column; align-items:center; text-align:center; gap:12px
    }
    .avatar{
      width:120px; height:120px; border-radius:50%; object-fit:cover; border:3px solid var(--accent);
      background:#ddd;
    }
    .name{font-size:1.3rem; font-weight:700}
    .muted{color:var(--muted)}
    .chips{display:flex; flex-wrap:wrap; gap:8px; justify-content:center}
    .chip{padding:6px 10px; border-radius:999px; background:#f2f4f7; border:1px solid var(--line); font-size:.9rem}
    .btn-row{display:flex; gap:10px; flex-wrap:wrap; justify-content:center}
    .btn{border:1px solid var(--line); background:#fff; padding:10px 12px; border-radius:10px; cursor:pointer}
    .btn.primary{background:var(--accent); color:#fff; border-color:var(--accent)}
    .section{display:grid; gap:14px}
    .section h2{margin:0; font-size:1.05rem}
    .kv{display:grid; grid-template-columns:80px 1fr; gap:8px; padding:10px 0; border-bottom:1px dashed var(--line)}
    .kv:last-child{border-bottom:0}
    /* --- CONTACT INFO FIX --- */
    .section .kv div:last-child {
      word-break: break-word;         /* allow breaking long words or emails */
      overflow-wrap: anywhere;        /* force wrap for long strings */
      white-space: normal;            /* allow wrapping on small screens */
      max-width: 100%;                /* prevent overflow beyond card edge */
    }

    .section .kv {
      align-items: start;             /* aligns label and value top-aligned */
      gap: 12px;                      /* add breathing room */
    }

    .section .label {
      font-weight: 600;
      color: var(--muted);
      min-width: 100px;               /* keeps labels consistent width */
      word-wrap: normal;
    }

    #address, #email, #phone {
      display: block;
      line-height: 1.4;
    }

    @media (max-width: 600px) {
      .kv {
        grid-template-columns: 1fr;   /* stack label/value on mobile */
      }
      .section .label {
        min-width: auto;
      }
    }
    .label{color:var(--muted)}
    .two{display:grid; grid-template-columns:1fr 1fr; gap:14px}
    @media (max-width:620px){ .kv{grid-template-columns:1fr} .two{grid-template-columns:1fr} }
    .links a{display:inline-block; margin-right:10px; color:var(--primary); text-decoration:none}
    .links a:hover{text-decoration:underline}
    footer{padding:24px 16px; text-align:center; color:var(--muted)}

    /* Overlay background */
    .popup-overlay {
      position: fixed;
      inset: 0;
      background: rgba(0, 0, 0, 0.4);
      backdrop-filter: blur(4px);
      display: none;
      justify-content: center;
      align-items: center;
      z-index: 9999;
    }

    /* Card style popup */
    .popup-card {
      background: var(--panel, #fff);
      color: var(--text, #111);
      padding: 24px 28px;
      border-radius: 14px;
      width: 90%;
      max-width: 480px;
      box-shadow: 0 12px 28px rgba(0, 0, 0, 0.25);
      animation: slideUp 0.25s ease-out;
      position: relative;
    }

    /* Fade/slide animation */
    @keyframes slideUp {
      from { opacity: 0; transform: translateY(40px); }
      to { opacity: 1; transform: translateY(0); }
    }

    /* Close button */
    .popup-card .close-btn {
      position: absolute;
      top: 10px;
      right: 16px;
      font-size: 22px;
      color: var(--muted, #777);
      cursor: pointer;
      transition: color 0.2s ease;
    }
    .popup-card .close-btn:hover {
      color: var(--primary, #4f46e5);
    }

    /* Form layout */
    .popup-card .form-grid {
      display: grid;
      gap: 14px;
      margin-top: 20px;
    }
    .popup-card .form-grid label {
      display: flex;
      flex-direction: column;
      text-align: left;
      font-size: 14px;
      color: var(--muted, #666);
    }
    .popup-card .form-grid input,
    .popup-card .form-grid textarea {
      margin-top: 6px;
      padding: 8px 10px;
      border-radius: 8px;
      border: 1px solid var(--border, #ccc);
      background: var(--panel, #fff);
      color: var(--text, #111);
      font-size: 14px;
    }
    .popup-card .form-grid input:focus,
    .popup-card .form-grid textarea:focus {
      outline: 2px solid var(--primary, #4f46e5);
    }

  </style>
</head>
<body>
  <header>
    <div class="wrap topbar">
      <div class="brand">
        <div class="logo">NU</div>
        <div>Northport University</div>
      </div>
      <div class="top-actions">
        <a href="faculty_dashboard.php" title="Back to Dashboard">← Back to Dashboard</a>
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
        <div class="muted" id="facultyTitle"><?php echo htmlspecialchars($ranking); ?></div>
        <div class="chips">
          <span class="chip" id="research1">N/A</span>
          <span class="chip" id="research2">N/A</span>
        </div>
        <div class="btn-row">
          <a class="btn primary" href="mailto:<?php echo htmlspecialchars($user['Email']); ?>">Email</a>
          <a class="btn primary" id="editProfileBtn" onclick="openPopup()">Edit Profile</a>
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
            <a href="#">CV</a>
            <a href="#">Google Scholar</a>
            <a href="#">Department Page</a>
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