<?php
require_once __DIR__ . '/config.php';
header('Content-Type: application/json');

$mysqli = get_db();
$mysqli->set_charset('utf8mb4');

$sql = "
    SELECT 
        ts.TS_ID AS TimeSlotID,
        GROUP_CONCAT(Day.DayOfWeek ORDER BY Day.DayID SEPARATOR '/') AS Days,
        CONCAT(MIN(p.StartTime), ' - ', MAX(p.EndTime)) AS TimeRange
    FROM TimeSlot ts
    JOIN TimeSlotDay tsd ON ts.TS_ID = tsd.TS_ID
    JOIN Day ON tsd.DayID = Day.DayID
    JOIN TimeSlotPeriod tsp ON ts.TS_ID = tsp.TS_ID
    JOIN Period p ON tsp.PeriodID = p.PeriodID
    GROUP BY ts.TS_ID
    ORDER BY ts.TS_ID ASC
";

$result = $mysqli->query($sql);

$timeslots = [];

while ($row = $result->fetch_assoc()) {
    $timeslots[] = [
        'id'   => $row['TimeSlotID'],
        'label' => $row['Days'] . " " . $row['TimeRange']
    ];
}

echo json_encode($timeslots);