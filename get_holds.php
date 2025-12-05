<?php
require_once __DIR__ . '/config.php';
header('Content-Type: application/json');

$mysqli = get_db();
$mysqli->set_charset('utf8mb4');

$result = $mysqli->query("SELECT * FROM Hold ORDER BY HoldID ASC");
$holds = [];

while ($row = $result->fetch_assoc()) {
    $holds[] = [
        'id'   => $row['HoldID'],
        'type'   => $row['HoldType']
    ];
}

echo json_encode($holds);
?>