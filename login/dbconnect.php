<?php
$db_host = "localhost";
$db_port = "5432";
$db_name = "your_database_name";
$db_user = "postgres"; 
$db_password = "your_password"; 

// Tạo chuỗi kết nối
$conn_string = "host=$db_host port=$db_port dbname=$db_name user=$db_user password=$db_password";

// Kết nối tới PostgreSQL
$conn = pg_connect($conn_string);

// Kiểm tra kết nối
if (!$conn) {
    error_log("Không thể kết nối tới PostgreSQL với thông tin sau: $conn_string");
    // Trả về phản hồi JSON lỗi
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Không thể kết nối cơ sở dữ liệu.']);
    exit();
}

// Đóng kết nối (tùy chọn, nếu bạn muốn đóng sau khi hoàn tất)
pg_close($conn);
?>
