<?php
/**
 * 【架构重构】订单列表页面
 * 表现层 - 仅负责数据展示和用户交互
 *
 * 【新增】未支付订单倒计时功能
 */
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/auth_guard.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_procedures.php';
requireRole('Customer');

// 订单支付超时时间（秒）
define('ORDER_PAYMENT_TIMEOUT', 15 * 60); // 15分钟

$customerId = $_SESSION['user_id'];

// 处理取消订单请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_order') {
    $cancelOrderId = (int)($_POST['order_id'] ?? 0);
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
    header("Location: orders.php");
    exit();
}

// ========== 数据准备 ==========
$pageData = prepareOrdersPageData($pdo, $customerId);
$orders = $pageData['orders'];

// 检查并自动取消过期订单
foreach ($orders as $key => $order) {
    if ($order['OrderStatus'] === 'Pending') {
        $orderTime = strtotime($order['OrderDate']);
        $expiryTime = $orderTime + ORDER_PAYMENT_TIMEOUT;
        if (time() > $expiryTime) {
            try {
                $pdo->beginTransaction();
                DBProcedures::cancelOrder($pdo, $order['OrderID']);
                $pdo->commit();
                $orders[$key]['OrderStatus'] = 'Cancelled';
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
            }
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<!-- ========== 表现层 ========== -->
<h2 class="text-warning mb-4">My Orders</h2>

<div class="card bg-secondary text-light">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-dark table-hover mb-0">
                <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Total</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $o):
                        // 计算未支付订单剩余时间
                        $remainingSeconds = 0;
                        if ($o['OrderStatus'] === 'Pending') {
                            $orderTime = strtotime($o['OrderDate']);
                            $expiryTime = $orderTime + ORDER_PAYMENT_TIMEOUT;
                            $remainingSeconds = max(0, $expiryTime - time());
                        }
                    ?>
                    <tr>
                        <td>#<?= $o['OrderID'] ?></td>
                        <td><?= formatDate($o['OrderDate']) ?></td>
                        <td>
                            <?php
                            // 【修复】正确区分订单类型
                            // InStore = 店内购买
                            // Online + Pickup = 线上支付线下取货
                            // Online + Shipping = 线上配送
                            if($o['OrderType'] == 'InStore'): ?>
                                <span class="badge bg-success text-dark">In-Store</span>
                            <?php elseif(($o['FulfillmentType'] ?? '') == 'Pickup'): ?>
                                <span class="badge bg-info text-dark">Pickup</span>
                            <?php else: ?>
                                <span class="badge bg-primary">Delivery</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $statusClass = match($o['OrderStatus']) {
                                'Paid' => 'text-success',
                                'Completed' => 'text-success',
                                'Pending' => 'text-warning',
                                'Cancelled' => 'text-danger',
                                default => 'text-white'
                            };
                            ?>
                            <span class="<?= $statusClass ?> fw-bold"><?= h($o['OrderStatus']) ?></span>
                            <?php if ($o['OrderStatus'] === 'Pending' && $remainingSeconds > 0): ?>
                                <br>
                                <small class="countdown-timer text-muted" data-remaining="<?= $remainingSeconds ?>">
                                    <i class="fa-solid fa-clock me-1"></i>
                                    <span class="countdown-text"></span>
                                </small>
                            <?php endif; ?>
                        </td>
                        <td class="text-warning fw-bold"><?= formatPrice($o['TotalAmount']) ?></td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="order_detail.php?id=<?= $o['OrderID'] ?>" class="btn btn-outline-warning">
                                    <i class="fa-solid fa-eye me-1"></i>Details
                                </a>
                                <?php if ($o['OrderStatus'] === 'Pending'): ?>
                                <a href="pay.php?order_id=<?= $o['OrderID'] ?>" class="btn btn-warning">
                                    <i class="fa-solid fa-credit-card"></i>
                                </a>
                                <form method="POST" class="d-inline confirm-form" data-confirm-message="Are you sure you want to cancel this order?">
                                    <input type="hidden" name="action" value="cancel_order">
                                    <input type="hidden" name="order_id" value="<?= $o['OrderID'] ?>">
                                    <button type="submit" class="btn btn-outline-danger">
                                        <i class="fa-solid fa-ban"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if(empty($orders)): ?>
            <div class="p-4 text-center text-muted">No order history found.</div>
        <?php endif; ?>
    </div>
</div>

<script>
// 订单列表倒计时
function formatTime(seconds) {
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${mins}:${secs.toString().padStart(2, '0')}`;
}

document.querySelectorAll('.countdown-timer').forEach(timer => {
    let remaining = parseInt(timer.dataset.remaining);
    const textEl = timer.querySelector('.countdown-text');

    function update() {
        if (remaining <= 0) {
            textEl.textContent = 'Expired';
            timer.classList.add('text-danger');
            // 刷新页面以更新状态
            setTimeout(() => window.location.reload(), 2000);
            return;
        }

        textEl.textContent = formatTime(remaining);

        if (remaining <= 60) {
            timer.classList.remove('text-muted');
            timer.classList.add('text-danger');
        } else if (remaining <= 180) {
            timer.classList.remove('text-muted');
            timer.classList.add('text-warning');
        }

        remaining--;
        setTimeout(update, 1000);
    }

    update();
});

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

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
