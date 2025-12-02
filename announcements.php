<?php
session_start();
require_once __DIR__ . '/config.php';

// --- 1. Security and Role Check ---
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    redirect('login.php');
}

$userId = $_SESSION['user_id'];
$userRole = strtolower($_SESSION['role']); // 'student', 'faculty', or 'admin'

$mysqli = get_db();
$mysqli->set_charset('utf8mb4');

$res = false; // Initialize result set

// --- 2. Dynamic SQL Query Based on Role ---

if ($userRole === 'student') {
    // STUDENT QUERY: Courses enrolled in + Admin: ALL/STUDENTS
    $sql = "
        -- 1. Course Announcements (Student Enrollment)
        SELECT a.Title, a.Message, a.DatePosted, c.CourseName, 'COURSE' AS SourceType, a.DatePosted AS SortDate
        FROM CourseAnnouncements a
        JOIN CourseSection cs ON a.CRN = cs.CRN
        JOIN Course c ON cs.CourseID = c.CourseID
        JOIN StudentEnrollment se ON se.CRN = cs.CRN
        WHERE se.StudentID = ?

        UNION ALL

        -- 2. Admin Announcements (Targeted at ALL or STUDENTS)
        SELECT aa.Title, aa.Message, aa.DatePosted, 'SYSTEM' AS CourseName, 'ADMIN' AS SourceType, aa.DatePosted AS SortDate
        FROM AdminAnnouncements aa
        WHERE aa.TargetGroup = 'ALL' OR aa.TargetGroup = 'STUDENTS'

        ORDER BY SortDate DESC
    ";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('i', $userId); // Bind student ID

} elseif ($userRole === 'faculty') {
    // FACULTY QUERY: Courses they teach + Admin: ALL/FACULTY
    // Requires a join to FacultyCourseAssignments to filter course announcements
    $sql = "
        -- 1. Course Announcements (Courses Taught)
        SELECT a.Title, a.Message, a.DatePosted, c.CourseName, 'COURSE' AS SourceType, a.DatePosted AS SortDate
        FROM CourseAnnouncements a
        JOIN CourseSection cs ON a.CRN = cs.CRN
        JOIN Course c ON cs.CourseID = c.CourseID
        JOIN FacultyHistory fca ON fca.CRN = cs.CRN
        WHERE fca.FacultyID = ?

        UNION ALL

        -- 2. Admin Announcements (Targeted at ALL or FACULTY)
        SELECT aa.Title, aa.Message, aa.DatePosted, 'SYSTEM' AS CourseName, 'ADMIN' AS SourceType, aa.DatePosted AS SortDate
        FROM AdminAnnouncements aa
        WHERE aa.TargetGroup = 'ALL' OR aa.TargetGroup = 'FACULTY'

        ORDER BY SortDate DESC
    ";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('i', $userId); // Bind faculty ID

} elseif ($userRole === 'admin') {
    // ADMIN QUERY: Admin announcements targeted at ALL or ADMINS
    // For simplicity, we won't show ALL course announcements unless specified.
    $sql = "
        -- 1. Admin Announcements (Targeted at ALL or ADMINS)
        SELECT aa.Title, aa.Message, aa.DatePosted, 'SYSTEM' AS CourseName, 'ADMIN' AS SourceType, aa.DatePosted AS SortDate
        FROM AdminAnnouncements aa
        WHERE aa.TargetGroup IN ('ALL', 'ADMINS')

        ORDER BY SortDate DESC
    ";
    $stmt = $mysqli->prepare($sql);
    // No bind_param needed for this simple admin-only view

} else {
    // Fallback for unknown/unauthorized role that somehow got past the initial check
    // This is defensive programming.
    $stmt = null; 
}

// --- 3. Execute Query and Fetch Results ---
if (isset($stmt)) {
    // Execute if a prepared statement was successfully created
    if ($userRole === 'student' || $userRole === 'faculty') {
        // Execute with bound parameter for student/faculty
        $stmt->execute();
    } else {
        // Execute without bound parameter for admin (in this specific case)
        $stmt->execute();
    }
    
    $res = $stmt->get_result();
    $stmt->close();
}

// Determine dashboard link for the "Back" button
$dashboardLink = 'dashboard.php'; // Default fallback
if ($userRole === 'student') {
    $dashboardLink = 'student_dashboard.php';
} elseif ($userRole === 'faculty') {
    $dashboardLink = 'faculty_dashboard.php';
} elseif ($userRole === 'admin') {
    // ADMIN ROLE: Check for specific admin permission levels
    if ($adminPermission === 'UpdateAdmin') {
        $dashboardLink = 'update_admin_dashboard.php';
    } elseif ($adminPermission === 'ViewAdmin') {
        $dashboardLink = 'view_admin_dashboard.php';
    } else {
        $dashboardLink = 'login.html';
    }
}
?>

<!DOCTYPE html>
<html lang='en'>
<head>
  <meta charset='UTF-8'>
  <title>All Announcements</title>
  <link rel='stylesheet' href='styles.css'>
</head>
<body class='page'>
  <div class='wrap'>
    <h1>All Announcements</h1>
    
    <?php if ($res && $res->num_rows > 0): ?>
      <?php while ($row = $res->fetch_assoc()): ?>
        <div class='announcement'>
          <?php 
          $sourceClass = strtolower($row['SourceType']); 
          $sourceLabel = $row['SourceType'] === 'COURSE' ? 'Course' : 'System';
          ?>

          <div class='announcement-header <?= $sourceClass ?>'>
            <h3><?= htmlspecialchars($row['Title']) ?> 
                — 
                <strong><?= htmlspecialchars($row['CourseName']) ?></strong>
            </h3>
          </div>
          
          <p class='announcement-message'><?= nl2br(htmlspecialchars($row['Message'])) ?></p>
          
          <small class='announcement-footer'>
            Posted <?= htmlspecialchars($row['DatePosted']) ?> 
            (Source: <?= $sourceLabel ?>)
          </small>
        </div>
      <?php endwhile; ?>
    <?php else: ?>
      <p>No announcements available.</p>
    <?php endif; ?>
    
    <a href='<?= $dashboardLink ?>' class='btn outline'>← Back to Dashboard</a>
  </div>
  
  <footer class="footer">© <span id="year"></span> Northport University</footer>
  <script>
  // Minimal JS for year and initials fallback
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
