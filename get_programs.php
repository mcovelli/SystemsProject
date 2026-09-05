<?php
require_once 'config.php';
$mysqli = get_db();
header('Content-Type: application/json');

/* Declaration screens want only what can still be chosen, so ACTIVE is
   the default. Admin screens pass ?all=1 to see retired programs too --
   otherwise there would be no way to reactivate one.

   ?current=<id> keeps one specific program in the list even if it has
   been retired, so a student already enrolled in it still sees their own
   program rather than a blank selection. */
$includeRetired = isset($_GET['all']) && $_GET['all'] === '1';
$current = isset($_GET['current']) && ctype_digit((string)$_GET['current'])
    ? (int)$_GET['current']
    : 0;

if ($includeRetired) {
    $stmt = $mysqli->prepare(
        "SELECT ProgramID, ProgramName, Status FROM Program ORDER BY ProgramName"
    );
} else {
    $stmt = $mysqli->prepare(
        "SELECT ProgramID, ProgramName, Status FROM Program
          WHERE Status = 'ACTIVE' OR ProgramID = ?
          ORDER BY ProgramName"
    );
    $stmt->bind_param("i", $current);
}

$stmt->execute();
$res = $stmt->get_result();

$programs = [];
while ($row = $res->fetch_assoc()) {
    $programs[] = [
        'id' => $row['ProgramID'],
        'name' => $row['ProgramName'],
        'status' => $row['Status']
    ];
}
$stmt->close();

echo json_encode($programs);
?>
