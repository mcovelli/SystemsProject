<?php
session_start(); require_once __DIR__ . '/config.php';
$m = get_db(); $id = (int)($_GET['as'] ?? 2);
$r = $m->query("SELECT UserID, FirstName, LastName, UserType FROM Users WHERE UserID = $id")->fetch_assoc();
$_SESSION['user_id']=(int)$r['UserID']; $_SESSION['login_id']=(int)$r['UserID'];
$_SESSION['user_type']=$r['UserType']; $_SESSION['role']=strtolower($r['UserType']);
$_SESSION['first_name']=$r['FirstName']; $_SESSION['last_name']=$r['LastName'];
$_SESSION['admin_type']='update';
header('Location: ' . ($_GET['to'] ?? 'ViewMajors.php'));
