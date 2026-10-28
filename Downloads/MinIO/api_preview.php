<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");

require_once __DIR__ . '/../MinioClient.php';
require_once __DIR__ . '/../db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header("Content-Type: application/json; charset=UTF-8");
    http_response_code(405);
    echo json_encode(["message" => "Phương thức request không được chấp nhận"]);
    exit();
}

if (!isset($_GET['event_id'])) {
    header("Content-Type: application/json; charset=UTF-8");
    http_response_code(400);
    echo json_encode(["message" => "Thiếu tham số dữ liệu Event_ID"]);
    exit();
}

$eventId = intval($_GET['event_id']);

try {
    // 1. Tìm thông tin metadata lưu trong MySQL
    $sql = "SELECT object_key, mime_type, original_name FROM FileMetadata WHERE event_id = :event_id LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':event_id' => $eventId]);
    $fileMeta = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$fileMeta) {
        header("Content-Type: application/json; charset=UTF-8");
        http_response_code(404); // Not Found
        echo json_encode(["message" => "Không tìm thấy poster hoặc hình ảnh cho sự kiện này"]);
        exit();
    }

    $s3Client = getMinioClient();
    $bucketName = 'event-posters';

    // 2. Thực hiện lấy stream file từ cụm lưu trữ MinIO
    $result = $s3Client->getObject([
        'Bucket' => $bucketName,
        'Key'    => $fileMeta['object_key']
    ]);

    // 3. Cấu hình Header trả luồng dữ liệu file về trực tiếp cho trình duyệt hiển thị công khai
    header("Content-Type: " . $fileMeta['mime_type']);
    
    // Nếu muốn khi gọi link này file tự động tải xuống máy thay vì xem trực tiếp, hãy bỏ comment dòng dưới:
    // header("Content-Disposition: attachment; filename=\"" . $fileMeta['original_name'] . "\"");

    echo $result['Body'];
    http_response_code(200); // OK

} catch (Exception $e) {
    header("Content-Type: application/json; charset=UTF-8");
    http_response_code(500);
    echo json_encode(["message" => "Lỗi kết nối hoặc không thể truy xuất tệp tin từ MinIO: " . $e->getMessage()]);
}
