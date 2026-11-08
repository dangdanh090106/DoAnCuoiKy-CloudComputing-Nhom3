<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Tự động lấy tên file hiện tại để so sánh và bật class active
$current_page = basename($_SERVER['PHP_SELF']);
?>
<header class="site-header">
    <div class="header-container">
        <!-- Khối Logo và Tên Website -->
        <a href="index.php" class="brand-area">
            <img src="./images/logo.png" alt="Logo" class="main-logo" onerror="this.style.display='none'">
            <span class="site-title">Student Event Management</span>
        </a>

        <!-- Khối Menu và Phân quyền bên phải -->
        <div class="right-header-content">
            <nav class="main-menu">
                <ul>
                    <!-- Tự động thêm class active và sửa lại đường dẫn không bị lỗi 404 -->
                    <li>
                        <a href="index.php" class="<?= ($current_page == 'index.php') ? 'active' : '' ?>">Trang chủ</a>
                    </li>
                    <li>
                        <a href="su-kien.php" class="<?= ($current_page == 'su-kien.php') ? 'active' : '' ?>">Sự kiện</a>
                    </li>
                    <li>
                        <a href="lich-su.php" class="<?= ($current_page == 'lich-su.php') ? 'active' : '' ?>">Lịch sử</a>
                    </li>
                    <li>
                        <a href="dang-ky.php" class="<?= ($current_page == 'dang-ky.php') ? 'active' : '' ?>">Đăng ký</a>
                    </li>
                </ul>
            </nav>

            <div class="user-action">
                <?php 
                // Kiểm tra xem Admin đã đăng nhập chưa
                $isAdmin = (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) || 
                           (isset($_SESSION['user']) && is_array($_SESSION['user']) && isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'admin');
                
                // Kiểm tra xem Người dùng (User) đã đăng nhập chưa
                $isUser = isset($_SESSION['user_id']) || isset($_SESSION['username']);
                ?>

                <!-- 1. KHU VỰC DÀNH CHO NGƯỜI DÙNG (USER) -->
                <div class="user-action-inner" style="display: inline-block;">
                    <?php if ($isUser): ?>
                        <a href="user/dashboard.php" class="btn-user-login">
                            👤 <?= htmlspecialchars($_SESSION['username'] ?? 'Tài khoản') ?>
                        </a>
                        <a href="user/logout.php" class="btn-user-logout">Đăng xuất</a>
                    <?php else: ?>
                        <a href="user/login.php" class="btn-user-login">USER LOGIN</a>
                    <?php endif; ?>
                </div>

                <!-- 2. KHU VỰC DÀNH CHO ADMIN -->
                <div class="admin-box" style="display: inline-block; border-left: 1px solid rgba(255,255,255,0.2); padding-left: 12px; margin-left: 8px;">
                    <?php if ($isAdmin): ?>
                        <!-- Khi đã đăng nhập Admin, hiển thị nút Quản Trị -->
                        <a href="admin/dashboard.php" class="btn-admin-nav">⚙ Quản Trị</a>
                    <?php else: ?>
                        <!-- Nếu chưa đăng nhập thì hiển thị nút Admin Login -->
                        <a href="admin/login.php" class="btn-login">Admin login</a>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </div>
</header>
<script src="js/heder.js"></script>