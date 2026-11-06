
<?php
session_start();
require_once "config/database.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    $stmt = $conn->prepare(
        "SELECT * FROM users WHERE email = :email"
    );
    $stmt->execute(["email" => $email]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user["password"])) {
        $_SESSION["user_id"] = $user["id"];
        $_SESSION["user_name"] = $user["name"];

        header("Location: index.php");
        exit;
    } else {
        $error = "Email hoặc mật khẩu không chính xác!";
    }
}

include "includes/header.php";
?>

<main class="auth-page">
    <div class="auth-box">
        <h1 class="auth-heading">Đăng nhập</h1>
        <p>Chào mừng bạn quay trở lại ArtTicket.</p>

        <?php if (isset($_GET["registered"])): ?>
            <p style="color: green;">
                Đăng ký thành công! Hãy đăng nhập nhé.
            </p>
        <?php endif; ?>

        <?php if ($error !== ""): ?>
            <p style="color: red;">
                <?php echo htmlspecialchars($error); ?>
            </p>
        <?php endif; ?>

        <form method="POST" id="loginForm">
            <label>Email</label>
            <input
                type="email"
                name="email"
                placeholder="Nhập email"
                required
            >

            <label>Mật khẩu</label>
            <input
                type="password"
                name="password"
                placeholder="Nhập mật khẩu"
                required
            >

            <button type="submit" class="auth-btn">
                Đăng nhập
            </button>
        </form>

        <p class="auth-switch">
            Chưa có tài khoản?
            <a href="register.php">Đăng ký</a>
        </p>
    </div>
</main>

<?php include "includes/footer.php"; ?>
