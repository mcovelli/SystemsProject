<?php
require_once __DIR__ . '/config.php';
header('Content-Type: application/json');

$mysqli = get_db();
$mysqli->set_charset('utf8mb4');

$sql = "SELECT DISTINCT DegreeLevel FROM Program ORDER BY DegreeLevel";
$result = $mysqli->query($sql);

$degreeLevel = [];

while ($row = $result->fetch_assoc()) {
    $degreeLevel[] = [
        'degreelevel' => $row['DegreeLevel']
    ];
}

echo json_encode($degreeLevel);
?>