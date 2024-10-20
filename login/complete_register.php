<?php
// complete_register.php
session_start();

// Kiểm tra bước hiện tại
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['step']) && $_POST['step'] == '3') {
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
        header("Location: register.php?step=3");
        exit();
    }

    // Lấy dữ liệu từ form
    $address = isset($_POST['address']) ? trim($_POST['address']) : '';
    $date_of_birth = isset($_POST['date_of_birth']) ? $_POST['date_of_birth'] : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
    $role = isset($_POST['role']) ? $_POST['role'] : '';
    $school_code = isset($_POST['school_code']) ? trim($_POST['school_code']) : null;
    $gender = isset($_POST['gender']) ? $_POST['gender'] : '';
    $terms = isset($_POST['terms']) ? true : false;

    $error_message = '';

    // Kiểm tra mật khẩu khớp
    if ($password !== $confirm_password) {
        $error_message .= "Mật khẩu không khớp.<br>";
    }

    // Kiểm tra mật khẩu hợp lệ: ít nhất 8 ký tự và không chứa ký tự đặc biệt
    if (!preg_match('/^[A-Za-z0-9]{8,}$/', $password)) {
        $error_message .= "Mật khẩu phải có ít nhất 8 ký tự và không chứa ký tự đặc biệt.<br>";
    }

    // Kiểm tra xem vai trò có yêu cầu mã trường hay không
    if (($role === 'QuanLyNhaTruong' || $role === 'GiaoVien') && empty($school_code)) {
        $error_message .= "Vui lòng nhập Mã Trường.<br>";
    }

    // Nếu vai trò yêu cầu mã trường, kiểm tra xem mã trường có tồn tại trong bảng schools không
    if (($role === 'QuanLyNhaTruong' || $role === 'GiaoVien') && !empty($school_code)) {
        $check_school_query = "SELECT 1 FROM public.schools WHERE school_code = $1 LIMIT 1";
        $school_result = pg_query_params($conn, $check_school_query, array($school_code));
        if (!$school_result || pg_num_rows($school_result) == 0) {
            $error_message .= "Mã Trường không tồn tại.<br>";
        }
    }

    // Kiểm tra điều khoản
    if (!$terms) {
        $error_message .= "Bạn phải đồng ý với điều khoản sử dụng.<br>";
    }

    // Nếu có lỗi, chuyển hướng lại form với thông báo lỗi
    if (!empty($error_message)) {
        $_SESSION['error_message'] = $error_message;
        header("Location: register.php?step=3");
        pg_close($conn);
        exit();
    }

    // Mã hóa mật khẩu
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Cập nhật thông tin chi tiết và mật khẩu vào cơ sở dữ liệu
    $update_query = "UPDATE public.user SET address = $1, date_of_birth = $2, hashed_password = $3, gender = $4, role = $5, school_code = $6 WHERE email = $7";
    $update_result = pg_query_params($conn, $update_query, array(
        $address,
        $date_of_birth,
        $hashed_password,
        $gender,
        $role,
        $school_code,
        $_SESSION['email']
    ));

    if ($update_result) {
        // Đăng ký thành công, xóa thông tin session và chuyển hướng đến trang thành công
        unset($_SESSION['email']);
        unset($_SESSION['user_id']);
        header("Location: success.html");
        pg_close($conn);
        exit();
    } else {
        $_SESSION['error_message'] = "Đã xảy ra lỗi khi hoàn tất đăng ký: " . pg_last_error($conn);
        header("Location: register.php?step=3");
        pg_close($conn);
        exit();
    }
}
?>
