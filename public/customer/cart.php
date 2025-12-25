<?php
/**
 * 购物车页面
 * 【架构重构】遵循理想化分层架构
 * - 顶部：仅调用业务逻辑函数准备数据
 * - 底部：仅负责 HTML 渲染
 */
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/auth_guard.php';
requireRole('Customer');
require_once __DIR__ . '/../../includes/functions.php';

// =============================================
// 【数据准备层】调用 functions.php 的业务逻辑
// =============================================
$pageData = prepareCartPageData($pdo);
$cartItems = $pageData['items'];
$summary = $pageData['summary'];
$isEmpty = $pageData['empty'];

// 提取变量供模板使用
$subtotal = $summary['subtotal'] ?? 0;
$isBirthdayMonth = $summary['is_birthday_month'] ?? false;
$discountAmount = $summary['discount_amount'] ?? 0;
$shippingCost = $summary['shipping_cost'] ?? 0;
$finalTotal = $summary['final_total'] ?? 0;

require_once __DIR__ . '/../../includes/header.php';
?>

<!-- =============================================
     【表现层】仅负责 HTML 渲染，无任何业务逻辑
     ============================================= -->

<h2 class="text-warning mb-4"><i class="fa-solid fa-cart-shopping me-2"></i>Shopping Cart</h2>

<div class="row">
    <div class="col-lg-8">
        <?php if ($isEmpty): ?>
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

                <?php if (!$isEmpty): ?>
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
