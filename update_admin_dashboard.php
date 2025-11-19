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

if (!$admin) {
    echo "<p>Profile not found for your account.</p>";
    exit;
}

// Placeholder quick links, tasks, announcements and messages
$quickLinks = [
    ['label' => 'Profile',      'href' => 'admin_profile.php',    'icon' => 'user'],
    ['label' => 'Messages',     'href' => 'messages.php',                      'icon' => 'mail'],
    ['label' => 'View Directory', 'href' => 'viewDirectory.php',       'icon' => 'file-text'],
    ['label' => 'Create Directory',      'href' => 'createDirectory.php',           'icon' => 'book'],
    ['label' => 'Assign Advisor',      'href' => 'assign_advisor.php',           'icon' => 'pencil']
];
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

  <main class="container">
    <section class="left">
      <div class="stats">
        <div class="card stat">
          <div class="card-head">
            <div class="muted">Number of Courses (All)</div>
            <i data-lucide="pencil"></i>
          </div>
          <div class="stat-value"></div>
        </div>

        <div class="card stat">
          <div class="card-head">
            <div class="muted">Admin ID</div>
            <i data-lucide="id-card"></i>
          </div>
          <div class="stat-value"><?php echo htmlspecialchars($admin['AdminID']); ?></div>
        </div>

        <div class="grid-two sm-one">
        <div class="card">
          <div class="card-title">Recent Messages</div>
              <?php
              $list = $mysqli->prepare("
                  SELECT 
                      c.CopyID,
                      m.Title, 
                      LEFT(m.Message, 120) AS PreviewText,
                      m.DatePosted,
                      m.SenderEmail
                  FROM MessageCopies c
                  JOIN Messages m ON m.MessageID = c.MessageID
                  WHERE c.OwnerEmail = ?
                    AND c.Folder = 'INBOX'
                    AND c.IsDeleted = 0
                  ORDER BY m.DatePosted DESC
                  LIMIT 5
              ");
              $list->bind_param("s", $admin['Email']);
              $list->execute();
              $list_res = $list->get_result();

              if ($list_res->num_rows > 0):
              ?>
                <ul style="list-style:none; padding:0; margin:0;">
                  <?php while ($m = $list_res->fetch_assoc()): ?>
                    <li style="border-bottom:1px solid var(--line); padding:10px 0;">
                      <strong><?= htmlspecialchars($m['Title']) ?></strong>
                      <span style="color:var(--muted);"> — <?= htmlspecialchars($m['SenderEmail']) ?></span>
                      <div style="margin-top:4px;"><?= nl2br(htmlspecialchars($m['PreviewText'])) ?>…</div>
                      <small style="color:var(--muted);">Posted <?= htmlspecialchars($m['DatePosted']) ?></small>
                    </li>
                  <?php endwhile; ?>
                </ul>
                <div style="text-align:right; margin-top:10px;">
                  <a href="messages.php" class="btn outline">View All Messages →</a>
                </div>
              <?php else: ?>
                <p>No recent Messages.</p>
              <?php endif;
              $list->close();
              ?>
            </div>
        </div>

        <div class="card stat">
          <div class="card-head">
            <div class="muted">Security Level</div>
            <i data-lucide="user"></i>
          </div>
          <div class="stat-value"><?php echo htmlspecialchars($admin['SecurityType']); ?></div>
        </div>

        <div class="card stat">
          <div class="card-head">
            <div class="muted">Date of Birth</div>
            <i data-lucide="calendar"></i>
          </div>
          <div class="stat-value"><?php echo $admin['DOB'] ? date('m/d/Y', strtotime($admin['DOB'])) : 'N/A'; ?></div>
        </div>

        <div class="card stat">
          <div class="card-head">
            <div class="muted">Office Location</div>
            <i data-lucide="map-pin"></i>
          </div>
          <div class="stat-value"></div>
        </div>
      </div>

    </section>

    <aside class="right">
      <div class="card">
        <div class="card-title">Quick Actions</div>
        <div class="quick-grid" id="adminQuickLinks"></div>
      </div>
    </aside>
  </main>

<footer>© <span id="year"></span> Northport University • All rights reserved</footer>

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

    // Tab switching
    document.querySelectorAll('.tabs .tab').forEach(tab => {
      tab.addEventListener('click', () => {
        document.querySelectorAll('.tabs .tab').forEach(t => t.classList.remove('active'));
        tab.classList.add('active');
        const target = tab.getAttribute('data-tab');
        document.querySelectorAll('.tab-panel').forEach(panel => {
          panel.classList.toggle('active', panel.id === 'panel-' + target);
        });
      });
    });

    // Insert quick links
    const quickLinks = <?php echo json_encode($quickLinks); ?>;
    const qlContainer = document.getElementById('adminQuickLinks');
    quickLinks.forEach(link => {
      const div = document.createElement('div');
      div.className = 'ql';
      div.addEventListener('click', () => {
        if (link.href) window.location.href = link.href;
      });
      const icon = document.createElement('i');
      icon.setAttribute('data-lucide', link.icon);
      const span = document.createElement('span');
      span.textContent = link.label;
      div.appendChild(icon);
      div.appendChild(span);
      qlContainer.appendChild(div);
    });
    lucide.createIcons();

    // Insert tasks
   /* const tasks = <?php echo json_encode($tasks); ?>;
    const taskList = document.getElementById('adminTasksList');
    tasks.forEach(task => {
      const item = document.createElement('div');
      item.className = 'row between small';
      item.innerHTML = '<span>' + task.title + '</span><span class="muted">' + task.due + '</span>';
      taskList.appendChild(item);
    });

    // Announcements
    const announcements = <?php echo json_encode($announcements); ?>;
    const annList = document.getElementById('adminAnnList');
    announcements.forEach(ann => {
      const item = document.createElement('div');
      item.className = 'vstack';
      const title = document.createElement('strong');
      title.textContent = ann.title;
      const body = document.createElement('span');
      body.className = 'muted';
      body.textContent = ann.body;
      const date = document.createElement('span');
      date.className = 'muted small';
      date.textContent = ann.date;
      item.appendChild(title);
      item.appendChild(body);
      item.appendChild(date);
      annList.appendChild(item);
    });

    // Messages
    const messages = <?php echo json_encode($messages); ?>;
    const msgList = document.getElementById('adminMsgList');
    messages.forEach(msg => {
      const item = document.createElement('div');
      item.className = 'row between small';
      item.innerHTML = '<span><strong>' + msg.from + ':</strong> ' + msg.subject + '</span><span class="muted">' + msg.time + '</span>';
      msgList.appendChild(item);
    });*/
  </script>
</body>
</html>