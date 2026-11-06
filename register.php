
<?php
session_start();
require_once "config/database.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirmPassword = $_POST["confirmPassword"] ?? "";

    if ($name === "" || $email === "" || $password === "") {
        $error = "Vui lòng nhập đầy đủ thông tin!";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Email không hợp lệ!";

    } elseif (strlen($password) < 6) {
        $error = "Mật khẩu phải có ít nhất 6 ký tự!";

    } elseif ($password !== $confirmPassword) {
        $error = "Mật khẩu xác nhận không khớp!";

    } else {
        // Kiểm tra email đã tồn tại
        $stmt = $conn->prepare(
            "SELECT id FROM users WHERE email = :email"
        );

        $stmt->execute(["email" => $email]);

        if ($stmt->fetch()) {
            $error = "Email này đã được đăng ký!";

        } else {
            // Mã hóa mật khẩu
            $hashedPassword = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            // Lưu tài khoản
            $stmt = $conn->prepare(
                "INSERT INTO users (name, email, password)
                 VALUES (:name, :email, :password)"
            );

            $stmt->execute([
                "name" => $name,
                "email" => $email,
                "password" => $hashedPassword
            ]);

            header("Location: login.php?registered=1");
            exit;
        }
    }
}

include "includes/header.php";
?>

<main class="auth-page">
    <div class="auth-box">

        <h1 class="auth-heading">Tạo tài khoản</h1>

        <p>Đăng ký để bắt đầu hành trình khám phá nghệ thuật.</p>

        <?php if ($error !== ""): ?>
            <p style="color: red;">
                <?php echo htmlspecialchars($error); ?>
            </p>
        <?php endif; ?>

        <form method="POST" id="registerForm">

            <label>Họ và tên</label>
            <input
                type="text"
                name="name"
                placeholder="Nhập họ tên"
                required
                value="<?php echo htmlspecialchars($_POST["name"] ?? ""); ?>"
            >

            <label>Email</label>
            <input
                type="email"
                name="email"
                placeholder="Nhập email"
                required
                value="<?php echo htmlspecialchars($_POST["email"] ?? ""); ?>"
            >

            <label>Mật khẩu</label>
            <input
                type="password"
                name="password"
                id="password"
                placeholder="Ít nhất 6 ký tự"
                required
            >

            <label>Xác nhận mật khẩu</label>
            <input
                type="password"
                name="confirmPassword"
                id="confirmPassword"
                placeholder="Nhập lại mật khẩu"
                required
            >

            <button type="submit" class="auth-btn">
                Đăng ký
            </button>

        </form>

        <p class="auth-switch">
            Đã có tài khoản?
            <a href="login.php">Đăng nhập</a>
        </p>

    </div>
</main>

<?php include "includes/footer.php"; ?>
