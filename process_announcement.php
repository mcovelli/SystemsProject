<?php
session_start();
require_once __DIR__ . '/config.php';

// --- 1. Security and Role Check ---
$userId = $_SESSION['user_id'] ?? null;
$userRole = strtolower($_SESSION['role'] ?? '');

// Ensure a user is logged in and has the necessary posting permissions
if (!$userId || ($userRole !== 'faculty' && $userRole !== 'admin')) {
    redirect('login.php');
}

$mysqli = get_db();
$mysqli->set_charset('utf8mb4');

// --- 2. Sanitize and Validate Input ---
$title = trim($_POST['title'] ?? '');
$message = trim($_POST['message'] ?? '');
$datePosted = date('Y-m-d H:i:s');

if (empty($title) || empty($message)) {
    redirect('send_announcement.php?error=empty_fields');
}

// Prepare variables for the database operation
$stmt = null;
$isSuccess = false;

// --- 3. Process Submission based on Role and Target ---

if ($userRole === 'faculty' && isset($_POST['target_crn'])) {
    // ===================================
    // FACULTY: Insert into CourseAnnouncements
    // ===================================
    $crn = $_POST['target_crn'];

    // SECURITY CHECK: Verify the faculty member is actually assigned to this CRN
    $checkStmt = $mysqli->prepare("
        SELECT CRN FROM CourseSection WHERE CRN = ? AND FacultyID = ?
    ");
    // CRN and FacultyID are both unsigned integers
    $checkStmt->bind_param('ii', $crn, $userId); 
    $checkStmt->execute();
    
    if ($checkStmt->get_result()->num_rows === 0) {
        $checkStmt->close();
        // Unauthorized access attempt (CRN not assigned to this faculty)
        redirect('send_announcement.php?error=unauthorized_crn');
    }
    $checkStmt->close();

    // INSERT ANNOUNCEMENT
    $stmt = $mysqli->prepare("
        INSERT INTO CourseAnnouncements (CRN, FacultyID, Title, Message, DatePosted)
        VALUES (?, ?, ?, ?, ?)
    ");
    // Bind types: CRN(i), FacultyID(i), Title(s), Message(s), DatePosted(s)
    $stmt->bind_param('iisss', $crn, $userId, $title, $message, $datePosted);

} elseif ($userRole === 'admin' && isset($_POST['target_group'])) {
    // ===================================
    // ADMIN: Insert into AdminAnnouncements
    // ===================================
    $targetGroup = $_POST['target_group'];

    // Validation: Ensure the target group is one of the valid ENUM values
    $validTargets = ['ALL', 'STUDENTS', 'FACULTY', 'ADMINS'];
    if (!in_array($targetGroup, $validTargets)) {
         redirect('send_announcement.php?error=invalid_target');
    }

    // INSERT ANNOUNCEMENT
    $stmt = $mysqli->prepare("
        INSERT INTO AdminAnnouncements (TargetGroup, AdminID, Title, Message, DatePosted)
        VALUES (?, ?, ?, ?, ?)
    ");
    // Bind types: TargetGroup(s), AdminID(i), Title(s), Message(s), DatePosted(s)
    $stmt->bind_param('sisss', $targetGroup, $userId, $title, $message, $datePosted);

} else {
    // Neither faculty CRN nor admin target group was successfully submitted
    redirect('send_announcement.php?error=missing_target');
}

// --- 4. Execute and Final Redirection ---

if (isset($stmt)) {
    if ($stmt->execute()) {
        $isSuccess = true;
    } else {
        // Log the actual database error for debugging
        error_log("DB Error posting announcement: " . $stmt->error);
    }
    $stmt->close();
}

if ($isSuccess) {
    redirect('send_announcement.php?success=1');
} else {
    redirect('send_announcement.php?error=db_fail');
}
?>