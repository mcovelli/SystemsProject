<?php
session_start();
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

if (!isset($_GET['courseID'])) {
    echo json_encode([]);
    exit;
}

$courseID = $_GET['courseID'];

$mysqli = get_db();
$mysqli->set_charset('utf8mb4');

$sql = "
    SELECT 
        CourseID,
        PrerequisiteCourseID,
        MinGradeRequired
    FROM CoursePrerequisite
    WHERE CourseID = ?
";

$stmt = $mysqli->prepare($sql);
$stmt->bind_param('s', $courseID);
$stmt->execute();
$res = $stmt->get_result();

$rows = [];
while ($row = $res->fetch_assoc()) {
    $rows[] = [
        'courseID'            => $row['CourseID'],
        'prereqCourseID'      => $row['PrerequisiteCourseID'],
        'minGradeRequired'    => $row['MinGradeRequired'],
    ];
}

$stmt->close();

echo json_encode($rows);