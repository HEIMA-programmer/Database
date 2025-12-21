<?php
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/auth_guard.php';
requireRole('Staff');
require_once __DIR__ . '/../../includes/header.php';

// 处理回购提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        $releaseId = $_POST['release_id'];
        $condition = $_POST['condition'];
        $buyPrice = $_POST['buy_price'];
        $resalePrice = $_POST['resale_price'];
        $shopId = $_SESSION['shop_id'] ?? 1;
        $empId = $_SESSION['user_id'];

        // 1. 创建回购单 (Purchase Order - Type: Buyback)
        // 这确保了财务上的支出有据可查
        $poSql = "INSERT INTO PurchaseOrder (SupplierID, CreatedByEmployeeID, OrderDate, Status, SourceType) 
                  VALUES (NULL, ?, NOW(), 'Received', 'Buyback')";
        $stmtPo = $pdo->prepare($poSql);
        $stmtPo->execute([$empId]);
        $poId = $pdo->lastInsertId();

        // 2. 记录 PO Line (作为单据明细)
        $lineSql = "INSERT INTO PurchaseOrderLine (PO_ID, ReleaseID, Quantity, UnitCost) VALUES (?, ?, 1, ?)";
        $stmtLine = $pdo->prepare($lineSql);
        $stmtLine->execute([$poId, $releaseId, $buyPrice]);

        // 3. 插入物理库存 (StockItem) 并关联 PO
        $stockSql = "INSERT INTO StockItem (ReleaseID, ShopID, SourcePO_ID, BatchNo, ConditionGrade, Status, UnitPrice, AcquiredDate) 
                     VALUES (?, ?, ?, ?, ?, 'Available', ?, NOW())";
        
        $batchNo = 'BUYBACK-' . date('Ymd') . '-' . $poId;
        
        $stmtStock = $pdo->prepare($stockSql);
        $stmtStock->execute([
            $releaseId,
            $shopId,
            $poId, // Traceability: 关联到回购单
            $batchNo,
            $condition,
            $resalePrice
        ]);

        $pdo->commit();
        flash("Buyback processed successfully. PO #$poId created. Item added to inventory.", 'success');
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log($e->getMessage());
        flash("Error processing buyback: " . $e->getMessage(), 'danger');
    }
    header("Location: buyback.php");
    exit();
}

$releases = $pdo->query("SELECT ReleaseID, Title, ArtistName FROM ReleaseAlbum ORDER BY Title")->fetchAll();
?>

<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="text-center mb-4">
            <h2 class="text-warning"><i class="fa-solid fa-recycle me-2"></i>Used Record Buyback</h2>
            <p class="text-muted">Process customer trade-ins and add to local inventory.</p>
        </div>

        <div class="card bg-dark border-secondary shadow-lg">
            <div class="card-body p-4">
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label text-warning">Select Release Album</label>
                        <select name="release_id" class="form-select" required>
                            <option value="">-- Choose Album --</option>
                            <?php foreach($releases as $r): ?>
                                <option value="<?= $r['ReleaseID'] ?>"><?= h($r['Title']) ?> (<?= h($r['ArtistName']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text text-muted">If album not in DB, create it in Admin > Products first.</div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Condition</label>
                            <select name="condition" class="form-select" required>
                                <option value="VG+">Very Good Plus (Standard)</option>
                                <option value="Mint">Mint (Perfect)</option>
                                <option value="NM">Near Mint</option>
                                <option value="VG">Very Good</option>
                                <option value="G">Good</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Payout Amount (Cost)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-secondary border-secondary text-light">¥</span>
                                <input type="number" name="buy_price" class="form-control" placeholder="Paid to customer" step="1" required>
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label text-info">Target Resale Price</label>
                        <div class="input-group">
                            <span class="input-group-text bg-secondary border-secondary text-light">¥</span>
                            <input type="number" name="resale_price" class="form-control" placeholder="Price on sticker" step="1" required>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-warning w-100 fw-bold py-2">
                        <i class="fa-solid fa-check me-2"></i>Authorize Payout & Print Label
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>