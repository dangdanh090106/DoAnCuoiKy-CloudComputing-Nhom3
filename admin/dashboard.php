<?php
// Bật hiển thị lỗi để dễ kiểm tra khi lập trình
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/check_admin.php'; // Kiểm tra quyền admin
require_once __DIR__ . '/../database.php';   // Kết nối CSDL

/** @var PDO $conn */

// Tự động thêm các cột điểm danh vào bảng su_kien nếu chưa tồn tại
try {
    $conn->exec("ALTER TABLE su_kien ADD COLUMN IF NOT EXISTS is_attendance_open INT DEFAULT 0");
    $conn->exec("ALTER TABLE su_kien ADD COLUMN IF NOT EXISTS passcode VARCHAR(10) DEFAULT NULL");
    $conn->exec("ALTER TABLE su_kien ADD COLUMN IF NOT EXISTS passcode_expired_at DATETIME DEFAULT NULL");
} catch (PDOException $e) {
    // Bỏ qua nếu cột đã có sẵn
}

// Lấy thông tin tài khoản Admin đang đăng nhập từ Session
$admin_ho_ten = $_SESSION['ho_ten'] ?? 'Admin';
$admin_ma_sv  = $_SESSION['ma_sv'] ?? '';

// Xử lý Mở / Đóng điểm danh trực tiếp tại Dashboard (Tránh lỗi 404)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && in_array($_POST['action'], ['open', 'close'])) {
    $eventId = $_POST['event_id'] ?? '';
    $action = $_POST['action'];
    
    if ($eventId !== '') {
        try {
            if ($action === 'open') {
                $passcode = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
                $expiredAt = date('Y-m-d H:i:s', strtotime('+5 minutes'));
                $stmt_update = $conn->prepare("UPDATE su_kien SET is_attendance_open = 1, passcode = ?, passcode_expired_at = ? WHERE ma_sukien = ?");
                $stmt_update->execute([$passcode, $expiredAt, $eventId]);
            } else {
                $stmt_update = $conn->prepare("UPDATE su_kien SET is_attendance_open = 0, passcode = NULL, passcode_expired_at = NULL WHERE ma_sukien = ?");
                $stmt_update->execute([$eventId]);
            }
            header("Location: dashboard.php");
            exit();
        } catch (PDOException $e) {
            echo "<script>alert('Lỗi CSDL: " . addslashes($e->getMessage()) . "');</script>";
        }
    }
}

// 1. Xử lý khi Admin bấm Duyệt hoặc Từ chối yêu cầu phân quyền của sinh viên
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_admin'])) {
    $target_user_id = $_POST['target_user_id'] ?? '';
    $admin_action   = $_POST['action_admin'];

    if ($target_user_id) {
        if ($admin_action === 'approve') {
            $stmt_approve = $conn->prepare("UPDATE users SET vai_tro = 'quan_ly_su_kien', yeu_cau_phan_quyen = 'approved' WHERE id = ?");
            $stmt_approve->execute([$target_user_id]);
        } elseif ($admin_action === 'reject') {
            $stmt_reject = $conn->prepare("UPDATE users SET vai_tro = 'rejected', yeu_cau_phan_quyen = 'rejected' WHERE id = ?");
            $stmt_reject->execute([$target_user_id]);
        }
    }
}

// 2. Lấy danh sách sinh viên đang chờ duyệt quyền (pending)
$stmt_pending = $conn->query("SELECT * FROM users WHERE yeu_cau_phan_quyen = 'pending'");
$pending_requests = $stmt_pending->fetchAll();

// 3. Lấy danh sách sự kiện phục vụ quản lý điểm danh (Sắp xếp tăng dần từ 1 đến 6 theo ma_sukien)
try {
    $stmt_events = $conn->query("SELECT * FROM su_kien ORDER BY ma_sukien ASC");
    $events_list = $stmt_events->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $events_list = [];
}

// 4. Lựa chọn lớp học theo bộ lọc (mặc định '242101TH001')
$selected_class = $_GET['ma_lop'] ?? '242101TH001';

// 5. Truy vấn lấy tổng số sinh viên thuộc lớp được chọn
$stmt_count = $conn->prepare("SELECT COUNT(*) as total FROM users WHERE ma_lop = ? AND (vai_tro = 'sinh_vien' OR vai_tro IS NULL OR vai_tro = '')");
$stmt_count->execute([$selected_class]);
$total_students = $stmt_count->fetch()['total'] ?? 0;

// 6. Truy vấn danh sách và số lượng tất cả Nhân viên / Quản lý sự kiện
$stmt_staff_list = $conn->query("SELECT ma_sv, ho_ten, email, sdt, chuc_vu_mong_muon FROM users WHERE vai_tro = 'quan_ly_su_kien' ORDER BY ma_sv ASC");
$staff_members = $stmt_staff_list->fetchAll();
$total_staff = count($staff_members);

// 7. Truy vấn danh sách sinh viên thuộc lớp kèm thống kê sự kiện & điểm rèn luyện
$first_day_of_month = date('Y-m-01 00:00:00');
$last_day_of_month  = date('Y-m-t 23:59:59');

$sql_students = "
    SELECT u.*,
        (
            SELECT COUNT(DISTINCT dk.su_kien_id)
            FROM dang_ky_su_kien dk
            JOIN su_kien sk ON dk.su_kien_id = sk.ma_sukien
            WHERE dk.user_id = u.id
              AND dk.trang_thai = 'da_tham_gia'
              AND sk.thoi_gian_bat_dau BETWEEN :first_day AND :last_day
        ) AS so_su_kien_thang,
        (
            SELECT COALESCE(SUM(sk.diem_ren_luyen), 0)
            FROM dang_ky_su_kien dk
            JOIN su_kien sk ON dk.su_kien_id = sk.ma_sukien
            WHERE dk.user_id = u.id
              AND dk.trang_thai = 'da_tham_gia'
        ) AS tong_diem_ren_luyen
    FROM users u
    WHERE u.ma_lop = :ma_lop AND (u.vai_tro = 'sinh_vien' OR u.vai_tro IS NULL OR u.vai_tro = '')
";

try {
    $stmt_students = $conn->prepare($sql_students);
    $stmt_students->execute([
        ':first_day' => $first_day_of_month,
        ':last_day'  => $last_day_of_month,
        ':ma_lop'    => $selected_class
    ]);
    $students = $stmt_students->fetchAll();
} catch (PDOException $e) {
    $stmt_students = $conn->prepare("SELECT *, 0 AS so_su_kien_thang, 0 AS tong_diem_ren_luyen FROM users WHERE ma_lop = ? AND (vai_tro = 'sinh_vien' OR vai_tro IS NULL OR vai_tro = '')");
    $stmt_students->execute([$selected_class]);
    $students = $stmt_students->fetchAll();
}

// 8. Lấy danh sách tất cả các lớp học có trong hệ thống để đổ vào menu thả xuống
$stmt_classes = $conn->query("SELECT DISTINCT ma_lop FROM users WHERE ma_lop IS NOT NULL AND ma_lop != '' ORDER BY ma_lop ASC");
$classes = $stmt_classes->fetchAll();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - Quản lý Lớp & Phân Quyền</title>
    <!-- Thêm Bootstrap 5 & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <!-- Liên kết đường dẫn CSS chuẩn hiện tại -->
    <link rel="stylesheet" href="../css/dashboard.css">
</head>
<body>
    <div class="container">
        <!-- HEADER -->
        <div class="header">
            <h2>🛠️ Trang Quản Trị Hệ Thống (Admin)</h2>
            <div class="header-nav">
                <!-- 🔔 CHUÔNG THÔNG BÁO -->
                <a href="#requests-section" class="notification-bell" title="Yêu cầu nâng cấp quyền">
                    🔔 Thông báo
                    <?php if (count($pending_requests) > 0): ?>
                        <span class="notification-badge"><?php echo count($pending_requests); ?></span>
                    <?php endif; ?>
                </a>

                <a href="../index.php" class="btn-home">Trang chủ</a>
                <a href="../su-kien.php" class="btn-events">Quản lý sự kiện</a>
                <a href="logout.php" class="btn-logout">Đăng xuất</a>
            </div>
        </div>

        <!-- 🔔 KHU VỰC DUYỆT YÊU CẦU NÂNG CẤP QUYỀN -->
        <?php if (count($pending_requests) > 0): ?>
            <div id="requests-section" class="approval-box">
                <h3>🔔 Danh sách yêu cầu nâng cấp quyền (<?php echo count($pending_requests); ?>)</h3>
                <table>
                    <thead>
                        <tr style="background: #ffe8a1;">
                            <th>Mã SV</th>
                            <th>Họ tên</th>
                            <th>Lớp</th>
                            <th>Chức vụ đăng ký</th>
                            <th>Lý do xin nâng cấp</th>
                            <th style="text-align: center;">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending_requests as $req): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($req['ma_sv']); ?></td>
                                <td><strong><?php echo htmlspecialchars($req['ho_ten']); ?></strong></td>
                                <td><?php echo htmlspecialchars($req['ma_lop'] ?? ''); ?></td>
                                <td><span style="color: #0d6efd; font-weight: bold;"><?php echo htmlspecialchars($req['chuc_vu_mong_muon'] ?? 'Chưa rõ'); ?></span></td>
                                <td><span style="font-style: italic; color: #555;"><?php echo htmlspecialchars($req['ly_do_nang_cap'] ?? 'Không có lý do'); ?></span></td>
                                <td style="text-align: center;">
                                    <form method="POST" style="display: inline-block;">
                                        <input type="hidden" name="target_user_id" value="<?php echo $req['id']; ?>">
                                        <button type="submit" name="action_admin" value="approve" style="background: #198754; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-weight: bold;">Duyệt</button>
                                        <button type="submit" name="action_admin" value="reject" style="background: #dc3545; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-weight: bold;">Từ chối</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <!-- ================================================================= -->
        <!-- 🛡️ QUẢN LÝ ĐIỂM DANH SỰ KIỆN (MÃ PASSCODE 5 PHÚT) - ĐẶT LÊN TRÊN -->
        <!-- ================================================================= -->
        <div class="card text-white border-0 shadow-lg p-4 mb-4 rounded-4" style="background-color: #34495e !important; border: 1px solid #2c3e50 !important;">
            <h4 class="text-success mb-3 fw-bold">
                <i class="bi bi-shield-lock-fill me-2"></i> Quản lý Điểm Danh Sự Kiện (Mã Passcode 5 phút)
            </h4>
            
            <div class="table-responsive">
                <table class="table table-dark table-hover align-middle text-center border-secondary mb-0" style="background-color: #212529; border-radius: 8px; overflow: hidden;">
                    <thead>
                        <tr class="table-secondary text-dark fw-bold">
                            <th style="width: 5%;">ID</th>
                            <th style="width: 30%; text-align: left; padding-left: 15px;">Tên sự kiện</th>
                            <th style="width: 15%;">Trạng thái điểm danh</th>
                            <th style="width: 15%;">Mã Passcode hiện tại</th>
                            <th style="width: 20%;">Hành động mở/đóng</th>
                            <th style="width: 15%;">Thống kê</th>
                        </tr>
                    </thead>
                    <tbody class="text-white">
                        <?php if (count($events_list) > 0): ?>
                            <?php foreach ($events_list as $ev): ?>
                                <?php 
                                    $evId = $ev['ma_sukien'] ?? 0; // Khớp cột khóa chính ma_sukien
                                    $eventTitle = $ev['ten_sukien'] ?? 'Sự kiện chưa có tên'; 
                                    $isOpen = isset($ev['is_attendance_open']) && $ev['is_attendance_open'] == 1;
                                    $passcode = $isOpen ? ($ev['passcode'] ?? '------') : '------';
                                ?>
                                <tr>
                                    <td class="fw-bold text-white"><?php echo htmlspecialchars($evId); ?></td>
                                    <td style="text-align: left; padding-left: 15px;" class="fw-semibold text-info">
                                        <?php echo htmlspecialchars($eventTitle); ?>
                                    </td>
                                    <td>
                                        <?php if ($isOpen): ?>
                                            <span class="badge bg-success px-3 py-2">Đang mở</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary px-3 py-2">Đã đóng</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="font-monospace fs-5 text-warning fw-bold" style="letter-spacing: 3px;">
                                            <?php echo htmlspecialchars($passcode); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <!-- action="" để gửi dữ liệu xử lý trực tiếp tại trang dashboard.php -->
                                        <form action="" method="POST" class="d-inline">
                                            <input type="hidden" name="event_id" value="<?php echo htmlspecialchars($evId); ?>">
                                            <?php if ($isOpen): ?>
                                                <button type="submit" name="action" value="close" class="btn btn-danger btn-sm fw-bold px-3">
                                                    <i class="bi bi-lock-fill me-1"></i> Đóng
                                                </button>
                                            <?php else: ?>
                                                <button type="submit" name="action" value="open" class="btn btn-warning btn-sm text-dark fw-bold px-3">
                                                    <i class="bi bi-key-fill me-1"></i> Mở (5 phút)
                                                </button>
                                            <?php endif; ?>
                                        </form>
                                    </td>
                                    <td>
                                        <a href="chi_tiet_diem_danh.php?id=<?php echo htmlspecialchars($evId); ?>" class="btn btn-info btn-sm text-dark fw-bold px-3">
                                            <i class="bi bi-people-fill me-1"></i> Xem danh sách
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-white-50 py-3">Chưa có sự kiện nào trong hệ thống.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- BỘ LỌC CHỌN LỚP HỌC -->
        <div class="filter-form">
            <label><strong>Chọn lớp học:</strong></label>
            <select onchange="location = 'dashboard.php?ma_lop=' + this.value;">
                <?php foreach ($classes as $cls): ?>
                    <option value="<?php echo htmlspecialchars($cls['ma_lop']); ?>" <?php echo ($cls['ma_lop'] === $selected_class) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($cls['ma_lop']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- 📊 THỐNG KÊ GRID (SINH VIÊN & NHÂN VIÊN) -->
        <div class="stats-grid">
            <!-- Thống kê lớp -->
            <div class="stat-card">
                <h3>📊 Thống kê lớp: <?php echo htmlspecialchars($selected_class); ?></h3>
                <p>Tổng số sinh viên: <span><?php echo $total_students; ?></span> người</p>
            </div>

            <!-- Thống kê nhân sự + Danh sách chi tiết -->
            <div class="stat-card-staff">
                <h3>👔 Thống kê Nhân sự / Ban tổ chức</h3>
                <p>Tổng số Quản lý sự kiện: <span><?php echo $total_staff; ?></span> người</p>

                <div class="staff-list-box">
                    <div class="staff-list-title">📌 Danh sách Quản lý sự kiện hiện tại:</div>
                    <?php if ($total_staff > 0): ?>
                        <ul class="staff-ul">
                            <?php foreach ($staff_members as $staff): ?>
                                <?php $is_current = ($staff['ma_sv'] === $admin_ma_sv); ?>
                                <li class="<?php echo $is_current ? 'current-logged-in' : ''; ?>">
                                    <strong><?php echo htmlspecialchars($staff['ho_ten']); ?></strong> 
                                    (<?php echo htmlspecialchars($staff['ma_sv']); ?>)
                                    <?php if (!empty($staff['chuc_vu_mong_muon'])): ?>
                                        - <em><?php echo htmlspecialchars($staff['chuc_vu_mong_muon']); ?></em>
                                    <?php endif; ?>

                                    <?php if ($is_current): ?>
                                        <span class="badge-current-user">🟢 Đang đăng nhập</span>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p style="font-size: 13px; color: #777; margin-top: 5px;">Chưa có quản lý sự kiện nào.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- DANH SÁCH SINH VIÊN -->
        <h3>📋 Danh sách sinh viên & Điểm rèn luyện</h3>
        <table>
            <thead>
                <tr>
                    <th>STT</th>
                    <th>Mã SV</th>
                    <th>Họ và tên</th>
                    <th>Email</th>
                    <th>Sự kiện tháng này</th>
                    <th>Điểm rèn luyện cộng</th>
                    <th>Trạng thái</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($students) > 0): ?>
                    <?php $i = 1; foreach ($students as $stu): ?>
                        <tr>
                            <td><?php echo $i++; ?></td>
                            <td><?php echo htmlspecialchars($stu['ma_sv']); ?></td>
                            <td><?php echo htmlspecialchars($stu['ho_ten']); ?></td>
                            <td><?php echo htmlspecialchars($stu['email'] ?? 'Chưa cập nhật'); ?></td>
                            <td><span class="badge-event"><?php echo (int)($stu['so_su_kien_thang'] ?? 0); ?> sự kiện</span></td>
                            <td><span class="badge-score">+<?php echo (int)($stu['tong_diem_ren_luyen'] ?? 0); ?> điểm</span></td>
                            <td><?php echo htmlspecialchars($stu['trang_thai'] ?? 'Đang học'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" style="text-align: center; color: #777;">Không có sinh viên nào trong lớp này.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>