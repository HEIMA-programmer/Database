<?php
/**
 * 结账页面
 * 
 * 【修复】根据店铺类型显示履行选项：
 * - 仓库(Warehouse): 只能选择线上运输
 * - 门店(Retail): 可选线上运输或线下自提
 */
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/auth_guard.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_procedures.php';
requireRole('Customer');

// 检查购物车
if (empty($_SESSION['cart'])) {
    flash('Your cart is empty.', 'warning');
    header('Location: cart.php');
    exit;
}

// 【架构重构】使用DBProcedures获取购物车商品和店铺信息
$cartItems = DBProcedures::getCheckoutCartItems($pdo, $_SESSION['cart']);

// 验证商品可用性
if (count($cartItems) != count($_SESSION['cart'])) {
    flash('Some items are no longer available. Please review your cart.', 'warning');
    header('Location: cart.php');
    exit;
}

// 获取店铺信息
$shopInfo = [
    'ShopID' => $cartItems[0]['ShopID'],
    'Name' => $cartItems[0]['ShopName'],
    'Type' => $cartItems[0]['ShopType'],
    'Address' => $cartItems[0]['ShopAddress']
];

// 计算总价
$total = array_sum(array_column($cartItems, 'UnitPrice'));

// 【架构重构】使用DBProcedures获取客户信息
$customerId = $_SESSION['user_id'];
$customer = DBProcedures::getCustomerProfile($pdo, $customerId);

// 计算折扣
$discount = 0;
if ($customer['DiscountRate'] > 0) {
    $discount = $total * ($customer['DiscountRate'] / 100);
}

// 【新增】定义运费（选择Shipping时收取，Pickup免运费）
$shippingCost = 0;
define('SHIPPING_FEE', 15.00); // 固定运费¥15

$finalTotal = $total - $discount;

// ========== 处理订单提交 ==========
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fulfillmentType = $_POST['fulfillment_type'] ?? '';
    $shippingAddress = trim($_POST['shipping_address'] ?? '');

    // 验证履行方式
    if ($shopInfo['Type'] == 'Warehouse' && $fulfillmentType != 'Shipping') {
        $errors[] = 'Warehouse orders can only be shipped.';
    }

    if (!in_array($fulfillmentType, ['Shipping', 'Pickup'])) {
        $errors[] = 'Please select a valid fulfillment option.';
    }

    if ($fulfillmentType == 'Shipping' && empty($shippingAddress)) {
        $errors[] = 'Please enter a shipping address.';
    }

    // 【新增】计算运费
    $shippingCost = ($fulfillmentType == 'Shipping') ? SHIPPING_FEE : 0;
    $finalTotalWithShipping = $finalTotal + $shippingCost;

    if (empty($errors)) {
        try {
            // 【架构重构】使用存储过程创建订单
            $stockItemIds = array_column($cartItems, 'StockItemID');
            $result = DBProcedures::createOnlineOrderComplete(
                $pdo,
                $customerId,
                $shopInfo['ShopID'],
                $stockItemIds,
                $fulfillmentType,
                $fulfillmentType == 'Shipping' ? $shippingAddress : null,
                $shippingCost
            );

            if ($result && isset($result['order_id']) && $result['order_id'] > 0) {
                $orderId = $result['order_id'];

                // 清空购物车
                $_SESSION['cart'] = [];

                flash('Order #' . $orderId . ' created! Please complete your payment.', 'info');
                header('Location: pay.php?order_id=' . $orderId);
                exit;
            } elseif (isset($result['error']) && $result['error'] == 'no_available_items') {
                $errors[] = 'Some items are no longer available. Please review your cart.';
            } else {
                $errors[] = 'Order creation failed. Please try again.';
            }
        } catch (Exception $e) {
            $errors[] = 'Order failed: ' . $e->getMessage();
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row mb-4">
    <div class="col">
        <h1 class="display-5 text-warning fw-bold">
            <i class="fa-solid fa-credit-card me-2"></i>Checkout
        </h1>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?= h($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="POST">
    <div class="row">
        <div class="col-lg-8">
            <!-- 店铺信息 -->
            <div class="card bg-dark border-secondary mb-4">
                <div class="card-header bg-dark border-secondary">
                    <h5 class="mb-0 text-warning">
                        <i class="fa-solid <?= $shopInfo['Type'] == 'Warehouse' ? 'fa-warehouse' : 'fa-store' ?> me-2"></i>
                        Order from: <?= h($shopInfo['Name']) ?>
                    </h5>
                </div>
            </div>
            
            <!-- 履行方式选择 -->
            <div class="card bg-dark border-warning mb-4">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0"><i class="fa-solid fa-truck me-2"></i>Fulfillment Options</h5>
                </div>
                <div class="card-body">
                    <?php if ($shopInfo['Type'] == 'Warehouse'): ?>
                        <!-- 仓库只能运输 -->
                        <div class="alert alert-info bg-dark border-info mb-3">
                            <i class="fa-solid fa-info-circle me-2"></i>
                            Warehouse orders are shipped directly to your address.
                        </div>
                        <input type="hidden" name="fulfillment_type" value="Shipping">
                        <div class="form-check p-3 border border-warning rounded">
                            <input class="form-check-input" type="radio" checked disabled>
                            <label class="form-check-label w-100">
                                <div class="d-flex align-items-center">
                                    <i class="fa-solid fa-shipping-fast fa-2x text-warning me-3"></i>
                                    <div>
                                        <strong class="text-white">Home Delivery</strong>
                                        <p class="mb-0 text-muted small">Standard shipping (3-5 business days)</p>
                                    </div>
                                </div>
                            </label>
                        </div>
                    <?php else: ?>
                        <!-- 门店可选运输或自提 -->
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="form-check p-3 border border-secondary rounded h-100 fulfillment-option" data-type="pickup">
                                    <input class="form-check-input" type="radio" name="fulfillment_type" 
                                           value="Pickup" id="pickup" <?= ($_POST['fulfillment_type'] ?? '') == 'Pickup' ? 'checked' : '' ?>>
                                    <label class="form-check-label w-100" for="pickup">
                                        <div class="d-flex align-items-center">
                                            <i class="fa-solid fa-store fa-2x text-info me-3"></i>
                                            <div>
                                                <strong class="text-white">In-Store Pickup</strong>
                                                <p class="mb-0 text-muted small">Pick up at <?= h($shopInfo['Name']) ?></p>
                                                <p class="mb-0 text-muted small"><?= h($shopInfo['Address']) ?></p>
                                            </div>
                                        </div>
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check p-3 border border-secondary rounded h-100 fulfillment-option" data-type="shipping">
                                    <input class="form-check-input" type="radio" name="fulfillment_type" 
                                           value="Shipping" id="shipping" <?= ($_POST['fulfillment_type'] ?? '') == 'Shipping' ? 'checked' : '' ?>>
                                    <label class="form-check-label w-100" for="shipping">
                                        <div class="d-flex align-items-center">
                                            <i class="fa-solid fa-shipping-fast fa-2x text-warning me-3"></i>
                                            <div>
                                                <strong class="text-white">Home Delivery</strong>
                                                <p class="mb-0 text-muted small">Standard shipping (3-5 business days)</p>
                                            </div>
                                        </div>
                                    </label>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- 配送地址（仅运输时显示） -->
                    <div id="shipping-address-section" class="mt-4" style="<?= ($shopInfo['Type'] == 'Retail' && ($_POST['fulfillment_type'] ?? '') != 'Shipping') ? 'display:none;' : '' ?>">
                        <label for="shipping_address" class="form-label text-warning">
                            <i class="fa-solid fa-location-dot me-1"></i>Shipping Address
                        </label>
                        <textarea class="form-control bg-dark text-white border-secondary" 
                                  id="shipping_address" name="shipping_address" rows="3"
                                  placeholder="Enter your full shipping address..."><?= h($_POST['shipping_address'] ?? $customer['Address'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>
            
            <!-- 订单商品 -->
            <div class="card bg-dark border-secondary">
                <div class="card-header bg-dark border-secondary">
                    <h5 class="mb-0 text-warning">
                        <i class="fa-solid fa-box me-2"></i>Order Items (<?= count($cartItems) ?>)
                    </h5>
                </div>
                <div class="card-body p-0">
                    <table class="table table-dark mb-0">
                        <tbody>
                            <?php foreach ($cartItems as $item): ?>
                            <tr>
                                <td>
                                    <strong class="text-white"><?= h($item['Title']) ?></strong><br>
                                    <small class="text-warning"><?= h($item['ArtistName']) ?></small>
                                    <span class="badge bg-secondary ms-2"><?= h($item['ConditionGrade']) ?></span>
                                </td>
                                <td class="text-end text-white"><?= formatPrice($item['UnitPrice']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <!-- 订单摘要 -->
            <div class="card bg-dark border-warning sticky-top" style="top: 20px;">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0"><i class="fa-solid fa-receipt me-2"></i>Order Summary</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Subtotal</span>
                        <span class="text-white"><?= formatPrice($total) ?></span>
                    </div>
                    
                    <?php if ($discount > 0): ?>
                    <div class="d-flex justify-content-between mb-2 text-success">
                        <span><i class="fa-solid fa-tag me-1"></i><?= h($customer['TierName']) ?> Discount (<?= $customer['DiscountRate'] ?>%)</span>
                        <span>-<?= formatPrice($discount) ?></span>
                    </div>
                    <?php endif; ?>

                    <!-- 【新增】运费显示 -->
                    <div class="d-flex justify-content-between mb-2" id="shipping-cost-row">
                        <span class="text-muted"><i class="fa-solid fa-truck me-1"></i>Shipping</span>
                        <span class="text-white" id="shipping-cost-display">
                            <?= $shopInfo['Type'] == 'Warehouse' ? formatPrice(SHIPPING_FEE) : 'Select option' ?>
                        </span>
                    </div>

                    <hr class="border-secondary">

                    <div class="d-flex justify-content-between mb-2">
                        <span class="fs-5 text-warning">Total</span>
                        <span class="fs-4 text-warning fw-bold" id="total-display">
                            <?= $shopInfo['Type'] == 'Warehouse' ? formatPrice($finalTotal + SHIPPING_FEE) : formatPrice($finalTotal) ?>
                        </span>
                    </div>
                    
                    <?php if ($customer['Points'] > 0): ?>
                    <div class="text-muted small mb-3">
                        <i class="fa-solid fa-coins me-1"></i>You have <?= number_format($customer['Points']) ?> points
                    </div>
                    <?php endif; ?>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-warning btn-lg">
                            <i class="fa-solid fa-check me-1"></i> Place Order
                        </button>
                        <a href="cart.php" class="btn btn-outline-secondary">
                            <i class="fa-solid fa-arrow-left me-1"></i> Back to Cart
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const pickupRadio = document.getElementById('pickup');
    const shippingRadio = document.getElementById('shipping');
    const addressSection = document.getElementById('shipping-address-section');
    const fulfillmentOptions = document.querySelectorAll('.fulfillment-option');
    const shippingCostDisplay = document.getElementById('shipping-cost-display');
    const totalDisplay = document.getElementById('total-display');

    // 【新增】价格常量
    const SHIPPING_FEE = <?= SHIPPING_FEE ?>;
    const subtotalAfterDiscount = <?= $finalTotal ?>;

    function updateUI() {
        // 更新地址显示
        // 修复：Warehouse 时没有 radio 按钮，地址栏应始终显示
        const isWarehouse = !pickupRadio && !shippingRadio;
        
        if (isWarehouse || (shippingRadio && shippingRadio.checked)) {
            addressSection.style.display = 'block';
        } else if (addressSection) {
            addressSection.style.display = 'none';
        }

        // 【新增】更新运费和总额显示
        if (shippingRadio && shippingRadio.checked) {
            shippingCostDisplay.textContent = '¥' + SHIPPING_FEE.toFixed(2);
            totalDisplay.textContent = '¥' + (subtotalAfterDiscount + SHIPPING_FEE).toFixed(2);
        } else if (pickupRadio && pickupRadio.checked) {
            shippingCostDisplay.innerHTML = '<span class="text-success">Free</span>';
            totalDisplay.textContent = '¥' + subtotalAfterDiscount.toFixed(2);
        }

        // 更新选中样式
        fulfillmentOptions.forEach(opt => {
            const radio = opt.querySelector('input[type="radio"]');
            if (radio && radio.checked) {
                opt.classList.remove('border-secondary');
                opt.classList.add('border-warning');
            } else {
                opt.classList.remove('border-warning');
                opt.classList.add('border-secondary');
            }
        });
    }

    if (pickupRadio) pickupRadio.addEventListener('change', updateUI);
    if (shippingRadio) shippingRadio.addEventListener('change', updateUI);

    // 点击卡片也能选中
    fulfillmentOptions.forEach(opt => {
        opt.addEventListener('click', function() {
            const radio = this.querySelector('input[type="radio"]');
            if (radio) {
                radio.checked = true;
                updateUI();
            }
        });
    });

    updateUI();
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>