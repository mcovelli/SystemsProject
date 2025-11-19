<?php
require_once __DIR__ . '/config.php';
$mysqli = get_db();

$sql = "SELECT MajorID, CourseID FROM MajorRequirement ORDER BY MajorName";
$result = $mysqli->query($sql);

$majorRequirements = [];

while ($row = $result->fetch_assoc()) {
    $majorRequirements[] = [
        'majorid' => (int)$row['MajorID'],
        'courseid' => $row['CourseID']
    ];
}

header('Content-Type: application/json');
echo json_encode($majorRequirements);
?>