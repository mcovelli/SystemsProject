<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Forgot Password | Northport University</title>
  <style>
            body { font-family: system-ui, sans-serif; max-width: 480px; margin: 2rem auto; }
            input, button { width: 100%; padding: .6rem; margin-top: .6rem; }
          </style>
</head>
<body>
  <h1>Forgot Password</h1>
  <p>To reset your password, please verify your identity.</p>
  <form action="verify_identity.php" method="get">
    <button type="submit">Verify My Identity</button>
  </form>
  <button><a href="login.html">Back to Login</a></button>
</body>
</html>