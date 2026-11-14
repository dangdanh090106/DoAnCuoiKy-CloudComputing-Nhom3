<?php

$host = getenv('DB_HOST') ?: 'database';
$port = getenv('DB_PORT') ?: '3306';
$dbname = getenv('DB_NAME') ?: 'do_an_cuoi_ky';
$username = getenv('DB_USER') ?: 'projectuser';
$password = getenv('DB_PASSWORD') ?: '';

try {
    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );


    $conn = $pdo;

} catch (PDOException $e) {

    error_log("Database connection failed: " . $e->getMessage());

    http_response_code(500);
    exit("Không thể kết nối cơ sở dữ liệu. Vui lòng thử lại sau.");
}
