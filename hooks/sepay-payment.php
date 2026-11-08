<?php
header("Content-Type: application/json; charset=utf-8");

require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../config/sepay.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method not allowed"]);
    exit;
}

$body = file_get_contents("php://input");
$signature = $_SERVER["HTTP_X_SEPAY_SIGNATURE"] ?? "";
$timestamp = (int)($_SERVER["HTTP_X_SEPAY_TIMESTAMP"] ?? 0);

if (!$SEPAY_WEBHOOK_SECRET || abs(time() - $timestamp) > 300) {
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

$data = json_decode($body, true);

if (!is_array($data) || ($data["transferType"] ?? "") !== "in") {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Invalid payment data"]);
    exit;
}

// Bo qua giao dich thu webhook cua SePay.
if (($data["id"] ?? null) == 0 && ($data["code"] ?? "") === "SEPAYTEST") {
    echo json_encode(["success" => true, "message" => "Test webhook received"]);
    exit;
}

$content = $data["content"] ?? "";

if (!preg_match('/ART\d{14}[A-Z0-9]{4}/i', $content, $match)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Booking code not found"]);
    exit;
}

$bookingCode = strtoupper($match[0]);
$amount = (int)($data["transferAmount"] ?? 0);

try {
    $conn->beginTransaction();

    $stmt = $conn->prepare("
        SELECT
            b.id,
            b.booking_code,
            b.ticket_type,
            b.quantity,
            b.total_amount,
            b.status AS booking_status,
            p.status AS payment_status,
            u.name AS customer_name,
            u.email AS customer_email,
            e.name AS event_name,
            e.start_date,
            e.location
        FROM bookings b
        JOIN payments p ON p.booking_id = b.id
        JOIN users u ON u.id = b.user_id
        JOIN events e ON e.id = b.event_id
        WHERE b.booking_code = :code
        FOR UPDATE
    ");
    $stmt->execute(["code" => $bookingCode]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking || $amount !== (int)$booking["total_amount"]) {
        $conn->rollBack();
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Booking or amount mismatch"]);
        exit;
    }

    // SePay co the gui lai webhook. Don da thanh toan dung so tien
    // thi tra thanh cong, khong cap nhat va khong gui email lan nua.
    // Tao du mot ve rieng cho moi ve trong don, ke ca don da paid tu truoc.
    // Ma ve duoc sinh ngau nhien va khong trung lap.
    $issueMissingTickets = function () use ($conn, $booking): void {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM tickets WHERE booking_id = :id");
        $stmt->execute(["id" => $booking["id"]]);
        $existingCount = (int)$stmt->fetchColumn();
        $missingCount = max(0, (int)$booking["quantity"] - $existingCount);

        $insert = $conn->prepare("
            INSERT INTO tickets (booking_id, ticket_code, status)
            VALUES (:booking_id, :ticket_code, 'valid')
        ");

        for ($i = 0; $i < $missingCount; $i++) {
            $ticketCode = "TKT-" . strtoupper(bin2hex(random_bytes(8)));
            $insert->execute([
                "booking_id" => $booking["id"],
                "ticket_code" => $ticketCode
            ]);
        }
    };

    if (
        $booking["booking_status"] === "paid" &&
        $booking["payment_status"] === "paid"
    ) {
        $issueMissingTickets();
        $conn->commit();
        echo json_encode(["success" => true, "message" => "Payment already confirmed"]);
        exit;
    }

    if (
        $booking["booking_status"] !== "pending" ||
        $booking["payment_status"] !== "pending"
    ) {
        $conn->rollBack();
        http_response_code(409);
        echo json_encode(["success" => false, "message" => "Booking is not payable"]);
        exit;
    }

    $stmt = $conn->prepare("
        UPDATE payments
        SET status = 'paid'
        WHERE booking_id = :id AND status = 'pending'
    ");
    $stmt->execute(["id" => $booking["id"]]);

    $stmt = $conn->prepare("
        UPDATE bookings
        SET status = 'paid'
        WHERE id = :id AND status = 'pending'
    ");
    $stmt->execute(["id" => $booking["id"]]);

    // Moi booking tao dung so ve tuong ung voi so luong da mua.
    $issueMissingTickets();

    $conn->commit();

    // Gui email sau khi da commit. Loi email khong lam huy thanh toan.
    require_once __DIR__ . "/../includes/send-ticket-email.php";
    sendTicketConfirmation($booking);

    echo json_encode(["success" => true, "message" => "Payment confirmed"]);
} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    error_log("SePay webhook error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["success" => false]);
}
