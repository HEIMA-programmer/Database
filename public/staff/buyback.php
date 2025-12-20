<?php
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/auth_guard.php';
requireRole(['Staff', 'Manager']);
require_once __DIR__ . '/../../includes/header.php';

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $releaseId = $_POST['release_id'];
    $condition = $_POST['condition'];
    $buyPrice  = $_POST['buy_price']; // 采购成本
    $sellPrice = $_POST['sell_price']; // 预定售价
    $shopId    = $_SESSION['shop_id'];
    $empId     = $_SESSION['user_id'];

    try {
        $pdo->beginTransaction();

        // 1. 创建回购单 (Purchase Order)
        // SourceType = 'Buyback' [cite: 68]
        $poSql = "INSERT INTO PurchaseOrder (CreatedByEmployeeID, OrderDate, Status, SourceType) 
                  VALUES (:emp, NOW(), 'Received', 'Buyback')";
        $stmt = $pdo->prepare($poSql);
        $stmt->execute([':emp' => $empId]);
        $poId = $pdo->lastInsertId();

        // 2. 记录 PO Line
        $lineSql = "INSERT INTO PurchaseOrderLine (PO_ID, ReleaseID, Quantity, UnitCost) 
                    VALUES (:poid, :rid, 1, :cost)";
        $stmt = $pdo->prepare($lineSql);
        $stmt->execute([':poid' => $poId, ':rid' => $releaseId, ':cost' => $buyPrice]);

        // 3. 自动入库 (StockItem)
        // 生成批次号: B + 日期 + POID (例如 B20251220-105) 
        $batchNo = 'B' . date('Ymd') . '-' . $poId;
        
        $stockSql = "INSERT INTO StockItem (ReleaseID, ShopID, SourcePO_ID, BatchNo, ConditionGrade, Status, UnitPrice, AcquiredDate) 
                     VALUES (:rid, :shop, :poid, :batch, :cond, 'Available', :price, NOW())";
        $stmt = $pdo->prepare($stockSql);
        $stmt->execute([
            ':rid' => $releaseId,
            ':shop' => $shopId,
            ':poid' => $poId,
            ':batch' => $batchNo,
            ':cond' => $condition,
            ':price' => $sellPrice
        ]);

        $newStockId = $pdo->lastInsertId();
        $pdo->commit();

        flash("Buyback processed! Item #$newStockId added to inventory (Batch: $batchNo).", 'success');
        
    } catch (Exception $e) {
        $pdo->rollBack();
        flash("Error: " . $e->getMessage(), 'danger');
    }
}

// 获取所有 Release 用于下拉选择
$releases = $pdo->query("SELECT ReleaseID, Title, ArtistName FROM ReleaseAlbum ORDER BY Title")->fetchAll();
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <h2 class="text-warning mb-4"><i class="fa-solid fa-recycle me-2"></i>Used Record Buyback</h2>
        
        <div class="card bg-secondary text-light shadow">
            <div class="card-header bg-dark border-warning">
                Process New Buyback
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Select Release Album</label>
                        <select name="release_id" class="form-select bg-dark text-white border-secondary" required>
                            <option value="">-- Search Database --</option>
                            <?php foreach ($releases as $r): ?>
                                <option value="<?= $r['ReleaseID'] ?>">
                                    <?= h($r['Title']) ?> - <?= h($r['ArtistName']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text text-light-50">Cannot find the album? Contact Admin to add to catalog first.</div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Condition Grade</label>
                            <select name="condition" class="form-select bg-dark text-white border-secondary" required>
                                <option value="Mint">Mint (Perfect)</option>
                                <option value="NM">Near Mint (NM)</option>
                                <option value="VG+">Very Good Plus (VG+)</option>
                                <option value="VG">Very Good (VG)</option>
                            </select>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Buy Price (Cost)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-dark text-secondary border-secondary">¥</span>
                                <input type="number" step="0.01" name="buy_price" class="form-control bg-dark text-white border-secondary" required>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label text-warning">Resell Price</label>
                            <div class="input-group">
                                <span class="input-group-text bg-dark text-warning border-warning">¥</span>
                                <input type="number" step="0.01" name="sell_price" class="form-control bg-dark text-white border-warning" required>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-info small">
                        <i class="fa-solid fa-circle-info me-2"></i>
                        This action will automatically create a Purchase Order and add the item to <strong><?= h($_SESSION['shop_name']) ?></strong>'s active inventory.
                    </div>

                    <button type="submit" class="btn btn-warning w-100 fw-bold py-2">Confirm Buyback & Add to Inventory</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>