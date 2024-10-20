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

// Kiểm tra người dùng đã đăng nhập
if (!isset($_SESSION['userid'])) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Người dùng chưa đăng nhập']);
    exit();
}

$userid = $_SESSION['userid'];

// Lấy pickups đang chờ xử lý
$pendingPickupsQuery = "SELECT p.id AS pickup_id, s.name AS student_name, s.class, date_trunc('second', p.created_at) AS created_at
                        FROM public.pickup_history p
                        JOIN public.student s ON p.student_id = s.id
                        WHERE p.user_id = $1 AND p.status = 'Chờ xử lý'
                        ORDER BY p.created_at DESC";
$pendingPickupsResult = pg_query_params($conn, $pendingPickupsQuery, array($userid));

$pendingPickups = [];

if ($pendingPickupsResult) {
    while ($row = pg_fetch_assoc($pendingPickupsResult)) {
        $dt = new DateTime($row['created_at']);
        $created_at = $dt->format('Y-m-d H:i:s'); // Bao gồm microseconds

        $pendingPickups[] = [
            'pickup_id' => $row['pickup_id'],
            'student_name' => $row['student_name'],
            'class' => $row['class'],
            'created_at' => $created_at
        ];
    }
} else {
    // Xử lý lỗi nếu cần
}

header('Content-Type: application/json');
echo json_encode(['status' => 'success', 'pending_pickups' => $pendingPickups]);
pg_close($conn);
?>
