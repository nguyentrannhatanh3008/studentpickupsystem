<?php
session_start();

// Cấu hình kết nối cơ sở dữ liệu
$servername = "localhost";
$username_db = "postgres";
$password_db = "!xNq!TRWY.AuD9U";
$dbname = "studentpickup";

// Thiết lập múi giờ
date_default_timezone_set('Asia/Ho_Chi_Minh');

// Bắt đầu bộ đệm đầu ra
ob_start();

// Kết nối tới cơ sở dữ liệu PostgreSQL
$conn = pg_connect("host=$servername dbname=$dbname user=$username_db password=$password_db");

if (!$conn) {
    echo json_encode(['status' => 'error', 'message' => 'Kết nối cơ sở dữ liệu thất bại.']);
    exit();
}

// Kiểm tra đăng nhập và vai trò
$isLoggedIn = isset($_SESSION['userid']) && isset($_SESSION['role']) && strcasecmp(trim($_SESSION['role']), 'GiaoVien') === 0;

if (!$isLoggedIn) {
    echo json_encode(['status' => 'error', 'message' => 'Người dùng chưa đăng nhập hoặc không có quyền truy cập.']);
    exit();
}

$userid = $_SESSION['userid'];

// Lấy thông tin người dùng để truy vấn số điện thoại
$userQuery = "SELECT phone FROM public.\"user\" WHERE id = $1";
$userResult = pg_query_params($conn, $userQuery, array($userid));

if ($userResult && pg_num_rows($userResult) > 0) {
    $user = pg_fetch_assoc($userResult);
    $user_phone = $user['phone'];

    // Truy vấn thông tin giáo viên dựa trên phone_number
    $teacherQuery = "SELECT * FROM public.teachers WHERE phone_number = $1";
    $teacherResult = pg_query_params($conn, $teacherQuery, array($user_phone));

    if ($teacherResult && pg_num_rows($teacherResult) > 0) {
        $teacher = pg_fetch_assoc($teacherResult);
        $class_id = $teacher['class_id'];

        // Lấy thông tin các lớp
        // (Optional: If you have multiple classes per teacher)

        // Handle date filters
        $start_date = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
        $end_date = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';

        $historyQuery = "SELECT p.id AS pickup_id, s.id AS student_id, s.name AS student_name, s.class, p.created_at, p.status, p.last_replay_time
                        FROM public.pickup_history p
                        JOIN public.student s ON p.student_id = s.id
                        WHERE s.class_id = $1";

        $params = [$class_id];
        $param_index = 2;

        if (!empty($start_date)) {
            $historyQuery .= " AND p.created_at >= $" . $param_index;
            $params[] = $start_date . ' 00:00:00';
            $param_index++;
        }

        if (!empty($end_date)) {
            $historyQuery .= " AND p.created_at <= $" . $param_index;
            $params[] = $end_date . ' 23:59:59';
            $param_index++;
        }

        $historyQuery .= " ORDER BY p.created_at DESC";

        $historyResult = pg_query_params($conn, $historyQuery, $params);

        if ($historyResult) {
            $history = [];
            while ($row = pg_fetch_assoc($historyResult)) {
                $history[] = [
                    'pickup_id' => $row['pickup_id'],
                    'student_id' => $row['student_id'],
                    'student_name' => $row['student_name'],
                    'class' => $row['class'],
                    'created_at' => $row['created_at'],
                    'status' => $row['status'],
                    'last_replay_time' => $row['last_replay_time']
                ];
            }

            echo json_encode(['status' => 'success', 'history' => $history]);
            exit();
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Không thể lấy lịch sử đón.']);
            exit();
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Không tìm thấy giáo viên.']);
        exit();
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Không tìm thấy người dùng.']);
    exit();
}

pg_close($conn);
?>
