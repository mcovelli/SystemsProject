<?php
require_once __DIR__ . '/config.php';
header('Content-Type: application/json');

$mysqli = get_db();
$mysqli->set_charset('utf8mb4');

$result = $mysqli->query("SELECT CourseID FROM Course ORDER BY CourseID ASC");
$courses = [];

while ($row = $result->fetch_assoc()) {
    $courses[] = [
        'id'   => $row['CourseID'],
    ];
}

echo json_encode($courses);
?>