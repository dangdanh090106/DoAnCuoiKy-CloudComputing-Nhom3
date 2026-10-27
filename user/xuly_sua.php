<?php
session_start();
require_once '../database.php';

$db = isset($conn) ? $conn : (isset($pdo) ? $pdo : null);
if (!$db) {
    die("Lỗi: Không tìm thấy kết nối cơ sở dữ liệu.");
}

$vai_tro = $_SESSION['vai_tro'] ?? '';
$isManager = in_array(strtolower(trim($vai_tro)), ['admin', 'quan_ly_su_kien', 'quan_ly', 'management']);
if (!$isManager) {
    header("Location: login.php");
    exit();
}

function getColValue($row, ...$keys) {
    if (!$row) return '';
    foreach ($keys as $k) {
        if (isset($row[$k]) && $row[$k] !== '') {
            return $row[$k];
        }
    }
    return '';
}

$stmtCols = $db->query("SHOW COLUMNS FROM su_kien");
$existingCols = $stmtCols->fetchAll(PDO::FETCH_COLUMN);
$existingColsLower = array_map('strtolower', $existingCols);

function findRealCol($existingCols, $candidates, $default) {
    foreach ($candidates as $c) {
        foreach ($existingCols as $ec) {
            if (strtolower($ec) === strtolower($c)) {
                return $ec;
            }
        }
    }
    return $default;
}

$colPk       = findRealCol($existingCols, ['ma_sukien', 'id', 'ma_sk', 'id_sukien'], 'ma_sukien');
$colTitle    = findRealCol($existingCols, ['ten_su_kien', 'ten_sukien', 'tieu_de', 'ten'], 'ten_su_kien');
$colStart    = findRealCol($existingCols, ['thoi_gian_bat_dau', 'bat_dau', 'ngay_to_chuc'], 'thoi_gian_bat_dau');
$colEnd      = findRealCol($existingCols, ['thoi_gian_ket_thuc', 'ket_thuc'], 'thoi_gian_ket_thuc');
$colLocation = findRealCol($existingCols, ['ten_diadiem', 'dia_diem', 'diadiem', 'dia_chi'], 'ten_diadiem');
$colPosition = findRealCol($existingCols, ['vi_tri', 'vi_tri_cu_the', 'dia_chi_chi_tiet'], 'vi_tri');
$colTarget   = findRealCol($existingCols, ['doi_tuong_tham_gia', 'doi_tuong_ap_dung', 'doi_tuong'], 'doi_tuong_tham_gia');
$colCapacity = findRealCol($existingCols, ['suc_chua', 'so_luong', 'so_nguoi_toi_da'], 'suc_chua');
$colDesc     = findRealCol($existingCols, ['mo_ta', 'mota', 'chi_tiet', 'description'], 'mo_ta');

$id = $_GET['ma_sukien'] ?? $_GET['id'] ?? $_GET['ma_sk'] ?? null;

$event = null;
if ($id) {
    try {
        $hasDiaDiemTable = false;
        try {
            $testTbl = $db->query("SELECT 1 FROM dia_diem LIMIT 1");
            if ($testTbl) $hasDiaDiemTable = true;
        } catch (Exception $ex) { $hasDiaDiemTable = false; }

        if ($hasDiaDiemTable && in_array('ma_diadiem', $existingColsLower)) {
            $stmt = $db->prepare("SELECT sk.*, dd.ten_diadiem, dd.vi_tri as vi_tri_diadiem, dd.suc_chua as suc_chua_diadiem 
                                  FROM su_kien sk 
                                  LEFT JOIN dia_diem dd ON sk.ma_diadiem = dd.ma_diadiem 
                                  WHERE sk.{$colPk} = ? LIMIT 1");
            $stmt->execute([$id]);
            $event = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $stmt = $db->prepare("SELECT * FROM su_kien WHERE {$colPk} = ? LIMIT 1");
            $stmt->execute([$id]);
            $event = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        $event = null;
    }
}

$error_msg = "";
$save_success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $post_id      = $_POST['ma_sukien'] ?? null;
    $ten_su_kien  = trim($_POST['ten_su_kien'] ?? '');
    $bat_dau      = $_POST['thoi_gian_bat_dau'] ?? null;
    $ket_thuc     = $_POST['thoi_gian_ket_thuc'] ?? null;
    $dia_diem_txt = trim($_POST['dia_diem'] ?? '');
    $vi_tri       = trim($_POST['vi_tri'] ?? '');
    $doi_tuong    = trim($_POST['doi_tuong'] ?? '');
    $suc_chua     = trim($_POST['suc_chua'] ?? '');
    $mo_ta        = trim($_POST['mo_ta'] ?? '');

    if ($post_id) {
        try {
            $updateFields = [];
            $params = [':id' => $post_id];

            $updateFields[] = "{$colTitle} = :ten";
            $params[':ten'] = $ten_su_kien;

            if (in_array(strtolower($colStart), $existingColsLower)) {
                $updateFields[] = "{$colStart} = :bat_dau";
                $params[':bat_dau'] = !empty($bat_dau) ? $bat_dau : null;
            }
            if (in_array(strtolower($colEnd), $existingColsLower)) {
                $updateFields[] = "{$colEnd} = :ket_thuc";
                $params[':ket_thuc'] = !empty($ket_thuc) ? $ket_thuc : null;
            }

            if (in_array('ma_diadiem', $existingColsLower)) {
                $ma_diadiem_val = 1;
                if (!empty($dia_diem_txt)) {
                    $stmtCheckDD = $db->prepare("SELECT ma_diadiem FROM dia_diem WHERE ten_diadiem = ? LIMIT 1");
                    $stmtCheckDD->execute([$dia_diem_txt]);
                    $foundDD = $stmtCheckDD->fetch(PDO::FETCH_ASSOC);

                    if ($foundDD) {
                        $ma_diadiem_val = $foundDD['ma_diadiem'];
                    } else {
                        $stmtInsDD = $db->prepare("INSERT INTO dia_diem (ten_diadiem, vi_tri, suc_chua) VALUES (?, ?, ?)");
                        $stmtInsDD->execute([$dia_diem_txt, $vi_tri, !empty($suc_chua) ? $suc_chua : 0]);
                        $ma_diadiem_val = $db->lastInsertId();
                    }
                }
                $updateFields[] = "ma_diadiem = :ma_diadiem";
                $params[':ma_diadiem'] = $ma_diadiem_val;
            } elseif (in_array(strtolower($colLocation), $existingColsLower)) {
                $updateFields[] = "{$colLocation} = :dia_diem";
                $params[':dia_diem'] = $dia_diem_txt;
            }

            if (in_array(strtolower($colPosition), $existingColsLower)) {
                $updateFields[] = "{$colPosition} = :vi_tri";
                $params[':vi_tri'] = $vi_tri;
            }
            if (in_array(strtolower($colTarget), $existingColsLower)) {
                $updateFields[] = "{$colTarget} = :doi_tuong";
                $params[':doi_tuong'] = $doi_tuong;
            }
            if (in_array(strtolower($colCapacity), $existingColsLower)) {
                $updateFields[] = "{$colCapacity} = :suc_chua";
                $params[':suc_chua'] = !empty($suc_chua) ? $suc_chua : 0;
            }
            if (in_array(strtolower($colDesc), $existingColsLower)) {
                $updateFields[] = "{$colDesc} = :mo_ta";
                $params[':mo_ta'] = $mo_ta;
            }

            $sql = "UPDATE su_kien SET " . implode(", ", $updateFields) . " WHERE {$colPk} = :id";
            $updateStmt = $db->prepare($sql);
            $updateStmt->execute($params);

            $save_success = true;
        } catch (Exception $e) {
            $error_msg = "Lỗi khi lưu dữ liệu: " . $e->getMessage();
        }
    }
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
            $val_capacity = getColValue($event, $colCapacity, 'suc_chua');
            $val_desc     = getColValue($event, $colDesc, 'mo_ta', 'mota');
            ?>
            <form method="POST" class="p-4 shadow">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="ma_sukien" value="<?php echo htmlspecialchars($val_id); ?>">

                <div class="mb-3">
                    <label class="form-label">Tên sự kiện (*):</label>
                    <input type="text" name="ten_su_kien" class="form-control" value="<?php echo htmlspecialchars($val_title); ?>" required>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Thời gian bắt đầu (*):</label>
                        <input type="datetime-local" name="thoi_gian_bat_dau" class="form-control" value="<?php echo !empty($val_start) ? date('Y-m-d\TH:i', strtotime($val_start)) : ''; ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Thời gian kết thúc:</label>
                        <input type="datetime-local" name="thoi_gian_ket_thuc" class="form-control" value="<?php echo !empty($val_end) ? date('Y-m-d\TH:i', strtotime($val_end)) : ''; ?>">
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