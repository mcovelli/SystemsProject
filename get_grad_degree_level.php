<?php
require_once __DIR__ . '/config.php';
$mysqli = get_db();

$sql = "SELECT DISTINCT DegreeLevel FROM Program ORDER BY DegreeLevel";
$result = $mysqli->query($sql);

$degreeLevel = [];

while ($row = $result->fetch_assoc()) {
    $degreeLevel[] = $row['DegreeLevel'];
}

header('Content-Type: application/json');
echo json_encode($degreeLevel);
?>