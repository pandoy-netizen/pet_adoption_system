
<?php
// Railway MySQL Database Connection

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$host = getenv('MYSQLHOST') ?: getenv('MYSQL_HOST') ?: 'localhost';
$port = (int)(getenv('MYSQLPORT') ?: getenv('MYSQL_PORT') ?: 3306);
$user = getenv('MYSQLUSER') ?: getenv('MYSQL_USER') ?: 'root';
$password = getenv('MYSQLPASSWORD') ?: getenv('MYSQL_PASSWORD') ?: '';
$database = getenv('MYSQLDATABASE') ?: getenv('MYSQL_DATABASE') ?: 'pet_adoption_rescue';

try {
    $conn = new mysqli(
        $host,
        $user,
        $password,
        $database,
        $port
    );

    $conn->set_charset('utf8mb4');

} catch (mysqli_sql_exception $e) {
    error_log("Database connection error: " . $e->getMessage());

    http_response_code(500);
    exit("Database connection failed. Please check Railway MySQL variables.");
}

// Do not add a closing PHP tag.
