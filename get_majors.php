<?php
require_once __DIR__ . '/config.php';
$mysqli = get_db();

/* Declaration screens want only what can still be chosen, so ACTIVE is
   the default. Admin screens pass ?all=1 to see retired majors too --
   otherwise there would be no way to reactivate one. */
$includeRetired = isset($_GET['all']) && $_GET['all'] === '1';

$sql = $includeRetired
    ? "SELECT MajorID, MajorName, Status FROM Major ORDER BY MajorName"
    : "SELECT MajorID, MajorName, Status FROM Major WHERE Status = 'ACTIVE' ORDER BY MajorName";
$result = $mysqli->query($sql);

$majors = [];

while ($row = $result->fetch_assoc()) {
    $majors[] = [
        'id' => (int)$row['MajorID'],
        'name' => $row['MajorName'],
        'status' => $row['Status']
    ];
}

header('Content-Type: application/json');
echo json_encode($majors);
?>
