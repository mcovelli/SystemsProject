<?php
declare(strict_types=1);
ob_start();
session_start();

/* Log mysqli errors to Apache log */
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/* ------------ DB CONFIG ------------ */
/* If you have a config.php, include it and read constants/vars there. */
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

  if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    /* ----------- SHOW FORM (requires ?token=...) ----------- */
    $token = trim((string)($_GET['token'] ?? ''));
    if ($token === '') {
      // Coming here without a token -> send back with error
      bad('badreset'); // or 'missing_token'
    }

    // Validate token exists & not expired
    $q = $mysqli->prepare("SELECT LoginID FROM Login WHERE ResetToken = ? AND ResetExpiry > NOW() LIMIT 1");
    $q->bind_param("s", $token);
    $q->execute();
    $q->bind_result($loginId);
    $ok = $q->fetch();
    $q->close();

    if (!$ok) {
      bad('expired'); // invalid or expired token
    }

    // Render a minimal form that preserves the token via hidden input
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
      <meta charset="utf-8">
      <title>Reset Password</title>
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <style>
        body { font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; margin: 2rem; }
        form { max-width: 420px; }
        label { display:block; margin: .75rem 0 .25rem; }
        input[type=password], input[type=submit] { width: 100%; padding: .6rem; }
        .btn { margin-top: 1rem; }
      </style>
    </head>
    <body>
      <h1>Reset Password</h1>
      <form method="post" action="/SystemsProject/reset_password.php">
        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token, ENT_QUOTES); ?>">
        <label for="p1">New password</label>
        <input id="p1" name="p1" type="password" required>

        <label for="p2">Confirm new password</label>
        <input id="p2" name="p2" type="password" required>

        <input class="btn" type="submit" value="Set new password">
      </form>
    </body>
    </html>
    <?php
    exit;
  }

  /* ------------------ POST: SET PASSWORD ------------------ */
  $token = trim((string)($_POST['token'] ?? ''));
  $p1    = (string)($_POST['p1'] ?? '');
  $p2    = (string)($_POST['p2'] ?? '');

  if ($token === '') {
    bad('badreset'); // missing token on POST
  }
  if ($p1 === '' || $p2 === '') {
    bad('empty'); // both password fields required
  }
  if ($p1 !== $p2) {
    bad('nomatch');
  }
  // Basic strength check (adjust to your policy)
  if (strlen($p1) < 8) {
    bad('weak'); // too short
  }

  // Validate token + get login row
  $stmt = $mysqli->prepare("SELECT LoginID FROM Login WHERE ResetToken = ? AND ResetExpiry > NOW() LIMIT 1");
  $stmt->bind_param("s", $token);
  $stmt->execute();
  $stmt->bind_result($loginId);
  $ok = $stmt->fetch();
  $stmt->close();

  if (!$ok) {
    bad('expired');
  }

  // Hash & update, then clear token
  $hash = password_hash($p1, PASSWORD_DEFAULT);

  $up = $mysqli->prepare("
    UPDATE Login
       SET Password = ?, ResetToken = NULL, ResetExpiry = NULL, LoginAttempts = 0
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

  // Success
  redirect('/SystemsProject/login.html?info=reset_ok');

} catch (Throwable $e) {
  error_log("[RESET] 500: " . $e->getMessage());
  http_response_code(500);
  // In prod keep body empty; while debugging you can show a simple message:
  echo "Server error. Check the log.";
  exit;
}