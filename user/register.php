<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../database.php';

/** @var PDO $conn */

$error = "";
$success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ma_sv    = trim($_POST['ma_sv'] ?? '');
    $ho_ten   = trim($_POST['ho_ten'] ?? '');
    $ma_lop   = trim($_POST['ma_lop'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $sdt      = trim($_POST['sdt'] ?? '');
    $mat_khau = trim($_POST['mat_khau'] ?? '');

    if (!empty($ma_sv) && !empty($ho_ten) && !empty($mat_khau)) {
        try {
            // Kiểm tra xem mã sinh viên đã tồn tại chưa
            $stmt_check = $conn->prepare("SELECT id FROM users WHERE ma_sv = ?");
            $stmt_check->execute([$ma_sv]);
            if ($stmt_check->fetch()) {
                $error = "Mã sinh viên này đã tồn tại trong hệ thống!";
            } else {
                // Thêm tài khoản mới với vai trò mặc định là sinh_vien
                $stmt_insert = $conn->prepare("
                    INSERT INTO users (ma_sv, ho_ten, ma_lop, email, sdt, vai_tro, mat_khau, trang_thai) 
                    VALUES (?, ?, ?, ?, ?, 'sinh_vien', ?, 'Đang học')
                ");
                $stmt_insert->execute([$ma_sv, $ho_ten, $ma_lop, $email, $sdt, $mat_khau]);
                $success = "Đăng ký tài khoản thành công! Bạn có thể chuyển sang trang đăng nhập.";
            }
        } catch (PDOException $e) {
            $error = "Lỗi cơ sở dữ liệu: " . $e->getMessage();
        }
    } else {
        $error = "Vui lòng nhập đầy đủ các thông tin bắt buộc!";
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Đăng ký tài khoản Sinh viên</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f6f9; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
        .register-box { background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); width: 100%; max-width: 400px; }
        h2 { text-align: center; color: #333; margin-bottom: 20px; }
        .alert-error { background: #f8d7da; color: #721c24; padding: 10px; border-radius: 4px; margin-bottom: 15px; font-size: 14px; }
        .alert-success { background: #d4edda; color: #155724; padding: 10px; border-radius: 4px; margin-bottom: 15px; font-size: 14px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; color: #555; font-size: 14px; }
        input { width: 100%; padding: 9px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; font-size: 14px; }
        button { width: 100%; padding: 10px; background: #198754; border: none; color: white; font-weight: bold; border-radius: 4px; cursor: pointer; font-size: 15px; }
        button:hover { background: #157347; }
        .login-link { text-align: center; margin-top: 15px; font-size: 14px; }
        .login-link a { color: #0d6efd; text-decoration: none; }
    </style>
</head>
<body>
    <div class="register-box">
        <h2>Đăng Ký Tài Khoản</h2>
        
        <?php if (!empty($error)): ?>
            <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Mã sinh viên (*)</label>
                <input type="text" name="ma_sv" required placeholder="Ví dụ: 24210501026">
            </div>
            <div class="form-group">
                <label>Họ và tên (*)</label>
                <input type="text" name="ho_ten" required placeholder="Nhập họ và tên đầy đủ">
            </div>
            <div class="form-group">
                <label>Lớp học</label>
                <input type="text" name="ma_lop" placeholder="Ví dụ: 242101TH001">
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" placeholder="Nhập email cá nhân hoặc trường">
            </div>
            <div class="form-group">
                <label>Số điện thoại</label>
                <input type="text" name="sdt" placeholder="Nhập số điện thoại">
            </div>
            <div class="form-group">
                <label>Mật khẩu (*)</label>
                <input type="password" name="mat_khau" required placeholder="Nhập mật khẩu">
            </div>
            <button type="submit">Đăng Ký</button>
        </form>
        
        <div class="login-link">
            Đã có tài khoản? <a href="login.php">Đăng nhập ngay</a>
        </div>
    </div>
</body>
</html>