<?php
session_start();

$servername = "localhost";
$username = "postgres";
$password = "!xNq!TRWY.AuD9U";
$dbname = "studentpickup";

$conn = pg_connect("host=$servername dbname=$dbname user=$username password=$password");

if (!$conn) {
    die("Connection failed: " . pg_last_error());
}

if (!isset($_POST['pickup_id']) || empty($_POST['pickup_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Missing pickup ID']);
    exit();
}

$pickupId = $_POST['pickup_id'];

// Cập nhật trạng thái và thời gian đón
$query = "UPDATE public.pickup_requests SET status = 'Đã xác nhận', pickup_time = NOW() WHERE id = $1";
$result = pg_query_params($conn, $query, array($pickupId));

if ($result) {
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => pg_last_error($conn)]);
}


$pickupId = $_POST['pickup_id'];
$studentName = $_POST['student_id'];
$classId = $_POST['class_id'];
$pickupTime = $_POST['pickup_time'];



pg_close($conn);
?>
