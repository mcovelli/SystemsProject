<?php
require_once __DIR__ . '/config.php';
header('Content-Type: application/json');

$mysqli = get_db();
$mysqli->set_charset('utf8mb4');

$result = $mysqli->query("SELECT RoomID, RoomType FROM Room WHERE RoomType IN ('Lecture', 'Lab') ORDER BY RoomID ASC");
$rooms = [];

while ($row = $result->fetch_assoc()) {
    $rooms[] = [
        'id' => $row['RoomID'],
        'type' => $row['RoomType']
    ];
}

echo json_encode($rooms);
?>