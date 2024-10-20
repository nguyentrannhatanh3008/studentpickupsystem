<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
$servername = "localhost";
$username = "postgres";
$password = "!xNq!TRWY.AuD9U";
$dbname = "studentpickup";

// Kết nối tới cơ sở dữ liệu PostgreSQL
$conn = pg_connect("host=$servername dbname=$dbname user=$username password=$password");

if (!$conn) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Kết nối thất bại: ' . pg_last_error()]);
    exit();
}

$isLoggedIn = isset($_SESSION['userid']);

if ($isLoggedIn) {
    $userid = $_SESSION['userid'];

    // Lấy thông tin người dùng từ cơ sở dữ liệu
    $query = "SELECT child_id FROM public.user WHERE id = $1";
    $result = pg_query_params($conn, $query, array($userid));

    if ($result === false) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Lỗi truy vấn: ' . pg_last_error($conn)]);
        exit();
    }

    $user = pg_fetch_assoc($result);
    if (!$user) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Người dùng không tồn tại.']);
        exit();
    }

    $child_id = $user['child_id'];

    // Kiểm tra nếu child_id không tồn tại
    if (!$child_id) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Người dùng không liên kết với học sinh nào.']);
        exit();
    }

    // Lấy danh sách thông báo chưa đọc
    $newNotificationsQuery = "SELECT id, title, message, status, to_char(created_at, 'YYYY-MM-DD HH24:MI:SS') AS created_at
                             FROM public.notifications
                             WHERE child_id = $1 AND status = 'Chưa đọc'
                             ORDER BY created_at DESC";
    $newNotificationsResult = pg_query_params($conn, $newNotificationsQuery, array($child_id));

    if ($newNotificationsResult === false) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Lỗi truy vấn: ' . pg_last_error($conn)]);
        exit();
    }

    $newNotifications = [];
    while ($notification = pg_fetch_assoc($newNotificationsResult)) {
        $newNotifications[] = $notification;
    }

    header('Content-Type: application/json');
    echo json_encode(['status' => 'success', 'new_notifications' => $newNotifications]);
    pg_close($conn);
    exit();
}

// Nếu người dùng chưa đăng nhập
header('Content-Type: application/json');
echo json_encode(['status' => 'error', 'message' => 'Người dùng chưa đăng nhập']);
pg_close($conn);
exit();
?>
