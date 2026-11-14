<?php
declare(strict_types=1);

/**
 * Helper cho đồ án: kết nối MinIO bằng AWS SDK for PHP.
 * Cài SDK: composer require aws/aws-sdk-php
 * Lưu file này ở thư mục gốc, cùng cấp api_su_kien.php.
 */

function eventMinioConfig(): array
{
    $endpoint = trim((string) (getenv('MINIO_ENDPOINT') ?: 'minio:9000'));
    if (!preg_match('~^https?://~i', $endpoint)) {
        // Cho phép cấu hình MINIO_ENDPOINT=minio và MINIO_PORT=9000.
        if (strpos($endpoint, ':') === false) {
            $endpoint .= ':' . (getenv('MINIO_PORT') ?: '9000');
        }
        $endpoint = 'http://' . $endpoint;
    }

    $bucket = getenv('MINIO_BUCKET') ?: getenv('MINIO_BUCKET_NAME') ?: 'event-posters';
    $accessKey = getenv('MINIO_ACCESS_KEY') ?: getenv('MINIO_ROOT_USER') ?: '';
    $secretKey = getenv('MINIO_SECRET_KEY') ?: getenv('MINIO_ROOT_PASSWORD') ?: '';

    if ($accessKey === '' || $secretKey === '') {
        throw new RuntimeException('Thiếu MINIO_ACCESS_KEY/MINIO_SECRET_KEY trong environment.');
    }

    return [
        'endpoint' => $endpoint,
        'bucket' => $bucket,
        'access_key' => $accessKey,
        'secret_key' => $secretKey,
    ];
}

function eventMinioClient(): \Aws\S3\S3Client
{
    $autoload = __DIR__ . '/vendor/autoload.php';
    if (!is_file($autoload)) {
        throw new RuntimeException('Chưa có vendor/autoload.php. Hãy cài aws/aws-sdk-php và đưa vendor vào Docker image.');
    }
    require_once $autoload;

    $config = eventMinioConfig();
    return new \Aws\S3\S3Client([
        'version' => 'latest',
        'region' => 'us-east-1',
        'endpoint' => $config['endpoint'],
        'use_path_style_endpoint' => true,
        'credentials' => [
            'key' => $config['access_key'],
            'secret' => $config['secret_key'],
        ],
    ]);
}

/**
 * Upload poster: trả về dữ liệu để lưu trong DB.
 * Gọi hàm sau khi đã xác thực quyền quản lý sự kiện.
 * Lưu ý: upload thành công nhưng INSERT DB thất bại thì phải xóa object vừa tạo.
 */
function eventUploadPoster(array $file): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new InvalidArgumentException('File upload không hợp lệ (mã lỗi ' . ($file['error'] ?? '?') . ').');
    }

    $tmpPath = (string) ($file['tmp_name'] ?? '');
    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0 || $size > 5 * 1024 * 1024) {
        throw new InvalidArgumentException('Ảnh phải lớn hơn 0 byte và không vượt quá 5 MB.');
    }
    if (!is_uploaded_file($tmpPath)) {
        throw new InvalidArgumentException('Tệp không phải file upload hợp lệ từ PHP.');
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmpPath);
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];
    if (!isset($allowed[$mime])) {
        throw new InvalidArgumentException('Chỉ chấp nhận JPG, PNG, WEBP hoặc GIF.');
    }

    $key = 'posters/' . gmdate('Y/m') . '/' . bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
    $config = eventMinioConfig();
    eventMinioClient()->putObject([
        'Bucket' => $config['bucket'],
        'Key' => $key,
        'SourceFile' => $tmpPath,
        'ContentType' => $mime,
    ]);

    return [
        'bucket' => $config['bucket'],
        'object_key' => $key,
        'original_name' => basename((string) ($file['name'] ?? 'poster')),
        'mime_type' => $mime,
        'file_size' => $size,
        'uploaded_at' => gmdate('Y-m-d H:i:s'),
    ];
}

function eventDeletePoster(string $key): void
{
    if (strpos($key, 'posters/') !== 0) {
        throw new InvalidArgumentException('Không được xóa object ngoài thư mục posters/.');
    }
    $config = eventMinioConfig();
    eventMinioClient()->deleteObject([
        'Bucket' => $config['bucket'],
        'Key' => $key,
    ]);
}
