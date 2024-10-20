<?php
header('Content-Type: application/json');

// Kết nối đến cơ sở dữ liệu
$servername = "localhost";
$username = "postgres";
$password = "!xNq!TRWY.AuD9U";
$dbname = "studentpickup";

$conn = pg_connect("host=$servername dbname=$dbname user=$username password=$password");
if (!$conn) {
    echo json_encode(['status' => 'error', 'message' => 'Không thể kết nối cơ sở dữ liệu']);
    exit();
}

// Kiểm tra trạng thái của dialog (ví dụ: kiểm tra xem có yêu cầu đón nào chưa xử lý)
$userid = $_SESSION['userid'] ?? null;

if ($userid) {
    $query = "SELECT * FROM public.pickup_history WHERE user_id = $1 AND status = 'Chờ xử lý'";
    $result = pg_query_params($conn, $query, array($userid));

    if ($result && pg_num_rows($result) > 0) {
        // Có yêu cầu đón đang chờ xử lý
        // Bạn có thể lấy nội dung dialog nếu cần
        $dialog_content = ''; // Tạo nội dung dialog nếu cần
        echo json_encode(['status' => 'active', 'content' => $dialog_content]);
    } else {
        // Không còn yêu cầu đón nào, xóa dialog
        echo json_encode(['status' => 'removed']);
    }
} else {
    // Người dùng chưa đăng nhập
    echo json_encode(['status' => 'error', 'message' => 'Người dùng chưa đăng nhập']);
}

pg_close($conn);
?>
