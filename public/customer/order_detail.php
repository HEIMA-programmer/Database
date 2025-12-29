<?php
/**
 * 【架构重构】订单详情页面
 * 表现层 - 仅负责数据展示和用户交互
 * 【新增】支持15分钟支付倒计时显示和手动取消订单
 */
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/auth_guard.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_procedures.php';
requireRole('Customer');

$customerId = $_SESSION['user_id'];
$orderId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// 支付超时时间（15分钟）
define('PAYMENT_TIMEOUT_MINUTES', 15);

if (!$orderId) {
    flash("Invalid order ID.", 'danger');
    header("Location: orders.php");
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

// 计算剩余支付时间
$remainingSeconds = 0;
$isExpired = false;
if ($order['OrderStatus'] === 'Pending') {
    $orderTime = strtotime($order['OrderDate']);
    $expiryTime = $orderTime + (PAYMENT_TIMEOUT_MINUTES * 60);
    $remainingSeconds = $expiryTime - time();
    $isExpired = $remainingSeconds <= 0;
}

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
                                <th class="text-end">Price</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                            <tr>
                                <td>
                                    <i class="fa-solid fa-compact-disc text-warning me-2"></i>
                                    <?= h($item['AlbumTitle']) ?>
                                </td>
                                <td class="text-end text-success fw-bold"><?= formatPrice($item['PriceAtSale']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="border-top border-secondary">
                            <tr>
                                <th>Total</th>
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
                    <li>
                        <small class="text-muted d-block">Status</small>
                        <span class="badge <?= $statusClass ?>"><?= h($order['OrderStatus']) ?></span>
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
                        <span class="fw-bold">剩余支付时间: </span>
                        <span id="countdown-display" class="text-danger fw-bold" data-remaining="<?= $remainingSeconds ?>"></span>
                    </div>
                    <p class="small mb-3">Please complete your payment within the time limit.</p>
                    <div class="d-flex gap-2">
                        <a href="pay.php?order_id=<?= $order['OrderID'] ?>" class="btn btn-dark btn-sm">
                            <i class="fa-solid fa-credit-card me-1"></i>Pay Now
                        </a>
                        <button type="button" class="btn btn-outline-dark btn-sm" onclick="cancelOrder(<?= $order['OrderID'] ?>)">
                            <i class="fa-solid fa-times me-1"></i>Cancel Order
                        </button>
                    </div>
                <?php else: ?>
                    <p class="text-danger fw-bold mb-2">
                        <i class="fa-solid fa-exclamation-triangle me-1"></i>
                        支付已超时，订单将被自动取消
                    </p>
                    <button type="button" class="btn btn-outline-dark btn-sm" onclick="cancelOrder(<?= $order['OrderID'] ?>)">
                        <i class="fa-solid fa-times me-1"></i>取消订单
                    </button>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- 倒计时和取消订单脚本 -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const countdownEl = document.getElementById('countdown-display');
    if (!countdownEl) return;

    let remaining = parseInt(countdownEl.dataset.remaining);

    function updateCountdown() {
        if (remaining <= 0) {
            countdownEl.textContent = '已超时';
            // 自动取消订单
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

function cancelOrder(orderId) {
    if (!confirm('确定要取消此订单吗？取消后库存将被释放。')) {
        return;
    }

    fetch('cancel_order.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'order_id=' + orderId
    }).then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('订单已取消');
            location.reload();
        } else {
            alert('取消失败: ' + data.message);
        }
    }).catch(error => {
        console.error('Error:', error);
        alert('操作失败，请重试');
    });
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
