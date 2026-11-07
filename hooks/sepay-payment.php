
<?php
// Nhận thông báo thanh toán từ SePay

header("Content-Type: application/json; charset=utf-8");

require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../config/sepay.php";

// Chỉ nhận POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode([
        "success" => false,
        "message" => "Webhook chi nhan POST"
    ]);
    exit;
}

// Đọc dữ liệu gốc
$body = file_get_contents("php://input");
$signature = $_SERVER["HTTP_X_SEPAY_SIGNATURE"] ?? "";
$timestamp = (int)($_SERVER["HTTP_X_SEPAY_TIMESTAMP"] ?? 0);

// Kiểm tra chữ ký HMAC
if (
    !$SEPAY_WEBHOOK_SECRET ||
    abs(time() - $timestamp) > 300
) {
    http_response_code(401);
    exit;
}

$expected = "sha256=" . hash_hmac(
    "sha256",
    $timestamp . "." . $body,
    $SEPAY_WEBHOOK_SECRET
);


if (!hash_equals($expected, $signature)) {
    
http_response_code(401);
    exit;
}

// Đọc JSON
$data = json_decode($body, true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode([
        "error" => "JSON không hợp lệ",
        "body" => $body
    ]);
    exit;
}

if (($data["transferType"] ?? "") !== "in") {
    http_response_code(400);
    echo json_encode([
        "error" => "Sai transferType",
        "received" => $data["transferType"] ?? null
    ]);
    exit;
}

// Lấy mã đơn hàng từ nội dung chuyển khoản
$content = $data["content"] ?? "";
$code = $data["code"] ?? "";

// Nhận diện webhook thử của SePay
if (
    ($data["id"] ?? null) == 0 &&
    $code === "SEPAYTEST"
) {
    echo json_encode([
        "success" => true,
        "message" => "SePay test webhook received"
    ]);
    exit;
}

if (!preg_match('/ART\d{14}[A-Z0-9]{4}/i', $content, $match)) {
   http_response_code(400);
echo json_encode([
    "error" => "LOI_3_BOOKING_CODE",
    "content" => $content
]);
exit;
}

$bookingCode = strtoupper($match[0]);
$amount = (int)($data["transferAmount"] ?? 0);

try {
    $conn->beginTransaction();

    // Kiểm tra đơn hàng và số tiền chính xác
    $stmt = $conn->prepare("
        SELECT b.id, b.total_amount
        FROM bookings b
        JOIN payments p ON p.booking_id = b.id
        WHERE b.booking_code = :code
        AND b.status = 'pending'
        AND p.status = 'pending'
        FOR UPDATE
    ");

    $stmt->execute(["code" => $bookingCode]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking || $amount !== (int)$booking["total_amount"]) {
        $conn->rollBack();
http_response_code(400);
echo json_encode([
    "error" => "LOI_4_BOOKING_OR_AMOUNT",
    "booking_found" => (bool)$booking,
    "received_amount" => $amount
]);
exit;
    }

    // Xác nhận thanh toán
    $stmt = $conn->prepare("
        UPDATE payments
        SET status = 'paid'
        WHERE booking_id = :id
        AND status = 'pending'
    ");
    $stmt->execute(["id" => $booking["id"]]);

    $stmt = $conn->prepare("
        UPDATE bookings
        SET status = 'paid'
        WHERE id = :id
        AND status = 'pending'
    ");
    $stmt->execute(["id" => $booking["id"]]);

    $conn->commit();

    echo json_encode(["success" => true]);

} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    error_log("SePay webhook: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["success" => false]);
}
