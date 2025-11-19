<?php
require_once __DIR__ . '/config.php';
header('Content-Type: application/json');

$mysqli = get_db();
$mysqli->set_charset('utf8mb4');

// Adjust this query to match your Office table schema
$result = $mysqli->query("SELECT OfficeID FROM Office ORDER BY OfficeID ASC");

$offices = [];
while ($row = $result->fetch_assoc()) {
    $offices[] = [
        'id' => (int)$row['OfficeID'],
    ];
}

echo json_encode($offices);
?>