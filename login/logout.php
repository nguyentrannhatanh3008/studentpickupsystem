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
    // Log the error and proceed with logout without clearing the token
    error_log("Connection failed: " . pg_last_error());
    // Proceed to destroy the session and redirect
}

// Clear the "Remember Me" cookie and remove token from the database
if (isset($_COOKIE['remember_token'])) {
    $token = $_COOKIE['remember_token'];

    if ($conn) {
        // Update the user record to remove the remember_token
        $update_query = "UPDATE public.user SET remember_token = NULL WHERE remember_token = $1";
        $result = pg_query_params($conn, $update_query, array($token));

        if ($result === false) {
            // Log the error
            error_log("Error updating remember_token: " . pg_last_error($conn));
        }
    }

    // Clear the cookie by setting its expiration time in the past
    setcookie('remember_token', '', time() - 3600, "/", "", isset($_SERVER["HTTPS"]), true);
}

// Destroy the session
session_unset();
session_destroy();

// Close the database connection if it was established
if ($conn) {
    pg_close($conn);
}

// Redirect to the homepage or login page
header("Location: index.php");
exit();
?>
