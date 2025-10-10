<?php
declare(strict_types=1);
ob_start();
session_start();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/* ---------------- DB CONFIG ---------------- */
$DB_HOST = "127.0.0.1";
$DB_PORT = 3306;
$DB_USER = "phpuser";
$DB_PASS = "SystemsFall2025!";
$DB_NAME = "University";

function loghit(string $msg): void { error_log("[VERIFY_ID] $msg"); }

/** Normalize various date inputs to Y-m-d; return '' if invalid */
function normalize_dob(string $raw): string {
  $raw = trim($raw);
  if ($raw === '') return '';
  if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) return $raw; // already YYYY-MM-DD

  $fmts = ['m/d/Y','m-d-Y','n/j/Y','n-j-Y','Y/m/d','Y.m.d','M j, Y','F j, Y'];
  foreach ($fmts as $fmt) {
      $dt = DateTime::createFromFormat($fmt, $raw);
      if ($dt && $dt->format($fmt) === $raw) return $dt->format('Y-m-d');
  }
  $t = strtotime($raw);
  return $t ? date('Y-m-d', $t) : '';
}

function render_form(string $err = '', string $reason = '',
                     string $first = '', string $last = '', string $email = '', string $dob = ''): void {
  $errHtml = $err ? '<p style="color:#b00020">'.htmlspecialchars($err, ENT_QUOTES).'</p>' : '';
  $reason  = htmlspecialchars($reason, ENT_QUOTES);
  $first   = htmlspecialchars($first, ENT_QUOTES);
  $last    = htmlspecialchars($last, ENT_QUOTES);
  $email   = htmlspecialchars($email, ENT_QUOTES);
  $dob     = htmlspecialchars($dob, ENT_QUOTES);

  echo <<<HTML
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Verify Your Identity</title>
  <style>
    body { font-family: system-ui, Arial, sans-serif; max-width: 520px; margin: 40px auto; }
    form { display: grid; gap: 12px; }
    label { display: grid; gap: 6px; }
    input { padding: 10px; font-size: 16px; }
    button { padding: 10px; font-size: 16px; cursor: pointer; }
    .hint { color:#555; font-size: 14px; }
  </style>
</head>
<body>
  <h2>Verify Your Identity</h2>
  $errHtml
  <p class="hint">Enter your First Name, Last Name, Email, and Date of Birth to continue.</p>
  <form method="post" action="/SystemsProject/verify_identity.php">
    <input type="hidden" name="reason" value="$reason">

    <label>First Name
      <input type="text" name="first_name" required value="$first" autocomplete="given-name">
    </label>

    <label>Last Name
      <input type="text" name="last_name" required value="$last" autocomplete="family-name">
    </label>

    <label>Email
      <input type="email" name="email" required value="$email" autocomplete="email">
    </label>

    <label>Date of Birth (YYYY-MM-DD)
      <input type="text" name="dob" placeholder="YYYY-MM-DD" required value="$dob" autocomplete="bday">
    </label>

    <button type="submit">Continue</button>
  </form>
  <p class="hint" style="margin-top:10px;"><a href="/SystemsProject/login.html">Back to login</a></p>
</body>
</html>
HTML;
}

try {
  $mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, $DB_PORT);
  $mysqli->set_charset('utf8mb4');

  if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Prefill email from forgot_password.html if present
    $prefillEmail = (string)($_GET['email'] ?? '');
    $reason       = (string)($_GET['reason'] ?? '');
    loghit("GET email={$prefillEmail}");
    render_form('', $reason, '', '', $prefillEmail, '');
    exit;
  }

  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reason = (string)($_POST['reason'] ?? '');
    $first  = trim((string)($_POST['first_name'] ?? ''));
    $last   = trim((string)($_POST['last_name'] ?? ''));
    $email  = trim((string)($_POST['email'] ?? ''));
    $dobRaw = trim((string)($_POST['dob'] ?? ''));

    // Basic validation
    if ($first === '' || $last === '' || $email === '' || $dobRaw === '') {
      render_form('Please fill out all fields.', $reason, $first, $last, $email, $dobRaw); exit;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      render_form('Please enter a valid email address.', $reason, $first, $last, $email, $dobRaw); exit;
    }
    $dob = normalize_dob($dobRaw);
    if ($dob === '') {
      render_form('DOB format not recognized. Use YYYY-MM-DD (e.g., 1999-04-30).',
                  $reason, $first, $last, $email, $dobRaw); exit;
    }

    // Verify identity via Users + Login (joined by Email)
    $stmt = $mysqli->prepare("
      SELECT l.LoginID
        FROM Users u
        JOIN Login l ON l.Email = u.Email
       WHERE u.FirstName = ?
         AND u.LastName  = ?
         AND u.Email     = ?
         AND u.DOB       = ?
       LIMIT 1
    ");
    $stmt->bind_param("ssss", $first, $last, $email, $dob);
    $stmt->execute();
    $stmt->bind_result($loginId);
    $ok = $stmt->fetch();
    $stmt->close();

    if (!$ok) {
      loghit("IDENTITY FAIL email='$email' first='$first' last='$last' dob=$dob");
      render_form('We could not verify those details. Please check and try again.',
                  $reason, $first, $last, $email, $dobRaw);
      exit;
    }

    // Generate reset token & expiry
    $tok = bin2hex(random_bytes(32));          // 64-char hex
    $exp = date('Y-m-d H:i:s', time() + 3600); // 1 hour

    // Save token on the matched Login row
    $set = $mysqli->prepare("
      UPDATE Login
         SET ResetToken = ?, ResetExpiry = ?, LoginAttempts = 0
       WHERE LoginID = ?
       LIMIT 1
    ");
    $set->bind_param("ssi", $tok, $exp, $loginId);
    $set->execute();
    $set->close();

    loghit("IDENTITY OK email='$email' loginid=$loginId -> reset_password.php");
    header('Location: /SystemsProject/reset_password.php?token=' . urlencode($tok));
    exit;
  }

  http_response_code(405);
  echo 'Method not allowed';
  exit;

} catch (Throwable $e) {
  loghit("ERROR: ".$e->getMessage());
  render_form('Something went wrong while verifying. Please try again.');
  exit;
}