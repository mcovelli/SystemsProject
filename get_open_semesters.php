<?php
require_once __DIR__ . '/config.php';
header('Content-Type: application/json');

$mysqli = get_db();
$mysqli->set_charset('utf8mb4');

// Get today's date
$today = date('Y-m-d');

// Query: semesters within the add/drop window
$sql = "
    SELECT SemesterID, SemesterName, Year
    FROM Semester
    WHERE DATE_ADD(StartDate, INTERVAL -90 DAY) <= ?
      AND DATE_ADD(StartDate, INTERVAL 7 DAY) >= ?
    ORDER BY Year DESC, SemesterName DESC
";

$stmt = $mysqli->prepare($sql);
$stmt->bind_param('ss', $today, $today);
$stmt->execute();
$result = $stmt->get_result();

$semesters = [];
while ($row = $result->fetch_assoc()) {
    $semesters[] = [
        'SemesterID' => $row['SemesterID'],
        'SemesterName' => $row['SemesterName'],
        'Year' => $row['Year']
    ];
}

/* This ran above the query, against a $semesters that did not exist yet, and
   the assignment below then discarded whatever it appended -- so callers got a
   bare [] and rendered an empty dropdown with nothing to explain it. */
if (empty($semesters)) {
    $semesters[] = ['SemesterID' => '', 'SemesterName' => 'No open semesters', 'Year' => ''];
}

echo json_encode($semesters);
$stmt->close();
?>