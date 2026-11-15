
<?php
$host = getenv('MYSQLHOST') ?: getenv('MYSQL_HOST');
$user = getenv('MYSQLUSER') ?: getenv('MYSQL_USER');

$password = getenv('MYSQLPASSWORD');
if ($password === false) {
    $password = getenv('MYSQL_PASSWORD');
}

$database = getenv('MYSQLDATABASE') ?: getenv('MYSQL_DATABASE');
$port = getenv('MYSQLPORT') ?: getenv('MYSQL_PORT') ?: 3306;

if (
    !$host ||
    !$user ||
    $password === false ||
    !$database
) {
    error_log("Railway MySQL environment variables are missing.");
    http_response_code(500);
    die("Database configuration error. Please check Railway Variables.");
}

try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    $conn = mysqli_connect(
        $host,
        $user,
        $password,
        $database,
        (int)$port
    );

    mysqli_set_charset($conn, "utf8mb4");

} catch (mysqli_sql_exception $e) {
    error_log("MySQL Connection Error: " . $e->getMessage());
    http_response_code(500);
    die("Database connection failed. Please check Railway MySQL settings.");
}
?>
