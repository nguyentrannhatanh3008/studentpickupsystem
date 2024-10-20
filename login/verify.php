<?php
// verify.php
session_start();

// Kiểm tra bước hiện tại
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['step']) && $_POST['step'] == '2') {
    // Kết nối cơ sở dữ liệu PostgreSQL
    $servername = "localhost";
    $db_username = "postgres"; // PostgreSQL username
    $db_password = "!xNq!TRWY.AuD9U"; // PostgreSQL password
    $dbname = "studentpickup"; // Database name

    // Tạo chuỗi kết nối
    $conn_string = "host=$servername dbname=$dbname user=$db_username password=$db_password";

    // Kết nối đến PostgreSQL
    $conn = pg_connect($conn_string);

    // Kiểm tra kết nối
    if (!$conn) {
        $_SESSION['error_message'] = "Kết nối cơ sở dữ liệu thất bại.";
        header("Location: register.php?step=2");
        exit();
    }

    // Lấy dữ liệu từ form
    $verification_code = isset($_POST['verification_code']) ? trim($_POST['verification_code']) : '';
    $email = isset($_SESSION['email']) ? $_SESSION['email'] : '';

    $error_message = '';

    // Kiểm tra xem email có trong session không
    if (empty($email)) {
        $error_message .= "Không tìm thấy thông tin email. Vui lòng đăng ký lại.<br>";
    }

    // Kiểm tra mã xác thực
    if (empty($verification_code)) {
        $error_message .= "Vui lòng nhập mã xác thực.<br>";
    }

    if (!empty($error_message)) {
        $_SESSION['error_message'] = $error_message;
        header("Location: register.php?step=2");
        pg_close($conn);
        exit();
    }

    // Kiểm tra mã xác thực trong cơ sở dữ liệu
    $check_code_query = "SELECT id, is_verified FROM public.user WHERE email = $1 AND verification_code = $2";
    $result = pg_query_params($conn, $check_code_query, array($email, $verification_code));

    if ($result && pg_num_rows($result) > 0) {
        $user = pg_fetch_assoc($result);
        if ($user['is_verified']) {
            $_SESSION['error_message'] = "Tài khoản đã được xác thực.";
            header("Location: sucess.html");
            pg_close($conn);
            exit();
        }

        // Cập nhật trạng thái xác thực
        $update_query = "UPDATE public.user SET is_verified = TRUE, verification_code = NULL WHERE id = $1";
        $update_result = pg_query_params($conn, $update_query, array($user['id']));

        if ($update_result) {
            // Đánh dấu đã xác thực
            $_SESSION['email_verified'] = true;
            header("Location: register.php?step=3");
            pg_close($conn);
            exit();
        } else {
            $_SESSION['error_message'] = "Đã xảy ra lỗi khi xác thực tài khoản.";
            header("Location: register.php?step=2");
            pg_close($conn);
            exit();
        }
    } else {
        $_SESSION['error_message'] = "Mã xác thực không đúng. Vui lòng thử lại.";
        header("Location: register.php?step=2");
        pg_close($conn);
        exit();
    }
}
?>
