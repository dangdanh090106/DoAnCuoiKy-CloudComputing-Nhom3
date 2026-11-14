<?php
declare(strict_types=1);
/**
 * Proxy GET ảnh từ bucket MinIO PRIVATE theo ID sự kiện.
 * Link từ giao diện: poster_su_kien.php?ma_sukien=123
 * Không mở bucket ở chế độ public để hiển thị poster.
 */
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/minio_storage.php';

$id = filter_input(INPUT_GET, 'ma_sukien', FILTER_VALIDATE_INT);
if (!$id || $id <= 0) {
    http_response_code(400);
    exit('Mã sự kiện không hợp lệ.');
}
try {
    $db = $pdo ?? $conn ?? null;
    if (!$db instanceof PDO) throw new RuntimeException('Database unavailable');
    $stmt = $db->prepare('SELECT file_name, file_path_key, file_type FROM su_kien WHERE ma_sukien = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row || empty($row['file_path_key']) || !str_starts_with((string)$row['file_path_key'], 'posters/')) {
        http_response_code(404);
        exit('Poster chưa tồn tại trên MinIO.');
    }
    $mime = (string)($row['file_type'] ?? '');
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) {
        http_response_code(415);
        exit('Định dạng ảnh không được hỗ trợ.');
    }
    $config = eventMinioConfig();
    $object = eventMinioClient()->getObject([
        'Bucket' => $config['bucket'],
        'Key' => (string)$row['file_path_key'],
    ]);
    header('Content-Type: ' . $mime);
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: public, max-age=300');
    if (isset($object['ContentLength'])) header('Content-Length: ' . (int)$object['ContentLength']);
    echo $object['Body']->getContents();
} catch (Throwable $exception) {
    error_log('Cannot display MinIO poster: ' . $exception->getMessage());
    if (!headers_sent()) http_response_code(502);
    exit('Không thể tải poster từ MinIO.');
}
