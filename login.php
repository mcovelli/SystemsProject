<?php
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1');
session_start();
require_once __DIR__ . '/config.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/* ---------- only POST ---------- */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(PROJECT_ROOT . '/login.html');
}

/* ---------- Regenerate session ID ---------- */
session_regenerate_id(true);

/* ---------- inputs ---------- */
$email    = trim($_POST['email'] ?? '');
$password = (string)($_POST['password'] ?? '');

if ($email === '' || $password === '') {
    redirect(PROJECT_ROOT . '/login.html?err=empty');
}

try {
    $mysqli = get_db();

    /* ---------- load user by email ---------- */
    $stmt = $mysqli->prepare("
        SELECT 
            u.UserID,
            u.Email,
            u.UserType,
            u.Status,
            u.FirstName,
            u.LastName,
            l.LoginID,
            l.Password AS PasswordHash,
            l.LoginAttempts,
            l.MustReset
        FROM Users u
        INNER JOIN Login l ON l.LoginID = u.UserID
        WHERE u.Email = ?
        LIMIT 1
    ");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user) {
        redirect(PROJECT_ROOT . '/login.html?err=invalid');
    }

    // status check
    if (strtoupper((string)$user['Status']) !== 'ACTIVE') {
        redirect(PROJECT_ROOT . '/login.html?err=inactive');
    }

    $loginID   = (int)$user['LoginID'];
    $attempts  = (int)$user['LoginAttempts'];
    $mustReset = (int)$user['MustReset'];

    /* ---------- BLOCK LOCKED USERS ---------- */
    if ($mustReset === 1 || $attempts >= 3) {
        redirect(PROJECT_ROOT . "/login.html?err=locked");
    }

    /* ---------- password verify ---------- */
    $stored = (string)$user['PasswordHash'];
    $is_valid = false;

    if ($stored !== '' && str_starts_with($stored, '$2y$')) { // bcrypt
        if (password_verify($password, $stored)) {
            $is_valid = true;

            if (password_needs_rehash($stored, PASSWORD_BCRYPT)) {
                $new = password_hash($password, PASSWORD_BCRYPT);
                $up  = $mysqli->prepare("UPDATE Login SET Password = ? WHERE LoginID = ?");
                $up->bind_param('si', $new, $loginID);
                $up->execute();
                $up->close();
            }
        }

    } elseif ($stored !== '' && hash('sha256', $password) === strtolower($stored)) {
        // legacy SHA-256 password (auto upgrade)
        $is_valid = true;
        $new = password_hash($password, PASSWORD_BCRYPT);
        $up  = $mysqli->prepare("UPDATE Login SET Password = ? WHERE LoginID = ?");
        $up->bind_param('si', $new, $loginID);
        $up->execute();
        $up->close();
    }

    /* ---------- invalid password ---------- */
    if (!$is_valid) {

        $attempts++;
        $upd = $mysqli->prepare("UPDATE Login SET LoginAttempts = ? WHERE LoginID = ?");
        $upd->bind_param("ii", $attempts, $loginID);
        $upd->execute();
        $upd->close();

        if ($attempts >= 3) {
            $lock = $mysqli->prepare("UPDATE Login SET MustReset = 1 WHERE LoginID = ?");
            $lock->bind_param("i", $loginID);
            $lock->execute();
            $lock->close();

            redirect(PROJECT_ROOT . "/login.html?err=locked");
        }

        redirect(PROJECT_ROOT . "/login.html?err=invalid");
    }

    /* ---------- SUCCESSFUL LOGIN ---------- */

    // Reset failed attempts
    $reset = $mysqli->prepare("UPDATE Login SET LoginAttempts = 0 WHERE LoginID = ?");
    $reset->bind_param("i", $loginID);
    $reset->execute();
    $reset->close();

    // set session
    $_SESSION['login_id']    = $loginID;
    $_SESSION['user_type']   = $user['UserType'];
    $_SESSION['user_id']     = (int)$user['UserID'];
    $_SESSION['role']        = strtolower($user['UserType']);
    $_SESSION['first_name']  = $user['FirstName'];
    $_SESSION['last_name']   = $user['LastName'];

    /* ---------- role routing ---------- */
    switch ($_SESSION['role']) {
        case 'student': redirect(PROJECT_ROOT . '/student_dashboard.php'); break;
        case 'faculty': redirect(PROJECT_ROOT . '/faculty_dashboard.php'); break;
        case 'statstaff': redirect(PROJECT_ROOT . '/statstaff_dashboard.php'); break;

        case 'admin':
            $stmt = $mysqli->prepare("SELECT SecurityType FROM Admin WHERE AdminID = ?");
            $stmt->bind_param('i', $_SESSION['user_id']);
            $stmt->execute();
            $sub = $stmt->get_result()->fetch_assoc();
            $_SESSION['admin_type'] = strtolower($sub['SecurityType'] ?? 'view');
            $stmt->close();

            if ($_SESSION['admin_type'] === 'update') {
                redirect(PROJECT_ROOT . '/update_admin_dashboard.php');
            } else {
                redirect(PROJECT_ROOT . '/view_admin_dashboard.php');
            }
            break;

        default:
            redirect(PROJECT_ROOT . '/login.html?err=role');
    }

} catch (Throwable $e) {
    http_response_code(500);
    echo "<pre style='color:#b00;font-weight:700'>LOGIN ERROR: Internal Server Error" "</pre>";
    echo "<pre> Internal Server Error" "</pre>";
    exit;
}

if (isset($_GET['err'])): ?>
        <?php
            $err = $_GET['err'];
            $msg = '';

            switch ($err) {
                case 'empty':
                    $msg = "Please enter both email and password.";
                    break;

                case 'invalid':
                    $msg = "Invalid email or password.";
                    break;

                case 'inactive':
                    $msg = "Your account is inactive. Contact support.";
                    break;

                case 'locked':
                    $msg = "Too many failed attempts. Your account is locked.<br>You must reset your password to log in again.";
                    break;

                case 'role':
                    $msg = "Your account has an unrecognized role type.";
                    break;

                default:
                    $msg = "An unknown error occurred.";
                    break;
            }
        ?>
        <div class="alert error"><?= $msg ?></div>
    <?php endif; ?>
