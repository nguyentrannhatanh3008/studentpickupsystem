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

// Bắt đầu bộ đệm đầu ra
ob_start();

// Kết nối tới cơ sở dữ liệu PostgreSQL
$conn = pg_connect("host=$servername dbname=$dbname user=$username_db password=$password_db");

if (!$conn) {
    // Ghi log lỗi và gửi phản hồi JSON
    error_log('Kết nối thất bại: ' . pg_last_error());
    header('Content-Type: application/json');
    ob_clean();
    echo json_encode(['status' => 'error', 'message' => 'Kết nối cơ sở dữ liệu thất bại.']);
    exit();
}

// Kiểm tra đăng nhập
$isLoggedIn = isset($_SESSION['userid']);

if ($isLoggedIn) {
    $userid = $_SESSION['userid'];
    // Lấy thông tin người dùng
    $query = "SELECT * FROM public.user WHERE id = $1";
    $result = pg_query_params($conn, $query, array($userid));

    if ($result === false) {
        error_log("Lỗi truy vấn người dùng: " . pg_last_error($conn));
        $isLoggedIn = false;
    } else {
        $user = pg_fetch_assoc($result);
        if (!$user) {
            $isLoggedIn = false;
        } else {
            // Cập nhật role trong session
            $_SESSION['role'] = $user['role'];
            $role = $user['role'];

            // Ghi log để kiểm tra vai trò (bạn có thể loại bỏ sau khi kiểm tra)
            error_log("User ID: $userid, Role: '$role'");

            // Kiểm tra vai trò và chuyển hướng nếu không phải PhuHuynh
            if (strcasecmp(trim($role), 'PhuHuynh') !== 0) {
                header("Location: index_teacher.php");
                exit();
            }
        }
    }

    // Lấy số lượng thông báo chưa đọc
    $unreadCount = 0;
    $countQuery = "SELECT COUNT(*) AS unread FROM public.notifications WHERE user_id = $1 AND status = 'Chưa đọc'";
    $countResult = pg_query_params($conn, $countQuery, array($userid));

    if ($countResult) {
        $countRow = pg_fetch_assoc($countResult);
        $unreadCount = intval($countRow['unread']);
    } else {
        error_log("Lỗi truy vấn thông báo: " . pg_last_error($conn));
    }
}

// Nếu người dùng chưa đăng nhập
if (!$isLoggedIn) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json');
        ob_clean();
        echo json_encode(['status' => 'error', 'message' => 'Người dùng chưa đăng nhập']);
        exit();
    } else {
        header("Location: login.php");
        exit();
    }
}



// Hàm để gửi yêu cầu "Replay" đến Flask server
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
    curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Max execution time of 10 seconds

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
        return ['status' => 'error', 'message' => 'Không nhận được trạng thái từ Flask server.'];
    }

    return $response;
}

// Xử lý yêu cầu POST (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Xử lý đăng ký đón
    if (isset($_POST['registerPickup'])) {
        if (!empty($_POST['students'])) {
            $selectedStudents = $_POST['students'];
            $responseArray = [];

            foreach ($selectedStudents as $studentId) {
                $pickupQuery = "INSERT INTO public.pickup_history (user_id, student_id, created_at, status) VALUES ($1, $2, NOW(), 'Chờ xử lý') RETURNING id, created_at";
                $pickupResult = pg_query_params($conn, $pickupQuery, array($userid, $studentId));

                if ($pickupResult) {
                    $pickupRow = pg_fetch_assoc($pickupResult);
                    $created_at = date('Y-m-d H:i:s', strtotime($pickupRow['created_at']));

                    // Lấy thông tin học sinh
                    $studentQuery = "SELECT * FROM public.student WHERE id = $1";
                    $studentResult = pg_query_params($conn, $studentQuery, array($studentId));

                    if ($studentResult) {
                        $student = pg_fetch_assoc($studentResult);

                        // Lấy FPN và MPN của học sinh
                        $FPN = $student['fpn'];
                        $MPN = $student['mpn'];

                        // Mảng lưu trữ user_ids để gửi thông báo
                        $user_ids = [];

                        // Kiểm tra FPN
                        if (!empty($FPN)) {
                            $userQuery = "SELECT id FROM public.user WHERE phone = $1";
                            $userResult = pg_query_params($conn, $userQuery, array($FPN));
                            if ($userResult && pg_num_rows($userResult) > 0) {
                                while ($user = pg_fetch_assoc($userResult)) {
                                    $user_ids[] = $user['id'];
                                }
                            }
                        }

                        // Kiểm tra MPN
                        if (!empty($MPN)) {
                            $userQuery = "SELECT id FROM public.user WHERE phone = $1";
                            $userResult = pg_query_params($conn, $userQuery, array($MPN));
                            if ($userResult && pg_num_rows($userResult) > 0) {
                                while ($user = pg_fetch_assoc($userResult)) {
                                    if (!in_array($user['id'], $user_ids)) {
                                        $user_ids[] = $user['id'];
                                    }
                                }
                            }
                        }

                        // Gửi thông báo cho các user_ids
                        foreach ($user_ids as $notify_user_id) {
                            $notificationTitle = "Yêu cầu đón";
                            $notificationMessage = "Yêu cầu đón cho học sinh " . $student['name'] . " lúc " . $created_at;
                            $notificationStatus = "Chưa đọc";
                            $notificationQuery = "INSERT INTO public.notifications (user_id, title, message, status, created_at) VALUES ($1, $2, $3, $4, NOW())";
                            $notificationResult = pg_query_params($conn, $notificationQuery, array($notify_user_id, $notificationTitle, $notificationMessage, $notificationStatus));

                            if (!$notificationResult) {
                                header('Content-Type: application/json');
                                ob_clean();
                                echo json_encode(['status' => 'error', 'message' => 'Không thể chèn thông báo: ' . pg_last_error()]);
                                exit();
                            }
                        }

                        $responseArray[] = [
                            'status' => 'Chờ xử lý',
                            'student_id' => $studentId,
                            'student_name' => $student['name'],
                            'class' => $student['class'],
                            'created_at' => $created_at,
                            'pickup_id' => $pickupRow['id']
                        ];
                    } else {
                        header('Content-Type: application/json');
                        ob_clean();
                        echo json_encode(['status' => 'error', 'message' => 'Không thể lấy thông tin học sinh: ' . pg_last_error()]);
                        exit();
                    }
                } else {
                    header('Content-Type: application/json');
                    ob_clean();
                    echo json_encode(['status' => 'error', 'message' => 'Không thể chèn lịch sử đón: ' . pg_last_error()]);
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
            echo json_encode(['status' => 'error', 'message' => 'Không có học sinh nào được chọn.']);
            exit();
        }
    }

    // Xử lý các hành động 'confirm', 'cancel', 'replay', 'delete_pickups'
    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        if (isset($_POST['pickup_id'])) {
            $pickupId = intval($_POST['pickup_id']);
        }

        if ($action === 'confirm') {
            // Xử lý xác nhận
            $query = "UPDATE public.pickup_history SET status = 'Đã đón', pickup_time = NOW() WHERE id = $1 RETURNING student_id, pickup_time";
            $result = pg_query_params($conn, $query, array($pickupId));

            if ($result) {
                $row = pg_fetch_assoc($result);
                $studentId = $row['student_id'];
                $pickupTime = date('Y-m-d H:i:s', strtotime($row['pickup_time']));

                // Lấy thông tin học sinh
                $studentQuery = "SELECT * FROM public.student WHERE id = $1";
                $studentResult = pg_query_params($conn, $studentQuery, array($studentId));

                if ($studentResult) {
                    $student = pg_fetch_assoc($studentResult);

                    // Lấy FPN và MPN của học sinh
                    $FPN = $student['fpn'];
                    $MPN = $student['mpn'];

                    // Mảng lưu trữ user_ids để gửi thông báo
                    $user_ids = [];

                    // Kiểm tra FPN
                    if (!empty($FPN)) {
                        $userQuery = "SELECT id FROM public.user WHERE phone = $1";
                        $userResult = pg_query_params($conn, $userQuery, array($FPN));
                        if ($userResult && pg_num_rows($userResult) > 0) {
                            while ($user = pg_fetch_assoc($userResult)) {
                                $user_ids[] = $user['id'];
                            }
                        }
                    }

                    // Kiểm tra MPN
                    if (!empty($MPN)) {
                        $userQuery = "SELECT id FROM public.user WHERE phone = $1";
                        $userResult = pg_query_params($conn, $userQuery, array($MPN));
                        if ($userResult && pg_num_rows($userResult) > 0) {
                            while ($user = pg_fetch_assoc($userResult)) {
                                if (!in_array($user['id'], $user_ids)) {
                                    $user_ids[] = $user['id'];
                                }
                            }
                        }
                    }

                    // Gửi thông báo cho các user_ids
                    foreach ($user_ids as $notify_user_id) {
                        $notificationTitle = "Đã Được Xác Nhận";
                        $notificationMessage = "Yêu cầu đón cho học sinh " . $student['name'] . " lúc " . $pickupTime . " đã được xác nhận.";
                        $notificationStatus = "Chưa đọc";
                        $notificationQuery = "INSERT INTO public.notifications (user_id, title, message, status, created_at) VALUES ($1, $2, $3, $4, NOW())";
                        $notificationResult = pg_query_params($conn, $notificationQuery, array($notify_user_id, $notificationTitle, $notificationMessage, $notificationStatus));

                        if (!$notificationResult) {
                            header('Content-Type: application/json');
                            ob_clean();
                            echo json_encode(['status' => 'error', 'message' => 'Không thể chèn thông báo: ' . pg_last_error()]);
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
                    echo json_encode(['status' => 'error', 'message' => 'Không thể lấy thông tin học sinh: ' . pg_last_error()]);
                    exit();
                }
            } else {
                header('Content-Type: application/json');
                ob_clean();
                echo json_encode(['status' => 'error', 'message' => 'Không thể cập nhật lịch sử đón: ' . pg_last_error()]);
                exit();
            }
        } elseif ($action === 'cancel') {
            // Xử lý hủy
            $query = "UPDATE public.pickup_history SET status = 'Đã hủy' WHERE id = $1 RETURNING student_id";
            $result = pg_query_params($conn, $query, array($pickupId));

            if ($result) {
                $row = pg_fetch_assoc($result);
                $studentId = $row['student_id'];

                // Lấy thông tin học sinh
                $studentQuery = "SELECT * FROM public.student WHERE id = $1";
                $studentResult = pg_query_params($conn, $studentQuery, array($studentId));

                if ($studentResult) {
                    $student = pg_fetch_assoc($studentResult);

                    // Lấy FPN và MPN của học sinh
                    $FPN = $student['fpn'];
                    $MPN = $student['mpn'];

                    // Mảng lưu trữ user_ids để gửi thông báo
                    $user_ids = [];

                    // Kiểm tra FPN
                    if (!empty($FPN)) {
                        $userQuery = "SELECT id FROM public.user WHERE phone = $1";
                        $userResult = pg_query_params($conn, $userQuery, array($FPN));
                        if ($userResult && pg_num_rows($userResult) > 0) {
                            while ($user = pg_fetch_assoc($userResult)) {
                                $user_ids[] = $user['id'];
                            }
                        }
                    }

                    // Kiểm tra MPN
                    if (!empty($MPN)) {
                        $userQuery = "SELECT id FROM public.user WHERE phone = $1";
                        $userResult = pg_query_params($conn, $userQuery, array($MPN));
                        if ($userResult && pg_num_rows($userResult) > 0) {
                            while ($user = pg_fetch_assoc($userResult)) {
                                if (!in_array($user['id'], $user_ids)) {
                                    $user_ids[] = $user['id'];
                                }
                            }
                        }
                    }

                    // Gửi thông báo cho các user_ids
                    foreach ($user_ids as $notify_user_id) {
                        $notificationTitle = "Đã hủy đón";
                        $notificationMessage = "Yêu cầu đón cho học sinh " . $student['name'] . " đã bị hủy.";
                        $notificationStatus = "Chưa đọc";
                        $notificationQuery = "INSERT INTO public.notifications (user_id, title, message, status, created_at) VALUES ($1, $2, $3, $4, NOW())";
                        $notificationResult = pg_query_params($conn, $notificationQuery, array($notify_user_id, $notificationTitle, $notificationMessage, $notificationStatus));

                        if (!$notificationResult) {
                            header('Content-Type: application/json');
                            ob_clean();
                            echo json_encode(['status' => 'error', 'message' => 'Không thể chèn thông báo: ' . pg_last_error()]);
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
                    echo json_encode(['status' => 'error', 'message' => 'Không thể lấy thông tin học sinh: ' . pg_last_error()]);
                    exit();
                }
            } else {
                header('Content-Type: application/json');
                ob_clean();
                echo json_encode(['status' => 'error', 'message' => 'Không thể cập nhật trạng thái đón: ' . pg_last_error()]);
                exit();
            }
        } elseif ($action === 'replay') {
            // Xử lý phát lại
            // Check if all required parameters are present
            if (!isset($_POST['pickup_id']) || !isset($_POST['student_id']) || !isset($_POST['student_name'])) {
                // Log the received POST data for debugging
                error_log("Replay Action Missing Parameters: " . print_r($_POST, true));

                header('Content-Type: application/json');
                ob_clean();
                echo json_encode(['status' => 'error', 'message' => 'Thiếu tham số yêu cầu.']);
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
                        'message' => 'Vui lòng chờ trước khi phát lại.',
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
                                $errorMessage = isset($replayResponse['message']) ? $replayResponse['message'] : 'Không thể gửi yêu cầu phát lại.';
                                error_log("Error in sendReplayToFlask: $errorMessage");
                                header('Content-Type: application/json');
                                ob_clean();
                                echo json_encode([
                                    'status' => 'error',
                                    'message' => 'Không thể gửi yêu cầu phát lại: ' . $errorMessage
                                ]);
                                exit();
                            }
                        } else {
                            header('Content-Type: application/json');
                            ob_clean();
                            echo json_encode(['status' => 'error', 'message' => 'Không thể lấy thông tin học sinh: ' . pg_last_error($conn)]);
                            exit();
                        }
                    } else {
                        error_log("Failed to update last_replay_time for pickup_id $pickupId: " . pg_last_error($conn));
                        header('Content-Type: application/json');
                        ob_clean();
                        echo json_encode(['status' => 'error', 'message' => 'Không thể cập nhật thời gian phát lại: ' . pg_last_error($conn)]);
                        exit();
                    }
                }
            } else {
                error_log("Failed to fetch pickup_history for pickup_id $pickupId: " . pg_last_error($conn));
                header('Content-Type: application/json');
                ob_clean();
                echo json_encode(['status' => 'error', 'message' => 'Không thể lấy thông tin yêu cầu đón: ' . pg_last_error($conn)]);
                exit();
            }
        } elseif ($action === 'delete_pickups') {
            // Xử lý yêu cầu xóa pickups
            if (isset($_POST['pickup_ids'])) {
                $pickupIds = $_POST['pickup_ids'];

                if (!is_array($pickupIds) || empty($pickupIds)) {
                    header('Content-Type: application/json');
                    ob_clean();
                    echo json_encode(['status' => 'error', 'message' => 'Danh sách pickup không hợp lệ.']);
                    exit();
                }

                // Chuẩn bị chuỗi để sử dụng trong câu truy vấn
                $placeholders = [];
                $params = []; // Không cần user_id vì phụ huynh có thể xóa tất cả pickups của mình
                $i = 1;
                foreach ($pickupIds as $id) {
                    if (!is_numeric($id)) {
                        header('Content-Type: application/json');
                        ob_clean();
                        echo json_encode(['status' => 'error', 'message' => 'ID pickup không hợp lệ.']);
                        exit();
                    }
                    $placeholders[] = '$' . $i++;
                    $params[] = $id;
                }
                $placeholders_str = implode(',', $placeholders);

                // Truy vấn để xóa các pickups
                $deleteQuery = "DELETE FROM public.pickup_history WHERE id IN ($placeholders_str) AND student_id IN (SELECT id FROM public.student WHERE FPN = $".($i)." OR MPN = $".($i+1).")";
                // Append FPN and MPN to params
                $params[] = $user['phone'];
                $params[] = $user['phone'];
                $result = pg_query_params($conn, $deleteQuery, $params);

                if ($result) {
                    $deletedCount = pg_affected_rows($result);
                    header('Content-Type: application/json');
                    ob_clean();
                    echo json_encode(['status' => 'success', 'deleted_count' => $deletedCount]);
                    exit();
                } else {
                    header('Content-Type: application/json');
                    ob_clean();
                    echo json_encode(['status' => 'error', 'message' => 'Không thể xóa pickups: ' . pg_last_error($conn)]);
                    exit();
                }
            } else {
                header('Content-Type: application/json');
                ob_clean();
                echo json_encode(['status' => 'error', 'message' => 'Thiếu tham số yêu cầu.']);
                exit();
            }
        }
    }

    header('Content-Type: application/json');
    ob_clean();
    echo json_encode(['status' => 'error', 'message' => 'Yêu cầu POST không hợp lệ.']);
    exit();
}

// Lấy danh sách học sinh liên quan đến phụ huynh
// Dựa trên FPN hoặc MPN của người dùng
$studentQuery = "SELECT * FROM public.student WHERE FPN = $1 OR MPN = $1";
$studentResult = pg_query_params($conn, $studentQuery, array($user['phone']));

if ($studentResult === false) {
    error_log("Không thể lấy danh sách học sinh: " . pg_last_error($conn));
}

// Lấy lịch sử đón để hiển thị trên trang
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

$pendingPickupsQuery = "SELECT p.id AS pickup_id, s.name AS student_name, s.class, date_trunc('second', p.created_at) AS created_at, p.student_id, p.status, p.last_replay_time
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

if (ob_get_length()) {
    ob_end_clean();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <!-- Các thẻ meta và liên kết CSS -->
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Student Pick-Up System</title>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.dataTables.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/index.css">
</head>
<body>
    <header class="header">
        <h1></h1>
        <nav class="navbar">
            <ul class="nav">
                <?php if ($user): ?>
                    <li class="nav-item dropdown">
                        <a href="#" class="nav-link dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" style="color: white;">
                            <i class="fas fa-user-circle" style="font-size: 20px; margin-right: 10px; vertical-align: middle;"></i>
                            <span class="username" style="font-size: 20px; font-weight: bold; margin-right: 5px; vertical-align: middle;">
                                <?php echo htmlspecialchars($user['name']); ?>
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
        <div class="top">
            <i class="fas fa-bars" id ="btn"></i>
        </div>
        <ul class="nav-list">
            <li><a href="index.php"><i class="fas fa-home"></i> <span class="nav-item">Trang Chủ</span></a></li>
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
            <li><a href="studentlist.php"><i class="fas fa-child"></i> <span class="nav-item">Danh Sách Học Sinh</span></a></li>
        </ul>
    </div>
    
    <div class="main-container">
        <div class="form-container">
            <h2>Đăng ký đón con</h2>
            <form method="POST" action="index.php" id="pickupForm">
                <div class="input-group">
                    <div id="studentCheckboxes">
                        <?php 
                        if ($user && $studentResult) {
                            while ($row = pg_fetch_assoc($studentResult)) : ?>
                                <div class="checkbox-item">
                                    <input type="checkbox" id="student_<?php echo htmlspecialchars($row['id']); ?>" 
                                        name="students[]" 
                                        value="<?php echo htmlspecialchars($row['id']); ?>"
                                        data-name="<?php echo htmlspecialchars($row['name']); ?>"
                                        data-class="<?php echo htmlspecialchars($row['class']); ?>"
                                        <?php if (in_array($row['id'], $disabledStudents)) echo 'disabled'; ?>>
                                    <label for="student_<?php echo htmlspecialchars($row['id']); ?>">
                                        <?php echo htmlspecialchars($row['name']); ?>
                                    </label>
                                </div>
                        <?php endwhile; 
                        } ?>
                    </div>
                </div>
                <button type="submit" name="registerPickup" class="btn btn-primary mt-3">
                    Đón Con
                </button>
            </form>
        </div>
        <div class="auto-fill-container" id="autoFillContainer"></div>
        <div id="dialogContainer" class="dialog-container">
        <?php foreach ($pendingPickups as $pickup): ?>
            <div class="dialog-box" id="dialog_<?php echo htmlspecialchars($pickup['pickup_id']); ?>">
                <h2>Thông tin đón con</h2>
                <p><strong>Tên:</strong> <?php echo htmlspecialchars($pickup['student_name']); ?></p>
                <p><strong>Lớp:</strong> <?php echo htmlspecialchars($pickup['class']); ?></p>
                <p><strong>Thời gian:</strong> <?php echo htmlspecialchars($pickup['created_at']); ?></p>
                <p><strong>Trạng thái:</strong> <span id="dialog_status_<?php echo htmlspecialchars($pickup['pickup_id']); ?>" style="color: orange;">Chờ xử lý</span></p>
                <button type="button" 
                        data-pickup-id="<?php echo htmlspecialchars($pickup['pickup_id']); ?>" 
                        data-student-id="<?php echo htmlspecialchars($pickup['student_id']); ?>" 
                        data-student-name="<?php echo htmlspecialchars($pickup['student_name']); ?>" 
                        data-student-class="<?php echo htmlspecialchars($pickup['class']); ?>" 
                        class="confirm-btn btn btn-success">Xác nhận</button>
                <button type="button" 
                        data-pickup-id="<?php echo htmlspecialchars($pickup['pickup_id']); ?>" 
                        data-student-id="<?php echo htmlspecialchars($pickup['student_id']); ?>" 
                        class="cancel-btn btn btn-warning">Hủy</button>
                <button type="button" 
                        data-pickup-id="<?php echo htmlspecialchars($pickup['pickup_id']); ?>" 
                        data-student-id="<?php echo htmlspecialchars($pickup['student_id']); ?>" 
                        data-student-name="<?php echo htmlspecialchars($pickup['student_name']); ?>" 
                        data-student-class="<?php echo htmlspecialchars($pickup['class']); ?>" 
                        class="replay-btn btn btn-info">Phát lại</button>
            </div>
        <?php endforeach; ?>

        </div>

    </div>
    <div class="history-section">
        <h2>Lịch Sử Đón</h2>
        <!-- Nút xóa đã chọn -->
        <button id="deleteSelectedBtn" class="btn btn-danger mb-3" style="display: none;">
            <i class="fas fa-trash-alt"></i> Xóa các mục đã chọn
        </button>
        <table id="pickupHistoryTable" class="table table-striped">
            <thead>
                <tr>
                    <th>
                        <input type="checkbox" id="selectAll">
                        <i class="fas fa-trash-alt" id="deleteAllBtn" style="cursor: pointer; color: white; margin-left: 10px;" title="Xóa các mục đã chọn"></i>
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
                    if ($historyResult && pg_num_rows($historyResult) > 0) {
                        while ($row = pg_fetch_assoc($historyResult)) {
                            $pickupId = htmlspecialchars($row['pickup_id']);
                            $studentId = htmlspecialchars($row['student_id']);
                            $studentName = htmlspecialchars($row['student_name']);
                            $studentClass = htmlspecialchars($row['class']);
                            $createdAt = new DateTime($row['created_at']);
                            $created_at_formatted = $createdAt->format('Y-m-d H:i:s');

                            // Calculate expiration time (24 hours from created_at)
                            $expirationTime = clone $createdAt;
                            $expirationTime->modify('+1 day');
                            $now = new DateTime();

                            $interval = $now->diff($expirationTime);
                            $isExpired = $now >= $expirationTime;

                            // Check status
                            $status = htmlspecialchars($row['status']);
                            $isPending = strtolower($status) === 'chờ xử lý';

                            // Calculate remaining cooldown time for "Replay" button
                            $cooldownPeriod = 3 * 60; // 3 minutes in seconds
                            if ($row['last_replay_time']) {
                                $lastReplayTime = new DateTime($row['last_replay_time']);
                                $timeSinceLastReplay = $now->getTimestamp() - $lastReplayTime->getTimestamp();
                            } else {
                                $timeSinceLastReplay = PHP_INT_MAX; // Nếu chưa bao giờ phát lại, cho phép phát lại
                            }

                            if ($timeSinceLastReplay < $cooldownPeriod) {
                                $replayCooldownRemaining = $cooldownPeriod - $timeSinceLastReplay;
                            } else {
                                $replayCooldownRemaining = 0;
                            }

                            echo "<tr id='row_$pickupId'>";

                            // Only display checkbox if expired and not pending
                            if ($isExpired && !$isPending) {
                                echo "<td><input type='checkbox' class='row-checkbox' data-pickup-id='$pickupId'></td>";
                            } else {
                                echo "<td></td>";
                            }

                            echo "<td><span class='student-name' data-student-name='$studentName'>$studentName</span></td>";
                            echo "<td><span class='student-class' data-student-class='$studentClass'>$studentClass</span></td>";
                            echo "<td>$created_at_formatted</td>";
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

                            // Delete and Action buttons
                            echo "<td>";
                            if ($isExpired && !$isPending) {
                                echo "<button class='btn btn-sm btn-danger delete-btn' data-pickup-id='$pickupId'><i class='fas fa-trash-alt'></i> Xóa</button>";
                            }
                            if ($isPending) {
                                if ($replayCooldownRemaining > 0) {
                                    // "Replay" button disabled with countdown
                                    $cooldownText = gmdate("i:s", $replayCooldownRemaining);
                                    echo "<button class='btn btn-sm btn-info replay-btn' data-pickup-id='$pickupId' data-student-id='$studentId' data-cooldown='$replayCooldownRemaining' disabled>Phát lại ($cooldownText)</button>";
                                } else {
                                    // "Replay" button enabled
                                    echo "<button class='btn btn-sm btn-info replay-btn' data-pickup-id='$pickupId' data-student-id='$studentId'><i class='fas fa-redo'></i> Phát lại</button>";
                                }
                            }
                            echo "</td>";

                            echo "</tr>";
                        }
                    }
                ?>
            </tbody>
        </table>
    </div>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success">
            <?php echo htmlspecialchars($_SESSION['success_message']); ?>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger">
            <?php echo htmlspecialchars($_SESSION['error_message']); ?>
        </div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>


    <!-- Tải jQuery trước -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>

    <!-- Tải Bootstrap JavaScript sau jQuery -->
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.bundle.min.js"></script>

    <!-- Tải DataTables sau jQuery -->
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>

    <!-- Tải Socket.io -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/socket.io/4.4.1/socket.io.min.js"></script>

    <!-- Tải FontAwesome -->
    <script src="https://kit.fontawesome.com/a076d05399.js"></script>

    <!-- Tải mã JavaScript của bạn cuối cùng -->
    <script src="js/index.js"></script>
</body>
</html>

<?php
pg_close($conn);
?>
