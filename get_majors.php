<?php
require_once __DIR__ . '/config.php';
$mysqli = get_db();

$sql = "SELECT MajorID, MajorName FROM Major ORDER BY MajorName";
$result = $mysqli->query($sql);

$majors = [];

while ($row = $result->fetch_assoc()) {
    $majors[] = [
        'id' => (int)$row['MajorID'],
        'name' => $row['MajorName']
    ];
}

header('Content-Type: application/json');
echo json_encode($majors);
?>