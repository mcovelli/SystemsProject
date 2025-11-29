<?php
session_start();
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'statstaff') {
    redirect(PROJECT_ROOT . "/login.html");
}

$userId = $_SESSION['user_id'];

$mysqli = get_db();
$mysqli->set_charset('utf8mb4');

$sql = "SELECT UserID, FirstName, LastName, Email, UserType, Status, DOB
        FROM Users WHERE UserID = ? LIMIT 1";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$res = $stmt->get_result();
$statstaff = $res->fetch_assoc();
$stmt->close();

if (!$statstaff) {
    echo "<p>Profile not found for your account.</p>";
    exit;
}
?>
<!doctype html>
<html lang="en" data-theme="light">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Northport University — Stat Staff Dashboard</title>
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
      <span class="pill">Staff Portal</span>
      <h3>Welcome, <?php echo htmlspecialchars(
    $statstaff['FirstName'] . ' ' . $statstaff['LastName']); ?></h3>
      <h1> <?php echo htmlspecialchars(' (' . $statstaff['UserType'] . ' - ');?></h1>
    </div>
    <div class="top-actions">
      <div class="search">
        <i class="search-icon" data-lucide="search"></i>
        <input type="text" placeholder="Search courses, people, anything…" />
      </div>
      <button class="icon-btn" aria-label="Notifications" a href = announcements.php><i data-lucide="bell"></i></button>
      <button id="themeToggle" class="icon-btn" aria-label="Toggle theme"><i data-lucide="moon"></i></button>
      <div class="divider"></div>
      <div class="user">
        <img class="avatar" src="https://i.pravatar.cc/64?img=12" alt="avatar" />
        <div class="user-meta">
          <div class="name"><?php echo htmlspecialchars(
            $statstaff['FirstName'] . ' ' . $statstaff['LastName']); ?></div>
          <div class="sub"></div>
        </div>
        <div class="header-left">
        <div class="menu">
      <button>☰ Menu</button>
        <div class="menu-content">
        <a href="statstaff_profile.php">Profile</a>
        <a href="messages.php">Messages</a>
        <a href="viewDirectory.php">View Directory</a>
        <a href="logout.php">Logout</a>
      </div>
  </div>
    </div>
  </header>

  <main class="container">

    <section class="left">
      <div class="stats">
        <div class="card stat">
          <div class="card-head">
            <div class="muted"></div>
            <i data-lucide="line-chart"></i>
          </div>
          <div class="stat-value"></div>
          <div class="sub muted"></div>
        </div>

        <div class="card stat">
          <div class="card-head">
            <div class="sub muted"></div>
            <i data-lucide="clipboard-list"></i>
          </div>
          <div class="stat-value"></div>
          <div class="sub muted"></div>
          <div class="sub muted"></div>

        <div class="card stat">
          <div class="card-head">
            <div class="muted">Unread Messages</div>
            <i data-lucide="inbox"></i>
          </div>
          <div class="stat-value">2</div>
          <div class="sub muted">Inbox • Faculty, Bursar</div>
        </div>

        <div class="card stat">
          <div class="card-head">
            <div class="muted"></div>
            <i data-lucide="triangle-alert"></i>
          </div>
          <div class="stat-value"></div>
          <div class="sub muted"></div>
        </div>
      </div>

      <div class="grid-two">
        <div class="card">
          <div class="card-title"></div>
          <div class="row between small muted">
            <span></span>
            <div class="progress">
              <div class="bar"></div>
            </div>
          </div>
          <div class="badges">
            <span class="badge"></span>
          </div>
        </div>

        <div class="card">
          <div class="card-title"></div>
          <div class="chart-wrap">
            <canvas id="gpaChart" height="200"></canvas>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-head between">
          <div class="card-title"></div>
          <div class="row gap">
            <button class="btn outline"><i data-lucide="calendar-days"></i> Open Calendar</button>
            <button class="btn">Add Event</button>
          </div>
        </div>
        <div class="table-wrap">
          <form method="get" class="">
            <label for=""></label>
            <select name="" id="">
              <option value=""></option>
            </select>
          </form>
          <table>
            <thead>
              <tr>
                <th></th>
              </tr>
            </thead>
            <tbody>
                <tr>
                  <td></td>
                </tr>
            </tbody>
          </table>
        </div>
      </div>
    </section>
    <br>
    <aside class="right">
      <div class="card">
        <div class="card-title">Quick Actions</div>
        <div class="quick-grid" id="studentQuickLinks"></div>
      </div>

      <div class="tabs">
        <div class="tabs-list">
          <button class="tab active" data-tab="tasks">To‑Dos</button>
          <button class="tab" data-tab="announcements">Announcements</button>
        </div>
        <div class="tab-panels">
          <div class="tab-panel active" id="panel-tasks">
            <div class="card">
              <div class="card-title"></div>
              <div id="studentTasksList" class="vstack gap"></div>
              <div class="pt-8">
                <button class="btn"><i data-lucide="clipboard-list"></i> View All Tasks</button>
              </div>
            </div>
          </div>
          <div class="tab-panel" id="panel-announcements">
            <div class="card">
              <div class="card-title">Campus Updates</div>
              <div id="studentAnnList" class="vstack gap"></div>
              <div class="pt-8">
                <button class="btn outline">View All Announcements</button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="grid-two sm-one">
        <div class="card">
          <div class="card-title">Recent Messages</div>
              <?php
              $list = $mysqli->prepare("
                  SELECT 
                      c.CopyID,
                      c.MessageID,
                      m.Title,
                      m.Message,
                      m.DatePosted,
                      m.SenderEmail,
                      m.RecipientEmail
                  FROM MessageCopies c
                  JOIN Messages m ON m.MessageID = c.MessageID
                  WHERE c.OwnerEmail = ?
                    AND c.Folder = ?
                    AND c.IsDeleted = 0
                  ORDER BY m.DatePosted DESC
              ");
              $list->bind_param("ss", $userEmail, $folderSQL);
              $list->execute();
              $list_res = $list->get_result();

              if ($list_res->num_rows > 0):
              ?>
                <ul style="list-style:none; padding:0; margin:0;">
                  <?php while ($m = $list_res->fetch_assoc()): ?>
                    <li style="border-bottom:1px solid var(--line); padding:10px 0;">
                      <strong><?= htmlspecialchars($m['Title']) ?></strong>
                      <span style="color:var(--muted);"> — <?= htmlspecialchars($m['Email']) ?></span>
                      <div style="margin-top:4px;"><?= nl2br(htmlspecialchars($m['Message'])) ?></div>
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

        <div class="card">
          <div class="card-title"></div>
          <div class="row between small">
            <span></span>
            <strong></strong>
          </div>
          <div class="row between small muted">
            <span></span>
            <span></span>
          </div>
          <div class="row gap pt-8">
            <button class="btn"><i data-lucide="credit-card"></i></button>
            <button class="btn outline"></button>
          </div>
        </div>
      </div>
    </aside>
  </main>
  <footer class="footer">
    © <span id="year"></span> Northport University • All rights reserved • <a href="#" class="link">Privacy</a>
  </footer>
  </body>
  <script src = "https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
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

  