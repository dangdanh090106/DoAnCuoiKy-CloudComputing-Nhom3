<?php
$host = getenv('DB_HOST') ?: 'database'; // Trỏ về container database thay vì localhost
$port = getenv('DB_PORT') ?: '3306';
$dbname = getenv('DB_NAME') ?: 'do_an_cuoi_ky';
$username = getenv('DB_USER') ?: 'projectuser'; // Đổi mặc định thành projectuser hoặc root tùy ý
$password = getenv('DB_PASSWORD') ?: 'change_this_password'; // Gán mật khẩu mặc định khớp với file .env

try {
    // Khởi tạo đối tượng PDO với host cấu hình đúng cho Docker
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Gán đồng thời sang biến $conn để tương thích với tất cả các file khác
    $conn = $pdo;

} catch (PDOException $e) {
    die("Kết nối cơ sở dữ liệu thất bại: " . $e->getMessage());
}
?>