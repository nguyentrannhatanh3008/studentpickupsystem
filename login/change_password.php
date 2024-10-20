<?php
session_start();

// Kết nối cơ sở dữ liệu
$servername = "localhost";
$name = "postgres";
$password = "!xNq!TRWY.AuD9U";
$dbname = "studentpickup";

$conn = pg_connect("host=$servername dbname=$dbname user=$name password=$password");

if (!$conn) {
    die("Connection failed: " . pg_last_error());
}

// Check if the user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit();
}

$isLoggedIn = isset($_SESSION['userid']);

if ($isLoggedIn) {
    $userid = $_SESSION['userid'];

    // Fetch user information from the database
    $query = "SELECT * FROM public.user WHERE id = $1";
    $result = pg_query_params($conn, $query, array($userid));

    if ($result === false) {
        die("Error in query: " . pg_last_error($conn));
    } else {
        $user = pg_fetch_assoc($result);
        if (!$user) {
            $isLoggedIn = false; // If no user is found, set logged-in status to false
            header("Location: login.php");
            exit();
        }
    }

    // Fetch unread notifications count
    $unreadCount = 0;
    $countQuery = "SELECT COUNT(*) AS unread FROM public.notifications WHERE user_id = $1 AND status = 'Chưa đọc'";
    $countResult = pg_query_params($conn, $countQuery, array($userid));

    if ($countResult) {
        $countRow = pg_fetch_assoc($countResult);
        $unreadCount = intval($countRow['unread']);
    } else {
        // Handle query error if needed
        $unreadCount = 0;
    }
}

// Handle password change request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // Basic server-side validation
    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $error = "Vui lòng điền đầy đủ các trường!";
    } elseif ($newPassword !== $confirmPassword) {
        $error = "Mật khẩu xác nhận không khớp!";
    } elseif (strlen($newPassword) < 8) {
        $error = "Mật khẩu mới phải có ít nhất 8 ký tự!";
    } elseif (!ctype_alnum($newPassword)) {
        $error = "Mật khẩu mới chỉ được chứa chữ cái và số, không chứa ký tự đặc biệt!";
    } else {
        // Fetch current hashed password from the database
        $query = "SELECT hashed_password FROM public.user WHERE id = $1";
        $result = pg_query_params($conn, $query, array($userid));

        if ($result) {
            $userData = pg_fetch_assoc($result);

            // Verify current password
            if (password_verify($currentPassword, $userData['hashed_password'])) {
                // Hash the new password
                $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);

                // Update the password in the database
                $updateQuery = "UPDATE public.user SET hashed_password = $1 WHERE id = $2";
                $updateResult = pg_query_params($conn, $updateQuery, array($newPasswordHash, $userid));

                if ($updateResult) {
                    $success = "Mật khẩu đã được thay đổi thành công!";
                } else {
                    $error = "Có lỗi xảy ra khi thay đổi mật khẩu. Vui lòng thử lại!";
                }
            } else {
                $error = "Mật khẩu hiện tại không đúng!";
            }
        } else {
            $error = "Có lỗi xảy ra khi truy vấn cơ sở dữ liệu!";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đổi Mật Khẩu</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <!-- Bootstrap CSS -->
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/style-change-password.css">
    <style>
        body, html {
            margin: 0;
            padding: 0;
            font-family: 'Open Sans', sans-serif;
            background-color: #f4f4f4;
            color: #333;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 20px; /* Adjust padding as needed */
            background-color: #db4b23;
            color: white;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        /* Container for navigation and auth buttons */
        .nav-auth {
            display: flex;
            align-items: center;
        }

        /* Push auth buttons to the right */
        .auth-buttons {
            margin-left: auto; /* This will push the auth buttons to the right */
            display: flex;
        }

        .auth-buttons a {
            padding: 8px 16px; /* Adjust padding for better spacing */
            margin-left: 10px; /* Space between buttons */
            background-color: #e65c00;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: background-color 0.3s;
        }

        .auth-buttons a:hover {
            background-color: #cc5200;
        }

        .sidebar {
            width: 70px; /* Collapsed width */
            height: 100vh;
            background-color: #db4b23;
            color: white;
            position: fixed;
            left: 0;
            top: 0;
            transition: width 0.5s; /* Smooth transition for expanding/collapsing */
            overflow-x: hidden; /* Hide overflow text */
            z-index: 1000; /* Ensure it stays on top */
            display: flex; /* Use Flexbox to align content */
            flex-direction: column; /* Align children vertically */
            align-items: center; /* Center items horizontally */
            justify-content: center; /* Center items vertically */
        }

        /* Expanded sidebar styles */
        .sidebar.active {
            width: 250px; /* Expanded width */
        }

        /* Sidebar navigation list */
        .sidebar ul {
            list-style: none;
            padding: 0;
            margin: 0;
            width: 100%; /* Full width for the list */
        }

        /* Sidebar navigation items */
        .sidebar ul li {
            width: 100%; /* Ensure each list item takes full width */
            padding: 15px;
            text-align: left;
            transition: background-color 0.3s;
            display: flex; /* Use flexbox for aligning items */
            align-items: center; /* Center the icon and text vertically */
            justify-content: flex-start; /* Align items to the left when expanded */
        }

        .sidebar ul li:hover {
            background-color: #e65c00;
        }

        .sidebar ul li a {
            text-decoration: none;
            color: white;
            display: flex;
            align-items: center;
        }

        /* Icon styles in sidebar */
        .sidebar ul li a i {
            margin-right: 20px;
            font-size: 18px;
        }

        /* Text styles in sidebar */
        .sidebar ul li a .nav-item {
            opacity: 0; /* Hidden by default */
            transition: opacity 0.5s; /* Smooth transition for text */
        }

        /* Show text when sidebar is expanded */
        .sidebar.active ul li a .nav-item {
            opacity: 1;
        }

        /* Adjust main container based on sidebar state */
        .main-container {
            margin-left: 70px; /* Margin when sidebar is collapsed */
            transition: margin-left 0.5s;
        }

        .sidebar.active ~ .main-container {
            margin-left: 250px; /* Adjust margin when sidebar is expanded */
        }

        /* Top bar styles */
        .top {
            position: absolute;
            top: 15px;
            left: 10px;
            cursor: pointer;
            color: white;
        }

        /* Container styles */
        .container {
            max-width: 500px; /* Increased width for better appearance */
            width: 100%;
            background-color: white;
            padding: 30px 40px; /* Increased padding */
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2); /* Enhanced box-shadow */
            border-radius: 10px; /* Increased border-radius */
            margin: 80px auto; /* Center the container and add top margin */
        }

        .container h2 {
            text-align: center;
            color: #db4b23;
            margin-bottom: 30px; /* Increased margin for better spacing */
        }

        .form-group {
            margin-bottom: 20px;
            position: relative; /* For positioning the eye icon */
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: bold;
        }

        .input-container {
            position: relative;
        }

        .input-container input[type="password"] {
            width: 100%;
            padding: 10px 40px 10px 15px; /* Add padding-right for eye icon */
            border: 1px solid #db4b23;
            border-radius: 5px;
            transition: border-color 0.3s;
        }

        .input-container input[type="password"]:focus {
            border-color: #e65c00;
            outline: none;
        }

        .input-container .toggle-password {
            position: absolute;
            top: 50%;
            right: 15px;
            transform: translateY(-50%);
            cursor: pointer;
            color: #db4b23;
            transition: color 0.3s;
        }

        .input-container .toggle-password:hover {
            color: #e65c00;
        }

        .btn {
            width: 100%;
            padding: 12px;
            background-color: #db4b23;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s;
        }

        .btn:hover {
            background-color: #e65c00;
        }

        .message {
            text-align: center;
            margin-top: 20px;
            font-size: 16px;
        }

        .message.error {
            color: red;
        }

        .message.success {
            color: green;
        }
        .dropdown-menu {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            z-index: 1000;
            min-width: 160px;
            padding: 10px 0;
            margin: 2px 0 0;
            font-size: 14px;
            text-align: left;
            list-style: none;
            background-color: #db4b23;
            color: #ffffff;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.175);
        }

        /* Show the dropdown menu */
        .dropdown-menu.show {
            display: block;
        }

        /* Dropdown button styles */
        .dropdown-menu a {
            display: flex;
            align-items: center;
            justify-content: start;
            padding: 8px 20px;
            margin: 4px 0;
            background-color: #db4b23;
            color: white;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        /* Dropdown button hover effect */
        .dropdown-menu a:hover {
            background-color: #e65c00;
        }

        /* Icon styling within dropdown items */
        .dropdown-menu a i {
            margin-right: 10px;
        }
    </style>
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

    <div class="container">
        <div class="form-container">
            <h2>Đổi Mật Khẩu</h2>
            <?php if (isset($error)): ?>
                <div class="message error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if (isset($success)): ?>
                <div class="message success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            <form method="post" action="change_password.php">
                <div class="form-group">
                    <label for="current_password">Mật khẩu hiện tại:</label>
                    <div class="input-container">
                        <input type="password" name="current_password" id="current_password" class="form-control" required>
                        <i class="fas fa-eye toggle-password" toggle="#current_password"></i>
                    </div>
                </div>
                <div class="form-group">
                    <label for="new_password">Mật khẩu mới:</label>
                    <div class="input-container">
                        <input type="password" name="new_password" id="new_password" class="form-control" required>
                        <i class="fas fa-eye toggle-password" toggle="#new_password"></i>
                    </div>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Xác nhận mật khẩu mới:</label>
                    <div class="input-container">
                        <input type="password" name="confirm_password" id="confirm_password" class="form-control" required>
                        <i class="fas fa-eye toggle-password" toggle="#confirm_password"></i>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Đổi Mật Khẩu</button>
            </form>
        </div>
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
                dropdownToggle.addEventListener('click', function(event) {
                    event.preventDefault();
                    event.stopPropagation(); // Prevent event bubbling
                    dropdownMenu.classList.toggle('show');
                });
            }

            // Close dropdown when clicking outside
            window.addEventListener('click', function(e) {
                if (!dropdownToggle.contains(e.target) && !dropdownMenu.contains(e.target)) {
                    dropdownMenu.classList.remove('show');
                }
            });

            // Password toggle functionality
            const togglePasswordIcons = document.querySelectorAll('.toggle-password');

            togglePasswordIcons.forEach(function(icon) {
                icon.addEventListener('click', function() {
                    const input = document.querySelector(this.getAttribute('toggle'));
                    if (input.type === "password") {
                        input.type = "text";
                        this.classList.remove('fa-eye');
                        this.classList.add('fa-eye-slash');
                    } else {
                        input.type = "password";
                        this.classList.remove('fa-eye-slash');
                        this.classList.add('fa-eye');
                    }
                });
            });
        });
    </script>
</body>

</html>
<?php
// Close the database connection
pg_close($conn);
?>
