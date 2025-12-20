<?php
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/auth_guard.php';
requireRole('Staff');
require_once __DIR__ . '/../../includes/header.php';

// 当前店员所在的店铺
$shopId = $_SESSION['shop_id'] ?? 1;
$shopName = $_SESSION['shop_name'] ?? 'Unknown Shop';

// 搜索逻辑
$search = $_GET['q'] ?? '';
$sql = "SELECT * FROM vw_staff_pos_lookup WHERE ShopID = :shop AND Status = 'Available'";
$params = [':shop' => $shopId];

if ($search) {
    $sql .= " AND (Title LIKE :q OR StockItemID LIKE :q)";
    $params[':q'] = "%$search%";
}
$sql .= " LIMIT 20";

$items = $pdo->prepare($sql);
$items->execute($params);
$items = $items->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="text-warning mb-0">Point of Sale</h2>
        <span class="badge bg-secondary text-info border border-info"><?= h($shopName) ?> Terminal</span>
    </div>
    <div>
        <a href="/customer/cart.php" class="btn btn-outline-warning">
            <i class="fa-solid fa-cart-shopping me-2"></i>Go to Checkout
        </a>
    </div>
</div>

<div class="card bg-dark border-secondary mb-4">
    <div class="card-body p-3">
        <form method="GET" class="d-flex gap-2">
            <input type="text" name="q" class="form-control form-control-lg bg-secondary text-white border-0" 
                   placeholder="Scan Barcode (ID) or Search Title..." value="<?= h($search) ?>" autofocus>
            <button type="submit" class="btn btn-warning px-4">Search</button>
        </form>
    </div>
</div>

<div class="row row-cols-1 row-cols-md-4 g-3">
    <?php if(empty($items)): ?>
        <div class="col-12 text-center text-muted py-5">
            <i class="fa-solid fa-barcode fa-3x mb-3"></i>
            <p>No items found in local inventory.</p>
        </div>
    <?php else: ?>
        <?php foreach($items as $item): ?>
        <div class="col">
            <div class="card h-100 border-secondary">
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="badge bg-secondary"><?= $item['ConditionGrade'] ?></span>
                        <small class="text-muted">#<?= $item['StockItemID'] ?></small>
                    </div>
                    <h5 class="card-title text-white text-truncate"><?= h($item['Title']) ?></h5>
                    <p class="card-text text-warning fw-bold fs-4">¥<?= number_format($item['UnitPrice'], 2) ?></p>
                    
                    <form action="/customer/cart_action.php" method="POST">
                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="stock_id" value="<?= $item['StockItemID'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-light w-100">
                            <i class="fa-solid fa-plus me-1"></i> Add to Cart
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>