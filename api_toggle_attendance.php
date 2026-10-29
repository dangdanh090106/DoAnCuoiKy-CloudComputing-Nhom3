<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/database.php';

if (!isset($pdo) && isset($conn)) {
    $pdo = $conn;
}

$data = json_decode(file_get_contents('php://input'), true);
$eventId = $data['event_id'] ?? null;
$action = $data['action'] ?? null;

if (!$eventId || !$action) {
    echo json_encode(['status' => 'error', 'message' => 'Thiếu thông tin sự kiện hoặc hành động.']);
    exit;
}

// Tự động lấy danh sách tất cả các cột trong bảng su_kien để quét tìm khóa chính chính xác
$columns = [];
try {
    $stmtCol = $pdo->query("SHOW COLUMNS FROM su_kien");
    while ($row = $stmtCol->fetch(PDO::FETCH_ASSOC)) {
        $columns[] = $row['Field'];
    }
} catch (Exception $e) {
    // Dự phòng nếu không truy vấn được cấu trúc bảng
    $columns = ['id', 'ma_su_kien', 'id_su_kien', 'su_kien_id'];
}

$passcode = rand(100000, 999999); // Tạo mã Passcode 6 số ngẫu nhiên
$expiredAt = date('Y-m-d H:i:s', strtotime('+5 minutes')); // Hết hạn sau 5 phút
$updated = false;

if ($action === 'open_attendance') {
    // Thử cập nhật qua từng cột tồn tại trong bảng
    foreach ($columns as $col) {
        try {
            $stmt = $pdo->prepare("UPDATE su_kien SET is_attendance_open = 1, passcode = ?, passcode_expired_at = ? WHERE $col = ?");
            $stmt->execute([$passcode, $expiredAt, $eventId]);
            if ($stmt->rowCount() > 0) {
                $updated = true;
                break;
            }
        } catch (PDOException $e) {}
    }

    // Nếu không thay đổi rowCount (do trạng thái trước đó giống hệt), kiểm tra xem dòng có tồn tại không để ép cập nhật
    if (!$updated) {
        foreach ($columns as $col) {
            try {
                $check = $pdo->prepare("SELECT * FROM su_kien WHERE $col = ?");
                $check->execute([$eventId]);
                if ($check->fetch()) {
                    $stmt = $pdo->prepare("UPDATE su_kien SET is_attendance_open = 1, passcode = ?, passcode_expired_at = ? WHERE $col = ?");
                    $stmt->execute([$passcode, $expiredAt, $eventId]);
                    $updated = true;
                    break;
                }
            } catch (Exception $e) {}
        }
    }

    if ($updated) {
        echo json_encode([
            'status' => 'success', 
            'message' => 'Đã mở điểm danh thành công trong 5 phút!',
            'passcode' => $passcode
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => "Không tìm thấy sự kiện với ID [$eventId] trong CSDL."]);
    }
} 
elseif ($action === 'close_attendance') {
    foreach ($columns as $col) {
        try {
            $stmt = $pdo->prepare("UPDATE su_kien SET is_attendance_open = 0, passcode = NULL WHERE $col = ?");
            $stmt->execute([$eventId]);
            if ($stmt->rowCount() > 0) {
                $updated = true;
                break;
            }
        } catch (PDOException $e) {}
    }

    if (!$updated) {
        foreach ($columns as $col) {
            try {
                $check = $pdo->prepare("SELECT * FROM su_kien WHERE $col = ?");
                $check->execute([$eventId]);
                if ($check->fetch()) {
                    $stmt = $pdo->prepare("UPDATE su_kien SET is_attendance_open = 0, passcode = NULL WHERE $col = ?");
                    $stmt->execute([$eventId]);
                    $updated = true;
                    break;
                }
            } catch (Exception $e) {}
        }
    }

    if ($updated) {
        echo json_encode([
            'status' => 'success', 
            'message' => 'Đã đóng điểm danh sự kiện thành công.'
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => "Không tìm thấy sự kiện với ID [$eventId] để đóng."]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Hành động không hợp lệ.']);
}
?>