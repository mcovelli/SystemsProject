<?php
declare(strict_types=1);
ob_start();
session_start();

/* Show mysqli errors in the Apache log */
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$DB_HOST = "127.0.0.1";
$DB_PORT = 3306;
$DB_USER = "phpuser";
$DB_PASS = "SystemsFall2025!";
$DB_NAME = "University";

try {
  $mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, $DB_PORT);
  $mysqli->set_charset('utf8mb4');

  if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $t = $_GET['token'] ?? '';
    if ($t === '') { http_response_code(400); exit('Missing token'); }

    // Validate token is present & not expired
    $stmt = $mysqli->prepare("SELECT LoginID FROM Login WHERE ResetToken = ? AND ResetExpiry > NOW() LIMIT 1");
    $stmt->bind_param("s", $t);
    $stmt->execute();
    $stmt->bind_result($loginId);
    $valid = $stmt->fetch();
    $stmt->close();

    if (!$valid) { http_response_code(400); exit('Invalid or expired token'); }
    ?>
    <!doctype html>
    <html>
    <head>
        <meta charset="utf-8">
        <title>Reset Password</title>
        <link rel="stylesheet" href="styles.css">
    </head>
    <body>
        <div class="auth-container">
      <h2>Reset Password</h2>
      <form method="post" action="/reset_password.php">
        <input type="hidden" name="token" value="<?php echo htmlspecialchars($t, ENT_QUOTES); ?>">
        <label>New password</label>
        <input type="password" name="newpw" required>
        <button type="submit">Update</button>
      </form>
      <p><a href="/app/login.html">Back to login</a></p>
    </body>
    </html>
    <?php
    exit;
  }

  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $t   = $_POST['token'] ?? '';
    $new = $_POST['newpw'] ?? '';

    if ($t === '' || $new === '') {
      header('Location: /app/login.html?err=badreset'); exit;
    }

    // Confirm token again (still valid)
    $sel = $mysqli->prepare("SELECT LoginID FROM Login WHERE ResetToken = ? AND ResetExpiry > NOW() LIMIT 1");
    $sel->bind_param("s", $t);
    $sel->execute();
    $sel->bind_result($loginId);
    $ok = $sel->fetch();
    $sel->close();

    if (!$ok) { header('Location: /app/login.html?err=expired'); exit; }

    // Hash the new password
    $hash = password_hash($new, PASSWORD_DEFAULT);

    // Update password, clear reset fields & attempts
    $upd = $mysqli->prepare("
      UPDATE Login
         SET Password = ?, ResetToken = NULL, ResetExpiry = NULL, LoginAttempts = 0
       WHERE LoginID = ?
    ");
    $upd->bind_param("si", $hash, $loginId);
    $upd->execute();
    $upd->close();

    // Send back to login page with a success message
    error_log("[RESET] Password updated for LoginID=$loginId, redirecting to login.html");
    header('Location: /app/login.html?info=reset_ok'); 
    exit;
  }

  http_response_code(405); // method not allowed
  exit;

} catch (Throwable $e) {
  error_log("[RESET] 500: " . $e->getMessage());
  http_response_code(500);
  exit; // keep body empty; see Apache error_log for details
}