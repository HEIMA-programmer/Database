<?php
/**
 * 【架构重构】订单详情页面
 * 表现层 - 仅负责数据展示和用户交互
 *
 * 【新增】未支付订单倒计时功能：
 * - 订单创建后15分钟内需要支付
 * - 超时自动取消并恢复库存
 * - 支持手动取消未支付订单
 */
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/auth_guard.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_procedures.php';
requireRole('Customer');

// 订单支付超时时间（秒）
define('ORDER_PAYMENT_TIMEOUT', 15 * 60); // 15分钟

$customerId = $_SESSION['user_id'];
$orderId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$orderId) {
    flash("Invalid order ID.", 'danger');
    header("Location: orders.php");
    exit();
}

// 处理取消订单请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_order') {
    $cancelOrderId = (int)($_POST['order_id'] ?? 0);
    if ($cancelOrderId === $orderId) {
        // 验证订单属于当前客户且状态为Pending
        $stmt = $pdo->prepare("SELECT OrderStatus FROM CustomerOrder WHERE OrderID = ? AND CustomerID = ?");
        $stmt->execute([$cancelOrderId, $customerId]);
        $orderCheck = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($orderCheck && $orderCheck['OrderStatus'] === 'Pending') {
            try {
                $pdo->beginTransaction();
                DBProcedures::cancelOrder($pdo, $cancelOrderId);
                $pdo->commit();
                flash("Order #$cancelOrderId has been cancelled. Inventory has been restored.", 'info');
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                flash("Failed to cancel order: " . $e->getMessage(), 'danger');
            }
        } else {
            flash("Cannot cancel this order.", 'danger');
        }
    }
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

// 计算未支付订单的剩余支付时间
$remainingSeconds = 0;
$isExpired = false;
if ($order['OrderStatus'] === 'Pending') {
    $orderTime = strtotime($order['OrderDate']);
    $expiryTime = $orderTime + ORDER_PAYMENT_TIMEOUT;
    $remainingSeconds = $expiryTime - time();

    // 如果已过期，自动取消订单
    if ($remainingSeconds <= 0) {
        $isExpired = true;
        try {
            $pdo->beginTransaction();
            DBProcedures::cancelOrder($pdo, $orderId);
            $pdo->commit();
            flash("Order #$orderId has been automatically cancelled due to payment timeout. Inventory has been restored.", 'warning');
            header("Location: orders.php");
            exit();
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            // 静默失败，让页面继续加载
        }
    }
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

                <!-- 倒计时显示 -->
                <div class="countdown-container mb-3" id="countdownContainer" data-remaining="<?= $remainingSeconds ?>">
                    <div class="d-flex align-items-center">
                        <i class="fa-solid fa-hourglass-half me-2"></i>
                        <span class="small">Time remaining: </span>
                        <span id="countdown" class="ms-1 fw-bold"></span>
                    </div>
                    <div class="progress mt-2" style="height: 6px;">
                        <div class="progress-bar bg-dark" id="countdownProgress" role="progressbar" style="width: 100%"></div>
                    </div>
                </div>

                <p class="small mb-3">Please complete your payment before time runs out. The order will be automatically cancelled if not paid in time.</p>

                <div class="d-flex gap-2">
                    <a href="pay.php?order_id=<?= $order['OrderID'] ?>" class="btn btn-dark btn-sm">
                        <i class="fa-solid fa-credit-card me-1"></i>Pay Now
                    </a>
                    <form method="POST" class="d-inline confirm-form" data-confirm-message="Are you sure you want to cancel this order?">
                        <input type="hidden" name="action" value="cancel_order">
                        <input type="hidden" name="order_id" value="<?= $order['OrderID'] ?>">
                        <button type="submit" class="btn btn-outline-dark btn-sm">
                            <i class="fa-solid fa-ban me-1"></i>Cancel Order
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($order['OrderStatus'] === 'Pending'): ?>
<script>
// 订单支付倒计时
(function() {
    const container = document.getElementById('countdownContainer');
    const countdownEl = document.getElementById('countdown');
    const progressEl = document.getElementById('countdownProgress');
    let remaining = parseInt(container.dataset.remaining);
    const total = <?= ORDER_PAYMENT_TIMEOUT ?>;

    function formatTime(seconds) {
        const mins = Math.floor(seconds / 60);
        const secs = seconds % 60;
        return `${mins}:${secs.toString().padStart(2, '0')}`;
    }

    function updateCountdown() {
        if (remaining <= 0) {
            countdownEl.textContent = 'Expired';
            progressEl.style.width = '0%';
            // 自动刷新页面触发后端取消
            RetroEcho.showAlert('Your order has expired and will be cancelled.', {
                title: 'Order Expired',
                type: 'warning'
            }).then(() => {
                window.location.reload();
            });
            return;
        }

        countdownEl.textContent = formatTime(remaining);
        const progressPct = (remaining / total) * 100;
        progressEl.style.width = progressPct + '%';

        // 时间紧迫时改变颜色
        if (remaining <= 60) {
            countdownEl.classList.add('text-danger');
            progressEl.classList.remove('bg-dark');
            progressEl.classList.add('bg-danger');
        } else if (remaining <= 180) {
            countdownEl.classList.add('text-warning');
        }

        remaining--;
        setTimeout(updateCountdown, 1000);
    }

    updateCountdown();
})();

// 处理需要确认的表单
document.querySelectorAll('.confirm-form').forEach(form => {
    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        const message = this.dataset.confirmMessage || 'Are you sure?';
        const confirmed = await RetroEcho.showConfirm(message, {
            title: 'Cancel Order',
            confirmText: 'Yes, Cancel',
            cancelText: 'No, Keep Order',
            confirmClass: 'btn-danger',
            icon: 'fa-ban'
        });
        if (confirmed) {
            this.submit();
        }
    });
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
