<?php
require_once __DIR__ . '/config.php';

$mysqli = get_db();
$mysqli->set_charset('utf8mb4');

$sql = "SELECT DeptName, DeptID FROM Department ORDER BY DeptName ASC";
$result = $mysqli->query($sql);

$departments = [];

while ($row = $result->fetch_assoc()) {
    $departments[] = [
        'name' => $row['DeptName'],
        'id' => $row['DeptID']
    ];
}

header('Content-Type: application/json');
echo json_encode($departments);