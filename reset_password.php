<?php
declare(strict_types=1);
ob_start();
session_start();
require_once __DIR__ . '/config.php';

/* Log mysqli errors to Apache log */
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
  $mysqli = get_db();
  $mysqli->set_charset('utf8mb4');

  if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $t = $_GET['token'] ?? '';
    if ($t === '') { http_response_code(400); exit('Missing token'); }

        // Validate token
        $stmt = $mysqli->prepare("
            SELECT LoginID FROM Login
             WHERE ResetToken = ? AND ResetExpiry > NOW()
             LIMIT 1
        ");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $stmt->bind_result($loginId);
        $valid = $stmt->fetch();
        $stmt->close();

        if (!$valid) {
            header('Location: ' . PROJECT_ROOT . '/login.html?err=expired');
            exit;
        }

        // Show form
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="utf-8">
            <title>Reset Password | Northport University</title>
            <style>
                body { font-family: system-ui, sans-serif; max-width: 420px; margin: 2rem auto; }
                label { display: block; margin-top: 1rem; }
                input[type=password], input[type=submit] { width: 100%; padding: .6rem; margin-top: .3rem; }
                .btn { margin-top: 1rem; }
            </style>
        </head>
        <body>
            <h1>Reset Your Password</h1>
            <p>Enter your new password below to regain access to your account.</p>
            <form method="post" action="reset_password.php">
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

    if ($token === '' || $p1 === '' || $p2 === '') {
        header('Location: ' . PROJECT_ROOT . '/login.html?err=empty');
        exit;
    }
    if ($p1 !== $p2) {
        header('Location: ' . PROJECT_ROOT . '/login.html?err=nomatch');
        exit;
    }
    if (strlen($p1) < 8) {
        header('Location: ' . PROJECT_ROOT . '/login.html?err=weak');
        exit;
    }

    // Validate token
    $stmt = $mysqli->prepare("
        SELECT LoginID FROM Login
         WHERE ResetToken = ? AND ResetExpiry > NOW()
         LIMIT 1
    ");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $stmt->bind_result($loginId);
    $ok = $stmt->fetch();
    $stmt->close();

    if (!$ok) {
        header('Location: ' . PROJECT_ROOT . '/login.html?err=expired');
        exit;
    }

    // Hash and update password
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
        header('Location: ' . PROJECT_ROOT . '/login.html?err=badreset');
        exit;
    }

    // ✅ Success
    header('Location: ' . PROJECT_ROOT . '/login.html?info=reset_ok');
    exit;

} catch (Throwable $e) {
    error_log("[RESET] 500: " . $e->getMessage());
    http_response_code(500);
    echo "Server error. Please try again later.";
    exit;
}