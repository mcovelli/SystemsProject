<?php
declare(strict_types=1);
ob_start();
session_start();

/* Log all mysqli errors to Apache error log */
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function fail_500(string $msg): never {
  error_log("[LOGIN] 500: " . $msg);
  http_response_code(500);
  exit;
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
    header('Location: /SystemsProject/login.html');
    exit;
  }

  /* ------------------ READ FORM FIELDS ------------------ */
  $emailRaw = trim((string)($_POST['email'] ?? ''));
  $passIn   = (string)($_POST['password'] ?? '');

  if ($emailRaw === '' || $passIn === '') {
    header('Location: /SystemsProject/login.html?err=empty');
    exit;
  }

  if (!filter_var($emailRaw, FILTER_VALIDATE_EMAIL)) {
    header('Location: /SystemsProject/login.html?err=invalid');
    exit;
  }

  /* ------------------ FETCH LOGIN ROW ------------------ */
  $sql = "
    SELECT
      l.LoginID,
      l.Email,
      l.Password,
      COALESCE(l.LoginAttempts, 0) AS LoginAttempts,
      COALESCE(l.MustReset, 0)     AS MustReset,
      l.UserType                   AS LoginUserType,
      u.UserID                     AS UserID,
      u.UserType                   AS UsersUserType
    FROM Login l
    LEFT JOIN Users u ON u.Email = l.Email
    WHERE l.Email = ?
    LIMIT 1
  ";
  $stmt = $mysqli->prepare($sql);
  $stmt->bind_param("s", $emailRaw);
  $stmt->execute();
  $res = $stmt->get_result();
  $row = $res->fetch_assoc();
  $stmt->close();

  if (!$row) {
    header('Location: /SystemsProject/login.html?err=invalid');
    exit;
  }

  $loginId = (int)$row['LoginID'];
  $attempts = (int)$row['LoginAttempts'];
  $mustReset = (int)$row['MustReset'];

  /* ------------------ CHECK LOCKOUT STATUS ------------------ */
  if ($attempts >= 3 || $mustReset === 1) {
    // user is locked out until password reset
    header('Location: /SystemsProject/verify_identity.php?loginid=' . urlencode((string)$loginId) . '&reason=locked');
    exit;
  }

  /* ------------------ VERIFY PASSWORD ------------------ */
  $stored = (string)$row['Password'];
  $looksHashed = str_starts_with($stored, '$2y$') || str_starts_with($stored, '$argon2');
  $ok = $looksHashed ? password_verify($passIn, $stored) : ($passIn === $stored);

  if ($ok) {
    /* ---------- SUCCESSFUL LOGIN ---------- */
    $upd = $mysqli->prepare("
      UPDATE Login
      SET LoginAttempts = 0, MustReset = 0, ResetToken = NULL, ResetExpiry = NULL
      WHERE LoginID = ?
    ");
    $upd->bind_param("i", $loginId);
    $upd->execute();
    $upd->close();

    session_regenerate_id(true);
    $_SESSION['login_id'] = $loginId;
    if (isset($row['UserID']) && $row['UserID'] !== null) {
      $_SESSION['user_id'] = (int)$row['UserID'];
    }

    $roleSource = $row['UsersUserType'] ?: $row['LoginUserType'];
    $role = strtolower(preg_replace('/\s+/', '', (string)$roleSource));
    $_SESSION['role'] = $role;

    function target_for_role(string $role): string { 
      switch (strtolower($role)) {
        case 'student':   return '/SystemsProject/student_dashboard.php';
        case 'faculty':   return '/SystemsProject/faculty_dashboard.php';
        case 'admin':     return '/SystemsProject/admin_dashboard.php';
        case 'statstaff': return '/SystemsProject/statstaff_dashboard.php';
        default:          return '/SystemsProject/login.html?err=route';
      }
    }

    $target = target_for_role($role ?? '');
    if (strpos($target, '/SystemsProject/') !== 0) {
      error_log("[LOGIN] Unsafe redirect target: " . var_export($target, true));
      $target = '/SystemsProject/login.html?err=route';
    }

    error_log("[LOGIN] SUCCESS email={$row['Email']} loginId=$loginId role=$role redirect=$target");
    header('Location: ' . $target, true, 302);
    exit;

  } else {
    /* ---------- FAILED LOGIN ---------- */
    $mysqli->begin_transaction();

    $inc = $mysqli->prepare("UPDATE Login SET LoginAttempts = COALESCE(LoginAttempts,0) + 1 WHERE LoginID = ?");
    $inc->bind_param("i", $loginId);
    $inc->execute();
    $inc->close();

    $get = $mysqli->prepare("SELECT LoginAttempts FROM Login WHERE LoginID = ? LIMIT 1");
    $get->bind_param("i", $loginId);
    $get->execute();
    $get->bind_result($attempts);
    $get->fetch();
    $get->close();

    $mysqli->commit();

    if ((int)$attempts >= 3) {
      // Lock user and require password reset
      $lock = $mysqli->prepare("UPDATE Login SET MustReset = 1 WHERE LoginID = ?");
      $lock->bind_param("i", $loginId);
      $lock->execute();
      $lock->close();

      header('Location: /SystemsProject/verify_identity.php?loginid=' . urlencode((string)$loginId) . '&reason=locked');
      exit;
    }

    header('Location: /SystemsProject/login.html?err=invalid');
    exit;
  }

} catch (Throwable $e) {
  fail_500($e->getMessage());
}