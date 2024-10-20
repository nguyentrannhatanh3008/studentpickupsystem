<?php
// register.php

// Require Composer's autoloader
require __DIR__ . '/../vendor/autoload.php';

// Get configuration from config.php
$config = require __DIR__ . '/../config.php';

// Enable PHP error reporting based on environment
if (isset($config['environment']) && $config['environment'] === 'development') {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
}

// Start session with secure settings
session_start([
    'cookie_httponly' => true,
    'cookie_secure' => isset($_SERVER['HTTPS']),
    'cookie_samesite' => 'Strict',
]);

// Import PHPMailer classes into the global namespace
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Function to generate CSRF token
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

// Function to validate CSRF token
function validate_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Function to validate phone number
function validate_phone($phone) {
    // Adjust the regex pattern based on your requirements
    return preg_match('/^[0-9]{10,15}$/', $phone);
}

// Function to sanitize input data
function sanitize_input($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

// Function to verify Google reCAPTCHA
function verify_recaptcha($recaptcha_secret, $recaptcha_response) {
    $url = 'https://www.google.com/recaptcha/api/siteverify';
    $data = [
        'secret' => $recaptcha_secret,
        'response' => $recaptcha_response,
        'remoteip' => $_SERVER['REMOTE_ADDR']
    ];

    $options = [
        'http' => [
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($data),
            'timeout' => 10
        ],
    ];
    $context  = stream_context_create($options);
    $result = @file_get_contents($url, false, $context);
    if ($result === FALSE) {
        return false;
    }

    $resultData = json_decode($result, true);
    return isset($resultData['success']) && $resultData['success'];
}

// Generate CSRF token
generate_csrf_token();

// Connect to PostgreSQL database
$conn_string = "host={$config['database']['host']} dbname={$config['database']['dbname']} user={$config['database']['username']} password={$config['database']['password']}";
$conn = pg_connect($conn_string);

// Check connection
if (!$conn) {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Kết nối cơ sở dữ liệu thất bại.']);
        exit();
    } else {
        echo "<div style='color: red;'>Kết nối cơ sở dữ liệu thất bại.</div>";
        exit();
    }
}

// Initialize error and success messages
$error_message = '';
$success_message = '';

// Determine current step
$step = 'step1'; // Default step
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['step'])) {
        $step = $_POST['step'];
    }
} elseif (isset($_GET['step'])) {
    $step = $_GET['step'];
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        $_SESSION['error_message'] = "Yêu cầu không hợp lệ. Vui lòng thử lại.";
        header("Location: register.php#step-1");
        pg_close($conn);
        exit();
    }

    // Verify reCAPTCHA for step1
    if ($step == 'step1' && isset($_POST['g-recaptcha-response'])) {
        $recaptcha_valid = verify_recaptcha($config['recaptcha']['secret_key'], $_POST['g-recaptcha-response']);
        if (!$recaptcha_valid) {
            $_SESSION['error_message'] = "Vui lòng xác nhận rằng bạn không phải là robot.";
            header("Location: register.php#step-1");
            pg_close($conn);
            exit();
        }
    }

    if ($step == 'step1') {
        // Step 1: Basic Information

        // Get and sanitize form data
        $username = isset($_POST['username']) ? sanitize_input($_POST['username']) : '';
        $email = isset($_POST['email']) ? filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL) : '';
        $phone = isset($_POST['phone']) ? sanitize_input($_POST['phone']) : '';
        $name = isset($_POST['name']) ? sanitize_input($_POST['name']) : '';

        // Validate inputs
        if (empty($username) || empty($email) || empty($phone) || empty($name)) {
            $error_message .= "Vui lòng điền đầy đủ các trường yêu cầu.<br>";
        }

        if (!$email) {
            $error_message .= "Định dạng email không hợp lệ.<br>";
        }

        if (!validate_phone($phone)) {
            $error_message .= "Số điện thoại không hợp lệ.<br>";
        }

        // Check for duplicate username, email, or phone
        if (empty($error_message)) {
            $duplicate_fields = [];
            $check_query = "SELECT username, email, phone FROM public.\"user\" WHERE username = $1 OR email = $2 OR phone = $3";
            $result = pg_query_params($conn, $check_query, array($username, $email, $phone));

            if ($result === false) {
                $_SESSION['error_message'] = "Lỗi thực thi truy vấn cơ sở dữ liệu.";
                error_log("Truy vấn kiểm tra trùng lặp thất bại: " . pg_last_error($conn));
                header("Location: register.php#step-1");
                pg_close($conn);
                exit();
            }

            if (pg_num_rows($result) > 0) {
                while ($row = pg_fetch_assoc($result)) {
                    if ($row['username'] === $username) {
                        $duplicate_fields[] = "Tên đăng nhập đã được sử dụng.";
                    }
                    if ($row['email'] === $email) {
                        $duplicate_fields[] = "Email đã được sử dụng.";
                    }
                    if ($row['phone'] === $phone) {
                        $duplicate_fields[] = "Số điện thoại đã được sử dụng.";
                    }
                }
            }

            if (!empty($duplicate_fields)) {
                foreach ($duplicate_fields as $msg) {
                    $error_message .= $msg . "<br>";
                }
                $_SESSION['error_message'] = $error_message;
                header("Location: register.php#step-1");
                pg_close($conn);
                exit();
            }
        }

        // Initialize role as null
        $auto_role = null;

        // Priority 1: Check in public.schools (QuanLyNhaTruong)
        if (empty($error_message)) {
            $school_query = "SELECT 1 FROM public.schools WHERE contact_number = $1 AND email = $2 LIMIT 1";
            $school_result = pg_query_params($conn, $school_query, array($phone, $email));

            if ($school_result && pg_num_rows($school_result) > 0) {
                $auto_role = 'QuanLyNhaTruong';
            } else {
                // Priority 2: Check in public.teachers (GiaoVien)
                $teacher_query = "SELECT 1 FROM public.teachers WHERE phone_number = $1 LIMIT 1";
                $teacher_result = pg_query_params($conn, $teacher_query, array($phone));

                if ($teacher_result && pg_num_rows($teacher_result) > 0) {
                    $auto_role = 'GiaoVien';
                } else {
                    $parent_query = "SELECT 1 FROM public.student WHERE FPN = $1 OR MPN = $1 LIMIT 1";
                    $parent_result = pg_query_params($conn, $parent_query, array($phone));

                    if ($parent_result && pg_num_rows($parent_result) > 0) {
                        $auto_role = 'PhuHuynh';
                        error_log("Auto Role set to PhuHuynh for phone: $phone");
                    }
                }
            }
        }

        if (is_null($auto_role)) {
            // Thêm thông báo lỗi vào session
            $_SESSION['error_message'] = "Không tìm thấy vai trò phù hợp với thông tin đã cung cấp. Vui lòng kiểm tra lại hoặc liên hệ hỗ trợ.";

            // Chuyển hướng đến trang error.php
            header("Location: error.php");
            pg_close($conn);
            exit();
        }

        // Save registration data to session, including the determined role
        $_SESSION['register_data'] = array(
            'username' => $username,
            'email' => $email,
            'phone' => $phone,
            'name' => $name,
            'role' => $auto_role // Auto-set role
        );

        // Generate a 6-digit verification code
        $verification_code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);

        // Save verification code and timestamp to session
        $_SESSION['verification_code'] = $verification_code;
        $_SESSION['verification_code_time'] = time();

        // Use PHPMailer to send verification email
        $mail = new PHPMailer(true);

        try {
            // SMTP server configuration
            $mail->isSMTP();
            $mail->Host       = $config['mailer']['host'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $config['mailer']['username'];
            $mail->Password   = $config['mailer']['password']; // Application-specific password
            $mail->SMTPSecure = $config['mailer']['encryption'];
            $mail->Port       = $config['mailer']['port'];

            // Sender and recipient
            $mail->setFrom($config['mailer']['from_email'], $config['mailer']['from_name']);
            $mail->addAddress($email, $name); // Recipient

            // Email content
            $mail->isHTML(true);
            $mail->CharSet = 'UTF-8';
            $mail->Subject = 'Mã Xác Thực Đăng Ký';
            $mail->Body    = "Xin Chào <b>{$name}</b>,<br><br>Mã xác thực của bạn là: <b>{$verification_code}</b><br><br>Mã này có hiệu lực trong 15 phút. Vui lòng nhập mã này để hoàn tất quá trình đăng ký.";
            $mail->AltBody = "Xin Chào {$name},\n\nMã xác thực của bạn là: {$verification_code}\n\nMã này có hiệu lực trong 15 phút. Vui lòng nhập mã này để hoàn tất quá trình đăng ký.";

            $mail->send();
            // If email sent successfully, redirect to step 2
            header("Location: register.php#step-2");
            pg_close($conn);
            exit();
        } catch (Exception $e) {
            // If email sending failed
            $_SESSION['error_message'] = "Đã xảy ra lỗi khi gửi email xác thực. Vui lòng thử lại.<br>Lỗi: " . ($config['environment'] === 'development' ? $mail->ErrorInfo : 'Vui lòng thử lại sau.');
            error_log("Lỗi gửi email: " . $mail->ErrorInfo);
            header("Location: register.php#step-1");
            pg_close($conn);
            exit();
        }

    } elseif ($step == 'step2') {
        // Step 2: Verification Code

        // Get form data
        $verification_code = isset($_POST['verification_code']) ? sanitize_input($_POST['verification_code']) : '';
        $email = $_SESSION['register_data']['email'] ?? '';

        // Check if email is in session
        if (empty($email)) {
            $_SESSION['error_message'] = "Không tìm thấy thông tin email. Vui lòng đăng ký lại.";
            header("Location: register.php#step-1");
            pg_close($conn);
            exit();
        }

        // Check verification code
        if (empty($verification_code)) {
            $_SESSION['error_message'] = "Vui lòng nhập mã xác thực.";
            header("Location: register.php#step-2");
            pg_close($conn);
            exit();
        }

        // Verify the code and check expiration (15 minutes)
        $max_time = 15 * 60; // 15 minutes
        if (!isset($_SESSION['verification_code']) || $verification_code !== $_SESSION['verification_code']) {
            $_SESSION['error_message'] = "Mã xác thực không đúng. Vui lòng thử lại.";
            header("Location: register.php#step-2");
            pg_close($conn);
            exit();
        }

        if (!isset($_SESSION['verification_code_time']) || (time() - $_SESSION['verification_code_time']) > $max_time) {
            $_SESSION['error_message'] = "Mã xác thực đã hết hạn. Vui lòng đăng ký lại.";
            // Clear previous verification data
            unset($_SESSION['verification_code']);
            unset($_SESSION['verification_code_time']);
            header("Location: register.php#step-1");
            pg_close($conn);
            exit();
        }

        // Remove verification code from session
        unset($_SESSION['verification_code']);
        unset($_SESSION['verification_code_time']);

        // Redirect to step 3
        header("Location: register.php#step-3");
        pg_close($conn);
        exit();

    } elseif ($step == 'step3') {
        // Step 3: Detailed Information and Final Registration

        // Get data from session
        if (!isset($_SESSION['register_data'])) {
            $_SESSION['error_message'] = "Không tìm thấy thông tin đăng ký. Vui lòng đăng ký lại.";
            header("Location: register.php#step-1");
            pg_close($conn);
            exit();
        }

        $username = $_SESSION['register_data']['username'] ?? '';
        $email = $_SESSION['register_data']['email'] ?? '';
        $phone = $_SESSION['register_data']['phone'] ?? '';
        $name = $_SESSION['register_data']['name'] ?? '';
        $role = $_SESSION['register_data']['role'] ?? '';

        // Get and sanitize form data
        $address = isset($_POST['address']) ? sanitize_input($_POST['address']) : '';
        $date_of_birth = isset($_POST['date_of_birth']) && !empty($_POST['date_of_birth']) ? $_POST['date_of_birth'] : null;
        $password_input = isset($_POST['password']) ? $_POST['password'] : '';
        $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
        $gender = isset($_POST['gender']) ? sanitize_input($_POST['gender']) : '';
        $selected_role = $role; // Use the auto-determined role
        $school_code = isset($_POST['school_code']) ? sanitize_input($_POST['school_code']) : null;
        $terms = isset($_POST['terms']) ? $_POST['terms'] : '';

        // Validate inputs
        if (empty($address) || empty($date_of_birth) || empty($password_input) || empty($confirm_password) || empty($gender)) {
            $error_message .= "Vui lòng điền đầy đủ các trường yêu cầu.<br>";
        }

        // Validate passwords
        if ($password_input !== $confirm_password) {
            $error_message .= "Mật khẩu không khớp.<br>";
        }

        // Validate role and school code
        if (($selected_role === 'QuanLyNhaTruong' || $selected_role === 'GiaoVien') && empty($school_code)) {
            $error_message .= "Vui lòng nhập Mã Trường.<br>";
        }

        // If role requires school code, verify it
        if (($selected_role === 'QuanLyNhaTruong' || $selected_role === 'GiaoVien') && !empty($school_code)) {
            $check_school_query = "SELECT 1 FROM public.schools WHERE school_code = $1 LIMIT 1";
            $school_result = pg_query_params($conn, $check_school_query, array($school_code));
            if (!$school_result || pg_num_rows($school_result) == 0) {
                $error_message .= "Mã Trường không tồn tại.<br>";
            }
        }

        // Validate gender
        $valid_genders = ['Nam', 'Nữ', 'Khác'];
        if (!in_array($gender, $valid_genders)) {
            $error_message .= "Giới tính không hợp lệ.<br>";
        }

        // Validate terms
        if ($terms !== 'on') {
            $error_message .= "Bạn phải đồng ý với điều khoản sử dụng.<br>";
        }

        // If there are errors, redirect back with error messages
        if (!empty($error_message)) {
            $_SESSION['error_message'] = $error_message;
            header("Location: register.php#step-3");
            pg_close($conn);
            exit();
        }

        // Hash the password
        $hashed_password = password_hash($password_input, PASSWORD_DEFAULT);

        // Insert new user into the database
        $insert_query = "INSERT INTO public.\"user\" (username, email, phone, name, address, date_of_birth, hashed_password, gender, role, school_code, is_verified) VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10, TRUE)";
        $insert_result = pg_query_params($conn, $insert_query, array(
            $username,
            $email,
            $phone,
            $name,
            $address,
            $date_of_birth,
            $hashed_password,
            $gender,
            $selected_role,
            $school_code
        ));

        if ($insert_result) {
            // Clear registration data from session
            unset($_SESSION['register_data']);
            unset($_SESSION['error_message']);

            // Set success message
            $_SESSION['success_message'] = "Đăng ký thành công! Bạn có thể đăng nhập ngay bây giờ.";

            // Redirect to success page
            header("Location: success.html");
            pg_close($conn);
            exit();
        } else {
            // Handle insertion error
            $_SESSION['error_message'] = "Đã xảy ra lỗi khi hoàn tất đăng ký. Vui lòng thử lại.";
            error_log("Lỗi thêm người dùng mới: " . pg_last_error($conn));
            header("Location: register.php#step-3");
            pg_close($conn);
            exit();
        }
    }
}

// Close connection after processing
pg_close($conn);
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng Ký</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans&display=swap" rel="stylesheet">
    <link rel="icon" href="img/html-5.png">
    <link rel="stylesheet" href="css/register.css">
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
</head>

<body>
    <div class="container">
        <div class="img">
            <img src="img/login-mobile.svg" alt="Login Image">
        </div>
        <div class="login-container">
            <?php
            // Display error message if exists
            if (isset($_SESSION['error_message']) && !empty($_SESSION['error_message'])) {
                echo '<div class="error-message">' . $_SESSION['error_message'] . '</div>';
                unset($_SESSION['error_message']);
            }

            // Display success message if exists
            if (isset($_SESSION['success_message']) && !empty($_SESSION['success_message'])) {
                echo '<div class="success-message">' . $_SESSION['success_message'] . '</div>';
                unset($_SESSION['success_message']);
            }
            ?>

            <!-- Registration Form -->
            <form id="registrationForm" action="register.php" method="POST">
                <input type="hidden" name="step" id="currentStep" value="step1">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">

                <!-- Step 1: Basic Information -->
                <div class="form-step active" id="step-1">
                    <h2>Đăng Ký - Bước 1</h2>

                    <div class="input-div">
                        <div class="i">
                            <i class="fas fa-user"></i>
                        </div>
                        <div class="input-container">
                            <input class="input" type="text" name="username" id="username" placeholder=" " required
                                value="<?php echo htmlspecialchars($_SESSION['register_data']['username'] ?? ''); ?>">
                            <h5>Tên Đăng Nhập</h5>
                            <div class="duplicate-error" id="username-error"></div>
                        </div>
                    </div>

                    <div class="input-div">
                        <div class="i">
                            <i class="fas fa-id-badge"></i>
                        </div>
                        <div class="input-container">
                            <input class="input" type="text" name="name" placeholder=" " required
                                value="<?php echo htmlspecialchars($_SESSION['register_data']['name'] ?? ''); ?>">
                            <h5>Họ và Tên</h5>
                            <div class="duplicate-error" id="name-error"></div>
                        </div>
                    </div>

                    <div class="input-div">
                        <div class="i">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <div class="input-container">
                            <input class="input" type="email" name="email" id="email" placeholder=" " required
                                value="<?php echo htmlspecialchars($_SESSION['register_data']['email'] ?? ''); ?>">
                            <h5>Email</h5>
                            <div class="duplicate-error" id="email-error"></div>
                        </div>
                    </div>

                    <div class="input-div">
                        <div class="i">
                            <i class="fas fa-phone"></i>
                        </div>
                        <div class="input-container">
                            <input class="input" type="tel" name="phone" id="phone" placeholder=" " required
                                value="<?php echo htmlspecialchars($_SESSION['register_data']['phone'] ?? ''); ?>">
                            <h5>Số Điện Thoại</h5>
                            <div class="duplicate-error" id="phone-error"></div>
                        </div>
                    </div>

                    <!-- Google reCAPTCHA -->
                    <div class="g-recaptcha" data-sitekey="<?php echo htmlspecialchars($config['recaptcha']['site_key'] ?? ''); ?>"></div>
                    <div class="duplicate-error" id="recaptcha-error"></div>

                    <div class="btn-container">
                        <button type="button" class="btn-action" id="next1">Tiếp Theo</button>
                    </div>
                    <div class="account">
                        <p>Đã có tài khoản?</p>
                        <a href="login.php">Đăng Nhập</a>
                    </div>
                </div>

                <!-- Step 2: Verification Code -->
                <div class="form-step" id="step-2">
                    <h2>Đăng Ký - Bước 2</h2>
                    <p>Mã xác thực đã được gửi đến email của bạn.</p>

                    <div class="verification-code-container">
                        <input class="verification-code-input" type="text" maxlength="1" pattern="[0-9]*" inputmode="numeric" required>
                        <input class="verification-code-input" type="text" maxlength="1" pattern="[0-9]*" inputmode="numeric" required>
                        <input class="verification-code-input" type="text" maxlength="1" pattern="[0-9]*" inputmode="numeric" required>
                        <input class="verification-code-input" type="text" maxlength="1" pattern="[0-9]*" inputmode="numeric" required>
                        <input class="verification-code-input" type="text" maxlength="1" pattern="[0-9]*" inputmode="numeric" required>
                        <input class="verification-code-input" type="text" maxlength="1" pattern="[0-9]*" inputmode="numeric" required>
                    </div>
                    <input type="hidden" name="verification_code" id="verification_code">
                    <div class="duplicate-error" id="verification-error"></div>

                    <div class="btn-container">
                        <button type="button" class="btn-action" id="prev2">Quay Lại</button>
                        <button type="button" class="btn-action" id="next2">Xác Thực</button>
                    </div>
                    <div class="account">
                        <p>Đã có tài khoản?</p>
                        <a href="login.php">Đăng Nhập</a>
                    </div>
                </div>

                <!-- Step 3: Detailed Information -->
                <div class="form-step" id="step-3">
                    <h2>Đăng Ký - Bước 3</h2>

                    <div class="input-div">
                        <div class="i">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <div class="input-container">
                            <input class="input" type="text" name="address" id="address" placeholder=" " required
                                value="<?php echo htmlspecialchars($_SESSION['register_data']['address'] ?? ''); ?>">
                            <h5>Địa Chỉ</h5>
                        </div>
                    </div>

                    <div class="input-div">
                        <div class="i">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <div class="input-container">
                            <input class="input" type="date" name="date_of_birth" id="date_of_birth" placeholder=" " required
                                value="<?php echo htmlspecialchars($_SESSION['register_data']['date_of_birth'] ?? ''); ?>">
                            <h5>Ngày Sinh</h5>
                        </div>
                    </div>

                    <div class="input-div">
                        <div class="i">
                            <i class="fas fa-lock"></i>
                        </div>
                        <div class="input-container">
                            <input class="input" type="password" name="password" id="password" placeholder=" " required>
                            <h5>Mật Khẩu</h5>
                            <i class="fas fa-eye toggle-password" id="togglePassword"></i>
                            <div class="duplicate-error" id="password-error"></div>
                        </div>
                    </div>

                    <div class="input-div">
                        <div class="i">
                            <i class="fas fa-lock"></i>
                        </div>
                        <div class="input-container">
                            <input class="input" type="password" name="confirm_password" id="confirm_password" placeholder=" " required>
                            <h5>Xác Nhận Mật Khẩu</h5>
                            <i class="fas fa-eye toggle-password" id="toggleConfirmPassword"></i>
                            <div class="duplicate-error" id="confirm-password-error"></div>
                        </div>
                    </div>

                    <div class="input-div">
                        <div class="i">
                            <i class="fas fa-venus-mars"></i>
                        </div>
                        <div class="input-container">
                            <select class="input" name="gender" id="gender" required>
                                <option value="" disabled <?php echo (!isset($_SESSION['register_data']['gender']) || empty($_SESSION['register_data']['gender'])) ? 'selected hidden' : ''; ?>></option>
                                <option value="Nam" <?php echo (isset($_SESSION['register_data']['gender']) && $_SESSION['register_data']['gender'] == 'Nam') ? 'selected' : ''; ?>>Nam</option>
                                <option value="Nữ" <?php echo (isset($_SESSION['register_data']['gender']) && $_SESSION['register_data']['gender'] == 'Nữ') ? 'selected' : ''; ?>>Nữ</option>
                                <option value="Khác" <?php echo (isset($_SESSION['register_data']['gender']) && $_SESSION['register_data']['gender'] == 'Khác') ? 'selected' : ''; ?>>Khác</option>
                            </select>
                            <h5>Giới Tính</h5>
                        </div>
                    </div>

                    <!-- Role Display (Read-Only) -->
                    <div class="input-div" id="display-role-div">
                        <div class="i">
                            <i class="fas fa-user-tag"></i>
                        </div>
                        <div class="input-container">
                            <input class="input" type="text" name="display_role" id="display_role" placeholder=" " readonly
                                value="<?php
                                    if (isset($_SESSION['register_data']['role']) && !empty($_SESSION['register_data']['role'])) {
                                        switch ($_SESSION['register_data']['role']) {
                                            case 'PhuHuynh':
                                                echo 'Phụ Huynh';
                                                break;
                                            case 'GiaoVien':
                                                echo 'Giáo Viên';
                                                break;
                                            case 'QuanLyNhaTruong':
                                                echo 'Quản Lý Nhà Trường';
                                                break;
                                            default:
                                                echo '';
                                        }
                                    }
                                ?>">
                            <h5>Vai Trò</h5>
                        </div>
                    </div>

                    <!-- Hidden Role Input -->
                    <input type="hidden" name="role" id="role" value="<?php echo htmlspecialchars($_SESSION['register_data']['role'] ?? ''); ?>">

                    <!-- School Code (conditionally displayed) -->
                    <div class="input-div" id="school-code-div" style="display: <?php
                        if (isset($_SESSION['register_data']['role']) && in_array($_SESSION['register_data']['role'], ['GiaoVien', 'QuanLyNhaTruong'])) {
                            echo 'flex;';
                        } else {
                            echo 'none;';
                        }
                    ?>">
                        <div class="i">
                            <i class="fas fa-school"></i>
                        </div>
                        <div class="input-container">
                            <input class="input" type="text" name="school_code" id="school_code" placeholder=" "
                                value="<?php echo htmlspecialchars($_SESSION['register_data']['school_code'] ?? ''); ?>">
                            <h5>Mã Trường</h5>
                        </div>
                    </div>

                    <div class="terms">
                        <input type="checkbox" name="terms" id="terms" required>
                        <label for="terms">Tôi đã đọc và đồng ý với </label><a href="#" id="action-modal">điều khoản sử dụng.</a>
                    </div>
                    <div class="btn-container">
                        <button type="button" class="btn-action" id="prev3">Quay Lại</button>
                        <button type="button" class="btn-action" id="next3">Đăng Ký</button>
                    </div>
                    <div class="account">
                        <p>Đã có tài khoản?</p>
                        <a href="login.php">Đăng Nhập</a>
                    </div>
                </div>
            </form>

            <!-- Terms and Conditions Modal -->
            <div id="modal-terms" class="modal">
                <div class="modal-content">
                    <span class="close" data-modal="modal-terms">&times;</span>
                    <h2>Điều Khoản và Dịch Vụ</h2>
                    <p>Chào mừng bạn đến với Ứng Dụng của chúng tôi! Bằng cách truy cập trang web của chúng tôi tại [Website URL], bạn đồng ý tuân thủ các điều khoản dịch vụ này, tất cả các luật và quy định áp dụng, và đồng ý rằng bạn chịu trách nhiệm tuân thủ bất kỳ luật địa phương áp dụng nào. Nếu bạn không đồng ý với bất kỳ điều khoản nào, bạn bị cấm sử dụng hoặc truy cập vào trang web này.</p>
                    <p>Quyền được cấp phép tạm thời để tải xuống một bản sao tài liệu trên trang web của chúng tôi chỉ để xem tạm thời cá nhân, phi thương mại. Đây là việc cấp phép, không phải chuyển nhượng quyền sở hữu. Bạn không được phép chỉnh sửa tài liệu; sử dụng tài liệu cho bất kỳ mục đích thương mại nào, hoặc cho bất kỳ hiển thị công cộng nào; cố gắng giải mã hoặc phân tích ngược bất kỳ phần mềm nào có trên trang web của chúng tôi; chuyển tài liệu cho người khác hoặc "mirror" tài liệu trên bất kỳ máy chủ nào khác.</p>
                    <p>Tài liệu trên trang web của chúng tôi được cung cấp theo cơ sở 'như hiện tại'. Chúng tôi không đưa ra bất kỳ bảo đảm nào, dù rõ ràng hay ngụ ý, và từ chối mọi bảo đảm khác bao gồm, nhưng không giới hạn, các bảo đảm ngụ ý về khả năng bán hàng, phù hợp với một mục đích cụ thể, hoặc không vi phạm quyền sở hữu trí tuệ hoặc vi phạm quyền khác. Hơn nữa, chúng tôi không bảo đảm hoặc đưa ra bất kỳ tuyên bố nào về độ chính xác, kết quả có thể đạt được, hoặc độ tin cậy của việc sử dụng tài liệu trên trang web của mình.</p>
                    <p>Chúng tôi chưa xem xét tất cả các trang web liên kết đến trang web của mình và không chịu trách nhiệm về nội dung của bất kỳ trang liên kết nào như vậy. Việc bao gồm bất kỳ liên kết nào không ngụ ý sự đồng ý của chúng tôi đối với trang đó. Việc sử dụng bất kỳ trang web liên kết nào như vậy là dưới sự chịu trách nhiệm của người dùng.</p>
                    <p>Các điều khoản và điều kiện này được điều chỉnh bởi và giải thích theo luật pháp của Việt Nam và bạn cam kết không thể hủy bỏ với quyền tài phán độc quyền của các tòa án tại tỉnh hoặc địa điểm đó.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Custom Scripts -->
    <script src="js/register.js"></script>
</body>

</html>
