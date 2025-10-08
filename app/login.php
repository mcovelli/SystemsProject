<?php
declare(strict_types=1);
ob_start();
session_start();

/* Make mysqli throw readable errors we can log */
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function fail_500(string $msg): never {
  error_log("[LOGIN] 500: " . $msg);
  http_response_code(500);
  exit; // keep body empty in prod; check error_log for details
}

/* ------------------ DB CONFIG ------------------ */
$DB_HOST = "127.0.0.1";
$DB_PORT = 3306;
$DB_USER = "phpuser";
$DB_PASS = "SystemsFall2025!";
$DB_NAME = "University";

try {
  /* ------------------ CONNECT ------------------ */
  $mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, $DB_PORT);
  $mysqli->set_charset('utf8mb4');

  /* ------------------ ONLY ALLOW POST ------------------ */
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.html'); exit;
  }

  /* ------------------ READ FORM FIELDS ------------------ */
  // form uses name="username" and name="password". We'll also accept "loginid".
  $loginRaw = trim((string)($_POST['username'] ?? $_POST['loginid'] ?? ''));
  $passIn   = (string)($_POST['password'] ?? '');

  if ($loginRaw === '' || $passIn === '') {
    header('Location: login.html?err=empty'); exit;
  }

  // Your schema has LoginID INT. If you actually use alphanumeric IDs, tell me and we'll switch to VARCHAR + bind "s".
  if (!ctype_digit($loginRaw)) {
    header('Location: login.html?err=invalid'); exit;
  }
  $loginId = (int)$loginRaw;

  /* ------------------ FETCH LOGIN + ROLE (only existing columns) ------------------ */
  $sql = "
    SELECT 
      l.LoginID, l.UserID, l.Password, l.LoginAttempts,
      l.UserType AS LoginUserType,
      u.UserType AS UsersUserType
    FROM Login l
    LEFT JOIN Users u ON u.UserID = l.UserID
    WHERE l.LoginID = ?
    LIMIT 1
  ";
  $stmt = $mysqli->prepare($sql);
  $stmt->bind_param("i", $loginId);
  $stmt->execute();
  $res = $stmt->get_result();
  $row = $res->fetch_assoc();
  $stmt->close();

  if (!$row) {
    header('Location: login.html?err=invalid'); exit;
  }

  /* ------------------ VERIFY PASSWORD ------------------ */

  // Accept either hashed (after reset) or legacy plaintext
  $stored = (string)$row['Password'];
  $input  = (string)$passIn;

  $looksHashed = str_starts_with($stored, '$2y$') || str_starts_with($stored, '$argon2');
  if ($looksHashed) {
    $ok = password_verify($input, $stored);
  } else {
    $ok = ($input === $stored); // legacy plaintext fallback
  }

  if ($ok) {
    // Reset attempts on success
    $upd = $mysqli->prepare("UPDATE Login SET LoginAttempts = 0, ResetToken = NULL, ResetExpiry = NULL WHERE LoginID = ?");
    $upd->bind_param("i", $loginId);
    $upd->execute();
    $upd->close();

    // Set session + role
    $_SESSION['login_id'] = (int)$row['LoginID'];
    $_SESSION['user_id']  = (int)$row['UserID'];

    $roleSource = $row['UsersUserType'] ?: $row['LoginUserType'];   // 'Student','Faculty','Admin','StatStaff'
    $role = strtolower(preg_replace('/\s+/', '', (string)$roleSource)); // -> 'student','faculty','admin','statstaff'
    $_SESSION['role'] = $role;

    // Routes (absolute paths)
    $routes = [
      'student'   => 'student_dashboard.php',
      'faculty'   => 'faculty_dashboard.php',
      'admin'     => 'admin_dashboard.php',
      'statstaff' => 'statstaff_dashboard.php',
    ];

    // Safety check
    if (!isset($routes[$role]) || !file_exists($_SERVER['DOCUMENT_ROOT'] . $routes[$role])) {
      error_log("[LOGIN] SUCCESS but route missing for role=$role");
      header('Location: login.html?err=route'); exit;
    }

    error_log("[LOGIN] SUCCESS LoginID=$loginId role=$role redirect=" . $routes[$role]);
    header('Location: ' . $routes[$role], true, 302);
    exit;
  }

  /* ------------------ FAIL: increment attempts; on 3 redirect to reset ------------------ */
  $mysqli->begin_transaction();

  // increment attempts
  $inc = $mysqli->prepare("UPDATE Login SET LoginAttempts = COALESCE(LoginAttempts,0) + 1 WHERE LoginID = ?");
  $inc->bind_param("i", $loginId);
  $inc->execute();
  $inc->close();

  // read updated attempts
  $get = $mysqli->prepare("SELECT LoginAttempts FROM Login WHERE LoginID = ? LIMIT 1");
  $get->bind_param("i", $loginId);
  $get->execute();
  $get->bind_result($attempts);
  $get->fetch();
  $get->close();

  if ((int)$attempts >= 3) {
    header('Location: verify_identity.php?loginid=' . urlencode((string)$loginId) . '&reason=failed_attempts');
    exit;
  }

  $mysqli->commit();

  // below threshold
  header('Location: login.html?err=invalid'); exit;

} catch (Throwable $e) {
  // Anything unexpected (missing column, bad SQL, etc.) gets logged here
  fail_500($e->getMessage());
}