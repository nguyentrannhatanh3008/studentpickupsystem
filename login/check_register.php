<?php
header('Content-Type: application/json');
session_start();

// Tắt hiển thị lỗi PHP trong môi trường sản xuất
ini_set('display_errors', 0);
error_reporting(0);

// Kết nối cơ sở dữ liệu PostgreSQL
$servername = "localhost";
$db_username = "postgres"; // PostgreSQL username
$db_password = "!xNq!TRWY.AuD9U"; // PostgreSQL password
$dbname = "studentpickup"; // Database name

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../vendor/autoload.php';
$config = require __DIR__ . '/../config.php';

$conn_string = "host={$config['database']['host']} dbname={$config['database']['dbname']} user={$config['database']['username']} password={$config['database']['password']}";
$conn = pg_connect($conn_string);

if (!$conn) {
    echo json_encode(['error' => 'Kết nối cơ sở dữ liệu thất bại.']);
    exit();
}

$field = isset($_POST['field']) ? trim($_POST['field']) : '';
$value = isset($_POST['value']) ? trim($_POST['value']) : '';

if ($field === '' || $value === '') {
    echo json_encode(['error' => 'Thiếu thông tin.']);
    exit();
}

$allowed_fields = ['username', 'email', 'phone'];

if (!in_array($field, $allowed_fields)) {
    echo json_encode(['error' => 'Trường không hợp lệ.']);
    exit();
}

$check_query = "SELECT 1 FROM public.\"user\" WHERE $field = $1 LIMIT 1";
$result = pg_query_params($conn, $check_query, array($value));

if ($result === false) {
    echo json_encode(['error' => 'Lỗi truy vấn cơ sở dữ liệu.']);
    exit();
}

if (pg_num_rows($result) > 0) {
    echo json_encode(['exists' => true]);
} else {
    echo json_encode(['exists' => false]);
}

pg_close($conn);
?>
