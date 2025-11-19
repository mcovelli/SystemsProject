<?php
require_once __DIR__ . '/config.php';
header('Content-Type: application/json');

$mysqli = get_db();
$mysqli->set_charset('utf8mb4');

$result = $mysqli->query("SELECT LogID FROM AuditLog WHERE Operation IN ('INSERT', 'UPDATE', 'DELETE') ORDER BY LogID ASC");
$audit_logs = [];

while ($row = $result->fetch_assoc()) {
    $audit_logs[] = $row['Details'];
}

echo json_encode($audit_logs);
?>