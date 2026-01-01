<?php
/**
 * 【架构重构】订单列表页面
 * 表现层 - 仅负责数据展示和用户交互
 * 【新增】支持15分钟支付倒计时显示
 */
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/auth_guard.php';
require_once __DIR__ . '/../../includes/functions.php';
requireRole('Customer');

$customerId = $_SESSION['user_id'];

// ========== 数据准备 ==========
$pageData = prepareOrdersPageData($pdo, $customerId);
$orders = $pageData['orders'];

// PAYMENT_TIMEOUT_MINUTES 常量已在 config/db_connect.php 中定义

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
                    <?php foreach ($orders as $o): ?>
                    <?php
                    // 计算剩余支付时间
                    $remainingSeconds = 0;
                    $isExpired = false;
                    if ($o['OrderStatus'] === 'Pending') {
                        $orderTime = strtotime($o['OrderDate']);
                        $expiryTime = $orderTime + (PAYMENT_TIMEOUT_MINUTES * 60);
                        $remainingSeconds = $expiryTime - time();
                        $isExpired = $remainingSeconds <= 0;
                    }
                    ?>
                    <tr data-order-id="<?= $o['OrderID'] ?>">
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
                            <?php if ($o['OrderStatus'] === 'Pending' && !$isExpired): ?>
                                <br>
                                <small class="countdown-timer text-danger" data-remaining="<?= $remainingSeconds ?>">
                                    <i class="fa-solid fa-clock me-1"></i>
                                    <span class="countdown-display"></span>
                                </small>
                            <?php elseif ($o['OrderStatus'] === 'Pending' && $isExpired): ?>
                                <br>
                                <small class="text-danger">
                                    <i class="fa-solid fa-exclamation-triangle me-1"></i>已超时
                                </small>
                            <?php endif; ?>
                        </td>
                        <td class="text-warning fw-bold"><?= formatPrice($o['TotalAmount']) ?></td>
                        <td>
                            <a href="order_detail.php?id=<?= $o['OrderID'] ?>" class="btn btn-sm btn-outline-warning">
                                <i class="fa-solid fa-eye me-1"></i>Details
                            </a>
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

<!-- 倒计时脚本 -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const timers = document.querySelectorAll('.countdown-timer');

    timers.forEach(function(timer) {
        let remaining = parseInt(timer.dataset.remaining);
        const display = timer.querySelector('.countdown-display');
        const orderId = timer.closest('tr').dataset.orderId;

        function updateDisplay() {
            if (remaining <= 0) {
                display.textContent = '已超时';
                // 自动取消订单
                cancelExpiredOrder(orderId);
                return;
            }

            const minutes = Math.floor(remaining / 60);
            const seconds = remaining % 60;
            display.textContent = `${minutes}:${seconds.toString().padStart(2, '0')} 剩余`;

            remaining--;
            setTimeout(updateDisplay, 1000);
        }

        updateDisplay();
    });

    function cancelExpiredOrder(orderId) {
        fetch('cancel_order.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            credentials: 'same-origin', // 确保发送session cookies
            body: 'order_id=' + orderId + '&auto_cancel=1'
        }).then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            }
        }).catch(error => console.error('Error:', error));
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
