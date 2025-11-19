<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/config.php';

$_SESSION = [];
session_destroy();
header('Location: ' . PROJECT_ROOT . '/login.html?info=logged_out');
exit;
?>