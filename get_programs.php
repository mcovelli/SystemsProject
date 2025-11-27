<?php
require_once 'config.php';
$mysqli = get_db();
header('Content-Type: application/json');

$res = $mysqli->query("SELECT ProgramID, ProgramName FROM Program ORDER BY ProgramName");
$programs = [];
while ($row = $res->fetch_assoc()) {
    $programs[] = 
    ['id' => $row['ProgramID'], 
    'name' => $row['ProgramName']];
}

echo json_encode($programs);
?>