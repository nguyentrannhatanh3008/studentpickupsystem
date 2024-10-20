<?php
// config.php

return [
    'database' => [
        'host' => 'localhost',
        'dbname' => 'studentpickup',
        'username' => 'postgres',
        'password' => '!xNq!TRWY.AuD9U'
    ],
    'mailer' => [
        'host' => 'smtp.gmail.com',
        'username' => 'hethongquanlyduadonhocsinh@gmail.com',
        'password' => 'cqsi cwtc oqxd buud', // Sử dụng mật khẩu ứng dụng (App Password)
        'encryption' => PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS,
        'port' => 587,
        'from_email' => 'hethongquanlyduadonhocsinh@gmail.com',
        'from_name' => 'Hệ Thống Quản Lý Đưa Đón Học Sinh'
    ]
];
?>
