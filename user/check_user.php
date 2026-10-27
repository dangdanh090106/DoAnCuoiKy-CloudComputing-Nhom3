<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Kiểm tra nếu chưa đăng nhập -> Đẩy về trang đăng nhập
if (!isset($_SESSION['ma_sv'])) {
    header("Location: login.php");
    exit();
}

// 2. Cho phép cả Sinh viên lẫn Quản lý sự kiện / Admin truy cập trang dashboard sinh viên
$allowed_roles = ['sinh_vien', 'quan_ly_su_kien', 'admin'];
if (!isset($_SESSION['vai_tro']) || !in_array($_SESSION['vai_tro'], $allowed_roles)) {
    header("Location: login.php");
    exit();
}