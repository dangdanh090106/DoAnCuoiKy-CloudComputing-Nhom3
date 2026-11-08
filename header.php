<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
                    <li>
                        <a href="/QUAN-LY-SU-KIEN-SINH-VIEN/index.php">Trang chủ</a>
                    </li>
                    <li>
                      <a href="/QUAN-LY-SU-KIEN-SINH-VIEN/su-kien.php">Sự kiện</a>
                    </li>
                    <li>
                       <a href="/QUAN-LY-SU-KIEN-SINH-VIEN/lich-su.php">Lịch sử</a>
                    </li>
                    <li>
                      <a href="/QUAN-LY-SU-KIEN-SINH-VIEN/dang-ky.php">Đăng ký</a>
                    </li>
                </ul>
            </nav>

            <!-- KHU VỰC CÁC NÚT: Cấu trúc xếp dọc hoàn toàn (Flex-direction: column) -->
            <div class="header-actions-vertical">
                <?php 
                $isAdmin = (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) || 
                           (isset($_SESSION['user']) && is_array($_SESSION['user']) && isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'admin');
                $isLoggedIn = isset($_SESSION['user_id']) || isset($_SESSION['username']);
                ?>

                <!-- 1. Nút Đăng nhập / Tài khoản -->
                <?php if ($isLoggedIn): ?>
                    <a href="user/dashboard.php" class="btn-custom-3d btn-user-login">
                        👤 <?= htmlspecialchars($_SESSION['username'] ?? 'Tài khoản') ?>
                    </a>
                <?php else: ?>
                    <a href="user/login.php" class="btn-custom-3d btn-user-login">Đăng nhập</a>
                <?php endif; ?>

                <!-- 2. Nút Admin -->
                <?php if ($isAdmin): ?>
                    <a href="admin/dashboard.php" class="btn-custom-3d btn-admin-nav">⚙ Quản Trị</a>
                <?php else: ?>
                    <a href="admin/dashboard.php" class="btn-custom-3d btn-admin-login">⚙ Quản Trị</a>
                <?php endif; ?>

                <!-- 3. Nút Đăng xuất (Nằm ở dưới cùng khi người dùng đã đăng nhập) -->
                <?php if ($isLoggedIn): ?>
                    <a href="user/logout.php" class="btn-custom-3d btn-user-logout">Đăng xuất</a>
                <?php endif; ?>
            </div>

        </div>
    </div>
</header>

<!-- CSS B BỐ CỤC XẾP DỌC HOÀN TOÀN CẢ 3 NÚT -->
<style>
/* Ép toàn bộ khối chứa nút xếp thành 1 cột dọc */
.header-actions-vertical {
  display: flex !important;
  flex-direction: column !important;
  align-items: stretch !important; /* Các nút có độ rộng đồng đều */
  gap: 8px !important;
  min-width: 140px; /* Đảm bảo độ rộng hợp lý cho cột nút */
}

/* Kiểu dáng 3D chung cho các nút */
.btn-custom-3d {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 8px 16px;
  font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
  font-size: 13px;
  font-weight: 700;
  text-decoration: none;
  text-transform: uppercase;
  letter-spacing: 0.8px;
  color: #ffffff !important;
  border: none;
  border-radius: 10px;
  cursor: pointer;
  user-select: none;
  overflow: hidden;
  transition: all 0.15s cubic-bezier(0.4, 0, 0.2, 1);
  white-space: nowrap;
  text-align: center;
}

/* Nút Đăng nhập / Tài khoản / Admin (Màu Đỏ - Xanh lục 3D) */
.btn-user-login, .btn-admin-nav, .btn-admin-login {
  background: linear-gradient(135deg, #e11d48 0%, #059669 100%);
  box-shadow: 0 4px 0 #047857, 0 6px 12px rgba(5, 150, 105, 0.3), inset 0 2px 0 rgba(255, 255, 255, 0.35);
}
.btn-user-login:hover, .btn-admin-nav:hover, .btn-admin-login:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 0 #047857, 0 10px 16px rgba(5, 150, 105, 0.4), inset 0 2px 0 rgba(255, 255, 255, 0.45);
}
.btn-user-login:active, .btn-admin-nav:active, .btn-admin-login:active {
  transform: translateY(2px);
  box-shadow: 0 2px 0 #047857, 0 3px 6px rgba(5, 150, 105, 0.3), inset 0 1px 0 rgba(0, 0, 0, 0.2);
}

/* Nút Đăng xuất (Màu Đỏ tươi 3D nằm dưới cùng) */
.btn-user-logout {
  background: linear-gradient(180deg, #ff4b4b 0%, #dc2626 100%);
  box-shadow: 0 4px 0 #991b1b, 0 6px 12px rgba(220, 38, 38, 0.4), inset 0 2px 0 rgba(255, 255, 255, 0.35);
}
.btn-user-logout:hover {
  transform: translateY(-2px);
  background: linear-gradient(180deg, #ff5c5c 0%, #ef4444 100%);
  box-shadow: 0 6px 0 #991b1b, 0 10px 16px rgba(239, 68, 68, 0.5), inset 0 2px 0 rgba(255, 255, 255, 0.45);
}
.btn-user-logout:active {
  transform: translateY(2px);
  box-shadow: 0 2px 0 #991b1b, 0 3px 6px rgba(220, 38, 38, 0.3), inset 0 1px 0 rgba(0, 0, 0, 0.2);
}

/* Hiệu ứng lóe sáng khi di chuột qua nút */
.btn-custom-3d::before {
  content: '';
  position: absolute;
  top: 0;
  left: -100%;
  width: 100%;
  height: 100%;
  background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
  transition: left 0.6s ease;
}
.btn-custom-3d:hover::before {
  left: 100%;
}
</style>

<script>
    document.addEventListener("DOMContentLoaded", function () {
        const currentPath = window.location.pathname.split("/").pop().split("?")[0];
        const menuLinks = document.querySelectorAll(".main-menu a");

        menuLinks.forEach(link => {
            const href = link.getAttribute("href");
            if (href) {
                const linkFileName = href.split("/").pop().split("?")[0];
                if (linkFileName === currentPath) {
                    link.classList.add("active");
                }
            }
        });
    });
</script>
<script src="js/heder.js"></script>