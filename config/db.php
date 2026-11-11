
<?php
// Railway MySQL Database Connection

$host = getenv('MYSQLHOST');
$user = getenv('MYSQLUSER');
$password = getenv('MYSQLPASSWORD');
$database = getenv('MYSQLDATABASE');
$port = (int) (getenv('MYSQLPORT') ?: 3306);

// Check required environment variables
if (!$host || !$user || !$password || !$database) {
    error_log("Missing Railway MySQL environment variables.");
    die("Database configuration error.");
}

// Connect to Railway MySQL
try {
    $conn = new mysqli(
        $host,
        $user,
        $password,
        $database,
        $port
    );

    // Set character encoding
    $conn->set_charset("utf8mb4");

} catch (mysqli_sql_exception $e) {
    error_log("MySQL connection error: " . $e->getMessage());
    die("Database connection failed. Check Railway MySQL settings.");
}
?>
