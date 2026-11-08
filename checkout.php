<?php
session_start();

require_once "config/database.php";
require_once "config/sepay-bank.php";

// Kiểm tra đăng nhập
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$error = "";
$booking = null;
$event = null;
$qrUrl = "";

// Kiểm tra cấu hình ngân hàng
$bankConfigValid = (
    !empty($SEPAY_BANK) &&
    !empty($SEPAY_ACCOUNT) &&
    !empty($SEPAY_ACCOUNT_NAME)
);

// Nếu đã có mã đơn hàng
if (isset($_GET["booking_code"])) {

    $stmt = $conn->prepare("
        SELECT b.*, e.name AS event_name, e.image,
               p.status AS payment_status
        FROM bookings b
        JOIN events e ON b.event_id = e.id
        LEFT JOIN payments p ON p.booking_id = b.id
        WHERE b.booking_code = :code
        AND b.user_id = :user_id
    ");

    $stmt->execute([
        "code" => $_GET["booking_code"],
        "user_id" => $_SESSION["user_id"]
    ]);

    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        die("Không tìm thấy đơn đặt vé!");
    }

    // Tạo QR nếu đơn chưa thanh toán
    if (
        $booking["status"] === "pending" &&
        $booking["payment_status"] === "pending" &&
        $bankConfigValid
    ) {
        // Nội dung chuyển khoản bắt buộc có SEVQR
        $transferContent = "SEVQR " . $booking["booking_code"];

        $params = [
            "acc" => $SEPAY_ACCOUNT,
            "bank" => $SEPAY_BANK,
            "amount" => (int)$booking["total_amount"],
            "des" => $transferContent,
            "template" => "compact",
            "showinfo" => "true",
            "holder" => $SEPAY_ACCOUNT_NAME
        ];

        $qrUrl = "https://vietqr.app/img?" . http_build_query($params);
    }

} else {

    // Lấy thông tin triển lãm
    $eventId = $_POST["event_id"] ?? $_GET["event_id"] ?? "";
    $ticketType = $_POST["ticket_type"] ?? $_GET["ticket_type"] ?? "standard";
    $quantity = (int)($_POST["quantity"] ?? $_GET["quantity"] ?? 1);

    if (!in_array($ticketType, ["standard", "special"])) {
        die("Loại vé không hợp lệ!");
    }

    if ($quantity < 1 || $quantity > 10) {
        die("Số lượng vé phải từ 1 đến 10!");
    }

    $stmt = $conn->prepare("
        SELECT * FROM events
        WHERE event_code = :id
        AND status = 'active'
    ");

    $stmt->execute(["id" => $eventId]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$event) {
        die("Không tìm thấy triển lãm!");
    }

    // Lấy giá vé từ database
    $ticketPrice = ($ticketType === "standard")
        ? $event["standard_price"]
        : $event["special_price"];

    $total = $ticketPrice * $quantity;

    // Tạo đơn hàng
    if ($_SERVER["REQUEST_METHOD"] === "POST") {

        $bookingCode = "ART" . date("YmdHis")
            . strtoupper(substr(uniqid(), -4));

        try {
            $conn->beginTransaction();

            // Lưu đơn đặt vé
            $sql = "INSERT INTO bookings
                (booking_code, user_id, event_id, ticket_type,
                 quantity, unit_price, total_amount, status)
                VALUES
                (:code, :user_id, :event_id, :type,
                 :quantity, :price, :total, 'pending')";

            $stmt = $conn->prepare($sql);

            $stmt->execute([
                "user_id" => $_SESSION["user_id"],
                "code" => $bookingCode,
                "event_id" => $event["id"],
                "type" => $ticketType,
                "quantity" => $quantity,
                "price" => $ticketPrice,
                "total" => $total
            ]);

            $bookingId = $conn->lastInsertId();

            // Tạo thông tin thanh toán
            $sql = "INSERT INTO payments
                (booking_id, transaction_code, amount,
                 payment_method, status)
                VALUES
                (:booking_id, :code, :amount, 'sepay', 'pending')";

            $stmt = $conn->prepare($sql);

            $stmt->execute([
                "booking_id" => $bookingId,
                "code" => $bookingCode,
                "amount" => $total
            ]);

            $conn->commit();

            // Chuyển đến trang thanh toán
            header("Location: checkout.php?booking_code=" . urlencode($bookingCode));
            exit;

        } catch (Exception $e) {

            if ($conn->inTransaction()) {
                $conn->rollBack();
            }

            $error = "Không thể tạo đơn hàng: " . $e->getMessage();
        }
    }
}

include "includes/header.php";
?>

<main class="auth-page">
    <div class="auth-box">

        <h1 class="auth-heading">Thanh toán vé</h1>

        <?php if ($error !== ""): ?>
            <p style="color:red;">
                <?php echo htmlspecialchars($error); ?>
            </p>
        <?php endif; ?>


        <?php if ($booking): ?>

            <h2>
                <?php echo $booking["status"] === "paid"
                    ? "Thanh toán thành công!"
                    : "Thông tin đơn hàng"; ?>
            </h2>

            <p>Mã đơn hàng:
                <strong>
                    <?php echo htmlspecialchars($booking["booking_code"]); ?>
                </strong>
            </p>

            <p>Triển lãm:
                <?php echo htmlspecialchars($booking["event_name"]); ?>
            </p>

            <p>Loại vé:
                <?php echo $booking["ticket_type"] === "standard"
                    ? "Vé tiêu chuẩn"
                    : "Vé đặc biệt"; ?>
            </p>

            <p>Số lượng:
                <?php echo (int)$booking["quantity"]; ?>
            </p>

            <p>Tổng tiền:
                <strong>
                    <?php echo number_format($booking["total_amount"], 0, ',', '.'); ?>đ
                </strong>
            </p>


            <?php if (
                $booking["status"] === "paid" ||
                $booking["payment_status"] === "paid"
            ): ?>

                <div role="status" style="margin:18px 0; padding:16px; border-radius:10px; background:#e8f7ee; color:#18733b;">
                    <h3 style="margin:0 0 8px;">✓ Thanh toán thành công!</h3>
                    <p style="margin:0;">ArtTicket đã nhận được thanh toán và xác nhận đơn hàng của bạn.</p>
                </div>

                <a href="my-tickets.php" class="auth-btn" style="display:inline-block; text-decoration:none; text-align:center;">Xem vé của tôi</a>


            <?php elseif ($booking["status"] === "pending"): ?>

                <h3>Quét mã QR để thanh toán</h3>

                <?php if ($qrUrl !== ""): ?>

                    <div style="text-align:center; margin:20px 0;">

                        <img
                            src="<?php echo htmlspecialchars($qrUrl, ENT_QUOTES, 'UTF-8'); ?>"
                            alt="QR thanh toán SePay"
                            style="width:260px; max-width:100%;"
                        >

                    </div>

                    <p>
                        Ngân hàng:
                        <strong><?php echo htmlspecialchars($SEPAY_BANK); ?></strong>
                    </p>

                    <p>
                        Số tài khoản:
                        <strong>
                            <?php echo htmlspecialchars($SEPAY_ACCOUNT); ?>
                        </strong>
                    </p>

                    <p>
                        Chủ tài khoản:
                        <strong>
                            <?php echo htmlspecialchars($SEPAY_ACCOUNT_NAME); ?>
                        </strong>
                    </p>

                    <p>
                        Số tiền cần chuyển:
                        <strong>
                            <?php echo number_format($booking["total_amount"], 0, ',', '.'); ?>đ
                        </strong>
                    </p>

                    <p>
                        Nội dung chuyển khoản:
                        <strong>
                            <?php echo htmlspecialchars(
                                "SEVQR " . $booking["booking_code"]
                            ); ?>
                        </strong>
                    </p>

                    <p style="color:#d97706;">
                        Vui lòng chuyển đúng số tiền và nội dung trên.
                    </p>

                <?php else: ?>

                    <p style="color:red;">
                        Chưa cấu hình thông tin tài khoản nhận tiền.
                        Vui lòng kiểm tra config/sepay-bank.php.
                    </p>

                <?php endif; ?>

                <p style="color:orange;">
                    Trạng thái: Đang chờ thanh toán
                </p>

                <p style="font-size:14px; color:#666;">
                    Trang sẽ tự kiểm tra trạng thái thanh toán sau mỗi 8 giây. Sau khi SePay xác nhận, thông báo thành công sẽ tự hiển thị.
                </p>

                <script>
                    // Tự tải lại để nhận trạng thái mới từ webhook SePay.
                    setTimeout(function () {
                        window.location.reload();
                    }, 8000);
                </script>

                <button
                    type="button"
                    class="auth-btn"
                    onclick="location.reload()"
                >
                    Kiểm tra trạng thái thanh toán
                </button>

            <?php else: ?>

                <p>Trạng thái đơn hàng:
                    <?php echo htmlspecialchars($booking["status"]); ?>
                </p>

            <?php endif; ?>


        <?php else: ?>

            <h2><?php echo htmlspecialchars($event["name"]); ?></h2>

            <p>Người đặt:
                <?php echo htmlspecialchars($_SESSION["user_name"]); ?>
            </p>

            <p>Loại vé:
                <?php echo $ticketType === "standard"
                    ? "Vé tiêu chuẩn"
                    : "Vé đặc biệt"; ?>
            </p>

            <p>Số lượng: <?php echo $quantity; ?></p>

            <p>Đơn giá:
                <?php echo number_format($ticketPrice, 0, ',', '.'); ?>đ
            </p>

            <h3>
                Tổng tiền:
                <?php echo number_format($total, 0, ',', '.'); ?>đ
            </h3>

            <form method="POST">

                <input
                    type="hidden"
                    name="event_id"
                    value="<?php echo htmlspecialchars($eventId); ?>"
                >

                <input
                    type="hidden"
                    name="ticket_type"
                    value="<?php echo htmlspecialchars($ticketType); ?>"
                >

                <input
                    type="hidden"
                    name="quantity"
                    value="<?php echo $quantity; ?>"
                >

                <button type="submit" class="auth-btn">
                    Tạo đơn hàng
                </button>

            </form>

        <?php endif; ?>

    </div>
</main>

<?php include "includes/footer.php"; ?>