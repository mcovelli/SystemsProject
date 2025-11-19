<?php
require_once __DIR__ . '/config.php';
$mysqli = get_db();
$mysqli->set_charset('utf8mb4');

$sql = "
SELECT 
    f.FacultyID,
    CONCAT(u.FirstName, ' ', u.LastName) AS FacultyName,
    GROUP_CONCAT(DISTINCT d.DeptName ORDER BY d.DeptName SEPARATOR ', ') AS DeptNames
FROM Faculty f
JOIN Users u ON f.FacultyID = u.UserID
LEFT JOIN Faculty_Dept fd ON f.FacultyID = fd.FacultyID
LEFT JOIN Department d ON fd.DeptID = d.DeptID
GROUP BY f.FacultyID, u.FirstName, u.LastName
ORDER BY u.LastName ASC, u.FirstName ASC
";

$result = $mysqli->query($sql);

$faculty = [];
while ($row = $result->fetch_assoc()) {
    $faculty[] = [
        'FacultyID'   => $row['FacultyID'],
        'FacultyName' => $row['FacultyName'],
        'DeptNames'   => $row['DeptNames'] ?: 'No Department Assigned'
    ];
}

header('Content-Type: application/json');
echo json_encode($faculty);