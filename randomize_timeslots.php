<?php
require_once "config.php";
$mysqli = get_db();

// Fetch all sections
$res = $mysqli->query("SELECT CRN, FacultyID, RoomID FROM CourseSection ORDER BY CRN");
$sections = $res->fetch_all(MYSQLI_ASSOC);

$availableSlots = range(1, 18);

// Track what is taken to prevent collisions
$facultyTaken = [];
$roomTaken = [];

foreach ($sections as $sec) {
    $crn = $sec['CRN'];
    $fid = $sec['FacultyID'];
    $rid = $sec['RoomID'];

    // Shuffle available slots for randomness
    $slots = $availableSlots;
    shuffle($slots);

    $assigned = null;

    foreach ($slots as $slot) {

        // Check faculty collision
        if (isset($facultyTaken[$fid][$slot])) continue;

        // Check room collision
        if (isset($roomTaken[$rid][$slot])) continue;

        // Assign it
        $assigned = $slot;
        $facultyTaken[$fid][$slot] = true;
        $roomTaken[$rid][$slot] = true;
        break;
    }

    if ($assigned === null) {
        echo "❌ No available slot for CRN $crn\n";
        continue;
    }

    // Update database
    $stmt = $mysqli->prepare("UPDATE CourseSection SET TimeSlotID = ? WHERE CRN = ?");
    $stmt->bind_param("ii", $assigned, $crn);
    $stmt->execute();
    $stmt->close();

    echo "✔ Assigned CRN $crn → TimeSlot $assigned\n";
}

echo "🎉 TimeSlot randomization complete.\n";