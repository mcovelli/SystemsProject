<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<?php $nu_title = 'Forgot Password'; require __DIR__ . '/partials/head.php'; ?>
<body>
  <h1>Forgot Password</h1>
  <p>To reset your password, please verify your identity.</p>
  <form action="verify_identity.php" method="get">
    <button type="submit">Verify My Identity</button>
  </form>
  <button><a href="login.html">Back to Login</a></button>
</body>
</html>