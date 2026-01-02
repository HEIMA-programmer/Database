<?php
/**
 * 【架构重构】订单详情页面
 * 表现层 - 仅负责数据展示和用户交互
 * 【新增】支持15分钟支付倒计时显示和手动取消订单
 * 【新增】支持收货确认功能（Shipped状态的delivery订单）
 */
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/auth_guard.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_procedures.php';
requireRole('Customer');

$customerId = $_SESSION['user_id'];
$orderId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// PAYMENT_TIMEOUT_MINUTES 常量已在 config/db_connect.php 中定义

if (!$orderId) {
    flash("Invalid order ID.", 'danger');
    header("Location: orders.php");
    exit();
}

// ========== POST 请求处理 - 收货确认 ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_received'])) {
    $confirmOrderId = (int)$_POST['order_id'];

    // 验证订单归属和状态
    $orderCheck = DBProcedures::getOrderBasicInfo($pdo, $confirmOrderId);
    if ($orderCheck && $orderCheck['CustomerID'] == $customerId && $orderCheck['OrderStatus'] === 'Shipped') {
        // 更新订单状态为Completed
        $result = DBProcedures::updateOrderStatus($pdo, $confirmOrderId, 'Completed');
        if ($result) {
            flash('Thank you! Your order has been marked as received.', 'success');
        } else {
            flash('Failed to confirm receipt. Please try again.', 'danger');
        }
    } else {
        flash('Invalid order or order cannot be confirmed.', 'danger');
    }
    header("Location: order_detail.php?id=" . $confirmOrderId);
    exit();
}

// ========== 数据准备 ==========
$pageData = prepareOrderDetailPageData($pdo, $orderId, $customerId);

if (!$pageData['found']) {
    flash("Order not found or access denied.", 'danger');
    header("Location: orders.php");
    exit();
}

$order = $pageData['order'];
$items = $pageData['items'];
$statusClass = $pageData['status_class'];

// 【新增】按Release+Condition+Price分组商品
$itemsGrouped = [];
$subtotalBeforeDiscount = 0; // 计算折扣前小计（商品原价总和）

foreach ($items as $item) {
    $key = ($item['ReleaseID'] ?? $item['AlbumTitle']) . '_' . ($item['ConditionGrade'] ?? 'N/A') . '_' . $item['PriceAtSale'];
    // 使用PriceAtSale作为原价（未折扣的单价）
    $originalPrice = $item['PriceAtSale'];
    $subtotalBeforeDiscount += $originalPrice;

    if (!isset($itemsGrouped[$key])) {
        $itemsGrouped[$key] = [
            'AlbumTitle' => $item['AlbumTitle'],
            'ArtistName' => $item['ArtistName'] ?? '',
            'ConditionGrade' => $item['ConditionGrade'] ?? 'N/A',
            'PriceAtSale' => $item['PriceAtSale'],
            'OriginalPrice' => $originalPrice,
            'Quantity' => 1,
            'Subtotal' => $item['PriceAtSale']
        ];
    } else {
        $itemsGrouped[$key]['Quantity']++;
        $itemsGrouped[$key]['Subtotal'] += $item['PriceAtSale'];
    }
}

// 计算运费和积分
$shippingCost = $order['ShippingCost'] ?? 0;
$goodsAmount = $order['TotalAmount'] - $shippingCost;

// 【修复】从订单总价反推折扣金额
// TotalAmount = 商品实付金额 + 运费，已包含折扣
// totalDiscount = 商品原价总和 - 商品实付金额
$totalDiscount = $subtotalBeforeDiscount - $goodsAmount;
if ($totalDiscount < 0.01) $totalDiscount = 0; // 避免浮点误差

// 积分计算：每消费1元商品获得1积分（不含运费）
$pointsEarned = floor($goodsAmount);

// 计算剩余支付时间
$remainingSeconds = 0;
$isExpired = false;
if ($order['OrderStatus'] === 'Pending') {
    $orderTime = strtotime($order['OrderDate']);
    $expiryTime = $orderTime + (PAYMENT_TIMEOUT_MINUTES * 60);
    $remainingSeconds = $expiryTime - time();
    $isExpired = $remainingSeconds <= 0;
}

// 判断是否可以确认收货
$canConfirmReceived = ($order['OrderStatus'] === 'Shipped' &&
                       $order['OrderType'] === 'Online' &&
                       ($order['FulfillmentType'] ?? '') !== 'Pickup');

require_once __DIR__ . '/../../includes/header.php';
?>

<!-- ========== 表现层 ========== -->
<div class="mb-4">
    <a href="orders.php" class="btn btn-outline-light">
        <i class="fa-solid fa-arrow-left me-2"></i>Back to Orders
    </a>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card bg-dark border-secondary mb-4">
            <div class="card-header border-secondary d-flex justify-content-between align-items-center">
                <h5 class="mb-0 text-warning">
                    <i class="fa-solid fa-receipt me-2"></i>Order #<?= $order['OrderID'] ?>
                </h5>
                <span class="badge <?= $statusClass ?>"><?= h($order['OrderStatus']) ?></span>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-dark table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Album</th>
                                <th>Condition</th>
                                <th class="text-center">Qty</th>
                                <th class="text-end">Unit Price</th>
                                <th class="text-end">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($itemsGrouped as $item): ?>
                            <?php
                                // 计算折扣
                                $originalTotal = $item['OriginalPrice'] * $item['Quantity'];
                                $discount = $originalTotal - $item['Subtotal'];
                                $hasDiscount = $discount > 0.01;
                            ?>
                            <tr>
                                <td>
                                    <i class="fa-solid fa-compact-disc text-warning me-2"></i>
                                    <span class="text-white"><?= h($item['AlbumTitle']) ?></span>
                                    <?php if ($item['ArtistName']): ?>
                                        <br><small class="text-warning"><?= h($item['ArtistName']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $condClass = match($item['ConditionGrade']) {
                                        'New', 'Mint' => 'bg-success',
                                        'NM', 'VG+'   => 'bg-info text-dark',
                                        default       => 'bg-secondary'
                                    };
                                    ?>
                                    <span class="badge <?= $condClass ?>"><?= h($item['ConditionGrade']) ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-warning text-dark"><?= $item['Quantity'] ?></span>
                                </td>
                                <td class="text-end text-warning"><?= formatPrice($item['PriceAtSale']) ?></td>
                                <td class="text-end">
                                    <span class="text-success fw-bold"><?= formatPrice($item['Subtotal']) ?></span>
                                    <?php if ($hasDiscount): ?>
                                        <br><small class="text-info">-<?= formatPrice($discount) ?> discount</small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="border-top border-secondary">
                            <?php if ($totalDiscount > 0.01): ?>
                            <tr>
                                <td colspan="4" class="text-end text-muted">Subtotal</td>
                                <td class="text-end text-muted"><?= formatPrice($subtotalBeforeDiscount) ?></td>
                            </tr>
                            <tr>
                                <td colspan="4" class="text-end text-info">
                                    <i class="fa-solid fa-tags me-1"></i>Member Discount
                                </td>
                                <td class="text-end text-info">-<?= formatPrice($totalDiscount) ?></td>
                            </tr>
                            <?php endif; ?>
                            <?php if ($shippingCost > 0): ?>
                            <tr>
                                <td colspan="4" class="text-end text-muted">
                                    <i class="fa-solid fa-truck me-1"></i>Shipping
                                </td>
                                <td class="text-end text-muted"><?= formatPrice($shippingCost) ?></td>
                            </tr>
                            <?php endif; ?>
                            <tr>
                                <th colspan="4" class="text-end">Total</th>
                                <th class="text-end text-warning fs-5"><?= formatPrice($order['TotalAmount']) ?></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card bg-secondary border-0 mb-4">
            <div class="card-header bg-transparent border-bottom border-dark">
                <h6 class="mb-0 text-light"><i class="fa-solid fa-circle-info me-2"></i>Order Details</h6>
            </div>
            <div class="card-body">
                <ul class="list-unstyled mb-0">
                    <li class="mb-3">
                        <small class="text-muted d-block">Order Date</small>
                        <span class="text-light"><?= formatDate($order['OrderDate']) ?></span>
                    </li>
                    <li class="mb-3">
                        <small class="text-muted d-block">Order Type</small>
                        <?php
                        // 【修复】正确区分订单类型
                        // InStore = 店内购买
                        // Online + Pickup = 线上支付线下取货
                        // Online + Shipping = 线上配送
                        if($order['OrderType'] == 'InStore'): ?>
                            <span class="badge bg-success text-dark">In-Store</span>
                        <?php elseif(($order['FulfillmentType'] ?? '') == 'Pickup'): ?>
                            <span class="badge bg-info text-dark">In-Store Pickup</span>
                        <?php else: ?>
                            <span class="badge bg-primary">Online Delivery</span>
                        <?php endif; ?>
                    </li>
                    <li class="mb-3">
                        <small class="text-muted d-block">Fulfilled By</small>
                        <span class="text-light"><?= h($order['ShopName'] ?? 'Warehouse') ?></span>
                    </li>
                    <li class="mb-3">
                        <small class="text-muted d-block">Status</small>
                        <span class="badge <?= $statusClass ?>"><?= h($order['OrderStatus']) ?></span>
                    </li>
                    <li>
                        <small class="text-muted d-block">Points Earned</small>
                        <?php if ($order['OrderStatus'] === 'Completed'): ?>
                            <span class="text-warning fw-bold">
                                <i class="fa-solid fa-star me-1"></i>+<?= number_format($pointsEarned) ?> pts
                            </span>
                        <?php elseif (in_array($order['OrderStatus'], ['Pending', 'Paid', 'Shipped'])): ?>
                            <span class="text-muted">
                                <i class="fa-regular fa-star me-1"></i>~<?= number_format($pointsEarned) ?> pts
                                <small class="d-block text-light-50">(upon completion)</small>
                            </span>
                        <?php else: ?>
                            <span class="text-muted">--</span>
                        <?php endif; ?>
                    </li>
                </ul>
            </div>
        </div>

        <?php if ($order['OrderStatus'] === 'Pending'): ?>
        <div class="card bg-warning text-dark border-0 mb-3">
            <div class="card-body">
                <h6 class="mb-2"><i class="fa-solid fa-clock me-2"></i>Payment Required</h6>
                <?php if (!$isExpired): ?>
                    <div class="mb-2">
                        <span class="fw-bold">Time remaining: </span>
                        <span id="countdown-display" class="text-danger fw-bold" data-remaining="<?= $remainingSeconds ?>"></span>
                    </div>
                    <p class="small mb-3">Please complete your payment within the time limit.</p>
                    <div class="d-flex gap-2">
                        <a href="pay.php?order_id=<?= $order['OrderID'] ?>" class="btn btn-dark btn-sm">
                            <i class="fa-solid fa-credit-card me-1"></i>Pay Now
                        </a>
                        <button type="button" class="btn btn-outline-dark btn-sm" onclick="showCancelModal()">
                            <i class="fa-solid fa-times me-1"></i>Cancel Order
                        </button>
                    </div>
                <?php else: ?>
                    <p class="text-danger fw-bold mb-2">
                        <i class="fa-solid fa-exclamation-triangle me-1"></i>
                        Payment expired, order will be cancelled automatically
                    </p>
                    <button type="button" class="btn btn-outline-dark btn-sm" onclick="showCancelModal()">
                        <i class="fa-solid fa-times me-1"></i>Cancel Order
                    </button>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($canConfirmReceived): ?>
        <div class="card bg-success text-white border-0 mb-3">
            <div class="card-body">
                <h6 class="mb-2"><i class="fa-solid fa-truck me-2"></i>Order Shipped</h6>
                <p class="small mb-3">Your order has been shipped. Please confirm when you receive it.</p>
                <form method="POST" id="confirmReceivedForm">
                    <input type="hidden" name="confirm_received" value="1">
                    <input type="hidden" name="order_id" value="<?= $order['OrderID'] ?>">
                    <button type="button" class="btn btn-light btn-sm" onclick="showConfirmReceivedModal()">
                        <i class="fa-solid fa-check me-1"></i>Confirm Received
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Custom Confirm Modal for Cancel Order -->
<div class="modal fade custom-confirm-modal" id="cancelOrderModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa-solid fa-exclamation-triangle me-2"></i>Cancel Order</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                Are you sure you want to cancel this order? Inventory will be released.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Keep Order</button>
                <button type="button" class="btn btn-danger" onclick="cancelOrder(<?= $order['OrderID'] ?>)">Cancel Order</button>
            </div>
        </div>
    </div>
</div>

<!-- Custom Confirm Modal for Confirm Received -->
<div class="modal fade custom-confirm-modal" id="confirmReceivedModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa-solid fa-check-circle me-2"></i>Confirm Receipt</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                Have you received your order? This will mark the order as completed.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Not Yet</button>
                <button type="button" class="btn btn-success" onclick="document.getElementById('confirmReceivedForm').submit()">
                    Yes, I Received It
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Alert Modal for showing messages -->
<div class="modal fade custom-confirm-modal" id="alertModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="alertModalTitle">Notice</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="alertModalBody">
                Message
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-warning" data-bs-dismiss="modal" id="alertModalBtn">OK</button>
            </div>
        </div>
    </div>
</div>

<!-- 倒计时和取消订单脚本 -->
<script>
let cancelModal, confirmReceivedModal, alertModal;

document.addEventListener('DOMContentLoaded', function() {
    const cancelModalEl = document.getElementById('cancelOrderModal');
    const confirmReceivedModalEl = document.getElementById('confirmReceivedModal');
    const alertModalEl = document.getElementById('alertModal');

    if (cancelModalEl) cancelModal = new bootstrap.Modal(cancelModalEl);
    if (confirmReceivedModalEl) confirmReceivedModal = new bootstrap.Modal(confirmReceivedModalEl);
    if (alertModalEl) alertModal = new bootstrap.Modal(alertModalEl);

    const countdownEl = document.getElementById('countdown-display');
    if (!countdownEl) return;

    let remaining = parseInt(countdownEl.dataset.remaining);

    function updateCountdown() {
        if (remaining <= 0) {
            countdownEl.textContent = 'Expired';
            // Auto cancel order
            cancelOrder(<?= $order['OrderID'] ?>);
            return;
        }

        const minutes = Math.floor(remaining / 60);
        const seconds = remaining % 60;
        countdownEl.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;

        remaining--;
        setTimeout(updateCountdown, 1000);
    }

    updateCountdown();
});

function showCancelModal() {
    if (cancelModal) cancelModal.show();
}

function showConfirmReceivedModal() {
    if (confirmReceivedModal) confirmReceivedModal.show();
}

function showAlert(title, message, callback) {
    document.getElementById('alertModalTitle').textContent = title;
    document.getElementById('alertModalBody').textContent = message;
    const btn = document.getElementById('alertModalBtn');
    if (callback) {
        btn.onclick = function() {
            alertModal.hide();
            callback();
        };
    } else {
        btn.onclick = function() { alertModal.hide(); };
    }
    alertModal.show();
}

function cancelOrder(orderId) {
    cancelModal.hide();

    fetch('cancel_order.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        credentials: 'same-origin',
        body: 'order_id=' + orderId
    }).then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('Order Cancelled', 'Your order has been cancelled successfully.', function() {
                location.reload();
            });
        } else {
            showAlert('Error', 'Failed to cancel: ' + data.message);
        }
    }).catch(error => {
        console.error('Error:', error);
        showAlert('Error', 'Operation failed. Please try again.');
    });
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
