<?php
declare(strict_types=1);
ob_start();
session_start();

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require_once __DIR__ . '/config.php';
$mysqli = get_db();

function fail_500(string $msg): never {
  error_log("[LOGIN] 500: " . $msg);
  http_response_code(500);
  exit;
}

try {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . PROJECT_ROOT . '/login.html');
    exit;
  }

  $emailRaw = trim((string)($_POST['email'] ?? ''));
  $passIn   = (string)($_POST['password'] ?? '');

  if ($emailRaw === '' || $passIn === '') {
    header('Location: ' . PROJECT_ROOT . '/login.html?err=empty');
    exit;
  }

  if (!filter_var($emailRaw, FILTER_VALIDATE_EMAIL)) {
    header('Location: ' . PROJECT_ROOT . '/login.html?err=invalid');
    exit;
  }

  $sql = "
    SELECT l.LoginID, l.Email, l.Password,
           COALESCE(l.LoginAttempts, 0) AS LoginAttempts,
           COALESCE(l.MustReset, 0) AS MustReset,
           l.UserType AS LoginUserType,
           u.UserID AS UserID,
           u.UserType AS UsersUserType
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
    header('Location: ' . PROJECT_ROOT . '/login.html?err=invalid');
    exit;
  }

  $loginId = (int)$row['LoginID'];
  $attempts = (int)$row['LoginAttempts'];
  $mustReset = (int)$row['MustReset'];

  if ($attempts >= 3 || $mustReset === 1) {
    header('Location: ' . PROJECT_ROOT . '/verify_identity.php?loginid=' . urlencode((string)$loginId) . '&reason=locked');
    exit;
  }

  $stored = (string)$row['Password'];
  $ok = password_verify($passIn, $stored) || $passIn === $stored;

  if ($ok) {
    $upd = $mysqli->prepare("UPDATE Login SET LoginAttempts = 0, MustReset = 0, ResetToken = NULL, ResetExpiry = NULL WHERE LoginID = ?");
    $upd->bind_param("i", $loginId);
    $upd->execute();
    $upd->close();

    session_regenerate_id(true);
    $_SESSION['login_id'] = $loginId;
    if (isset($row['UserID'])) $_SESSION['user_id'] = (int)$row['UserID'];

    $roleSource = $row['UsersUserType'] ?: $row['LoginUserType'];
    $role = strtolower(preg_replace('/\s+/', '', (string)$roleSource));
    $_SESSION['role'] = $role;

    $target = match ($role) {
      'student' => PROJECT_ROOT . '/student_dashboard.php',
      'faculty' => PROJECT_ROOT . '/faculty_dashboard.php',
      'admin' => PROJECT_ROOT . '/admin_dashboard.php',
      'statstaff' => PROJECT_ROOT . '/statstaff_dashboard.php',
      default => PROJECT_ROOT . '/login.html?err=route'
    };

    header('Location: ' . $target, true, 302);
    exit;
  }

  // failed login
  $mysqli->query("UPDATE Login SET LoginAttempts = COALESCE(LoginAttempts,0)+1 WHERE LoginID=$loginId");
  header('Location: ' . PROJECT_ROOT . '/login.html?err=invalid');
  exit;

} catch (Throwable $e) {
  fail_500($e->getMessage());
}
?>