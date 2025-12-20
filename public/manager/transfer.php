<?php
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/auth_guard.php';
requireRole('Manager');
require_once __DIR__ . '/../../includes/header.php';

// 获取所有店铺
$shops = $pdo->query("SELECT * FROM Shop")->fetchAll();

// 处理调拨提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stockId = $_POST['stock_id'];
    $toShopId = $_POST['to_shop_id'];
    $empId = $_SESSION['user_id'];

    // 获取当前库存信息，验证所有权
    $stmt = $pdo->prepare("SELECT ShopID FROM StockItem WHERE StockItemID = ? AND Status = 'Available'");
    $stmt->execute([$stockId]);
    $currentItem = $stmt->fetch();

    if ($currentItem && $currentItem['ShopID'] != $toShopId) {
        try {
            $pdo->beginTransaction();

            // 1. 记录调拨日志
            $logSql = "INSERT INTO InventoryTransfer (StockItemID, FromShopID, ToShopID, AuthorizedByEmployeeID) 
                       VALUES (:sid, :from, :to, :emp)";
            $stmt = $pdo->prepare($logSql);
            $stmt->execute([
                ':sid' => $stockId,
                ':from' => $currentItem['ShopID'],
                ':to' => $toShopId,
                ':emp' => $empId
            ]);

            // 2. 更新物品物理位置
            $updSql = "UPDATE StockItem SET ShopID = :to WHERE StockItemID = :sid";
            $stmt = $pdo->prepare($updSql);
            $stmt->execute([':to' => $toShopId, ':sid' => $stockId]);

            $pdo->commit();
            flash("Stock Item #$stockId transferred successfully.", 'success');

        } catch (Exception $e) {
            $pdo->rollBack();
            flash("Transfer failed: " . $e->getMessage(), 'danger');
        }
    } else {
        flash("Invalid Item ID or Item already at destination.", 'danger');
    }
}
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <h2 class="text-warning mb-4"><i class="fa-solid fa-truck-ramp-box me-2"></i>Stock Transfer</h2>
        
        <div class="card bg-secondary text-light">
            <div class="card-header bg-dark border-info">New Transfer Request</div>
            <div class="card-body">
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Stock Item ID (Available Only)</label>
                        <input type="number" name="stock_id" class="form-control bg-dark text-white border-secondary" placeholder="Scan or Enter ID" required>
                        <div class="form-text text-light-50">Enter the unique Stock ID found on the physical item tag.</div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Destination Location</label>
                        <select name="to_shop_id" class="form-select bg-dark text-white border-secondary" required>
                            <?php foreach ($shops as $s): ?>
                                <option value="<?= $s['ShopID'] ?>"><?= h($s['Name']) ?> (<?= $s['Type'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-info w-100 fw-bold text-dark">Authorize Transfer</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>