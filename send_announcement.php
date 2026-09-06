<?php
session_start();
require_once __DIR__ . '/config.php';

// Check for faculty or admin role
$userRole = strtolower($_SESSION['role'] ?? '');
if (!isset($_SESSION['user_id']) || ($userRole !== 'faculty' && $userRole !== 'admin')) {
    redirect('login.php');
}

$userId = $_SESSION['user_id'];
$mysqli = get_db();
$mysqli->set_charset('utf8mb4');

$sections = [];

// --- 1. Data Fetching based on Role ---
if ($userRole === 'faculty') {
    // Fetch sections taught by this faculty (original logic)
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
}


?>
<!doctype html>
<html lang="en">
<?php $nu_title = 'Announcements'; require __DIR__ . '/partials/head.php'; ?>
<?php $nu_search = false; $nu_bell = false; require __DIR__ . '/partials/header.php'; ?>
<body>
    <h1>Send Announcement</h1>

    <main id="main" tabindex="-1" class="container">
        <section class="left">
            <div class="card">
                <form method="post" action="process_announcement.php" class="form-grid">
                    
                    <?php if ($userRole === 'faculty'): ?>
                        
                        <h2>Course Announcement (To Students)</h2>
                        <label>
                            <select name="target_crn" required>
                                <option value="">Select Course Section</option>
                                <?php foreach ($sections as $sec): ?>
                                    <option value="<?= htmlspecialchars($sec['CRN']) ?>">
                                        <?= htmlspecialchars($sec['CourseName'] . ' (' . $sec['SemesterName'] . ' ' . $sec['Year'] . ')') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="help-text">Announcements will be visible only to students enrolled in this section.</small>
                        </label>

                    <?php elseif ($userRole === 'admin'): ?>
                        
                        <h2>System Announcement (To User Group)</h2>
                        <label>
                            <select name="target_group" required>
                                <option value="">Select Target Group</option>
                                <option value="ALL">All Users (System-wide)</option>
                                <option value="STUDENTS">All Students</option>
                                <option value="FACULTY">All Faculty</option>
                                <option value="ADMINS">All Admins</option>
                            </select>
                            <small class="help-text">Announcements will be visible to the selected user role(s).</small>
                        </label>
                        
                    <?php endif; ?>
                    <br>
                    <label>
                        <input type="text" name="title" required maxlength="255" placeholder='Subject'>
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

    <?php require __DIR__ . '/partials/footer.php'; ?>


  <script>
    // Create icons on load
    lucide.createIcons();
    // Set footer year
    document.getElementById('year').textContent = new Date().getFullYear();
  </script>
</body>
</html>