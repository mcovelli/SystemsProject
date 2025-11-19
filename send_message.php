<?php
session_start();
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['user_id'])) {
    redirect('login.php');
}

$mysqli = get_db();
$mysqli->set_charset('utf8mb4');

$sender   = trim($_POST['senderEmail']);
$recipient = trim($_POST['recipientEmail']);
$title     = trim($_POST['title']);
$body      = trim($_POST['body']);

if (!$sender || !$recipient || !$title || !$body) {
    die("Missing required fields.");
}

// 1. Insert into Messages table
$stmt = $mysqli->prepare("
    INSERT INTO Messages (SenderEmail, RecipientEmail, Title, Message, DatePosted)
    VALUES (?, ?, ?, ?, NOW())
");
$stmt->bind_param("ssss", $sender, $recipient, $title, $body);
$stmt->execute();

$messageID = $stmt->insert_id;
$stmt->close();

// 2. Insert two MessageCopies
// Sender → SENT
$copy1 = $mysqli->prepare("
    INSERT INTO MessageCopies (MessageID, OwnerEmail, Folder, IsDeleted)
    VALUES (?, ?, 'SENT', 0)
");
$copy1->bind_param("is", $messageID, $sender);
$copy1->execute();
$copy1->close();

// Recipient → INBOX
$copy2 = $mysqli->prepare("
    INSERT INTO MessageCopies (MessageID, OwnerEmail, Folder, IsDeleted)
    VALUES (?, ?, 'INBOX', 0)
");
$copy2->bind_param("is", $messageID, $recipient);
$copy2->execute();
$copy2->close();

redirect("messages.php?folder=sent");