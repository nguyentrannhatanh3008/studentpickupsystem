<?php
$host = 'localhost';
$db   = 'studentpickup';
$user = 'postgres';
$pass = '!xNq!TRWY.AuD9U';
$port = "5432";

$dsn = "pgsql:host=$host;port=$port;dbname=$db;";

try {
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (PDOException $e) {
    echo 'Connection failed: ' . $e->getMessage();
    exit();
}
?>
