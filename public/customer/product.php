<?php
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/auth_guard.php';
requireRole('Customer');
require_once __DIR__ . '/../../includes/header.php';

$stockId = $_GET['id'] ?? 0;

// 1. 获取商品详情 (联表查询 Release 信息)
$sql = "SELECT s.*, r.Title, r.ArtistName, r.LabelName, r.ReleaseYear, r.Genre, r.Description, sh.Name as ShopName
        FROM StockItem s
        JOIN ReleaseAlbum r ON s.ReleaseID = r.ReleaseID
        JOIN Shop sh ON s.ShopID = sh.ShopID
        WHERE s.StockItemID = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$stockId]);
$item = $stmt->fetch();

if (!$item) {
    flash("Item not found.", 'danger');
    header("Location: catalog.php");
    exit();
}

// 2. 检查是否有同款专辑在其他店铺 (展示库存分布 - 这是一个很棒的功能点)
$otherStockSql = "SELECT s.StockItemID, s.ConditionGrade, s.UnitPrice, sh.Name as ShopName
                  FROM StockItem s
                  JOIN Shop sh ON s.ShopID = sh.ShopID
                  WHERE s.ReleaseID = ? AND s.Status = 'Available' AND s.StockItemID != ?
                  LIMIT 5";
$otherStmt = $pdo->prepare($otherStockSql);
$otherStmt->execute([$item['ReleaseID'], $stockId]);
$otherItems = $otherStmt->fetchAll();
?>

<div class="row g-5">
    <div class="col-md-5">
        <div class="card bg-dark border-secondary p-4">
            <div class="vinyl-placeholder w-100" style="aspect-ratio: 1/1;">
                <div class="vinyl-center" style="width: 35%; height: 35%;">
                    <div><?= h($item['LabelName']) ?><br><small><?= $item['ReleaseYear'] ?></small></div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-7">
        <div class="mb-2">
            <span class="badge bg-warning text-dark me-2"><?= h($item['Genre']) ?></span>
            <span class="text-secondary"><?= h($item['LabelName']) ?>, <?= $item['ReleaseYear'] ?></span>
        </div>
        
        <h1 class="display-4 text-white fw-bold mb-2"><?= h($item['Title']) ?></h1>
        <h3 class="text-warning mb-4"><?= h($item['ArtistName']) ?></h3>

        <div class="card bg-secondary border-0 mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <div class="small text-muted text-uppercase">Current Item Price</div>
                        <div class="display-6 fw-bold text-white"><?= formatPrice($item['UnitPrice']) ?></div>
                    </div>
                    <div class="text-end">
                        <div class="badge bg-info text-dark mb-1"><?= h($item['ConditionGrade']) ?> Condition</div>
                        <div class="small text-light"><i class="fa-solid fa-location-dot me-1"></i><?= h($item['ShopName']) ?></div>
                    </div>
                </div>

                <?php if ($item['Status'] == 'Available'): ?>
                    <form action="cart_action.php" method="POST">
                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="stock_id" value="<?= $item['StockItemID'] ?>">
                        <button type="submit" class="btn btn-warning w-100 btn-lg fw-bold">
                            <i class="fa-solid fa-cart-plus me-2"></i>Add to Cart
                        </button>
                    </form>
                <?php else: ?>
                    <button class="btn btn-secondary w-100 btn-lg" disabled>Sold Out</button>
                <?php endif; ?>
            </div>
        </div>

        <div class="mb-4">
            <h5 class="text-white border-bottom border-secondary pb-2">Description</h5>
            <p class="text-muted"><?= nl2br(h($item['Description'] ?: 'No description available for this classic record.')) ?></p>
        </div>

        <?php if (!empty($otherItems)): ?>
            <h5 class="text-white border-bottom border-secondary pb-2 mt-5">Also Available At</h5>
            <div class="list-group list-group-flush bg-transparent">
                <?php foreach ($otherItems as $o): ?>
                    <a href="product.php?id=<?= $o['StockItemID'] ?>" class="list-group-item list-group-item-action bg-dark text-light border-secondary d-flex justify-content-between align-items-center">
                        <div>
                            <i class="fa-solid fa-record-vinyl text-secondary me-2"></i>
                            <?= h($o['ShopName']) ?>
                            <span class="badge bg-secondary ms-2"><?= h($o['ConditionGrade']) ?></span>
                        </div>
                        <span class="text-warning fw-bold"><?= formatPrice($o['UnitPrice']) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>