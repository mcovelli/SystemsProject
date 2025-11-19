<?php
require_once __DIR__ . '/config.php';
header('Content-Type: application/json');

$mysqli = get_db();
$mysqli->set_charset('utf8mb4');

$result = $mysqli->query("SELECT UserID FROM Users WHERE UserType IN ('Student', 'Admin', 'Faculty', 'Statstaff') ORDER BY UserID ASC");
$users = [];

while ($row = $result->fetch_assoc()) {
    $users[] = $row['UsertID'];
}

echo json_encode($users);
?>