<?php
require_once __DIR__ . '/config.php';
$mysqli = get_db();

$sql = "SELECT MinorID, CourseID FROM MinorRequirement ORDER BY MinorName";
$result = $mysqli->query($sql);

$minorRequirements = [];

while ($row = $result->fetch_assoc()) {
    $minorRequirements[] = [
        'minorid' => (int)$row['MinorID'],
        'courseid' => $row['CourseID']
    ];
}

header('Content-Type: application/json');
echo json_encode($majorRequirements);
?>