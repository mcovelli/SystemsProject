<?php
require_once __DIR__ . '/config.php';
$mysqli = get_db();

$sql = "SELECT RequirementID, ProgramID, CourseID FROM ProgramRequirement ORDER BY ProgramName";
$result = $mysqli->query($sql);

$programRequirements = [];

while ($row = $result->fetch_assoc()) {
    $programRequirements[] = [
        'requirementid' => (int)$row['RequirementID'],
        'programid' => (int)$row['ProgramID'],
        'courseid' => $row['CourseID']
    ];
}

header('Content-Type: application/json');
echo json_encode($majorRequirements);
?>