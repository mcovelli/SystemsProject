<?php
require_once __DIR__ . '/config.php';
session_start();
header('Content-Type: application/json');

$mysqli = get_db();
$semester = $_GET['semester'] ?? null;
$userId = $_SESSION['user_id'];
$role = strtolower($_SESSION['role'] ?? '');

$semesterFilter = "";
if ($semester) {
    $semesterEsc = $mysqli->real_escape_string($semester);
    $semesterFilter = "AND cs.SemesterID = '$semesterEsc'";
}

$sql = "
SELECT 
    ca.CRN,
    ca.Title,
    ca.Message,
    ca.DatePosted,
    ca.FacultyID AS SenderID,
    'COURSE' AS SourceType
FROM CourseAnnouncements ca
JOIN CourseSection cs ON cs.CRN = ca.CRN
WHERE 1=1
$semesterFilter
ORDER BY ca.DatePosted DESC
";

$result = $mysqli->query($sql);

$out = [];
while ($row = $result->fetch_assoc()) {
    $out[] = $row;
}

echo json_encode($out);