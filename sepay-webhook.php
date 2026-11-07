
<?php
require_once __DIR__ . "/config/database.php";
require_once __DIR__ . "/config/sepay.php";

header("Content-Type: application/json; charset=UTF-8");

function response($status, $data) {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

// 1. Chỉ nhận POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    response(405, ["success" => false]);
}

// 2. Lấy dữ liệu gốc
$body = file_get_contents("php://input");

if (!$body) {
    response(400, ["success" => false]);
}

// 3. Xác thực chữ ký HMAC-SHA256
$signature = $_SERVER["HTTP_X_SEPAY_SIGNATURE"] ?? "";
$timestamp = (int) ($_SERVER["HTTP_X_SEPAY_TIMESTAMP"] ?? 0);

if ($timestamp <= 0 || abs(time() - $timestamp) > 300) {
    response(401, ["success" => false]);
}

$expected = "sha256=" . hash_hmac(
    "sha256",
    $timestamp . "." . $body,
    SEPAY_WEBHOOK_SECRET
);

if (!hash_equals($expected, $signature)) {
    response(401, ["success" => false]);
}

// 4. Đọc dữ liệu giao dịch
$data = json_decode($body, true);

if (!is_array($data)) {
    response(400, ["success" => false]);
}

// Chỉ nhận giao dịch tiền vào
if (($data["transferType"] ?? "") !== "in") {
    response(200, ["success" => true]);
}

// 5. Tìm mã đơn hàng trong nội dung chuyển khoản
$text = implode(" ", [
    $data["code"] ?? "",
    $data["content"] ?? "",
    $data["description"] ?? ""
]);

if (!preg_match('/ART\d{14}[A-Z0-9]{4}/', $text, $matches)) {
    response(200, ["success" => true]);
}

$bookingCode = $matches[0];
$amount = $data["transferAmount"] ?? 0;

if (!is_numeric($amount) || (float)$amount <= 0) {
    response(400, ["success" => false]);
}

try {
    $conn->beginTransaction();

    // 6. Tìm đơn hàng và khóa bản ghi khi xử lý
    $stmt = $conn->prepare("
        SELECT
            b.id,
            b.total_amount,
            b.status AS booking_status,
            p.id AS payment_id,
            p.status AS payment_status
        FROM bookings b
        INNER JOIN payments p ON p.booking_id = b.id
        WHERE b.booking_code = :booking_code
        LIMIT 1
        FOR UPDATE
    ");

    $stmt->execute([
        "booking_code" => $bookingCode
    ]);

    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        $conn->rollBack();
        response(200, ["success" => true]);
    }

    // 7. Chống xác nhận trùng
    if (
        $order["booking_status"] === "paid" &&
        $order["payment_status"] === "paid"
    ) {
        $conn->commit();
        response(200, ["success" => true]);
    }

    // Chỉ xử lý đơn đang chờ thanh toán
    if (
        $order["booking_status"] !== "pending" ||
        $order["payment_status"] !== "pending"
    ) {
        $conn->commit();
        response(200, ["success" => true]);
    }

    // 8. Kiểm tra chính xác số tiền
    if ((float)$amount != (float)$order["total_amount"]) {
        $conn->rollBack();
        response(200, ["success" => true]);
    }

    // 9. Cập nhật trạng thái thanh toán
    $stmt = $conn->prepare("
        UPDATE payments
        SET status = 'paid'
        WHERE id = :id
    ");

    $stmt->execute([
        "id" => $order["payment_id"]
    ]);

    $stmt = $conn->prepare("
        UPDATE bookings
        SET status = 'paid'
        WHERE id = :id
    ");

    $stmt->execute([
        "id" => $order["id"]
    ]);

    $conn->commit();

    response(200, [
        "success" => true,
        "message" => "Payment confirmed"
    ]);

} catch (Throwable $e) {

    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    error_log("SePay Webhook Error: " . $e->getMessage());

    response(500, ["success" => false]);
}
