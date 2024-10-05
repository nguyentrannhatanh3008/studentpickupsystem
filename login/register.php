<?php
session_start();
$servername = "localhost";
$username = "postgres"; // PostgreSQL username
$password = "!xNq!TRWY.AuD9U"; // PostgreSQL password
$dbname = "studentpickup"; // Database name

// Connect to PostgreSQL
$conn = pg_connect("host=$servername dbname=$dbname user=$username password=$password");

// Check connection
if (!$conn) {
    die("Connection failed: " . pg_last_error());
}

// Handle POST request from the registration form
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $gender = $_POST['gender'];
    $phone = $_POST['phone'];
    $name = $_POST['name'];
    $address = $_POST['address'];
    $date_of_birth = $_POST['date_of_birth'];

    // Check if password and confirm password match
    if ($password !== $confirm_password) {
        echo "<script>alert('Passwords do not match!'); window.location.href = 'register.html';</script>";
        exit();
    }

    // Hash the password for security
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Check if user already exists
    $query = "SELECT * FROM public.user WHERE username = $1 OR email = $2 OR phone = $3";
    $result = pg_query_params($conn, $query, array($username, $email, $phone));

    if ($result === false) {
        echo "Error in query: " . pg_last_error($conn);
    } else {
        if (pg_num_rows($result) > 0) {
            echo "<script>alert('Username, email or phone already exists!'); window.location.href = 'register.html';</script>";
        } else {
            // Insert new user into the database
            $query = "INSERT INTO public.user (username, email, hashed_password, gender, phone, name, address, date_of_birth) VALUES ($1, $2, $3, $4, $5, $6, $7, $8)";
            $result = pg_query_params($conn, $query, array($username, $email, $hashed_password, $gender, $phone, $name, $address, $date_of_birth));

            if ($result) {
                echo "<script>alert('Registration successful! Please login.'); window.location.href = 'login.html';</script>";
            } else {
                echo "Error in insertion: " . pg_last_error($conn);
            }
        }
    }
}

// Close the connection
pg_close($conn);
?>
