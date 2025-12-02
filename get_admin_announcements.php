<?php
require_once __DIR__ . '/config.php';
session_start();
header('Content-Type: application/json');

$mysqli = get_db();
$mysqli->set_charset('utf8mb4');

$role = strtolower($_SESSION['role'] ?? '');
$semester = $_GET['semester'] ?? null;

// role targeting
switch ($role) {
    case 'student':
        $roleFilter = "TargetGroup IN ('ALL','STUDENT')";
        break;
    case 'faculty':
        $roleFilter = "TargetGroup IN ('ALL','FACULTY')";
        break;
    case 'admin':
        $roleFilter = "TargetGroup IN ('ALL','ADMIN')";
        break;
    default:
        $roleFilter = "TargetGroup = 'ALL'";
}

$semesterJoin = "";
$semesterWhere = "";

if ($semester) {
    $sem = $mysqli->real_escape_string($semester);

    $semesterJoin = "LEFT JOIN CourseSection cs ON aa.CRN = cs.CRN";

    // allow:
    //   - CRN is NULL (always show)
    //   - CRN matches semester
    $semesterWhere = "AND (aa.CRN IS NULL OR cs.SemesterID = '$sem')";
}

$sql = "
SELECT 
    aa.CRN,
    aa.Title,
    aa.Message,
    aa.DatePosted,
    aa.AdminID AS SenderID,
    aa.TargetGroup,
    'ADMIN' AS SourceType
FROM AdminAnnouncements aa
$semesterJoin
WHERE $roleFilter
$semesterWhere
ORDER BY aa.DatePosted DESC
";

$result = $mysqli->query($sql);

$out = [];
while ($row = $result->fetch_assoc()) {
    $out[] = $row;
}

echo json_encode($out);