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

// Lấy thông tin người dùng từ phiên
$userid = $_SESSION['userid'];

// Xử lý yêu cầu thay đổi mật khẩu
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPassword = $_POST['current_password'];
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];

    // Kiểm tra xem mật khẩu mới và xác nhận mật khẩu có khớp không
    if ($newPassword !== $confirmPassword) {
        $error = "Mật khẩu xác nhận không khớp!";
    } else {
        // Truy vấn để lấy mật khẩu hiện tại từ cơ sở dữ liệu
        $query = "SELECT hashed_password FROM public.user WHERE id = $1";
        $result = pg_query_params($conn, $query, array($userid));

        if ($result) {
            $user = pg_fetch_assoc($result);

            // Kiểm tra mật khẩu hiện tại có đúng không
            if (password_verify($currentPassword, $user['password_hash'])) {
                // Cập nhật mật khẩu mới vào cơ sở dữ liệu
                $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
                $updateQuery = "UPDATE public.user SET password_hash = $1 WHERE id = $2";
                $updateResult = pg_query_params($conn, $updateQuery, array($newPasswordHash, $userid));

                if ($updateResult) {
                    $success = "Mật khẩu đã được thay đổi thành công!";
                } else {
                    $error = "Có lỗi xảy ra khi thay đổi mật khẩu!";
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
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đổi Mật Khẩu</title>
    <link rel="stylesheet" href="css/style-change-password.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <style>
        body, html {
        margin: 0;
        padding: 0;
        font-family: 'Open Sans', sans-serif;
        background-color: #f4f4f4;
        color: #333;
    }

        /* Header styles, incorporating flexbox for layout */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 20px; /* Adjust padding as needed */
            background-color: #db4b23;
            color: white;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
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

        .container {
            max-width: 400px;
            width: 100%;
            background-color: white;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            border-radius: 8px;
        }

        .container h2 {
            text-align: center;
            color: #db4b23;
        }

        .input-group {
            margin-bottom: 15px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #555;
        }

        input[type="password"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #db4b23;
            border-radius: 5px;
        }

        .btn {
            width: 100%;
            padding: 10px;
            background-color: #db4b23;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            margin-top: 10px;
        }

        .btn:hover {
            background-color: #e06600;
        }

        .message {
            text-align: center;
            margin-top: 20px;
            color: #db4b23;
        }
        .dropdown-menu {
        display: none;
        position: absolute;
        top: 100%;
        left: 0;
        z-index: 1000;
        min-width: 160px;
        padding: 10px 0; /* Add padding around the menu */
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
        justify-content: start; /* Align text to the left */
        padding: 8px 20px; /* Add padding inside each item for spacing */
        margin: 4px 0; /* Add margin between buttons for spacing */
        background-color: #db4b23; /* Button background color */
        color: white;
        border-radius: 4px; /* Slightly rounded corners */
        cursor: pointer;
        transition: background-color 0.3s ease;
    }

    /* Dropdown button hover effect */
    .dropdown-menu a:hover {
        background-color: #e65c00; /* Change color on hover */
    }

    /* Icon styling within dropdown items */
    .dropdown-menu a i {
        margin-right: 10px; /* Space between icon and text */
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
    <div class="container">
        <h2>Đổi Mật Khẩu</h2>
        <?php if (isset($error)): ?>
            <div class="message" style="color: red;"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if (isset($success)): ?>
            <div class="message" style="color: green;"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <form method="post" action="change_password.php">
            <div class="input-group">
                <label for="current_password">Mật khẩu hiện tại:</label>
                <input type="password" name="current_password" id="current_password" required>
            </div>
            <div class="input-group">
                <label for="new_password">Mật khẩu mới:</label>
                <input type="password" name="new_password" id="new_password" required>
            </div>
            <div class="input-group">
                <label for="confirm_password">Xác nhận mật khẩu mới:</label>
                <input type="password" name="confirm_password" id="confirm_password" required>
            </div>
            <button type="submit" class="btn">Đổi Mật Khẩu</button>
        </form>
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