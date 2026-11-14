<?php
// API điểm danh: dùng bảng dang_ky (bảng đăng ký đang được sử dụng thực tế).
// Giữ nguyên định dạng phản hồi JSON để tương thích JavaScript của dashboard.
header('Content-Type: application/json; charset=UTF-8');
session_start();
// Thống nhất múi giờ hiển thị/hết hạn passcode với trang Admin.
date_default_timezone_set('Asia/Ho_Chi_Minh');
require_once __DIR__ . '/database.php';

$db = $conn ?? $pdo ?? null;

function checkinResponse(string $status, string $message, int $code = 200): void
{
    http_response_code($code);
    echo json_encode([
        'status' => $status,
        'message' => $message,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!($db instanceof PDO)) {
    checkinResponse('error', 'Không tìm thấy kết nối cơ sở dữ liệu.', 500);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    checkinResponse('error', 'Phương thức yêu cầu không hợp lệ.', 405);
}

$maSv = trim((string) ($_SESSION['ma_sv'] ?? ''));
if ($maSv === '') {
    checkinResponse('error', 'Vui lòng đăng nhập tài khoản sinh viên để điểm danh.', 401);
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    checkinResponse('error', 'Dữ liệu điểm danh không hợp lệ.', 400);
}

$eventId = filter_var(
    $payload['event_id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);
$rawPasscode = $payload['passcode'] ?? null;
if (!$eventId || !(is_string($rawPasscode) || is_int($rawPasscode))) {
    checkinResponse('error', 'Vui lòng chọn sự kiện và nhập mã Passcode.', 400);
}
$inputPasscode = trim((string) $rawPasscode);
if (!preg_match('/^[0-9]{6}$/D', $inputPasscode)) {
    checkinResponse('error', 'Passcode phải gồm đúng 6 chữ số.', 400);
}

try {
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->beginTransaction();

    // Khóa tài khoản sinh viên trong giao dịch để hai yêu cầu đồng thời
    // không được cộng điểm hai lần.
    $stmtUser = $db->prepare('SELECT id, vai_tro FROM users WHERE ma_sv = ? FOR UPDATE');
    $stmtUser->execute([$maSv]);
    $user = $stmtUser->fetch(PDO::FETCH_ASSOC);
    if (!$user || $user['vai_tro'] !== 'sinh_vien') {
        $db->rollBack();
        checkinResponse('error', 'Không tìm thấy tài khoản sinh viên hợp lệ.', 403);
    }
    $userId = (int) $user['id'];

    // su_kien có cột ma_sukien, không có su_kien_id.
    $stmtEvent = $db->prepare('SELECT ma_sukien, is_attendance_open, passcode, passcode_expired_at FROM su_kien WHERE ma_sukien = ? FOR UPDATE');
    $stmtEvent->execute([$eventId]);
    $event = $stmtEvent->fetch(PDO::FETCH_ASSOC);
    if (!$event || (int) $event['is_attendance_open'] !== 1) {
        $db->rollBack();
        checkinResponse('error', 'Sự kiện chưa mở điểm danh hoặc đã kết thúc.');
    }

    // Giữ thống nhất với logic thời gian PHP của phần giao diện.
    // Nếu chưa có hạn sử dụng, tuyệt đối không coi mã là vô thời hạn.
    $expiresAt = !empty($event['passcode_expired_at'])
        ? strtotime($event['passcode_expired_at'])
        : false;
    if ($expiresAt === false || $expiresAt <= time()) {
        $db->rollBack();
        checkinResponse('error', 'Mã Passcode đã hết hạn! Vui lòng liên hệ Ban tổ chức.');
    }

    if (empty($event['passcode']) ||
        !hash_equals(trim((string) $event['passcode']), $inputPasscode)) {
        $db->rollBack();
        checkinResponse('error', 'Mã Passcode không chính xác.');
    }

    // Không tự tạo đăng ký mới. Chỉ sinh viên đã có bản ghi đăng ký
    // trong bảng dang_ky mới được phép điểm danh.
    $stmtReg = $db->prepare('
        SELECT ma_dangky, trang_thai
        FROM dang_ky
        WHERE ma_sukien = ? AND ma_sv = ?
        ORDER BY ma_dangky ASC
        FOR UPDATE
    ');
    $stmtReg->execute([$eventId, $maSv]);
    $registrations = $stmtReg->fetchAll(PDO::FETCH_ASSOC);
    if (!$registrations) {
        $db->rollBack();
        checkinResponse('error', 'Bạn chưa đăng ký tham gia sự kiện này.');
    }

    $registeredRecord = null;
    foreach ($registrations as $registration) {
        $status = trim((string) ($registration['trang_thai'] ?? ''));
        $normalized = strtolower($status);
        if (in_array($normalized, ['da_tham_gia', 'present'], true)
            || $status === 'Đã tham gia') {
            $db->rollBack();
            checkinResponse('warning', 'Bạn đã điểm danh sự kiện này rồi!');
        }
        if ($registeredRecord === null &&
            (in_array($normalized, ['da_dang_ky', 'registered', 'confirmed'], true)
                || $status === 'Đã đăng ký' || $status === 'đã đăng ký')) {
            $registeredRecord = $registration;
        }
    }

    if ($registeredRecord === null) {
        $db->rollBack();
        checkinResponse('error', 'Đăng ký của bạn không ở trạng thái hợp lệ để điểm danh.');
    }

    // Chuyển đúng một bản ghi đã đăng ký sang trạng thái đã tham gia.
    // Ghi thời điểm điểm danh theo múi giờ Việt Nam sau khi xác thực passcode.
    // Cần chạy migration để có cả thoi_gian_diem_danh và diem_cong trước khi chạy API.
    $checkinAt = date('Y-m-d H:i:s');
    $diemThuong = 5;
    $stmtUpdate = $db->prepare('
        UPDATE dang_ky
        SET trang_thai = ?, thoi_gian_diem_danh = ?, diem_cong = ?
        WHERE ma_dangky = ? AND trang_thai = ?
    ');
    $stmtUpdate->execute([
        'da_tham_gia',
        $checkinAt,
        $diemThuong,
        $registeredRecord['ma_dangky'],
        $registeredRecord['trang_thai'],
    ]);
    if ($stmtUpdate->rowCount() !== 1) {
        throw new RuntimeException('Không thể cập nhật trạng thái điểm danh.');
    }

    // Bảng su_kien không có diem_thuong, nên giữ mức cộng mặc định 5 điểm.
    $stmtPoints = $db->prepare('
        UPDATE users
        SET diem_ren_luyen = COALESCE(diem_ren_luyen, 0) + ?
        WHERE id = ?
    ');
    $stmtPoints->execute([$diemThuong, $userId]);
    if ($stmtPoints->rowCount() !== 1) {
        throw new RuntimeException('Không thể cập nhật điểm rèn luyện.');
    }

    // Trạng thái điểm danh và điểm rèn luyện được lưu cùng một giao dịch.
    $db->commit();
    checkinResponse('success', 'Điểm danh thành công và được cộng 5 điểm rèn luyện!');
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Check-in failed: ' . $e->getMessage());
    checkinResponse('error', 'Lỗi hệ thống điểm danh. Vui lòng kiểm tra log PHP/Docker.', 500);
}
