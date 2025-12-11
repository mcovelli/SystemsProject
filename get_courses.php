<?php
require_once __DIR__ . '/config.php';
header('Content-Type: application/json');

$mysqli = get_db();
$mysqli->set_charset('utf8mb4');

$sql = "
    SELECT 
        c.CourseID,
        c.CourseName,
        c.Credits,
        c.CourseType,
        c.DeptID,
        d.DeptName
    FROM Course c
    JOIN Department d ON c.DeptID = d.DeptID
    ORDER BY c.CourseID ASC
";

$result = $mysqli->query($sql);

if (!$result) {
    http_response_code(500);
    echo json_encode(['error' => $mysqli->error]);
    exit;
}

$courses = [];
while ($row = $result->fetch_assoc()) {
    $courses[] = [
        'courseID'   => $row['CourseID'],
        'courseName' => $row['CourseName'],
        'deptID'     => $row['DeptID'],
        'deptName'   => $row['DeptName'],
        'credits'    => $row['Credits'],
        'level'      => $row['CourseType'],
    ];
}

echo json_encode($courses);