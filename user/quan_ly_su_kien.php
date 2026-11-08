<?php
session_start();
require_once '../database.php'; 

// 1. Kiểm tra đăng nhập và phân quyền
if (!isset($_SESSION['vai_tro'])) {
    header("Location: login.php");
    exit();
}

if ($_SESSION['vai_tro'] !== 'quan_ly_su_kien' &&$_SESSION['vai_tro'] !== 'admin') {
    echo "<script>alert('Bạn không có quyền truy cập trang quản lý sự kiện!'); window.location.href='dashboard.php';</script>";
    exit();
}

// 2. Xử lý khi người dùng gửi Form Đăng sự kiện
$thong_bao = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['them_su_kien'])) {
    $ten_input          = trim($_POST['ten_su_kien'] ?? '');
    
    // Chuyển đổi định dạng d/m/Y H:i sang Y-m-d H:i:s cho MySQL
    $raw_start          =$_POST['thoi_gian_bat_dau'] ?? '';
    $raw_end            =$_POST['thoi_gian_ket_thuc'] ?? '';
    
    $thoi_gian_bat_dau  = !empty($raw_start) ? date('Y-m-d H:i:s', strtotime(str_replace('/', '-', $raw_start))) : '';
    $thoi_gian_ket_thuc = !empty($raw_end) ? date('Y-m-d H:i:s', strtotime(str_replace('/', '-', $raw_end))) : '';
    
    $dia_diem           = trim($_POST['dia_diem'] ?? '');
    $vi_tri             = trim($_POST['vi_tri'] ?? '');
    $doi_tuong_tham_gia = trim($_POST['doi_tuong_tham_gia'] ?? '');
    $suc_chua           = (int)($_POST['suc_chua'] ?? 0);
    $mo_ta              = trim($_POST['mo_ta'] ?? '');
    $ma_sv_tao          =$_SESSION['ma_sv'] ?? ''; 

    // 📸 Xử lý upload ảnh lưu trực tiếp vào thư mục ../images/
    $anh_bia_name = "";
    if (isset($_FILES['anh_su_kien']) && $_FILES['anh_su_kien']['error'] === UPLOAD_ERR_OK) {$file_tmp = $_FILES['anh_su_kien']['tmp_name'];$file_size = $_FILES['anh_su_kien']['size'];$file_ext = strtolower(pathinfo($_FILES['anh_su_kien']['name'], PATHINFO_EXTENSION));$allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        // Giới hạn phía server: 5MB (5242880 bytes)
        if ($file_size > 5 * 1024 * 1024) {$error = "Dung lượng ảnh quá lớn! Vui lòng chọn ảnh dưới 5MB.";
        } elseif (!in_array($file_ext, $allowed_ext)) {$error = "Định dạng ảnh không hợp lệ (Chỉ chấp nhận JPG, PNG, GIF, WEBP).";
        } else {
            $upload_dir = __DIR__ . '/../images/';
            if (!is_dir($upload_dir) && !mkdir($upload_dir, 0777, true) && !is_dir($upload_dir)) {
                $error = "Không thể tạo thư mục lưu ảnh.";
            } else {
                $anh_bia_name = time() . '_' . uniqid() . '.' . $file_ext;
                if (!move_uploaded_file($file_tmp, $upload_dir . $anh_bia_name)) {
                    $error = "Không thể lưu ảnh vào thư mục images.";
                    $anh_bia_name = "";
                }
            }
        }
    }

    if (empty($error)) {
        if (!empty($ten_input) && !empty($thoi_gian_bat_dau) && !empty($thoi_gian_ket_thuc)) {
            try {
                $db_connection = isset($pdo) ?$pdo : (isset($conn) ?$conn : null);

                if (!$db_connection) {
                    throw new Exception("Không tìm thấy kết nối CSDL hợp lệ!");
                }

                // Tự động quét danh sách cột thực tế của bảng su_kien
                $stmt_cols =$db_connection->query("SHOW COLUMNS FROM su_kien");
                $columns =$stmt_cols->fetchAll(PDO::FETCH_COLUMN);

                // Tìm cột tiêu đề sự kiện
                $col_title = '';$candidates = ['ten_su_kien', 'tieu_de', 'tieude', 'ten_sk', 'ten'];
                foreach ($candidates as$c) {
                    if (in_array($c,$columns)) {
                        $col_title =$c;
                        break;
                    }
                }
                if (empty($col_title) && count($columns) > 1) {
                    $col_title =$columns[1]; 
                } elseif (empty($col_title)) {$col_title = 'ten_su_kien';
                }

                // Tìm cột chứa ảnh trong CSDL
                $col_img = '';$img_candidates = ['anh_bia', 'hinh_anh', 'anh', 'image', 'file_name'];
                foreach ($img_candidates as$ic) {
                    if (in_array($ic,$columns)) {
                        $col_img =$ic;
                        break;
                    }
                }

                // Xây dựng mảng dữ liệu chèn vào CSDL
                $insert_data = [];$insert_data[$col_title] =$ten_input;

                if (in_array('thoi_gian_bat_dau', $columns)) $insert_data['thoi_gian_bat_dau'] =$thoi_gian_bat_dau;
                if (in_array('thoi_gian_ket_thuc', $columns)) $insert_data['thoi_gian_ket_thuc'] =$thoi_gian_ket_thuc;
                if (in_array('dia_diem', $columns)) $insert_data['dia_diem'] =$dia_diem;
                if (in_array('vi_tri', $columns)) $insert_data['vi_tri'] =$vi_tri;
                if (in_array('doi_tuong_tham_gia', $columns)) $insert_data['doi_tuong_tham_gia'] =$doi_tuong_tham_gia;
                if (in_array('suc_chua', $columns)) $insert_data['suc_chua'] =$suc_chua;
                if (in_array('mo_ta', $columns)) $insert_data['mo_ta'] =$mo_ta;
                if (!empty($col_img) && !empty($anh_bia_name))$insert_data[$col_img] =$anh_bia_name;
                if (in_array('ma_sv_tao', $columns)) $insert_data['ma_sv_tao'] =$ma_sv_tao;

                $fields = array_keys($insert_data);
                $placeholders = array_fill(0, count($fields), '?');

                $sql = "INSERT INTO su_kien (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
                $stmt =$db_connection->prepare($sql);$success = $stmt->execute(array_values($insert_data));

                if ($success) {$thong_bao = "🎉 Thêm sự kiện mới thành công!";
                } else {
                    $error = "Lỗi khi thêm sự kiện vào cơ sở dữ liệu.";
                }
            } catch (Exception $e) {$error = "Lỗi hệ thống CSDL: " . $e->getMessage();
            }
        } else {
            $error = "Vui lòng nhập đầy đủ Tên sự kiện, Thời gian bắt đầu và Thời gian kết thúc!";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trang Quản Lý & Đăng Sự Kiện</title>
    
    <!-- CSS Vũ Trụ Chống Chói -->
    <link rel="stylesheet" href="../css/quan-ly-su-kien.css?v=<?php echo time(); ?>">
    
    <!-- Flatpickr (Bộ chọn ngày giờ chuẩn Việt Nam) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/dark.css">
    
    <style>
        .flatpickr-input {
            background-color: rgba(2, 1, 6, 0.98) !important;
            color: #00ffcc !important;
            font-weight: 700;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Điều hướng phía trên form -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <a href="dashboard.php" class="back-link">← Quay lại trang cá nhân</a>
            <a href="../su-kien.php" style="background: linear-gradient(135deg, #ff3366, #990033); color: #ffffff; padding: 10px 20px; border-radius: 10px; font-weight: 700; text-decoration: none; font-size: 14px; border: 1px solid #00e6b8; box-shadow: 0 0 15px rgba(255,51,102,0.4); display: inline-flex; align-items: center; gap: 6px;">
                🎪 Xem danh sách sự kiện →
            </a>
        </div>

        <h2>Quản Lý & Đăng Thông Tin Sự Kiện</h2>
        <p>
            Đăng nhập bởi: <b><?php echo htmlspecialchars($_SESSION['ho_ten'] ?? 'Quản lý'); ?></b> 
            (Vai trò: <span style="color: #00e6b8; font-weight: 600;"><?php echo htmlspecialchars($_SESSION['vai_tro']); ?></span>)
        </p>
        <hr style="margin: 25px 0; border: 0; border-top: 1px solid rgba(0,230,184,0.2);">

        <?php if (!empty($thong_bao)): ?>
            <div class="alert-success"><?php echo htmlspecialchars($thong_bao); ?></div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert-danger" id="errorAlert"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Form Đăng sự kiện -->
        <form id="eventForm" action="" method="POST" enctype="multipart/form-data">
            
            <div class="form-group">
                <label>Tên sự kiện (*):</label>
                <input type="text" name="ten_su_kien" required placeholder="Nhập tên sự kiện...">
            </div>

            <div class="form-group">
                <label>🖼️ Ảnh bìa sự kiện (Tối đa 5MB):</label>
                <input type="file" name="anh_su_kien" id="anh_su_kien" accept="image/*">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>⏰ Thời gian bắt đầu (*):</label>
                    <input type="text" id="thoi_gian_bat_dau" name="thoi_gian_bat_dau" value="20/10/2026 17:13" required placeholder="Chọn ngày bắt đầu...">
                </div>
                <div class="form-group">
                    <label>🏁 Thời gian kết thúc (*):</label>
                    <input type="text" id="thoi_gian_ket_thuc" name="thoi_gian_ket_thuc" value="20/10/2026 19:13" required placeholder="Chọn ngày kết thúc...">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>📍 Địa điểm:</label>
                    <input type="text" name="dia_diem" placeholder="Ví dụ: Hội trường A, Sân bóng...">
                </div>
                <div class="form-group">
                    <label>📌 Vị trí cụ thể:</label>
                    <input type="text" name="vi_tri" placeholder="Ví dụ: Tầng 2 - Tòa nhà trung tâm">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>👥 Đối tượng tham gia:</label>
                    <input type="text" name="doi_tuong_tham_gia" placeholder="Ví dụ: Sinh viên toàn trường, CLB...">
                </div>
                <div class="form-group">
                    <label>👤 Sức chứa (Số người tối đa):</label>
                    <input type="number" name="suc_chua" min="0" placeholder="Ví dụ: 200">
                </div>
            </div>

            <div class="form-group">
                <label>📝 Mô tả và nội dung sự kiện:</label>
                <textarea name="mo_ta" rows="5" placeholder="Nhập chi tiết mô tả sự kiện..."></textarea>
            </div>

            <button type="submit" name="them_su_kien" class="btn-submit">🚀 Đăng Sự Kiện Mới</button>
        </form>
    </div>

    <!-- Script Kích hoạt Bộ chọn Ngày Giờ chuẩn Việt Nam & Kiểm tra kích thước ảnh -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://npmcdn.com/flatpickr/dist/l10n/vn.js"></script>
    <script>
        flatpickr("#thoi_gian_bat_dau, #thoi_gian_ket_thuc", {
            enableTime: true,
            dateFormat: "d/m/Y H:i",
            time_24hr: true,
            locale: "vn"
        });

// HÀM XỬ LÝ LƯU SỰ KIỆN QUA API VỚI FORMDATA (CÓ CHỨA ẢNH)
        document.getElementById('eventForm').addEventListener('submit', function(e) {
            e.preventDefault(); // Ngăn form tự động chuyển trang mặc định của HTML

            console.log("1. Đã bấm nút lưu, bắt đầu kiểm tra dữ liệu...");

            // Kiểm tra dung lượng ảnh (nếu có chọn)
            const fileInput = document.getElementById('anh_su_kien');
            if (fileInput && fileInput.files.length > 0) {
                const fileSize = fileInput.files[0].size; 
                const maxSize = 5 * 1024 * 1024; // 5MB
                if (fileSize > maxSize) {
                    alert('⚠️ Ảnh bạn chọn có dung lượng quá lớn (' + (fileSize / (1024*1024)).toFixed(2) + ' MB). Vui lòng chọn ảnh nhỏ hơn 5MB!');
                    return false;
                }
                console.log("2. Ảnh hợp lệ, chuẩn bị đóng gói...");
            } else {
                console.log("2. Không có ảnh nào được chọn, bỏ qua phần kiểm tra dung lượng.");
            }

            // Gom toàn bộ thông tin chữ + File ảnh
            let formData = new FormData(this);

            // Xử lý lại định dạng ngày giờ để gửi qua API (vì MySQL cần Y-m-d H:i:s)
            const rawStart = document.getElementById('thoi_gian_bat_dau').value;
            const rawEnd = document.getElementById('thoi_gian_ket_thuc').value;
            
            function parseDateForMySQL(str) {
                if(!str) return '';
                if(str.includes('/')) {
                    const parts = str.split(' ');
                    const dateParts = parts[0].split('/');
                    return `${dateParts[2]}-${dateParts[1]}-${dateParts[0]} ${parts[1]}:00`;
                }
                return str;
            }

            // Ghi đè lại định dạng ngày đã chuẩn hóa vào formData trước khi gửi đi
            formData.set('thoi_gian_bat_dau', parseDateForMySQL(rawStart));
            formData.set('thoi_gian_ket_thuc', parseDateForMySQL(rawEnd));

            console.log("3. Đang gửi dữ liệu qua Fetch tới api_su_kien.php...");

            // Bắt đầu gửi Fetch
            fetch('../api_su_kien.php', {
                method: 'POST',
                body: formData // Truyền thẳng formData, trình duyệt sẽ tự động biết có file ảnh và set Content-Type
            })
            .then(response => {
                console.log("4. Đã nhận phản hồi từ server, đang phân tích JSON...");
                return response.json();
            })
            .then(data => {
                console.log("5. Phản hồi JSON:", data);
                if (data.status === 'success') {
                    alert('🎉 Thêm sự kiện và upload ảnh thành công!');
                    window.location.href = '../su-kien.php'; // Chuyển về trang danh sách
                } else {
                    alert('Lỗi từ Server: ' + data.message);
                }
            })
            .catch(err => {
                console.error("LỖI NGHIÊM TRỌNG:", err);
                alert('Có lỗi xảy ra khi kết nối tới hệ thống! Vui lòng ấn F12 -> Console để xem chi tiết.');
            });
        });
    </script>
</body>
</html>