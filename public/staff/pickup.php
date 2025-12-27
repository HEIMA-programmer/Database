<?php
/**
 * 【架构重构】店内取货页面
 * 表现层 - 仅负责数据展示和用户交互
 */
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/auth_guard.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_procedures.php';
requireRole(['Staff', 'Manager']);

$shopId = $_SESSION['shop_id'];

// ========== POST 请求处理 ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'])) {
    $orderId = (int)$_POST['order_id'];

    $result = handlePickupConfirmation($pdo, $orderId, $shopId);
    flash($result['message'], $result['success'] ? 'success' : 'danger');

    header("Location: pickup.php");
    exit();
}

// ========== 数据准备 ==========
$pageData = preparePickupPageData($pdo, $shopId);
$orders = $pageData['orders'];

// 【新增】获取历史取货记录
$pickupHistory = DBProcedures::getPickupHistory($pdo, $shopId, 10);

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/staff_nav.php';
?>

<!-- ========== 表现层 ========== -->
<h2 class="text-warning mb-4"><i class="fa-solid fa-box-open me-2"></i>In-Store Pickup Queue</h2>

<?php if (empty($orders)): ?>
    <div class="alert alert-success text-center py-5">
        <h4>No pending pickups.</h4>
        <p>All online orders for this location have been fulfilled.</p>
    </div>
<?php else: ?>
    <div class="row">
        <?php foreach ($orders as $o): ?>
        <div class="col-md-6 mb-3">
            <div class="card bg-secondary text-light border-start border-4 border-warning">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <h5 class="card-title">Order #<?= $o['OrderID'] ?></h5>
                        <span class="badge bg-info text-dark">Ready for Pickup</span>
                    </div>
                    <p class="card-text mb-1">Customer: <strong><?= h($o['CustomerName']) ?></strong></p>
                    <p class="card-text text-light-50 small">Date: <?= formatDate($o['OrderDate']) ?></p>

                    <form method="POST" class="mt-3">
                        <input type="hidden" name="order_id" value="<?= $o['OrderID'] ?>">
                        <button type="submit" class="btn btn-warning w-100 fw-bold">
                            Mark as Collected
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- 【新增】历史取货记录 -->
<div class="mt-5">
    <h4 class="text-warning mb-3">
        <i class="fa-solid fa-history me-2"></i>Pickup History
    </h4>

    <?php if (empty($pickupHistory)): ?>
        <div class="alert alert-secondary text-center">
            <i class="fa-solid fa-inbox fa-2x mb-2 d-block opacity-50"></i>
            No pickup history yet.
        </div>
    <?php else: ?>
        <div class="card bg-dark border-secondary">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-dark table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Order #</th>
                                <th>Date</th>
                                <th>Customer</th>
                                <th>Items</th>
                                <th>Total</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pickupHistory as $order): ?>
                            <tr>
                                <td>#<?= $order['OrderID'] ?></td>
                                <td><?= formatDate($order['OrderDate']) ?></td>
                                <td>
                                    <div><?= h($order['CustomerName']) ?></div>
                                    <small class="text-muted"><?= h($order['CustomerEmail'] ?? '') ?></small>
                                </td>
                                <td><span class="badge bg-secondary"><?= $order['ItemCount'] ?> items</span></td>
                                <td class="text-warning fw-bold"><?= formatPrice($order['TotalAmount']) ?></td>
                                <td><span class="badge bg-success">Completed</span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
