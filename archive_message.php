<?php
session_start();
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['user_id'])) {
    redirect('login.php');
}

$mysqli = get_db();
$mysqli->set_charset('utf8mb4');

$copyId = intval($_POST['CopyID'] ?? 0);

$stmt = $mysqli->prepare("
    UPDATE MessageCopies
    SET Folder = 'ARCHIVED'
    WHERE CopyID = ?
");
$stmt->bind_param("i", $copyId);
$stmt->execute();
$stmt->close();

header("Location: messages.php");
exit;