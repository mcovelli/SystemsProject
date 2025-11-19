<?php
/* -----------------------------------------------
   Northport University Project Configuration
   Works for both local & EC2 environments
   ----------------------------------------------- */

declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1');

/* ---------- PROJECT ROOT ---------- */
/* Adjust if project is in subfolder (e.g., /SystemsProject) */
define('PROJECT_ROOT', '/SystemsProject');

/* ---------- DATABASE CONNECTION ---------- */
/* Use local credentials by default; override with environment vars if present */
$DB_HOST = getenv('DB_HOST') ?: '127.0.0.1';
$DB_PORT = (int)(getenv('DB_PORT') ?: 3306);
$DB_USER = getenv('DB_USER') ?: 'phpuser';
$DB_PASS = getenv('DB_PASS') ?: 'SystemsFall2025!';
$DB_NAME = getenv('DB_NAME') ?: 'University';

/* ---------- HELPER: Database connection ---------- */
function get_db(): mysqli {
    global $DB_HOST, $DB_PORT, $DB_USER, $DB_PASS, $DB_NAME;

    $mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, $DB_PORT);

    if ($mysqli->connect_error) {
        error_log('DB Connection failed: ' . $mysqli->connect_error);
        die('<h2>Database connection failed.</h2>');
    }

    $mysqli->set_charset('utf8mb4');
    return $mysqli;
}

/* ---------- HELPER: Redirect ---------- */
function redirect(string $path): never {
    header('Location: ' . $path, true, 302);
    exit;
}

/* ---------- HELPER: Debug Redirects  ---------- */

function debug_redirect(string $path): never {
    echo "Redirecting to: " . htmlspecialchars($path);
    exit;
}
