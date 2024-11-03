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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['student_id'])) {
    $student_id = intval($_POST['student_id']);

    // Fetch student information ensuring the student belongs to the teacher's class
    $studentQuery = "SELECT * FROM public.student WHERE id = $1 AND class_id = $2";
    $teacher_class_id = $_SESSION['class_id'];
    $studentResult = pg_query_params($conn, $studentQuery, array($student_id, $teacher_class_id));

    if ($studentResult && pg_num_rows($studentResult) > 0) {
        $student = pg_fetch_assoc($studentResult);
        echo json_encode([
            'status' => 'success',
            'student' => [
                'id' => htmlspecialchars($student['id']),
                'code' => htmlspecialchars($student['code']),
                'name' => htmlspecialchars($student['name']),
                'gender' => htmlspecialchars($student['gender']),
                'fn' => htmlspecialchars($student['fn']),
                'fpn' => htmlspecialchars($student['fpn']),
                'mn' => htmlspecialchars($student['mn']),
                'mpn' => htmlspecialchars($student['mpn']),
                'created_at' => htmlspecialchars($student['created_at']),
                'updated_at' => htmlspecialchars($student['updated_at']),
                'deleted_at' => htmlspecialchars($student['deleted_at']),
                'class_id' => htmlspecialchars($student['class_id']),
                'class' => htmlspecialchars($student['class']),
                'birthdate' => htmlspecialchars($student['birthdate']),
            ]
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Không tìm thấy thông tin học sinh.']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Thiếu tham số yêu cầu.']);
}

pg_close($conn);
?>
