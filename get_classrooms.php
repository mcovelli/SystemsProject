<?php
require_once __DIR__ . '/config.php';

$mysqli = get_db();
$mysqli->set_charset('utf8mb4');

$timeSlotID = $_GET['timeSlotID'] ?? null;
$semester   = $_GET['semester'] ?? null;
$year       = $_GET['year'] ?? null;
$current    = $_GET['current'] ?? null;

if (!$timeSlotID || !$semester || !$year) {
    echo json_encode([]);
    exit;
}

// Return ONLY rooms NOT booked for this time slot, semester, year
// EXCEPT include the current room being used by this course section
$sql = "
    SELECT r.RoomID AS id, r.RoomType AS type
    FROM Room r
    WHERE 
        r.RoomID NOT IN (
            SELECT RoomID FROM CourseSection
            WHERE TimeSlotID = ?
              AND SemesterID = ?
              AND Year = ?
              AND RoomID <> ?
        )
";

$stmt = $mysqli->prepare($sql);
$stmt->bind_param("isis", $timeSlotID, $semester, $year, $current);
$stmt->execute();
$res = $stmt->get_result();

$rooms = [];
while ($row = $res->fetch_assoc()) {
    $rooms[] = $row;
}

echo json_encode($rooms);