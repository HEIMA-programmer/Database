<?php
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/auth_guard.php';
requireRole(['Staff', 'Manager']);
require_once __DIR__ . '/../../includes/header.php';

// 初始化 POS 购物车
if (!isset($_SESSION['pos_cart'])) {
    $_SESSION['pos_cart'] = [];
}

$shopId = $_SESSION['shop_id'];
$search = $_GET['q'] ?? '';

// 处理加入购物车逻辑
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_item'])) {
    $stockId = $_POST['stock_id'];
    $title = $_POST['title'];
    $price = $_POST['price'];
    
    // 简单结构存储
    $_SESSION['pos_cart'][$stockId] = [
        'id' => $stockId,
        'title' => $title,
        'price' => $price
    ];
    flash("Item added to transaction.", 'success');
    header("Location: pos.php"); // 防止表单重提交
    exit();
}

// 处理清空
if (isset($_POST['clear_cart'])) {
    $_SESSION['pos_cart'] = [];
    header("Location: pos.php");
    exit();
}

// 搜索逻辑 (仅搜索本店 Available 的库存)
$results = [];
if ($search) {
    // 使用 Phase 1 定义的视图 vw_staff_pos_lookup
    // 注意：视图本身是全局的，我们需要在 PHP 层面过滤 ShopID
    $sql = "SELECT * FROM vw_staff_pos_lookup 
            WHERE ShopID = :shop 
            AND Status = 'Available' 
            AND (Title LIKE :q OR ArtistName LIKE :q OR BatchNo LIKE :q)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':shop' => $shopId, ':q' => "%$search%"]);
    $results = $stmt->fetchAll();
}

// 计算当前总价
$total = 0;
foreach ($_SESSION['pos_cart'] as $item) {
    $total += $item['price'];
}
?>

<div class="row">
    <div class="col-md-8">
        <h2 class="text-warning mb-4"><i class="fa-solid fa-cash-register me-2"></i>POS Terminal - <?= h($_SESSION['shop_name']) ?></h2>
        
        <div class="card bg-secondary text-light mb-4">
            <div class="card-body">
                <form method="GET" class="d-flex gap-2">
                    <input type="text" name="q" class="form-control form-control-lg bg-dark text-white border-secondary" 
                           placeholder="Scan Batch No (e.g. B2025...) or Search Title" value="<?= h($search) ?>" autofocus>
                    <button type="submit" class="btn btn-warning btn-lg">Search</button>
                </form>
            </div>
        </div>

        <?php if ($search && empty($results)): ?>
            <div class="alert alert-info">No available items found in local inventory.</div>
        <?php elseif (!empty($results)): ?>
            <div class="table-responsive">
                <table class="table table-dark table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Batch #</th>
                            <th>Album</th>
                            <th>Condition</th>
                            <th>Price</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results as $r): ?>
                        <tr>
                            <td class="text-info font-monospace"><?= h($r['BatchNo']) ?></td>
                            <td>
                                <div class="fw-bold"><?= h($r['Title']) ?></div>
                                <small class="text-muted"><?= h($r['ArtistName']) ?></small>
                            </td>
                            <td><span class="badge bg-black"><?= h($r['ConditionGrade']) ?></span></td>
                            <td class="fw-bold text-warning"><?= formatPrice($r['UnitPrice']) ?></td>
                            <td>
                                <?php if (isset($_SESSION['pos_cart'][$r['StockItemID']])): ?>
                                    <button class="btn btn-sm btn-secondary" disabled>Added</button>
                                <?php else: ?>
                                    <form method="POST">
                                        <input type="hidden" name="add_item" value="1">
                                        <input type="hidden" name="stock_id" value="<?= $r['StockItemID'] ?>">
                                        <input type="hidden" name="title" value="<?= $r['Title'] ?>">
                                        <input type="hidden" name="price" value="<?= $r['UnitPrice'] ?>">
                                        <button type="submit" class="btn btn-sm btn-success">Add</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="col-md-4">
        <div class="card bg-dark text-white border-warning h-100">
            <div class="card-header bg-warning text-dark fw-bold d-flex justify-content-between align-items-center">
                <span>Current Transaction</span>
                <form method="POST" class="m-0">
                    <button type="submit" name="clear_cart" class="btn btn-sm btn-outline-dark px-2 py-0">Clear</button>
                </form>
            </div>
            <div class="card-body d-flex flex-column">
                <ul class="list-group list-group-flush bg-dark flex-grow-1 mb-3 overflow-auto" style="max-height: 400px;">
                    <?php if (empty($_SESSION['pos_cart'])): ?>
                        <li class="list-group-item bg-dark text-muted text-center py-5">Empty Basket</li>
                    <?php else: ?>
                        <?php foreach ($_SESSION['pos_cart'] as $item): ?>
                        <li class="list-group-item bg-dark text-light border-secondary d-flex justify-content-between">
                            <div>
                                <div class="fw-bold text-truncate" style="max-width: 150px;"><?= h($item['title']) ?></div>
                                <small class="text-muted">ID: <?= $item['id'] ?></small>
                            </div>
                            <span class="text-warning"><?= formatPrice($item['price']) ?></span>
                        </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>

                <div class="mt-auto">
                    <div class="d-flex justify-content-between mb-3 h4">
                        <span>Total:</span>
                        <span class="text-warning"><?= formatPrice($total) ?></span>
                    </div>
                    
                    <form action="pos_checkout.php" method="POST">
                        <div class="mb-3">
                            <label class="small text-muted">Payment Method</label>
                            <select name="payment_method" class="form-select bg-secondary text-light border-secondary">
                                <option value="Cash">Cash</option>
                                <option value="Card">Credit Card</option>
                                <option value="AliPay">AliPay</option>
                                <option value="WeChat">WeChat Pay</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-warning w-100 py-3 fw-bold" <?= empty($_SESSION['pos_cart']) ? 'disabled' : '' ?>>
                            COMPLETE SALE
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>