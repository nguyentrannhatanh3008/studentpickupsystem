<?php
session_start();

// Cấu hình kết nối cơ sở dữ liệu
$servername = "localhost";
$username_db = "postgres";
$password_db = "!xNq!TRWY.AuD9U";
$dbname = "studentpickup";

// Vô hiệu hóa hiển thị lỗi trực tiếp và chuyển sang ghi log
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Thiết lập múi giờ
date_default_timezone_set('Asia/Ho_Chi_Minh');

// Kết nối tới cơ sở dữ liệu PostgreSQL
$conn = pg_connect("host=$servername dbname=$dbname user=$username_db password=$password_db");

if (!$conn) {
    error_log('Kết nối thất bại: ' . pg_last_error());
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Kết nối cơ sở dữ liệu thất bại.']);
    exit();
}

// Kiểm tra đăng nhập
$isLoggedIn = isset($_SESSION['userid']);

if (!$isLoggedIn) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Người dùng chưa đăng nhập']);
    exit();
}

$userid = $_SESSION['userid'];

// Lấy thông tin người dùng
$query = "SELECT * FROM public.user WHERE id = $1";
$result = pg_query_params($conn, $query, array($userid));

if ($result === false) {
    error_log("Lỗi truy vấn người dùng: " . pg_last_error($conn));
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Lỗi truy vấn người dùng.']);
    exit();
}

$user = pg_fetch_assoc($result);
if (!$user) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Không tìm thấy người dùng.']);
    exit();
}

// Fetch pickups based on FPN or MPN
$historyQuery = "SELECT p.id AS pickup_id, s.id AS student_id, s.name AS student_name, s.class, p.created_at, p.status, p.last_replay_time
                FROM public.pickup_history p
                JOIN public.student s ON p.student_id = s.id
                WHERE s.FPN = $1 OR s.MPN = $1
                ORDER BY p.created_at DESC";

$historyResult = pg_query_params($conn, $historyQuery, array($user['phone']));

if (!$historyResult) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Failed to fetch pickup history.']);
    exit();
}

$history = [];
while ($row = pg_fetch_assoc($historyResult)) {
    $history[] = [
        'pickup_id' => $row['pickup_id'],
        'student_id' => $row['student_id'],
        'student_name' => $row['student_name'],
        'class' => $row['class'],
        'created_at' => $row['created_at'],
        'status' => $row['status'],
        'replay_deadline' => $row['last_replay_time'] ? strtotime($row['last_replay_time']) + (3 * 60) : 0 // 3 minutes cooldown
    ];
}

// Lấy đón đang chờ xử lý
$pendingPickupsQuery = "SELECT p.id AS pickup_id, s.name AS student_name, s.class, date_trunc('second', p.created_at) AS created_at, p.student_id
                        FROM public.pickup_history p
                        JOIN public.student s ON p.student_id = s.id
                        WHERE (s.FPN = $1 OR s.MPN = $1) AND p.status = 'Chờ xử lý'
                        ORDER BY p.created_at DESC";
$pendingPickupsResult = pg_query_params($conn, $pendingPickupsQuery, array($user['phone']));

$pendingPickups = [];

if ($pendingPickupsResult) {
    while ($row = pg_fetch_assoc($pendingPickupsResult)) {
        $dt = new DateTime($row['created_at']);
        $created_at = $dt->format('Y-m-d H:i:s'); // Bao gồm microseconds

        $pendingPickups[] = [
            'pickup_id' => $row['pickup_id'],
            'student_id' => $row['student_id'],
            'student_name' => $row['student_name'],
            'class' => $row['class'],
            'created_at' => $created_at,
            'status' => $row['status'],
            'replay_deadline' => $row['last_replay_time'] ? strtotime($row['last_replay_time']) + (3 * 60) : 0 // 3 minutes cooldown
        ];
    }
} else {
    error_log("Không thể lấy đón đang chờ xử lý: " . pg_last_error($conn));
}

// Lấy danh sách học sinh bị khóa (đã đăng ký đón và đang chờ xử lý)
$disabledQuery = "SELECT DISTINCT p.student_id FROM public.pickup_history p
                  JOIN public.student s ON p.student_id = s.id
                  WHERE (s.FPN = $1 OR s.MPN = $1) AND p.status = 'Chờ xử lý'";
$disabledResult = pg_query_params($conn, $disabledQuery, array($user['phone']));

$disabledStudents = [];

if ($disabledResult) {
    while ($row = pg_fetch_assoc($disabledResult)) {
        $disabledStudents[] = $row['student_id'];
    }
} else {
    error_log("Không thể lấy danh sách học sinh bị khóa: " . pg_last_error($conn));
}

header('Content-Type: application/json');
echo json_encode([
    'status' => 'success',
    'history' => $history,
    'pendingPickups' => $pendingPickups,
    'disabledStudents' => $disabledStudents
]);
pg_close($conn);
?>
