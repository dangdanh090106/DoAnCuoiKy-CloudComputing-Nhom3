<?php
declare(strict_types=1);

/**
 * Trang quản lý Banner an toàn hơn (thay cho user/banners.php).
 * Yêu cầu: database.php cung cấp $pdo (PDO), đăng nhập lưu user_id, vai_tro trong session.
 * Chức năng hiện có: xem, thêm, sửa tiêu đề/ảnh, xóa Banner.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');

// Chặn truy cập trước khi tải hoặc thực thi bất cứ thao tác DB nào.
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    exit('Bạn cần đăng nhập để quản lý Banner.');
}
if (!in_array($_SESSION['vai_tro'] ?? '', ['admin', 'quan_ly_su_kien'], true)) {
    http_response_code(403);
    exit('Bạn không có quyền quản lý Banner.');
}

require_once __DIR__ . '/../database.php';
if (!isset($pdo) || !$pdo instanceof PDO) {
    http_response_code(500);
    exit('Không kết nối được cơ sở dữ liệu.');
}

function bannerEsc(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function bannerCsrf(): string
{
    // Chia sẻ khóa token với các API sự kiện, địa điểm, Banner.
    if (empty($_SESSION['event_csrf_token'])) {
        $_SESSION['event_csrf_token'] = bin2hex(random_bytes(32));
    }
    return (string) $_SESSION['event_csrf_token'];
}

function bannerCheckCsrf(): void
{
    $provided = $_POST['csrf_token'] ?? null;
    if (!is_string($provided) || !hash_equals(bannerCsrf(), $provided)) {
        http_response_code(403);
        exit('CSRF token không hợp lệ. Tải lại trang và thử lại.');
    }
}

function bannerId(mixed $raw): ?int
{
    $id = filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    return $id === false ? null : (int) $id;
}

function bannerUpload(array $file): ?string
{
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
        throw new RuntimeException('Upload ảnh thất bại.');
    }
    if (!isset($file['size']) || $file['size'] <= 0 || $file['size'] > 5 * 1024 * 1024) {
        throw new InvalidArgumentException('Dung lượng ảnh phải từ 1 byte đến 5 MB.');
    }
    $tmp = (string) $file['tmp_name'];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    $types = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($types[$mime])) {
        throw new InvalidArgumentException('Chỉ chấp nhận ảnh JPG, PNG hoặc WebP.');
    }
    $dimensions = @getimagesize($tmp);
    if ($dimensions === false || ($dimensions[0] ?? 0) < 1 || ($dimensions[1] ?? 0) < 1 || $dimensions[0] > 8000 || $dimensions[1] > 8000) {
        throw new InvalidArgumentException('Ảnh không hợp lệ hoặc có kích thước quá lớn.');
    }

    $dir = __DIR__ . '/../uploads/banners';
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('Không tạo được thư mục uploads/banners.');
    }
    $name = bin2hex(random_bytes(16)) . '.' . $types[$mime];
    if (!move_uploaded_file($tmp, $dir . DIRECTORY_SEPARATOR . $name)) {
        throw new RuntimeException('Không lưu được ảnh trên máy chủ.');
    }
    return 'uploads/banners/' . $name;
}

function bannerDeleteImageIfUnused(PDO $db, ?string $url): void
{
    if (!$url || !preg_match('~\Auploads/banners/([a-zA-Z0-9._() -]+\.(?:jpg|jpeg|png|webp))\z~iD', $url, $matches)) {
        return;
    }
    $check = $db->prepare('SELECT COUNT(*) FROM banners WHERE image_url = ?');
    $check->execute([$url]);
    if ((int) $check->fetchColumn() > 0) {
        return; // Không được xóa ảnh khi Banner khác còn dùng.
    }
    $dir = realpath(__DIR__ . '/../uploads/banners');
    if ($dir === false) return;
    $file = realpath($dir . DIRECTORY_SEPARATOR . $matches[1]);
    if ($file === false || dirname($file) !== $dir || !is_file($file)) return;
    if (!@unlink($file)) {
        error_log('Không dọn được ảnh Banner: ' . $file);
    }
}

$message = $_SESSION['banner_flash'] ?? '';
unset($_SESSION['banner_flash']);
$error = '';
$editData = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    bannerCheckCsrf();
    $newImage = null;
    try {
        $action = (string) ($_POST['action'] ?? 'save');
        if ($action === 'delete') {
            $id = bannerId($_POST['id'] ?? null);
            if ($id === null) throw new InvalidArgumentException('Mã Banner không hợp lệ.');
            $pdo->beginTransaction();
            $find = $pdo->prepare('SELECT image_url FROM banners WHERE id = ? FOR UPDATE');
            $find->execute([$id]);
            $oldImage = $find->fetchColumn();
            if ($oldImage === false) throw new InvalidArgumentException('Không tìm thấy Banner cần xóa.');
            $delete = $pdo->prepare('DELETE FROM banners WHERE id = ?');
            $delete->execute([$id]);
            $pdo->commit();
            bannerDeleteImageIfUnused($pdo, (string) $oldImage);
            $_SESSION['banner_flash'] = 'Đã xóa Banner thành công.';
        } elseif ($action === 'save') {
            $title = trim((string) ($_POST['title'] ?? ''));
            $length = function_exists('mb_strlen') ? mb_strlen($title, 'UTF-8') : strlen($title);
            if ($title === '' || $length > 255) {
                throw new InvalidArgumentException('Tiêu đề phải từ 1 đến 255 ký tự.');
            }
            $rawId = $_POST['edit_id'] ?? '';
            $isEdit = $rawId !== '';
            $editId = $isEdit ? bannerId($rawId) : null;
            if ($isEdit && $editId === null) throw new InvalidArgumentException('Mã Banner cần sửa không hợp lệ.');

            $oldImage = null;
            if ($isEdit) {
                $find = $pdo->prepare('SELECT image_url FROM banners WHERE id = ?');
                $find->execute([$editId]);
                $oldImage = $find->fetchColumn();
                if ($oldImage === false) throw new InvalidArgumentException('Không tìm thấy Banner cần sửa.');
            }

            $newImage = bannerUpload($_FILES['banner_image'] ?? []);
            if (!$isEdit && $newImage === null) {
                throw new InvalidArgumentException('Vui lòng chọn ảnh Banner.');
            }
            if ($isEdit) {
                if ($newImage !== null) {
                    $stmt = $pdo->prepare('UPDATE banners SET title = ?, image_url = ? WHERE id = ?');
                    $stmt->execute([$title, $newImage, $editId]);
                } else {
                    $stmt = $pdo->prepare('UPDATE banners SET title = ? WHERE id = ?');
                    $stmt->execute([$title, $editId]);
                }
                if ($newImage !== null) bannerDeleteImageIfUnused($pdo, (string) $oldImage);
                $_SESSION['banner_flash'] = 'Cập nhật Banner thành công.';
            } else {
                $stmt = $pdo->prepare('INSERT INTO banners (title, image_url) VALUES (?, ?)');
                $stmt->execute([$title, $newImage]);
                $_SESSION['banner_flash'] = 'Thêm Banner thành công.';
            }
        } else {
            throw new InvalidArgumentException('Thao tác không hợp lệ.');
        }
        header('Location: banners.php', true, 303);
        exit;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        // Nếu upload thành công nhưng ghi DB thất bại, xóa file mồ côi.
        if ($newImage !== null) bannerDeleteImageIfUnused($pdo, $newImage);
        $error = $exception instanceof InvalidArgumentException ? $exception->getMessage() : 'Không thể xử lý Banner. Kiểm tra log PHP/Docker.';
        error_log('user/banners.php: ' . $exception->getMessage());
    }
}

if (isset($_GET['edit'])) {
    $editId = bannerId($_GET['edit']);
    if ($editId !== null) {
        $stmtEdit = $pdo->prepare('SELECT * FROM banners WHERE id = ?');
        $stmtEdit->execute([$editId]);
        $editData = $stmtEdit->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}
$banners = $pdo->query('SELECT * FROM banners ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
$csrfToken = bannerCsrf();
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Quản lý Banner Slide Trang Chủ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-4 mb-5">
    <h2>Quản lý Banner Slide Trang Chủ</h2>
    <a href="dashboard.php" class="btn btn-secondary mb-3">Quay lại Dashboard</a>

    <?php if ($message): ?>
        <div class="alert alert-success" role="status"><?= bannerEsc($message) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger" role="alert"><?= bannerEsc($error) ?></div>
    <?php endif; ?>

    <div class="card mb-4 shadow-sm">
        <div class="card-header bg-primary text-white">
            <?= $editData ? 'Chỉnh sửa Banner (ID: ' . bannerEsc($editData['id']) . ')' : 'Thêm Banner Mới (Kích thước gợi ý: 1000 x 400 px)' ?>
        </div>
        <div class="card-body">
            <form action="banners.php" method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= bannerEsc($csrfToken) ?>">
                <input type="hidden" name="action" value="save">
                <?php if ($editData): ?>
                    <input type="hidden" name="edit_id" value="<?= bannerEsc($editData['id']) ?>">
                <?php endif; ?>
                <div class="mb-3">
                    <label class="form-label" for="banner-title">Tiêu đề Banner:</label>
                    <input id="banner-title" type="text" name="title" maxlength="255" class="form-control" required
                           placeholder="Nhập tiêu đề..." value="<?= $editData ? bannerEsc($editData['title']) : '' ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="banner-image">Hình ảnh Banner (gợi ý 1000 × 400 px, tối đa 5 MB):</label>
                    <?php if ($editData && !empty($editData['image_url'])): ?>
                        <div class="mb-2">
                            <img src="../<?= bannerEsc($editData['image_url']) ?>" alt="Ảnh Banner hiện tại"
                                 style="width: 200px; height: 80px; object-fit: cover;" class="rounded border">
                            <br><small class="text-muted">Ảnh hiện tại (chọn ảnh mới nếu muốn thay đổi)</small>
                        </div>
                    <?php endif; ?>
                    <input id="banner-image" type="file" name="banner_image" class="form-control"
                           accept="image/jpeg,image/png,image/webp" <?= $editData ? '' : 'required' ?>>
                </div>
                <button type="submit" class="btn btn-success"><?= $editData ? 'Cập nhật Banner' : 'Đăng Banner' ?></button>
                <?php if ($editData): ?>
                    <a href="banners.php" class="btn btn-secondary">Hủy bỏ</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-secondary text-white">Danh sách Banner hiện tại</div>
        <div class="card-body table-responsive">
            <table class="table table-bordered align-middle">
                <thead><tr><th>ID</th><th>Hình ảnh</th><th>Tiêu đề</th><th>Thao tác</th></tr></thead>
                <tbody>
                <?php if ($banners): ?>
                    <?php foreach ($banners as $b): ?>
                        <tr>
                            <td><?= bannerEsc($b['id']) ?></td>
                            <td><img src="../<?= bannerEsc($b['image_url']) ?>" alt="Banner" class="rounded"
                                     style="width: 150px; height: 60px; object-fit: cover;"></td>
                            <td><strong><?= bannerEsc($b['title']) ?></strong></td>
                            <td>
                                <div class="d-flex gap-2 flex-wrap">
                                    <a href="banners.php?edit=<?= rawurlencode((string) $b['id']) ?>"
                                       class="btn btn-warning btn-sm text-white">Sửa</a>
                                    <form action="banners.php" method="post"
                                          onsubmit="return confirm('Bạn có chắc chắn muốn xóa Banner này?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= bannerEsc($b['id']) ?>">
                                        <input type="hidden" name="csrf_token" value="<?= bannerEsc($csrfToken) ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">Xóa</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="4" class="text-center">Chưa có Banner nào.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>
