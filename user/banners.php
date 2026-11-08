<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$host = 'localhost';
$db   = 'do_an_cuoi_ky'; 
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

try {
    $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (\PDOException $e) {
    die("Lỗi kết nối CSDL: " . $e->getMessage());
}

$msg = '';

// Xử lý thêm banner mới (chỉ cần Tiêu đề và Ảnh)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_banner'])) {
    $title = trim($_POST['title']);
    
    if (isset($_FILES['banner_image']) && $_FILES['banner_image']['error'] === 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        $filename = $_FILES['banner_image']['name'];
        $filetmp = $_FILES['banner_image']['tmp_name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (in_array($ext, $allowed)) {
            $uploadDir = '../uploads/banners/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $newFilename = time() . '_' . basename($filename);
            $destination = $uploadDir . $newFilename;

            if (move_uploaded_file($filetmp, $destination)) {
                $imagePath = 'uploads/banners/' . $newFilename;

                $stmt = $pdo->prepare("INSERT INTO banners (title, image_url) VALUES (?, ?)");
                $stmt->execute([$title, $imagePath]);
                $msg = "Thêm banner thành công!";
            } else {
                $msg = "Lỗi khi lưu file hình ảnh.";
            }
        } else {
            $msg = "Chỉ chấp nhận định dạng ảnh jpg, jpeg, png, webp.";
        }
    } else {
        $msg = "Vui lòng chọn hình ảnh banner.";
    }
}

// Xử lý xóa banner
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $pdo->prepare("SELECT image_url FROM banners WHERE id = ?");
    $stmt->execute([$id]);
    $banner = $stmt->fetch();
    if ($banner && file_exists('../' . $banner['image_url'])) {
        unlink('../' . $banner['image_url']);
    }

    $delStmt = $pdo->prepare("DELETE FROM banners WHERE id = ?");
    $delStmt->execute([$id]);
    header("Location: banners.php");
    exit;
}

$banners = $pdo->query("SELECT * FROM banners ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Quản lý Banner</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-4 mb-5">
        <h2>Quản lý Banner Slide Trang Chủ</h2>
        <a href="dashboard.php" class="btn btn-secondary mb-3">Quay lại Dashboard</a>

        <?php if (!empty($msg)): ?>
            <div class="alert alert-info"><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>

        <!-- Form Thêm Banner -->
        <div class="card mb-4 shadow-sm">
            <div class="card-header bg-primary text-white">Thêm Banner Mới</div>
            <div class="card-body">
                <form action="" method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label class="form-label">Tiêu đề Banner:</label>
                        <input type="text" name="title" class="form-control" required placeholder="Nhập tiêu đề...">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Hình ảnh Banner:</label>
                        <input type="file" name="banner_image" class="form-control" accept="image/*" required>
                    </div>
                    <button type="submit" name="add_banner" class="btn btn-success">Đăng Banner</button>
                </form>
            </div>
        </div>

        <!-- Danh sách Banner -->
        <div class="card shadow-sm">
            <div class="card-header bg-secondary text-white">Danh sách Banner hiện tại</div>
            <div class="card-body">
                <table class="table table-bordered align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Hình ảnh</th>
                            <th>Tiêu đề</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($banners) > 0): ?>
                            <?php foreach ($banners as $b): ?>
                            <tr>
                                <td><?= $b['id'] ?></td>
                                <td><img src="../<?= htmlspecialchars($b['image_url']) ?>" alt="Banner" style="width: 150px; height: 50px; object-fit: cover;" class="rounded"></td>
                                <td><strong><?= htmlspecialchars($b['title']) ?></strong></td>
                                <td>
                                    <a href="banners.php?delete=<?= $b['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Bạn có chắc chắn muốn xóa?')">Xóa</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="4" class="text-center">Chưa có banner nào.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>