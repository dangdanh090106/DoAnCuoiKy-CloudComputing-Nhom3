<?php
// BẮT BUỘC: Luôn đặt session_start() ở dòng đầu tiên của file index trước mọi mã HTML
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// (Tùy chọn) Nhúng file kết nối cơ sở dữ liệu của bạn nếu cần dùng ở dưới
// include 'database.php'; 
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hệ Thống Quản Lý Sự Kiện Sinh Viên</title>
    
    <!-- Nhúng 2 file CSS của bạn -->
    <link rel="stylesheet" type="text/css" href="./css/header.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" type="text/css" href="./css/index.css?v=<?php echo time(); ?>">
</head>
<body>

    <!-- 1. Chèn Video Background (tự động chạy lặp lại, tắt tiếng để trình duyệt cho phép tự phát) -->
    <video autoplay loop muted playsinline preload="auto" class="video-background">
        <source src="videos/video02.mp4" type="video/mp4">
        Trình duyệt của bạn không hỗ trợ video background.
    </video>

    <!-- 2. Nhúng file Header chứa Logo, Menu Phân Quyền và Khối Tài Khoản -->
    <?php include 'header.php'; ?>

    <!-- Banner Trang chủ (Hero Banner) -->
    <div class="hero-banner">
        <div class="hero-content">
            <h2>Sự Kiện Công Nghệ & Sinh Viên 2026</h2>
            <p>Cập nhật nhanh các hoạt động ngoại khóa, hội thảo chuyên đề và tích lũy điểm rèn luyện dễ dàng.</p>
            <a href="su-kien.php" class="hero-btn">Khám phá ngay</a>
        </div>
    </div>

    <main class="main-content">
        <div class="container">
            <!-- 1. Tiêu đề chào mừng -->
            <div class="welcome-section">
                <h1>Hệ Thống Quản Lý Sự Kiện Sinh Viên</h1>
                <p class="welcome-text">Chào mừng bạn đến với trang quản trị dự án Cloud Computing.</p>
            </div>

            <!-- 2. Thống kê nhanh (Quick Stats) -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>03</h3>
                    <p>Sự kiện sắp diễn ra</p>
                </div>
                <div class="stat-card">
                    <h3>120+</h3>
                    <p>Sinh viên tham gia</p>
                </div>
                <div class="stat-card">
                    <h3>+5 ĐRL</h3>
                    <p>Điểm rèn luyện trung bình</p>
                </div>
            </div>

            <!-- 2.5. Phần Giới thiệu trang web sự kiện sinh viên -->
            <div class="about-section">
                <div class="about-card">
                    <h3>Về Hệ Thống Sự Kiện Sinh Viên</h3>
                    <p>
                        Đây là trang web sự kiện sinh viên – nền tảng công nghệ tiên tiến được xây dựng nhằm kết nối sinh viên với các hoạt động ngoại khóa, 
                        hội thảo chuyên đề công nghệ và các sự kiện lớn của nhà trường. Với sự hỗ trợ của công nghệ 
                        <strong>Cloud Computing</strong>, hệ thống mang đến giải pháp quản lý minh bạch, giúp sinh viên 
                        dễ dàng cập nhật thông tin, đăng ký tham gia và tự động tích lũy điểm rèn luyện (ĐRL) một cách nhanh chóng nhất.
                    </p>
                    <div class="about-features">
                        <div class="feature-item">
                            <span>🚀</span> Cập nhật sự kiện 24/7
                        </div>
                        <div class="feature-item">
                            <span>📱</span> Điểm danh bằng mã QR thông minh
                        </div>
                        <div class="feature-item">
                            <span>⭐</span> Tích hợp điểm rèn luyện minh bạch
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- 3. Khu vực tính năng phân quyền & Sự kiện nổi bật -->
            <div class="dashboard-grid">
                <?php 
                // Kiểm tra trạng thái đăng nhập của Admin (hỗ trợ cả session admin hoặc user role admin)
                $isAdmin = (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) || 
                           (isset($_SESSION['user']) && is_array($_SESSION['user']) && $_SESSION['user']['role'] === 'admin');
                
                if ($isAdmin): 
                ?>
                    <!-- Giao diện hiển thị khi là Admin -->
                    <div class="card card-admin">
                        <h3>Chức năng Admin</h3>
                        <p>Bạn đang đăng nhập với quyền <strong>Quản trị viên</strong>. Bạn có thể quản lý sự kiện và phê duyệt dữ liệu sinh viên.</p>
                        <a href="admin/dashboard.php" class="card-btn">Đến trang quản lý Admin</a>
                    </div>
                <?php else: ?>
                    <!-- Giao diện hiển thị cho Sinh viên / Khách -->
                    <div class="card card-student">
                        <h3>Sự kiện của tôi</h3>
                        <p>Xem danh sách các sự kiện sắp diễn ra và đăng ký tham gia tích hợp điểm rèn luyện.</p>
                        <a href="su-kien.php" class="card-btn">Xem sự kiện</a>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </main>

</body>
</html>