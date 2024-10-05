<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();

// Database configuration
$servername = "localhost";
$username = "postgres";
$password = "!xNq!TRWY.AuD9U";
$dbname = "studentpickup";

// Connect to PostgreSQL database
$conn = pg_connect("host=$servername dbname=$dbname user=$username password=$password");

if (!$conn) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Connection failed: ' . pg_last_error()]);
    exit();
}

// Check if the user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'User not logged in.']);
    exit();
}

// Ensure user ID is set
if (!isset($_SESSION['userid'])) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'User ID not found.']);
    exit();
}

$userid = intval($_SESSION['userid']);

// Handle AJAX request to mark notification as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['notification_id'])) {
    $notificationId = intval($_POST['notification_id']);

    // Update the notification status to 'Đã Đọc'
    $updateQuery = "UPDATE public.notifications SET status = 'Đã Đọc' WHERE id = $1 AND user_id = $2";
    $updateResult = pg_query_params($conn, $updateQuery, array($notificationId, $userid));

    header('Content-Type: application/json');
    if ($updateResult) {
        // Check if any row was actually updated
        if (pg_affected_rows($updateResult) > 0) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Không tìm thấy thông báo hoặc bạn không có quyền cập nhật.']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => pg_last_error($conn)]);
    }
    pg_close($conn);
    exit(); // Terminate script execution after handling AJAX
}

// If not an AJAX request, return an error
header('Content-Type: application/json');
echo json_encode(['status' => 'error', 'message' => 'Invalid request method or missing parameters.']);
pg_close($conn);
?>
