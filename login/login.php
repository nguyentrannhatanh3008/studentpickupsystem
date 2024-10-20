<?php
session_start();

// Database configuration
$servername = "localhost";
$db_username = "postgres"; // PostgreSQL username
$db_password = "!xNq!TRWY.AuD9U"; // PostgreSQL password
$dbname = "studentpickup"; // Database name

// Connect to PostgreSQL database
$conn = pg_connect("host=$servername dbname=$dbname user=$db_username password=$db_password");

// Check connection
if (!$conn) {
    // Log the error and display a generic message to the user
    error_log("Connection failed: " . pg_last_error());
    die("An unexpected error occurred. Please try again later.");
}

// Initialize error message variable
$error_message = "";

// Handle "Remember Me" auto-login
if (!isset($_SESSION['loggedin']) && isset($_COOKIE['remember_token'])) {
    $token = $_COOKIE['remember_token'];
    $query = "SELECT * FROM public.user WHERE remember_token = $1";
    $result = pg_query_params($conn, $query, array($token));

    if ($result && pg_num_rows($result) > 0) {
        $user = pg_fetch_assoc($result);
        // Log the user in by setting session variables
        $_SESSION['loggedin'] = true;
        $_SESSION['username'] = $user['username'];
        $_SESSION['userid'] = $user['id'];
        $_SESSION['role'] = $user['role'];
    }
}

// Handle POST request from login form
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $remember_me = isset($_POST['remember_me']);

    // Check if input fields are empty
    if (empty($username) || empty($password)) {
        echo "<script>
                alert('Please fill in all fields.');
                window.location.href = './login.php';
              </script>";
        exit();
    }

    // Fetch user info from the database
    $query = "SELECT * FROM public.user WHERE username = $1";
    $result = pg_query_params($conn, $query, array($username));

    if ($result === false) {
        // Log the error and display a generic message to the user
        error_log("Query failed: " . pg_last_error($conn));
        echo "<script>
                alert('An unexpected error occurred. Please try again later.');
                window.location.href = './login.php';
              </script>";
        pg_close($conn);
        exit();
    } else {
        if (pg_num_rows($result) > 0) {
            $user = pg_fetch_assoc($result);

            // Verify hashed password
            if (password_verify($password, $user['hashed_password'])) { // Ensure 'hashed_password' field exists
                // Login successful, set session variables
                $_SESSION['loggedin'] = true;
                $_SESSION['username'] = $user['username'];
                $_SESSION['userid'] = $user['id'];

                // Handle "Remember Me" functionality
                if ($remember_me) {
                    // Generate a random token
                    $token = bin2hex(random_bytes(16));

                    // Store the token in the database
                    $update_query = "UPDATE public.user SET remember_token = $1 WHERE id = $2";
                    $update_result = pg_query_params($conn, $update_query, array($token, $user['id']));

                    if ($update_result === false) {
                        // Log the error
                        error_log("Error setting remember_token: " . pg_last_error($conn));
                    }

                    // Set the token in a secure, HttpOnly cookie with 30 days expiry
                    setcookie('remember_token', $token, [
                        'expires' => time() + (30 * 24 * 60 * 60),
                        'path' => '/',
                        'domain' => '', // Set to your domain
                        'secure' => isset($_SERVER["HTTPS"]), // Ensure HTTPS
                        'httponly' => true,
                        'samesite' => 'Lax' // Can be 'Strict' or 'None' based on your needs
                    ]);
                } else {
                    // If "Remember Me" not checked, clear any existing tokens and cookies
                    if (!empty($user['remember_token'])) {
                        $clear_query = "UPDATE public.user SET remember_token = NULL WHERE id = $1";
                        pg_query_params($conn, $clear_query, array($user['id']));
                    }

                    if (isset($_COOKIE['remember_token'])) {
                        setcookie('remember_token', '', time() - 3600, "/", "", isset($_SERVER["HTTPS"]), true);
                    }
                }

                // Display success message and redirect to index.php
                echo "
                <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css'>
                <style>
                    @import url('https://fonts.googleapis.com/css2?family=Open+Sans:wght@600&display=swap');
                    .success-message {
                        background-color: #4CAF50;
                        color: white;
                        padding: 15px 30px;
                        position: fixed;
                        top: 20px;
                        left: 50%;
                        transform: translateX(-50%);
                        z-index: 1000;
                        border-radius: 5px;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        font-size: 20px;
                        font-weight: bold;
                        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
                        font-family: 'Open Sans', sans-serif;
                    }
                    .success-message i {
                        margin-right: 10px;
                        font-size: 24px;
                    }
                </style>
                <div class='success-message' id='successMessage'>
                    <i class='fas fa-check-circle'></i> Đăng Nhập Thành Công.
                </div>
                <script>
                    setTimeout(function() {
                        window.location.href = './index.php';
                    }, 2000);
                </script>";
                pg_close($conn);
                exit();
            } else {
                // Wrong password
                $error_message = "Sai Mật Khẩu Vui Lòng Thử Lại!";
            }
        } else {
            // Username not found
            $error_message = "Tên Đăng Nhập Không Tồn Tại Vui Lòng Thử Lại!";
        }
    }
}

// Close connection before rendering the form
pg_close($conn);

// Retrieve remembered username if available (Optional)
$remembered_username = "";
if (isset($_COOKIE['remember_token'])) {
    // Reconnect to fetch username based on remember_token
    $conn = pg_connect("host=$servername dbname=$dbname user=$db_username password=$db_password");
    if ($conn) {
        $token = $_COOKIE['remember_token'];
        $query = "SELECT username FROM public.user WHERE remember_token = $1";
        $result = pg_query_params($conn, $query, array($token));

        if ($result && pg_num_rows($result) > 0) {
            $user = pg_fetch_assoc($result);
            $remembered_username = $user['username'];
        }

        pg_close($conn);
    }
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng Nhập</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">

    <!-- Favicon -->
    <link rel="icon" href="img/html-5.png">

    <!-- Custom Styles -->
    <style>
        /* [Your existing CSS styles, including the checkbox styles] */
        /* Global styles */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Open Sans', sans-serif;
        }

        body {
            background: #f6f5f7;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            overflow: hidden;
        }

        .container {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 14px 28px rgba(0, 0, 0, 0.25),
                0 10px 10px rgba(0, 0, 0, 0.22);
            display: flex;
            width: 800px;
            max-width: 100%;
            overflow: hidden;
        }

        .img {
            width: 50%;
            background: linear-gradient(to right, #db4b23, #ff7e6a);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px;
        }

        .img img {
            width: 100%;
            max-width: 300px;
            animation: float 6s ease-in-out infinite;
        }

        @keyframes float {
            0%,
            100% {
                transform: translateY(0);
            }

            50% {
                transform: translateY(-20px);
            }
        }

        .login-container {
            width: 50%;
            padding: 40px;
        }

        .login-container h2 {
            margin-bottom: 10px;
            color: #333;
        }

        .login-container p {
            margin-bottom: 20px;
            color: #555;
        }

        /* Input styles */
        .input-div {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            position: relative;
        }

        .input-div .i {
            margin-right: 10px;
            color: #db4b23;
            min-width: 30px;
            text-align: center;
            font-size: 20px;
        }

        .input-container {
            position: relative;
            width: 100%;
        }

        .input-container input {
            width: 100%;
            padding: 10px 35px 10px 0;
            border: none;
            border-bottom: 1px solid #db4b23;
            outline: none;
            background: transparent;
            font-size: 16px;
            color: #333;
        }

        .input-container h5 {
            position: absolute;
            left: 0;
            top: 10px;
            transition: all 0.3s ease;
            pointer-events: none;
            font-size: 16px;
            color: #999;
        }

        /* Thay đổi màu khi focus */
        .input-container input:focus~h5 {
            top: -20px;
            font-size: 12px;
            color: #db4b23;
        }

        .input-container input:not(:placeholder-shown)~h5 {
            top: -20px;
            font-size: 12px;
            color: #999;
        }

        .toggle-password {
            position: absolute;
            right: 0;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #db4b23;
            font-size: 18px;
        }

        /* Error Message */
        .duplicate-error {
            color: red;
            font-size: 12px;
            margin-top: 5px;
        }

        /* Button */
        .btn {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 5px;
            background: linear-gradient(to right, #db4b23, #ff7e6a);
            color: #fff;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.3s;
        }

        .btn:hover {
            background: linear-gradient(to right, #ff7e6a, #db4b23);
        }

        .forgot,
        .account a {
            color: #db4b23;
            text-decoration: none;
            transition: color 0.3s;
        }

        .forgot:hover,
        .account a:hover {
            color: #ff7e6a;
        }

        .others {
            display: flex;
            align-items: center;
            margin: 20px 0;
        }

        .others hr {
            flex: 1;
            border: none;
            height: 1px;
            background: #ccc;
        }

        .others p {
            margin: 0 10px;
            color: #555;
        }

        .error-message {
            background-color: #ffe6e6;
            border: 1px solid #ffcccc;
            padding: 10px;
            border-radius: 5px;
            color: #cc0000;
            margin-bottom: 15px;
        }

        /* Checkbox Styles */
        .checkbox-container {
            display: flex;
            align-items: center;
            cursor: pointer;
            position: relative;
            font-size: 16px;
            user-select: none;
        }

        /* Hide default checkbox */
        .checkbox-container input {
            position: absolute;
            opacity: 0;
            cursor: pointer;
            height: 0;
            width: 0;
        }

        /* Create custom checkbox */
        .checkmark {
            height: 20px;
            width: 20px;
            background-color: #fff;
            border: 2px solid #db4b23;
            border-radius: 4px;
            margin-right: 10px;
            transition: background-color 0.3s, border-color 0.3s;
            position: relative;
        }

        /* When checkbox is checked */
        .checkbox-container input:checked~.checkmark {
            background-color: #db4b23;
            border-color: #db4b23;
        }

        /* Create the checkmark/indicator */
        .checkmark:after {
            content: "";
            position: absolute;
            display: none;
        }

        /* Show the checkmark when checked */
        .checkbox-container input:checked~.checkmark:after {
            display: block;
        }

        /* Style the checkmark/indicator */
        .checkbox-container .checkmark:after {
            left: 6px;
            top: 2px;
            width: 5px;
            height: 10px;
            border: solid white;
            border-width: 0 2px 2px 0;
            transform: rotate(45deg);
        }

        /* Hover effects */
        .checkbox-container:hover input~.checkmark {
            border-color: #ff7e6a;
        }

        .checkbox-container input:focus~.checkmark {
            box-shadow: 0 0 3px #db4b23;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .container {
                flex-direction: column;
                width: 90%;
            }

            .img,
            .login-container {
                width: 100%;
            }

            .img {
                padding: 20px;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="img">
            <img src="img/authentication.svg" alt="Authentication">
        </div>
        <div class="login-container">
            <!-- Form action points to PHP login handler -->
            <form id="loginForm" action="login.php" method="POST">
                <h2>Đăng Nhập</h2>
                <p>Chào mừng trở lại!</p>

                <!-- Display error message if exists -->
                <?php if (!empty($error_message)) : ?>
                    <div class="error-message">
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>

                <div class="input-div">
                    <div class="i">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="input-container">
                        <input class="input" type="text" name="username" placeholder=" " required value="<?php echo htmlspecialchars($remembered_username); ?>">
                        <h5>Tên Đăng Nhập</h5>
                        <div class="duplicate-error" id="username-error"></div>
                    </div>
                </div>

                <div class="input-div">
                    <div class="i">
                        <i class="fas fa-key"></i>
                    </div>
                    <div class="input-container">
                        <input class="input" type="password" name="password" id="password" placeholder=" " required>
                        <h5>Mật Khẩu</h5>
                        <i class="fas fa-eye toggle-password" id="togglePassword"></i>
                        <div class="duplicate-error" id="password-error"></div>
                    </div>
                </div>

                <!-- Remember Me Checkbox -->
                <div class="input-div">
                    <label class="checkbox-container">
                        <input type="checkbox" name="remember_me" id="remember_me" <?php if (!empty($remembered_username)) echo 'checked'; ?>>
                        <span class="checkmark"></span>
                        Nhớ thông tin đăng nhập
                    </label>
                </div>

                <input type="submit" class="btn" value="Đăng Nhập">
                <a class="forgot" href="forgotPassword.php">Quên mật khẩu?</a>
                <div class="others">
                    <hr>
                    <p>Hoặc</p>
                    <hr>
                </div>
                <div class="account">
                    <p>Bạn không có tài khoản?</p>
                    <a href="register.php">Đăng Ký</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Custom Scripts -->
    <script>
        // Toggle password visibility
        const togglePassword = document.getElementById('togglePassword');
        const password = document.getElementById('password');

        togglePassword.addEventListener('click', function () {
            // Toggle the type attribute
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            // Toggle the eye slash icon
            this.classList.toggle('fa-eye-slash');
            this.classList.toggle('fa-eye');
        });

        // Optional: Client-side form validation
        const loginForm = document.getElementById('loginForm');

        loginForm.addEventListener('submit', function (e) {
            const username = loginForm.username.value.trim();
            const pwd = loginForm.password.value.trim();

            if (username === '' || pwd === '') {
                e.preventDefault();
                alert('Please fill in all fields.');
            }
        });
    </script>
</body>

</html>
