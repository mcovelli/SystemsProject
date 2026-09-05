<?php
require_once __DIR__ . '/config.php';
$mysqli = get_db();

/* Declaration screens want only what can still be chosen, so ACTIVE is
   the default. Admin screens pass ?all=1 to see retired minors too --
   otherwise there would be no way to reactivate one. */
$includeRetired = isset($_GET['all']) && $_GET['all'] === '1';

$sql = $includeRetired
    ? "SELECT MinorID, MinorName, Status FROM Minor ORDER BY MinorName"
    : "SELECT MinorID, MinorName, Status FROM Minor WHERE Status = 'ACTIVE' ORDER BY MinorName";
$result = $mysqli->query($sql);

$minors = [];

while ($row = $result->fetch_assoc()) {
    $minors[] = [
        'id' => (int)$row['MinorID'],
        'name' => $row['MinorName'],
        'status' => $row['Status']
    ];
}

header('Content-Type: application/json');
echo json_encode($minors);
?>
