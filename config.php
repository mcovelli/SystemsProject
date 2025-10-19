<?php
declare(strict_types=1);

/* ---------- Project Root ---------- */
/**
 * Adjusts automatically depending on environment.
 * Works both locally (XAMPP) and on EC2.
 */
if (!defined('PROJECT_ROOT')) {
    define('PROJECT_ROOT', '/SystemsProject');
}

/* ---------- Database Connection ---------- */
/**
 * Returns a connected mysqli instance.
 * 
 * Uses consistent credentials across local and remote setups.
 */
function get_db(): mysqli {
    $DB_HOST = "127.0.0.1";
    $DB_PORT = 3306;
    $DB_USER = "phpuser";
    $DB_PASS = "SystemsFall2025!";
    $DB_NAME = "University";

    $mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, $DB_PORT);

    if ($mysqli->connect_errno) {
        error_log("[DB ERROR] Connection failed: " . $mysqli->connect_error);
        http_response_code(500);
        exit("Database connection failed.");
    }

    $mysqli->set_charset('utf8mb4');
    return $mysqli;
}

/* ---------- Global Redirect Helper ---------- */
function redirect(string $path): never {
    header('Location: ' . $path, true, 302);
    exit;
}

/* ---------- Global Error Helper ---------- */
function fail_500(string $msg): never {
    error_log('[FATAL] ' . $msg);
    http_response_code(500);
    echo "Server error. Please try again later.";
    exit;
}
?>