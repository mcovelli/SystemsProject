<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/config.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $mysqli = get_db();
    $mysqli->set_charset('utf8mb4');

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $token = $_GET['token'] ?? '';
        if ($token === '') {
            http_response_code(400);
            exit('Missing token');
        }

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
          </style>
        </head>
        <body>
          <h1>Reset Your Password</h1>
          <form method="post" action="reset_password.php">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token, ENT_QUOTES); ?>">
            <label for="p1">New Password</label>
            <input id="p1" name="p1" type="password" required minlength="8">
            <label for="p2">Confirm Password</label>
            <input id="p2" name="p2" type="password" required minlength="8">
            <input type="submit" value="Set New Password">
          </form>
        </body>
        </html>
        <?php
        exit;
    }

    // --- POST ---
    $token = trim($_POST['token'] ?? '');
    $p1 = $_POST['p1'] ?? '';
    $p2 = $_POST['p2'] ?? '';

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

    $hash = password_hash($p1, PASSWORD_BCRYPT);
    $up = $mysqli->prepare("
        UPDATE Login
        SET Password = ?, ResetToken = NULL, ResetExpiry = NULL, LoginAttempts = 0, MustReset = 0
        WHERE LoginID = ?
    ");
    $up->bind_param('si', $hash, $loginId);
    $up->execute();
    $up->close();

    header('Location: ' . PROJECT_ROOT . '/login.html?msg=reset_success');
    exit;

} catch (Throwable $e) {
    error_log("[RESET_PW] " . $e->getMessage());
    http_response_code(500);
    exit("Server error. Please try again later.");
}
?>