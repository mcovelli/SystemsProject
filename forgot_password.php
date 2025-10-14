<?php
require_once __DIR__ . '/config.php';
$mysqli = get_db();

if ($_SERVER["REQUEST_METHOD"] === "GET" && isset($_GET["token"])) {
    $token = $_GET["token"];
    echo '<form action="' . PROJECT_ROOT . '/reset_password.php" method="post">
            <input type="hidden" name="token" value="' . htmlspecialchars($token) . '">
            <input type="password" name="new_password" placeholder="New Password" required>
            <button type="submit">Update Password</button>
          </form>';
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $token = $_POST["token"];
    $newPassword = $_POST["new_password"];

    $stmt = $mysqli->prepare("SELECT UserID FROM Login WHERE Token = ? AND Expiry > NOW()");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $stmt->bind_result($userId);
    if ($stmt->fetch()) {
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $upd = $mysqli->prepare("UPDATE Login SET Password = ? WHERE UserID = ?");
        $upd->bind_param("si", $hash, $userId);
        $upd->execute();
        echo "Password updated!";
    } else {
        echo "Invalid or expired token.";
    }
}
?>