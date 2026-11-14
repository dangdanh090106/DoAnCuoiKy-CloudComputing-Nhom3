<?php
session_start();
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../minio_storage.php';

$db = $pdo ?? $conn ?? null;
if (!$db instanceof PDO) {
    http_response_code(500);
    exit('Không tìm thấy kết nối cơ sở dữ liệu.');
}
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$vai_tro = strtolower(trim((string)($_SESSION['vai_tro'] ?? '')));
if (!in_array($vai_tro, ['admin', 'quan_ly_su_kien'], true)) {
    header('Location: login.php');
    exit();
}
if (empty($_SESSION['event_csrf_token'])) {
    $_SESSION['event_csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['event_csrf_token'];

function eventDeleteValidId($raw): ?int {
    $id = filter_var($raw, FILTER_VALIDATE_INT);
    return ($id !== false && $id > 0) ? (int)$id : null;
}
function eventDeleteHtml($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$delete_success = false;
$deleted_name = '';
$error_msg = '';
$storage_warning = '';

// URL GET chỉ dùng để CHỌN sự kiện, tuyệt đối không xóa bằng GET.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        if (($_POST['action'] ?? '') !== 'delete') {
            throw new InvalidArgumentException('Thao tác không hợp lệ.');
        }
        if (!hash_equals((string)$csrf_token, (string)($_POST['csrf_token'] ?? ''))) {
            throw new InvalidArgumentException('CSRF token không hợp lệ. Tải lại trang rồi thử lại.');
        }
        $id = eventDeleteValidId($_POST['ma_sukien'] ?? null);
        if ($id === null) throw new InvalidArgumentException('Mã sự kiện không hợp lệ.');

        $db->beginTransaction();
        $get = $db->prepare('SELECT ma_sukien, ten_sukien, file_path_key FROM su_kien WHERE ma_sukien = ? FOR UPDATE');
        $get->execute([$id]);
        $row = $get->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new InvalidArgumentException('Sự kiện không còn tồn tại.');
        $deleted_name = (string)$row['ten_sukien'];
        $oldKey = (string)($row['file_path_key'] ?? '');

        // dat_ghe trong bản SQL của nhóm không có FK CASCADE tới su_kien.
        $stmtSeats = $db->prepare('DELETE FROM dat_ghe WHERE su_kien_id = ?');
        $stmtSeats->execute([$id]);
        // Các đăng ký ở dang_ky / dang_ky_su_kien có FK ON DELETE CASCADE.
        $stmt = $db->prepare('DELETE FROM su_kien WHERE ma_sukien = ?');
        $stmt->execute([$id]);
        if ($stmt->rowCount() !== 1) throw new RuntimeException('Không thể xóa bản ghi sự kiện.');
        $db->commit();
        $delete_success = true;

        // Chỉ dọn object MinIO sau khi MySQL đã commit xóa thành công.
        if ($oldKey !== '') {
            try {
                // Không xóa object nếu có sự kiện khác vẫn tham chiếu cùng key.
                $countStmt = $db->prepare('SELECT COUNT(*) FROM su_kien WHERE file_path_key = ?');
                $countStmt->execute([$oldKey]);
                if ((int)$countStmt->fetchColumn() === 0) eventDeletePoster($oldKey);
            } catch (Throwable $e) {
                error_log('Đã xóa sự kiện nhưng chưa dọn được poster MinIO: ' . $e->getMessage());
                $storage_warning = 'Đã xóa sự kiện trong MySQL. Cần kiểm tra ảnh còn lại trên MinIO.';
            }
        }
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        if ($e instanceof InvalidArgumentException) {
            $error_msg = $e->getMessage();
        } else {
            error_log('Xóa sự kiện thất bại: ' . $e->getMessage());
            $error_msg = 'Không xóa được sự kiện. Có thể đang có dữ liệu liên quan. Hãy kiểm tra log Docker.';
        }
    }
}

try {
    $listStmt = $db->query('SELECT ma_sukien, ten_sukien, thoi_gian_bat_dau FROM su_kien ORDER BY thoi_gian_bat_dau DESC');
    $events = $listStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $events = [];
    error_log('Không tải được danh sách sự kiện khi xóa: ' . $e->getMessage());
    $error_msg = 'Không tải được danh sách sự kiện.';
}

$selectedId = eventDeleteValidId($_GET['ma_sukien'] ?? $_GET['id'] ?? null);
$selectedEvent = null;
if ($selectedId !== null) {
    foreach ($events as $one) {
        if ((int)$one['ma_sukien'] === $selectedId) {
            $selectedEvent = $one;
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Xóa sự kiện - Quản Lý Vũ Trụ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../css/user-delete.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <!-- Giữ nguyên các lớp hiệu ứng và CSS vũ trụ của nhóm. -->
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

        <?php if ($error_msg !== ''): ?>
            <div class="alert alert-danger shadow"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= eventDeleteHtml($error_msg) ?></div>
        <?php endif; ?>
        <?php if ($storage_warning !== ''): ?>
            <div class="alert alert-warning shadow"><?= eventDeleteHtml($storage_warning) ?></div>
        <?php endif; ?>

        <div class="card p-4 shadow">
            <h5 class="text-warning mb-3"><i class="bi bi-list-check me-2"></i>Chọn sự kiện bạn muốn xóa:</h5>
            <?php if (empty($events)): ?>
                <p class="text-muted mb-0">Chưa có sự kiện nào trong hệ thống.</p>
            <?php else: ?>
                <div class="list-group">
                    <?php foreach ($events as $item): ?>
                        <div class="list-group-item mb-2 d-flex justify-content-between align-items-center">
                            <div>
                                <i class="bi bi-calendar-event me-2 text-warning"></i>
                                <strong><?= eventDeleteHtml($item['ten_sukien']) ?></strong>
                                <small class="d-block opacity-75 mt-1"><i class="bi bi-clock me-1"></i><?= eventDeleteHtml($item['thoi_gian_bat_dau']) ?></small>
                            </div>
                            <button type="button" class="btn btn-danger btn-sm fw-bold px-3 delete-event-button"
                                data-id="<?= (int)$item['ma_sukien'] ?>"
                                data-name="<?= eventDeleteHtml($item['ten_sukien']) ?>">
                                <i class="bi bi-trash me-1"></i> Xóa
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Chỉ form POST + CSRF mới thực sự xóa. -->
    <form method="POST" action="delete.php" id="deleteEventForm" hidden>
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="ma_sukien" id="deleteEventId" value="">
        <input type="hidden" name="csrf_token" value="<?= eventDeleteHtml($csrf_token) ?>">
    </form>

    <script>
        function confirmDelete(id, name) {
            Swal.fire({
                title: 'XÁC NHẬN XÓA?',
                // Xử lý tên như văn bản, tránh chèn HTML không an toàn.
                text: 'Bạn có chắc chắn muốn xóa sự kiện "' + name + '"? Các lượt đăng ký và chỗ đã đặt của sự kiện cũng sẽ bị xóa. Hành động này không thể hoàn tác!',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ff1e56',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Xóa Ngay',
                cancelButtonText: 'Hủy Bỏ',
                background: '#0a0826',
                color: '#ffffff',
                customClass: { popup: 'swal-space-popup' }
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('deleteEventId').value = String(id);
                    document.getElementById('deleteEventForm').requestSubmit();
                }
            });
        }
        document.querySelectorAll('.delete-event-button').forEach((button) => {
            button.addEventListener('click', () => confirmDelete(button.dataset.id, button.dataset.name));
        });
        // Tương thích JS cũ: link delete.php?ma_sukien=... chỉ MỞ trang xác nhận.
        <?php if ($selectedEvent !== null && !$delete_success): ?>
        document.addEventListener('DOMContentLoaded', () => {
            confirmDelete(
                <?= (int)$selectedEvent['ma_sukien'] ?>,
                <?= json_encode((string)$selectedEvent['ten_sukien'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>
            );
        });
        <?php endif; ?>
    </script>

    <?php if ($delete_success): ?>
    <script>
        Swal.fire({
            title: '🎉 XÓA THÀNH CÔNG!',
            text: <?= json_encode('Đã xóa sự kiện "' . $deleted_name . '".' . ($storage_warning ? ' ' . $storage_warning : ''), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>,
            icon: 'success',
            background: '#0a0826',
            color: '#ffffff',
            confirmButtonText: 'ĐỒNG Ý',
            confirmButtonColor: '#00ffcc',
            customClass: { popup: 'swal-space-popup' }
        }).then(() => { window.location.href = 'delete.php'; });
    </script>
    <?php endif; ?>
</body>
</html>
