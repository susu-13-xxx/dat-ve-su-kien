<?php
session_start();
require_once __DIR__ . "/config/database.php";

$configFile = __DIR__ . "/config/ticket-scanner.php";
if (!file_exists($configFile)) {
    http_response_code(503);
    exit("Chua cau hinh cong cu quet ve. Hay tao config/ticket-scanner.php tu file config/ticket-scanner.php.example va doi mat khau.");
}
$scannerConfig = require $configFile;
$scannerPassword = (string)($scannerConfig["password"] ?? "");

if ($scannerPassword === "" || $scannerPassword === "CHANGE_THIS_TO_A_LONG_RANDOM_PASSWORD") {
    http_response_code(503);
    exit("Hay dat mat khau rieng trong config/ticket-scanner.php truoc khi su dung.");
}

if (empty($_SESSION["ticket_scanner_csrf"])) {
    $_SESSION["ticket_scanner_csrf"] = bin2hex(random_bytes(32));
}
$message = "";
$messageType = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["scanner_login"])) {
    if (hash_equals($scannerPassword, (string)($_POST["scanner_password"] ?? ""))) {
        session_regenerate_id(true);
        $_SESSION["ticket_scanner_authenticated"] = true;
        $_SESSION["ticket_scanner_csrf"] = bin2hex(random_bytes(32));
        header("Location: scan-ticket.php");
        exit;
    }
    $message = "Mat khau khong dung.";
    $messageType = "error";
}

if (isset($_GET["logout"])) {
    unset($_SESSION["ticket_scanner_authenticated"]);
    header("Location: scan-ticket.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["scan_ticket"])) {
    header("Content-Type: application/json; charset=utf-8");
    if (empty($_SESSION["ticket_scanner_authenticated"])) {
        http_response_code(401);
        echo json_encode(["success" => false, "message" => "Vui long dang nhap lai."]);
        exit;
    }
    if (!hash_equals($_SESSION["ticket_scanner_csrf"], (string)($_POST["csrf"] ?? ""))) {
        http_response_code(403);
        echo json_encode(["success" => false, "message" => "Phien lam viec khong hop le."]);
        exit;
    }

    $ticketCode = strtoupper(trim((string)($_POST["ticket_code"] ?? "")));
    $ticketCode = preg_replace('/^ARTTICKET:/i', '', $ticketCode);
    if (!preg_match('/^TKT-[A-F0-9]{16}$/', $ticketCode)) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Ma QR khong dung dinh dang ve ArtTicket."]);
        exit;
    }

    try {
        $conn->beginTransaction();
        $stmt = $conn->prepare("
            SELECT t.id, t.ticket_code, t.status, t.used_at,
                   b.booking_code, b.status AS booking_status,
                   e.name AS event_name, e.start_date, e.location
            FROM tickets t
            JOIN bookings b ON b.id = t.booking_id
            JOIN events e ON e.id = b.event_id
            WHERE t.ticket_code = :code
            FOR UPDATE
        ");
        $stmt->execute(["code" => $ticketCode]);
        $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$ticket) {
            $conn->rollBack();
            echo json_encode(["success" => false, "state" => "invalid", "message" => "Khong tim thay ve nay."]);
            exit;
        }
        if ($ticket["booking_status"] !== "paid") {
            $conn->rollBack();
            echo json_encode(["success" => false, "state" => "unpaid", "message" => "Don hang chua thanh toan."]);
            exit;
        }
        if ($ticket["status"] !== "valid") {
            $conn->rollBack();
            echo json_encode(["success" => false, "state" => "used", "message" => "Ve da duoc su dung truoc do."]);
            exit;
        }

        $update = $conn->prepare("UPDATE tickets SET status = 'used', used_at = NOW() WHERE id = :id AND status = 'valid'");
        $update->execute(["id" => $ticket["id"]]);
        if ($update->rowCount() !== 1) {
            $conn->rollBack();
            echo json_encode(["success" => false, "state" => "used", "message" => "Ve vua duoc su dung o mot cong khac."]);
            exit;
        }
        $conn->commit();
        echo json_encode([
            "success" => true,
            "state" => "valid",
            "message" => "Ve hop le! Da xac nhan vao cong.",
            "event" => $ticket["event_name"],
            "ticket_code" => $ticket["ticket_code"],
            "booking_code" => $ticket["booking_code"],
            "date" => $ticket["start_date"],
            "location" => $ticket["location"]
        ]);
    } catch (Throwable $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        error_log("ArtTicket scanner error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Loi he thong khi kiem tra ve."]);
    }
    exit;
}

include __DIR__ . "/includes/header.php";
?>
<main class="auth-page">
    <div class="auth-box" style="max-width:620px;">
        <h1 class="auth-heading">Cong kiem soat ve</h1>
        <?php if (empty($_SESSION["ticket_scanner_authenticated"])): ?>
            <p>Nhap mat khau nhan vien de mo cong quet ve.</p>
            <?php if ($message !== ""): ?><p style="color:#b42318;"><?php echo htmlspecialchars($message); ?></p><?php endif; ?>
            <form method="POST">
                <label>Mat khau nhan vien</label>
                <input type="password" name="scanner_password" required autocomplete="current-password">
                <button class="auth-btn" type="submit" name="scanner_login" value="1">Dang nhap cong quet ve</button>
            </form>
        <?php else: ?>
            <p>Quet QR tren ve dien tu hoac nhap ma ve thu cong.</p>
            <div id="reader" style="width:100%; margin:16px auto;"></div>
            <form id="manualForm">
                <label>Ma ve</label>
                <input id="ticketCode" type="text" placeholder="TKT-..." autocomplete="off" required>
                <button class="auth-btn" type="submit">Kiem tra va xac nhan ve</button>
            </form>
            <div id="result" role="status" style="margin-top:16px;padding:14px;border-radius:8px;display:none;"></div>
            <p style="margin-top:16px;"><a href="scan-ticket.php?logout=1">Dang xuat cong quet ve</a></p>
        <?php endif; ?>
    </div>
</main>
<?php if (!empty($_SESSION["ticket_scanner_authenticated"])): ?>
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
const csrfToken = <?php echo json_encode($_SESSION["ticket_scanner_csrf"]); ?>;
const resultBox = document.getElementById("result");
let busy = false;

async function checkTicket(rawValue) {
    if (busy) return;
    busy = true;
    const code = String(rawValue || "").trim().replace(/^ARTTICKET:/i, "");
    const form = new URLSearchParams({scan_ticket: "1", ticket_code: code, csrf: csrfToken});
    try {
        const response = await fetch("scan-ticket.php", {
            method: "POST",
            headers: {"Content-Type": "application/x-www-form-urlencoded"},
            body: form
        });
        const data = await response.json();
        resultBox.style.display = "block";
        resultBox.style.background = data.success ? "#e8f7ee" : "#fff1f0";
        resultBox.style.color = data.success ? "#18733b" : "#b42318";
        resultBox.innerHTML = "";
        const title = document.createElement("strong");
        title.textContent = data.message || "Khong co phan hoi.";
        resultBox.appendChild(title);
        if (data.event) {
            const detail = document.createElement("p");
            detail.textContent = data.event + " | Ma ve: " + data.ticket_code + " | Ngay: " + (data.date || "Chua cap nhat") + " | Dia diem: " + (data.location || "Chua cap nhat");
            resultBox.appendChild(detail);
        }
        document.getElementById("ticketCode").value = "";
    } catch (error) {
        resultBox.style.display = "block";
        resultBox.textContent = "Khong ket noi duoc may chu. Hay thu lai.";
    } finally {
        busy = false;
    }
}

document.getElementById("manualForm").addEventListener("submit", function(event) {
    event.preventDefault();
    checkTicket(document.getElementById("ticketCode").value);
});

if (window.Html5Qrcode) {
    const scanner = new Html5Qrcode("reader");
    scanner.start(
        {facingMode: "environment"},
        {fps: 10, qrbox: {width: 220, height: 220}},
        decodedText => {
            scanner.pause(true);
            checkTicket(decodedText).finally(() => setTimeout(() => scanner.resume(), 1800));
        },
        () => {}
    ).catch(() => {
        const note = document.createElement("p");
        note.textContent = "Khong mo duoc camera. Hay cho phep trinh duyet truy cap camera hoac nhap ma ve thu cong.";
        document.getElementById("reader").appendChild(note);
    });
} else {
    document.getElementById("reader").textContent = "Khong tai duoc thu vien quet QR. Ban van co the nhap ma ve thu cong.";
}
</script>
<?php endif; ?>
<?php include __DIR__ . "/includes/footer.php"; ?>
