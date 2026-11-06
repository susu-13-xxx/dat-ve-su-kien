
<?php
require_once "config/database.php";

// Nhận mã triển lãm từ URL
$id = $_GET["id"] ?? "";

// Tìm triển lãm trong database
$sql = "SELECT * FROM events WHERE event_code = :id AND status = 'active'";
$stmt = $conn->prepare($sql);
$stmt->execute(["id" => $id]);

$event = $stmt->fetch(PDO::FETCH_ASSOC);

// Nếu không tìm thấy triển lãm
if (!$event) {
    die("Không tìm thấy triển lãm!");
}

include "includes/header.php";
?>

<main class="detail-page">

    <section class="detail-banner">
        <img
            src="<?php echo htmlspecialchars($event["image"]); ?>"
            alt="<?php echo htmlspecialchars($event["name"]); ?>"
        >

        <div class="detail-banner-content">
            <span>TRIỂN LÃM NGHỆ THUẬT</span>

            <h1><?php echo htmlspecialchars($event["name"]); ?></h1>

            <p>
                <?php echo date("d/m/Y", strtotime($event["start_date"])); ?>
                –
                <?php echo date("d/m/Y", strtotime($event["end_date"])); ?>
            </p>
        </div>
    </section>

    <div class="container detail-layout">

        <section class="detail-content">

            <h2>Giới thiệu triển lãm</h2>

            <p>
                <?php echo htmlspecialchars($event["description"]); ?>
            </p>

            <h2>Thông tin triển lãm</h2>

            <p>
                <strong>Thời gian:</strong>
                <?php echo date("d/m/Y", strtotime($event["start_date"])); ?>
                –
                <?php echo date("d/m/Y", strtotime($event["end_date"])); ?>
            </p>

            <p>
                <strong>Địa điểm:</strong>
                <?php echo htmlspecialchars($event["location"]); ?>
            </p>

            <p>
                <strong>Giờ mở cửa:</strong> 08:00 – 21:00
            </p>

            <h2>Điểm nổi bật</h2>

            <ul>
                <li>Khám phá những tác phẩm nghệ thuật đặc sắc.</li>
                <li>Trải nghiệm không gian triển lãm độc đáo.</li>
                <li>Lưu giữ những khoảnh khắc đáng nhớ.</li>
            </ul>

        </section>

        <aside class="ticket-box">

            <h2>Đặt vé tham quan</h2>

            <form action="checkout.php" method="GET">

                <input
                    type="hidden"
                    name="event_id"
                    value="<?php echo htmlspecialchars($event["event_code"]); ?>"
                >

                <label for="ticketType">Loại vé</label>

                <select id="ticketType" name="ticket_type">
                    <option value="standard"
                        data-price="<?php echo $event["standard_price"]; ?>">
                        Vé tiêu chuẩn -
                        <?php echo number_format($event["standard_price"], 0, ',', '.'); ?>đ
                    </option>

                    <option value="special"
                        data-price="<?php echo $event["special_price"]; ?>">
                        Vé đặc biệt -
                        <?php echo number_format($event["special_price"], 0, ',', '.'); ?>đ
                    </option>
                </select>

                <label for="ticketQuantity">Số lượng vé</label>

                <input
                    type="number"
                    id="ticketQuantity"
                    name="quantity"
                    value="1"
                    min="1"
                    max="10"
                >

                <div class="total-price">
                    <span>Tổng tiền:</span>

                    <strong id="totalPrice">
                        <?php echo number_format($event["standard_price"], 0, ',', '.'); ?>đ
                    </strong>
                </div>

                <button type="submit" class="auth-btn">
                    Tiếp tục thanh toán
                </button>

            </form>

        </aside>

    </div>
</main>

<script>
const ticketType = document.getElementById("ticketType");
const ticketQuantity = document.getElementById("ticketQuantity");
const totalPrice = document.getElementById("totalPrice");

function updateTotal() {
    const selectedOption = ticketType.options[ticketType.selectedIndex];
    const price = Number(selectedOption.dataset.price);

    let quantity = Number(ticketQuantity.value) || 1;
    quantity = Math.max(1, Math.min(10, quantity));

    ticketQuantity.value = quantity;

    totalPrice.textContent =
        (price * quantity).toLocaleString("vi-VN") + "đ";
}

ticketType.addEventListener("change", updateTotal);
ticketQuantity.addEventListener("input", updateTotal);
</script>

<?php include "includes/footer.php"; ?>
