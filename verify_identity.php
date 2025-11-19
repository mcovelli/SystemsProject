<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/config.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $mysqli = get_db();
    $mysqli->set_charset('utf8mb4');

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
          <meta charset="utf-8">
          <title>Verify Identity | Northport University</title>
          <style>
            body { font-family: system-ui, sans-serif; max-width: 480px; margin: 2rem auto; }
            input, button { width: 100%; padding: .6rem; margin-top: .6rem; }
          </style>
        </head>
        <body>
          <h1>Verify Your Identity</h1>
          <p>Please provide your details to verify your account before resetting your password.</p>
          <form method="post" action="verify_identity.php">
            <label>First Name</label>
            <input name="first" required>

            <label>Last Name</label>
            <input name="last" required>

            <label>Email</label>
            <input name="email" type="email" required>

            <label>Date of Birth</label>
            <input name="dob" type="date" required>

            <button type="submit">Verify Identity</button>
          </form>
        </body>
        </html>
        <?php
        exit;
    }

    /* ------------------ POST: VALIDATE USER ------------------ */
    $first = trim($_POST['first'] ?? '');
    $last  = trim($_POST['last'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $dob   = trim($_POST['dob'] ?? '');

    if (!$first || !$last || !$email || !$dob) {
        header('Location: ' . PROJECT_ROOT . '/login.html?err=empty');
        exit;
    }

    $stmt = $mysqli->prepare("
        SELECT l.LoginID, u.UserID
        FROM Users u
        INNER JOIN Login l ON l.LoginID = u.UserID
        WHERE u.FirstName = ? AND u.LastName = ? AND u.Email = ? AND u.DOB = ?
        LIMIT 1
    ");
    $stmt->bind_param('ssss', $first, $last, $email, $dob);
    $stmt->execute();
    $res = $stmt->get_result();
    $user = $res->fetch_assoc();
    $stmt->close();

    if (!$user) {
        header('Location: ' . PROJECT_ROOT . '/login.html?err=notfound');
        exit;
    }

    // Generate secure token
    $token = bin2hex(random_bytes(32));
    $expiry = date('Y-m-d H:i:s', time() + 3600); // 1 hour

    $stmt = $mysqli->prepare("
        UPDATE Login
        SET ResetToken = ?, ResetExpiry = ?
        WHERE LoginID = ?
    ");
    $stmt->bind_param('ssi', $token, $expiry, $user['LoginID']);
    $stmt->execute();
    $stmt->close();

    $reset_link = PROJECT_ROOT . "/reset_password.php?token=" . urlencode($token);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
      <meta charset="utf-8">
      <title>Identity Verified</title>
      <style>
        body { font-family: system-ui, sans-serif; max-width: 480px; margin: 2rem auto; text-align: center; }
        a { color: #0055cc; font-weight: bold; }
      </style>
    </head>
    <body>
      <h1>Identity Verified</h1>
      <p>Your identity has been confirmed.</p>
      <p><a href="<?php echo htmlspecialchars($reset_link, ENT_QUOTES); ?>">Continue to Reset Password</a></p>
      <p>This link will expire in 1 hour.</p>
    </body>
    </html>
    <?php
    exit;

} catch (Throwable $e) {
    error_log("[VERIFY_IDENTITY] " . $e->getMessage());
    http_response_code(500);
    exit("Server error. Please try again later.");
}
?>