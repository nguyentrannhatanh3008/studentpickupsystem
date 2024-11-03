<?php
session_start();

// Database configuration
$servername = "localhost";
$username_db = "postgres";
$password_db = "!xNq!TRWY.AuD9U";
$dbname = "studentpickup";

// Error logging configuration
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Set timezone
date_default_timezone_set('Asia/Ho_Chi_Minh');

// Connect to PostgreSQL database
$conn = pg_connect("host=$servername dbname=$dbname user=$username_db password=$password_db");

if (!$conn) {
    echo json_encode(['status' => 'error', 'message' => 'Kết nối cơ sở dữ liệu thất bại.']);
    exit();
}

// Check login and role
$isLoggedIn = isset($_SESSION['userid']) && isset($_SESSION['role']) && strcasecmp(trim($_SESSION['role']), 'GiaoVien') === 0;

if (!$isLoggedIn) {
    echo json_encode(['status' => 'error', 'message' => 'Người dùng chưa đăng nhập hoặc không có quyền truy cập.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = isset($_POST['student_id']) ? intval($_POST['student_id']) : null;
    $status = isset($_POST['status']) ? trim($_POST['status']) : null;
    $date = isset($_POST['date']) ? trim($_POST['date']) : null;

    // Build the query
    $query = "SELECT p.id AS pickup_id, s.name AS student_name, s.class, p.created_at, p.status
              FROM public.pickup_history p
              JOIN public.student s ON p.student_id = s.id
              WHERE s.class_id = $1";
    $params = [$_SESSION['class_id']];
    $i = 2;

    if (!empty($student_id)) {
        $query .= " AND s.id = $" . $i++;
        $params[] = $student_id;
    }

    if (!empty($status)) {
        $query .= " AND p.status = $" . $i++;
        $params[] = $status;
    }

    if (!empty($date)) {
        $query .= " AND DATE(p.created_at) = $" . $i++;
        $params[] = $date;
    }

    $query .= " ORDER BY p.created_at DESC";

    $result = pg_query_params($conn, $query, $params);

    if ($result) {
        $data = [];
        while ($row = pg_fetch_assoc($result)) {
            $data[] = [
                'id' => htmlspecialchars($row['pickup_id']),
                'student_name' => htmlspecialchars($row['student_name']),
                'class' => htmlspecialchars($row['class']),
                'created_at' => htmlspecialchars($row['created_at']),
                'status' => htmlspecialchars($row['status'])
            ];
        }
        echo json_encode(['status' => 'success', 'data' => $data]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Lỗi khi lọc lịch sử: ' . pg_last_error($conn)]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Phương thức yêu cầu không hợp lệ.']);
}

pg_close($conn);
?>
