<?php
session_start();
require_once __DIR__ . "/../config/database.php";
if (!isset($_SESSION["user_id"]) || ($_SESSION["user_role"] ?? "user") !== "admin") {
    http_response_code(403); exit("Ban khong co quyen truy cap.");
}
$error = "";
$success = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";
    $id = (int)($_POST["id"] ?? 0);
    if ($action === "toggle" && $id > 0) {
        $stmt = $conn->prepare("UPDATE events SET status = IF(status = 'active', 'inactive', 'active') WHERE id = :id");
        $stmt->execute(["id" => $id]);
        $success = "Da cap nhat trang thai su kien.";
    } elseif ($action === "save") {
        $eventCode = strtolower(trim($_POST["event_code"] ?? ""));
        $name = trim($_POST["name"] ?? "");
        $description = trim($_POST["description"] ?? "");
        $image = trim($_POST["image"] ?? "");
        $startDate = $_POST["start_date"] ?? "";
        $endDate = $_POST["end_date"] ?? "";
        $location = trim($_POST["location"] ?? "");
        $standardPrice = (int)($_POST["standard_price"] ?? 0);
        $specialPrice = (int)($_POST["special_price"] ?? 0);
        if ($eventCode === "" || $name === "" || $startDate === "" || $endDate === "" || $location === "" || $standardPrice < 0 || $specialPrice < 0) {
            $error = "Vui long nhap day du thong tin hop le.";
        } else {
            try {
                if ($id > 0) {
                    $stmt = $conn->prepare("UPDATE events SET event_code=:code,name=:name,description=:description,image=:image,start_date=:start_date,end_date=:end_date,location=:location,standard_price=:standard_price,special_price=:special_price WHERE id=:id");
                    $stmt->execute(["code"=>$eventCode,"name"=>$name,"description"=>$description,"image"=>$image,"start_date"=>$startDate,"end_date"=>$endDate,"location"=>$location,"standard_price"=>$standardPrice,"special_price"=>$specialPrice,"id"=>$id]);
                } else {
                    $stmt = $conn->prepare("INSERT INTO events (event_code,name,description,image,start_date,end_date,location,standard_price,special_price,status) VALUES (:code,:name,:description,:image,:start_date,:end_date,:location,:standard_price,:special_price,'active')");
                    $stmt->execute(["code"=>$eventCode,"name"=>$name,"description"=>$description,"image"=>$image,"start_date"=>$startDate,"end_date"=>$endDate,"location"=>$location,"standard_price"=>$standardPrice,"special_price"=>$specialPrice]);
                }
                header("Location: events.php?saved=1"); exit;
            } catch (PDOException $e) { $error = "Khong the luu. Ma su kien co the da ton tai."; }
        }
    }
}
$edit = null;
if (isset($_GET["edit"])) {
    $stmt = $conn->prepare("SELECT * FROM events WHERE id = :id");
    $stmt->execute(["id" => (int)$_GET["edit"]]);
    $edit = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}
$events = $conn->query("SELECT * FROM events ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
include __DIR__ . "/../includes/header.php";
?>
<main class="events-page"><div class="container">
<h1 class="events-title">Quản lý sự kiện</h1>
<p style="margin-bottom:20px;"><a href="index.php">← Quay lại tổng quan</a></p>
<?php if ($error): ?><p style="color:#b42318;"><?php echo htmlspecialchars($error); ?></p><?php endif; ?>
<?php if (isset($_GET["saved"])): ?><p style="color:green;">Đã lưu sự kiện thành công.</p><?php endif; ?>
<div style="background:white;padding:24px;border-radius:12px;margin-bottom:25px;">
<h2><?php echo $edit ? "Sửa sự kiện" : "Thêm sự kiện mới"; ?></h2>
<form method="POST">
<input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?php echo (int)($edit["id"] ?? 0); ?>">
<label>Mã sự kiện (không dấu, viết liền)</label><input name="event_code" required value="<?php echo htmlspecialchars($edit["event_code"] ?? ""); ?>">
<label>Tên sự kiện</label><input name="name" required value="<?php echo htmlspecialchars($edit["name"] ?? ""); ?>">
<label>Mô tả</label><textarea name="description" rows="3" style="width:100%;padding:12px;"><?php echo htmlspecialchars($edit["description"] ?? ""); ?></textarea>
<label>Link ảnh</label><input name="image" value="<?php echo htmlspecialchars($edit["image"] ?? ""); ?>">
<label>Ngày bắt đầu</label><input type="date" name="start_date" required value="<?php echo htmlspecialchars(isset($edit["start_date"]) ? date("Y-m-d",strtotime($edit["start_date"])) : ""); ?>">
<label>Ngày kết thúc</label><input type="date" name="end_date" required value="<?php echo htmlspecialchars(isset($edit["end_date"]) ? date("Y-m-d",strtotime($edit["end_date"])) : ""); ?>">
<label>Địa điểm</label><input name="location" required value="<?php echo htmlspecialchars($edit["location"] ?? ""); ?>">
<label>Giá vé tiêu chuẩn (VNĐ)</label><input type="number" min="0" name="standard_price" required value="<?php echo htmlspecialchars((string)($edit["standard_price"] ?? "0")); ?>">
<label>Giá vé đặc biệt (VNĐ)</label><input type="number" min="0" name="special_price" required value="<?php echo htmlspecialchars((string)($edit["special_price"] ?? "0")); ?>">
<button class="auth-btn" type="submit">Lưu sự kiện</button>
<?php if ($edit): ?><a style="display:block;text-align:center;margin-top:15px;" href="events.php">Hủy sửa</a><?php endif; ?>
</form></div>
<div style="background:white;padding:24px;border-radius:12px;overflow-x:auto;"><h2 style="margin-bottom:16px;">Danh sách sự kiện</h2>
<table style="width:100%;border-collapse:collapse;min-width:750px;"><thead><tr style="text-align:left;background:#f8f7f2;"><th style="padding:12px;">Tên sự kiện</th><th>Mã</th><th>Giá tiêu chuẩn</th><th>Trạng thái</th><th>Thao tác</th></tr></thead><tbody>
<?php foreach ($events as $event): ?><tr style="border-top:1px solid #eee;"><td style="padding:12px;"><?php echo htmlspecialchars($event["name"]); ?></td><td><?php echo htmlspecialchars($event["event_code"]); ?></td><td><?php echo number_format((float)$event["standard_price"],0,",","."); ?>đ</td><td><?php echo $event["status"] === "active" ? "Đang hiển thị" : "Đã ẩn"; ?></td><td><a href="events.php?edit=<?php echo (int)$event["id"]; ?>">Sửa</a><form method="POST" style="display:inline;margin-left:10px;"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?php echo (int)$event["id"]; ?>"><button type="submit" onclick="return confirm('Đổi trạng thái sự kiện này?')" style="border:0;background:none;color:#a47c30;cursor:pointer;">Ẩn/Hiện</button></form></td></tr><?php endforeach; ?>
</tbody></table></div></div></main>
<?php include __DIR__ . "/../includes/footer.php"; ?>
