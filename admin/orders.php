<?php
session_start();
require_once __DIR__ . "/../config/database.php";
if (!isset($_SESSION["user_id"]) || ($_SESSION["user_role"] ?? "user") !== "admin") { http_response_code(403); exit("Ban khong co quyen truy cap."); }
$stmt = $conn->query("
 SELECT b.booking_code,b.quantity,b.ticket_type,b.total_amount,b.status,b.created_at,
        u.name AS customer_name,u.email,e.name AS event_name,p.status AS payment_status,
        p.transaction_code,p.paid_at
 FROM bookings b LEFT JOIN users u ON u.id=b.user_id
 JOIN events e ON e.id=b.event_id
 LEFT JOIN payments p ON p.booking_id=b.id
 ORDER BY b.created_at DESC
");
$orders=$stmt->fetchAll(PDO::FETCH_ASSOC);
include __DIR__ . "/../includes/header.php";
?>
<main class="events-page"><div class="container"><h1 class="events-title">Quản lý đơn đặt vé</h1>
<p style="margin-bottom:20px;"><a href="index.php">← Quay lại tổng quan</a></p>
<div style="background:white;padding:24px;border-radius:12px;overflow-x:auto;"><table style="width:100%;border-collapse:collapse;min-width:1000px;">
<thead><tr style="text-align:left;background:#f8f7f2;"><th style="padding:12px;">Mã đơn</th><th>Khách hàng</th><th>Email</th><th>Sự kiện</th><th>Số vé</th><th>Tổng tiền</th><th>Thanh toán</th><th>Ngày đặt</th></tr></thead><tbody>
<?php if (!$orders): ?><tr><td colspan="8" style="padding:15px;">Chưa có đơn đặt vé.</td></tr><?php else: foreach($orders as $order): ?>
<tr style="border-top:1px solid #eee;"><td style="padding:12px;"><?php echo htmlspecialchars($order["booking_code"]); ?></td><td><?php echo htmlspecialchars($order["customer_name"] ?? "Khách"); ?></td><td><?php echo htmlspecialchars($order["email"] ?? ""); ?></td><td><?php echo htmlspecialchars($order["event_name"]); ?></td><td><?php echo (int)$order["quantity"]; ?></td><td><?php echo number_format((float)$order["total_amount"],0,",","."); ?>đ</td><td><?php echo ($order["payment_status"] ?? $order["status"]) === "paid" ? "Đã thanh toán" : "Chờ thanh toán"; ?></td><td><?php echo htmlspecialchars($order["created_at"]); ?></td></tr>
<?php endforeach; endif; ?></tbody></table></div></div></main>
<?php include __DIR__ . "/../includes/footer.php"; ?>
