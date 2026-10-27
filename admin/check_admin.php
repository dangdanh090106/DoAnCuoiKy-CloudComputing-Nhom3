<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Nếu chưa đăng nhập hoặc không phải admin thì chuyển hướng về trang login
if (!isset($_SESSION['vai_tro']) || $_SESSION['vai_tro'] !== 'admin') {
    header("Location: login.php");
    exit();
}
?>