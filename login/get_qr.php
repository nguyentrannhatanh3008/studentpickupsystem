<?php
// get_qr.php

$servername = "localhost";
$username = "postgres";
$password = "!xNq!TRWY.AuD9U";
$dbname = "studentpickup";

// Kiểm tra tham số student_id
if (!isset($_GET['student_id'])) {
    http_response_code(400);
    echo "Thiếu tham số student_id.";
    exit();
}

$student_id = intval($_GET['student_id']);

// Kết nối đến cơ sở dữ liệu PostgreSQL
$conn = pg_connect("host=$servername dbname=$dbname user=$username password=$password");

// Kiểm tra kết nối
if (!$conn) {
    http_response_code(500);
    echo "Kết nối thất bại: " . pg_last_error();
    exit();
}

// Truy vấn lấy mã QR từ cơ sở dữ liệu
$query = "SELECT qr_image FROM public.student_qrcodes WHERE student_id = $1";
$result = pg_query_params($conn, $query, array($student_id));

if ($result === false) {
    http_response_code(500);
    echo "Lỗi trong truy vấn: " . pg_last_error($conn);
    pg_close($conn);
    exit();
}

if (pg_num_rows($result) > 0) {
    $row = pg_fetch_assoc($result);
    $qr_image = $row['qr_image'];
    
    // Xuất hình ảnh với header thích hợp
    header("Content-Type: image/png");
    echo pg_unescape_bytea($qr_image);
} else {
    http_response_code(404);
    echo "Không tìm thấy mã QR cho student_id: $student_id";
}

pg_close($conn);
?>
