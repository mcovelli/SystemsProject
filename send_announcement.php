<?php
session_start();
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'faculty' && ($_SESSION['role'] ?? '') !== 'admin' ) {
    redirect('login.php');
}

$userId = $_SESSION['user_id'];

$mysqli = get_db();
$mysqli->set_charset('utf8mb4');

// Fetch sections taught by this faculty
$stmt = $mysqli->prepare("
    SELECT cs.CRN, c.CourseName, s.SemesterName, s.Year
    FROM CourseSection cs
    JOIN Course c ON cs.CourseID = c.CourseID
    JOIN Semester s ON cs.SemesterID = s.SemesterID
    WHERE cs.FacultyID = ?
    ORDER BY s.Year DESC, s.SemesterName DESC
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$sections = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!doctype html>
<html lang="en" data-theme="light">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Announcements • Northport University</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="./styles.css" />
</head>
<header class="topbar">
    <div class="brand">
      <div class="logo">NU</div>
      <h1>Northport University</h1>
    </div>
    <div class="top-actions">
      <div class="search" style="width: min(360px, 40vw)">
        <i class="search-icon" data-lucide="search"></i>
        <input id="q" type="text" placeholder="Search code, title, instructor…" />
      </div>
      <button class="btn outline" id="themeToggle" title="Toggle theme">🌙</button>
      <div class="top-actions">
        <a href="faculty_dashboard.php" title="Back to Dashboard">← Back to Dashboard</a>
      </div>
  </header>
<body>
  <h1>Send Announcement</h1>

<main class="container">
  <section class="left">
    <div class = "card">
      <form method="post" action="process_announcement.php" class="form-grid">
        <label>
          <select name="course_section" required>
            <option value="">Select Course Section</option>
            <?php foreach ($sections as $sec): ?>
              <option value="<?= htmlspecialchars($sec['CRN']) ?>">
                <?= htmlspecialchars($sec['CourseName'] . ' (' . $sec['SemesterName'] . ' ' . $sec['Year'] . ')') ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>
        <br>

        <label>
          <input type="text" name="title" required maxlength="255" placeholder = 'Subject'>
        </label>
        <br>

        <label>
          <textarea name="message" rows="5" required placeholder="Message"></textarea>
        </label>
        <br>

        <button type="submit" class="btn primary">Post Announcement</button>
      </form>
    </div>
  </section>
</main>

    <footer class="footer">
    © <span id="year"></span> Northport University • All rights reserved • <a href="#" class="link">Privacy</a>
  </footer>

  <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>

  <script>
    // Create icons on load
    lucide.createIcons();
    // Set footer year
    document.getElementById('year').textContent = new Date().getFullYear();
    // Theme toggle
    const themeToggle = document.getElementById('themeToggle');
    themeToggle.addEventListener('click', () => {
      const root = document.documentElement;
      const current = root.getAttribute('data-theme') || 'light';
      root.setAttribute('data-theme', current === 'light' ? 'dark' : 'light');
      themeToggle.querySelector('i').setAttribute('data-lucide', current === 'light' ? 'sun' : 'moon');
      lucide.createIcons();
    });
  </script>
</body>
</html>