<?php
// Dùng cùng cơ chế kiểm tra quyền Admin với admin/dashboard.php.
require_once __DIR__ . '/check_admin.php';
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
// Đồng bộ múi giờ tạo passcode, kiểm tra thời hạn và thời điểm điểm danh.
date_default_timezone_set('Asia/Ho_Chi_Minh');
require_once __DIR__ . '/../database.php';

// database.php của nhóm có thể cung cấp $pdo hoặc $conn.
$pdo = $pdo ?? $conn ?? $db ?? null;
if (!($pdo instanceof PDO)) {
    http_response_code(500);
    exit('Không tìm thấy kết nối cơ sở dữ liệu.');
}

$eventId = filter_var(
    $_GET['id'] ?? $_GET['event_id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);
if ($eventId === false || $eventId === null) {
    http_response_code(400);
    exit('Thiếu hoặc sai mã sự kiện.');
}

function loadAttendanceEvent(PDO $pdo, int $eventId): ?array
{
    // su_kien dùng ma_sukien; địa điểm liên kết qua ma_diadiem.
    $stmt = $pdo->prepare('
        SELECT sk.*, dd.ten_diadiem AS ten_dia_diem
        FROM su_kien AS sk
        LEFT JOIN dia_diem AS dd ON dd.ma_diadiem = sk.ma_diadiem
        WHERE sk.ma_sukien = ?
        LIMIT 1
    ');
    $stmt->execute([$eventId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

try {
    $event = loadAttendanceEvent($pdo, (int) $eventId);
} catch (PDOException $e) {
    error_log('Attendance event query failed: ' . $e->getMessage());
    http_response_code(500);
    exit('Không thể tải thông tin sự kiện. Kiểm tra log PHP/Docker.');
}
if (!$event) {
    http_response_code(404);
    exit('Không tìm thấy thông tin sự kiện trong cơ sở dữ liệu.');
}

$message = '';
$message_type = '';

// Chặn yêu cầu POST từ trang khác, không ảnh hưởng cách hiển thị cũ.
if (empty($_SESSION['attendance_admin_csrf'])) {
    $_SESSION['attendance_admin_csrf'] = bin2hex(random_bytes(32));
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['btn_toggle_attendance'])) {
    $submittedToken = (string) ($_POST['csrf_token'] ?? '');
    $action = (string) ($_POST['action'] ?? '');

    if (!hash_equals($_SESSION['attendance_admin_csrf'], $submittedToken)) {
        http_response_code(403);
        $message = 'Phiên xác thực không hợp lệ. Vui lòng tải lại trang và thử lại.';
        $message_type = 'danger';
    } elseif (!in_array($action, ['open', 'close'], true)) {
        $message = 'Hành động điểm danh không hợp lệ.';
        $message_type = 'danger';
    } else {
        try {
            if ($action === 'open') {
                $passcode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                $expiredAt = date('Y-m-d H:i:s', time() + 300);
                $stmtUpdate = $pdo->prepare('
                    UPDATE su_kien
                    SET is_attendance_open = 1, passcode = ?, passcode_expired_at = ?
                    WHERE ma_sukien = ?
                ');
                $stmtUpdate->execute([$passcode, $expiredAt, $eventId]);
                $message = 'Đã mở điểm danh thành công! Mã Passcode mới: '
                    . "<strong class='text-warning fs-4'>" . htmlspecialchars($passcode, ENT_QUOTES, 'UTF-8')
                    . '</strong> (Hiệu lực trong 5 phút).';
                $message_type = 'success';
            } else {
                $stmtUpdate = $pdo->prepare('
                    UPDATE su_kien
                    SET is_attendance_open = 0, passcode = NULL, passcode_expired_at = NULL
                    WHERE ma_sukien = ?
                ');
                $stmtUpdate->execute([$eventId]);
                $message = 'Đã đóng phiên điểm danh.';
                $message_type = 'warning';
            }
            $event = loadAttendanceEvent($pdo, (int) $eventId);
        } catch (PDOException $e) {
            error_log('Attendance toggle failed: ' . $e->getMessage());
            $message = 'Lỗi cập nhật phiên điểm danh. Vui lòng xem log PHP/Docker.';
            $message_type = 'danger';
        }
    }
}

// Cờ mở trong MySQL có thể vẫn = 1 sau 5 phút;
// chỉ xem là thực sự đang mở khi passcode chưa hết hạn.
$expiresTs = !empty($event['passcode_expired_at'])
    ? strtotime((string) $event['passcode_expired_at'])
    : false;
$attendanceIsActive = (int) ($event['is_attendance_open'] ?? 0) === 1
    && $expiresTs !== false && $expiresTs > time();
$attendanceExpired = (int) ($event['is_attendance_open'] ?? 0) === 1
    && !$attendanceIsActive;

// Đọc bảng dang_ky đang được api_checkin.php cập nhật thực tế.
// Sau khi chạy migration, mỗi lượt điểm danh mới có thoi_gian_diem_danh.
// Những lượt cũ chưa có thời điểm chính xác sẽ giữ NULL.
$registrations = [];
$registrationsLoadFailed = false;
try {
    $stmtReg = $pdo->prepare('
        SELECT d.ma_dangky, d.ma_sv, d.trang_thai, d.thoi_gian_diem_danh, u.ho_ten
        FROM dang_ky AS d
        LEFT JOIN users AS u
          ON d.ma_sv COLLATE utf8mb4_unicode_ci = u.ma_sv COLLATE utf8mb4_unicode_ci
        WHERE d.ma_sukien = ?
        ORDER BY d.ma_dangky ASC
    ');
    $stmtReg->execute([$eventId]);
    $registrations = $stmtReg->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $registrationsLoadFailed = true;
    error_log('Attendance list query failed: ' . $e->getMessage());
}

// Cùng một quy tắc xác định đã điểm danh cho thống kê, bảng và CSV.
function attendanceHasCheckedIn(array $registration): bool
{
    $status = trim((string) ($registration['trang_thai'] ?? ''));
    return in_array(strtolower($status), ['da_tham_gia', 'present'], true)
        || in_array($status, ['Đã tham gia', 'đã tham gia'], true);
}

// Tính theo MSSV duy nhất để tránh đếm trùng nếu có nhiều dòng đăng ký.
// Danh sách phía dưới vẫn hiển thị nguyên trạng từng dòng trong dang_ky.
$attendanceByStudent = [];
if (!$registrationsLoadFailed) {
    foreach ($registrations as $registration) {
        $studentCode = trim((string) ($registration['ma_sv'] ?? ''));
        if ($studentCode === '') {
            continue;
        }
        if (!array_key_exists($studentCode, $attendanceByStudent)) {
            $attendanceByStudent[$studentCode] = false;
        }
        if (attendanceHasCheckedIn($registration)) {
            $attendanceByStudent[$studentCode] = true;
        }
    }
}
$totalRegistered = count($attendanceByStudent);
$totalAttended = count(array_filter($attendanceByStudent));
$totalNotAttended = $totalRegistered - $totalAttended;
$attendancePercentage = $totalRegistered > 0
    ? round($totalAttended * 100 / $totalRegistered, 1)
    : 0;
$attendancePercentageText = rtrim(rtrim(number_format($attendancePercentage, 1, '.', ''), '0'), '.');

// Ngăn dữ liệu do người dùng nhập bị Excel hiểu thành công thức khi mở CSV.
function attendanceCsvCell($value): string
{
    $cell = (string) ($value ?? '');
    if (preg_match('/^[\x00-\x20]*[=+\-@]/', $cell)) {
        $cell = "'" . $cell;
    }
    return $cell;
}

// Xuất Excel .xlsx thật: giữ nguyên bảng, thống kê, quyền truy cập và phiên điểm danh.
// File hỗ trợ UTF-8, giữ MSSV dạng text và định dạng ngày giờ trong Excel.
if (($_GET['export'] ?? '') === 'xlsx') {
    if ($registrationsLoadFailed) {
        http_response_code(500);
        exit('Không thể xuất Excel vì không tải được danh sách điểm danh.');
    }
    require_once __DIR__ . '/vevent_xlsx_report.php';
    $tempFile = tempnam(sys_get_temp_dir(), 'vevent_xlsx_');
    if ($tempFile === false) {
        http_response_code(500);
        exit('Không thể tạo file Excel tạm trên máy chủ.');
    }
    try {
        VeventAttendanceXlsx::create(
            $event, $registrations, (int) $eventId,
            $totalRegistered, $totalAttended, $totalNotAttended,
            $totalRegistered > 0 ? ($totalAttended / $totalRegistered) : 0.0,
            $tempFile
        );
        if (headers_sent()) {
            throw new RuntimeException('HTTP headers đã được gửi trước khi xuất Excel.');
        }
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="diem_danh_su_kien_' . (int) $eventId . '.xlsx"');
        header('Content-Length: ' . filesize($tempFile));
        header('Cache-Control: private, no-store, max-age=0');
        header('X-Content-Type-Options: nosniff');
        readfile($tempFile);
    } catch (Throwable $e) {
        error_log('XLSX attendance export failed: ' . $e->getMessage());
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/plain; charset=UTF-8');
            echo 'Không thể xuất Excel. Vui lòng kiểm tra log PHP/Docker.';
        }
    } finally {
        @unlink($tempFile);
    }
    exit;
}

// CSV là thao tác chỉ đọc. Kiểm tra Admin đã chạy từ check_admin.php
// ở đầu file; không xuất passcode hay thông tin cá nhân ngoài danh sách.
if (($_GET['export'] ?? '') === 'csv') {
    if ($registrationsLoadFailed) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=UTF-8');
        exit('Không thể xuất danh sách điểm danh. Vui lòng kiểm tra log PHP/Docker.');
    }

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="diem_danh_su_kien_' . (int) $eventId . '.csv"');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('X-Content-Type-Options: nosniff');
    $stream = fopen('php://output', 'wb');
    if ($stream === false) {
        http_response_code(500);
        exit('Không thể tạo file CSV.');
    }
    // BOM UTF-8: giúp Excel trên Windows nhận đúng dấu tiếng Việt.
    fwrite($stream, "\xEF\xBB\xBF");
    fputcsv($stream, ['STT', 'Họ tên', 'Mã sinh viên', 'Trạng thái', 'Thời gian điểm danh'], ';', '"', '');
    foreach ($registrations as $index => $registration) {
        $checkedIn = attendanceHasCheckedIn($registration);
        $checkinTime = $checkedIn && !empty($registration['thoi_gian_diem_danh'])
            ? (string) $registration['thoi_gian_diem_danh']
            : '';
        $row = [
            (string) ($index + 1),
            $registration['ho_ten'] ?? 'Chưa cập nhật',
            $registration['ma_sv'] ?? '',
            $checkedIn ? 'Đã điểm danh' : 'Chưa điểm danh',
            $checkinTime,
        ];
        fputcsv($stream, array_map('attendanceCsvCell', $row), ';', '"', '');
    }
    fclose($stream);
    exit;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản Lý Điểm Danh - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../css/header.css">
    <link rel="stylesheet" href="../css/su-kien.css">
    <style>
        .custom-card {
            background: rgba(25, 30, 36, 0.95) !important;
            border: 1px solid rgba(0, 245, 160, 0.2) !important;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
        }
        .btn-neon-success {
            background: linear-gradient(135deg, #00f5a0 0%, #00d9f5 100%);
            color: #000;
            border: none;
            font-weight: bold;
            transition: all 0.3s ease;
        }
        .btn-neon-success:hover {
            opacity: 0.9;
            box-shadow: 0 0 15px rgba(0, 245, 160, 0.5);
            color: #000;
        }
    </style>
</head>
<body class="bg-dark text-white">

    <main class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <!-- THÔNG TIN SỰ KIỆN -->
                <div class="card custom-card text-white p-4 mb-4 rounded-4">
                    <h2 class="text-info fw-bold mb-3" style="color: #00f5a0 !important;">
                        <i class="bi bi-calendar-event me-2"></i><?php echo htmlspecialchars($event['ten_sukien'] ?? $event['ten_su_kien'] ?? 'Sự kiện'); ?>
                    </h2>
                    <p class="text-light mb-2">
                        <i class="bi bi-clock me-2 text-info"></i><strong>Thời gian bắt đầu:</strong> <?php echo htmlspecialchars($event['thoi_gian_bat_dau'] ?? 'Đang cập nhật'); ?>
                    </p>
                    <p class="text-light mb-0">
                        <i class="bi bi-geo-alt me-2 text-info"></i><strong>Địa điểm:</strong> <?php echo htmlspecialchars($event['ten_dia_diem'] ?? 'Chưa cập nhật'); ?>
                    </p>
                </div>

                <?php if (!empty($message)): ?>
                    <div class="alert alert-<?php echo $message_type; ?> text-center fw-bold shadow-sm border-0 bg-secondary text-white">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <!-- ĐIỀU KHIỂN PHIÊN ĐIỂM DANH -->
                <div class="card custom-card text-white p-4 mb-4 rounded-4">
                    <h4 class="text-info mb-3"><i class="bi bi-shield-lock-fill me-2"></i>Điều Hành Điểm Danh (5 Phút)</h4>
                    
                    <div class="row align-items-center">
                        <div class="col-md-7 mb-3 mb-md-0">
                            <?php if ($attendanceIsActive): ?>
                                <div class="p-3 bg-success bg-opacity-25 border border-success rounded-3 text-light">
                                    <h5 class="text-success mb-1 fw-bold"><i class="bi bi-unlock-fill me-2"></i>Trạng thái: Đang Mở</h5>
                                    <p class="mb-1">Mã Passcode hiện tại: <strong class="fs-3 text-warning tracking-widest"><?php echo htmlspecialchars($event['passcode'] ?? '------'); ?></strong></p>
                                    <small class="text-light opacity-75">Hết hạn lúc: <?php echo htmlspecialchars($event['passcode_expired_at'] ?? ''); ?></small>
                                </div>
                            <?php else: ?>
                                <div class="p-3 bg-dark border border-secondary rounded-3 text-light">
                                    <h5 class="mb-1 fw-bold text-warning"><i class="bi bi-lock-fill me-2"></i>Trạng thái: <?php echo $attendanceExpired ? 'Đã Hết Hạn' : 'Đang Đóng'; ?></h5>
                                    <p class="mb-0 text-light opacity-75">Nhấn nút bên phải để bắt đầu phiên điểm danh 5 phút cho sinh viên.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-5 text-md-end">
                            <form action="" method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['attendance_admin_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
                                <?php if ($attendanceIsActive): ?>
                                    <input type="hidden" name="action" value="close">
                                    <button type="submit" name="btn_toggle_attendance" class="btn btn-danger w-100 fw-bold py-3 shadow-sm">
                                        <i class="bi bi-stop-circle me-1"></i> Đóng Điểm Danh
                                    </button>
                                <?php else: ?>
                                    <input type="hidden" name="action" value="open">
                                    <button type="submit" name="btn_toggle_attendance" class="btn btn-neon-success w-100 py-3 shadow-sm">
                                        <i class="bi bi-play-circle me-1"></i> Mở Điểm Danh (Tạo Passcode)
                                    </button>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- THỐNG KÊ NHANH: dùng cùng dữ liệu danh sách, không sửa database -->
                <div class="row g-3 mb-4" aria-label="Thống kê điểm danh">
                    <div class="col-6 col-lg-3">
                        <div class="card custom-card text-white h-100 p-3 rounded-4">
                            <div class="small text-light opacity-75 mb-2"><i class="bi bi-people me-1 text-info"></i> Tổng đăng ký</div>
                            <div class="fs-2 fw-bold text-info"><?php echo $registrationsLoadFailed ? '---' : $totalRegistered; ?></div>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="card custom-card text-white h-100 p-3 rounded-4">
                            <div class="small text-light opacity-75 mb-2"><i class="bi bi-person-check me-1 text-success"></i> Đã điểm danh</div>
                            <div class="fs-2 fw-bold text-success"><?php echo $registrationsLoadFailed ? '---' : $totalAttended; ?></div>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="card custom-card text-white h-100 p-3 rounded-4">
                            <div class="small text-light opacity-75 mb-2"><i class="bi bi-person-x me-1 text-warning"></i> Chưa điểm danh</div>
                            <div class="fs-2 fw-bold text-warning"><?php echo $registrationsLoadFailed ? '---' : $totalNotAttended; ?></div>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="card custom-card text-white h-100 p-3 rounded-4">
                            <div class="small text-light opacity-75 mb-2"><i class="bi bi-bar-chart-line me-1 text-info"></i> Tỷ lệ tham gia</div>
                            <div class="fs-2 fw-bold" style="color: #00f5a0;"><?php echo $registrationsLoadFailed ? '---' : $attendancePercentageText . '%'; ?></div>
                        </div>
                    </div>
                </div>

                <!-- DANH SÁCH SINH VIÊN ĐIỂM DANH -->
                <div class="card custom-card text-white p-4 rounded-4">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                        <h4 class="text-info mb-0"><i class="bi bi-people-fill me-2"></i>Danh Sách Tham Gia & Điểm Danh</h4>
                        <?php if (!$registrationsLoadFailed): ?>
                            <a href="?id=<?php echo (int) $eventId; ?>&amp;export=xlsx" class="btn btn-outline-info fw-bold">
                                <i class="bi bi-file-earmark-spreadsheet me-1"></i> Xuất Excel (.xlsx)
                            </a>
                        <?php endif; ?>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-dark table-striped table-hover align-middle mb-0" style="background: transparent;">
                            <thead>
                                <tr class="text-info">
                                    <th>STT</th>
                                    <th>Họ Tên</th>
                                    <th>Mã Sinh Viên</th>
                                    <th>Trạng Thái</th>
                                    <th>Thời Gian Điểm Danh</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($registrationsLoadFailed): ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-warning py-4">Lỗi tải danh sách điểm danh. Vui lòng kiểm tra log PHP/Docker.</td>
                                    </tr>
                                <?php elseif (!empty($registrations)): ?>
                                    <?php foreach ($registrations as $index => $reg): ?>
                                        <tr class="text-light">
                                            <td><?php echo $index + 1; ?></td>
                                            <!-- Họ tên lấy từ users, MSSV và trạng thái lấy từ dang_ky -->
                                            <td class="fw-bold"><?php echo htmlspecialchars($reg['ho_ten'] ?? 'Chưa cập nhật'); ?></td>
                                            <td><?php echo htmlspecialchars($reg['ma_sv'] ?? 'Chưa cập nhật'); ?></td>
                                            <td>
                                                <?php 
                                                    // api_checkin.php cập nhật trang_thai thành da_tham_gia.
                                                    $is_present = attendanceHasCheckedIn($reg);
                                                ?>
                                                <?php if ($is_present): ?>
                                                    <span class="badge bg-success px-3 py-2">Đã điểm danh</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary px-3 py-2 text-light">Chưa điểm danh</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($is_present && !empty($reg['thoi_gian_diem_danh'])): ?>
                                                    <?php echo htmlspecialchars((string) $reg['thoi_gian_diem_danh'], ENT_QUOTES, 'UTF-8'); ?>
                                                <?php else: ?>
                                                    <span title="Chưa ghi nhận thời gian điểm danh thực tế">---</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-light opacity-75 py-4">Chưa có sinh viên nào đăng ký tham gia hoặc điểm danh sự kiện này.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="small text-light opacity-75 mt-3">
                        Thống kê theo MSSV duy nhất; danh sách và file Excel hiển thị từng bản ghi đăng ký. Thời gian "---" là lượt điểm danh cũ chưa lưu được thời điểm thực tế.
                    </div>
                </div>

                <div class="mt-4 text-center">
                    <!-- Bạn có thể sửa URL nút Quay lại nếu cần -->
                    <a href="dashboard.php" class="btn btn-outline-light px-4 py-2">
                        <i class="bi bi-arrow-left me-1"></i> Quay lại Dashboard
                    </a>
                </div>
            </div>
        </div>
    </main>

</body>
</html>