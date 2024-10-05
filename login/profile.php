<?php
session_start();

$servername = "localhost";
$username = "postgres";
$password = "!xNq!TRWY.AuD9U";
$dbname = "studentpickup";

// Connect to PostgreSQL database
$conn = pg_connect("host=$servername dbname=$dbname user=$username password=$password");

if (!$conn) {
    die("Connection failed: " . pg_last_error());
}
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.html");
    exit();
}
// Check if the user is logged in
$isLoggedIn = isset($_SESSION['userid']);

if ($isLoggedIn) {
    $userid = $_SESSION['userid'];

    // Fetch user information from the database
    $query = "SELECT * FROM public.user WHERE id = $1";
    $result = pg_query_params($conn, $query, array($userid));

    if ($result === false) {
        echo "Error in query: " . pg_last_error($conn);
    } else {
        $user = pg_fetch_assoc($result);
        if (!$user) {
            $isLoggedIn = false; // If no user is found, set logged-in status to false
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile</title>
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
                <?php if ($user): ?>
                    <li class="nav-item dropdown">
                        <a href="#" class="nav-link dropdown-toggle" data-toggle="dropdown" aria-expanded="false" style="color: white;">
                            <i class="fas fa-user-circle" style="font-size: 20px; margin-right: 10px; vertical-align: middle; color: white;"></i>
                            <span class="username" style="font-size: 20px; font-weight: bold; margin-right: 5px; vertical-align: middle; color: white;">
                                <?php echo htmlspecialchars($user['name']); ?>
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
            <li><a href="profile.php"><i class="fas fa-user"></i> <span class="nav-item">Hồ Sơ</span></a></li>
            <li><a href="studentlist.php"><i class="fas fa-child"></i> <span class="nav-item">Danh Sách Học Sinh</span></a></li>
        </ul>
    </div>

    <div class="profile-container">
        <?php if ($isLoggedIn): ?>
            <h2>Thông Tin Người Dùng</h2>
            <div class="profile-info">
                <div class="info-item">
                    <label>ID:</label>
                    <span><?php echo htmlspecialchars($user['id']); ?></span>
                </div>
                <div class="info-item">
                    <label>Username:</label>
                    <span><?php echo htmlspecialchars($user['username']); ?></span>
                </div>
                <div class="info-item">
                    <label>Name:</label>
                    <span><?php echo !empty($user['name']) ? htmlspecialchars($user['name']) : 'None'; ?></span>
                </div>
                <div class="info-item">
                    <label>Email:</label>
                    <span><?php echo !empty($user['email']) ? htmlspecialchars($user['email']) : 'None'; ?></span>
                </div>
                <div class="info-item">
                    <label>Phone Number:</label>
                    <span><?php echo !empty($user['phone']) ? htmlspecialchars($user['phone']) : 'None'; ?></span>
                </div>
                <div class="info-item">
                    <label>Gender:</label>
                    <span><?php echo !empty($user['gender']) ? htmlspecialchars($user['gender']) : 'None'; ?></span>
                </div>
                <div class="info-item">
                    <label>Date of Birth:</label>
                    <span><?php echo !empty($user['date_of_birth']) ? htmlspecialchars($user['date_of_birth']) : 'None'; ?></span>
                </div>
            </div>
        <?php else: ?>
            <div class="login-message">
                <p>Bạn cần đăng nhập hoặc đăng ký để có thông tin tài khoản.</p>
            </div>

            <!-- Login and Register buttons at the bottom -->
            <div class="auth-buttons">
                <a href="login.html" class="btn">Đăng Nhập</a>
                <a href="register.html" class="btn">Đăng Ký</a>
            </div>
        <?php endif; ?>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const btn = document.querySelector("#btn");
            const sidebar = document.querySelector(".sidebar");
            const dropdownToggle = document.querySelector('.nav-link.dropdown-toggle');
            const dropdownMenu = document.querySelector('.dropdown-menu');

            // Sidebar toggle
            if (btn) {
                btn.addEventListener('click', function() {
                    sidebar.classList.toggle("active");
                });
            }

            // Dropdown toggle
            if (dropdownToggle) {
                dropdownToggle.addEventListener('click', function (event) {
                    event.preventDefault();
                    event.stopPropagation(); // Add this line to prevent event bubbling
                    dropdownMenu.classList.toggle('show');
                });
            }


            // Close dropdown when clicking outside
            window.addEventListener('click', function(e) {
                if (!dropdownToggle.contains(e.target) && !dropdownMenu.contains(e.target)) {
                    dropdownMenu.classList.remove('show');
                }
            });
        });
    </script>
</body>
</html>

<?php
// Close the database connection
pg_close($conn);
?>
