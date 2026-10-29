<?php
session_start();
require_once '../database.php';

// Tự động nhận diện biến kết nối PDO ($conn hoặc $pdo)
$db = isset($conn) ? $conn : (isset($pdo) ? $pdo : null);

if (!$db) {
    die("Lỗi: Không tìm thấy kết nối cơ sở dữ liệu.");
}

// Kiểm tra quyền quản trị
$vai_tro = $_SESSION['vai_tro'] ?? '';
$isManager = in_array(strtolower(trim($vai_tro)), ['admin', 'quan_ly_su_kien', 'quan_ly', 'management']);

if (!$isManager) {
    header("Location: login.php");
    exit();
}

// Hàm lấy giá trị cột linh hoạt
function getColValue($row, ...$keys) {
    if (!$row) return '';
    foreach ($keys as $k) {
        if (isset($row[$k]) && $row[$k] !== '') {
            return $row[$k];
        }
    }
    return '';
}

// Lấy danh sách toàn bộ cột thực tế trong bảng su_kien
$stmtCols = $db->query("SHOW COLUMNS FROM su_kien");
$existingCols = $stmtCols->fetchAll(PDO::FETCH_COLUMN);

// Hàm tìm tên cột chính xác tồn tại trong CSDL
function findRealCol($existingCols, $candidates, $default) {
    foreach ($candidates as $c) {
        if (in_array($c, $existingCols)) {
            return $c;
        }
    }
    return $default;
}

// Tự động phát hiện các cột thực tế
$colPk    = findRealCol($existingCols, ['ma_sukien', 'id', 'ma_sk', 'id_sukien'], 'ma_sukien');
$colTitle = findRealCol($existingCols, ['ten_su_kien', 'ten_sukien', 'tieu_de', 'ten'], 'ten_su_kien');
$colStart = findRealCol($existingCols, ['thoi_gian_bat_dau', 'bat_dau', 'ngay_to_chuc'], 'thoi_gian_bat_dau');

// Lấy ID cần xóa từ URL
$id = $_GET['ma_sukien'] ?? $_GET['id'] ?? $_GET['ma_sk'] ?? null;

$delete_success = false;
$deleted_name = "";
$error_msg = "";

// XỬ LÝ XÓA KHI CÓ ID
if ($id) {
    try {
        // Lấy tên sự kiện trước khi xóa để hiện thông báo chi tiết
        $stmtGet = $db->prepare("SELECT * FROM su_kien WHERE {$colPk} = ?");
        $stmtGet->execute([$id]);
        $eventToDelete = $stmtGet->fetch(PDO::FETCH_ASSOC);
        if ($eventToDelete) {
            $deleted_name = getColValue($eventToDelete, $colTitle, 'ten_su_kien', 'ten_sukien', 'tieu_de');
        }

        // Thực thi xóa
        $stmt = $db->prepare("DELETE FROM su_kien WHERE {$colPk} = ?");
        $stmt->execute([$id]);
        $delete_success = true;
    } catch (Exception $e) {
        $error_msg = "Lỗi khi xóa dữ liệu: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Xóa sự kiện - Quản Lý Vũ Trụ</title>
    <!-- Bootstrap 5 & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <!-- CSS Vũ Trụ 3D -->
    <link rel="stylesheet" href="../css/user-delete.css">
    <!-- SweetAlert2 (Popup Vũ Trụ 3D) -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <!-- LỚP HẠT SAO VÀ BÁNH RĂNG ĐỘNG CƠ CÔNG NGHỆ 3D -->
    <div class="starfield-layer-1"></div>
    <div class="starfield-layer-2"></div>
    <div class="bg-space-dust"></div>
    <div class="gear-engine-left"></div>
    <div class="gear-engine-right"></div>

    <div class="container" style="max-width: 850px;">
        <div class="d-flex justify-content-between align-items-center mb-4">
           <h3 class="text-warning mb-0"><i class="bi bi-trash-fill text-white me-2"></i>Quản Lý Xóa Sự Kiện</h3>
            <a href="../su-kien.php" class="btn btn-outline-light btn-sm"><i class="bi bi-arrow-left me-1"></i> Trở về trang sự kiện</a>
        </div>

        <?php if (!empty($error_msg)): ?>
            <div class="alert alert-danger shadow"><i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo htmlspecialchars($error_msg); ?></div>
        <?php endif; ?>

        <!-- GIAO DIỆN CHỌN SỰ KIỆN CẦN XÓA -->
        <div class="card p-4 shadow">
            <h5 class="text-warning mb-3"><i class="bi bi-list-check me-2"></i>Chọn sự kiện bạn muốn xóa:</h5>
            <?php
            try {
                $list = $db->query("SELECT * FROM su_kien ORDER BY {$colStart} DESC")->fetchAll(PDO::FETCH_ASSOC);
                if (empty($list)) {
                    echo "<p class='text-muted mb-0'>Chưa có sự kiện nào trong hệ thống.</p>";
                } else {
                    echo "<div class='list-group'>";
                    foreach ($list as $item) {
                        $item_id   = getColValue($item, $colPk, 'ma_sukien', 'id');
                        $item_name = getColValue($item, $colTitle, 'ten_su_kien', 'ten_sukien', 'tieu_de');
                        $item_time = getColValue($item, $colStart, 'thoi_gian_bat_dau');

                        echo "<div class='list-group-item mb-2 d-flex justify-content-between align-items-center'>
                                <div>
                                    <i class='bi bi-calendar-event me-2 text-warning'></i>
                                    <strong>" . htmlspecialchars($item_name) . "</strong>
                                    <small class='d-block opacity-75 mt-1'><i class='bi bi-clock me-1'></i>{$item_time}</small>
                                </div>
                                <button onclick='confirmDelete(\"{$item_id}\", \"" . addslashes(htmlspecialchars($item_name)) . "\")' class='btn btn-danger btn-sm fw-bold px-3'>
                                    <i class='bi bi-trash me-1'></i> Xóa
                                </button>
                              </div>";
                    }
                    echo "</div>";
                }
            } catch (Exception $e) {
                echo "<p class='text-danger'>Lỗi tải danh sách: " . $e->getMessage() . "</p>";
            }
            ?>
        </div>
    </div>

    <!-- JAVASCRIPT SWEETALERT2 TÙY CHỈNH VŨ TRỤ 3D -->
    <script>
        // Hàm hỏi xác nhận xóa phong cách Vũ Trụ 3D
        function confirmDelete(id, name) {
            Swal.fire({
                title: 'XÁC NHẬN XÓA?',
                html: 'Bạn có chắc chắn muốn xóa sự kiện:<br><b style="color: #00ffcc;">"' + name + '"</b>?<br><small style="color: #ff4d79;">Hành động này không thể hoàn tác!</small>',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ff1e56',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="bi bi-trash"></i> Xóa Ngay',
                cancelButtonText: 'Hủy Bỏ',
                background: '#0a0826',
                color: '#ffffff',
                customClass: {
                    popup: 'swal-space-popup'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'delete.php?ma_sukien=' + id;
                }
            });
        }
    </script>

    <!-- HỘP THÔNG BÁO XÓA THÀNH CÔNG VŨ TRỤ 3D -->
    <?php if ($delete_success): ?>
    <script>
        Swal.fire({
            title: '🎉 XÓA THÀNH CÔNG!',
            text: '<?php echo !empty($deleted_name) ? "Đã xóa sự kiện thành công!" : "Đã xóa dữ liệu thành công!"; ?>',
            icon: 'success',
            background: '#0a0826',
            color: '#ffffff',
            confirmButtonText: 'ĐỒNG Ý',
            confirmButtonColor: '#00ffcc',
            customClass: {
                popup: 'swal-space-popup'
            }
        }).then(() => {
            // Giữ nguyên người dùng ở lại trang delete.php để tiếp tục xóa sự kiện khác
            window.location.href = "delete.php";
        });
    </script>
    <?php endif; ?>
</body>
</html>