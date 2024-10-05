<?php
session_start();

$servername = "localhost";
$db_username = "postgres"; // PostgreSQL username
$db_password = "!xNq!TRWY.AuD9U"; // PostgreSQL password
$dbname = "studentpickup"; // Database name

// Connect to PostgreSQL database
$conn = pg_connect("host=$servername dbname=$dbname user=$db_username password=$db_password");

// Check connection
if (!$conn) {
    die("Connection failed: " . pg_last_error());
}

// Handle POST request from login form
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    // Check if input fields are empty
    if (empty($username) || empty($password)) {
        echo "<script>
                alert('Please fill in all fields.');
                window.location.href = './login.html';
              </script>";
        exit();
    }

    // Fetch user info from the database
    $query = "SELECT * FROM public.user WHERE username = $1";
    $result = pg_query_params($conn, $query, array($username));

    if ($result === false) {
        echo "<script>
                alert('Error in query: " . pg_last_error($conn) . "');
                window.location.href = './login.html';
              </script>";
        pg_close($conn); // Close the connection before exiting
        exit();
    } else {
        if (pg_num_rows($result) > 0) {
            $user = pg_fetch_assoc($result);

            // Verify hashed password
            if (password_verify($password, $user['hashed_password'])) { // Use 'hashed_password' field
                // Login successful, set session variables
                $_SESSION['loggedin'] = true;
                $_SESSION['username'] = $user['username'];
                $_SESSION['userid'] = $user['id'];

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
                    <i class='fas fa-check-circle'></i> Successfully Logged In. Please wait...
                </div>
                <script>
                    setTimeout(function() {
                        window.location.href = './index.php';
                    }, 2000);
                </script>";
                pg_close($conn); // Close the connection before exiting
                exit();
            } else {
                // Wrong password
                echo "<script>
                        alert('Invalid credentials!');
                        window.location.href = './login.html';
                      </script>";
            }
        } else {
            // Username not found
            echo "<script>
                    alert('User not found!');
                    window.location.href = './login.html';
                  </script>";
        }
    }
}

// Close connection
pg_close($conn);
?>
