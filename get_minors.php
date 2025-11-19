<?php
require_once __DIR__ . '/config.php';
$mysqli = get_db();

$sql = "SELECT MinorID, MinorName FROM Minor ORDER BY MinorName";
$result = $mysqli->query($sql);

$minors = [];

while ($row = $result->fetch_assoc()) {
    $minors[] = [
        'id' => (int)$row['MinorID'],
        'name' => $row['MinorName']
    ];
}

header('Content-Type: application/json');
echo json_encode($minors);
?>