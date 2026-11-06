
<?php
session_start();
require_once "config/database.php";

// Kiểm tra đăng nhập
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$error = "";
$booking = null;
$event = null;

// Lấy đơn hàng đã tạo nếu có
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
                (:booking_id, :code, :amount, 'SePay', 'pending')";

            $stmt = $conn->prepare($sql);

            $stmt->execute([
                "booking_id" => $bookingId,
                "code" => $bookingCode,
                "amount" => $total
            ]);

            $conn->commit();

            // Chuyển hướng để tránh tạo đơn trùng khi tải lại
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

            <h2>Đặt vé thành công!</h2>

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
                    ? "Vé tiêu chuẩn" : "Vé đặc biệt"; ?>
            </p>

            <p>Số lượng:
                <?php echo $booking["quantity"]; ?>
            </p>

            <p>Tổng tiền:
                <strong>
                    <?php echo number_format($booking["total_amount"], 0, ',', '.'); ?>đ
                </strong>
            </p>

            <p>Trạng thái đơn hàng: Chờ thanh toán</p>

            <p>
                Thông tin QR thanh toán và xác nhận thanh toán
                sẽ được bổ sung ở bước tích hợp SePay.
            </p>

            <a href="my-tickets.php">Xem vé của tôi</a>

        <?php else: ?>

            <h2><?php echo htmlspecialchars($event["name"]); ?></h2>

            <p>Người đặt:
                <?php echo htmlspecialchars($_SESSION["user_name"]); ?>
            </p>

            <p>Loại vé:
                <?php echo $ticketType === "standard"
                    ? "Vé tiêu chuẩn" : "Vé đặc biệt"; ?>
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
                <input type="hidden" name="event_id"
                       value="<?php echo htmlspecialchars($eventId); ?>">

                <input type="hidden" name="ticket_type"
                       value="<?php echo htmlspecialchars($ticketType); ?>">

                <input type="hidden" name="quantity"
                       value="<?php echo $quantity; ?>">

                <button type="submit" class="auth-btn">
                    Tạo đơn hàng
                </button>
            </form>

        <?php endif; ?>

    </div>
</main>

<?php include "includes/footer.php"; ?>
