<?php
session_start();
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'faculty') {
    redirect('login.php');
}

$facultyId = $_SESSION['user_id'];
$sectionId = intval($_POST['course_section'] ?? 0);
$title = trim($_POST['title'] ?? '');
$message = trim($_POST['message'] ?? '');

if ($sectionId === 0 || $title === '' || $message === '') {
    die("Missing required fields.");
}

$mysqli = get_db();
$mysqli->set_charset('utf8mb4');

// Insert announcement
$stmt = $mysqli->prepare("
    INSERT INTO CourseAnnouncements (CRN, FacultyID, Title, Message)
    VALUES (?, ?, ?, ?)
");
$stmt->bind_param('iiss', $sectionId, $facultyId, $title, $message);
$stmt->execute();
$stmt->close();

echo "<p>Announcement posted successfully! <a href='faculty_dashboard.php'>Return to Dashboard</a></p>";
?>