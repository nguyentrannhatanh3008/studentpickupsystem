<?php
// error.php
session_start();

// Lấy thông báo lỗi từ session nếu có
$error_message = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : "";

// Sau khi lấy thông báo, xóa nó khỏi session để tránh hiển thị lại
unset($_SESSION['error_message']);
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng Ký Thất Bại</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <script src="https://kit.fontawesome.com/a81368914c.js" crossorigin="anonymous"></script>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="icon" href="img/html-5.png">

    <!-- Embedded CSS Styles -->
    <style>
        /* Reset some default styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        /* Set the body background and font */
        body {
            font-family: 'Open Sans', sans-serif;
            background: #f0f4f8;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
            overflow: hidden;
        }

        /* Wave image styling */
        .wave {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            opacity: 0.1;
            z-index: -1;
        }

        /* Main container styling */
        .container {
            display: flex;
            flex-direction: row;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            max-width: 900px;
            width: 90%;
            margin: 20px;
        }

        /* Image section styling */
        .img {
            flex: 1;
            background: linear-gradient(135deg, #db4b23, #ff7e6a);
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 40px;
        }

        .img img {
            width: 100%;
            max-width: 300px;
            animation: float 6s ease-in-out infinite;
        }

        /* Confirmation section styling */
        .confirm-container {
            flex: 1;
            padding: 40px;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .content {
            text-align: center;
            width: 100%;
        }

        .error-banner {
            background-color: #f8d7da;
            color: #721c24;
            padding: 15px;
            border: 1px solid #f5c6cb;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 1.2em;
        }

        .content h2 {
            font-size: 2em;
            color: #333;
            margin-bottom: 20px;
        }

        .content i {
            font-size: 4em;
            color: #f44336;
            margin-bottom: 30px;
            animation: shake 1.5s infinite;
        }

        .btn-container {
            margin-top: 20px;
        }

        .btn-action {
            display: inline-block;
            padding: 12px 30px;
            background: linear-gradient(135deg, #db4b23, #ff7e6a);
            color: #fff;
            border: none;
            border-radius: 25px;
            text-decoration: none;
            font-size: 1em;
            transition: background 0.3s ease, transform 0.3s ease;
        }

        .btn-action:hover {
            background: linear-gradient(135deg, #db4b23, #ff7e6a);
            transform: translateY(-2px);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .container {
                flex-direction: column;
            }

            .img,
            .confirm-container {
                padding: 20px;
            }

            .img img {
                max-width: 200px;
            }

            .content h2 {
                font-size: 1.5em;
            }

            .content i {
                font-size: 3em;
            }

            .btn-action {
                padding: 10px 25px;
                font-size: 0.9em;
            }
        }

        /* Animations */
        @keyframes float {
            0% {
                transform: translatey(0px);
            }

            50% {
                transform: translatey(-20px);
            }

            100% {
                transform: translatey(0px);
            }
        }

        @keyframes shake {
            0%,
            100% {
                transform: rotate(0deg);
            }

            25% {
                transform: rotate(-5deg);
            }

            75% {
                transform: rotate(5deg);
            }
        }
        .error_message {
            padding: 10px;
            margin: 0 auto 15px auto; /* Center the margin */
            border-radius: 5px;
            width: 80%;
            max-width: 600px;
            position: fixed;
            top: 20px; /* Adjust as needed */
            left: 50%;
            transform: translateX(-50%);
            z-index: 1000; /* Ensure it stays on top */
            text-align: center;
        }

        .error_message {
            background-color: #f8d7da;
            color: #842029;
            border: 1px solid #f5c2c7;
        }

    </style>
</head>

<body>
    <img class="wave" src="img/wave.svg" alt="Wave Background">
    <div class="container">
        <div class="img">
            <img src="img/sad_face.svg" alt="Sad Face">
        </div>
        <div class="confirm-container">
            <div class="content">
                <?php if (!empty($error_message)): ?>
                    <div class="error-banner">
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>
                <h2>Không Thể Hoàn Tất Việc Đăng Ký!</h2>
                <i class="fas fa-exclamation-circle"></i>
                <div class="btn-container">
                    <a href="register.php" class="btn-action">Thử Lại</a>
                </div>
            </div>
        </div>
    </div>
</body>

</html>
