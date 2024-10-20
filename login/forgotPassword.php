<?php
session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Composer autoload
require __DIR__ . '/../vendor/autoload.php';

// Load configuration
$config = require __DIR__ . '/../config.php';

// Database connection
try {
    $dsn = "pgsql:host={$config['database']['host']};dbname={$config['database']['dbname']}";
    $pdo = new PDO($dsn, $config['database']['username'], $config['database']['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (PDOException $e) {
    die("Không thể kết nối đến cơ sở dữ liệu: " . $e->getMessage());
}

// Generate random verification token
function generateToken($length = 6) {
    return str_pad(rand(0, 999999), $length, '0', STR_PAD_LEFT);
}

// Send email function
function sendEmail($email, $name, $token, $config) {
    $mail = new PHPMailer(true);
    try {
        // SMTP server configuration
        $mail->isSMTP();
        $mail->Host       = $config['mailer']['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $config['mailer']['username'];
        $mail->Password   = $config['mailer']['password'];
        $mail->SMTPSecure = $config['mailer']['encryption'];
        $mail->Port       = $config['mailer']['port'];

        // Check if 'from' address is valid
        if (empty($config['mailer']['from_email']) || !filter_var($config['mailer']['from_email'], FILTER_VALIDATE_EMAIL)) {
            return "SMTP 'from' address is invalid or empty. Please check your configuration.";
        }

        // Sender and recipient
        $mail->setFrom($config['mailer']['from_email'], $config['mailer']['from_name']);
        $mail->addAddress($email, $name);

        // Email content
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = 'Mã Xác Thực Khôi Phục Tài Khoản';
        $mail->Body    = "Xin Chào {$name},<br><br>Mã xác thực của bạn là: <b>{$token}</b>.<br><br>Vui lòng không chia sẻ mã này với bất kỳ ai.<br><br>Trân trọng,<br>Hệ Thống Quản Lý Đưa Đón Học Sinh";

        $mail->send();
        return true;
    } catch (Exception $e) {
        return "Không thể gửi email. Lỗi: {$mail->ErrorInfo}";
    }
}

// Handle reset process action
if (isset($_GET['action']) && $_GET['action'] === 'reset_process') {
    // Unset all relevant session variables
    unset($_SESSION['pending_verification']);
    unset($_SESSION['verified_user']);
    // Redirect to the first page
    header("Location: forgotPassword.php");
    exit();
}


// Xử lý khi form được gửi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        if ($action === 'send_code') {
            $method = $_POST['method'];
            if ($method === 'email') {
                $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
                if (!$email) {
                    $_SESSION['error-message'] = "Email không hợp lệ.";
                    header("Location: forgotPassword.php");
                    exit();
                }

                // Check if user exists
                $stmt = $pdo->prepare("SELECT * FROM public.\"user\" WHERE email = :email");
                $stmt->execute(['email' => $email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$user) {
                    $_SESSION['error-message'] = "Email không tồn tại.";
                    header("Location: forgotPassword.php");
                    exit();
                }

                // Generate verification token
                $token = generateToken();

                // Save the token and verification status in the database
                $stmt = $pdo->prepare("UPDATE public.\"user\" SET verification_code = :token, is_verified = false WHERE email = :email");
                $stmt->execute(['token' => $token, 'email' => $email]);

                // Send email with PHPMailer
                $sendEmailStatus = sendEmail($email, $user['name'], $token, $config);
                if ($sendEmailStatus === true) {
                    $_SESSION['success-message'] = "Mã xác thực đã được gửi đến email của bạn.";
                    $_SESSION['pending_verification'] = $email; // Store email for verification
                } else {
                    $_SESSION['error-message'] = $sendEmailStatus;
                }
            } else {
                // Method 'phone' is not supported
                $_SESSION['error-message'] = "Phương thức 'Số Điện Thoại' chưa được hỗ trợ.";
            }
            header("Location: forgotPassword.php");
            exit();
        } elseif ($action === 'verify_code') {
            $method = $_POST['method'];
            $token = $_POST['token'];

            if ($method === 'email') {
                if (isset($_SESSION['pending_verification'])) {
                    $email = $_SESSION['pending_verification'];
                } else {
                    $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
                }

                if (!$email) {
                    $_SESSION['error-message'] = "Email không hợp lệ.";
                    header("Location: forgotPassword.php");
                    exit();
                }

                // Kiểm tra mã xác thực
                $stmt = $pdo->prepare("SELECT * FROM public.\"user\" WHERE email = :email AND verification_code = :token");
                $stmt->execute(['email' => $email, 'token' => $token]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($user) {
                    // Mã xác thực hợp lệ
                    $stmt = $pdo->prepare("UPDATE public.\"user\" SET is_verified = true WHERE email = :email");
                    $stmt->execute(['email' => $email]);
                    $_SESSION['verified_user'] = $user['id'];
                    $_SESSION['success-message'] = "Xác thực thành công. Vui lòng đổi mật khẩu.";
                } else {
                    $_SESSION['error-message'] = "Mã xác thực không hợp lệ.";
                }
            } else {
                // Phương thức "phone" không được hỗ trợ
                $_SESSION['error-message'] = "Phương thức 'Số Điện Thoại' chưa được hỗ trợ.";
            }
            header("Location: forgotPassword.php");
            exit();
        } elseif ($action === 'reset_password') {
            if (!isset($_SESSION['verified_user'])) {
                $_SESSION['error-message'] = "Bạn cần xác thực trước khi đổi mật khẩu.";
                header("Location: forgotPassword.php");
                exit();
            }

            $password = $_POST['password'];
            $confirm_password = $_POST['confirm_password'];

            // Kiểm tra mật khẩu
            if ($password !== $confirm_password) {
                $_SESSION['error-message'] = "Mật khẩu xác nhận không khớp.";
                header("Location: forgotPassword.php");
                exit();
            }

            // Kiểm tra độ phức tạp của mật khẩu
            if (strlen($password) < 8 || !preg_match('/^(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d]{8,}$/', $password)) {
                $_SESSION['error-message'] = "Mật khẩu phải có ít nhất 8 ký tự, bao gồm cả chữ cái và số.";
                header("Location: forgotPassword.php");
                exit();
            }

            // Mã hóa mật khẩu
            $hashed_password = password_hash($password, PASSWORD_BCRYPT);

            // Cập nhật mật khẩu
            $stmt = $pdo->prepare("UPDATE public.\"user\" SET hashed_password = :password, verification_code = NULL, is_verified = false WHERE id = :id");
            $stmt->execute(['password' => $hashed_password, 'id' => $_SESSION['verified_user']]);

            // Xóa phiên xác thực
            unset($_SESSION['verified_user']);
            unset($_SESSION['pending_verification']);

            $_SESSION['password_reset_success'] = true;
            $_SESSION['success-message'] = "Mật khẩu đã được cập nhật thành công. Bạn có thể đăng nhập ngay bây giờ.";
            header("Location: forgotPassword.php");
            exit();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lấy Lại Tài Khoản</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="icon" href="img/html-5.png">
    <link rel="stylesheet" href="css/forgotpass.css">
</head>

<body>
    <!-- Notification Messages -->
    <?php if (isset($_SESSION['error-message'])): ?>
        <div class="error-message">
            <?php echo $_SESSION['error-message']; unset($_SESSION['error-message']); ?>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['success-message'])): ?>
        <div class="success-message">
            <?php echo $_SESSION['success-message']; unset($_SESSION['success-message']); ?>
        </div>
    <?php endif; ?>

    <img class="wave" src="img/wave.svg" alt="Wave Background">
    <div class="container">
        <div class="img">
            <img src="img/personalization.svg" alt="Personalization Illustration">
        </div>
        <div class="login-container">
            <form id="forgotPasswordForm" action="forgotPassword.php" method="POST">
                <?php if (!isset($_SESSION['verified_user'])): ?>
                    <?php if (!isset($_SESSION['pending_verification'])): ?>
                        <!-- Bước 1: Gửi mã xác thực -->
                        <h2>Lấy Lại Tài Khoản</h2>
                        <p>Chọn phương thức để lấy lại tài khoản của bạn</p>

                        <!-- Phương thức khôi phục -->
                        <div class="method-selection">
                            <label>
                                <input type="radio" name="method" value="email" checked>
                                Email
                            </label>
                            <label>
                                <input type="radio" name="method" value="phone" id="phoneMethod">
                                Số Điện Thoại
                            </label>
                        </div>

                        <!-- Phần nhập Email -->
                        <div class="form-step active" id="emailStep">
                            <div class="input-div">
                                <div class="i">
                                    <i class="fas fa-envelope"></i>
                                </div>
                                <div class="input-container" style="position: relative;">
                                    <input class="input" type="email" name="email" id="email" placeholder=" " required>
                                    <h5>Email</h5>
                                </div>
                            </div>
                        </div>

                        <!-- Phần nhập Số Điện Thoại (chưa hỗ trợ) -->
                        <div class="form-step" id="phoneStep">
                            <div class="input-div" style="position: relative;">
                                <div class="i">
                                    <i class="fas fa-phone"></i>
                                </div>
                                <div class="input-container" style="position: relative;">
                                    <input class="input" type="tel" name="phone" id="phone" placeholder=" " pattern="[0-9]{10}" required disabled>
                                    <h5>Số Điện Thoại</h5>
                                    <div class="input-overlay" id="phoneOverlay" style="display: none;">
                                        <p>Tính năng này chưa được hỗ trợ.</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <input type="hidden" name="action" value="send_code">
                        <input type="submit" class="btn-action" value="Gửi">
                        <div class="account">
                            <p>Đã Nhớ Mật Khẩu?</p>
                            <a href="login.php">Đăng Nhập</a>
                        </div>
                    <?php else: ?>
                        <!-- Bước 2: Xác thực mã -->
                        <h2>Xác Thực Mã</h2>
                        <p>Nhập mã xác thực đã được gửi đến email của bạn</p>

                        <!-- Phương thức xác thực (Chỉ hỗ trợ email) -->
                        <div class="method-selection">
                            <label>
                                <input type="radio" name="method" value="email" checked>
                                Email
                            </label>
                            <label>
                                <input type="radio" name="method" value="phone" id="verifyPhoneMethod">
                                Số Điện Thoại
                            </label>
                        </div>

                        <!-- Phần nhập Email -->
                        <div class="form-step active" id="verifyEmailStep">
                            <div class="input-div">
                                <div class="i">
                                    <i class="fas fa-envelope"></i>
                                </div>
                                <div class="input-container" style="position: relative;">
                                    <input class="input" type="email" name="email" id="verify_email" placeholder=" " required value="<?php echo htmlspecialchars($_SESSION['pending_verification']); ?>" readonly>
                                    <h5>Email</h5>
                                </div>
                            </div>
                        </div>

                        <!-- Phần nhập Số Điện Thoại (chưa hỗ trợ) -->
                        <div class="form-step" id="verifyPhoneStep">
                            <div class="input-div" style="position: relative;">
                                <div class="i">
                                    <i class="fas fa-phone"></i>
                                </div>
                                <div class="input-container" style="position: relative;">
                                    <input class="input" type="tel" name="phone" id="verify_phone" placeholder=" " pattern="[0-9]{10}" required disabled>
                                    <h5>Số Điện Thoại</h5>
                                    <div class="input-overlay" id="verifyPhoneOverlay" style="display: none;">
                                        <p>Tính năng này chưa được hỗ trợ.</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Phần nhập mã xác thực -->
                        <div class="input-div">
                            <div class="i">
                                <i class="fas fa-key"></i>
                            </div>
                            <div class="input-container">
                                <input class="input" type="text" name="token" id="token" placeholder=" " required>
                                <h5>Mã Xác Thực</h5>
                            </div>
                        </div>

                        <!-- Button Group -->
                        <div class="button-group">
                            <!-- Quay Lại Button -->
                            <button type="button" class="btn-back" onclick="resetToStep1()">Quay Lại</button>
                            <!-- Xác Thực Button -->
                            <input type="hidden" name="action" value="verify_code">
                            <input type="submit" class="btn-action" value="Xác Thực">
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <!-- Bước 3: Đổi mật khẩu -->
                    <h2>Đổi Mật Khẩu</h2>
                    <p>Nhập mật khẩu mới của bạn</p>

                    <!-- Phần nhập mật khẩu mới -->
                    <div class="input-div">
                        <div class="i">
                            <i class="fas fa-lock"></i>
                        </div>
                        <div class="input-container">
                            <input class="input" type="password" name="password" id="password" placeholder=" " required>
                            <h5>Mật Khẩu Mới</h5>
                            <i class="fas fa-eye toggle-password" toggle="#password"></i>
                        </div>
                    </div>

                    <!-- Phần xác nhận mật khẩu -->
                    <div class="input-div">
                        <div class="i">
                            <i class="fas fa-lock"></i>
                        </div>
                        <div class="input-container">
                            <input class="input" type="password" name="confirm_password" id="confirm_password" placeholder=" " required>
                            <h5>Xác Nhận Mật Khẩu</h5>
                            <i class="fas fa-eye toggle-password" toggle="#confirm_password"></i>
                        </div>
                    </div>

                    <input type="hidden" name="action" value="reset_password">
                    <input type="submit" class="btn-action" value="Đổi Mật Khẩu">

                    <div class="account">
                        <p>Quay lại?</p>
                        <a href="login.php">Đăng Nhập</a>
                    </div>
                <?php endif; ?>
            </form>
        </div>
        <div id="successModal" class="modal">
            <div class="modal-content">
                <span class="close-modal">&times;</span>
                <p>Bạn đã đổi mật khẩu thành công.</p>
                <button id="loginButton" class="btn-login">Đăng Nhập</button>
            </div>
        </div>
    </div>

    <!-- JavaScript để chuyển đổi giữa Email và Số Điện Thoại và quản lý modals -->
    <script>
        // Chuyển đổi giữa các phương thức gửi mã
        const methodRadios = document.getElementsByName('method');
        const emailStep = document.getElementById('emailStep');
        const phoneStep = document.getElementById('phoneStep');
        const phoneOverlay = document.getElementById('phoneOverlay');

        // Xử lý phương thức gửi mã
        methodRadios.forEach(radio => {
            radio.addEventListener('change', () => {
                if (radio.value === 'email') {
                    emailStep.classList.add('active');
                    phoneStep.classList.remove('active');
                    phoneStep.querySelector('input').disabled = true;
                    phoneOverlay.style.display = 'none';
                } else if (radio.value === 'phone') {
                    emailStep.classList.remove('active');
                    phoneStep.classList.add('active');
                    phoneStep.querySelector('input').disabled = true; // Disable input
                    phoneOverlay.style.display = 'flex'; // Show overlay with message
                }
            });
        });

        const togglePassword = document.querySelectorAll('.toggle-password');
        togglePassword.forEach(toggle => {
            toggle.addEventListener('click', () => {
                const input = document.querySelector(toggle.getAttribute('toggle'));
                if (input.type === 'password') {
                    input.type = 'text';
                    toggle.classList.remove('fa-eye');
                    toggle.classList.add('fa-eye-slash');
                } else {
                    input.type = 'password';
                    toggle.classList.remove('fa-eye-slash');
                    toggle.classList.add('fa-eye');
                }
            });
        });


        // Function to reset to Step 1
        function resetToStep1() {
            // Send a GET request to reset the process
            window.location.href = 'forgotPassword.php?action=reset_process';
        }
        <?php if (isset($_SESSION['password_reset_success'])): ?>
            // Unset the session variable
            <?php unset($_SESSION['password_reset_success']); ?>
            // Display the modal when the page loads
            document.addEventListener('DOMContentLoaded', function() {
                var successModal = document.getElementById('successModal');
                successModal.style.display = 'block';

                // Close the modal when the 'X' is clicked
                var closeModal = document.getElementsByClassName('close-modal')[0];
                closeModal.onclick = function() {
                    successModal.style.display = 'none';
                }

                // Close the modal when clicking outside of it
                window.onclick = function(event) {
                    if (event.target == successModal) {
                        successModal.style.display = 'none';
                    }
                }

                // Redirect to login page when 'Đăng Nhập' button is clicked
                var loginButton = document.getElementById('loginButton');
                loginButton.onclick = function() {
                    window.location.href = 'login.php';
                }
            });
        <?php endif; ?>
    </script>
</body>

</html>
