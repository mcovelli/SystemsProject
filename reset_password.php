<?php
declare(strict_types=1);
ob_start();
session_start();
require_once __DIR__ . '/config.php';
/* Log mysqli errors to Apache log */
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/* ------------ DB CONFIG ------------ */
$DB_HOST = "127.0.0.1";
$DB_PORT = 3306;
$DB_USER = "phpuser";
$DB_PASS = "SystemsFall2025!";
$DB_NAME = "University";

/* Small helpers */
function redirect(string $path): never {
  header('Location: ' . $path, true, 302);
  exit;
}
function bad(string $code): never {
  redirect('/SystemsProject/login.html?err=' . urlencode($code));
}

try {
  $mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, $DB_PORT);
  $mysqli->set_charset('utf8mb4');

  /* ------------------ GET: DISPLAY FORM ------------------ */
  if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $token = trim((string)($_GET['token'] ?? ''));
    if ($token === '') {
      bad('missing_token');
    }

    // Validate token exists & not expired
    $q = $mysqli->prepare("
      SELECT LoginID
        FROM Login
       WHERE ResetToken = ? AND ResetExpiry > NOW()
       LIMIT 1
    ");
    $q->bind_param("s", $token);
    $q->execute();
    $q->bind_result($loginId);
    $ok = $q->fetch();
    $q->close();

    if (!$ok) {
      bad('expired'); // invalid or expired token
    }

    // Show reset form
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
      <meta charset="utf-8">
      <title>Reset Password</title>
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <style>
        body { font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; margin: 2rem; }
        form { max-width: 420px; margin-top: 2rem; }
        label { display:block; margin: .75rem 0 .25rem; }
        input[type=password], input[type=submit] { width: 100%; padding: .6rem; }
        .btn { margin-top: 1rem; }
      </style>
    </head>
    <body>
      <h1>Reset Your Password</h1>
      <p>Enter your new password below. Once complete, your account will be unlocked.</p>
      <form method="post" action="/SystemsProject/reset_password.php">
        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token, ENT_QUOTES); ?>">

        <label for="p1">New Password</label>
        <input id="p1" name="p1" type="password" required minlength="8" placeholder="At least 8 characters">

        <label for="p2">Confirm New Password</label>
        <input id="p2" name="p2" type="password" required minlength="8" placeholder="Re-enter password">

        <input class="btn" type="submit" value="Set New Password">
      </form>
    </body>
    </html>
    <?php
    exit;
  }

  /* ------------------ POST: PROCESS RESET ------------------ */
  $token = trim((string)($_POST['token'] ?? ''));
  $p1    = (string)($_POST['p1'] ?? '');
  $p2    = (string)($_POST['p2'] ?? '');

  if ($token === '') {
    bad('missing_token');
  }
  if ($p1 === '' || $p2 === '') {
    bad('empty');
  }
  if ($p1 !== $p2) {
    bad('nomatch');
  }
  if (strlen($p1) < 8) {
    bad('weak');
  }

  // Validate token
  $stmt = $mysqli->prepare("
    SELECT LoginID
      FROM Login
     WHERE ResetToken = ? AND ResetExpiry > NOW()
     LIMIT 1
  ");
  $stmt->bind_param("s", $token);
  $stmt->execute();
  $stmt->bind_result($loginId);
  $ok = $stmt->fetch();
  $stmt->close();

  if (!$ok) {
    bad('expired');
  }

  // Hash and update — automatically clears lock
  $hash = password_hash($p1, PASSWORD_DEFAULT);

  $up = $mysqli->prepare("
    UPDATE Login
       SET Password = ?,
           ResetToken = NULL,
           ResetExpiry = NULL,
           LoginAttempts = 0,
           MustReset = 0
     WHERE LoginID = ?
     LIMIT 1
  ");
  $up->bind_param("si", $hash, $loginId);
  $up->execute();
  $affected = $up->affected_rows;
  $up->close();

  if ($affected !== 1) {
    bad('badreset');
  }

  // ✅ Success: lock cleared, password changed
  redirect('/SystemsProject/login.html?info=reset_ok');

} catch (Throwable $e) {
  error_log("[RESET] 500: " . $e->getMessage());
  http_response_code(500);
  echo "Server error. Please try again later.";
  exit;
}