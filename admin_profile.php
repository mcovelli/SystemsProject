<?php
// Admin profile page: shows a Admin member's information.
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/config.php';

// Only allow logged‑in admin members
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    redirect('login.php');
}

$adminId = $_SESSION['user_id'];

$mysqli = get_db();
$mysqli->set_charset('utf8mb4');

// Fetch user info
$u_stmt = $mysqli->prepare("SELECT UserID, FirstName, LastName, Email, UserType, Status, DOB, HouseNumber, Street, City, State, ZIP, PhoneNumber
        FROM Users WHERE UserID = ? LIMIT 1");
$u_stmt->bind_param('i', $adminId);
$u_stmt->execute();
$user = $u_stmt->get_result()->fetch_assoc();
$u_stmt->close();

$initials = substr($user['FirstName'], 0, 1) . substr($user['LastName'], 0, 1);

if (!$user) {
    echo "<p>Admin member not found.</p>";
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
    default:
        $dashboard = 'login.html'; // fallback
}

?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Admin Profile • Northport University</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="./profilestyles.css">
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
      <div class="crumb"><a href="<?= htmlspecialchars($dashboard) ?>" aria-label="Back to Dashboard">← Back to Dashboard</a></div>
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
        <div class="name" id="adminName"><?php echo htmlspecialchars($user['FirstName'] . ' ' . $user['LastName']); ?></div>
        <div class="chips">
          <span class="chip" id="research1">N/A</span>
          <span class="chip" id="research2">N/A</span>
        </div>
        <div class="btn-row">
          <button class="btn primary" href="mailto:<?php echo htmlspecialchars($user['Email']); ?>">Email</button>
          <button class="btn primary" id="editProfileBtn" onclick="openPopup()">Edit Profile</button>
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
            <div class="label"></div>
            <div id="bio"></div>
          </div>
          <div class="section">
          <h2>Links</h2>
          <div class="links" id="links">
            <a href="verify_identity.php">Reset Password</a>
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