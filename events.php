
<?php
require_once "config/database.php";

// Lấy danh sách triển lãm từ database
$sql = "SELECT * FROM events
        WHERE status = 'active'
        ORDER BY start_date ASC";

$stmt = $conn->prepare($sql);
$stmt->execute();

$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Phân loại triển lãm
$categories = [
    "vangogh" => "experience",
    "colors" => "painting",
    "lightspace" => "modern",
    "impressionism" => "painting",
    "dream" => "experience",
    "modern" => "modern"
];

include "includes/header.php";
?>

<main class="events-page">
    <div class="container">

        <h1 class="events-title">Khám phá triển lãm</h1>

        <p>Chọn triển lãm yêu thích và bắt đầu hành trình nghệ thuật của bạn.</p>

        <!-- Tìm kiếm và lọc -->
        <div class="event-search">
            <input
                type="text"
                id="searchInput"
                placeholder="Tìm kiếm triển lãm..."
            >

            <select id="categoryFilter">
                <option value="all">Tất cả danh mục</option>
                <option value="painting">Hội họa</option>
                <option value="experience">Trải nghiệm nghệ thuật</option>
                <option value="modern">Nghệ thuật hiện đại</option>
            </select>
        </div>

        <!-- Danh sách triển lãm -->
        <div class="events-grid" id="eventList">

            <?php foreach ($events as $event): ?>

                <?php
                    $code = $event["event_code"];
                    $category = $categories[$code] ?? "modern";
                ?>

                <div class="event-card"
                     data-category="<?php echo htmlspecialchars($category); ?>">

                    <img
                        src="<?php echo htmlspecialchars($event["image"]); ?>"
                        alt="<?php echo htmlspecialchars($event["name"]); ?>"
                    >

                    <div class="event-card-content">

                        <h3>
                            <?php echo htmlspecialchars($event["name"]); ?>
                        </h3>

                        <p>
                            <?php echo htmlspecialchars($event["description"]); ?>
                        </p>

                        <p class="event-date">
                            <?php
                            echo date("d/m/Y", strtotime($event["start_date"]));
                            ?>
                            –
                            <?php
                            echo date("d/m/Y", strtotime($event["end_date"]));
                            ?>
                        </p>

                        <p class="event-price">
                            Từ
                            <?php
                            echo number_format(
                                $event["standard_price"],
                                0,
                                ',',
                                '.'
                            );
                            ?>đ
                        </p>

                        <a href="event-detail.php?id=<?php echo urlencode($code); ?>">
                            Xem chi tiết →
                        </a>

                    </div>
                </div>

            <?php endforeach; ?>

        </div>

        <p id="noResult" class="no-result" style="display: none;">
            Không tìm thấy triển lãm phù hợp.
        </p>

    </div>
</main>

<script src="assets/js/main.js"></script>

<?php include "includes/footer.php"; ?>
