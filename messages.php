<?php
session_start();
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['user_id'])) {
    redirect('login.php');
}

$mysqli = get_db();
$mysqli->set_charset('utf8mb4');

// Get user email
$stmt = $mysqli->prepare("SELECT Email, FirstName, LastName FROM Users WHERE UserID = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();
$userEmail = $user['Email'];

// Determine folder (default inbox)
$folder = $_GET['folder'] ?? 'inbox';
$folderSQL = strtoupper($folder) === 'SENT' ? 'SENT' : 'INBOX';

// Get message list for this folder
$list = $mysqli->prepare("
    SELECT 
        c.CopyID,
        c.MessageID,
        m.Title,
        m.Message,
        m.DatePosted,
        m.SenderEmail,
        m.RecipientEmail
    FROM MessageCopies c
    JOIN Messages m ON m.MessageID = c.MessageID
    WHERE c.OwnerEmail = ?
      AND c.Folder = ?
      AND c.IsDeleted = 0
    ORDER BY m.DatePosted DESC
");
$list->bind_param("ss", $userEmail, $folderSQL);
$list->execute();
$list_res = $list->get_result();

// Get selected message
$message_id = $_GET['message_id'] ?? null;
$selected = null;

if ($message_id) {
    $msg = $mysqli->prepare("
        SELECT 
            c.CopyID,
            m.Title,
            m.Message,
            m.DatePosted,
            m.SenderEmail,
            m.RecipientEmail
        FROM MessageCopies c
        JOIN Messages m ON m.MessageID = c.MessageID
        WHERE c.CopyID = ?
          AND c.OwnerEmail = ?
          AND c.IsDeleted = 0
        LIMIT 1
    ");
    $msg->bind_param("is", $message_id, $userEmail);
    $msg->execute();
    $selected = $msg->get_result()->fetch_assoc();
    $msg->close();
}

// Determine back dashboard
$userRole = strtolower($_SESSION['role'] ?? '');
switch ($userRole) {
    case 'student':  $dashboard = 'student_dashboard.php'; break;
    case 'faculty':  $dashboard = 'faculty_dashboard.php'; break;
    case 'admin':
        $dashboard = ($_SESSION['admin_type'] ?? '') === 'update'
            ? 'update_admin_dashboard.php'
            : 'view_admin_dashboard.php';
        break;
    default: $dashboard = 'login.php';
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Messages</title>
    <link rel="stylesheet" href="./mailstyles.css">
</head>
<body>

<div class="layout">

<!-- SIDEBAR -->
<aside class="sidebar">
    <h2>Folders</h2>

    <form method="GET" action="messages.php">
        <input type="hidden" name="folder" value="inbox">
        <input type="submit" value="Inbox">
    </form>

    <form method="GET" action="messages.php">
        <input type="hidden" name="folder" value="sent">
        <input type="submit" value="Sent">
    </form>

    <h2 style="margin-top: 16px;">Actions</h2>
    <button type="button" class="primary" onclick="showCompose()">Compose</button>

    <p style="margin-top:20px;">
        <a href="<?= htmlspecialchars($dashboard) ?>">← Back to Dashboard</a>
    </p>
</aside>

<!-- MAIN CONTENT -->
<main class="content">
    <div class="toolbar">
        <h2 id="current-folder-label"><?= ucfirst(strtolower($folderSQL)) ?></h2>
    </div>

    <div class="message-layout">
        
        <!-- MESSAGE LIST -->
        <section class="panel" id="list-panel">
            <div class="panel-header">Messages</div>
            <div class="panel-body">
                <table>
                    <thead>
                        <tr>
                            <th>From</th>
                            <th>Title</th>
                        </tr>
                    </thead>
                    <tbody>

                    <?php if ($list_res->num_rows === 0): ?>
                        <tr><td colspan="2">No messages.</td></tr>

                    <?php else: ?>
                        <?php while ($row = $list_res->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <form method="GET" action="messages.php" class="message-row-form">
                                    <input type="hidden" name="folder" value="<?= htmlspecialchars($folderSQL) ?>">
                                    <input type="hidden" name="message_id" value="<?= $row['CopyID'] ?>">
                                    <button type="submit" class="message-row-btn">
                                        <div class="message-title"><?= htmlspecialchars($row['Title']) ?></div>
                                        <div class="message-meta"><?= htmlspecialchars($row['SenderEmail']) ?></div>
                                    </button>
                                </form>
                            </td>
                            <td><?= htmlspecialchars($row['Title']) ?></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>

                    </tbody>
                </table>
            </div>
        </section>

        <!-- MESSAGE VIEW / COMPOSE -->
        <section class="panel" id="view-panel">
            <div class="panel-header">
                <?= $selected ? htmlspecialchars($selected['Title']) : "Message" ?>
            </div>
            <div class="panel-body">

                <!-- MESSAGE VIEW -->
                <div id="message-view-section" class="<?= $selected ? '' : 'hidden' ?>">

                    <?php if ($selected): ?>

                        <div class="message-field">
                            <div class="message-field-label">From</div>
                            <div class="message-field-value"><?= htmlspecialchars($selected['SenderEmail']) ?></div>
                        </div>

                        <div class="message-field">
                            <div class="message-field-label">To</div>
                            <div class="message-field-value"><?= htmlspecialchars($selected['RecipientEmail']) ?></div>
                        </div>

                        <div class="message-field">
                            <div class="message-field-label">Date</div>
                            <div class="message-field-value"><?= htmlspecialchars($selected['DatePosted']) ?></div>
                        </div>

                        <div class="message-body">
                            <?= nl2br(htmlspecialchars($selected['Message'])) ?>
                        </div>

                        <?php if ($folderSQL === 'INBOX' || $folderSQL === 'SENT'): ?>
                        <form method="POST" action="archive_message.php" class="actions">
                            <input type="hidden" name="CopyID" value="<?= $selected['CopyID'] ?>">
                            <button type="submit" class="btn">Archive</button>
                        </form>
                        <?php endif; ?>

                    <?php else: ?>
                        <p>Select a message from the list.</p>
                    <?php endif; ?>
                </div>

                <!-- COMPOSE -->
                <div id="compose-section" class="hidden">
                    <form method="POST" action="send_message.php">
                        <div class="field">
                            <label>From</label>
                            <input type="email" name="senderEmail" value="<?= htmlspecialchars($userEmail) ?>" readonly>
                        </div>

                        <div class="field">
                            <label>To</label>
                            <input type="email" name="recipientEmail" required>
                        </div>

                        <div class="field">
                            <label>Title</label>
                            <input type="text" name="title" required>
                        </div>

                        <div class="field">
                            <label>Message</label>
                            <textarea name="body" required></textarea>
                        </div>

                        <button type="submit" class="btn primary">Send</button>
                        <button type="button" class="btn" onclick="cancelCompose()">Cancel</button>
                    </form>
                </div>

            </div>
        </section>

    </div>
</main>
</div>
<footer class="footer">© <span id="year"></span> Northport University</footer>

<script>
    // Populate the year in the footer
    document.getElementById('year').textContent = new Date().getFullYear();
function showCompose() {
    document.getElementById('compose-section').classList.remove('hidden');
    document.getElementById('message-view-section').classList.add('hidden');
    document.getElementById('current-folder-label').textContent = 'Compose';
}

function cancelCompose() {
    document.getElementById('compose-section').classList.add('hidden');
    document.getElementById('message-view-section').classList.remove('hidden');
    document.getElementById('current-folder-label').textContent = 'Inbox';
}
</script>

</body>
</html>