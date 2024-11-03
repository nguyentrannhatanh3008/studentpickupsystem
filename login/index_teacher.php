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

// Start output buffering
ob_start();

// Connect to PostgreSQL database
$conn = pg_connect("host=$servername dbname=$dbname user=$username_db password=$password_db");

if (!$conn) {
    error_log('Connection failed: ' . pg_last_error());
    header('Content-Type: application/json');
    ob_clean();
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed.']);
    exit();
}

// Check login and role
$isLoggedIn = isset($_SESSION['userid']) && isset($_SESSION['role']) && strcasecmp(trim($_SESSION['role']), 'GiaoVien') === 0;

if ($isLoggedIn) {
    $userid = $_SESSION['userid'];

    // Retrieve user information to query phone number
    $userQuery = "SELECT phone FROM public.\"user\" WHERE id = $1";
    $userResult = pg_query_params($conn, $userQuery, array($userid));

    if ($userResult && pg_num_rows($userResult) > 0) {
        $user = pg_fetch_assoc($userResult);
        $user_phone = $user['phone'];

        // Query teacher information based on phone_number
        $teacherQuery = "SELECT * FROM public.teachers WHERE phone_number = $1";
        $teacherResult = pg_query_params($conn, $teacherQuery, array($user_phone));

        if ($teacherResult && pg_num_rows($teacherResult) > 0) {
            $teacher = pg_fetch_assoc($teacherResult);
            $class_id = $teacher['class_id'];

            // Store class_id in session if needed
            $_SESSION['class_id'] = $class_id;

            // Log for debugging
            error_log("Teacher ID: " . $teacher['id'] . ", Class ID: " . $class_id);

            // Get unread notifications count
            $unreadCount = 0;
            $countQuery = "SELECT COUNT(*) AS unread FROM public.notifications WHERE user_id = $1 AND status = 'Chưa đọc'";
            $countResult = pg_query_params($conn, $countQuery, array($userid));

            if ($countResult) {
                $countRow = pg_fetch_assoc($countResult);
                $unreadCount = intval($countRow['unread']);
            } else {
                error_log("Notification query error: " . pg_last_error($conn));
            }
        } else {
            // If teacher not found, mark as not logged in
            error_log("Teacher not found with phone_number: $user_phone");
            $isLoggedIn = false;
        }
    } else {
        // If user not found, mark as not logged in
        error_log("User not found with id: $userid");
        $isLoggedIn = false;
    }

    if ($isLoggedIn) {
        // Fetch students belonging to the teacher's class
        $studentQuery = "SELECT * FROM public.student WHERE class_id = $1 ORDER BY name ASC";
        $studentResult = pg_query_params($conn, $studentQuery, array($class_id));

        if ($studentResult === false) {
            error_log("Failed to fetch students: " . pg_last_error($conn));
        }

        // Fetch pickup history to display on the page
        $historyQuery = "SELECT p.id AS pickup_id, s.id AS student_id, s.name AS student_name, s.class, p.created_at, p.status, p.last_replay_time
                        FROM public.pickup_history p
                        JOIN public.student s ON p.student_id = s.id
                        WHERE s.class_id = $1
                        ORDER BY p.created_at DESC";

        $historyResult = pg_query_params($conn, $historyQuery, array($class_id));

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
                'last_replay_time' => $row['last_replay_time']
            ];
        }

        // Fetch pending pickups
        $pendingPickupsQuery = "SELECT p.id AS pickup_id, s.name AS student_name, s.class, date_trunc('second', p.created_at) AS created_at, p.student_id, p.status, p.last_replay_time
                                FROM public.pickup_history p
                                JOIN public.student s ON p.student_id = s.id
                                WHERE s.class_id = $1 AND p.status = 'Chờ xử lý'
                                ORDER BY p.created_at DESC";
        $pendingPickupsResult = pg_query_params($conn, $pendingPickupsQuery, array($class_id));

        $pendingPickups = [];

        if ($pendingPickupsResult) {
            while ($row = pg_fetch_assoc($pendingPickupsResult)) {
                $pickupId = $row['pickup_id'];
                $studentName = $row['student_name'];
                $studentClass = $row['class'];
                $createdAt = $row['created_at'];
                $studentId = $row['student_id'];
                $status = $row['status'];
                $lastReplayTime = $row['last_replay_time'];

                $pendingPickups[] = [
                    'pickup_id' => $pickupId,
                    'student_id' => $studentId,
                    'student_name' => $studentName,
                    'class' => $studentClass,
                    'created_at' => $createdAt,
                    'status' => $status,
                    'last_replay_time' => $lastReplayTime
                ];
            }
        } else {
            error_log("Failed to fetch pending pickups: " . pg_last_error($conn));
        }

        // Fetch disabled students (already registered and pending)
        $disabledQuery = "SELECT DISTINCT p.student_id FROM public.pickup_history p
                          JOIN public.student s ON p.student_id = s.id
                          WHERE s.class_id = $1 AND p.status = 'Chờ xử lý'";
        $disabledResult = pg_query_params($conn, $disabledQuery, array($class_id));

        $disabledStudents = [];

        if ($disabledResult) {
            while ($row = pg_fetch_assoc($disabledResult)) {
                $disabledStudents[] = $row['student_id'];
            }
        } else {
            error_log("Failed to fetch disabled students: " . pg_last_error($conn));
        }
    }
}

// If user is not logged in or not a teacher
if (!$isLoggedIn) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json');
        ob_clean();
        echo json_encode(['status' => 'error', 'message' => 'User is not logged in or does not have access rights.']);
        exit();
    } else {
        header("Location: login.php");
        exit();
    }
}

/**
 * Function to send "Replay" request to Flask server
 * @param int $pickup_id
 * @param int $student_id
 * @param string $student_name
 * @return array
 */
function sendReplayToFlask($pickup_id, $student_id, $student_name) {
    $url = 'http://localhost:5000/replay'; // Ensure Flask server is running at this address

    $data = array(
        'pickup_id' => $pickup_id,
        'student_id' => $student_id,
        'student_name' => $student_name
    );

    $payload = json_encode($data);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5); // Timeout after 5 seconds
    curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Maximum execution time 10 seconds

    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($result === false) {
        $error = curl_error($ch);
        curl_close($ch);
        return ['status' => 'error', 'message' => 'cURL Error: ' . $error];
    }

    curl_close($ch);

    $response = json_decode($result, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['status' => 'error', 'message' => 'JSON Decode Error: ' . json_last_error_msg()];
    }

    if ($httpCode !== 200) {
        $message = isset($response['message']) ? $response['message'] : 'HTTP Error: ' . $httpCode;
        return ['status' => 'error', 'message' => $message];
    }

    // Ensure 'status' key exists
    if (!isset($response['status'])) {
        return ['status' => 'error', 'message' => 'No status received from Flask server.'];
    }

    return $response;
}

// Handle AJAX POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle pickup registration
    if (isset($_POST['action']) && $_POST['action'] === 'registerPickup') {
        if (!empty($_POST['students']) && is_array($_POST['students'])) {
            $selectedStudents = $_POST['students'];
            $responseArray = [];

            foreach ($selectedStudents as $studentId) {
                // Validate student ID
                if (!is_numeric($studentId)) {
                    continue; // Skip invalid IDs
                }
                $studentId = intval($studentId);

                // Check if student belongs to the teacher's class
                $checkClassQuery = "SELECT class_id FROM public.student WHERE id = $1";
                $checkClassResult = pg_query_params($conn, $checkClassQuery, array($studentId));

                if ($checkClassResult && pg_num_rows($checkClassResult) > 0) {
                    $studentClass = pg_fetch_assoc($checkClassResult)['class_id'];
                    if ($studentClass != $class_id) {
                        continue; // Skip if not in teacher's class
                    }
                } else {
                    continue; // Skip if student not found
                }

                // Insert into pickup_history
                $pickupQuery = "INSERT INTO public.pickup_history (user_id, student_id, created_at, status) VALUES ($1, $2, NOW(), 'Chờ xử lý') RETURNING id, created_at";
                $pickupResult = pg_query_params($conn, $pickupQuery, array($userid, $studentId));

                if ($pickupResult) {
                    $pickupRow = pg_fetch_assoc($pickupResult);
                    $pickupId = $pickupRow['id'];
                    $created_at = date('Y-m-d H:i:s', strtotime($pickupRow['created_at']));

                    // Fetch student information
                    $studentInfoQuery = "SELECT * FROM public.student WHERE id = $1";
                    $studentInfoResult = pg_query_params($conn, $studentInfoQuery, array($studentId));

                    if ($studentInfoResult) {
                        $student = pg_fetch_assoc($studentInfoResult);

                        // Fetch teacher's user_ids to notify
                        $teacherQuery = "SELECT user_id FROM public.teachers WHERE class_id = $1";
                        $teacherResult = pg_query_params($conn, $teacherQuery, array($class_id));

                        $user_ids = [];
                        if ($teacherResult) {
                            while ($teacher = pg_fetch_assoc($teacherResult)) {
                                $user_ids[] = $teacher['user_id'];
                            }
                        }

                        // Insert notifications
                        foreach ($user_ids as $notify_user_id) {
                            $notificationTitle = "Yêu cầu đón";
                            $notificationMessage = "Yêu cầu đón cho học sinh " . htmlspecialchars($student['name']) . " lúc " . $created_at;
                            $notificationStatus = "Chưa đọc";
                            $notificationInsertQuery = "INSERT INTO public.notifications (user_id, title, message, status, created_at) VALUES ($1, $2, $3, $4, NOW())";
                            $notificationResult = pg_query_params($conn, $notificationInsertQuery, array($notify_user_id, $notificationTitle, $notificationMessage, $notificationStatus));

                            if (!$notificationResult) {
                                header('Content-Type: application/json');
                                ob_clean();
                                echo json_encode(['status' => 'error', 'message' => 'Failed to insert notification: ' . pg_last_error()]);
                                exit();
                            }
                        }

                        $responseArray[] = [
                            'status' => 'Chờ xử lý',
                            'student_id' => $studentId,
                            'student_name' => htmlspecialchars($student['name']),
                            'class' => htmlspecialchars($student['class']),
                            'created_at' => $created_at,
                            'pickup_id' => $pickupId
                        ];
                    } else {
                        header('Content-Type: application/json');
                        ob_clean();
                        echo json_encode(['status' => 'error', 'message' => 'Failed to fetch student info: ' . pg_last_error($conn)]);
                        exit();
                    }
                } else {
                    header('Content-Type: application/json');
                    ob_clean();
                    echo json_encode(['status' => 'error', 'message' => 'Failed to insert pickup history: ' . pg_last_error()]);
                    exit();
                }
            }

            header('Content-Type: application/json');
            ob_clean();
            echo json_encode(['status' => 'success', 'pickups' => $responseArray]);
            exit();
        } else {
            header('Content-Type: application/json');
            ob_clean();
            echo json_encode(['status' => 'error', 'message' => 'No students selected.']);
            exit();
        }
    }

    // Handle other actions: confirm, cancel, replay, delete_pickups, fetch_history, fetch_student_details
    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        if (isset($_POST['pickup_id'])) {
            $pickupId = intval($_POST['pickup_id']);
        }

        if ($action === 'confirm') {
            // Confirm pickup
            $updateQuery = "UPDATE public.pickup_history SET status = 'Đã đón', pickup_time = NOW() WHERE id = $1 RETURNING student_id, pickup_time";
            $updateResult = pg_query_params($conn, $updateQuery, array($pickupId));

            if ($updateResult) {
                $row = pg_fetch_assoc($updateResult);
                $studentId = $row['student_id'];
                $pickupTime = date('Y-m-d H:i:s', strtotime($row['pickup_time']));

                // Fetch student information
                $studentInfoQuery = "SELECT * FROM public.student WHERE id = $1";
                $studentInfoResult = pg_query_params($conn, $studentInfoQuery, array($studentId));

                if ($studentInfoResult) {
                    $student = pg_fetch_assoc($studentInfoResult);

                    // Fetch teacher's user_ids to notify
                    $teacherQuery = "SELECT user_id FROM public.teachers WHERE class_id = $1";
                    $teacherResult = pg_query_params($conn, $teacherQuery, array($class_id));

                    $user_ids = [];
                    if ($teacherResult) {
                        while ($teacher = pg_fetch_assoc($teacherResult)) {
                            $user_ids[] = $teacher['user_id'];
                        }
                    }

                    // Insert notifications
                    foreach ($user_ids as $notify_user_id) {
                        $notificationTitle = "Đã Được Xác Nhận";
                        $notificationMessage = "Yêu cầu đón cho học sinh " . htmlspecialchars($student['name']) . " lúc " . $pickupTime . " đã được xác nhận.";
                        $notificationStatus = "Chưa đọc";
                        $notificationInsertQuery = "INSERT INTO public.notifications (user_id, title, message, status, created_at) VALUES ($1, $2, $3, $4, NOW())";
                        $notificationResult = pg_query_params($conn, $notificationInsertQuery, array($notify_user_id, $notificationTitle, $notificationMessage, $notificationStatus));

                        if (!$notificationResult) {
                            header('Content-Type: application/json');
                            ob_clean();
                            echo json_encode(['status' => 'error', 'message' => 'Failed to insert notification: ' . pg_last_error()]);
                            exit();
                        }
                    }

                    header('Content-Type: application/json');
                    ob_clean();
                    echo json_encode(['status' => 'success', 'student_id' => $studentId]);
                    exit();
                } else {
                    header('Content-Type: application/json');
                    ob_clean();
                    echo json_encode(['status' => 'error', 'message' => 'Failed to fetch student info: ' . pg_last_error($conn)]);
                    exit();
                }
            } else {
                header('Content-Type: application/json');
                ob_clean();
                echo json_encode(['status' => 'error', 'message' => 'Failed to update pickup history: ' . pg_last_error()]);
                exit();
            }
        } elseif ($action === 'cancel') {
            // Cancel pickup
            $updateQuery = "UPDATE public.pickup_history SET status = 'Đã hủy' WHERE id = $1 RETURNING student_id";
            $updateResult = pg_query_params($conn, $updateQuery, array($pickupId));

            if ($updateResult) {
                $row = pg_fetch_assoc($updateResult);
                $studentId = $row['student_id'];

                // Fetch student information
                $studentInfoQuery = "SELECT * FROM public.student WHERE id = $1";
                $studentInfoResult = pg_query_params($conn, $studentInfoQuery, array($studentId));

                if ($studentInfoResult) {
                    $student = pg_fetch_assoc($studentInfoResult);

                    // Fetch teacher's user_ids to notify
                    $teacherQuery = "SELECT user_id FROM public.teachers WHERE class_id = $1";
                    $teacherResult = pg_query_params($conn, $teacherQuery, array($class_id));

                    $user_ids = [];
                    if ($teacherResult) {
                        while ($teacher = pg_fetch_assoc($teacherResult)) {
                            $user_ids[] = $teacher['user_id'];
                        }
                    }

                    // Insert notifications
                    foreach ($user_ids as $notify_user_id) {
                        $notificationTitle = "Đã hủy đón";
                        $notificationMessage = "Yêu cầu đón cho học sinh " . htmlspecialchars($student['name']) . " đã bị hủy.";
                        $notificationStatus = "Chưa đọc";
                        $notificationInsertQuery = "INSERT INTO public.notifications (user_id, title, message, status, created_at) VALUES ($1, $2, $3, $4, NOW())";
                        $notificationResult = pg_query_params($conn, $notificationInsertQuery, array($notify_user_id, $notificationTitle, $notificationMessage, $notificationStatus));

                        if (!$notificationResult) {
                            header('Content-Type: application/json');
                            ob_clean();
                            echo json_encode(['status' => 'error', 'message' => 'Failed to insert notification: ' . pg_last_error()]);
                            exit();
                        }
                    }

                    header('Content-Type: application/json');
                    ob_clean();
                    echo json_encode(['status' => 'success', 'student_id' => $studentId]);
                    exit();
                } else {
                    header('Content-Type: application/json');
                    ob_clean();
                    echo json_encode(['status' => 'error', 'message' => 'Failed to fetch student info: ' . pg_last_error($conn)]);
                    exit();
                }
            } else {
                header('Content-Type: application/json');
                ob_clean();
                echo json_encode(['status' => 'error', 'message' => 'Failed to update pickup status: ' . pg_last_error($conn)]);
                exit();
            }
        } elseif ($action === 'replay') {
            // Handle replay action
            // Check if all required parameters are present
            if (!isset($_POST['pickup_id']) || !isset($_POST['student_id']) || !isset($_POST['student_name'])) {
                // Log the received POST data for debugging
                error_log("Replay Action Missing Parameters: " . print_r($_POST, true));

                header('Content-Type: application/json');
                ob_clean();
                echo json_encode(['status' => 'error', 'message' => 'Missing required parameters.']);
                exit();
            }

            $pickupId = intval($_POST['pickup_id']);
            $studentId = intval($_POST['student_id']);
            $studentName = trim($_POST['student_name']);

            // Log the received parameters
            error_log("Replay Action Received: pickup_id=$pickupId, student_id=$studentId, student_name=$studentName");

            // Fetch last_replay_time and student_id from pickup_history
            $query = "SELECT last_replay_time, student_id FROM public.pickup_history WHERE id = $1";
            $result = pg_query_params($conn, $query, array($pickupId));

            if ($result) {
                $row = pg_fetch_assoc($result);
                $lastReplayTime = $row['last_replay_time'] ? strtotime($row['last_replay_time']) : 0;
                $currentTime = time();
                $timeSinceLastReplay = $currentTime - $lastReplayTime;

                if ($timeSinceLastReplay < 3 * 60) { // 3 minutes cooldown
                    $remainingTime = 3 * 60 - $timeSinceLastReplay;
                    $deadline = $currentTime + $remainingTime;
                    $serverTime = $currentTime;

                    header('Content-Type: application/json');
                    ob_clean();
                    echo json_encode([
                        'status' => 'cooldown',
                        'message' => 'Please wait before replaying again.',
                        'deadline' => $deadline,
                        'server_time' => $serverTime
                    ]);
                    exit();
                } else {
                    // Update last_replay_time
                    $updateQuery = "UPDATE public.pickup_history SET last_replay_time = NOW() WHERE id = $1";
                    $updateResult = pg_query_params($conn, $updateQuery, array($pickupId));

                    if ($updateResult) {
                        // Get student info
                        $studentQuery = "SELECT name FROM public.student WHERE id = $1";
                        $studentResult = pg_query_params($conn, $studentQuery, array($studentId));

                        if ($studentResult) {
                            $studentRow = pg_fetch_assoc($studentResult);
                            $studentName = $studentRow['name'];

                            // Send to Flask
                            $replayResponse = sendReplayToFlask($pickupId, $studentId, $studentName);

                            if ($replayResponse['status'] === 'success') {
                                header('Content-Type: application/json');
                                ob_clean();
                                echo json_encode([
                                    'status' => 'success',
                                    'deadline' => $currentTime + 3 * 60,
                                    'server_time' => $currentTime
                                ]);
                                exit();
                            } else {
                                $errorMessage = isset($replayResponse['message']) ? $replayResponse['message'] : 'Failed to send replay request.';
                                error_log("Error in sendReplayToFlask: $errorMessage");
                                header('Content-Type: application/json');
                                ob_clean();
                                echo json_encode([
                                    'status' => 'error',
                                    'message' => 'Failed to send replay request: ' . $errorMessage
                                ]);
                                exit();
                            }
                        } else {
                            header('Content-Type: application/json');
                            ob_clean();
                            echo json_encode(['status' => 'error', 'message' => 'Failed to fetch student info: ' . pg_last_error($conn)]);
                            exit();
                        }
                    } else {
                        error_log("Failed to update last_replay_time for pickup_id $pickupId: " . pg_last_error($conn));
                        header('Content-Type: application/json');
                        ob_clean();
                        echo json_encode(['status' => 'error', 'message' => 'Failed to update replay time: ' . pg_last_error($conn)]);
                        exit();
                    }
                }
            } else {
                error_log("Failed to fetch pickup_history for pickup_id $pickupId: " . pg_last_error($conn));
                header('Content-Type: application/json');
                ob_clean();
                echo json_encode(['status' => 'error', 'message' => 'Failed to fetch pickup history: ' . pg_last_error($conn)]);
                exit();
            }
        } elseif ($action === 'delete_pickups') {
            // Handle bulk deletion of pickups
            if (isset($_POST['pickup_ids'])) {
                $pickupIds = $_POST['pickup_ids'];

                if (!is_array($pickupIds) || empty($pickupIds)) {
                    header('Content-Type: application/json');
                    ob_clean();
                    echo json_encode(['status' => 'error', 'message' => 'Invalid pickup list.']);
                    exit();
                }

                // Prepare placeholders and parameters
                $placeholders = [];
                $params = [];
                $i = 1;
                foreach ($pickupIds as $id) {
                    if (!is_numeric($id)) {
                        header('Content-Type: application/json');
                        ob_clean();
                        echo json_encode(['status' => 'error', 'message' => 'Invalid pickup ID.']);
                        exit();
                    }
                    $placeholders[] = '$' . $i++;
                    $params[] = $id;
                }
                $placeholders_str = implode(',', $placeholders);

                // Query to delete pickups belonging to the teacher's class
                $deleteQuery = "DELETE FROM public.pickup_history 
                                WHERE id IN ($placeholders_str) 
                                AND student_id IN (
                                    SELECT id FROM public.student WHERE class_id = $" . $i . "
                                )";
                $params[] = $class_id;
                $deleteResult = pg_query_params($conn, $deleteQuery, $params);

                if ($deleteResult) {
                    $deletedCount = pg_affected_rows($deleteResult);
                    header('Content-Type: application/json');
                    ob_clean();
                    echo json_encode(['status' => 'success', 'deleted_count' => $deletedCount]);
                    exit();
                } else {
                    header('Content-Type: application/json');
                    ob_clean();
                    echo json_encode(['status' => 'error', 'message' => 'Failed to delete pickups: ' . pg_last_error($conn)]);
                    exit();
                }
            } else {
                header('Content-Type: application/json');
                ob_clean();
                echo json_encode(['status' => 'error', 'message' => 'Missing required parameters.']);
                exit();
            }
        } elseif ($action === 'fetch_history') {
            // Fetch pickup history for DataTables
            $historyResult = pg_query_params($conn, $historyQuery, array($class_id));

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

                header('Content-Type: application/json');
                ob_clean();
                echo json_encode(['status' => 'success', 'history' => $history]);
                exit();
            } else {
                header('Content-Type: application/json');
                ob_clean();
                echo json_encode(['status' => 'error', 'message' => 'Failed to fetch history: ' . pg_last_error($conn)]);
                exit();
            }
        } elseif ($action === 'fetch_student_details') {
            // Fetch student details for a specific student
            if (isset($_POST['student_id'])) {
                $studentId = intval($_POST['student_id']);

                $studentQuery = "SELECT * FROM public.student WHERE id = $1 AND class_id = $2";
                $studentResult = pg_query_params($conn, $studentQuery, array($studentId, $class_id));

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
                            'birthdate' => htmlspecialchars($student['birthdate']),
                            'class_id' => htmlspecialchars($student['class_id']),
                            'class' => htmlspecialchars($student['class']),
                            'created_at' => htmlspecialchars($student['created_at']),
                            'updated_at' => htmlspecialchars($student['updated_at']),
                            'deleted_at' => htmlspecialchars($student['deleted_at']),
                        ]
                    ]);
                    exit();
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Student not found.']);
                    exit();
                }
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Missing student ID.']);
                exit();
            }
        }
    }
}

// Close database connection before outputting HTML
pg_close($conn);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <!-- Meta tags và liên kết CSS -->
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Teacher Dashboard - Student Pick-Up System</title>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.dataTables.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="css/index_teacher.css">
    <style>
        /* Additional CSS for collapsible boxes */
        .collapse-box {
            border: 1px solid #ccc;
            border-radius: 5px;
            margin-bottom: 20px;
            overflow: hidden;
        }

        .collapse-box-header {
            background-color: #f8f9fa;
            padding: 10px 15px;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .collapse-box-content {
            padding: 15px;
            display: none;
        }

        .collapse-box.open .collapse-box-content {
            display: block;
        }

        .collapse-icon {
            transition: transform 0.3s;
        }

        .collapse-box.open .collapse-icon {
            transform: rotate(180deg);
        }

        /* Dialog container styling */
        #dialogContainer {
            position: fixed;
            top: 80px;
            right: 20px;
            width: 300px;
            z-index: 1050;
        }

        .dialog-box {
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <header class="header">
        <i class="fas fa-bars" id="btn"></i>
        <h1></h1>
        <nav class="navbar">
            <ul class="nav">
                <?php if ($isLoggedIn && isset($teacher)): ?>
                    <li class="nav-item dropdown">
                        <a href="#" class="nav-link dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" style="color: white;">
                            <i class="fas fa-user-circle" style="font-size: 20px; margin-right: 10px; vertical-align: middle;"></i>
                            <span class="username" style="font-size: 20px; font-weight: bold; margin-right: 5px; vertical-align: middle;">
                                <?php echo htmlspecialchars($teacher['fullname']); ?>
                            </span>
                        </a>
                        <div class="dropdown-menu dropdown-menu-right" aria-labelledby="userDropdown">
                            <a class="dropdown-item" href="change_password.php"><i class="fa fa-key"></i> Đổi Mật Khẩu</a>
                            <a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt"></i> Đăng Xuất</a>
                        </div>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a href="login.php" class="nav-link">Đăng Nhập</a>
                    </li>
                    <li class="nav-item">
                        <a href="register.php" class="nav-link">Đăng Ký</a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    </header>
    <div class="sidebar" id="sidebar">
        <ul class="nav-list">
            <li><a href="index_teacher.php"><i class="fas fa-home"></i> <span class="nav-item">Trang Chủ</span></a></li>
            <li>
                <a href="notification.php" class="d-flex align-items-center">
                    <i class="fas fa-bell"></i>
                    <span class="nav-item ml-2">Thông Báo</span>
                    <?php if ($unreadCount > 0): ?>
                        <span class="badge badge-danger ml-auto"><?php echo $unreadCount; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li><a href="profile.php"><i class="fas fa-user"></i> <span class="nav-item"> Hồ Sơ</span></a></li>
            <li><a href="studentlist_teacher.php"><i class="fas fa-child"></i> <span class="nav-item">Danh Sách Học Sinh</span></a></li>
            <li><a href="reports.php"><i class="fas fa-chart-line"></i> <span class="nav-item">Báo Cáo</span></a></li>
        </ul>
    </div>
    
    <div class="main-container container-fluid">
        <!-- Dialog Container -->
        <div id="dialogContainer"></div>

        <!-- Collapsible Sections -->
        <div id="accordion">
            <!-- Pickup Registration -->
            <div class="card">
                <div class="card-header" id="headingPickupRegistration">
                    <h2 class="mb-0">
                        <button class="btn btn-link collapsed" data-toggle="collapse" data-target="#collapsePickupRegistration" aria-expanded="false" aria-controls="collapsePickupRegistration">
                            <i class="fas fa-chevron-down mr-2"></i> Đăng Ký Đón Học Sinh
                        </button>
                    </h2>
                </div>

                <div id="collapsePickupRegistration" class="collapse" aria-labelledby="headingPickupRegistration" data-parent="#accordion">
                    <div class="card-body">
                        <form method="POST" action="index_teacher.php" id="pickupForm">
                            <div class="form-group">
                                <label for="studentSelect">Chọn Học Sinh</label>
                                <select id="studentSelect" class="form-control" name="students[]" multiple required>
                                    <?php
                                        if ($studentResult && pg_num_rows($studentResult) > 0) {
                                            while ($student = pg_fetch_assoc($studentResult)) {
                                                // Check if student is disabled
                                                $disabled = in_array($student['id'], $disabledStudents) ? 'disabled' : '';
                                                $style = in_array($student['id'], $disabledStudents) ? 'style="background-color: #e0e0e0;"' : '';
                                                echo "<option value='" . htmlspecialchars($student['id']) . "' $disabled $style>" . htmlspecialchars($student['name']) . " - " . htmlspecialchars($student['class']) . "</option>";
                                            }
                                        }
                                    ?>
                                </select>
                            </div>
                            <button type="submit" name="registerPickup" class="btn btn-primary mt-3">
                                Đón Học Sinh
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Student List (Card-Based) -->
            <div class="card">
                <div class="card-header" id="headingStudentList">
                    <h2 class="mb-0">
                        <button class="btn btn-link collapsed" data-toggle="collapse" data-target="#collapseStudentList" aria-expanded="false" aria-controls="collapseStudentList">
                            <i class="fas fa-chevron-down mr-2"></i> Danh Sách Học Sinh
                        </button>
                    </h2>
                </div>

                <div id="collapseStudentList" class="collapse" aria-labelledby="headingStudentList" data-parent="#accordion">
                    <div class="card-body">
                        <div class="student-list-section mt-4">
                            <h3>Danh Sách Học Sinh</h3>
                            <div class="row">
                                <?php 
                                    // Fetch all students again for displaying as cards
                                    if ($studentResult && pg_num_rows($studentResult) > 0) {
                                        pg_result_seek($studentResult, 0); // Reset pointer
                                        while ($student = pg_fetch_assoc($studentResult)) {
                                ?>
                                    <div class="col-md-4 col-sm-6 mb-4">
                                        <div class="card h-100">
                                            <div class="card-body">
                                                <h5 class="card-title"><?php echo htmlspecialchars($student['name']); ?></h5>
                                                <p class="card-text"><strong>Lớp:</strong> <?php echo htmlspecialchars($student['class']); ?></p>
                                                <p class="card-text"><strong>Số Điện Thoại Bố:</strong> <?php echo htmlspecialchars($student['fpn']); ?></p>
                                                <p class="card-text"><strong>Số Điện Thoại Mẹ:</strong> <?php echo htmlspecialchars($student['mpn']); ?></p>
                                            </div>
                                            <div class="card-footer">
                                                <button class="btn btn-info btn-sm view-details-btn" data-student-id="<?php echo htmlspecialchars($student['id']); ?>">
                                                    <i class="fas fa-eye"></i> Xem Chi Tiết
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                <?php 
                                        }
                                    }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pickup History Section -->
        <div class="history-section mt-4">
            <h2>Lịch Sử Đón Học Sinh</h2>
            <!-- Bulk Delete Button -->
            <button id="deleteSelectedBtn" class="btn btn-danger mb-3" style="display: none;">
                <i class="fas fa-trash-alt"></i> Xóa các mục đã chọn
            </button>
            <table id="pickupHistoryTable" class="table table-striped table-bordered dt-responsive nowrap" style="width:100%">
                <thead>
                    <tr>
                        <th>
                            <input type="checkbox" id="selectAll">
                        </th>
                        <th>Tên Học Sinh</th>
                        <th>Lớp</th>
                        <th>Thời Gian Đón</th>
                        <th>Trạng Thái</th>
                        <th>Thời gian còn lại</th>
                        <th>Hành Động</th>
                    </tr>
                </thead>
                <tbody id="pickupHistoryBody">
                    <?php
                        foreach ($history as $pickup) {
                            $pickupId = htmlspecialchars($pickup['pickup_id']);
                            $studentId = htmlspecialchars($pickup['student_id']);
                            $studentName = htmlspecialchars($pickup['student_name']);
                            $studentClass = htmlspecialchars($pickup['class']);
                            $createdAt = new DateTime($pickup['created_at']);
                            $created_at_formatted = $createdAt->format('Y-m-d H:i:s');

                            // Calculate expiration time (24 hours from created_at)
                            $expirationTime = clone $createdAt;
                            $expirationTime->modify('+1 day');
                            $now = new DateTime();

                            $interval = $now->diff($expirationTime);
                            $isExpired = $now >= $expirationTime;

                            // Check status
                            $status = htmlspecialchars($pickup['status']);
                            $isPending = strtolower($status) === 'chờ xử lý';

                            // Calculate remaining cooldown time for "Replay" button
                            $cooldownPeriod = 3 * 60; // 3 minutes in seconds
                            if ($pickup['last_replay_time']) {
                                $lastReplayTime = new DateTime($pickup['last_replay_time']);
                                $timeSinceLastReplay = $now->getTimestamp() - $lastReplayTime->getTimestamp();
                            } else {
                                $timeSinceLastReplay = PHP_INT_MAX; // If never replayed, allow replay
                            }

                            if ($timeSinceLastReplay < $cooldownPeriod) {
                                $replayCooldownRemaining = $cooldownPeriod - $timeSinceLastReplay;
                            } else {
                                $replayCooldownRemaining = 0;
                            }

                            echo "<tr id='row_$pickupId'>";

                            // Checkbox for deletion
                            if ($isExpired && !$isPending) {
                                echo "<td><input type='checkbox' class='row-checkbox' data-pickup-id='$pickupId'></td>";
                            } else {
                                echo "<td></td>";
                            }

                            echo "<td>" . $studentName . "</td>";
                            echo "<td>" . $studentClass . "</td>";
                            echo "<td>" . $created_at_formatted . "</td>";
                            echo "<td id='status_$pickupId'>$status</td>";

                            // Display remaining time or 'Expired'
                            if (!$isExpired) {
                                // Remaining time
                                $remainingTime = '';
                                if ($interval->d > 0) {
                                    $remainingTime .= $interval->d . ' ngày ';
                                }
                                if ($interval->h > 0) {
                                    $remainingTime .= $interval->h . ' giờ ';
                                }
                                if ($interval->i > 0) {
                                    $remainingTime .= $interval->i . ' phút ';
                                }
                                if ($interval->s > 0) {
                                    $remainingTime .= $interval->s . ' giây';
                                }

                                echo "<td id='countdown_$pickupId' data-expiration='" . $expirationTime->format('Y-m-d H:i:s') . "'>$remainingTime</td>";
                            } else {
                                echo "<td>Đã hết hạn</td>";
                            }

                            // Actions: Delete and Replay
                            echo "<td>";
                            if ($isExpired && !$isPending) {
                                echo "<button class='btn btn-sm btn-danger delete-btn mr-2' data-pickup-id='$pickupId'><i class='fas fa-trash-alt'></i> Xóa</button>";
                            }
                            if ($isPending) {
                                if ($replayCooldownRemaining > 0) {
                                    // "Replay" button disabled with countdown
                                    $cooldownText = gmdate("i:s", $replayCooldownRemaining);
                                    echo "<button class='btn btn-sm btn-info replay-btn' data-pickup-id='$pickupId' data-student-id='$studentId' data-deadline='" . ($now->getTimestamp() + $replayCooldownRemaining) . "' disabled>Phát lại ($cooldownText)</button>";
                                } else {
                                    // "Replay" button enabled
                                    echo "<button class='btn btn-sm btn-info replay-btn' data-pickup-id='$pickupId' data-student-id='$studentId'><i class='fas fa-redo'></i> Phát lại</button>";
                                }
                            }
                            echo "</td>";

                            echo "</tr>";
                        }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Student Details Modal (Already Integrated) -->
    <div class="modal fade" id="studentDetailsModal" tabindex="-1" aria-labelledby="studentDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Thông Tin Chi Tiết Học Sinh</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Đóng">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <!-- Student details will be loaded here via AJAX -->
                    <div id="studentDetailsContent">
                        <p>Đang tải thông tin...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Đóng</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Confirmation Modal for Successful Pickup Registration -->
    <div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Đăng Ký Đón Thành Công</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Đóng">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p>Đã đăng ký đón học sinh thành công.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-dismiss="modal">Đóng</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Notifications -->
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($_SESSION['success_message']); ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Đóng">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($_SESSION['error_message']); ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Đóng">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>

    <!-- Bootstrap JS -->
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.bundle.min.js"></script>

    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>

    <!-- Select2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <!-- Socket.io -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/socket.io/4.4.1/socket.io.min.js"></script>

    <!-- FontAwesome -->
    <script src="https://kit.fontawesome.com/a076d05399.js"></script>

    <!-- Custom JavaScript -->
    <script src="js/index_teacher.js"></script>
</body>
</html>
