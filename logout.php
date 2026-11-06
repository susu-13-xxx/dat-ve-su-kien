
<?php
session_start();

// Xóa thông tin đăng nhập
session_unset();
session_destroy();

// Quay về trang chủ
header("Location: index.php");
exit;
?>
