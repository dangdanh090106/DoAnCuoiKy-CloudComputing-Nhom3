<?php
session_start();
require_once __DIR__ . '/../database.php';

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tai_khoan = trim($_POST['tai_khoan'] ?? '');
    $mat_khau = trim($_POST['mat_khau'] ?? '');

    if (!empty($tai_khoan) && !empty($mat_khau)) {
        try {
            $stmt = $conn->prepare("SELECT * FROM users WHERE (ma_sv = ? OR email = ?) AND vai_tro = 'admin'");
            $stmt->execute([$tai_khoan, $tai_khoan]);
            $admin = $stmt->fetch();

            if ($admin) {
                $is_valid = ($mat_khau === $admin['mat_khau']) || password_verify($mat_khau, $admin['mat_khau']);

                if ($is_valid) {
                    $_SESSION['user_id'] = $admin['id'];
                    $_SESSION['ma_sv'] = $admin['ma_sv'];
                    $_SESSION['ho_ten'] = $admin['ho_ten'];
                    $_SESSION['vai_tro'] = 'admin';

                    header("Location: dashboard.php");
                    exit();
                } else {
                    $error = "Mật khẩu quản trị không chính xác!";
                }
            } else {
                $error = "Tài khoản không tồn tại hoặc không có quyền Quản trị viên!";
            }
        } catch (PDOException $e) {
            $error = "Lỗi hệ thống: " . $e->getMessage();
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
    <title>Đăng nhập Quản trị viên</title>
    <!-- Thêm Bootstrap Icons nếu cần -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../css/admin-login.css">
</head>
<body>
    <!-- Các lớp nền vũ trụ và dải ngân hà -->
    <div class="starfield-layer-1"></div>
    <div class="starfield-layer-2"></div>
    <div class="bg-space-dust"></div>

    <!-- Hiệu ứng pháo hoa Canvas tự động nổ ra các hạt li ti -->
    <canvas id="fireworksCanvas" style="position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; pointer-events: none; z-index: 1;"></canvas>

    <div class="login-box">
        <h2>Đăng nhập Admin</h2>
        
        <?php if (!empty($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Tài khoản</label>
                <input type="text" name="tai_khoan" required placeholder="Nhập tài khoản admin">
            </div>
            <div class="form-group">
                <label>Mật khẩu</label>
                <input type="password" name="mat_khau" required placeholder="Nhập mật khẩu">
            </div>
            <button type="submit">
                <i class="bi bi-shield-lock-fill me-2"></i> Đăng nhập
            </button>
        </form>
    </div>

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

            if (!this.isSecondStage && !this.hasExploded && this.life >= this.maxLife) {
                this.hasExploded = true;
                const subColors = ['#ff1450', '#00ff88', '#ffffff', '#ffcb00'];
                for (let i = 0; i < 10; i++) {
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
        const x = Math.random() * canvas.width;
        const y = canvas.height * 0.2 + Math.random() * (canvas.height * 0.5);

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

    setInterval(createFirework, 800);
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