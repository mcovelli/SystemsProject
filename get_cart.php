<?php
session_start();
require_once __DIR__ . '/config.php';

header("Access-Control-Allow-Origin: " . ($_SERVER['HTTP_ORIGIN'] ?? '*'));
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json");
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Ensure session cart exists
if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Always return full cart, even if empty
echo json_encode([
    'cart' => $_SESSION['cart'],
    'count' => count($_SESSION['cart']),
    'message' => empty($_SESSION['cart']) ? 'Cart is empty.' : 'Cart loaded successfully.'
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

exit;