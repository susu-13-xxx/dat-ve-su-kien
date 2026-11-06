
<?php
session_start();
require_once "config/database.php";

// Yêu cầu đăng nhập
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

// Lấy danh sách vé của người đang đăng nhập
$sql = "SELECT
            b.*,
            e.name AS event_name,
            e.image AS event_image,
            e.start_date,
            e.location,
            p.status AS payment_status
        FROM bookings b
        JOIN events e ON b.event_id = e.id
        LEFT JOIN payments p ON p.booking_id = b.id
        WHERE b.user_id = :user_id
        ORDER BY b.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->execute([
    "user_id" => $_SESSION["user_id"]
]);

$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

include "includes/header.php";
?>

<main class="events-page">
    <div class="container">

        <h1 class="events-title">Vé của tôi</h1>

        <p>
            Xin chào <?php echo htmlspecialchars($_SESSION["user_name"]); ?>!
            Đây là danh sách vé bạn đã đặt tại ArtTicket.
        </p>

        <?php if (count($tickets) > 0): ?>

            <div class="events-grid">

                <?php foreach ($tickets as $ticket): ?>

                    <div class="event-card">

                        <img
                            src="<?php echo htmlspecialchars($ticket["event_image"]); ?>"
                            alt="<?php echo htmlspecialchars($ticket["event_name"]); ?>"
                        >

                        <div class="event-card-content">

                            <h3>
                                <?php echo htmlspecialchars($ticket["event_name"]); ?>
                            </h3>

                            <p>
                                Mã đơn:
                                <strong>
                                    <?php echo htmlspecialchars($ticket["booking_code"]); ?>
                                </strong>
                            </p>

                            <p>
                                Loại vé:
                                <?php echo $ticket["ticket_type"] === "standard"
                                    ? "Vé tiêu chuẩn" : "Vé đặc biệt"; ?>
                            </p>

                            <p>
                                Số lượng:
                                <?php echo $ticket["quantity"]; ?>
                            </p>

                            <p>
                                Ngày đặt:
                                <?php echo date(
                                    "d/m/Y H:i",
                                    strtotime($ticket["created_at"])
                                ); ?>
                            </p>

                            <p>
                                Tổng tiền:
                                <strong>
                                    <?php echo number_format(
                                        $ticket["total_amount"],
                                        0,
                                        ',',
                                        '.'
                                    ); ?>đ
                                </strong>
                            </p>

                            <p>
                                Trạng thái đơn hàng:
                                <strong>
                                    <?php
                                    echo $ticket["status"] === "paid"
                                        ? "Đã thanh toán"
                                        : "Chờ thanh toán";
                                    ?>
                                </strong>
                            </p>

                            <p>
                                Trạng thái thanh toán:
                                <?php
                                echo $ticket["payment_status"] === "paid"
                                    ? "Đã thanh toán"
                                    : "Chưa thanh toán";
                                ?>
                            </p>

                        </div>
                    </div>

                <?php endforeach; ?>

            </div>

        <?php else: ?>

            <div class="auth-box">
                <h2>Bạn chưa có đơn đặt vé nào!</h2>
                <p>Hãy khám phá những triển lãm thú vị tại ArtTicket nhé.</p>

                <a href="events.php" class="register-btn">
                    Khám phá triển lãm
                </a>
            </div>

        <?php endif; ?>

    </div>
</main>

<?php include "includes/footer.php"; ?>
