<?php
session_start();
require_once __DIR__ . "/../config/database.php";

if (!isset($_SESSION["user_id"]) || ($_SESSION["user_role"] ?? "user") !== "admin") {
    http_response_code(403);
    exit("Ban khong co quyen truy cap khu vuc quan tri.");
}

$eventCount = (int)$conn->query("SELECT COUNT(*) FROM events")->fetchColumn();
$activeEventCount = (int)$conn->query("SELECT COUNT(*) FROM events WHERE status = 'active'")->fetchColumn();
$bookingCount = (int)$conn->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
$paidBookingCount = (int)$conn->query("SELECT COUNT(*) FROM bookings WHERE status = 'paid'")->fetchColumn();
$revenue = (float)$conn->query("SELECT COALESCE(SUM(total_amount), 0) FROM bookings WHERE status = 'paid'")->fetchColumn();

$soldTicketCount = (int)$conn->query("
    SELECT COUNT(*)
    FROM tickets t
    JOIN bookings b ON b.id = t.booking_id
    WHERE b.status = 'paid'
")->fetchColumn();
$usedTicketCount = (int)$conn->query("
    SELECT COUNT(*)
    FROM tickets t
    JOIN bookings b ON b.id = t.booking_id
    WHERE b.status = 'paid' AND t.status = 'used'
")->fetchColumn();
$unusedTicketCount = max(0, $soldTicketCount - $usedTicketCount);

$monthlyStmt = $conn->query("
    SELECT DATE_FORMAT(created_at, '%Y-%m') AS month_key,
           SUM(total_amount) AS monthly_revenue
    FROM bookings
    WHERE status = 'paid'
      AND created_at >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month_key ASC
");
$monthlyRows = [];
foreach ($monthlyStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $monthlyRows[$row['month_key']] = (float)$row['monthly_revenue'];
}
$monthlyStats = [];
for ($i = 5; $i >= 0; $i--) {
    $monthDate = new DateTime("first day of -{$i} month");
    $monthKey = $monthDate->format('Y-m');
    $monthlyStats[] = [
        'label' => $monthDate->format('m/Y'),
        'revenue' => $monthlyRows[$monthKey] ?? 0
    ];
}
$maxMonthlyRevenue = max(array_column($monthlyStats, 'revenue'));


$latestStmt = $conn->query("
    SELECT b.booking_code, b.quantity, b.total_amount, b.status, b.created_at,
           u.name AS customer_name, e.name AS event_name
    FROM bookings b
    LEFT JOIN users u ON u.id = b.user_id
    JOIN events e ON e.id = b.event_id
    ORDER BY b.created_at DESC
    LIMIT 8
");
$latestBookings = $latestStmt->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . "/../includes/header.php";
?>
<main class="events-page admin-page">
    <div class="container">
        <h1 class="events-title">Quản trị ArtTicket</h1>
        <p style="text-align:center;margin-bottom:30px;">Xin chào quản trị viên <?php echo htmlspecialchars($_SESSION["user_name"]); ?>!</p>
        <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:25px;">
            <a class="register-btn" href="events.php">Quản lý sự kiện</a>
            <a class="register-btn" href="orders.php">Quản lý đơn đặt vé</a>
            <a class="register-btn" href="tickets.php">Quản lý vé</a>
            <a class="register-btn" href="../scan-ticket.php">Cổng kiểm soát vé</a>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:18px;margin-bottom:30px;">
            <?php
            $stats = [
                ["Tổng sự kiện", $eventCount],
                ["Sự kiện đang mở", $activeEventCount],
                ["Tổng đơn đặt vé", $bookingCount],
                ["Đơn đã thanh toán", $paidBookingCount],
                ["Tổng vé đã bán", $soldTicketCount],
                ["Vé đã sử dụng", $usedTicketCount],
                ["Vé chưa sử dụng", $unusedTicketCount],
                ["Doanh thu", number_format($revenue, 0, ",", ".") . "đ"]
            ];
            foreach ($stats as $stat):
            ?>
                <div class="admin-stat" style="background:white;padding:22px;border-radius:12px;box-shadow:0 3px 12px rgba(0,0,0,.05);">
                    <p style="color:#777;margin-bottom:10px;"><?php echo htmlspecialchars($stat[0]); ?></p>
                    <strong style="font-size:24px;color:#a47c30;"><?php echo htmlspecialchars((string)$stat[1]); ?></strong>
                </div>
            <?php endforeach; ?>
        </div>
        <div style="background:white;padding:24px;border-radius:12px;box-shadow:0 3px 12px rgba(0,0,0,.05);margin-bottom:25px;">
            <h2 style="margin-bottom:8px;">Doanh thu</h2>
            <p style="color:#777;margin-bottom:20px;font-size:14px;">Chỉ tính các đơn đã thanh toán, theo ngày tạo đơn.</p>
            <div style="display:grid;gap:14px;">
                <?php foreach ($monthlyStats as $month): ?>
                    <?php $barWidth = $maxMonthlyRevenue > 0 ? ($month['revenue'] / $maxMonthlyRevenue) * 100 : 0; ?>
                    <div>
                        <div style="display:flex;justify-content:space-between;gap:12px;margin-bottom:6px;font-size:14px;">
                            <strong><?php echo htmlspecialchars($month['label']); ?></strong>
                            <span><?php echo number_format($month['revenue'], 0, ",", "."); ?>đ</span>
                        </div>
                        <div style="height:12px;background:#f1eee7;border-radius:20px;overflow:hidden;">
                            <div style="height:100%;width:<?php echo $barWidth; ?>%;background:#a47c30;border-radius:20px;"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div style="background:white;padding:24px;border-radius:12px;overflow-x:auto;">
            <h2 style="margin-bottom:18px;">Đơn đặt vé</h2>
            <table style="width:100%;border-collapse:collapse;min-width:700px;">
                <thead><tr style="text-align:left;background:#f8f7f2;">
                    <th style="padding:12px;">Mã đơn</th><th>Khách hàng</th><th>Sự kiện</th><th>Số vé</th><th>Tổng tiền</th><th>Trạng thái</th>
                </tr></thead>
                <tbody>
                <?php if (!$latestBookings): ?>
                    <tr><td colspan="6" style="padding:15px;">Chưa có đơn đặt vé.</td></tr>
                <?php else: foreach ($latestBookings as $booking): ?>
                    <tr style="border-top:1px solid #eee;">
                        <td style="padding:12px;"><?php echo htmlspecialchars($booking["booking_code"]); ?></td>
                        <td><?php echo htmlspecialchars($booking["customer_name"] ?? "Khách"); ?></td>
                        <td><?php echo htmlspecialchars($booking["event_name"]); ?></td>
                        <td><?php echo (int)$booking["quantity"]; ?></td>
                        <td><?php echo number_format((float)$booking["total_amount"],0,",","."); ?>đ</td>
                        <td><?php echo $booking["status"] === "paid" ? "Đã thanh toán" : "Chờ thanh toán"; ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>
<?php include __DIR__ . "/../includes/footer.php"; ?>
