<?php
require_once __DIR__ . '/config.php';
header('Content-Type: application/json');

$mysqli = get_db();
$mysqli->set_charset('utf8mb4');

$sql = "
SELECT SemesterID, SemesterName, Year, StartDate, EndDate
FROM Semester
WHERE CURDATE() BETWEEN DATE_SUB(StartDate, INTERVAL 90 DAY)
                     AND DATE_ADD(StartDate, INTERVAL 7 DAY)
ORDER BY Year DESC, SemesterName DESC
";

$result = $mysqli->query($sql);
$semesters = [];

while ($row = $result->fetch_assoc()) {
    $semesters[] = $row;
}

echo json_encode($semesters);
