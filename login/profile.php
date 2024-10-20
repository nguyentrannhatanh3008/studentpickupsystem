<?php
session_start();

// Cấu hình kết nối cơ sở dữ liệu
$servername = "localhost";
$username_db = "postgres";
$password_db = "!xNq!TRWY.AuD9U";
$dbname = "studentpickup";

// Kết nối đến cơ sở dữ liệu PostgreSQL
$conn = pg_connect("host=$servername dbname=$dbname user=$username_db password=$password_db");

// Kiểm tra kết nối
if (!$conn) {
    die("Connection failed: " . pg_last_error());
}

// Kiểm tra xem người dùng đã đăng nhập hay chưa
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['userid'])) {
    header("Location: login.php");
    exit();
}

// Lấy userid từ phiên
$userid = $_SESSION['userid'];

// Lấy thông tin người dùng từ cơ sở dữ liệu
$query = "SELECT * FROM public.user WHERE id = $1";
$result = pg_query_params($conn, $query, array($userid));

if ($result === false) {
    // Nếu xảy ra lỗi trong truy vấn, ghi log và chuyển hướng
    error_log("Lỗi truy vấn: " . pg_last_error($conn));
    header("Location: login.php");
    exit();
}

$user = pg_fetch_assoc($result);

if (!$user) {
    // Nếu không tìm thấy người dùng, chuyển hướng đến trang đăng nhập
    header("Location: login.php");
    exit();
}

// Map role codes to display names
$role_map = [
    'QuanLyNhaTruong' => 'Quản Lý Nhà Trường',
    'PhuHuynh' => 'Phụ Huynh',
    'GiaoVien' => 'Giáo Viên'
];

// Lấy tên vai trò hiển thị dựa trên mã vai trò từ cơ sở dữ liệu
$role_display = isset($role_map[$user['role']]) ? $role_map[$user['role']] : 'Không xác định';

// Fetch unread notifications count
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

// Fetch the user's note
$note = "";
$noteQuery = "SELECT note FROM user_notes WHERE user_id = $1 LIMIT 1";
$noteResult = pg_query_params($conn, $noteQuery, array($userid));

if ($noteResult) {
    $noteRow = pg_fetch_assoc($noteResult);
    $note = $noteRow ? htmlspecialchars($noteRow['note']) : "";
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newNote = trim($_POST['note']);

    // Update or insert the note
    $existingNoteQuery = "SELECT * FROM user_notes WHERE user_id = $1";
    $existingNoteResult = pg_query_params($conn, $existingNoteQuery, array($userid));

    if ($existingNoteResult && pg_num_rows($existingNoteResult) > 0) {
        // Update existing note
        $updateNoteQuery = "UPDATE user_notes SET note = $1, updated_at = CURRENT_TIMESTAMP WHERE user_id = $2";
        pg_query_params($conn, $updateNoteQuery, array($newNote, $userid));
    } else {
        // Insert new note
        $insertNoteQuery = "INSERT INTO user_notes (user_id, note) VALUES ($1, $2)";
        pg_query_params($conn, $insertNoteQuery, array($userid, $newNote));
    }

    // Reload the page to reflect the changes
    header("Location: profile.php");
    exit();
}

// Đóng kết nối sau khi xử lý
pg_close($conn);
?>

<!DOCTYPE html>
<html lang="vi"> <!-- Đổi ngôn ngữ sang tiếng Việt -->
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hồ Sơ Cá Nhân</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <link rel="stylesheet" href="css/profile.css">
</head>
<body>
<header class="header">
    <h1></h1>
    <nav class="navbar">
        <ul class="nav">
            <li class="nav-item dropdown">
                <a href="#" class="nav-link dropdown-toggle" data-toggle="dropdown" aria-expanded="false" style="color: white;">
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
        <li><a href="profile.php"><i class="fas fa-user"></i> <span class="nav-item">Hồ Sơ</span></a></li>
        <li><a href="studentlist.php"><i class="fas fa-child"></i> <span class="nav-item">Danh Sách Học Sinh</span></a></li>
    </ul>
</div>

<div class="profile-container">
    <div class="profile-header">
        <h2>Thông Tin Cá Nhân</h2>
    </div>
    <form method="POST">
        <div class="profile-info">
            <table class="table table-borderless">
                <tr>
                    <th>Mã tài khoản:</th>
                    <td><?php echo htmlspecialchars($user['id']); ?></td>
                    <th>Tên đăng nhập:</th>
                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                </tr>
                <tr>
                    <th>Họ và tên:</th>
                    <td><?php echo htmlspecialchars($user['name']); ?></td>
                    <th>Giới tính:</th>
                    <td><?php echo htmlspecialchars($user['gender']); ?></td>
                </tr>
                <tr>
                    <th>Điện thoại:</th>
                    <td><?php echo htmlspecialchars($user['phone']); ?></td>
                    <th>Email:</th>
                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                </tr>
                <tr>
                    <th>Vai Trò:</th>
                    <td><?php echo htmlspecialchars($role_display); ?></td>
                    <th></th>
                    <td></td>
                </tr>
                <tr>
                    <th>Ghi chú:</th>
                    <td colspan="3">
                        <textarea name="note" class="form-control" rows="3"><?php echo $note; ?></textarea>
                    </td>
                </tr>
            </table>
        </div>
        <button type="submit" class="btn btn-primary update-btn">Cập nhật</button>
    </form>
</div>

<script src="js/profile.js"></script>
</body>
</html>
