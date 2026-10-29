<?php
// Bắt đầu session
session_start();

// Kết nối cơ sở dữ liệu từ thư mục gốc
include '../database.php';
$conn = $conn ?? $pdo ?? null;

// Lấy thông tin mã sinh viên và id từ session đăng nhập
$ma_sv = $_SESSION['ma_sv'] ?? '';
$user_id = $_SESSION['user_id'] ?? '';

if (empty($ma_sv) || empty($user_id)) {
    header("Location: ../login.php");
    exit();
}

// Xử lý khi người dùng gửi form yêu cầu nâng quyền
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'request_upgrade') {
    $chuc_vu_mong_muon = $_POST['chuc_vu_mong_muon'] ?? '';
    $ten_clb = $_POST['ten_clb'] ?? '';
    $ly_do_nang_cap = $_POST['ly_do_nang_cap'] ?? '';

    if (!empty($chuc_vu_mong_muon) && !empty($ten_clb) && !empty($ly_do_nang_cap)) {
        try {
            $stmt_update = $conn->prepare("UPDATE users SET yeu_cau_phan_quyen = 'pending', chuc_vu_mong_muon = ?, ten_clb = ?, ly_do_nang_cap = ? WHERE ma_sv = ?");
            $stmt_update->execute([$chuc_vu_mong_muon, $ten_clb, $ly_do_nang_cap, $ma_sv]);
            $message = "Gửi yêu cầu thành công! Vui lòng chờ Admin phê duyệt.";
        } catch (PDOException $e) {
            $error = "Lỗi hệ thống: " . $e->getMessage();
        }
    } else {
        $error = "Vui lòng chọn đầy đủ chức vụ, câu lạc bộ và nhập lý do yêu cầu!";
    }
}

// Xử lý khi sinh viên xác nhận điểm danh sự kiện
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'confirm_attendance') {
    $event_id = $_POST['event_id'] ?? '';
    $passcode_input = trim($_POST['passcode'] ?? '');

    // Truy vấn thông tin sự kiện bằng ma_sukien
    $stmt_check = $conn->prepare("SELECT * FROM su_kien WHERE ma_sukien = ? AND is_attendance_open = 1");
    $stmt_check->execute([$event_id]);
    $event = $stmt_check->fetch(PDO::FETCH_ASSOC);

    if ($event && trim($event['passcode'] ?? '') === $passcode_input) {
        $diem_thuong = $event['diem_thuong'] ?? 5; 
        $target_event_id = $event['ma_sukien'];

        try {
            $conn->beginTransaction();

            // Kiểm tra xem sinh viên đã có bản ghi trong bảng dang_ky_su_kien chưa
            $stmt_check_reg = $conn->prepare("SELECT id FROM dang_ky_su_kien WHERE user_id = ? AND su_kien_id = ?");
            $stmt_check_reg->execute([$user_id, $target_event_id]);
            $reg_row = $stmt_check_reg->fetch(PDO::FETCH_ASSOC);

            if ($reg_row) {
                // Đã có -> Cập nhật trạng thái thành da_tham_gia
                $stmt_update_dk = $conn->prepare("UPDATE dang_ky_su_kien SET trang_thai = 'da_tham_gia' WHERE user_id = ? AND su_kien_id = ?");
                $stmt_update_dk->execute([$user_id, $target_event_id]);
            } else {
                // Chưa có -> Thêm mới bản ghi với trạng thái da_tham_gia
                $stmt_insert_dk = $conn->prepare("INSERT INTO dang_ky_su_kien (user_id, su_kien_id, trang_thai) VALUES (?, ?, 'da_tham_gia')");
                $stmt_insert_dk->execute([$user_id, $target_event_id]);
            }

            // Tự động cộng điểm rèn luyện cho sinh viên
            $stmt_cong_diem = $conn->prepare("UPDATE users SET diem_ren_luyen = diem_ren_luyen + ? WHERE id = ?");
            $stmt_cong_diem->execute([$diem_thuong, $user_id]);

            $conn->commit();
            $message = "Điểm danh thành công! Bạn đã được cộng $diem_thuong điểm rèn luyện.";
            
            // F5 lại trang để đóng modal vĩnh viễn
            header("Location: dashboard.php");
            exit();
        } catch (Exception $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            $error = "Lỗi điểm danh: " . $e->getMessage();
        }
    } else {
        $error = "Mã Passcode không chính xác hoặc sự kiện đã đóng!";
    }
}

// Truy vấn lại thông tin người dùng mới nhất từ CSDL
$stmt = $conn->prepare("SELECT * FROM users WHERE ma_sv = ?");
$stmt->execute([$ma_sv]);
$user = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$vai_tro = $user['vai_tro'] ?? 'sinh_vien';
$yeu_cau = $user['yeu_cau_phan_quyen'] ?? 'none';
$chuc_vu_cu = $user['chuc_vu_mong_muon'] ?? '';
$ten_clb_cu = $user['ten_clb'] ?? '';
$ly_do_cu = $user['ly_do_nang_cap'] ?? '';

$has_notification = ($yeu_cau === 'approved' || $yeu_cau === 'rejected');

// Kiểm tra sự kiện đang mở điểm danh (Dùng LEFT JOIN để loại trừ những sự kiện đã điểm danh)
$active_event = null;
if (!empty($user_id) && ($vai_tro === 'sinh_vien' || empty($vai_tro))) {
    $stmt_active = $conn->prepare("
        SELECT sk.* FROM su_kien sk
        LEFT JOIN dang_ky_su_kien dk ON sk.ma_sukien = dk.su_kien_id AND dk.user_id = ?
        WHERE sk.is_attendance_open = 1 
        AND (dk.trang_thai IS NULL OR dk.trang_thai != 'da_tham_gia')
        LIMIT 1
    ");
    $stmt_active->execute([$user_id]);
    $active_event = $stmt_active->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo htmlspecialchars($user['ho_ten'] ?? ''); ?></title>
    <!-- Bootstrap 5 & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" type="text/css" href="../css/user-dashboard.css?v=<?php echo time(); ?>">
</head>
<body>
<div class="container py-4">
    <div class="header d-flex justify-content-between align-items-center mb-4">
        <h2>👋 Chào mừng, <?php echo htmlspecialchars($user['ho_ten'] ?? ''); ?></h2>
        <div class="header-nav">
            <a href="#role-section" class="notification-bell btn btn-outline-secondary btn-sm position-relative me-2" title="Thông báo hệ thống">
                🔔 Thông báo
                <?php if ($has_notification): ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">1</span>
                <?php endif; ?>
            </a>
            <a href="../index.php" class="btn btn-outline-light btn-sm me-2">Trang chủ</a>
            <a href="../index.php" class="btn btn-danger btn-sm">Đăng xuất</a>
        </div>
    </div>

    <!-- Hiển thị thông báo lỗi hoặc thành công -->
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger mt-3"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <?php if (!empty($message)): ?>
        <div class="alert alert-success mt-3"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <h3>Thông tin tài khoản</h3>
    <div class="info-grid row mb-4">
        <div class="col-md-6">
            <p><strong>Mã số / Mã SV:</strong> <?php echo htmlspecialchars($user['ma_sv'] ?? ''); ?></p>
            <p><strong>Họ và tên:</strong> <?php echo htmlspecialchars($user['ho_ten'] ?? ''); ?></p>
            <p><strong>Ngày sinh:</strong> <?php echo htmlspecialchars($user['ngay_sinh'] ?? 'Chưa cập nhật'); ?></p>
            <p><strong>Giới tính:</strong> <?php echo htmlspecialchars($user['gioi_tinh'] ?? 'Chưa cập nhật'); ?></p>
        </div>
        <div class="col-md-6">
            <p><strong>Số điện thoại:</strong> <?php echo htmlspecialchars($user['sdt'] ?? 'Chưa cập nhật'); ?></p>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email'] ?? 'Chưa cập nhật'); ?></p>
            <p><strong>Trạng thái:</strong> <?php echo htmlspecialchars($user['trang_thai'] ?? 'Đang học / Đang công tác'); ?></p>
            <p><strong>Điểm rèn luyện:</strong> 
                <span class="text-success fw-bold fs-5"><?php echo htmlspecialchars($user['diem_ren_luyen'] ?? 0); ?> điểm</span>
                <a href="diem-ren-luyen.php" class="btn btn-sm btn-outline-success ms-2 py-0">Xem chi tiết</a>
            </p>
        </div>
    </div>

    <!-- 🔑 KHU VỰC YÊU CẦU NÂNG CẤP QUYỀN & THÔNG BÁO -->
    <div id="role-section" class="role-section card bg-dark text-white p-4 border-secondary shadow-sm">
        <h3>🔒 Đăng ký & Trạng thái Quản lý sự kiện</h3>

        <?php if ($yeu_cau === 'approved'): ?>
            <div style="background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 6px; margin-bottom: 15px;" class="mt-3">
                <p style="color: #155724; font-weight: bold; margin: 0;">🎉 Chúc mừng! Yêu cầu của bạn đã được Admin phê duyệt.</p>
                <p style="color: #155724; margin: 5px 0 0;">Bạn đã chính thức trở thành <b>Quản lý sự kiện</b>.</p>
            </div>
        <?php elseif ($yeu_cau === 'rejected'): ?>
            <div style="background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; border-radius: 6px; margin-bottom: 15px;" class="mt-3">
                <p style="color: #721c24; font-weight: bold; margin: 0;">❌ Rất tiếc! Yêu cầu nâng cấp quyền của bạn đã bị từ chối.</p>
            </div>
        <?php endif; ?>

        <p class="mt-2">Vai trò hiện tại: <strong>
        <?php
            if ($vai_tro === 'admin') echo 'Quản trị viên (Admin)';
            elseif ($vai_tro === 'quan_ly_su_kien') echo 'Quản lý sự kiện';
            else echo 'Sinh viên';
        ?>
        </strong></p>

        <?php if ($vai_tro === 'sinh_vien'): ?>
            <?php if ($yeu_cau === 'pending'): ?>
                <div style="background: #fff3cd; padding: 12px; border-radius: 4px; border: 1px solid #ffeeba; margin-top: 10px;">
                    <p style="margin: 0; color: #856404; font-weight: bold;">⏳ Yêu cầu của bạn đang chờ Admin duyệt.</p>
                    <p style="margin: 5px 0 0; font-size: 13px; color: #000;">Chức vụ đăng ký: <strong><?php echo htmlspecialchars($chuc_vu_cu); ?></strong></p>
                    <p style="margin: 2px 0 0; font-size: 13px; color: #000;">Câu lạc bộ: <strong><?php echo htmlspecialchars($ten_clb_cu); ?></strong></p>
                    <p style="margin: 2px 0 0; font-size: 13px; color: #000;">Lý do: <em><?php echo htmlspecialchars($ly_do_cu); ?></em></p>
                </div>
            <?php else: ?>
                <form method="POST" style="margin-top: 15px;">
                    <input type="hidden" name="action" value="request_upgrade">
                    <div class="form-group mb-3">
                        <label>Bạn là ai / Chức vụ mong muốn (*)</label>
                        <select name="chuc_vu_mong_muon" class="form-control" required>
                            <option value="">-- Chọn chức vụ / vai trò --</option>
                            <option value="Chủ nhiệm CLB">Chủ nhiệm CLB</option>
                            <option value="Phó chủ nhiệm CLB">Phó chủ nhiệm CLB</option>
                            <option value="Nhân viên ban tổ chức">Nhân viên ban tổ chức</option>
                            <option value="Cán bộ lớp / Đoàn thanh niên">Cán bộ lớp / Đoàn thanh niên</option>
                        </select>
                    </div>

                    <div class="form-group mb-3">
                        <label>Chọn Câu lạc bộ (*)</label>
                        <select name="ten_clb" class="form-control" required>
                            <option value="">-- Chọn câu lạc bộ --</option>
                            <option value="Tin học">Tin học</option>
                            <option value="Tiếng Anh">Tiếng Anh</option>
                            <option value="Bạn đọc">Bạn đọc</option>
                            <option value="Thế thao">Thể thao</option>
                            <option value="Kỹ năng sống">Kỹ năng sống</option>
                            <option value="Thiện nguyện">Thiện nguyện</option>
                        </select>
                    </div>

                    <div class="form-group mb-3">
                        <label>Lý do xin nâng quyền (*)</label>
                        <textarea name="ly_do_nang_cap" class="form-control" rows="3" required placeholder="Ví dụ: Tôi là Chủ nhiệm CLB Tin học, cần tài khoản để tạo và quản lý sự kiện."></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary btn-request">
                        🚀 Gửi yêu cầu xét duyệt
                    </button>
                </form>
            <?php endif; ?>
        <?php else: ?>
            <p style="color: #198754; font-weight: bold; margin-bottom: 10px;" class="mt-3">
                ✔ Tài khoản của bạn đã có quyền quản lý sự kiện!
            </p>
            <a href="quan_ly_su_kien.php" class="btn btn-success btn-manager">
                👉 Đi tới Trang đăng & quản lý sự kiện
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- 🚀 MODAL TỰ ĐỘNG BẬT MÃ PASSCODE ĐIỂM DANH -->
<?php if ($active_event): ?>
<div class="modal fade" id="autoAttendanceModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark text-white border-success shadow-lg">
            <div class="modal-header border-secondary">
                <h5 class="modal-title text-success fw-bold">
                    <i class="bi bi-shield-lock-fill me-2"></i>Điểm Danh Sự Kiện Đang Mở!
                </h5>
            </div>
            <form action="" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="confirm_attendance">
                    <input type="hidden" name="event_id" value="<?php echo htmlspecialchars($active_event['ma_sukien']); ?>">
                    <p class="mb-2">Sự kiện: <strong class="text-info"><?php echo htmlspecialchars($active_event['ten_sukien'] ?? $active_event['tieu_de']); ?></strong> đang diễn ra.</p>
                    <p class="text-muted small mb-3">Vui lòng nhập mã Passcode 6 chữ số do Ban tổ chức cung cấp để xác nhận điểm danh và nhận điểm rèn luyện.</p>
                    
                    <div class="mb-3 text-center">
                        <input type="text" name="passcode" class="form-control form-control-lg text-center fw-bold fs-3 text-warning bg-secondary text-white border-0" required maxlength="6" autocomplete="off" placeholder="------">
                    </div>
                </div>
                <div class="modal-footer border-secondary justify-content-center">
                    <button type="submit" class="btn btn-success px-5 fw-bold">Xác Nhận Điểm Danh</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php if ($active_event): ?>
<script>
    document.addEventListener("DOMContentLoaded", function () {
        var myModal = new bootstrap.Modal(document.getElementById('autoAttendanceModal'));
        myModal.show();
    });
</script>
<?php endif; ?>
</body>
</html>