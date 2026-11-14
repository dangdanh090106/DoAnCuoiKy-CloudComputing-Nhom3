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
    header("Location: /user/login.php");
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

// Chỉ hiển thị popup điểm danh cho sự kiện sinh viên thực sự đã đăng ký.
// Bảng dang_ky dùng ma_sv và ma_sukien (không dùng user_id hoặc su_kien_id).
$active_event = null;
if (!empty($ma_sv) && $vai_tro === 'sinh_vien') {
    $stmt_active = $conn->prepare("
        SELECT sk.*
        FROM su_kien sk
        WHERE sk.is_attendance_open = 1
          AND sk.passcode IS NOT NULL
          AND EXISTS (
              SELECT 1 FROM dang_ky dk
              WHERE dk.ma_sukien = sk.ma_sukien
                AND dk.ma_sv = ?
                AND dk.trang_thai IN ('Đã đăng ký', 'da_dang_ky', 'registered', 'confirmed')
          )
          AND NOT EXISTS (
              SELECT 1 FROM dang_ky checked
              WHERE checked.ma_sukien = sk.ma_sukien
                AND checked.ma_sv = ?
                AND checked.trang_thai IN ('da_tham_gia', 'present', 'Đã tham gia')
          )
        ORDER BY sk.passcode_expired_at ASC
    ");
    $stmt_active->execute([$ma_sv, $ma_sv]);

    // Dùng cùng cách kiểm tra thời hạn với api_checkin.php (theo timezone PHP).
    // Không hiển thị mã đã hết hạn hoặc không có thời điểm hết hạn.
    while ($candidate = $stmt_active->fetch(PDO::FETCH_ASSOC)) {
        $expiresAt = !empty($candidate['passcode_expired_at'])
            ? strtotime($candidate['passcode_expired_at'])
            : false;
        if ($expiresAt !== false && $expiresAt > time()) {
            $active_event = $candidate;
            break;
        }
    }
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
    <!-- Gọi file CSS tùy chỉnh siêu cấp -->
    <link rel="stylesheet" type="text/css" href="../css/user-dashboard.css?v=<?php echo time(); ?>">
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>

    <!-- 🌌 HIỆU ỨNG LOADING CỔNG THIÊN HÀ 3D (CHẠY CHẬM 2 GIÂY ĐẦY ĐỦ SAO BĂNG) -->
    <div id="page-loader">
      <div class="shooting-star"></div>
      <div class="shooting-star"></div>
      <div class="galaxy-portal">
        <div class="portal-ring-red"></div>
        <div class="portal-ring-green"></div>
        <div class="portal-ring-purple"></div>
        <div class="portal-core"></div>
      </div>
      <div class="loader-text">Welcome back...</div>
    </div>

    <div class="container py-4">
        <!-- HEADER -->
        <div class="header d-flex justify-content-between align-items-center mb-4">
            <h2>
                <span class="wave-icon">👋</span> 
                <span class="welcome-text">Chào mừng,</span> 
                <span class="student-name"><?php echo htmlspecialchars($user['ho_ten'] ?? ''); ?></span>
            </h2>
            <div class="header-nav">
                <!-- Nút chuyển đổi Sáng/Tối 3D Neon -->
                <button class="theme-toggle-btn" id="themeToggleBtn" title="Chuyển đổi giao diện Sáng/Tối">
                  <i class="bi bi-moon-stars-fill" id="themeIcon"></i>
                </button>
            

                <a href="#" data-bs-toggle="modal" data-bs-target="#notificationsModal" role="button" class="notification-bell btn btn-outline-secondary btn-sm position-relative me-2" title="Thông báo hệ thống">
                    🔔 Thông báo
                    <?php if ($has_notification): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">1</span>
                    <?php endif; ?>
                </a>
                <a href="../index.php" class="btn btn-outline-light btn-sm me-2">Trang chủ</a>
                <a href="/user/logout.php" class="btn btn-danger btn-sm">Đăng xuất</a>
            </div>
        </div>

        <!-- Thông báo phân quyền: dữ liệu lấy từ bảng users, không tạo thông báo giả -->
        <div class="modal fade" id="notificationsModal" tabindex="-1" aria-labelledby="notificationsModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content" style="background:#100b20;color:#f5f5fa;border:1px solid #55ddc5;border-radius:18px;">
                    <div class="modal-header" style="border-color:#344055;">
                        <h5 class="modal-title" id="notificationsModalLabel"><i class="bi bi-bell-fill me-2"></i>Thông báo của bạn</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Đóng"></button>
                    </div>
                    <div class="modal-body">
                        <?php if ($yeu_cau === 'approved'): ?>
                            <div class="alert alert-success mb-0">
                                <strong><i class="bi bi-check-circle-fill me-2"></i>Yêu cầu đã được phê duyệt</strong>
                                <p class="mb-0 mt-2">Admin đã phê duyệt yêu cầu nâng quyền quản lý sự kiện của bạn.</p>
                            </div>
                        <?php elseif ($yeu_cau === 'rejected'): ?>
                            <div class="alert alert-danger mb-0">
                                <strong><i class="bi bi-x-circle-fill me-2"></i>Yêu cầu bị từ chối</strong>
                                <p class="mb-0 mt-2">Admin đã từ chối yêu cầu nâng quyền quản lý sự kiện của bạn.</p>
                            </div>
                        <?php elseif ($yeu_cau === 'pending'): ?>
                            <div class="alert alert-warning mb-0">
                                <strong><i class="bi bi-hourglass-split me-2"></i>Đang chờ phê duyệt</strong>
                                <p class="mb-0 mt-2">Yêu cầu nâng quyền của bạn đã được gửi và đang chờ Admin xét duyệt.</p>
                            </div>
                        <?php else: ?>
                            <p class="mb-0 text-center py-3" style="color:#cbd5e1;">Hiện chưa có thông báo mới về yêu cầu phân quyền.</p>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer" style="border-color:#344055;">
                        <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Đóng</button>
                        <button type="button" class="btn btn-success" data-bs-dismiss="modal" onclick="document.getElementById('roleSectionContent') && bootstrap.Collapse.getOrCreateInstance(document.getElementById('roleSectionContent'), {toggle:false}).show(); document.getElementById('role-section').scrollIntoView({behavior:'smooth',block:'start'});">Xem trạng thái chi tiết</button>
                    </div>
                </div>
            </div>
        </div>

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

        <!-- 🔑 KHU VỰC YÊU CẦU NÂNG CẤP QUYỀN (THU GỌN MẶC ĐỊNH KHI VÀO TRANG) -->
        <div id="role-section" class="role-section card p-4 shadow-sm">
            <div class="card-header-toggle collapsed d-flex justify-content-between align-items-center" data-bs-toggle="collapse" data-bs-target="#roleSectionContent" aria-expanded="false" aria-controls="roleSectionContent" style="cursor: pointer;">
                <h3>🔒 Đăng ký & Trạng thái Quản lý sự kiện</h3>
                <i class="bi bi-chevron-down toggle-arrow fs-4"></i>
            </div>

            <div class="collapse mt-3" id="roleSectionContent">
                <?php if ($yeu_cau === 'approved'): ?>
                    <div class="alert alert-success mt-2">
                        <p class="fw-bold mb-1">🎉 Chúc mừng! Yêu cầu của bạn đã được Admin phê duyệt.</p>
                        <p class="mb-0">Bạn đã chính thức trở thành <b>Quản lý sự kiện</b>.</p>
                    </div>
                <?php elseif ($yeu_cau === 'rejected'): ?>
                    <div class="alert alert-danger mt-2">
                        <p class="fw-bold mb-0">❌ Rất tiếc! Yêu cầu nâng cấp quyền của bạn đã bị từ chối.</p>
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
                        <div class="p-3 rounded border border-warning bg-warning bg-opacity-10 mt-2">
                            <p class="mb-1 text-warning fw-bold">⏳ Yêu cầu của bạn đang chờ Admin duyệt.</p>
                            <p class="mb-1 small">Chức vụ đăng ký: <strong><?php echo htmlspecialchars($chuc_vu_cu); ?></strong></p>
                            <p class="mb-1 small">Câu lạc bộ: <strong><?php echo htmlspecialchars($ten_clb_cu); ?></strong></p>
                            <p class="mb-0 small">Lý do: <em><?php echo htmlspecialchars($ly_do_cu); ?></em></p>
                        </div>
                    <?php else: ?>
                        <form method="POST" class="mt-3">
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
                    <p class="text-success fw-bold mb-2 mt-3">
                        ✔ Tài khoản của bạn đã có quyền quản lý sự kiện!
                    </p>
                    <a href="quan_ly_su_kien.php" class="btn btn-success btn-manager">
                        👉 Đi tới Trang đăng & quản lý sự kiện
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- 🚀 MODAL PASSCODE ĐIỂM DANH -->
    <?php if ($active_event): ?>
    <div class="modal fade" id="autoAttendanceModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow-lg">
                <div class="modal-header text-center position-relative">
                    <h5 class="modal-title text-success fw-bold w-100">
                        <i class="bi bi-shield-lock-fill me-2"></i>Điểm Danh Sự Kiện
                    </h5>
                    <button type="button" class="btn-close position-absolute" style="right: 15px;" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <div class="modal-body">
                    <input type="hidden" id="event_id" value="<?php echo htmlspecialchars($active_event['ma_sukien']); ?>">
                    <p class="mb-2 text-center">Sự kiện: <strong class="text-info"><?php echo htmlspecialchars($active_event['ten_sukien'] ?? $active_event['tieu_de']); ?></strong> đang diễn ra.</p>
                    <p class="text-muted small mb-3 text-center">Nhập mã Passcode 6 chữ số do Ban tổ chức cung cấp để xác nhận điểm danh.</p>
                    
                    <div class="mb-3 text-center px-4">
                        <input type="text" id="passcode" class="form-control form-control-lg text-center fw-bold fs-3 text-warning border-0" required maxlength="6" autocomplete="off" placeholder="------" style="letter-spacing: 5px;">
                    </div>
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-success px-5 fw-bold" id="btnSubmitCheckin">Xác Nhận</button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- XỬ LÝ JAVASCRIPT: LOADING, COLLAPSE ARROW & LOANG MÀU SÁNG/TỐI -->
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            <?php if ($active_event): ?>
            var myModal = new bootstrap.Modal(document.getElementById('autoAttendanceModal'));
            myModal.show();

            const btnCheckin = document.getElementById('btnSubmitCheckin');
            if(btnCheckin) {
                btnCheckin.addEventListener('click', function() {
                    var eventId = document.getElementById('event_id').value;
                    var passcode = document.getElementById('passcode').value;

                    if (passcode.trim().length === 0) {
                        Swal.fire('Lỗi', 'Vui lòng nhập mã Passcode!', 'error');
                        return;
                    }

                    fetch('../api_checkin.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ event_id: eventId, passcode: passcode })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success') {
                            Swal.fire({
                                title: 'Thành công!',
                                text: data.message,
                                icon: 'success',
                                confirmButtonText: 'Tuyệt vời'
                            }).then(() => { window.location.reload(); });
                        } else if (data.status === 'warning') {
                            Swal.fire('Thông báo', data.message, 'warning').then(() => { myModal.hide(); });
                        } else {
                            Swal.fire('Lỗi', data.message, 'error');
                        }
                    })
                    .catch(error => {
                        Swal.fire('Lỗi hệ thống', 'Không thể kết nối đến máy chủ điểm danh.', 'error');
                    });
                });
            }
            <?php endif; ?>

            // Xử lý xoay mũi tên khi thu gọn / mở bảng
            const toggleHeader = document.querySelector('.card-header-toggle');
            const targetCollapse = document.getElementById('roleSectionContent');

            if (toggleHeader && targetCollapse) {
                targetCollapse.addEventListener('hide.bs.collapse', function () {
                    toggleHeader.classList.add('collapsed');
                });
                targetCollapse.addEventListener('show.bs.collapse', function () {
                    toggleHeader.classList.remove('collapsed');
                });
            }
        });

        // 🌌 HIỆU ỨNG LOADING CHẠY CHẬM ĐÚNG 2 GIÂY
        setTimeout(function() {
            hideLoader();
        }, 1500);

        function hideLoader() {
            const loader = document.getElementById('page-loader');
            if(loader && !loader.classList.contains('loaded')) {
                loader.classList.add('loaded');
            }
        }

        // 🌗 XỬ LÝ CHUYỂN ĐỔI SÁNG / TỐI VỚI HIỆU ỨNG LOANG MÀU NGHỆ THUẬT (RIPPLE)
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
                    ? 'radial-gradient(circle, #0d021a 0%, #05010d 70%, #000000 100%)' 
                    : 'radial-gradient(circle, #ffffff 0%, #e2e8f0 70%, #cbd5e1 100%)';
                ripple.style.left = `${x}px`;
                ripple.style.top = `${y}px`;
                ripple.style.marginLeft = '-50vh';
                ripple.style.marginTop = '-50vh';
                ripple.style.width = '100vh';
                ripple.style.height = '100vh';
                
                document.body.appendChild(ripple);

                setTimeout(() => {
                    ripple.classList.add('active');
                }, 20);

                setTimeout(() => {
                    document.documentElement.setAttribute('data-theme', newTheme);
                    localStorage.setItem('theme', newTheme);
                    updateIcon(newTheme);
                }, 400);

                setTimeout(() => {
                    ripple.remove();
                }, 820);
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