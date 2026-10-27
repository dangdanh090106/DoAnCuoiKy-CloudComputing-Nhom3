<?php
session_start();
// Hiển thị nút Đăng sự kiện nếu là Quản lý sự kiện hoặc Admin
if (isset($_SESSION['vai_tro']) && ($_SESSION['vai_tro'] === 'quan_ly_su_kien' || $_SESSION['vai_tro'] === 'admin')): 
?>
<div style="text-align: right; margin: 20px 20px 0 0;">
    <a href="user/quan_ly_su_kien.php" class="btn-space-manager">
        <!-- 🌟 ĐỔI DẤU + CŨ THÀNH ICON PHÁT SÁNG CAO CẤP -->
        <i class="bi bi-plus-circle-fill"></i> Đăng & Bổ Sung Sự Kiện Mới
    </a>
   <!-- Nút Xóa sự kiện -->
<a href="user/delete.php" class="btn-space-danger">
    <i class="bi bi-gear-fill"></i> Xóa
</a>

<!-- Nút Sửa sự kiện -->
<a href="user/xuly_sua.php" class="btn-space-warning">
    <i class="bi bi-gear-fill"></i> Sửa
</a>
</div>

<?php endif; ?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Danh sách sự kiện</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/header.css">
    <link rel="stylesheet" type="text/css" href="./css/nut-su-kien.css?v=<?php echo time(); ?>">
        <link rel="stylesheet" type="text/css" href="./css/model-su-kien.css?v=<?php echo time(); ?>">
  <link rel="stylesheet" type="text/css" href="./css/su-kien.css?v=<?php echo time(); ?>">
<link rel="stylesheet" href="css/header.css">
    <!-- CSS để biến Video thành Background toàn màn hình -->
<!-- CSS để biến Video thành Background toàn màn hình -->


</head>
<body>

    <!-- Chèn Video Background (autoplay: tự chạy, loop: lặp lại, muted: tắt tiếng bắt buộc để trình duyệt cho phép tự chạy) -->
    <!-- Cập nhật lại thẻ video của bạn thành thế này -->
<video autoplay loop muted playsinline preload="auto" class="video-background">
    <source src="videos/video01.mp4" type="video/mp4">
    Trình duyệt của bạn không hỗ trợ video background.
</video>

    
    <!-- Lớp phủ tối giúp chữ màu trắng/xanh của bạn không bị chìm vào video -->
    <div class="video-overlay"></div>

    <?php include_once 'header.php'; ?>

    <main class="container-fluid event-container">
        <h1 class="text-center text-uppercase event-heading">
            Danh sách sự kiện
        </h1>
        
        <div class="search-box-wrapper">
            <i class="bi bi-search search-icon-inside"></i>
            <div class="input-group">
                <input type="text" id="searchInput" class="form-control custom-search-input" placeholder="Tìm kiếm sự kiện..." autocomplete="off">
                <button class="btn btn-custom-search" type="button" id="btnSearch">
                    Tìm kiếm
                </button>
            </div>
        </div>

        <div id="event-list" class="row">
            <p class="event-message">
                <i class="bi bi-arrow-repeat me-2"></i> Đang tải danh sách sự kiện.
            </p>
        </div>
    </main>

    <!-- Cửa sổ xem chi tiết và Đăng ký kết hợp phân tách giao diện linh hoạt -->
    <div class="modal fade" id="eventDetailModal" tabindex="-1" aria-labelledby="detailTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content" style="background-color: #1a1d29; color: #e2e8f0; border: 1px solid #2d3142;">
                <div class="modal-header" style="border-bottom: 1px solid #2d3142;">
                    <h5 class="modal-title text-success fw-bold" id="detailTitle">
                        Chi tiết sự kiện
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Đóng">
                    </button>
                </div>
                <div class="modal-body">
                    <!-- KHỐI THÔNG TIN -->
                    <div id="modalEventInfo">
                        <div class="detail-info mb-2">
                            <i class="bi bi-upc-scan text-success me-2"></i>
                            <div>
                                <span class="detail-label">Mã sự kiện:</span>
                                <span id="detailId" class="fw-bold"></span>
                            </div>
                        </div>
                        <div class="detail-info mb-2">
                            <i class="bi bi-calendar-event text-success me-2"></i>
                            <div>
                                <span class="detail-label">Thời gian bắt đầu:</span>
                                <span id="detailStart"></span>
                            </div>
                        </div>
                        <div class="detail-info mb-2">
                            <i class="bi bi-calendar-check text-success me-2"></i>
                            <div>
                                <span class="detail-label">Thời gian kết thúc:</span>
                                <span id="detailEnd"></span>
                            </div>
                        </div>
                        <div class="detail-info mb-2">
                            <i class="bi bi-geo-alt text-success me-2"></i>
                            <div>
                                <span class="detail-label">Địa điểm:</span>
                                <span id="detailLocation"></span>
                            </div>
                        </div>
                        <div class="detail-info mb-2">
                            <i class="bi bi-pin-map text-success me-2"></i>
                            <div>
                                <span class="detail-label">Vị trí:</span>
                                <span id="detailAddress"></span>
                            </div>
                        </div>
                        <div class="detail-info mb-2">
                            <i class="bi bi-people text-success me-2"></i>
                            <div>
                                <span class="detail-label">Đối tượng tham gia:</span>
                                <span id="detailAudience"></span>
                            </div>
                        </div>
                        <div class="detail-info mb-3">
                            <i class="bi bi-person-check text-success me-2"></i>
                            <div>
                                <span class="detail-label">Sức chứa:</span>
                                <span id="detailCapacity"></span>
                            </div>
                        </div>
                        <hr style="border-color:#444;">
                        <h6 style="color:#34d399;" class="fw-bold">Mô tả và nội dung sự kiện</h6>
                        <p id="detailDescription" class="mb-3 text-muted small"></p>
                    </div>

                    <!-- KHỐI BIỂU MẪU -->
                    <div id="modalRegisterSection">
                        <hr id="modalRegisterDivider" style="border-color:#444;">
                        <h6 style="color:#10b981;" class="fw-bold mb-3">
                            <i class="bi bi-pencil-square me-2"></i>BẠN CHẮC CHẮN MUỐN THAM GIA CHỨ?
                        </h6>
                        <div id="registerAlert" class="alert d-none mb-3" style="font-size: 14px; padding: 10px;"></div>
                        <form id="modalRegisterForm">
                            <input type="hidden" id="formEventId">
                            <div class="text-center mt-3">
    <a id="btnRegisterModal" href="dang-ky.php" class="btn btn-danger w-100 fw-bold py-2">
        <i class="bi bi-pencil-square me-2"></i>Đăng ký tham gia
    </a>
</div>
                        </form>
                    </div>
                </div>
                <div class="modal-footer" style="border-top: 1px solid #2d3142;">
                    <button type="button" class="btn btn-secondary w-100" data-bs-dismiss="modal">
                        Đóng
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/su-kien.js"></script>
    <!-- Truyền quyền từ PHP sang JavaScript -->
<script>
    const canManageEvents = <?php echo (isset($_SESSION['vai_tro']) && ($_SESSION['vai_tro'] === 'quan_ly_su_kien' || $_SESSION['vai_tro'] === 'admin')) ? 'true' : 'false'; ?>;

</script>

<!-- Sau đó mới gọi file js -->
<script src="js/su-kien.js"></script>
</body>
</html>
