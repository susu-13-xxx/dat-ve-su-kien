
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ArtTicket - Đặt vé triển lãm nghệ thuật</title>
    <?php
    $isAdminPage = strpos($_SERVER['SCRIPT_NAME'] ?? '', '/admin/') !== false;
    $basePath = $isAdminPage ? '../' : '';
    ?>
    <link rel="stylesheet" href="<?php echo $basePath; ?>assets/css/style.css?v=20261109">
</head>
<body>

<header class="header">
    <div class="container">
        <a href="<?php echo $basePath; ?>index.php" class="logo">ArtTicket.</a>

        <nav>
            <a href="<?php echo $basePath; ?>index.php">Trang chủ</a>
            <a href="<?php echo $basePath; ?>events.php">Triển lãm</a>
            <a href="<?php echo $basePath; ?>my-tickets.php">Vé của tôi</a>
            <?php if (($_SESSION["user_role"] ?? "user") === "admin"): ?>
                <a href="<?php echo $isAdminPage ? 'index.php' : 'admin/index.php'; ?>">Quản trị</a>
            <?php endif; ?>
        </nav>

        <div class="header-actions">
            <?php if (isset($_SESSION["user_id"])): ?>

                <span>
                    Xin chào, 
                    <?php echo htmlspecialchars($_SESSION["user_name"]); ?>
                </span>

                <a href="<?php echo $basePath; ?>logout.php" class="login-btn">Đăng xuất</a>

            <?php else: ?>

                <a href="<?php echo $basePath; ?>login.php" class="login-btn">Đăng nhập</a>
                <a href="<?php echo $basePath; ?>register.php" class="register-btn">Đăng ký</a>

            <?php endif; ?>
        </div>
    </div>
</header>
