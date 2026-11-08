<?php
$host = 'localhost';
$dbname = 'do_an_cuoi_ky'; // Tên database chính xác của bạn
$username = 'root';
$password = '';

try {
    // Khởi tạo đối tượng PDO
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Gán đồng thời sang biến $conn để tương thích với tất cả các file khác
    $conn = $pdo;

} catch (PDOException $e) {
    die("Kết nối cơ sở dữ liệu thất bại: " . $e->getMessage());
}
?>