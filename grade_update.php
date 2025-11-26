<?php
session_start();
require_once __DIR__ . '/config.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["ok" => false, "error" => "Invalid request"]);
    exit;
}

$studentId = $_POST['studentID'] ?? '';
$crn       = $_POST['crn'] ?? '';
$courseId  = $_POST['courseID'] ?? '';
$semester  = $_POST['semesterID'] ?? '';
$grade     = $_POST['grade'] ?? '';

$mysqli = get_db();
$mysqli->set_charset('utf8mb4');

$mysqli->begin_transaction();

// Update StudentEnrollment
$update = $mysqli->prepare(
    "UPDATE StudentEnrollment 
     SET Grade = ?, Status = 'COMPLETED'
     WHERE StudentID=? AND CRN=?"
);
$update->bind_param("sii", $grade, $studentId, $crn);
$update->execute();

// Insert StudentHistory
$insert = $mysqli->prepare(
    "INSERT INTO StudentHistory (StudentID, CRN, SemesterID, Grade, CourseID)
     VALUES (?, ?, ?, ?, ?)"
);
$insert->bind_param("iisss", $studentId, $crn, $semester, $grade, $courseId);
$insert->execute();

$mysqli->commit();

echo json_encode(["ok" => true]);