<?php
session_start();
require_once __DIR__ . '/config.php';

// Ensure only logged-in students or update-admins can access
if (
    !isset($_SESSION['user_id']) ||
    (
        ($_SESSION['role'] ?? '') !== 'student' &&
        !(($_SESSION['role'] ?? '') === 'admin' && ($_SESSION['admin_type'] ?? '') === 'update')
    )
) {
    header('Location: login.php');
    exit;
}

$mysqli = get_db();
$mysqli->set_charset('utf8mb4');

$userId  = $_SESSION['user_id'];
$crn     = $_POST['crn']     ?? '';
$semester = $_POST['semester'] ?? '';
$dept     = $_POST['dept']     ?? '';

if (empty($crn) || empty($semester)) {
    $_SESSION['error_message'] = "Invalid request. Missing CRN or semester.";
    header('Location: Add_Drop_courses.php');
    exit;
}

try {
    // Start transaction
    $mysqli->begin_transaction();

    // Verify the student is actually enrolled
    $check = $mysqli->prepare("
        SELECT 1 FROM StudentEnrollment 
        WHERE StudentID = ? AND CRN = ? AND SemesterID = ?
    ");
    $check->bind_param('iis', $userId, $crn, $semester);
    $check->execute();
    $check->store_result();

    if ($check->num_rows === 0) {
        $check->close();
        $mysqli->rollback();
        $_SESSION['error_message'] = "You are not enrolled in this course.";
        header("Location: Add_Drop_courses.php?Semester=" . urlencode($semester) . "&dept=" . urlencode($dept));
        exit;
    }
    $check->close();

    // Drop only if currently enrolled
    $drop = $mysqli->prepare("
        UPDATE StudentEnrollment 
        SET Status = 'DROPPED'
        WHERE StudentID = ? 
          AND CRN = ? 
          AND SemesterID = ? 
          AND Status IN ('ENROLLED','IN-PROGRESS')
    ");
    $drop->bind_param('iis', $userId, $crn, $semester);
    $drop->execute();
    
    // CHECK if a row was actually changed before giving back the seat!
    if ($drop->affected_rows > 0) {
        $drop->close();

        // Only increment seats if someone was actually enrolled
        $update = $mysqli->prepare("
            UPDATE CourseSection
            SET AvailableSeats = AvailableSeats + 1
            WHERE CRN = ? 
        ");
        $update->bind_param('i', $crn);
        $update->execute();
        $update->close();
        
        $_SESSION['success_message'] = "Successfully dropped course CRN $crn.";
    } else {
        // If 0 rows affected, they were likely already dropped
        $drop->close();
        $_SESSION['error_message'] = "Course was already dropped or could not be processed.";
    }

    $mysqli->commit();
    
    $_SESSION['success_message'] = "Successfully dropped course CRN $crn.";
} catch (Throwable $e) {
    $mysqli->rollback();
    $_SESSION['error_message'] = "Drop failed: " . $e->getMessage();
}

// Redirect back to Add/Drop view
$redirectUrl = "Add_Drop_courses.php";
$q = [];
if (!empty($semester)) $q[] = "Semester=" . urlencode($semester);
if (!empty($dept))     $q[] = "dept=" . urlencode($dept);
if ($q) $redirectUrl .= '?' . implode('&', $q);

header("Location: $redirectUrl");
exit;
?>