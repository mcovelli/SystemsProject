<?php
require_once __DIR__ . '/config.php';
header('Content-Type: application/json');

$mysqli = get_db();
$mysqli->set_charset('utf8mb4');

$result = $mysqli->query("SELECT DISTINCT CourseType FROM Course WHERE CourseType IS NOT NULL AND CourseType <> '' ORDER BY CourseID ASC");
$type = [];

while ($row = $result->fetch_assoc()) {
    $type[] = $row['CourseType'];
}

echo json_encode($type);
?>