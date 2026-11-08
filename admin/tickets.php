<?php
session_start();
require_once __DIR__ . "/../config/database.php";

if (!isset($_SESSION["user_id"]) || ($_SESSION["user_role"] ?? "user") !== "admin") {
    http_response_code(403);
    exit("Ban khong co quyen truy cap.");
}

$keyword = trim($_GET["q"] ?? "");
$sql = "
    SELECT t.ticket_code, t.status AS ticket_status, t.issued_at, t.used_at,
           b.booking_code, b.status AS booking_status, b.quantity,
           u.name AS customer_name, u.email,
           e.name AS event_name, p.status AS payment_status
    FROM tickets t
    JOIN bookings b ON b.id = t.booking_id
    LEFT JOIN users u ON u.id = b.user_id
    JOIN events e ON e.id = b.event_id
    LEFT JOIN payments p ON p.booking_id = b.id
";
$params = [];
if ($keyword !== "") {
    $sql .= " WHERE t.ticket_code LIKE :q OR b.booking_code LIKE :q OR u.name LIKE :q OR u.email LIKE :q OR e.name LIKE :q";
    $params["q"] = "%" . $keyword . "%";
}
$sql .= " ORDER BY t.issued_at DESC, t.id DESC LIMIT 300";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . "/../includes/header.php";
?>
<main class="events-page admin-page">
    <div class="container">
        <h1 class="events-title">Quản lý vé</h1>
        <p style="margin-bottom:20px;"><a href="index.php">← Quay lại tổng quan</a></p>

        <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px;">
            <input type="search" name="q" value="<?php echo htmlspecialchars($keyword); ?>"
                   placeholder="Tìm mã vé, mã đơn, khách hàng, email hoặc sự kiện..."
                   style="flex:1;min-width:240px;padding:12px;border:1px solid #ddd;border-radius:8px;">
            <button class="auth-btn" type="submit">Tìm kiếm</button>
            <?php if ($keyword !== ""): ?><a class="register-btn" href="tickets.php">Xóa tìm kiếm</a><?php endif; ?>
        </form>

        <div style="background:white;padding:24px;border-radius:12px;overflow-x:auto;">
            <p style="margin-bottom:15px;">Hiển thị tối đa 300 vé. Trang này chỉ tra cứu, không tự ý thay đổi trạng thái vé.</p>
            <table style="width:100%;border-collapse:collapse;min-width:1050px;">
                <thead><tr style="text-align:left;background:#f8f7f2;">
                    <th style="padding:12px;">Mã vé</th><th>Sự kiện</th><th>Khách hàng</th>
                    <th>Mã đơn</th><th>Thanh toán</th><th>Trạng thái vé</th><th>Thời gian sử dụng</th>
                </tr></thead>
                <tbody>
                <?php if (!$tickets): ?>
                    <tr><td colspan="7" style="padding:15px;">Không tìm thấy vé nào.</td></tr>
                <?php else: foreach ($tickets as $ticket): ?>
                    <tr style="border-top:1px solid #eee;">
                        <td style="padding:12px;"><strong><?php echo htmlspecialchars($ticket["ticket_code"]); ?></strong></td>
                        <td><?php echo htmlspecialchars($ticket["event_name"]); ?></td>
                        <td><?php echo htmlspecialchars($ticket["customer_name"] ?? "Khách"); ?><br><small><?php echo htmlspecialchars($ticket["email"] ?? ""); ?></small></td>
                        <td><?php echo htmlspecialchars($ticket["booking_code"]); ?></td>
                        <td><?php echo ($ticket["payment_status"] ?? $ticket["booking_status"]) === "paid" ? "Đã thanh toán" : "Chưa thanh toán"; ?></td>
                        <td><?php echo $ticket["ticket_status"] === "used" ? "Đã sử dụng" : ($ticket["ticket_status"] === "valid" ? "Chưa sử dụng" : htmlspecialchars($ticket["ticket_status"])); ?></td>
                        <td><?php echo $ticket["used_at"] ? htmlspecialchars($ticket["used_at"]) : "—"; ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>
<?php include __DIR__ . "/../includes/footer.php"; ?>
