<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
$servername = "localhost";
$username = "postgres";
$password = "!xNq!TRWY.AuD9U";
$dbname = "studentpickup";

// Bắt đầu bộ đệm đầu ra
ob_start();

// Kết nối tới cơ sở dữ liệu PostgreSQL
$conn = pg_connect("host=$servername dbname=$dbname user=$username password=$password");

if (!$conn) {
    // Gửi phản hồi JSON nếu kết nối thất bại
    header('Content-Type: application/json');
    ob_clean();
    echo json_encode(['status' => 'error', 'message' => 'Kết nối thất bại: ' . pg_last_error()]);
    exit();
}

$isLoggedIn = isset($_SESSION['userid']);

if ($isLoggedIn) {
    $userid = $_SESSION['userid'];

    // Lấy thông tin người dùng từ cơ sở dữ liệu
    $query = "SELECT * FROM public.user WHERE id = $1";
    $result = pg_query_params($conn, $query, array($userid));

    if ($result === false) {
        echo "Lỗi truy vấn: " . pg_last_error($conn);
    } else {
        $user = pg_fetch_assoc($result);
        if (!$user) {
            $isLoggedIn = false; // Nếu không tìm thấy người dùng, đặt trạng thái đăng nhập thành false
        }
    }
    $unreadCount = 0;
    $countQuery = "SELECT COUNT(*) AS unread FROM public.notifications WHERE user_id = $1 AND status = 'Chưa đọc'";
    $countResult = pg_query_params($conn, $countQuery, array($userid));
    
    if ($countResult) {
        $countRow = pg_fetch_assoc($countResult);
        $unreadCount = intval($countRow['unread']);
    } else {
        // Xử lý lỗi truy vấn nếu cần
        $unreadCount = 0;
    }
}

// Kiểm tra nếu người dùng đã đăng nhập
$userid = $_SESSION['userid'] ?? null;
if (!$userid) {
    // Xử lý yêu cầu AJAX cho người dùng chưa xác thực
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json');
        ob_clean();
        echo json_encode(['status' => 'error', 'message' => 'Người dùng chưa đăng nhập']);
        exit();
    } else {
        // Chuyển hướng đối với yêu cầu không phải AJAX
        header("Location: login.html");
        exit();
    }
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

                        // Chèn thông báo vào bảng notifications
                        $notificationTitle = "Yêu cầu đón";
                        $notificationMessage = "Yêu cầu đón cho học sinh " . $student['name'] . " lúc " . $created_at;
                        $notificationStatus = "Chưa đọc";
                        $notificationQuery = "INSERT INTO public.notifications (user_id, title, message, status, created_at) VALUES ($1, $2, $3, $4, NOW())";
                        $notificationResult = pg_query_params($conn, $notificationQuery, array($userid, $notificationTitle, $notificationMessage, $notificationStatus));

                        if (!$notificationResult) {
                            header('Content-Type: application/json');
                            ob_clean();
                            echo json_encode(['status' => 'error', 'message' => 'Không thể chèn thông báo: ' . pg_last_error()]);
                            exit();
                        }

                        $responseArray[] = [
                            'status' => 'success',
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

    // Xử lý các hành động 'confirm' và 'cancel'
    if (isset($_POST['action']) && isset($_POST['pickup_id'])) {
        $pickupId = $_POST['pickup_id'];
        $action = $_POST['action'];

        if ($action === 'confirm') {
            // Cập nhật trạng thái thành 'Đã đón' và lấy student_id và pickup_time
            $query = "UPDATE public.pickup_history SET status = 'Đã đón', pickup_time = NOW() WHERE id = $1 AND user_id = $2 RETURNING student_id, pickup_time";
            $result = pg_query_params($conn, $query, array($pickupId, $userid));

            if ($result) {
                $row = pg_fetch_assoc($result);
                $studentId = $row['student_id'];
                $pickupTime = date('Y-m-d H:i:s', strtotime($row['pickup_time']));

                // Lấy thông tin học sinh
                $studentQuery = "SELECT name FROM public.student WHERE id = $1";
                $studentResult = pg_query_params($conn, $studentQuery, array($studentId));

                if ($studentResult) {
                    $studentRow = pg_fetch_assoc($studentResult);
                    $studentName = $studentRow['name'];

                    // Chuẩn bị nội dung thông báo
                    $notificationTitle = "Đã Được Xác Nhận";
                    $notificationMessage = "Yêu cầu đón cho học sinh " . $studentName . " lúc " . $pickupTime . " đã được xác nhận.";
                    $notificationStatus = "Chưa đọc";

                    // Chèn thông báo vào bảng notifications
                    $notificationQuery = "INSERT INTO public.notifications (user_id, title, message, status, created_at) VALUES ($1, $2, $3, $4, NOW())";
                    $notificationResult = pg_query_params($conn, $notificationQuery, array($userid, $notificationTitle, $notificationMessage, $notificationStatus));

                    if (!$notificationResult) {
                        header('Content-Type: application/json');
                        ob_clean();
                        echo json_encode(['status' => 'error', 'message' => 'Không thể chèn thông báo: ' . pg_last_error()]);
                        exit();
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
            // Cập nhật trạng thái thành 'Đã hủy' và lấy student_id và pickup_time
            $query = "UPDATE public.pickup_history SET status = 'Đã hủy' WHERE id = $1 AND user_id = $2 RETURNING student_id, pickup_time";
            $result = pg_query_params($conn, $query, array($pickupId, $userid));
        
            if ($result) {
                $row = pg_fetch_assoc($result);
                $studentId = $row['student_id'];
                $pickupTime = $row['pickup_time'];
        
                // Lấy thông tin học sinh
                $studentQuery = "SELECT name FROM public.student WHERE id = $1";
                $studentResult = pg_query_params($conn, $studentQuery, array($studentId));
        
                if ($studentResult) {
                    $studentRow = pg_fetch_assoc($studentResult);
                    $studentName = $studentRow['name'];
        
                    // Chuẩn bị nội dung thông báo
                    $notificationTitle = "Đã hủy đón";
                    $notificationMessage = "Yêu cầu đón cho học sinh " . $studentName . " đã bị hủy.";
                    $notificationStatus = "Chưa đọc";
        
                    // Chèn thông báo vào bảng notifications
                    $notificationQuery = "INSERT INTO public.notifications (user_id, title, message, status, created_at) VALUES ($1, $2, $3, $4, NOW())";
                    $notificationResult = pg_query_params($conn, $notificationQuery, array($userid, $notificationTitle, $notificationMessage, $notificationStatus));
        
                    if (!$notificationResult) {
                        header('Content-Type: application/json');
                        ob_clean();
                        echo json_encode(['status' => 'error', 'message' => 'Không thể chèn thông báo: ' . pg_last_error()]);
                        exit();
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
        }
    }

    // Xử lý yêu cầu xóa pickups
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_pickups' && isset($_POST['pickup_ids'])) {
        $pickupIds = $_POST['pickup_ids'];

        if (!is_array($pickupIds) || empty($pickupIds)) {
            header('Content-Type: application/json');
            ob_clean();
            echo json_encode(['status' => 'error', 'message' => 'Danh sách pickup không hợp lệ.']);
            exit();
        }

        // Chuẩn bị chuỗi để sử dụng trong câu truy vấn
        $placeholders = [];
        $params = [$userid]; // Tham số đầu tiên là user_id
        $i = 2;
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

        // Truy vấn để xóa các pickups thuộc về người dùng
        $deleteQuery = "DELETE FROM public.pickup_history WHERE user_id = $1 AND id IN ($placeholders_str)";
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
    }

    // Nếu script đến đây, tức là yêu cầu POST không hợp lệ
    header('Content-Type: application/json');
    ob_clean();
    echo json_encode(['status' => 'error', 'message' => 'Yêu cầu POST không hợp lệ.']);
    exit();
}

// Tiếp tục với việc hiển thị trang

$historyArray = [];

$historyQuery = "SELECT p.id, s.name AS student_name, s.class, p.created_at, p.status, p.pickup_time
                 FROM public.pickup_history p
                 JOIN public.student s ON p.student_id = s.id
                 WHERE p.user_id = $1
                 ORDER BY p.created_at DESC";
$historyResult = pg_query_params($conn, $historyQuery, array($userid));

if ($historyResult) {
    while ($row = pg_fetch_assoc($historyResult)) {
        $historyArray[] = $row;
    }
} else {
    error_log("Không thể lấy lịch sử đón: " . pg_last_error());
}

// Lấy học sinh liên quan đến người dùng
$studentQuery = "SELECT * FROM public.student WHERE FPN = $1 OR MPN = $1";
$result = pg_query_params($conn, $studentQuery, array($user['phone']));

if ($result === false) {
    error_log("Không thể lấy danh sách học sinh: " . pg_last_error($conn));
}

// Lấy đón đang chờ xử lý
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
    error_log("Không thể lấy đón đang chờ xử lý: " . pg_last_error($conn));
}

$disabledStudents = [];
$disabledQuery = "SELECT DISTINCT student_id FROM public.pickup_history WHERE user_id = $1 AND status = 'Chờ xử lý'";
$disabledResult = pg_query_params($conn, $disabledQuery, array($userid));

if ($disabledResult) {
    while ($row = pg_fetch_assoc($disabledResult)) {
        $disabledStudents[] = $row['student_id'];
    }
} else {
    error_log("Không thể lấy danh sách học sinh bị khóa: " . pg_last_error($conn));
}

?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Pick-Up System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.bundle.min.js"></script>
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
                        <a href="login.html" class="nav-link">Đăng Nhập</a>
                    </li>
                    <li class="nav-item">
                        <a href="register.html" class="nav-link">Đăng Ký</a>
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
                        if ($user) {
                            while ($row = pg_fetch_assoc($result)) : ?>
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
                <button type="submit" name="registerPickup">Đón Con</button>
            </form>
        </div>
        <div class="auto-fill-container" id="autoFillContainer"></div>
        <div id="dialogContainer" class="dialog-container">
            <?php foreach ($pendingPickups as $pickup): ?>
                <div class="dialog-box">
                    <h2>Thông tin đón con</h2>
                    <p><strong>Tên:</strong> <?php echo htmlspecialchars($pickup['student_name']); ?></p>
                    <p><strong>Lớp:</strong> <?php echo htmlspecialchars($pickup['class']); ?></p>
                    <p><strong>Thời gian:</strong> <?php echo htmlspecialchars($pickup['created_at']); ?></p>
                    <p><strong>Trạng thái:</strong> <span id="dialog_status_<?php echo htmlspecialchars($pickup['pickup_id']); ?>" style="color: orange;">Chờ xử lý</span></p>
                    <button type="button" data-pickup-id="<?php echo htmlspecialchars($pickup['pickup_id']); ?>" class="confirm-btn btn btn-success">Xác nhận</button>
                    <button type="button" data-pickup-id="<?php echo htmlspecialchars($pickup['pickup_id']); ?>" class="cancel-btn btn btn-warning">Hủy</button>
                </div>
            <?php endforeach; ?>
        </div>

    </div>
    <div class="history-section">
        <h2>Pickup History</h2>
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
                    <th>Hành Động</th>
                </tr>
            </thead>
            <tbody id="pickupHistoryBody">
            <?php
                $historyQuery = "SELECT p.id, s.name AS student_name, s.class, p.created_at, p.status, p.pickup_time
                FROM public.pickup_history p
                JOIN public.student s ON p.student_id = s.id
                WHERE p.user_id = $1
                ORDER BY p.created_at DESC";
                $historyResult = pg_query_params($conn, $historyQuery, array($userid));

                if ($historyResult === false) {
                    error_log("Failed to execute history query: " . pg_last_error($conn));
                    echo "<tr><td colspan='6'>Error fetching history.</td></tr>";
                } else {
                    if (pg_num_rows($historyResult) > 0) {
                        while ($row = pg_fetch_assoc($historyResult)) {
                            $dt = new DateTime($row['created_at']);
                            $created_at = $dt->format('Y-m-d H:i:s');

                            // Kiểm tra trạng thái
                            $isPending = strtolower($row['status']) === 'Chờ xử lý';

                            echo "<tr id='row_" . htmlspecialchars($row['id']) . "'>";
                            if ($isPending) {
                                // Không hiển thị checkbox và nút xóa cho mục Pending
                                echo "<td></td>";
                            } else {
                                echo "<td><input type='checkbox' class='row-checkbox' data-pickup-id='" . htmlspecialchars($row['id']) . "'></td>";
                            }
                            echo "<td>" . htmlspecialchars($row['student_name']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['class']) . "</td>";
                            echo "<td>" . htmlspecialchars($created_at) . "</td>";
                            echo "<td id='status_" . htmlspecialchars($row['id']) . "'>" . htmlspecialchars($row['status']) . "</td>";
                            echo "<td><button class='btn btn-sm btn-danger delete-btn' data-pickup-id='" . htmlspecialchars($row['id']) . "'><i class='fas fa-trash-alt'></i></button></td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr id='noHistoryRow'><td colspan='6' class='no-history-message'>Không tìm thấy lịch sử.</td></tr>";
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


    <script src="js/index.js"></script>
</body>

</html>

<?php
pg_close($conn);
?>
