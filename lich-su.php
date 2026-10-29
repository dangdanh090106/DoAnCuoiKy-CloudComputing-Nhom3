<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'database.php';

$message = "";
$message_type = "";
$is_logged_in = false;
$history_list = [];

// 1. KIỂM TRA TRẠNG THÁI ĐĂNG NHẬP
if (isset($_SESSION['ma_sv']) && (isset($_SESSION['user_id']) || isset($_SESSION['id']))) {
    $is_logged_in = true;
    $ma_sv_hien_tai = $_SESSION['ma_sv'];
    $user_id_hien_tai = $_SESSION['user_id'] ?? $_SESSION['id'];

    // 2. XỬ LÝ HỦY ĐĂNG KÝ SỰ KIỆN (Chỉ chạy khi đã đăng nhập)
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'cancel') {
        $su_kien_id_huy = intval($_POST['su_kien_id']);
        
        if ($su_kien_id_huy > 0) {
            try {
                $conn->beginTransaction();

                $stmt_huy_dk = $conn->prepare("UPDATE dang_ky SET trang_thai = 'Đã hủy' WHERE user_id = :user_id AND su_kien_id = :su_kien_id");
                $stmt_huy_dk->execute([':user_id' => $user_id_hien_tai, ':su_kien_id' => $su_kien_id_huy]);

                $stmt_huy_ghe = $conn->prepare("UPDATE dat_ghe SET trang_thai = 'DA_HUY' WHERE user_id = :user_id AND su_kien_id = :su_kien_id");
                $stmt_huy_ghe->execute([':user_id' => $user_id_hien_tai, ':su_kien_id' => $su_kien_id_huy]);

                $conn->commit();
                $message = "Đã hủy vé thành công. Hẹn gặp lại bạn ở các sự kiện khác!";
                $message_type = "success";
            } catch (Exception $e) {
                $conn->rollBack();
                $message = "Lỗi hệ thống khi hủy vé: " . $e->getMessage();
                $message_type = "danger";
            }
        }
    }

    // 3. LẤY DANH SÁCH LỊCH SỬ ĐĂNG KÝ (Chỉ chạy khi đã đăng nhập)
    $sql_history = "
        SELECT dk.thoi_gian_dang_ky, dk.trang_thai as trang_thai_dk,
               sk.ma_sukien, sk.ten_sukien, sk.thoi_gian_bat_dau,
               dd.ten_diadiem,
               g.hang, g.so_ghe
        FROM dang_ky dk
        JOIN su_kien sk ON dk.su_kien_id = sk.ma_sukien
        LEFT JOIN dia_diem dd ON sk.ma_diadiem = dd.ma_diadiem
        LEFT JOIN dat_ghe dg ON dg.su_kien_id = sk.ma_sukien AND dg.user_id = :user_id AND dg.trang_thai = 'DA_DAT'
        LEFT JOIN ghe g ON dg.ghe_id = g.id
        WHERE dk.user_id = :user_id
        ORDER BY dk.thoi_gian_dang_ky DESC
    ";
    $stmt_history = $conn->prepare($sql_history);
    $stmt_history->execute([':user_id' => $user_id_hien_tai]);
    $history_list = $stmt_history->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lịch sử sự kiện</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/header.css">
    <link rel="stylesheet" href="css/su-kien.css">
    <link rel="stylesheet" href="css/lichsuuser.css">
</head>
<body>

    <video autoplay loop muted playsinline preload="auto" class="video-background">
        <source src="videos/video01.mp4" type="video/mp4">
    </video>
    <div class="video-overlay"></div>

    <?php include_once 'header.php'; ?>

    <main class="container main-history-container">
        <div class="history-card">
            <h2 class="text-center text-uppercase fw-bold text-success mb-4">
                <i class="bi bi-clock-history me-2"></i>Lịch sử tham gia sự kiện
            </h2>

            <?php if (!$is_logged_in): ?>
                <!-- Hiển thị thông báo khi chưa đăng nhập -->
                <div class="text-center py-5">
                    <i class="bi bi-shield-lock text-warning mb-3" style="font-size: 4rem;"></i>
                    <h4 class="fw-bold">Bạn cần đăng nhập để kiểm tra lịch sử</h4>
                    <p class="text-light opacity-75">Vui lòng đăng nhập vào hệ thống để xem và quản lý các sự kiện đã đăng ký của bạn.</p>
                </div>
            <?php else: ?>
                <!-- Hiển thị bảng dữ liệu khi đã đăng nhập -->
                <?php if (!empty($message)): ?>
                    <div class="alert alert-<?php echo $message_type; ?> text-center fw-bold border-0">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <div class="table-responsive">
                    <table class="table table-custom">
                        <thead>
                            <tr>
                                <th>Tên sự kiện</th>
                                <th>Thời gian bắt đầu</th>
                                <th>Địa điểm</th>
                                <th class="text-center">Vị trí ghế</th>
                                <th class="text-center">Trạng thái</th>
                                <th class="text-center">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($history_list) > 0): ?>
                                <?php foreach ($history_list as $row): 
                                    $is_cancelled = ($row['trang_thai_dk'] == 'Đã hủy');
                                    $is_past = (strtotime($row['thoi_gian_bat_dau']) < time());
                                ?>
                                <tr>
                                    <td class="fw-bold text-warning"><?php echo htmlspecialchars($row['ten_sukien']); ?></td>
                                    <td><?php echo date('d/m/Y - H:i', strtotime($row['thoi_gian_bat_dau'])); ?></td>
                                    <td><?php echo htmlspecialchars($row['ten_diadiem'] ?? 'Chưa cập nhật'); ?></td>
                                    
                                    <td class="text-center fw-bold text-info">
                                        <?php 
                                            if ($is_cancelled) {
                                                echo '<span class="text-muted fst-italic">Đã trả ghế</span>';
                                            } else {
                                                echo ($row['hang'] && $row['so_ghe']) ? $row['hang'].$row['so_ghe'] : 'Đang xếp';
                                            }
                                        ?>
                                    </td>
                                    
                                    <td class="text-center">
                                        <?php if ($is_cancelled): ?>
                                            <span class="badge bg-secondary badge-status">Đã hủy vé</span>
                                        <?php elseif ($is_past): ?>
                                            <span class="badge bg-primary badge-status">Đã kết thúc</span>
                                        <?php else: ?>
                                            <span class="badge bg-success badge-status">Đã đăng ký</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td class="text-center">
                                        <?php if (!$is_cancelled && !$is_past): ?>
                                            <form method="POST" onsubmit="return confirm('Bạn có chắc chắn muốn hủy tham gia sự kiện này? Ghế ngồi của bạn sẽ được nhường cho người khác.');">
                                                <input type="hidden" name="action" value="cancel">
                                                <input type="hidden" name="su_kien_id" value="<?php echo $row['ma_sukien']; ?>">
                                                <button type="submit" class="btn btn-outline-danger btn-sm fw-bold">
                                                    <i class="bi bi-x-circle me-1"></i>Hủy vé
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <button class="btn btn-outline-secondary btn-sm fw-bold" disabled>Không khả dụng</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center text-light py-4 fst-italic">
                                        Bạn chưa đăng ký tham gia sự kiện nào. 
                                        <br><a href="su-kien.php" class="btn btn-success btn-sm mt-2">Xem sự kiện ngay</a>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>