<?php
session_start();
$servername = "localhost";
$username = "postgres";
$password = "!xNq!TRWY.AuD9U";
$dbname = "studentpickup";

// Connect to PostgreSQL database
$conn = pg_connect("host=$servername dbname=$dbname user=$username password=$password");

// Check connection
if (!$conn) {
    die("Connection failed: " . pg_last_error());
}

// Check if the user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.html");
    exit();
}

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

$students = [];
if ($isLoggedIn && $user) {
    $query = "SELECT * FROM public.student WHERE FPN = $1 OR MPN = $1";
    $result = pg_query_params($conn, $query, array($user['phone']));

    if ($result !== false) {
        while ($row = pg_fetch_assoc($result)) {
            $students[] = $row;
        }
    } else {
        echo "Error fetching students: " . pg_last_error($conn);
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Danh Sách Con</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="css/style-studentlist.css">
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
    <div class="main-container">
        <h2>Danh Sách Học Sinh</h2>
        <table id="studentListTable" class="display dataTable">
            <thead>
                <tr>
                    <th>STT</th>
                    <th>Học sinh</th>
                    <th>Lớp</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($students)): ?>
                    <?php $index = 1; ?>
                    <?php foreach ($students as $student): ?>
                        <tr class="student-row" data-student-id="<?php echo htmlspecialchars($student['id']); ?>">
                            <td><?php echo $index++; ?></td>
                            <td><?php echo htmlspecialchars($student['name']); ?></td>
                            <td><?php echo htmlspecialchars($student['class']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="3" class="no-students">
                            Bạn không có học sinh nào được liên kết với tài khoản của bạn.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Student Details Modal -->
    <div class="modal fade" id="studentDetailsModal" tabindex="-1" aria-labelledby="studentDetailsModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
              <h5 class="modal-title" id="studentDetailsModalLabel">Thông tin Học sinh</h5>
              <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                  <span aria-hidden="true">&times;</span>
              </button>
          </div>
          <div class="modal-body">
            <p><strong>Tên:</strong> <span id="studentName"></span></p>
            <p><strong>Lớp:</strong> <span id="studentClass"></span></p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-dong" data-dismiss="modal">Đóng</button>
          </div>
        </div>
      </div>
    </div>

    <!-- jQuery (Full Version) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- Popper.js -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"></script>

    <!-- Bootstrap JS -->
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

    <!-- Custom JS -->
    <script src="js/studentlist.js"></script>
</body>

</html>
<?php
// Close the database connection
pg_close($conn);
?>
