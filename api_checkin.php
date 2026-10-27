<?php
header("Content-Type: application/json; charset=UTF-8");
session_start();
require_once 'database.php';

// Sử dụng $conn hoặc $pdo tùy theo cấu hình file database.php của bạn
$db = $conn ?? $pdo ?? null;

$ma_sv = $_SESSION['ma_sv'] ?? null;
if (!$ma_sv) {
    echo json_encode(["status" => "error", "message" => "Vui lòng đăng nhập tài khoản sinh viên để điểm danh."]);
    exit;
}

$data = json_decode(file_get_contents("php://input"));
$eventId = $data->event_id ?? null;
$inputPasscode = trim($data->passcode ?? '');

if (!$eventId || !$inputPasscode) {
    echo json_encode(["status" => "error", "message" => "Vui lòng nhập đầy đủ mã Passcode."]);
    exit;
}

try {
    // 1. Lấy user_id và thông tin sinh viên từ mã sinh viên
    $stmtUser = $db->prepare("SELECT id FROM users WHERE ma_sv = ?");
    $stmtUser->execute([$ma_sv]);
    $user = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(["status" => "error", "message" => "Không tìm thấy thông tin tài khoản sinh viên."]);
        exit;
    }
    $userId = $user['id'];

    // 2. Kiểm tra thông tin sự kiện (Hỗ trợ quét cả ma_sukien lẫn id)
    $stmt = $db->prepare("SELECT * FROM su_kien WHERE ma_sukien = ? OR id = ?");
    $stmt->execute([$eventId, $eventId]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$event || ($event['is_attendance_open'] ?? 0) != 1) {
        echo json_encode(["status" => "error", "message" => "Sự kiện chưa mở điểm danh hoặc đã kết thúc."]);
        exit;
    }

    // Kiểm tra thời gian hết hạn passcode (nếu cột có tồn tại)
    if (!empty($event['passcode_expired_at']) && strtotime($event['passcode_expired_at']) < time()) {
        echo json_encode(["status" => "error", "message" => "Mã Passcode đã hết hạn! Vui lòng liên hệ Ban tổ chức."]);
        exit;
    }

    // Kiểm tra tính chính xác của Passcode
    if (trim($event['passcode'] ?? '') !== $inputPasscode) {
        echo json_encode(["status" => "error", "message" => "Mã Passcode không chính xác."]);
        exit;
    }

    $targetEventId = $event['ma_sukien'] ?? $eventId;
    $diemThuong = $event['diem_thuong'] ?? 5; // Mặc định cộng 5 điểm nếu sự kiện không cấu hình

    // 3. Kiểm tra xem sinh viên đã có bản ghi trong bảng dang_ky_su_kien chưa
    $stmtReg = $db->prepare("SELECT * FROM dang_ky_su_kien WHERE su_kien_id = ? AND user_id = ?");
    $stmtReg->execute([$targetEventId, $userId]);
    $registration = $stmtReg->fetch(PDO::FETCH_ASSOC);

    // Nếu đã có bản ghi và trạng thái là đã tham gia rồi thì chặn
    if ($registration) {
        $currentStatus = $registration['trang_thai'] ?? '';
        if ($currentStatus === 'da_tham_gia' || $currentStatus === 'present') {
            echo json_encode(["status" => "warning", "message" => "Bạn đã điểm danh sự kiện này rồi!"]);
            exit;
        }
    }

    // Bắt đầu Transaction để đảm bảo tính toàn vẹn dữ liệu
    $db->beginTransaction();

    if ($registration) {
        // Đã có bản ghi -> Cập nhật trạng thái thành da_tham_gia
        $updateStmt = $db->prepare("UPDATE dang_ky_su_kien SET trang_thai = 'da_tham_gia' WHERE id = ?");
        $updateStmt->execute([$registration['id']]);
    } else {
        // Chưa có bản ghi -> Tự động chèn mới với trạng thái da_tham_gia
        $insertStmt = $db->prepare("INSERT INTO dang_ky_su_kien (user_id, su_kien_id, trang_thai) VALUES (?, ?, 'da_tham_gia')");
        $insertStmt->execute([$userId, $targetEventId]);
    }

    // 4. Tự động cộng điểm rèn luyện cho tài khoản sinh viên
    $updateDiem = $db->prepare("
        UPDATE users 
        SET diem_ren_luyen = diem_ren_luyen + ? 
        WHERE id = ?
    ");
    $updateDiem->execute([$diemThuong, $userId]);

    $db->commit();

    echo json_encode([
        "status" => "success", 
        "message" => "Điểm danh thành công và được cộng " . $diemThuong . " điểm rèn luyện!"
    ]);

} catch (PDOException $e) {
    if ($db && $db->inTransaction()) {
        $db->rollBack();
    }
    echo json_encode(["status" => "error", "message" => "Lỗi hệ thống cơ sở dữ liệu: " . $e->getMessage()]);
}
?>