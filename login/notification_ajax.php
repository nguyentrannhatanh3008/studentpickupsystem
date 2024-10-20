<?php
session_start();

// Set the response content type to JSON
header('Content-Type: application/json');

// Database connection parameters
$servername = "localhost";
$username = "postgres";
$password = "!xNq!TRWY.AuD9U";
$dbname = "studentpickup";

// Connect to PostgreSQL
$conn = pg_connect("host=$servername dbname=$dbname user=$username password=$password");

if (!$conn) {
    echo json_encode(['status' => 'error', 'message' => 'Kết nối thất bại: ' . pg_last_error()]);
    exit();
}

// Check if user is logged in
if (!isset($_SESSION['userid'])) {
    echo json_encode(['status' => 'error', 'message' => 'Người dùng chưa đăng nhập']);
    exit();
}

$userid = $_SESSION['userid'];

// Handle different AJAX actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Mark all notifications as read
    if (isset($_POST['action']) && $_POST['action'] === 'mark_all_read') {
        $markAllReadQuery = "UPDATE public.notifications SET status = 'Đã Đọc' WHERE user_id = $1 AND status = 'Chưa đọc'";
        $markAllReadResult = pg_query_params($conn, $markAllReadQuery, array($userid));

        if ($markAllReadResult) {
            $affectedRows = pg_affected_rows($markAllReadResult);
            echo json_encode(['status' => 'success', 'updated' => $affectedRows]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Lỗi khi đánh dấu tất cả đã đọc: ' . pg_last_error($conn)]);
        }
        pg_close($conn);
        exit();
    }

    // 2. Mark a single notification as read
    if (isset($_POST['notification_id'])) {
        $notificationId = intval($_POST['notification_id']);

        $updateQuery = "UPDATE public.notifications SET status = 'Đã Đọc' WHERE id = $1 AND user_id = $2";
        $updateResult = pg_query_params($conn, $updateQuery, array($notificationId, $userid));

        if ($updateResult && pg_affected_rows($updateResult) > 0) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Không tìm thấy thông báo hoặc bạn không có quyền cập nhật.']);
        }
        pg_close($conn);
        exit();
    }

    // 3. Delete multiple notifications
    if (isset($_POST['delete_ids']) && is_array($_POST['delete_ids'])) {
        $deleteIds = array_map('intval', $_POST['delete_ids']);

        if (count($deleteIds) > 0) {
            // Create placeholders for the query
            $placeholders = [];
            $params = [];
            foreach ($deleteIds as $index => $id) {
                $placeholders[] = '$' . ($index + 1);
                $params[] = $id;
            }

            $placeholders_str = implode(', ', $placeholders);
            $deleteQuery = "DELETE FROM public.notifications WHERE id IN ($placeholders_str) AND user_id = $" . (count($deleteIds) + 1) . ";";
            $params[] = $userid;

            $deleteResult = pg_query_params($conn, $deleteQuery, $params);

            if ($deleteResult) {
                $affectedRows = pg_affected_rows($deleteResult);
                if ($affectedRows > 0) {
                    echo json_encode(['status' => 'success', 'deleted' => $affectedRows]);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Không tìm thấy thông báo hoặc bạn không có quyền xóa.']);
                }
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Lỗi khi xóa thông báo: ' . pg_last_error($conn)]);
            }
            pg_close($conn);
            exit();
        }

        echo json_encode(['status' => 'error', 'message' => 'Yêu cầu POST không hợp lệ.']);
        pg_close($conn);
        exit();
    }
}

// If no valid POST action is provided
echo json_encode(['status' => 'error', 'message' => 'Yêu cầu không hợp lệ.']);
pg_close($conn);
exit();
?>
