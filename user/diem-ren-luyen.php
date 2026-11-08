<?php
// Bắt đầu session và kết nối cơ sở dữ liệu
session_start();
include '../database.php'; 
$conn = $conn ?? $pdo ?? null;

$ma_sv = $_SESSION['ma_sv'] ?? '';
$user_id = $_SESSION['user_id'] ?? '';

if (empty($ma_sv)) {
    header("Location: login.php");
    exit();
}

// 1. Truy vấn thông tin người dùng
$stmt_user = $conn->prepare("SELECT * FROM users WHERE ma_sv = ?");
$stmt_user->execute([$ma_sv]);
$user = $stmt_user->fetch(PDO::FETCH_ASSOC);

// 2. Lấy danh sách các sự kiện sinh viên đã tham gia thành công
$stmt_history = $conn->prepare("
    SELECT sk.*, dk.trang_thai 
    FROM dang_ky_su_kien dk 
    JOIN su_kien sk ON dk.su_kien_id = sk.ma_sukien 
    WHERE dk.user_id = ? AND dk.trang_thai = 'da_tham_gia'
");
$stmt_history->execute([$user_id]);
$diem_schedules = $stmt_history->fetchAll(PDO::FETCH_ASSOC);

// Tự động tính tổng điểm dựa trên các sự kiện đã tham gia
$tong_diem = 0;
foreach ($diem_schedules as $row) {
    $tong_diem += ($row['diem_thuong'] ?? 5);
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Điểm Rèn Luyện - <?php echo htmlspecialchars($user['ho_ten'] ?? ''); ?></title>
    <!-- Bootstrap 5 & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <!-- CSS Hệ thống & CSS Điểm Rèn Luyện -->
    <link rel="stylesheet" type="text/css" href="../css/user-dashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" type="text/css" href="../css/điểm rèn luyện.css?v=<?php echo time(); ?>">
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
  <div class="loader-text">Đang tải hồ sơ điểm rèn luyện...</div>
</div>

<div class="container py-4">
    <!-- HEADER ĐỒNG BỘ THEME SANH TRỌNG -->
    <div class="header d-flex justify-content-between align-items-center mb-4">
        <h2>
            <i class="bi bi-award-fill title-icon me-2"></i>
            <span class="welcome-text">Điểm Rèn Luyện</span> 
            <span class="student-name">Sinh Viên</span>
        </h2>
        <div class="header-nav">
            <!-- Nút chuyển đổi Sáng/Tối 3D Neon -->
            <button class="theme-toggle-btn" id="themeToggleBtn" title="Chuyển đổi giao diện Sáng/Tối">
              <i class="bi bi-moon-stars-fill" id="themeIcon"></i>
            </button>

            <a href="dashboard.php" class="btn btn-outline-light btn-sm me-2">
                <i class="bi bi-arrow-left"></i> Quay lại Dashboard
            </a>
            <a href="logout.php" class="btn btn-danger btn-sm">
                <i class="bi bi-box-arrow-right"></i> Đăng xuất
            </a>
        </div>
    </div>

    <!-- Thẻ hiển thị tổng điểm & Quy chế -->
    <div class="row mb-4 g-4">
        <div class="col-md-4">
            <div class="card score-card text-center py-4 h-100">
                <div class="card-body d-flex flex-column justify-content-center align-items-center">
                    <h5 class="card-title text-muted-custom mb-2">Tổng Điểm Tích Lũy</h5>
                    <div class="score-display my-2">
                        <span class="score-number"><?php echo $tong_diem; ?></span>
                        <span class="score-unit">điểm</span>
                    </div>
                    <span class="badge bg-green-glow mt-2">Hệ thống đồng bộ tự động</span>
                </div>
            </div>
        </div>
        <div class="col-md-8">
            <div class="card info-card h-100">
                <div class="card-body d-flex flex-column justify-content-center p-4">
                    <h5 class="card-title text-accent-cyan mb-3">
                        <i class="bi bi-info-circle-fill me-2"></i> Quy chế tích điểm rèn luyện:
                    </h5>
                    <p class="card-text text-main-custom mb-2">
                        <i class="bi bi-check-circle-fill text-neon-green me-2"></i> Điểm rèn luyện được tự động liên kết và tổng hợp từ các sự kiện mà bạn đã điểm danh thành công bằng Passcode.
                    </p>
                    <p class="card-text text-main-custom mb-0">
                        <i class="bi bi-shield-check text-neon-red me-2"></i> Mọi thắc mắc về điểm số vui lòng liên hệ Ban tổ chức sự kiện hoặc Phòng Công tác sinh viên.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Bảng lịch sử liên kết sự kiện -->
    <div class="card history-card">
        <div class="card-header history-header d-flex align-items-center">
            <i class="bi bi-clock-history me-2 text-neon-green fs-5"></i> 
            <span>Lịch sử sự kiện đã tham gia</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table cyber-table mb-0 align-middle">
                    <thead>
                        <tr>
                            <th class="ps-4">STT</th>
                            <th>Tên sự kiện</th>
                            <th>Trạng thái</th>
                            <th class="text-end pe-4">Điểm được cộng</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($diem_schedules)): ?>
                            <?php $stt = 1; foreach ($diem_schedules as $row): ?>
                                <tr>
                                    <td class="ps-4 text-muted-custom fw-bold"><?php echo sprintf("%02d", $stt++); ?></td>
                                    <td>
                                        <div class="event-name-box">
                                            <i class="bi bi-calendar2-event me-2 text-neon-green"></i>
                                            <strong><?php echo htmlspecialchars($row['ten_sukien'] ?? $row['tieu_de'] ?? 'Sự kiện'); ?></strong>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge status-badge-success">
                                            <i class="bi bi-check2-all me-1"></i> Đã tham gia (Điểm danh thành công)
                                        </span>
                                    </td>
                                    <td class="text-end pe-4">
                                        <span class="score-plus">+<?php echo htmlspecialchars($row['diem_thuong'] ?? 5); ?> điểm</span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center py-5">
                                    <div class="empty-state">
                                        <i class="bi bi-calendar-x fs-1 text-muted d-block mb-2"></i>
                                        <span class="text-muted-custom">Bạn chưa tham gia sự kiện nào để được tính điểm rèn luyện.</span>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- JS ĐỒNG BỘ LOANG MÀU THEME SÁNG TỐI & PAGE LOADER -->
<script>
    // 🌌 HIỆU ỨNG LOADING CỔNG THIÊN HÀ (ĐÚNG 2 GIÂY)
    setTimeout(function() {
        const loader = document.getElementById('page-loader');
        if(loader && !loader.classList.contains('loaded')) {
            loader.classList.add('loaded');
        }
    }, 2000);

    // 🌗 XỬ LÝ CHUYỂN ĐỔI SÁNG / TỐI VỚI HIỆU ỨNG LOANG MÀU CINEMATIC PRO
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
            }, 500);

            setTimeout(() => {
                ripple.remove();
            }, 1020);
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