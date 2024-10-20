<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

$servername = "localhost";
$username = "postgres";
$password = "!xNq!TRWY.AuD9U";
$dbname = "studentpickup";

// Kết nối tới cơ sở dữ liệu PostgreSQL
$conn = pg_connect("host=$servername dbname=$dbname user=$username password=$password");

if (!$conn) {
    echo json_encode(['status' => 'error', 'message' => 'Kết nối thất bại: ' . pg_last_error()]);
    exit();
}

// Lấy dữ liệu từ yêu cầu POST
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['pickup_id']) || !isset($data['status'])) {
    echo json_encode(['status' => 'error', 'message' => 'Dữ liệu không hợp lệ']);
    exit();
}

$pickup_id = intval($data['pickup_id']);
$status = pg_escape_string($data['status']);

// Cập nhật trạng thái trong cơ sở dữ liệu
$updateQuery = "UPDATE public.pickup_history SET status = '$status' WHERE id = $pickup_id AND user_id = $1";
$result = pg_query_params($conn, $updateQuery, array($_SESSION['userid']));

if ($result) {
    echo json_encode(['status' => 'success', 'message' => 'Cập nhật thành công']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Cập nhật thất bại: ' . pg_last_error()]);
}

pg_close($conn);
?>
