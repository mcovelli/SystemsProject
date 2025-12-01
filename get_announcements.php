<?php
require_once __DIR__ . '/config.php';
header('Content-Type: application/json');

$mysqli = get_db();
$mysqli->set_charset('utf8mb4');

$result = $mysqli->query("SELECT CRN, Title, Message, DatePosted FROM CourseAnnouncements");
$rooms = [];

while ($row = $result->fetch_assoc()) {
    $rooms[] = [
        'crn' => $row['CRN'],
        'title' => $row['Title'],
        'message' => $row['Message'],
        'date_posted' => $row['DatePosted']
    ];
}

echo json_encode($rooms);
?>