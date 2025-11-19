<?php
require_once __DIR__ . '/config.php';
header('Content-Type: application/json');

$mysqli = get_db();
$mysqli->set_charset('utf8mb4');

$sql = "
    SELECT SemesterID, SemesterName, Year
    FROM Semester
    ORDER BY Year DESC, SemesterName DESC
";
$result = $mysqli->query($sql);

$semesters = [];
while ($row = $result->fetch_assoc()) {
    $semesters[] = [
        'SemesterID' => $row['SemesterID'],
        'SemesterName' => $row['SemesterName'],
        'Year' => $row['Year']
    ];
}

echo json_encode($semesters);