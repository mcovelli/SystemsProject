<?php
require_once __DIR__ . '/config.php';
header('Content-Type: application/json');

$mysqli = get_db();
$mysqli->set_charset('utf8mb4');

$result = $mysqli->query("SELECT ca.CRN, ca.Title, ca.Message, ca.DatePosted, f.FacultyID FROM CourseAnnouncements ca
JOIN Faculty f ON ca.FacultyID = f.FacultyID
JOIN CourseSection cs ON ca.CRN = cs.CRN && f.FacultyID = cs.FacultyID
ORDER BY ca.DatePosted DESC");
$announcements = [];

while ($row = $result->fetch_assoc()) {
    $announcements[] = [
        'crn' => $row['CRN'],
        'title' => $row['Title'],
        'message' => $row['Message'],
        'date_posted' => $row['DatePosted'],
        'faculty_id' => $row['FacultyID']
    ];
}

echo json_encode($announcements);
?>