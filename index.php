<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include_once 'database.php'; 

// Truy vấn danh sách banner slide từ CSDL
$listBanners = [];
try {
    if (isset($pdo)) {
        $stmtBanners = $pdo->query("SELECT * FROM banners ORDER BY id DESC");
        $listBanners = $stmtBanners->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    $listBanners = [];
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hệ Thống Quản Lý Sự Kiện Sinh Viên</title>
    
    <!-- CSS Hệ thống -->
    <link rel="stylesheet" type="text/css" href="./css/header.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" type="text/css" href="./css/index.css?v=<?php echo time(); ?>">
    <!-- CSS Banner 3D Glassmorphic -->
    <link rel="stylesheet" type="text/css" href="./css/banner.css?v=<?php echo time(); ?>">
    
    <!-- Bootstrap CSS để chạy Slide Carousel -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

    <!-- 1. Video Background -->
    <video autoplay loop muted playsinline preload="auto" class="video-background">
        <source src="videos/video02.mp4" type="video/mp4">
        Trình duyệt của bạn không hỗ trợ video background.
    </video>

    <!-- 2. Header -->
    <?php include 'header.php'; ?>

    <!-- 3. Khu vực Slide Banner Trang chủ (Tự động chuyển trang sau mỗi 4 giây & Kích thước 1000x400px) -->
    <div class="container my-4 d-flex justify-content-center">
        <?php if (!empty($listBanners)): ?>
            <div id="homeBannerCarousel" class="carousel slide" data-bs-ride="carousel" data-bs-interval="4000">
                <!-- Thanh chỉ số phía dưới -->
                <div class="carousel-indicators">
                    <?php foreach ($listBanners as $index => $banner): ?>
                        <button type="button" data-bs-target="#homeBannerCarousel" data-bs-slide-to="<?= $index ?>" <?= $index === 0 ? 'class="active" aria-current="true"' : '' ?>></button>
                    <?php endforeach; ?>
                </div>

                <!-- Khung chứa danh sách slide ảnh -->
                <div class="carousel-inner">
                    <?php foreach ($listBanners as $index => $banner): ?>
                        <div class="carousel-item <?= $index === 0 ? 'active' : '' ?>">
                            <img src="./<?= htmlspecialchars($banner['image_url']) ?>" class="d-block w-100" alt="<?= htmlspecialchars($banner['title']) ?>">
                            
                            <!-- Khung tiêu đề kính mờ Glassmorphism nổi bật -->
                            <div class="carousel-caption d-none d-md-block">
                                <h5 class="banner-title"><?= htmlspecialchars($banner['title']) ?></h5>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Nút điều hướng Trái / Phải -->
                <button class="carousel-control-prev" type="button" data-bs-target="#homeBannerCarousel" data-bs-slide="prev">
                    <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                    <span class="visually-hidden">Previous</span>
                </button>
                <button class="carousel-control-next" type="button" data-bs-target="#homeBannerCarousel" data-bs-slide="next">
                    <span class="carousel-control-next-icon" aria-hidden="true"></span>
                    <span class="visually-hidden">Next</span>
                </button>
            </div>
        <?php else: ?>
            <!-- Giao diện dự phòng khi chưa có banner nào -->
            <div class="hero-banner w-100 text-center text-white">
                <div class="hero-content">
                    <h2>Sự Kiện Công Nghệ & Sinh Viên 2026</h2>
                    <p>Cập nhật nhanh các hoạt động ngoại khóa, hội thảo chuyên đề và tích lũy điểm rèn luyện dễ dàng.</p>
                    <a href="su-kien.php" class="hero-btn btn btn-primary mt-2">Khám phá ngay</a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- 4. Nội dung chính của Trang chủ -->
    <main class="main-content">
        <div class="container">
            <!-- Tiêu đề chào mừng -->
            <div class="welcome-section">
                <h1>Hệ Thống Quản Lý Sự Kiện Sinh Viên</h1>
                <p class="welcome-text">Chào mừng bạn đến với trang quản trị dự án Cloud Computing.</p>
            </div>

            <!-- Thống kê nhanh -->
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

            <!-- Phần Giới thiệu hệ thống -->
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
            
            <!-- Khu vực thẻ tính năng phân quyền -->
            <div class="dashboard-grid">
                <?php 
                $isAdmin = (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) || 
                           (isset($_SESSION['user']) && is_array($_SESSION['user']) && $_SESSION['user']['role'] === 'admin');
                
                if ($isAdmin): 
                ?>
                    <div class="card card-admin">
                        <h3>Chức năng Admin</h3>
                        <p>Bạn đang đăng nhập với quyền <strong>Quản trị viên</strong>. Bạn có thể quản lý sự kiện và phê duyệt dữ liệu sinh viên.</p>
                        <a href="admin/dashboard.php" class="card-btn">Đến trang quản lý Admin</a>
                    </div>
                <?php else: ?>
                    <div class="card card-student">
                        <h3>Sự kiện của tôi</h3>
                        <p>Xem danh sách các sự kiện sắp diễn ra và đăng ký tham gia tích hợp điểm rèn luyện.</p>
                        <a href="su-kien.php" class="card-btn">Xem sự kiện</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Bootstrap JS Bundle để kích hoạt hiệu ứng chuyển slide tự động -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>