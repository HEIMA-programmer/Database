<?php
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/auth_guard.php';
requireRole('Customer');
require_once __DIR__ . '/../../includes/header.php';

$cartIds = $_SESSION['cart'] ?? [];
$cartItems = [];
$subtotal = 0.00;

// 如果购物车不为空，从数据库获取详细信息
if (!empty($cartIds)) {
    // 生成占位符 ?,?,?
    $placeholders = implode(',', array_fill(0, count($cartIds), '?'));
    
    // 复用 Catalog 视图
    $sql = "SELECT * FROM vw_customer_catalog WHERE StockItemID IN ($placeholders)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($cartIds);
    $cartItems = $stmt->fetchAll();
    
    // 计算小计
    foreach ($cartItems as $item) {
        $subtotal += $item['UnitPrice'];
    }
}

// --- 业务规则计算 (Business Rules) ---

// 1. 生日折扣 (15% off during birthday month)
$isBirthdayMonth = false;
$discountRate = 0;
$discountAmount = 0;

if (isset($_SESSION['birth_month']) && $_SESSION['birth_month'] == date('m')) {
    $isBirthdayMonth = true;
    $discountRate = 0.15; // 15%
    $discountAmount = $subtotal * $discountRate;
}

// 2. 运费规则 (Free shipping over 200 RMB)
$shippingCost = ($subtotal > 200) ? 0 : 15.00;
if ($subtotal == 0) $shippingCost = 0; // 空车不收运费

// 最终总价
$finalTotal = $subtotal - $discountAmount + $shippingCost;

// 将计算结果存入 Session，供 Checkout 步骤验证使用
$_SESSION['checkout_totals'] = [
    'subtotal' => $subtotal,
    'discount' => $discountAmount,
    'shipping' => $shippingCost,
    'total'    => $finalTotal
];
?>

<h2 class="text-warning mb-4"><i class="fa-solid fa-cart-shopping me-2"></i>Shopping Cart</h2>

<div class="row">
    <div class="col-lg-8">
        <?php if (empty($cartItems)): ?>
            <div class="alert alert-secondary text-center py-5">
                <h4>Your cart is empty.</h4>
                <a href="catalog.php" class="btn btn-warning mt-3">Browse Catalog</a>
            </div>
        <?php else: ?>
            <div class="card bg-secondary text-light mb-3">
                <div class="card-header bg-dark d-flex justify-content-between">
                    <span>Items (<?= count($cartItems) ?>)</span>
                    <form action="cart_action.php" method="POST" class="d-inline">
                        <input type="hidden" name="action" value="clear">
                        <button type="submit" class="btn btn-sm btn-outline-danger border-0">Clear Cart</button>
                    </form>
                </div>
                <ul class="list-group list-group-flush bg-secondary">
                    <?php foreach ($cartItems as $item): ?>
                        <li class="list-group-item bg-secondary text-light d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center">
                                <div class="bg-dark rounded p-2 me-3 text-center" style="width: 50px; height: 50px;">
                                    <i class="fa-solid fa-music text-secondary"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0"><?= h($item['Title']) ?></h6>
                                    <small class="text-warning"><?= h($item['ArtistName']) ?></small>
                                    <span class="badge bg-black ms-2"><?= h($item['ConditionGrade']) ?></span>
                                </div>
                            </div>
                            <div class="d-flex align-items-center">
                                <span class="fw-bold me-4"><?= formatPrice($item['UnitPrice']) ?></span>
                                <form action="cart_action.php" method="POST">
                                    <input type="hidden" name="action" value="remove">
                                    <input type="hidden" name="stock_id" value="<?= $item['StockItemID'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Remove">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-4">
        <div class="card bg-dark text-white border-secondary">
            <div class="card-header bg-warning text-dark fw-bold">Order Summary</div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2">
                    <span>Subtotal</span>
                    <span><?= formatPrice($subtotal) ?></span>
                </div>
                
                <?php if ($isBirthdayMonth): ?>
                    <div class="d-flex justify-content-between mb-2 text-success">
                        <span><i class="fa-solid fa-cake-candles me-1"></i>Birthday Discount (15%)</span>
                        <span>-<?= formatPrice($discountAmount) ?></span>
                    </div>
                <?php endif; ?>

                <div class="d-flex justify-content-between mb-3">
                    <span>Shipping</span>
                    <?php if ($shippingCost == 0 && $subtotal > 0): ?>
                        <span class="text-success">Free</span>
                    <?php else: ?>
                        <span><?= formatPrice($shippingCost) ?></span>
                    <?php endif; ?>
                </div>

                <hr class="border-secondary">
                
                <div class="d-flex justify-content-between mb-4">
                    <span class="h5">Total</span>
                    <span class="h4 text-warning"><?= formatPrice($finalTotal) ?></span>
                </div>

                <?php if (!empty($cartItems)): ?>
                    <form action="checkout_process.php" method="POST">
                        <div class="mb-3">
                            <label class="form-label small">Order Type</label>
                            <select name="order_type" class="form-select bg-secondary text-light border-secondary mb-2">
                                <option value="Online">Home Delivery</option>
                                <option value="InStore">Pick up in Store (BOPIS)</option>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-success w-100 py-2 fw-bold">Proceed to Checkout</button>
                    </form>
                <?php else: ?>
                    <button class="btn btn-secondary w-100" disabled>Proceed to Checkout</button>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if ($subtotal > 0 && $subtotal < 200): ?>
            <div class="alert alert-info mt-3 small">
                <i class="fa-solid fa-truck-fast me-2"></i>
                Spend <strong><?= formatPrice(200 - $subtotal) ?></strong> more for free shipping!
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>