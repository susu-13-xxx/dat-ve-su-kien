<?php
/**
 * Gui email xac nhan ve sau khi SePay xac nhan thanh toan.
 * Khong de loi email anh huong den trang thai thanh toan.
 */
function sendTicketConfirmation(array $booking): bool
{
    $configFile = __DIR__ . "/../config/email.php";
    $autoloadFile = __DIR__ . "/../vendor/autoload.php";

    if (!file_exists($configFile) || !file_exists($autoloadFile)) {
        error_log("ArtTicket email: Chua co cau hinh email hoac thu vien PHPMailer.");
        return false;
    }

    $emailConfig = require $configFile;

    if (
        empty($emailConfig["username"]) ||
        empty($emailConfig["password"]) ||
        empty($emailConfig["from_email"])
    ) {
        error_log("ArtTicket email: Cau hinh email chua day du.");
        return false;
    }

    try {
        require_once $autoloadFile;

        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $emailConfig["host"] ?? "smtp.gmail.com";
        $mail->SMTPAuth = true;
        $mail->Username = $emailConfig["username"];
        $mail->Password = $emailConfig["password"];
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = (int)($emailConfig["port"] ?? 587);
        $mail->CharSet = "UTF-8";

        $mail->setFrom(
            $emailConfig["from_email"],
            $emailConfig["from_name"] ?? "ArtTicket"
        );
        $mail->addAddress($booking["customer_email"], $booking["customer_name"] ?? "");

        $ticketType = ($booking["ticket_type"] ?? "") === "standard"
            ? "Ve tieu chuan"
            : "Ve dac biet";

        $amount = number_format((int)$booking["total_amount"], 0, ",", ".") . " VND";
        $quantity = (int)$booking["quantity"];
        $eventName = htmlspecialchars($booking["event_name"] ?? "Su kien", ENT_QUOTES, "UTF-8");
        $customerName = htmlspecialchars($booking["customer_name"] ?? "Quy khach", ENT_QUOTES, "UTF-8");
        $bookingCode = htmlspecialchars($booking["booking_code"] ?? "", ENT_QUOTES, "UTF-8");
        $location = htmlspecialchars($booking["location"] ?? "Se cap nhat", ENT_QUOTES, "UTF-8");
        $eventDate = !empty($booking["start_date"])
            ? date("d/m/Y", strtotime($booking["start_date"]))
            : "Se cap nhat";

        $mail->isHTML(true);
        $mail->Subject = "ArtTicket - Xac nhan thanh toan ve";
        $mail->Body = "
            <div style='font-family:Arial,sans-serif;max-width:600px;margin:auto;color:#333;line-height:1.6'>
                <h2 style='color:#18733b'>Thanh toan thanh cong!</h2>
                <p>Xin chao {$customerName},</p>
                <p>ArtTicket da nhan duoc thanh toan va xac nhan don dat ve cua ban.</p>
                <div style='background:#f5f7fa;padding:18px;border-radius:10px'>
                    <p><strong>Su kien:</strong> {$eventName}</p>
                    <p><strong>Ma don hang:</strong> {$bookingCode}</p>
                    <p><strong>Loai ve:</strong> {$ticketType}</p>
                    <p><strong>So luong:</strong> {$quantity}</p>
                    <p><strong>Tong tien:</strong> {$amount}</p>
                    <p><strong>Ngay bat dau:</strong> {$eventDate}</p>
                    <p><strong>Dia diem:</strong> {$location}</p>
                    <p><strong>Trang thai:</strong> Da thanh toan</p>
                </div>
                <p>Vui long luu email nay va ma don hang de tra cuu khi can.</p>
                <p>Cam on ban da lua chon ArtTicket!</p>
            </div>
        ";
        $mail->AltBody =
            "ArtTicket: Thanh toan thanh cong. Ma don: " . ($booking["booking_code"] ?? "") .
            ". Su kien: " . ($booking["event_name"] ?? "") .
            ". Loai ve: " . $ticketType .
            ". So luong: {$quantity}. Tong tien: {$amount}.";

        $mail->send();
        return true;
    } catch (Throwable $e) {
        error_log("ArtTicket email send failed: " . $e->getMessage());
        return false;
    }
}
