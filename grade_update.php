<?php
session_start();
require_once __DIR__ . '/config.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["ok" => false, "error" => "Invalid request"]);
    exit;
}

$mysqli = get_db();
$mysqli->set_charset('utf8mb4');

$studentId = $_POST['studentID'] ?? '';
$crn       = $_POST['crn'] ?? '';
$courseId  = $_POST['courseID'] ?? '';
$semester  = $_POST['semesterID'] ?? '';
$grade     = $_POST['grade'] ?? '';

$semSql = "
    SELECT s.EndDate
    FROM CourseSection cs
    JOIN Semester s ON cs.SemesterID = s.SemesterID
    WHERE cs.CRN = ?
    LIMIT 1
";
$semStmt = $mysqli->prepare($semSql);
$semStmt->bind_param('i', $crn);
$semStmt->execute();
$semRes = $semStmt->get_result();
$semesterRow = $semRes->fetch_assoc();
$semStmt->close();

if (!$semesterRow) {
    echo json_encode([
        'ok' => false,
        'message' => 'Cannot find semester for this course.'
    ]);
    exit;
}

$endDate   = new DateTimeImmutable($semesterRow['EndDate']);
$today     = new DateTimeImmutable('today');
$deadline  = $endDate->modify('+7 days');

if ($today < $endDate || $today > $deadline) {
    echo json_encode([
        'ok' => false,
        'message' => 'Grade entry for this course is closed. It is only allowed from '
                     . $endDate->format('Y-m-d') . ' to ' . $deadline->format('Y-m-d') . '.'
    ]);
    exit;
}

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