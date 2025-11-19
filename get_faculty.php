<?php
require_once __DIR__ . '/config.php';
$mysqli = get_db();
$mysqli->set_charset('utf8mb4');

// Fetch all graduate-level programs
$sql = "SELECT f.FacultyID, CONCAT(fu.FirstName, ' ', fu.LastName) AS FacultyName, fd.DeptID, d.DeptName
FROM Faculty f
JOIN Users fu ON f.FacultyID = fu.UserID
JOIN Faculty_Dept fd ON f.FacultyID = fd.FacultyID
JOIN Department d ON fd.DeptID = d.DeptID
ORDER BY FacultyID ASC";
$result = $mysqli->query($sql);

$faculty = [];
while ($row = $result->fetch_assoc()) {
    $faculty[] = [
        'FacultyID' => $row['FacultyID'],
        'FacultyName' => $row['FacultyName'],
        'DeptID' => $row['DeptID'],
        'DeptName' => $row['DeptName'],
    ];
}

header('Content-Type: application/json');
echo json_encode($faculty);
?>