<?php
declare(strict_types=1);
ob_start();
session_start();

/* Show mysqli errors in the Apache log */
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
  // Expect name="email" and name="password" on the form
  $emailRaw = trim((string)($_POST['email'] ?? ''));
  $passIn   = (string)($_POST['password'] ?? '');

  if ($emailRaw === '' || $passIn === '') {
    header('Location: /SystemsProject/login.html?err=empty');
    exit;
  }

  // Basic email validation
  if (!filter_var($emailRaw, FILTER_VALIDATE_EMAIL)) {
    header('Location: /SystemsProject/login.html?err=invalid');
    exit;
  }

  /* ------------------ FETCH LOGIN ROW BY EMAIL ------------------ */
  // Assumes Login.Email exists and references Users.Email (UNIQUE)
  $sql = "
    SELECT
      l.LoginID,
      l.Email,
      l.Password,
      COALESCE(l.LoginAttempts, 0) AS LoginAttempts,
      l.UserType          AS LoginUserType,
      u.UserID            AS UserID,
      u.UserType          AS UsersUserType
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
    // No login row for that email
    header('Location: /SystemsProject/login.html?err=invalid');
    exit;
  }

  $loginId = (int)$row['LoginID'];

  /* ------------------ VERIFY PASSWORD ------------------ */
  $stored = (string)$row['Password'];
  $looksHashed = str_starts_with($stored, '$2y$') || str_starts_with($stored, '$argon2');
  $ok = $looksHashed ? password_verify($passIn, $stored) : ($passIn === $stored);

  if ($ok) {
    // Reset attempts on success + clear reset fields if present
    $upd = $mysqli->prepare("UPDATE Login SET LoginAttempts = 0, ResetToken = NULL, ResetExpiry = NULL WHERE LoginID = ?");
    $upd->bind_param("i", $loginId);
    $upd->execute();
    $upd->close();

    // Harden session
    session_regenerate_id(true);

    // Set session user identifiers
    $_SESSION['login_id'] = $loginId;
    // Prefer Users.UserID if present (join by email). If null, leave unset or handle gracefully.
    if (isset($row['UserID']) && $row['UserID'] !== null) {
      $_SESSION['user_id'] = (int)$row['UserID'];
    }

    // Role: prefer Users.UserType; fallback to Login.UserType
    $roleSource = $row['UsersUserType'] ?: $row['LoginUserType'];   // 'Student','Faculty','Admin','StatStaff'
    $role = strtolower(preg_replace('/\s+/', '', (string)$roleSource)); // -> 'student','faculty','admin','statstaff'
    $_SESSION['role'] = $role;

    $routes = [
      'student'   => '/SystemsProject/student_dashboard.php',
      'faculty'   => '/SystemsProject/faculty_dashboard.php',
      'admin'     => '/SystemsProject/admin_dashboard.php',
      'statstaff' => '/SystemsProject/statstaff_dashboard.php',
    ];

    if (!isset($routes[$role])) {
      error_log("[LOGIN] SUCCESS but unknown role=$role");
      header('Location: /SystemsProject/login.html?err=route');
      exit;
    }

    $target = $routes[$role];

    // When $target is an absolute web path (/SystemsProject/...), use DOCUMENT_ROOT to check file existence
    $abs = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/') . $target;
    if (!is_file($abs)) {
      error_log("[LOGIN] SUCCESS but file missing for role=$role path=$abs");
      header('Location: /SystemsProject/login.html?err=route');
      exit;
    }

    error_log("[LOGIN] SUCCESS email={$row['Email']} loginId=$loginId role=$role redirect=$target");
    header('Location: ' . $target, true, 302);
    exit;

  } else {
    /* ------------------ FAIL: increment attempts; on 3 → identity verify ------------------ */
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
      header('Location: /SystemsProject/verify_identity.php?loginid=' . urlencode((string)$loginId) . '&reason=failed_attempts');
      exit;
    }

    header('Location: /SystemsProject/login.html?err=invalid');
    exit;
  }

} catch (Throwable $e) {
  fail_500($e->getMessage());
}