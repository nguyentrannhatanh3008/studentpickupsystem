<?php
$host = "localhost"; // Địa chỉ máy chủ PostgreSQL
$port = "5432"; // Cổng PostgreSQL
$dbname = "studentpickup"; // Tên cơ sở dữ liệu đã tạo trong pgAdmin
$user = "postgres"; // Tên người dùng PostgreSQL
$password = "!xNq!TRWY.AuD9U"; // Mật khẩu của người dùng PostgreSQL

// Tạo chuỗi kết nối
$conn_string = "host=$host port=$port dbname=$dbname user=$user password=$password";

// Kết nối tới cơ sở dữ liệu PostgreSQL
$conn = pg_connect($conn_string);

if (!$conn) {
    echo "Lỗi kết nối tới PostgreSQL.";
} else {
    echo "Kết nối tới PostgreSQL thành công!";
}

// Đóng kết nối (tùy chọn, nếu bạn muốn đóng sau khi hoàn tất)
pg_close($conn);
?>
