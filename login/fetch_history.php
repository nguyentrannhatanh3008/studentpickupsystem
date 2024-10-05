<?php
session_start();
$servername = "localhost";
$username = "postgres";
$password = "!xNq!TRWY.AuD9U";
$dbname = "studentpickup";

// Connect to PostgreSQL database
$conn = pg_connect("host=$servername dbname=$dbname user=$username password=$password");

if (!$conn) {
    die("Connection failed: " . pg_last_error());
}

$userid = $_SESSION['userid'];

// Query to fetch pickup history for the logged-in user
$historyQuery = "SELECT p.id, s.name AS student_name, s.class, p.created_at, p.status 
                 FROM public.pickup_history p
                 JOIN public.student s ON p.student_id = s.id
                 WHERE p.user_id = $1
                 ORDER BY p.created_at DESC";
$historyResult = pg_query_params($conn, $historyQuery, array($userid));

if ($historyResult) {
    // Output each row as a table row
    while ($row = pg_fetch_assoc($historyResult)) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['student_name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['class']) . "</td>";
        echo "<td>" . htmlspecialchars($row['created_at']) . "</td>";
        echo "<td>" . htmlspecialchars($row['status']) . "</td>";
        echo "</tr>";
    }
} else {
    // Log error
    error_log("Failed to fetch pickup history: " . pg_last_error($conn));
    echo "<tr><td colspan='4' class='no-history-message'>No history found.</td></tr>";
}

pg_close($conn);
?>
