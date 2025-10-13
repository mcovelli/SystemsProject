<?php
declare(strict_types=1);
ob_start();
session_start();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$DB_HOST = "127.0.0.1";
$DB_PORT = 3306;
$DB_USER = "root";
$DB_PASS = "Marvelman190!";
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

function render_form(string $err = '', string $prefillLoginId = '', string $reason = '',
                     string $first = '', string $last = '', string $email = '', string $dob = ''): void {
  $errHtml  = $err ? '<p style="color:#b00020">'.htmlspecialchars($err, ENT_QUOTES).'</p>' : '';
  $prefillLoginId = htmlspecialchars($prefillLoginId, ENT_QUOTES);
  $reason = htmlspecialchars($reason, ENT_QUOTES);
  $first  = htmlspecialchars($first, ENT_QUOTES);
  $last   = htmlspecialchars($last, ENT_QUOTES);
  $email  = htmlspecialchars($email, ENT_QUOTES);
  $dob    = htmlspecialchars($dob, ENT_QUOTES);

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
  <p class="hint">Enter your LoginID, First Name, Last Name, Email, and Date of Birth to continue.</p>
  <form method="post" action="verify_identity.php">
    <input type="hidden" name="reason" value="$reason">

    <label>LoginID
      <input type="text" name="loginid" required value="$prefillLoginId" inputmode="numeric">
    </label>

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
      <input type="text" name="dob" placeholder="YYYY-MM-DD" required value="$dob">
    </label>

    <button type="submit">Continue</button>
  </form>
  <p class="hint" style="margin-top:10px;"><a href="login.html">Back to login</a></p>
</body>
</html>
HTML;
}

try {
  $mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, $DB_PORT);
  $mysqli->set_charset('utf8mb4');

  if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    loghit("GET from {$_SERVER['REMOTE_ADDR']} loginid=".(string)($_GET['loginid'] ?? ''));
    $prefill = (string)($_GET['loginid'] ?? '');
    $reason  = (string)($_GET['reason'] ?? '');
    render_form('', $prefill, $reason);
    exit;
  }

  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    loghit("POST from {$_SERVER['REMOTE_ADDR']}");
    $reason  = (string)($_POST['reason'] ?? '');
    $loginid = trim((string)($_POST['loginid'] ?? ''));
    $first   = trim((string)($_POST['first_name'] ?? ''));
    $last    = trim((string)($_POST['last_name'] ?? ''));
    $email   = trim((string)($_POST['email'] ?? ''));
    $dobRaw  = trim((string)($_POST['dob'] ?? ''));

    // Basic validation
    if ($loginid === '' || $first === '' || $last === '' || $email === '' || $dobRaw === '') {
      render_form('Please fill out all fields.', $loginid, $reason, $first, $last, $email, $dobRaw); exit;
    }
    if (!ctype_digit($loginid)) {
      render_form('LoginID must be numeric.', $loginid, $reason, $first, $last, $email, $dobRaw); exit;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      render_form('Please enter a valid email address.', $loginid, $reason, $first, $last, $email, $dobRaw); exit;
    }
    $dob = normalize_dob($dobRaw);
    if ($dob === '') {
      render_form('DOB format not recognized. Use YYYY-MM-DD (e.g., 1999-04-30).',
                  $loginid, $reason, $first, $last, $email, $dobRaw); exit;
    }

    $loginIdInt = (int)$loginid;

    // Verify Users(FirstName, LastName, Email, DOB) + Login(LoginID -> UserID)
    // This assumes Users table has columns: FirstName, LastName, Email, DOB, UserID
    $stmt = $mysqli->prepare("
      SELECT 1
      FROM Users u
      JOIN Login l ON l.UserID = u.UserID
      WHERE l.LoginID = ?
        AND u.FirstName = ?
        AND u.LastName  = ?
        AND u.Email     = ?
        AND u.DOB       = ?
      LIMIT 1
    ");
    $stmt->bind_param("issss", $loginIdInt, $first, $last, $email, $dob);
    $stmt->execute();
    $ok = (bool)$stmt->get_result()->fetch_row();
    $stmt->close();

    if (!$ok) {
      loghit("IDENTITY FAIL loginid=$loginIdInt first='$first' last='$last' email='$email' dob=$dob");
      render_form('We could not verify those details. Please check and try again.',
                  (string)$loginIdInt, $reason, $first, $last, $email, $dobRaw);
      exit;
    }

    // Generate reset token and expiry
    $tok = bin2hex(random_bytes(32));          // 64-char hex
    $exp = date('Y-m-d H:i:s', time() + 3600); // 1 hour

    // Ensure your Login table has ResetToken (CHAR(64) NULL) and ResetExpiry (DATETIME NULL)
    $set = $mysqli->prepare("UPDATE Login SET ResetToken = ?, ResetExpiry = ?, LoginAttempts = 0 WHERE LoginID = ?");
    $set->bind_param("ssi", $tok, $exp, $loginIdInt);
    $set->execute();
    $set->close();

    loghit("IDENTITY OK loginid=$loginIdInt -> reset_password.php");
    header('Location: reset_password.php?token=' . urlencode($tok));
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