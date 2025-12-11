<?php
session_start();
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

if (!isset($_GET['courseID'])) {
    echo json_encode([]);
    exit;
}

$mysqli = get_db();
$mysqli->set_charset('utf8mb4');

$courseID = $_GET['courseID'] ?? '';

$sql = "
    SELECT 
        cp.PrerequisiteCourseID AS prereqCourseID,
        cp.MinGradeRequired     AS minGradeRequired,
        c.CourseName
    FROM CoursePrerequisite cp
    JOIN Course c 
      ON cp.PrerequisiteCourseID = c.CourseID
    WHERE cp.CourseID = ?
";

$stmt = $mysqli->prepare($sql);
$stmt->bind_param('s', $courseID);
$stmt->execute();
$res = $stmt->get_result();

$rows = [];
while ($row = $res->fetch_assoc()) {
    $rows[] = $row;
}

$stmt->close();

echo json_encode($rows);