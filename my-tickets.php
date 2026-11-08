
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

// Bo sung ve cho cac don da thanh toan tu truoc khi tinh nang ve dien tu duoc them.
$findTickets = $conn->prepare("SELECT COUNT(*) FROM tickets WHERE booking_id = :id");
$createTicket = $conn->prepare("
    INSERT INTO tickets (booking_id, ticket_code, status)
    VALUES (:booking_id, :ticket_code, 'valid')
");
foreach ($tickets as $booking) {
    if ($booking["status"] !== "paid" || $booking["payment_status"] !== "paid") {
        continue;
    }

    $findTickets->execute(["id" => $booking["id"]]);
    $missing = max(0, (int)$booking["quantity"] - (int)$findTickets->fetchColumn());

    for ($i = 0; $i < $missing; $i++) {
        $createTicket->execute([
            "booking_id" => $booking["id"],
            "ticket_code" => "TKT-" . strtoupper(bin2hex(random_bytes(8)))
        ]);
    }
}

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

                            <?php if ($ticket["status"] === "paid" && $ticket["payment_status"] === "paid"): ?>
                                <div style="margin-top:15px; padding:12px; border-radius:8px; background:#e8f7ee; color:#18733b;">
                                    <strong>✓ Thanh toán thành công</strong>
                                    <p style="margin:5px 0 0;">Vé của bạn đã được xác nhận.</p>
                                </div>

                                <?php
                                $ticketStmt = $conn->prepare("
                                    SELECT ticket_code, status, issued_at
                                    FROM tickets
                                    WHERE booking_id = :booking_id
                                    ORDER BY id ASC
                                ");
                                $ticketStmt->execute(["booking_id" => $ticket["id"]]);
                                $issuedTickets = $ticketStmt->fetchAll(PDO::FETCH_ASSOC);
                                ?>

                                <?php if ($issuedTickets): ?>
                                    <div style="margin-top:16px;">
                                        <h4>Vé điện tử của bạn</h4>
                                        <?php foreach ($issuedTickets as $index => $issuedTicket): ?>
                                            <?php
                                            $qrData = "ARTTICKET:" . $issuedTicket["ticket_code"];
                                            $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?" . http_build_query([
                                                "size" => "180x180",
                                                "data" => $qrData
                                            ]);
                                            ?>
                                            <div style="margin:12px 0; padding:14px; border:1px solid #ddd; border-radius:10px; text-align:center;">
                                                <strong>Vé số <?php echo $index + 1; ?></strong>
                                                <div style="margin:10px;">
                                                    <img src="<?php echo htmlspecialchars($qrUrl, ENT_QUOTES, "UTF-8"); ?>" alt="Mã QR vé điện tử" width="180" height="180">
                                                </div>
                                                <p>Mã vé: <strong><?php echo htmlspecialchars($issuedTicket["ticket_code"]); ?></strong></p>
                                                <p style="margin:4px 0;">Trạng thái: <?php echo htmlspecialchars($issuedTicket["status"]); ?></p>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <p style="margin-top:12px;">Vé điện tử đang được khởi tạo. Vui lòng tải lại trang sau ít phút.</p>
                                <?php endif; ?>
                            <?php else: ?>
                                <div style="margin-top:15px; padding:12px; border-radius:8px; background:#fff7e6; color:#9a6700;">
                                    <strong>Đang chờ thanh toán</strong>
                                    <p style="margin:5px 0 0;">Đơn hàng sẽ được xác nhận sau khi hệ thống nhận được khoản thanh toán.</p>
                                </div>
                            <?php endif; ?>

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
