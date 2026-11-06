
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
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<header class="header">
    <div class="container">
        <a href="index.php" class="logo">ArtTicket.</a>

        <nav>
            <a href="index.php">Trang chủ</a>
            <a href="events.php">Triển lãm</a>
            <a href="my-tickets.php">Vé của tôi</a>
        </nav>

        <div class="header-actions">
            <?php if (isset($_SESSION["user_id"])): ?>

                <span>
                    Xin chào, 
                    <?php echo htmlspecialchars($_SESSION["user_name"]); ?>
                </span>

                <a href="logout.php" class="login-btn">Đăng xuất</a>

            <?php else: ?>

                <a href="login.php" class="login-btn">Đăng nhập</a>
                <a href="register.php" class="register-btn">Đăng ký</a>

            <?php endif; ?>
        </div>
    </div>
</header>
