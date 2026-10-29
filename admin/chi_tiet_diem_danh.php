<?php
session_start();
require_once __DIR__ . '/../database.php';

// Hỗ trợ nhận diện mọi tên biến kết nối từ database.php ($pdo, $conn, $db)
if (!isset($pdo)) {
    if (isset($conn)) {
        $pdo = $conn;
    } elseif (isset($db)) {
        $pdo = $db;
    }
}

/** @var PDO $pdo */

$eventId = $_GET['id'] ?? $_GET['event_id'] ?? 0;
if (!$eventId) {
    die("Thiếu mã sự kiện.");
}

// Truy vấn thông tin sự kiện an toàn
$event = null;
$possibleColumns = ['id', 'ma_su_kien', 'id_su_kien', 'ma_sukien'];
foreach ($possibleColumns as $col) {
    try {
        $stmtEvent = $pdo->prepare("SELECT * FROM su_kien WHERE $col = ?");
        $stmtEvent->execute([$eventId]);
        $event = $stmtEvent->fetch(PDO::FETCH_ASSOC);
        if ($event) {
            break;
        }
    } catch (PDOException $e) {}
}

if (!$event) {
    die("Không tìm thấy thông tin sự kiện trong cơ sở dữ liệu.");
}

$message = "";
$message_type = "";

// Xử lý Admin bật/tắt điểm danh hoặc tạo Passcode mới
if (isset($_POST['btn_toggle_attendance'])) {
    $action = $_POST['action'] ?? '';
    
    if ($action == 'open') {
        $passcode = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiredAt = date('Y-m-d H:i:s', strtotime('+5 minutes'));
        
        foreach ($possibleColumns as $col) {
            try {
                $stmtUpdate = $pdo->prepare("UPDATE su_kien SET is_attendance_open = 1, passcode = ?, passcode_expired_at = ? WHERE $col = ?");
                $stmtUpdate->execute([$passcode, $expiredAt, $eventId]);
                break;
            } catch (PDOException $e) {}
        }
        $message = "Đã mở điểm danh thành công! Mã Passcode mới: <strong class='text-warning fs-4'>$passcode</strong> (Hiệu lực trong 5 phút).";
        $message_type = "success";
    } else {
        foreach ($possibleColumns as $col) {
            try {
                $stmtUpdate = $pdo->prepare("UPDATE su_kien SET is_attendance_open = 0, passcode = NULL, passcode_expired_at = NULL WHERE $col = ?");
                $stmtUpdate->execute([$eventId]);
                break;
            } catch (PDOException $e) {}
        }
        $message = "Đã đóng phiên điểm danh.";
        $message_type = "warning";
    }

    // Tải lại thông tin sự kiện sau khi cập nhật
    foreach ($possibleColumns as $col) {
        try {
            $stmtEvent = $pdo->prepare("SELECT * FROM su_kien WHERE $col = ?");
            $stmtEvent->execute([$eventId]);
            $event = $stmtEvent->fetch(PDO::FETCH_ASSOC);
            if ($event) { break; }
        } catch (PDOException $e) {}
    }
}

// Lấy danh sách sinh viên tham gia sự kiện (Hỗ trợ quét đa dạng tên cột khóa ngoại)
$registrations = [];
try {
    $stmtReg = $pdo->prepare("
        SELECT d.*, u.ma_sv, u.ho_ten, u.email 
        FROM dang_ky_su_kien d
        LEFT JOIN users u ON (d.user_id = u.id OR d.sinh_vien_id = u.id OR d.user_id = u.ma_sv)
        WHERE d.su_kien_id = ?
    ");
    $stmtReg->execute([$eventId]);
    $registrations = $stmtReg->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $ex) {
    // Dự phòng trường hợp bảng tên là dang_ky
    try {
        $stmtReg = $pdo->prepare("
            SELECT d.*, u.ma_sv, u.ho_ten, u.email 
            FROM dang_ky d
            LEFT JOIN users u ON (d.user_id = u.id OR d.sinh_vien_id = u.id OR d.user_id = u.ma_sv)
            WHERE d.su_kien_id = ?
        ");
        $stmtReg->execute([$eventId]);
        $registrations = $stmtReg->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e2) {}
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

    <?php include_once __DIR__ . '/../header.php'; ?>

    <main class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <!-- THÔNG TIN SỰ KIỆN -->
                <div class="card custom-card text-white p-4 mb-4 rounded-4">
                    <h2 class="text-info fw-bold mb-3" style="color: #00f5a0 !important;">
                        <i class="bi bi-calendar-event me-2"></i><?php echo htmlspecialchars($event['ten_su_kien'] ?? $event['tieu_de'] ?? 'Sự kiện'); ?>
                    </h2>
                    <p class="text-light mb-2">
                        <i class="bi bi-clock me-2 text-info"></i><strong>Thời gian bắt đầu:</strong> <?php echo htmlspecialchars($event['thoi_gian_bat_dau'] ?? 'Đang cập nhật'); ?>
                    </p>
                    <p class="text-light mb-0">
                        <i class="bi bi-geo-alt me-2 text-info"></i><strong>Địa điểm:</strong> <?php echo htmlspecialchars($event['dia_diem'] ?? 'Chưa cập nhật'); ?>
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
                            <?php if (isset($event['is_attendance_open']) && $event['is_attendance_open'] == 1): ?>
                                <div class="p-3 bg-success bg-opacity-25 border border-success rounded-3 text-light">
                                    <h5 class="text-success mb-1 fw-bold"><i class="bi bi-unlock-fill me-2"></i>Trạng thái: Đang Mở</h5>
                                    <p class="mb-1">Mã Passcode hiện tại: <strong class="fs-3 text-warning tracking-widest"><?php echo htmlspecialchars($event['passcode'] ?? '------'); ?></strong></p>
                                    <small class="text-light opacity-75">Hết hạn lúc: <?php echo htmlspecialchars($event['passcode_expired_at'] ?? ''); ?></small>
                                </div>
                            <?php else: ?>
                                <div class="p-3 bg-dark border border-secondary rounded-3 text-light">
                                    <h5 class="mb-1 fw-bold text-warning"><i class="bi bi-lock-fill me-2"></i>Trạng thái: Đang Đóng</h5>
                                    <p class="mb-0 text-light opacity-75">Nhấn nút bên phải để bắt đầu phiên điểm danh 5 phút cho sinh viên.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-5 text-md-end">
                            <form action="" method="POST">
                                <?php if (isset($event['is_attendance_open']) && $event['is_attendance_open'] == 1): ?>
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

                <!-- DANH SÁCH SINH VIÊN ĐIỂM DANH -->
                <div class="card custom-card text-white p-4 rounded-4">
                    <h4 class="text-info mb-3"><i class="bi bi-people-fill me-2"></i>Danh Sách Tham Gia & Điểm Danh</h4>
                    <div class="table-responsive">
                        <table class="table table-dark table-striped table-hover align-middle mb-0" style="background: transparent;">
                            <thead>
                                <tr class="text-info">
                                    <th>STT</th>
                                    <th>Họ Tên</th>
                                    <th>Tên Tài Khoản</th>
                                    <th>Trạng Thái</th>
                                    <th>Thời Gian Điểm Danh</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($registrations)): ?>
                                    <?php foreach ($registrations as $index => $reg): ?>
                                        <tr class="text-light">
                                            <td><?php echo $index + 1; ?></td>
                                            <td class="fw-bold"><?php echo htmlspecialchars($reg['hoten'] ?? $reg['username'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($reg['username'] ?? 'N/A'); ?></td>
                                            <td>
                                                <?php if (isset($reg['status_attendance']) && $reg['status_attendance'] === 'present'): ?>
                                                    <span class="badge bg-success px-3 py-2">Đã điểm danh</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary px-3 py-2 text-light">Chưa điểm danh</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($reg['attended_at'] ?? '---'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-light opacity-75 py-4">Chưa có sinh viên nào đăng ký tham gia sự kiện này.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="mt-4 text-center">
                    <a href="dashboard.php" class="btn btn-outline-light px-4 py-2">
                        <i class="bi bi-arrow-left me-1"></i> Quay lại Dashboard
                    </a>
                </div>
            </div>
        </div>
    </main>

</body>
</html>