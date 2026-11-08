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

// Xử lý Mở / Đóng điểm danh trực tiếp tại Dashboard
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

// 3. Lấy danh sách sự kiện phục vụ quản lý điểm danh
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
        COALESCE(u.diem_ren_luyen, 0) AS tong_diem_ren_luyen,
        (
            SELECT COUNT(DISTINCT dk.su_kien_id)
            FROM dang_ky_su_kien dk
            JOIN su_kien sk ON dk.su_kien_id = sk.ma_sukien
            WHERE dk.user_id = u.id
              AND (dk.trang_thai = 'da_tham_gia' OR dk.trang_thai = 'present')
              AND sk.thoi_gian_bat_dau BETWEEN :first_day AND :last_day
        ) AS so_su_kien_thang
    FROM users u
    WHERE u.ma_lop = :ma_lop AND (u.vai_tro = 'sinh_vien' OR u.vai_tro IS NULL OR u.vai_tro = '')
    ORDER BY u.ma_sv ASC
";

try {
    $stmt_students = $conn->prepare($sql_students);
    $stmt_students->execute([
        ':first_day' => $first_day_of_month,
        ':last_day'  => $last_day_of_month,
        ':ma_lop'    => $selected_class
    ]);
    $students = $stmt_students->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $stmt_students = $conn->prepare("SELECT *, 0 AS so_su_kien_thang, COALESCE(diem_ren_luyen, 0) AS tong_diem_ren_luyen FROM users WHERE ma_lop = ? AND (vai_tro = 'sinh_vien' OR vai_tro IS NULL OR vai_tro = '') ORDER BY ma_sv ASC");
    $stmt_students->execute([$selected_class]);
    $students = $stmt_students->fetchAll(PDO::FETCH_ASSOC);
}

// 8. Lấy danh sách tất cả các lớp học có trong hệ thống để đổ vào menu thả xuống
$stmt_classes = $conn->query("SELECT DISTINCT ma_lop FROM users WHERE ma_lop IS NOT NULL AND ma_lop != '' ORDER BY ma_lop ASC");
$classes = $stmt_classes->fetchAll();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Quản lý Lớp & Phân Quyền</title>
    <!-- Thêm Bootstrap 5 & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <!-- Liên kết file CSS chuẩn Admin -->
    <link rel="stylesheet" type="text/css" href="../css/admin-dashboard.css?v=<?php echo time(); ?>">
<style>
    /* Ép sạch lề và khoảng trống thừa ở đầu trang */
    html, body {
      margin: 0 !important;
      padding: 0 !important;
    }

    .container, 
    .admin-main-container, 
    .admin-header-box, 
    .header {
      margin-top: 0 !important;
      padding-top: 10px !important;
    }

    .video-background {
      top: 0 !important;
      left: 0 !important;
      position: fixed !important;
    }

    /* ==========================================================
       ÉP MÀU CHÍNH XÁC CHO CHẾ ĐỘ TỐI (DARK THEME) 
       ========================================================== */
    html[data-theme="dark"] body {
        background-color: #050515 !important;
        color: #ffffff !important;
    }

    html[data-theme="dark"] .approval-box,
    html[data-theme="dark"] .stat-card,
    html[data-theme="dark"] .stat-card-staff,
    html[data-theme="dark"] .filter-form {
        background: linear-gradient(135deg, rgba(15, 12, 35, 0.95), rgba(8, 6, 20, 0.95)) !important;
        border: 1px solid rgba(255, 255, 255, 0.15) !important;
        color: #ffffff !important;
    }

    html[data-theme="dark"] table, 
    html[data-theme="dark"] th, 
    html[data-theme="dark"] td {
        color: #ffffff !important;
        background-color: transparent !important;
    }

    html[data-theme="dark"] th {
        background-color: rgba(255, 255, 255, 0.05) !important;
        color: #cbd5e1 !important;
        border-bottom: 2px solid rgba(255, 255, 255, 0.1) !important;
    }

    html[data-theme="dark"] td {
        border-bottom: 1px solid rgba(255, 255, 255, 0.08) !important;
    }


    /* ==========================================================
       ÉP MÀU CHÍNH XÁC CHO CHẾ ĐỘ SÁNG (LIGHT THEME) 
       ========================================================== */
    html[data-theme="light"] body {
        background-color: #f1f5f9 !important;
        color: #1e293b !important;
    }

    html[data-theme="light"] .approval-box,
    html[data-theme="light"] .stat-card,
    html[data-theme="light"] .stat-card-staff,
    html[data-theme="light"] .filter-form {
        background: linear-gradient(135deg, rgba(255, 255, 255, 0.95), rgba(241, 245, 249, 0.95)) !important;
        border: 1px solid rgba(0, 0, 0, 0.15) !important;
        color: #1e293b !important;
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1) !important;
    }

    html[data-theme="light"] table, 
    html[data-theme="light"] th, 
    html[data-theme="light"] td {
        color: #1e293b !important;
        background-color: transparent !important;
    }

    html[data-theme="light"] th {
        background-color: rgba(0, 0, 0, 0.05) !important;
        color: #475569 !important;
        border-bottom: 2px solid rgba(0, 0, 0, 0.1) !important;
    }

    html[data-theme="light"] td {
        border-bottom: 1px solid rgba(0, 0, 0, 0.08) !important;
    }

    html[data-theme="light"] td.text-info {
        color: #0284c7 !important; 
    }

    html[data-theme="light"] .filter-form select {
        background: #ffffff !important;
        color: #1e293b !important;
        border: 1px solid rgba(0, 0, 0, 0.2) !important;
    }
</style>
</head>
<body>

    <!-- 🌌 HIỆU ỨNG LOADING LÕI LƯỢNG TỬ 3D (CYBERPUNK REACTOR) -->
    <div id="page-loader">
      <div class="shooting-star"></div>
      <div class="shooting-star"></div>
      
      <div class="quantum-reactor">
        <div class="reactor-ring outer"></div>
        <div class="reactor-ring middle"></div>
        <div class="reactor-ring inner"></div>
        <div class="reactor-core-pulse"></div>
      </div>
      
      <div class="loader-text">Khởi tạo hệ thống Admin...</div>
    </div>
    
    <!-- Video Background -->
    <video autoplay loop muted playsinline preload="auto" class="video-background">
        <source src="videos/video03.mp4" type="video/mp4">
        Trình duyệt của bạn không hỗ trợ video background.
    </video>

    <div class="container py-3">
        <!-- HEADER -->
        <div class="header">
            <h2>🛠️ Trang Quản Trị Hệ Thống (Admin)</h2>
            <div class="header-nav">
                <!-- Nút chuyển đổi Sáng/Tối 3D Neon -->
                <button class="theme-toggle-btn" id="themeToggleBtn" title="Chuyển đổi giao diện Sáng/Tối">
                  <i class="bi bi-moon-stars-fill" id="themeIcon"></i>
                </button>

                <!-- 🔔 CHUÔNG THÔNG BÁO -->
                <a href="#requests-section" class="notification-bell btn btn-sm position-relative" title="Yêu cầu nâng cấp quyền">
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
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
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
                                    <td><span style="color: var(--neon-green); font-weight: bold;"><?php echo htmlspecialchars($req['chuc_vu_mong_muon'] ?? 'Chưa rõ'); ?></span></td>
                                    <td><span style="font-style: italic; opacity: 0.8;"><?php echo htmlspecialchars($req['ly_do_nang_cap'] ?? 'Không có lý do'); ?></span></td>
                                    <td style="text-align: center;">
                                        <form method="POST" style="display: inline-block;">
                                            <input type="hidden" name="target_user_id" value="<?php echo $req['id']; ?>">
                                            <button type="submit" name="action_admin" value="approve" class="btn btn-success btn-sm fw-bold px-3 me-1">Duyệt</button>
                                            <button type="submit" name="action_admin" value="reject" class="btn btn-danger btn-sm fw-bold px-3">Từ chối</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <!-- 🛡️ QUẢN LÝ ĐIỂM DANH SỰ KIỆN -->
        <div class="approval-box mb-4">
            <h3 class="text-success mb-3 fw-bold">
                <i class="bi bi-shield-lock-fill me-2"></i> Quản lý Điểm Danh Sự Kiện (Mã Passcode 5 phút)
            </h3>
            
            <div class="table-responsive">
                <table class="table align-middle text-center mb-0">
                    <thead>
                        <tr>
                            <th style="width: 5%;">ID</th>
                            <th style="width: 30%; text-align: left; padding-left: 15px;">Tên sự kiện</th>
                            <th style="width: 15%;">Trạng thái điểm danh</th>
                            <th style="width: 15%;">Mã Passcode hiện tại</th>
                            <th style="width: 20%;">Hành động mở/đóng</th>
                            <th style="width: 15%;">Thống kê</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($events_list) > 0): ?>
                            <?php foreach ($events_list as $ev): ?>
                                <?php 
                                    $evId = $ev['ma_sukien'] ?? 0;
                                    $eventTitle = $ev['ten_sukien'] ?? 'Sự kiện chưa có tên'; 
                                    $isOpen = isset($ev['is_attendance_open']) && $ev['is_attendance_open'] == 1;
                                    $passcode = $isOpen ? ($ev['passcode'] ?? '------') : '------';
                                ?>
                                <tr>
                                    <td class="fw-bold"><?php echo htmlspecialchars($evId); ?></td>
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
                                <td colspan="6" class="text-center py-3">Chưa có sự kiện nào trong hệ thống.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 📊 THỐNG KÊ GRID -->
        <div class="stats-grid mb-4">
            <div class="stat-card">
                <h3>📊 Thống kê lớp: <?php echo htmlspecialchars($selected_class); ?></h3>
                <p class="mb-0">Tổng số sinh viên: <span class="fw-bold text-success"><?php echo $total_students; ?></span> người</p>
            </div>

            <div class="stat-card-staff">
                <h3>👔 Thống kê Nhân sự / Ban tổ chức</h3>
                <p class="mb-2">Tổng số Quản lý sự kiện: <span class="fw-bold text-info"><?php echo $total_staff; ?></span> người</p>

                <div class="staff-list-box">
                    <div class="staff-list-title fw-bold mb-1">📌 Danh sách Quản lý sự kiện hiện tại:</div>
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
                        <p style="font-size: 13px; opacity: 0.7; margin-top: 5px;">Chưa có quản lý sự kiện nào.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- DANH SÁCH SINH VIÊN (KÈM BỘ LỌC CHỌN LỚP HỌC ĐƯỢC ĐẶT GỌN GÀNG TRÊN CÙNG) -->
        <div class="approval-box">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <h3 class="mb-0">📋 Danh sách sinh viên & Điểm rèn luyện</h3>
                <!-- BỘ LỌC CHỌN LỚP HỌC -->
                <div class="filter-form mb-0 p-2 d-inline-flex align-items-center gap-2">
                    <label class="mb-0"><strong>Chọn lớp:</strong></label>
                    <select onchange="location = 'dashboard.php?ma_lop=' + this.value;" class="form-select form-select-sm w-auto">
                        <?php foreach ($classes as $cls): ?>
                            <option value="<?php echo htmlspecialchars($cls['ma_lop']); ?>" <?php echo ($cls['ma_lop'] === $selected_class) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cls['ma_lop']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="table-responsive">
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
                            <?php foreach ($students as $index => $sv): ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td><?php echo htmlspecialchars($sv['ma_sv']); ?></td>
                                    <td><strong><?php echo htmlspecialchars($sv['ho_ten']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($sv['email']); ?></td>
                                    <td>
                                        <span class="badge bg-info text-dark rounded-pill fw-bold">
                                            <?php echo $sv['so_su_kien_thang']; ?> sự kiện
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-warning text-dark rounded-pill fw-bold">
                                            +<?php echo $sv['tong_diem_ren_luyen']; ?> điểm
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($sv['trang_thai'] ?? 'Đang học'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-3">Không có sinh viên nào trong lớp này.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- JAVASCRIPT: LOADING & CINEMATIC THEME RIPPLE (1.4s) -->
    <script>
        // 🌌 HIỆU ỨNG LOADING CỔNG THIÊN HÀ (ĐÚNG 2 GIÂY)
        setTimeout(function() {
            const loader = document.getElementById('page-loader');
            if(loader && !loader.classList.contains('loaded')) {
                loader.classList.add('loaded');
            }
        }, 2000);

        // 🌗 XỬ LÝ CHUYỂN ĐỔI SÁNG / TỐI VỚI HIỆU ỨNG LOANG MÀU CINEMATIC CHẬM (1.4s)
        const toggleBtn = document.getElementById('themeToggleBtn');
        const themeIcon = document.getElementById('themeIcon');
        const currentTheme = localStorage.getItem('theme') || 'dark';

        document.documentElement.setAttribute('data-theme', currentTheme);
        updateIcon(currentTheme);

        if(toggleBtn) {
            toggleBtn.addEventListener('click', function(e) {
                let theme = document.documentElement.getAttribute('data-theme');
                let newTheme = theme === 'dark' ? 'light' : 'dark';

                const rect = toggleBtn.getBoundingClientRect();
                const x = rect.left + rect.width / 2;
                const y = rect.top + rect.height / 2;

                const ripple = document.createElement('div');
                ripple.className = 'theme-ripple-overlay';
                ripple.style.background = newTheme === 'dark' 
                    ? 'radial-gradient(circle, rgba(13,2,26,0.95) 0%, rgba(5,1,13,0.98) 60%, rgba(0,0,0,1) 100%)' 
                    : 'radial-gradient(circle, rgba(255,255,255,0.95) 0%, rgba(226,232,240,0.98) 60%, rgba(203,213,225,1) 100%)';
                
                ripple.style.left = `${x}px`;
                ripple.style.top = `${y}px`;
                ripple.style.marginLeft = '-60vh';
                ripple.style.marginTop = '-60vh';
                ripple.style.width = '120vh';
                ripple.style.height = '120vh';
                
                document.body.appendChild(ripple);

                setTimeout(() => {
                    ripple.classList.add('active');
                }, 30);

                setTimeout(() => {
                    document.documentElement.setAttribute('data-theme', newTheme);
                    localStorage.setItem('theme', newTheme);
                    updateIcon(newTheme);
                }, 700);

                setTimeout(() => {
                    ripple.remove();
                }, 1420);
            });
        }

        function updateIcon(theme) {
            if(!themeIcon) return;
            if(theme === 'dark') {
                themeIcon.className = 'bi bi-sun-fill';
            } else {
                themeIcon.className = 'bi bi-moon-stars-fill';
            }
        }
    </script>
</body>
</html>