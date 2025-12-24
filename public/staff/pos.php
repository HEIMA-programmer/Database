<?php
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/auth_guard.php';
requireRole('Staff');
require_once __DIR__ . '/../../includes/header.php';

// 初始化 POS 购物车
if (!isset($_SESSION['pos_cart'])) {
    $_SESSION['pos_cart'] = [];
}

// 处理添加/移除操作
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_item'])) {
        $sid = $_POST['stock_id'];
        if (!in_array($sid, $_SESSION['pos_cart'])) {
            $_SESSION['pos_cart'][] = $sid;
            flash("Item #$sid added to POS cart.", 'success');
        }
    } elseif (isset($_POST['remove_item'])) {
        $sid = $_POST['stock_id'];
        $key = array_search($sid, $_SESSION['pos_cart']);
        if ($key !== false) {
            unset($_SESSION['pos_cart'][$key]);
            flash("Item removed.", 'info');
        }
    } elseif (isset($_POST['clear_cart'])) {
        $_SESSION['pos_cart'] = [];
        flash("Cart cleared.", 'info');
    }
    // 防止表单重复提交
    header("Location: pos.php");
    exit();
}

$shopId = $_SESSION['shop_id'] ?? 1;
$shopName = $_SESSION['shop_name'] ?? 'Unknown Shop';

// 搜索逻辑（优化：ID用精确匹配，标题用模糊匹配）
$search = $_GET['q'] ?? '';
$items = [];
if ($search) {
    // 智能判断：如果输入是纯数字，优先精确匹配ID；否则模糊匹配标题
    if (is_numeric($search)) {
        // ID精确搜索
        $sql = "SELECT * FROM vw_staff_pos_lookup WHERE ShopID = :shop AND Status = 'Available' AND StockItemID = :id LIMIT 20";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':shop' => $shopId, ':id' => (int)$search]);
        $items = $stmt->fetchAll();
    }

    // 如果ID搜索无结果，或者输入不是纯数字，则按标题搜索
    if (empty($items)) {
        $sql = "SELECT * FROM vw_staff_pos_lookup WHERE ShopID = :shop AND Status = 'Available' AND Title LIKE :q LIMIT 20";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':shop' => $shopId, ':q' => "%$search%"]);
        $items = $stmt->fetchAll();
    }
}

// 计算当前购物车总览（为了显示总价）使用视图查询
$cartTotal = 0;
$cartCount = count($_SESSION['pos_cart']);
if ($cartCount > 0) {
    $placeholders = implode(',', array_fill(0, $cartCount, '?'));
    $stmt = $pdo->prepare("SELECT UnitPrice FROM vw_staff_pos_lookup WHERE StockItemID IN ($placeholders)");
    $stmt->execute($_SESSION['pos_cart']);
    $prices = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $cartTotal = array_sum($prices);
}
?>

<div class="row">
    <div class="col-md-8">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="text-warning mb-0">Point of Sale</h2>
                <span class="badge bg-secondary text-info border border-info"><?= h($shopName) ?> Terminal</span>
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

        <div class="row row-cols-1 row-cols-md-3 g-3">
            <?php if(empty($items) && $search): ?>
                <div class="col-12 text-center text-muted py-5">
                    <p>No items found.</p>
                </div>
            <?php elseif(empty($items) && !$search): ?>
                <div class="col-12 text-center text-muted py-5">
                    <i class="fa-solid fa-barcode fa-3x mb-3"></i>
                    <p>Ready to scan.</p>
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
                            
                            <form method="POST">
                                <input type="hidden" name="add_item" value="1">
                                <input type="hidden" name="stock_id" value="<?= $item['StockItemID'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-light w-100">
                                    <i class="fa-solid fa-plus me-1"></i> Add
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card bg-secondary text-light h-100 border-0">
            <div class="card-header bg-dark border-secondary d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Current Transaction</h5>
                <form method="POST" onsubmit="return confirm('Clear cart?');">
                    <button type="submit" name="clear_cart" class="btn btn-sm btn-outline-danger">Clear</button>
                </form>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-3">
                    <span class="h5"><?= $cartCount ?> Items</span>
                    <span class="h4 text-warning"><?= formatPrice($cartTotal) ?></span>
                </div>
                
                <ul class="list-group list-group-flush bg-transparent mb-4" style="max-height: 400px; overflow-y: auto;">
                    <?php if($cartCount > 0): ?>
                        <?php foreach($_SESSION['pos_cart'] as $sid): ?>
                            <li class="list-group-item bg-dark text-light border-secondary d-flex justify-content-between align-items-center">
                                <span>Item #<?= $sid ?></span>
                                <form method="POST">
                                    <input type="hidden" name="remove_item" value="1">
                                    <input type="hidden" name="stock_id" value="<?= $sid ?>">
                                    <button type="submit" class="btn btn-sm text-danger"><i class="fa-solid fa-times"></i></button>
                                </form>
                            </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li class="list-group-item bg-transparent text-muted text-center">Cart is empty</li>
                    <?php endif; ?>
                </ul>

                <button class="btn btn-success w-100 btn-lg fw-bold" data-bs-toggle="modal" data-bs-target="#checkoutModal" <?= $cartCount == 0 ? 'disabled' : '' ?>>
                    Proceed to Checkout
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="checkoutModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-secondary text-light">
            <div class="modal-header border-dark">
                <h5 class="modal-title">Complete Transaction</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="pos_checkout.php" method="POST">
                <div class="modal-body">
                    <div class="alert alert-dark border-secondary">
                        <div class="d-flex justify-content-between">
                            <span>Total Due:</span>
                            <strong class="text-warning h4 mb-0"><?= formatPrice($cartTotal) ?></strong>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label text-info"><i class="fa-solid fa-user-tag me-2"></i>Customer Loyalty (Optional)</label>
                        <input type="email" name="customer_email" class="form-control bg-dark text-white border-secondary" placeholder="Enter Customer Email to earn points">
                        <div class="form-text text-light-50">Leave blank for guest checkout.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Payment Method</label>
                        <select class="form-select bg-dark text-white border-secondary">
                            <option>Cash</option>
                            <option>Credit Card</option>
                            <option>WeChat Pay</option>
                            <option>Alipay</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-dark">
                    <button type="submit" class="btn btn-success w-100 fw-bold">Confirm Payment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>