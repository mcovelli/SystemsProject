<?php
require_once __DIR__ . '/config.php';

$mysqli = get_db();
$mysqli->set_charset('utf8mb4');

$timeSlotID = $_GET['timeSlotID'] ?? null;
$semester   = $_GET['semester'] ?? null;
$year       = $_GET['year'] ?? null;
$current    = $_GET['current'] ?? null;  // currently assigned FacultyID

// If missing data, return all faculty
if (!$timeSlotID || !$semester || !$year) {
    $sql = "
        SELECT 
            f.FacultyID,
            CONCAT(u.FirstName, ' ', u.LastName) AS FacultyName,
            GROUP_CONCAT(d.DeptName ORDER BY d.DeptName SEPARATOR ', ') AS DeptNames
        FROM Faculty f
        JOIN Users u ON f.FacultyID = u.UserID
        JOIN Faculty_Dept fd ON f.FacultyID = fd.FacultyID
        JOIN Department d ON fd.DeptID = d.DeptID
        GROUP BY f.FacultyID
        ORDER BY FacultyName
    ";

    $res = $mysqli->query($sql);
    echo json_encode($res->fetch_all(MYSQLI_ASSOC));
    exit;
}

// Return ONLY faculty *not* teaching that slot (plus the current one)
$sql = "
    SELECT DISTINCT 
        f.FacultyID,
        CONCAT(u.FirstName, ' ', u.LastName) AS FacultyName,
        GROUP_CONCAT(d.DeptName ORDER BY d.DeptName SEPARATOR ', ') AS DeptNames
    FROM Faculty f
    JOIN Users u ON f.FacultyID = u.UserID
    JOIN Faculty_Dept fd ON f.FacultyID = fd.FacultyID
    JOIN Department d ON fd.DeptID = d.DeptID
    WHERE f.FacultyID NOT IN (
        SELECT FacultyID
        FROM CourseSection
        WHERE TimeSlotID = ?
          AND SemesterID = ?
          AND Year = ?
          AND FacultyID <> ?
    )
    GROUP BY f.FacultyID
    ORDER BY FacultyName
";

$stmt = $mysqli->prepare($sql);
$stmt->bind_param("isii", $timeSlotID, $semester, $year, $current);
$stmt->execute();
$result = $stmt->get_result();

echo json_encode($result->fetch_all(MYSQLI_ASSOC));