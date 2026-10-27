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
    <title>Điểm Rèn Luyện - <?php echo htmlspecialchars($user['ho_ten'] ?? ''); ?></title>
    <!-- Bootstrap 5 & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" type="text/css" href="../css/user-dashboard.css?v=<?php echo time(); ?>">
</head>
<body>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom border-secondary">
        <h2><i class="bi bi-award-fill text-warning me-2"></i> Điểm Rèn Luyện Sinh Viên</h2>
        <div>
            <a href="dashboard.php" class="btn btn-outline-light btn-sm me-2"><i class="bi bi-arrow-left"></i> Quay lại Dashboard</a>
            <a href="logout.php" class="btn btn-danger btn-sm"><i class="bi bi-box-arrow-right"></i> Đăng xuất</a>
        </div>
    </div>

    <!-- Thẻ hiển thị tổng điểm -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card bg-dark text-white border-success shadow-lg">
                <div class="card-body text-center py-4">
                    <h5 class="card-title text-muted mb-2">Tổng Điểm Tích Lũy</h5>
                    <h1 class="display-4 fw-bold text-success mb-0">
                        <?php echo $tong_diem; ?> <span class="fs-5">điểm</span>
                    </h1>
                </div>
            </div>
        </div>
        <div class="col-md-8">
            <div class="card bg-dark text-white border-secondary h-100 shadow-sm">
                <div class="card-body d-flex flex-column justify-content-center">
                    <h5 class="card-title text-info mb-2"><i class="bi bi-info-circle-fill me-1"></i> Quy chế tích điểm:</h5>
                    <p class="card-text text-muted mb-1">
                        <i class="bi bi-check2 text-success me-1"></i> Điểm rèn luyện được tự động liên kết và tổng hợp từ các sự kiện mà bạn đã điểm danh thành công.
                    </p>
                    <p class="card-text text-muted mb-0">
                        <i class="bi bi-check2 text-success me-1"></i> Mọi thắc mắc về điểm số vui lòng liên hệ Ban tổ chức.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Bảng lịch sử liên kết sự kiện -->
    <div class="card bg-dark text-white border-secondary shadow-sm">
        <div class="card-header bg-secondary bg-opacity-25 fw-bold">
            <i class="bi bi-clock-history me-2"></i> Lịch sử sự kiện đã tham gia
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-dark table-striped table-hover mb-0 align-middle">
                    <thead>
                        <tr>
                            <th class="ps-3">STT</th>
                            <th>Tên sự kiện</th>
                            <th>Trạng thái</th>
                            <th class="text-end pe-3">Điểm được cộng</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($diem_schedules)): ?>
                            <?php $stt = 1; foreach ($diem_schedules as $row): ?>
                                <tr>
                                    <td class="ps-3"><?php echo $stt++; ?></td>
                                    <td><strong><?php echo htmlspecialchars($row['ten_sukien'] ?? 'Sự kiện'); ?></strong></td>
                                    <td><span class="badge bg-success">Đã tham gia (Điểm danh thành công)</span></td>
                                    <td class="text-end pe-3 text-warning fw-bold">+<?php echo htmlspecialchars($row['diem_thuong'] ?? 5); ?> điểm</td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">Bạn chưa tham gia sự kiện nào để được tính điểm rèn luyện.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>