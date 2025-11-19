<?php
session_start();

// Ensure the cart exists
if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$crn = $_POST['crn'] ?? '';
$semester = urlencode($_POST['semester'] ?? ($_GET['Semester'] ?? ''));
$dept     = urlencode($_POST['dept'] ?? ($_GET['dept'] ?? ''));

// ✅ If CRN is provided, remove that course from cart
if ($crn !== '') {
    $_SESSION['cart'] = array_values(array_filter($_SESSION['cart'], function($item) use ($crn) {
        return (string)($item['crn'] ?? '') !== (string)$crn;
    }));
}

// ✅ Build redirect URL preserving filters
$redirectUrl = "Add_Drop_courses.php";
$queryParts = [];

if ($semester !== '') $queryParts[] = "Semester={$semester}";
if ($dept !== '')     $queryParts[] = "dept={$dept}";

if ($queryParts) {
    $redirectUrl .= '?' . implode('&', $queryParts);
}

header("Location: {$redirectUrl}");
exit;
?>