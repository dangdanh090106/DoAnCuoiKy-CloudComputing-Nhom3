<?php
session_start();
require_once __DIR__ . '/../database.php';

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tai_khoan = trim($_POST['tai_khoan'] ?? $_POST['ma_sv'] ?? '');
    $mat_khau = trim($_POST['mat_khau'] ?? '');

    if (!empty($tai_khoan) && !empty($mat_khau)) {
        try {
            $stmt = $conn->prepare("SELECT * FROM users WHERE ma_sv = ? OR email = ?");
            $stmt->execute([$tai_khoan, $tai_khoan]);
            $user = $stmt->fetch();

            if ($user) {
                $is_valid = ($mat_khau === $user['mat_khau']) || password_verify($mat_khau, $user['mat_khau']);

                if ($is_valid) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['ma_sv'] = $user['ma_sv'];
                    $_SESSION['ho_ten'] = $user['ho_ten'];
                    $_SESSION['vai_tro'] = $user['vai_tro'];

                    if ($user['vai_tro'] === 'admin') {
                        header("Location: ../admin/dashboard.php");
                    } else {
                        header("Location: dashboard.php");
                    }
                    exit();
                } else {
                    $error = "Mật khẩu không chính xác!";
                }
            } else {
                $error = "Tài khoản không tồn tại!";
            }
        } catch (PDOException $e) {
            $error = "Lỗi SQL: " . $e->getMessage();
        }
    } else {
        $error = "Vui lòng nhập đầy đủ thông tin!";
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Đăng nhập Hệ thống</title>
    <!-- Thêm Bootstrap Icons nếu cần icon -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../css/user-login.css">
</head>
<body>
    <!-- Khung đăng nhập kính mờ 3D -->
    <div class="login-box">
        <h2>Đăng nhập Hệ thống</h2>
        
        <?php if (!empty($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Mã SV / Tài khoản / Email</label>
                <input type="text" name="tai_khoan" required placeholder="Nhập mã SV hoặc email...">
            </div>
            <div class="form-group">
                <label>Mật khẩu</label>
                <input type="password" name="mat_khau" required placeholder="Nhập mật khẩu...">
            </div>
            <button type="submit">
                <i class="bi bi-box-arrow-in-right me-2"></i> Đăng nhập
            </button>
        </form>
    </div>
    <!-- Các lớp nền vũ trụ và dải ngân hà -->
    <div class="starfield-layer-1"></div>
    <div class="starfield-layer-2"></div>
    <div class="bg-space-dust"></div>

    <!-- Hiệu ứng pháo hoa Canvas mượt mà, tự động nổ ra các hạt li ti -->
    <canvas id="fireworksCanvas" style="position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; pointer-events: none; z-index: 1;"></canvas>

    

  <!-- Script điều khiển pháo hoa tần suất cao và dày đặc -->
    <script>
    const canvas = document.getElementById('fireworksCanvas');
    const ctx = canvas.getContext('2d');

    function resizeCanvas() {
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;
    }
    resizeCanvas();
    window.addEventListener('resize', resizeCanvas);

    class Particle {
        constructor(x, y, color, velocity, size, isSecondStage = false) {
            this.x = x;
            this.y = y;
            this.color = color;
            this.velocity = velocity;
            this.size = size;
            this.alpha = 1;
            this.friction = 0.94;
            this.gravity = 0.025;
            this.isSecondStage = isSecondStage;
            this.hasExploded = false;
            this.life = 0;
            this.maxLife = isSecondStage ? Math.random() * 30 + 15 : Math.random() * 25 + 20;
        }

        draw() {
            ctx.save();
            ctx.globalAlpha = this.alpha;
            ctx.beginPath();
            ctx.arc(this.x, this.y, this.size, 0, Math.PI * 2);
            ctx.fillStyle = this.color;
            ctx.shadowBlur = 8;
            ctx.shadowColor = this.color;
            ctx.fill();
            ctx.restore();
        }

        update(particlesArray) {
            this.velocity.x *= this.friction;
            this.velocity.y *= this.friction;
            this.velocity.y += this.gravity;
            this.x += this.velocity.x;
            this.y += this.velocity.y;
            this.alpha -= 0.015;
            this.life++;

            // NỔ TẦNG 2: Nhân đôi số lượng hạt con văng ra để hiệu ứng dày đặc
            if (!this.isSecondStage && !this.hasExploded && this.life >= this.maxLife) {
                this.hasExploded = true;
                const subColors = ['#ff1450', '#00ff88', '#ffffff', '#ffcb00'];
                for (let i = 0; i < 10; i++) { // Tăng lên 10 hạt con mỗi đợt nổ
                    const angle = (Math.PI * 2 / 10) * i + Math.random();
                    const speed = Math.random() * 3 + 1;
                    particlesArray.push(new Particle(
                        this.x,
                        this.y,
                        subColors[Math.floor(Math.random() * subColors.length)],
                        { x: Math.cos(angle) * speed, y: Math.sin(angle) * speed },
                        Math.random() * 1.4 + 0.5,
                        true
                    ));
                }
            }
        }
    }

    let particles = [];
    const colors = ['#ff1450', '#00ff88', '#ffffff', '#ff3366', '#00ffaa'];

    function createFirework() {
        // Phủ pháo hoa toàn bộ chiều rộng màn hình (cả hai bên và khu vực lân cận)
        const x = Math.random() * canvas.width;
        const y = canvas.height * 0.2 + Math.random() * (canvas.height * 0.5);

        // Tăng số lượng tia phát nổ ban đầu lên 22 tia mỗi lần bắn
        for (let i = 0; i < 22; i++) {
            const angle = (Math.PI * 2 / 22) * i;
            const speed = Math.random() * 4.5 + 2;
            particles.push(new Particle(
                x, y,
                colors[Math.floor(Math.random() * colors.length)],
                { x: Math.cos(angle) * speed, y: Math.sin(angle) * speed },
                Math.random() * 2.2 + 1,
                false
            ));
        }
    }

    // BẮN LIÊN TỤC: Giảm thời gian xuống 800ms để pháo hoa nổ liên hoan liên tục
    setInterval(createFirework, 800);

    // Kích hoạt thêm 1 đợt chéo góc ngay khi vừa tải trang cho xôm tụ
    setTimeout(createFirework, 200);
    setTimeout(createFirework, 500);

    function animate() {
        requestAnimationFrame(animate);
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        
        particles.forEach((particle, index) => {
            if (particle.alpha <= 0) {
                particles.splice(index, 1);
            } else {
                particle.update(particles);
                particle.draw();
            }
        });
    }
    animate();
    </script>
</body>
</html>