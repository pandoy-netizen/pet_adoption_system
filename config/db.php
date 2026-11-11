
<?php
// Railway MySQL connection
$host = getenv('MYSQLHOST') ?: 'localhost';
$user = getenv('MYSQLUSER') ?: 'root';
$password = getenv('MYSQLPASSWORD') ?: '';
$database = getenv('MYSQLDATABASE') ?: 'pet_adoption_rescue';
$port = (int)(getenv('MYSQLPORT') ?: 3306);

// Connect to MySQL
$conn = mysqli_connect(
    $host,
    $user,
    $password,
    $database,
    $port
);

// Check connection
if (!$conn) {
    die("Database connection failed.");
}

// Set character encoding
mysqli_set_charset($conn, "utf8mb4");
?>
