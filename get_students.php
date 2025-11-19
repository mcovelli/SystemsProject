<?php
require_once __DIR__ . '/config.php';
header('Content-Type: application/json');

$mysqli = get_db();
$mysqli->set_charset('utf8mb4');

$result = $mysqli->query("SELECT StudentID FROM Student WHERE StudentType IN ('Undergraduate', 'Graduate') ORDER BY StudentID ASC");
$students = [];

while ($row = $result->fetch_assoc()) {
    $students[] = $row['StudentID'];
}

echo json_encode($students);
?>