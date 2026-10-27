<?php
session_start();
session_unset();
session_destroy();

// Dùng đường dẫn tương đối chính xác quay về index.php cùng thư mục
header("Location: ../index.php");
exit();
?>