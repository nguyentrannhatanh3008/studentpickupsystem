<?php
session_start();

$servername = "localhost";
$username = "postgres";
$password = "!xNq!TRWY.AuD9U";
$dbname = "studentpickup";

// Connect to PostgreSQL database
$conn = pg_connect("host=$servername dbname=$dbname user=$username password=$password");

if (!$conn) {
    echo json_encode(['status' => 'error', 'message' => 'Connection failed: ' . pg_last_error()]);
    exit();
}

// Check if the user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo json_encode(['status' => 'error', 'message' => 'User not logged in']);
    exit();
}

// Ensure user ID is set
if (!isset($_SESSION['userid'])) {
    echo json_encode(['status' => 'error', 'message' => 'User ID not set']);
    exit();
}

$userId = $_SESSION['userid'];

// Check if student_id is provided
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['student_id'])) {
    $studentId = $_POST['student_id'];

    // Sanitize the student ID to prevent SQL injection
    if (!is_numeric($studentId)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid student ID']);
        exit();
    }

    // Fetch student details from the database, ensuring the student is associated with the logged-in user
    $query = "SELECT * FROM public.student WHERE id = $1 AND (FPN = (SELECT phone FROM public.user WHERE id = $2) OR MPN = (SELECT phone FROM public.user WHERE id = $2))";
    $result = pg_query_params($conn, $query, array($studentId, $userId));

    if ($result) {
        $student = pg_fetch_assoc($result);
        if ($student) {
            echo json_encode(['status' => 'success', 'student' => $student]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Student not found or not associated with your account']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => pg_last_error($conn)]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
}

pg_close($conn);
?>
