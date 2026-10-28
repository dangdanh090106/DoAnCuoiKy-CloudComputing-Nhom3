<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");

require_once __DIR__ . '/../MinioClient.php';
require_once __DIR__ . '/../db.php'; // Sử dụng file kết nối MySQL chung của bạn Băng

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["message" => "Phương thức request không được chấp nhận"]);
    exit();
}

// 1. Kiểm tra tham số đầu vào
if (!isset($_FILES['poster']) || !isset($_POST['event_id'])) {
    http_response_code(400); // Bad Request
    echo json_encode(["message" => "Dữ liệu không hợp lệ. Thiếu file poster hoặc Event_ID"]);
    exit();
}

$file = $_FILES['poster'];
$eventId = intval($_POST['event_id']);
$bucketName = 'event-posters';

// 2. Kiểm tra định dạng file (Chỉ nhận JPG, PNG, PDF theo đúng quy định đồ án)
$allowedTypes = ['image/jpeg', 'image/png', 'application/pdf'];
if (!in_array($file['type'], $allowedTypes)) {
    http_response_code(400);
    echo json_encode(["message" => "Định dạng file không hợp lệ. Hệ thống chỉ chấp nhận JPG, PNG hoặc PDF"]);
    exit();
}

// 3. Kiểm tra dung lượng file (Giới hạn tối đa 5MB)
$maxSize = 5 * 1024 * 1024;
if ($file['size'] > $maxSize) {
    http_response_code(400);
    echo json_encode(["message" => "Dung lượng file quá lớn. Vui lòng chọn file dưới 5MB"]);
    exit();
}

try {
    $s3Client = getMinioClient();

    // 4. Sinh Object Key định danh chống trùng theo cấu trúc: uploads/YYYY/MM/uuid_filename
    $year = date('Y');
    $month = date('m');
    $uuid = bin2hex(random_bytes(16)); // Tạo chuỗi định danh duy nhất (UUID tương đương)
    $originalName = basename($file['name']);
    $cleanFileName = preg_replace("/[^A-Za-z0-9\.\-_]/", '', $originalName); // Loại bỏ ký tự đặc biệt trong tên file
    $objectKey = "uploads/{$year}/{$month}/{$uuid}_{$cleanFileName}";

    // 5. Đẩy tệp tin lên hạ tầng lưu trữ MinIO
    $s3Client->putObject([
        'Bucket'     => $bucketName,
        'Key'        => $objectKey,
        'SourceFile' => $file['tmp_name'],
        'ContentType'=> $file['type']
    ]);

    // 6. Lưu bảng Metadata của file vào MySQL (Biến $conn lấy từ file db.php của Băng)
    $sql = "INSERT INTO FileMetadata (original_name, object_key, mime_type, file_size, upload_time, event_id) 
            VALUES (:original_name, :object_key, :mime_type, :file_size, NOW(), :event_id)";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':original_name' => $originalName,
        ':object_key'    => $objectKey,
        ':mime_type'     => $file['type'],
        ':file_size'     => $file['size'],
        ':event_id'      => $eventId
    ]);

    // Trả về mã HTTP 201 (Created) thành công cho Postman và Frontend
    http_response_code(201);
    echo json_encode([
        "message" => "Tải lên poster sự kiện thành công",
        "object_key" => $objectKey
    ]);

} catch (Exception $e) {
    http_response_code(500); // Internal Server Error
    echo json_encode(["message" => "Lỗi hệ thống khi xử lý file: " . $e->getMessage()]);
}
