<?php
require_once __DIR__ . '/config.php';
header('Content-Type: application/json');

$mysqli = get_db();
$mysqli->set_charset('utf8mb4');

$result = $mysqli->query("SELECT c.CourseID, c.CourseName, c.Credits, c.DeptID, d.DeptName, c.CourseType FROM Course c JOIN Department d ON c.DeptID = d.DeptID ORDER BY CourseID ASC");
$courses = [];

while ($row = $result->fetch_assoc()) {
    $courses[] = [
        'courseID'   => $row['CourseID'],
        'courseName'   => $row['CourseName'],
        'deptID'   => $row['DeptID'],
        'deptName'   => $row['DeptName'],
        'credits'   => $row['Credits'],
        'level'   => $row['CourseType'],
    ];
}

echo json_encode($courses);
?>