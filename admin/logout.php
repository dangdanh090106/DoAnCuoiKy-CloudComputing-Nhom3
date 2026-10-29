<?php
session_start();
session_unset();
session_destroy();
// Chuyển hướng về trang chủ ngoài thư mục gốc
header('Location: ../index.php');
exit;
?>