<?php
// Database connection parameters
$host = "localhost";
$user = "postgres"; // Tên người dùng PostgreSQL của bạn
$pass = "!xNq!TRWY.AuD9U"; // Mật khẩu PostgreSQL của bạn
$dbname = "studentpickup"; // Tên cơ sở dữ liệu của bạn

// Establishing a connection
$conn = pg_connect("host=$host dbname=$dbname user=$user password=$pass");

if (!$conn) {
    echo "Error: Unable to connect to the database.";
    exit;
}

// Retrieve notifications from the database
$query = "SELECT id, title, recipient, status, timestamp FROM notifications";  // Adjust table and field names as needed
$result = pg_query($conn, $query);

if (!$result) {
    echo "Error: Query failed.";
    exit;
}

// Prepare data for DataTables
$data = [];
while ($row = pg_fetch_assoc($result)) {
    $data[] = $row;
}

// Output JSON for DataTables
echo json_encode([
    "data" => $data
]);

// Close the connection
pg_close($conn);
?>
