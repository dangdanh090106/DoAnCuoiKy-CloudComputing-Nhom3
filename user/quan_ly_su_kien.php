<?php
session_start();

require_once __DIR__ . '/../database.php';

$db_connection = $pdo ?? $conn ?? null;

if (!($db_connection instanceof PDO)) {
    http_response_code(500);
    exit('Không thể kết nối cơ sở dữ liệu.');
}


// 1. Kiểm tra đăng nhập và phân quyền
if (!isset($_SESSION['vai_tro'])) {
    header("Location: login.php");
    exit();
}

if ($_SESSION['vai_tro'] !== 'quan_ly_su_kien' &&$_SESSION['vai_tro'] !== 'admin') {
    echo "<script>alert('Bạn không có quyền truy cập trang quản lý sự kiện!'); window.location.href='dashboard.php';</script>";
    exit();
}

// Token dùng cho POST multipart/form-data gửi tới api_su_kien.php.
if (empty($_SESSION['event_csrf_token'])) {
    $_SESSION['event_csrf_token'] = bin2hex(random_bytes(32));
}
$thong_bao = '';
$error = '';
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
        <form id="eventForm" action="../api_su_kien.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['event_csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
            
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
                    <input type="text" id="thoi_gian_bat_dau" name="thoi_gian_bat_dau" value="" required placeholder="Chọn ngày bắt đầu...">
                </div>
                <div class="form-group">
                    <label>🏁 Thời gian kết thúc (*):</label>
                    <input type="text" id="thoi_gian_ket_thuc" name="thoi_gian_ket_thuc" value="" required placeholder="Chọn ngày kết thúc...">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>📍 Phòng tổ chức:</label>
                    <select name="ma_diadiem" class="form-control">
                        <option value="">-- Chọn phòng tổ chức --</option>
                        <?php
                        try {
                            $rooms = $db_connection->query("SELECT ma_diadiem, ten_diadiem FROM dia_diem ORDER BY ten_diadiem")
                                ->fetchAll(PDO::FETCH_ASSOC);
                            foreach ($rooms as $room) {
                                echo '<option value="' . (int)$room['ma_diadiem'] . '">';
                                echo htmlspecialchars($room['ten_diadiem'], ENT_QUOTES, 'UTF-8');
                                echo '</option>';
                            }
                        } catch (Exception $e) {
                            error_log("Không tải được danh sách phòng: " . $e->getMessage());
                            echo '<option value="" disabled>Không tải được danh sách phòng</option>';
                        }
                        ?>
                    </select>
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

    <!-- Script kích hoạt bộ chọn ngày giờ và kiểm tra kích thước ảnh -->
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