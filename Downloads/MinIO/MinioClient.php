<?php
require_once __DIR__ . '/vendor/autoload.php';

use Aws\S3\S3Client;

function getMinioClient() {
    // Đọc cấu hình từ biến môi trường .env (Hệ thống Docker sẽ truyền các biến này vào)
    $endpoint = $_ENV['MINIO_ENDPOINT'] ?? 'http://localhost:9000';
    $accessKey = $_ENV['MINIO_ACCESS_KEY'] ?? 'minioadmin';
    $secretKey = $_ENV['MINIO_SECRET_KEY'] ?? 'minioadmin';

    return new S3Client([
        'version'     => 'latest',
        'region'      => 'us-east-1', // MinIO mặc định bắt buộc trường này
        'endpoint'    => $endpoint,
        'use_path_style_endpoint' => true, // Bắt buộc đối với cấu hình MinIO
        'credentials' => [
            'key'    => $accessKey,
            'secret' => $secretKey,
        ],
    ]);
}
