<?php
require_once 'config.php';
$mysqli = get_db();
$res = $mysqli->query("SELECT ProgramID, ProgramName FROM Program WHERE Status='ACTIVE'");
$programs = [];
while ($row = $res->fetch_assoc()) {
    $programs[] = ['id' => (int)$row['ProgramID'], 
    'name' => $row['ProgramName']];
}
header('Content-Type: application/json');
echo json_encode($programs);
?>