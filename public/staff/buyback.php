<?php
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/auth_guard.php';
requireRole('Staff');
require_once __DIR__ . '/../../includes/header.php';

// 处理回购提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        // 1. 插入库存 (StockItem)
        $sql = "INSERT INTO StockItem (ReleaseID, ShopID, BatchNo, AcquiredDate, ConditionGrade, UnitPrice, Status) 
                VALUES (:rid, :shop, :batch, CURDATE(), :cond, :price, 'Available')";
        
        $shopId = $_SESSION['shop_id'] ?? 1; // 默认为当前店员所在店铺
        $stmt = $pdo->prepare($sql);
        
        $stmt->execute([
            ':rid' => $_POST['release_id'],
            ':shop' => $shopId,
            ':batch' => 'BUYBACK-' . date('Ymd'), // 批次号标记为回购
            ':cond' => $_POST['condition'],
            ':price' => $_POST['resale_price'] // 设置预期的转售价
        ]);

        // 2. (可选) 记录支出交易日志 - Assignment 简单版可省略，但建议用 Flash 提示
        $buyPrice = $_POST['buy_price'];
        
        $pdo->commit();
        flash("Item bought back successfully. Paid customer: ¥$buyPrice. Added to inventory.", 'success');
    } catch (Exception $e) {
        $pdo->rollBack();
        flash("Error: " . $e->getMessage(), 'danger');
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
                                <option value="Mint">Mint (Perfect)</option>
                                <option value="NM">Near Mint</option>
                                <option value="VG+">Very Good Plus</option>
                                <option value="VG">Very Good</option>
                                <option value="G">Good</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Offer Price (Cost)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-secondary border-secondary text-light">¥</span>
                                <input type="number" name="buy_price" class="form-control" placeholder="Paid to customer" required>
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label text-info">Target Resale Price</label>
                        <div class="input-group">
                            <span class="input-group-text bg-secondary border-secondary text-light">¥</span>
                            <input type="number" name="resale_price" class="form-control" placeholder="Price on sticker" required>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-warning w-100 fw-bold py-2">
                        <i class="fa-solid fa-check me-2"></i>Complete Buyback & Add to Stock
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>