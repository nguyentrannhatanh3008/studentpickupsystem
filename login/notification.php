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
    // Xử lý yêu cầu AJAX nếu kết nối thất bại
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json');
        ob_clean();
        echo json_encode(['status' => 'error', 'message' => 'Kết nối thất bại: ' . pg_last_error()]);
        exit();
    } else {
        die("Kết nối thất bại: " . pg_last_error());
    }
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

// Xử lý đánh dấu tất cả thông báo đã đọc
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mark_all_read') {
    // Câu lệnh SQL để cập nhật tất cả thông báo chưa đọc thành đã đọc
    $markAllReadQuery = "UPDATE public.notifications SET status = 'Đã Đọc' WHERE user_id = $1 AND status = 'Chưa đọc'";
    $markAllReadResult = pg_query_params($conn, $markAllReadQuery, array($userid));
    
    header('Content-Type: application/json');
    if ($markAllReadResult) {
        $affectedRows = pg_affected_rows($markAllReadResult);
        echo json_encode(['status' => 'success', 'updated' => $affectedRows]);
    } else {
        echo json_encode(['status' => 'error', 'message' => pg_last_error($conn)]);
    }
    pg_close($conn);
    exit(); // Kết thúc thực thi script sau khi xử lý AJAX
}

// Xử lý đánh dấu một thông báo đã đọc
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['notification_id'])) {
    $notificationId = intval($_POST['notification_id']);

    // Cập nhật trạng thái thông báo thành 'Đã Đọc'
    $updateQuery = "UPDATE public.notifications SET status = 'Đã Đọc' WHERE id = $1 AND user_id = $2";
    $updateResult = pg_query_params($conn, $updateQuery, array($notificationId, $userid));

    header('Content-Type: application/json');
    if ($updateResult) {
        if (pg_affected_rows($updateResult) > 0) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Không tìm thấy thông báo hoặc bạn không có quyền cập nhật.']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => pg_last_error($conn)]);
    }
    pg_close($conn);
    exit(); // Kết thúc thực thi script sau khi xử lý AJAX
}

// Xử lý xóa nhiều thông báo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_ids']) && is_array($_POST['delete_ids'])) {
    $deleteIds = array_map('intval', $_POST['delete_ids']); // Đảm bảo tất cả ID là số nguyên

    if (count($deleteIds) > 0) {
        // Tạo chuỗi tham số cho câu lệnh SQL
        $placeholders = [];
        $params = [];
        $i = 1;
        foreach ($deleteIds as $id) {
            $placeholders[] = '$' . $i;
            $params[] = $id;
            $i++;
        }

        $placeholders_str = implode(', ', $placeholders);

        // Câu lệnh SQL để xóa nhiều thông báo
        $deleteQuery = "DELETE FROM public.notifications WHERE id IN ($placeholders_str) AND user_id = $".($i).";";
        $params[] = $userid; // Thêm user_id vào tham số cuối cùng

        $deleteResult = pg_query_params($conn, $deleteQuery, $params);

        header('Content-Type: application/json');
        if ($deleteResult) {
            $affectedRows = pg_affected_rows($deleteResult);
            if ($affectedRows > 0) {
                echo json_encode(['status' => 'success', 'deleted' => $affectedRows]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Không tìm thấy thông báo hoặc bạn không có quyền xóa.']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => pg_last_error($conn)]);
        }
        pg_close($conn);
        exit(); // Kết thúc thực thi script sau khi xử lý AJAX
    }

    header('Content-Type: application/json');
    ob_clean();
    echo json_encode(['status' => 'error', 'message' => 'Yêu cầu POST không hợp lệ.']);
    exit();
}


// Tiếp tục với việc hiển thị trang

$notificationQuery = "SELECT id, title, message, status, date_trunc('second', created_at) AS created_at
                      FROM public.notifications
                      WHERE user_id = $1
                      ORDER BY created_at DESC";
$notificationResult = pg_query_params($conn, $notificationQuery, array($userid));
$notificationCount = ($notificationResult) ? pg_num_rows($notificationResult) : 0;
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thông Báo</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="css/notifications.css?v=1.0"> <!-- Thêm tham số phiên bản để tránh cache -->
</head>

<body>
    <header class="header">
        <h1></h1>
        <nav class="navbar">
            <ul class="nav">
                <?php if ($user): ?>
                    <li class="nav-item dropdown">
                        <a href="#" class="nav-link dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" style="color: white;">
                            <i class="fas fa-user-circle" style="font-size: 20px; margin-right: 10px; vertical-align: middle; color: white;"></i>
                            <span class="username" style="font-size: 20px; font-weight: bold; margin-right: 5px; vertical-align: middle; color: white;">
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
            <i class="fas fa-bars" id="btn"></i>
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
            <li><a href="profile.php"><i class="fas fa-user"></i> <span class="nav-item">Hồ Sơ</span></a></li>
            <li><a href="studentlist.php"><i class="fas fa-child"></i> <span class="nav-item">Danh Sách Học Sinh</span></a></li>
        </ul>
    </div>

    <div class="container mt-4">
        <div class="notification-header mb-3">
            <h3>Danh sách Thông báo</h3>
            <p>Số lượng thông báo: <span id="notificationCount"><?php echo $notificationCount; ?></span></p>
            <button id="bulkDeleteButton" class="btn btn-danger btn-sm" style="display: none;">
                <i class="fa fa-trash-alt"></i> Xóa
            </button>
        </div>
        <table id="notificationsTable" class="table table-striped table-bordered">
            <thead>
                <tr>
                    <th style="width: 60px;">
                        <div class="header-controls">
                            <input type="checkbox" id="selectAll">
                            <i class="fas fa-trash-alt ml-2" id="headerTrash" title="Xóa các thông báo đã chọn"></i>
                        </div>
                    </th>
                    <th style="width: 40px;"> 
                        <i class="fa fa-check-circle icon-white" id="markAllReadIcon" title="Đánh Dấu Tất Cả Đã Đọc"></i> 
                    </th>
                    <th>Tiêu đề</th>
                    <th>Thông báo</th>
                    <th>Trạng thái</th>
                    <th>Thời gian gửi</th>
                    <th>Hành động</th> 
                </tr>
            </thead>
            <tbody>
                <?php if ($notificationResult && $notificationCount > 0): ?>
                    <?php while ($notification = pg_fetch_assoc($notificationResult)): ?>
                        <?php
                            $createdAt = new DateTime($notification['created_at']);
                            $formattedDate = $createdAt->format('Y-m-d H:i:s');
                            $rowClass = ($notification['status'] === 'Chưa đọc') ? 'notification-unread' : 'notification-read';
                            $iconClass = ($notification['status'] === 'Chưa đọc') ? 'icon-gray' : 'icon-orange';
                        ?>
                        <tr class="notification-row <?php echo $rowClass; ?>" data-notification-id="<?php echo htmlspecialchars($notification['id']); ?>">
                            <td>
                                <input type="checkbox" class="select-checkbox" value="<?php echo htmlspecialchars($notification['id']); ?>">
                            </td>
                            <td>
                                <i class="fa fa-check-circle <?php echo $iconClass; ?>" title="<?php echo htmlspecialchars($notification['status']); ?>"></i>
                            </td>
                            <td><?php echo htmlspecialchars($notification['title']); ?></td>
                            <td><?php echo htmlspecialchars($notification['message']); ?></td>
                            <td><?php echo htmlspecialchars($notification['status']); ?></td>
                            <td><?php echo htmlspecialchars($formattedDate); ?></td>
                            <td>
                                <button class="btn btn-danger btn-sm delete-notification" data-id="<?php echo htmlspecialchars($notification['id']); ?>">
                                    <i class="fa fa-trash"></i> Xóa
                                </button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Modal Xác Nhận Xóa Một Thông Báo -->
    <div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-labelledby="confirmDeleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 id="confirmDeleteModalLabel">Xác nhận xóa</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Đóng">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    Bạn có chắc chắn muốn xóa thông báo này?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Hủy</button>
                    <button type="button" class="btn btn-danger" id="deleteConfirmBtn">Xóa</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Xác Nhận Xóa Nhiều Thông Báo -->
    <div class="modal fade" id="confirmBulkDeleteModal" tabindex="-1" aria-labelledby="confirmBulkDeleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 id="confirmBulkDeleteModalLabel">Xác nhận xóa các thông báo đã chọn</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Đóng">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    Bạn có chắc chắn muốn xóa các thông báo đã chọn?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Hủy</button>
                    <button type="button" class="btn btn-danger" id="bulkDeleteConfirmBtn">Xóa</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Xác Nhận Đánh Dấu Tất Cả Đã Đọc -->
    <div class="modal fade" id="confirmMarkAllReadModal" tabindex="-1" aria-labelledby="confirmMarkAllReadModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Xác nhận đánh dấu tất cả đã đọc</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Đóng">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    Bạn có chắc chắn muốn đánh dấu tất cả thông báo đã đọc?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" id="markAllReadConfirmBtn">OK</button>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Hủy</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Chi Tiết Thông Báo (Nếu Cần) -->
    <div class="modal fade" id="notificationDetailsModal" tabindex="-1" aria-labelledby="notificationDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <!-- Tiêu đề Modal -->
                <div class="modal-header">
                    <h5 class="modal-title" id="modalNotificationTitle">Chi tiết Thông báo</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Đóng">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <!-- Nội dung Modal -->
                <div class="modal-body">
                    <p><strong>Tiêu đề:</strong> <span id="modalNotificationTitle"></span></p>
                    <p><strong>Thông báo:</strong> <span id="modalNotificationMessage"></span></p>
                    <p><strong>Thời gian:</strong> <span id="modalNotificationTime"></span></p>
                    <p><strong>Trạng thái:</strong> <span id="modalNotificationStatus"></span></p>
                </div>
                <div class="modal-footer">
                    <!-- Sử dụng các lớp nút tùy chỉnh -->
                    <button type="button" class="btn-da-doc btn btn-primary" id="markAsReadButton">Đã Đọc</button>
                    <button type="button" class="btn-dong btn btn-secondary" data-dismiss="modal">Đóng</button>
                </div>
            </div>
        </div>
    </div>


    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap4.min.js"></script>
    <script src="js/notifications.js"></script> <!-- Đảm bảo đường dẫn đúng -->
</body>

</html>
<?php
pg_close($conn);
?>
