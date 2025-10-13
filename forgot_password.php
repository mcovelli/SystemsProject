<?php
$mysqli = new mysqli("127.0.0.1", "root", "Marvelman190!", "University");

if ($_SERVER["REQUEST_METHOD"] === "GET" && isset($_GET["token"])) {
    $token = $_GET["token"];
    // Show form to enter new password
    echo '<form action="reset_password.php" method="post">
            <input type="hidden" name="token" value="'.$token.'">
            <input type="password" name="new_password" placeholder="New Password" required>
            <button type="submit">Update Password</button>
          </form>';
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $token = $_POST["token"];
    $newPassword = $_POST["new_password"];

    // Verify token
    $stmt = $mysqli->prepare("SELECT UserID FROM Login WHERE Token = ? AND Expiry > NOW()");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $stmt->bind_result($userId);
    if ($stmt->fetch()) {
        // Update login table (you should hash the password with password_hash())
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $mysqli->query("UPDATE Login SET Password='$hash' WHERE UserID='$userId'");
        echo "Password updated!";
    } else {
        echo "Invalid or expired token.";
    }
}
?>