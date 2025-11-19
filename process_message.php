<?php
session_start();
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['user_id'])) {
    redirect('login.php');
}

$mysqli = get_db();
$mysqli->set_charset('utf8mb4');

// Get sender email
$stmt = $mysqli->prepare("SELECT Email FROM Users WHERE UserID = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) die("Sender not found.");

$senderEmail = $row['Email'];

$recipientEmail = trim($_POST['recipient'] ?? '');
$title          = trim($_POST['title'] ?? '');
$message        = trim($_POST['message'] ?? '');

if ($recipientEmail === '' || $title === '' || $message === '') {
    die("Missing required fields.");
}

// Validate recipient exists
$check = $mysqli->prepare("SELECT Email FROM Users WHERE Email = ?");
$check->bind_param("s", $recipientEmail);
$check->execute();
$res = $check->get_result();
$check->close();

if ($res->num_rows === 0) {
    die("Recipient not found.");
}

try {
    $mysqli->begin_transaction();

    // Insert master message
    $msg = $mysqli->prepare("
        INSERT INTO Messages (SenderEmail, RecipientEmail, Title, Message)
        VALUES (?, ?, ?, ?)
    ");
    $msg->bind_param("ssss", $senderEmail, $recipientEmail, $title, $message);
    $msg->execute();
    $messageId = $msg->insert_id;
    $msg->close();

    // Sender copy
    $copy1 = $mysqli->prepare("
        INSERT INTO MessageCopies (MessageID, OwnerEmail, Folder)
        VALUES (?, ?, 'SENT')
    ");
    $copy1->bind_param("is", $messageId, $senderEmail);
    $copy1->execute();
    $copy1->close();

    // Recipient copy
    $copy2 = $mysqli->prepare("
        INSERT INTO MessageCopies (MessageID, OwnerEmail, Folder)
        VALUES (?, ?, 'INBOX')
    ");
    $copy2->bind_param("is", $messageId, $recipientEmail);
    $copy2->execute();
    $copy2->close();

    $mysqli->commit();
} catch (Throwable $e) {
    $mysqli->rollback();
    die("Send failed: " . $e->getMessage());
}

header("Location: messages.php");
exit;