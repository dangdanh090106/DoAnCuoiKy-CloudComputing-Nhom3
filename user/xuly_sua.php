<?php
// Sửa sự kiện: giữ giao diện cũ; ghi poster lên MinIO + metadata vào MySQL.
session_start();
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../minio_storage.php';

$db = $pdo ?? $conn ?? null;
if (!$db instanceof PDO) {
    http_response_code(500);
    exit('Không thể kết nối cơ sở dữ liệu.');
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

function getColValue($row, ...$keys): string {
    if (!$row) return '';
    foreach ($keys as $key) {
        if (isset($row[$key]) && $row[$key] !== '') return (string)$row[$key];
    }
    return '';
}
function validEventId($value): ?int {
    $id = filter_var($value, FILTER_VALIDATE_INT);
    return ($id !== false && $id > 0) ? (int)$id : null;
}
function eventDateFromForm($value): string {
    $value = str_replace('T', ' ', trim((string)$value));
    if (preg_match('/^\\d{4}-\\d{2}-\\d{2} \\d{2}:\\d{2}$/D', $value)) $value .= ':00';
    $dt = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value);
    if (!$dt || $dt->format('Y-m-d H:i:s') !== $value) {
        throw new InvalidArgumentException('Ngày giờ không hợp lệ.');
    }
    return $value;
}

// Các tên cột theo schema do_an_cuoi_ky của nhóm.
$colPk = 'ma_sukien';
$colTitle = 'ten_sukien';
$colStart = 'thoi_gian_bat_dau';
$colEnd = 'thoi_gian_ket_thuc';
$colLocation = 'ten_diadiem';
$colPosition = 'vi_tri';
$colTarget = 'doi_tuong_tham_gia';
$colCapacity = 'suc_chua';
$colDesc = 'mo_ta';

$error_msg = '';
$save_success = false;
$eventId = validEventId($_POST['ma_sukien'] ?? $_GET['ma_sukien'] ?? $_GET['id'] ?? null);

// Chỉ cho phép cập nhật với POST có CSRF; không xử lý dữ liệu sửa bằng GET.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $newPoster = null;
    try {
        $submittedToken = (string)($_POST['csrf_token'] ?? '');
        if (!hash_equals((string)$csrf_token, $submittedToken)) {
            throw new InvalidArgumentException('Phiên làm việc không hợp lệ. Hãy tải lại trang.');
        }
        if (($_POST['action'] ?? '') !== 'update' || $eventId === null) {
            throw new InvalidArgumentException('Mã sự kiện hoặc thao tác không hợp lệ.');
        }
        $title = trim((string)($_POST['ten_su_kien'] ?? ''));
        $desc = trim((string)($_POST['mo_ta'] ?? ''));
        $start = eventDateFromForm($_POST['thoi_gian_bat_dau'] ?? '');
        $end = eventDateFromForm($_POST['thoi_gian_ket_thuc'] ?? '');
        if ($title === '' || (function_exists('mb_strlen') ? mb_strlen($title) : strlen($title)) > 255) {
            throw new InvalidArgumentException('Tên sự kiện phải có từ 1 đến 255 ký tự.');
        }
        if ($end <= $start) throw new InvalidArgumentException('Thời gian kết thúc phải sau thời gian bắt đầu.');

        $locationText = trim((string)($_POST['dia_diem'] ?? ''));
        $position = trim((string)($_POST['vi_tri'] ?? ''));
        $target = trim((string)($_POST['doi_tuong'] ?? ''));
        $capacityRaw = trim((string)($_POST['suc_chua'] ?? ''));
        $capacity = $capacityRaw === '' ? 0 : filter_var($capacityRaw, FILTER_VALIDATE_INT);
        if ($capacity === false || $capacity < 0) throw new InvalidArgumentException('Sức chứa phải là số nguyên không âm.');

        $db->beginTransaction();
        $check = $db->prepare('SELECT * FROM su_kien WHERE ma_sukien = ? FOR UPDATE');
        $check->execute([$eventId]);
        $oldEvent = $check->fetch(PDO::FETCH_ASSOC);
        if (!$oldEvent) throw new InvalidArgumentException('Không tìm thấy sự kiện cần sửa.');
        $oldKey = (string)($oldEvent['file_path_key'] ?? '');

        $locationId = $oldEvent['ma_diadiem'] ?? null;
        if ($locationText !== '') {
            $find = $db->prepare('SELECT ma_diadiem FROM dia_diem WHERE ten_diadiem = ? LIMIT 1');
            $find->execute([$locationText]);
            $foundId = $find->fetchColumn();
            if ($foundId !== false) {
                $locationId = (int)$foundId;
            } else {
                // Khi người dùng gõ địa điểm mới, tạo bản ghi địa điểm như code cũ.
                $create = $db->prepare('INSERT INTO dia_diem (ten_diadiem, vi_tri, suc_chua) VALUES (?, ?, ?)');
                $create->execute([$locationText, $position, $capacity]);
                $locationId = (int)$db->lastInsertId();
            }
        }

        $fields = [
            'ten_sukien' => $title,
            'mo_ta' => $desc,
            'thoi_gian_bat_dau' => $start,
            'thoi_gian_ket_thuc' => $end,
            'ma_diadiem' => $locationId,
        ];
        // Chỉ lưu các trường bổ sung nếu database thực tế có cột tương ứng.
        $columnNames = $db->query('SHOW COLUMNS FROM su_kien')->fetchAll(PDO::FETCH_COLUMN);
        if (in_array('doi_tuong_tham_gia', $columnNames, true)) $fields['doi_tuong_tham_gia'] = $target;
        if (in_array('suc_chua', $columnNames, true)) $fields['suc_chua'] = $capacity;
        if (in_array('vi_tri', $columnNames, true)) $fields['vi_tri'] = $position;

        $upload = $_FILES['anh_su_kien'] ?? null;
        if ($upload !== null && ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $newPoster = eventUploadPoster($upload);
            $fields['file_name'] = $newPoster['original_name'];
            $fields['file_path_key'] = $newPoster['object_key'];
            $fields['file_type'] = $newPoster['mime_type'];
            $fields['file_size'] = $newPoster['file_size'];
            $fields['upload_time'] = $newPoster['uploaded_at'];
        }
        $assignments = [];
        $params = [];
        foreach ($fields as $column => $value) {
            $assignments[] = '`' . $column . '` = ?';
            $params[] = $value;
        }
        $params[] = $eventId;
        $stmt = $db->prepare('UPDATE su_kien SET ' . implode(', ', $assignments) . ' WHERE ma_sukien = ?');
        $stmt->execute($params);
        $db->commit();
        $save_success = true;

        // Chỉ xóa ảnh cũ SAU KHI MySQL cập nhật thành công.
        if ($newPoster !== null && $oldKey !== '' && $oldKey !== $newPoster['object_key']) {
            try {
                $count = $db->prepare('SELECT COUNT(*) FROM su_kien WHERE file_path_key = ?');
                $count->execute([$oldKey]);
                if ((int)$count->fetchColumn() === 0) eventDeletePoster($oldKey);
            } catch (Throwable $e) {
                error_log('Không dọn được poster cũ MinIO: ' . $e->getMessage());
            }
        }
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        if ($newPoster !== null) {
            try { eventDeletePoster((string)$newPoster['object_key']); }
            catch (Throwable $cleanupError) { error_log('MinIO orphan cần dọn: ' . $cleanupError->getMessage()); }
        }
        if ($e instanceof InvalidArgumentException) {
            $error_msg = $e->getMessage();
        } else {
            error_log('Lỗi sửa sự kiện: ' . $e->getMessage());
            $error_msg = 'Không thể cập nhật sự kiện. Kiểm tra log Docker.';
        }
    }
}

$event = null;
if ($eventId !== null) {
    $stmt = $db->prepare('SELECT sk.*, dd.ten_diadiem, dd.vi_tri AS vi_tri_diadiem, dd.suc_chua AS suc_chua_diadiem
                          FROM su_kien sk LEFT JOIN dia_diem dd ON sk.ma_diadiem = dd.ma_diadiem
                          WHERE sk.ma_sukien = ? LIMIT 1');
    $stmt->execute([$eventId]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Chỉnh sửa sự kiện</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../css/user-sua.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <div class="starfield-layer-1"></div>
    <div class="starfield-layer-2"></div>
    <div class="bg-space-dust"></div>
    <div class="gear-engine-left"></div>
    <div class="gear-engine-right"></div>

    <div class="container" style="max-width: 800px; padding-top: 40px; padding-bottom: 40px;">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="text-warning mb-0"><i class="bi bi-pencil-square me-2"></i>Chỉnh Sửa Thông Tin Sự Kiện</h3>
            <a href="../su-kien.php" class="btn btn-outline-light btn-sm"><i class="bi bi-arrow-left me-1"></i>Trở về danh sách</a>
        </div>

        <?php if (!empty($error_msg)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error_msg); ?></div>
        <?php endif; ?>

        <?php if (!$event): ?>
            <div class="card p-4 shadow">
                <h5 class="text-warning mb-3"><i class="bi bi-list-check me-2"></i>Chọn sự kiện bạn muốn chỉnh sửa:</h5>
                <?php
                try {
                    $list = $db->query("SELECT * FROM su_kien ORDER BY {$colStart} DESC")->fetchAll(PDO::FETCH_ASSOC);
                    if (empty($list)) {
                        echo "<p class='text-muted mb-0'>Chưa có sự kiện nào trong hệ thống.</p>";
                    } else {
                        echo "<div class='list-group'>";
                        foreach ($list as $item) {
                            $item_id   = getColValue($item, $colPk, 'ma_sukien', 'id');
                            $item_name = getColValue($item, $colTitle, 'ten_su_kien', 'tieu_de');
                            $item_time = getColValue($item, $colStart, 'thoi_gian_bat_dau');

                            echo "<a href='xuly_sua.php?ma_sukien={$item_id}' class='list-group-item list-group-item-action mb-2'>
                                    <span><i class='bi bi-calendar-event me-2 text-info'></i><strong>" . htmlspecialchars($item_name) . "</strong></span>
                                    <small class='text-light opacity-75'>({$item_time})</small>
                                  </a>";
                        }
                        echo "</div>";
                    }
                } catch (Exception $e) {
                    echo "<p class='text-danger'>Lỗi kết nối CSDL: " . $e->getMessage() . "</p>";
                }
                ?>
            </div>
        <?php else: ?>
            <?php
            $val_id       = getColValue($event, $colPk, 'ma_sukien', 'id');
            $val_title    = getColValue($event, $colTitle, 'ten_su_kien', 'tieu_de');
            $val_start    = getColValue($event, $colStart, 'thoi_gian_bat_dau');
            $val_end      = getColValue($event, $colEnd, 'thoi_gian_ket_thuc');
            $val_location = !empty($event['ten_diadiem']) ? $event['ten_diadiem'] : getColValue($event, $colLocation, 'dia_diem', 'diadiem');
            $val_position = !empty($event['vi_tri_diadiem']) ? $event['vi_tri_diadiem'] : getColValue($event, $colPosition, 'vi_tri');
            $val_target   = getColValue($event, $colTarget, 'doi_tuong_tham_gia', 'doi_tuong');
            $val_capacity = getColValue($event, 'suc_chua', 'suc_chua_diadiem');
            $val_desc     = getColValue($event, $colDesc, 'mo_ta', 'mota');
            ?>
           <form id="editEventForm" method="POST" class="p-4 shadow" enctype="multipart/form-data">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="ma_sukien" value="<?php echo htmlspecialchars($val_id); ?>">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">

                <div class="mb-3">
                    <label class="form-label">Tên sự kiện (*):</label>
                    <input type="text" name="ten_su_kien" class="form-control" value="<?php echo htmlspecialchars($val_title); ?>" required>
                </div>
            <div class="form-group mb-3">
            <label class="form-label text-info fw-bold" style="font-size: 13px;">ĐỔI ẢNH BÌA SỰ KIỆN (Bỏ trống nếu không đổi, tối đa 5MB):</label>
                <div class="d-flex align-items-center gap-3 bg-dark border border-info rounded p-2">
                <?php 
        // Hiển thị ảnh cũ nhỏ nhắn bên cạnh nút upload
                $anh_cu = $event['file_name'] ?? $event['anh_hien_thi'] ?? '';
                $src_anh = !empty($event['file_path_key'])
                    ? '../poster_su_kien.php?ma_sukien=' . rawurlencode((string)$val_id)
                    : ((!empty($anh_cu)) ? '../images/' . rawurlencode(basename((string)$anh_cu)) : '../images/sukien01.jpg');
                ?>
                <img src="<?php echo htmlspecialchars($src_anh, ENT_QUOTES, 'UTF-8'); ?>" alt="Ảnh hiện tại" style="width: 50px; height: 50px; object-fit: cover; border-radius: 6px; border: 1px solid #00f2c2;">
        
            <input type="file" name="anh_su_kien" id="anh_su_kien" class="form-control bg-dark text-white border-0 shadow-none" accept="image/jpeg, image/png, image/gif, image/webp">
                </div>
            </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Thời gian bắt đầu (*):</label>
                        <input type="datetime-local" name="thoi_gian_bat_dau" class="form-control" value="<?php echo !empty($val_start) ? date('Y-m-d\TH:i', strtotime($val_start)) : ''; ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Thời gian kết thúc:</label>
                        <input type="datetime-local" name="thoi_gian_ket_thuc" class="form-control" required value="<?php echo !empty($val_end) ? date('Y-m-d\TH:i', strtotime($val_end)) : ''; ?>">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Địa điểm tổ chức:</label>
                        <input type="text" name="dia_diem" class="form-control" value="<?php echo htmlspecialchars($val_location); ?>" placeholder="Ví dụ: Hội trường A">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Vị trí cụ thể:</label>
                        <input type="text" name="vi_tri" class="form-control" value="<?php echo htmlspecialchars($val_position); ?>" placeholder="Ví dụ: Tầng 2 - Tòa nhà trung tâm">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Đối tượng tham gia:</label>
                        <input type="text" name="doi_tuong" class="form-control" value="<?php echo htmlspecialchars($val_target); ?>" placeholder="Ví dụ: Sinh viên toàn trường">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Sức chứa (Số người tối đa):</label>
                        <input type="text" name="suc_chua" class="form-control" value="<?php echo htmlspecialchars($val_capacity); ?>" placeholder="Ví dụ: 200">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Mô tả và nội dung sự kiện:</label>
                    <textarea name="mo_ta" class="form-control" rows="5" placeholder="Nhập chi tiết mô tả sự kiện..."><?php echo htmlspecialchars($val_desc); ?></textarea>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-4">
                    <a href="../su-kien.php" class="btn btn-secondary">Hủy bỏ</a>
                    <button type="submit" class="btn btn-warning"><i class="bi bi-save me-1"></i>Lưu thay đổi</button>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <?php if ($save_success): ?>
    <script>
        Swal.fire({
            title: '🎉 THÀNH CÔNG!',
            text: 'Cập nhật thông tin sự kiện thành công!',
            icon: 'success',
            background: '#0a0826',
            color: '#ffffff',
            confirmButtonText: 'ĐỒNG Ý',
            confirmButtonColor: '#ff1e56',
            customClass: {
                popup: 'swal-space-popup'
            }
        }).then((result) => {
            if (result.isConfirmed || result.isDismissed) {
                window.location.href = "../su-kien.php";
            }
        });
    </script>
    <?php endif; ?>
</body>
</html>