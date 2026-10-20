<?php

$is_localhost = php_sapi_name() === 'cli'
             || in_array($_SERVER['SERVER_NAME'] ?? '', ['localhost', '127.0.0.1', '::1'])
             || strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false;

if ($is_localhost) {

    $host = 'localhost';
    $db   = 'nsbm_studycircle';
    $user = 'root';
    $pass = '';
} else {

    $host = 'sql107.infinityfree.com';
    $db   = 'if0_43342616_nsbm';
    $user = 'if0_43342616';
    $pass = 'NnQ3T7D4tR6PSyF';
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>
