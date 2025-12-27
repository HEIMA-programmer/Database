<?php
/**
 * 【架构重构】店内取货页面
 * 表现层 - 仅负责数据展示和用户交互
 * 【新增】添加历史取货记录显示
 */
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/auth_guard.php';
require_once __DIR__ . '/../../includes/functions.php';
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

// 【新增】获取已完成的取货记录
$stmt = $pdo->prepare("
    SELECT co.OrderID, co.OrderDate, co.TotalAmount, co.OrderStatus,
           c.Name as CustomerName, c.Email as CustomerEmail,
           COUNT(ol.StockItemID) as ItemCount
    FROM CustomerOrder co
    LEFT JOIN Customer c ON co.CustomerID = c.CustomerID
    LEFT JOIN OrderLine ol ON co.OrderID = ol.OrderID
    WHERE co.FulfilledByShopID = ? AND co.FulfillmentType = 'Pickup'
    AND co.OrderStatus = 'Completed'
    GROUP BY co.OrderID
    ORDER BY co.OrderDate DESC
    LIMIT 20
");
$stmt->execute([$shopId]);
$pickupHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 统计今日取货数量
$stmt = $pdo->prepare("
    SELECT COUNT(*) as TodayCount, COALESCE(SUM(TotalAmount), 0) as TodayTotal
    FROM CustomerOrder
    WHERE FulfilledByShopID = ? AND FulfillmentType = 'Pickup'
    AND DATE(OrderDate) = CURDATE() AND OrderStatus = 'Completed'
");
$stmt->execute([$shopId]);
$todayPickupStats = $stmt->fetch(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../../includes/header.php';
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

<!-- 【新增】今日统计和历史取货记录 -->
<div class="mt-5">
    <!-- 今日统计 -->
    <div class="card bg-dark border-success mb-4">
        <div class="card-body py-3">
            <div class="row text-center">
                <div class="col-md-6">
                    <div class="text-muted small">Today's Pickups</div>
                    <div class="text-success fs-3 fw-bold"><?= (int)$todayPickupStats['TodayCount'] ?></div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small">Today's Pickup Value</div>
                    <div class="text-warning fs-3 fw-bold"><?= formatPrice($todayPickupStats['TodayTotal']) ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- 历史取货记录 -->
    <div class="card bg-dark border-secondary">
        <div class="card-header bg-dark border-secondary">
            <h5 class="mb-0 text-warning"><i class="fa-solid fa-history me-2"></i>Recent Pickup History</h5>
        </div>
        <div class="card-body p-0">
            <?php if (empty($pickupHistory)): ?>
                <div class="p-4 text-center text-muted">No pickup history yet.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-dark table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Order #</th>
                                <th>Date/Time</th>
                                <th>Customer</th>
                                <th>Items</th>
                                <th>Total</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pickupHistory as $h): ?>
                            <tr>
                                <td class="text-warning">#<?= $h['OrderID'] ?></td>
                                <td><?= date('M d, H:i', strtotime($h['OrderDate'])) ?></td>
                                <td>
                                    <div><?= h($h['CustomerName'] ?: 'Unknown') ?></div>
                                    <small class="text-muted"><?= h($h['CustomerEmail'] ?: '') ?></small>
                                </td>
                                <td><span class="badge bg-secondary"><?= $h['ItemCount'] ?></span></td>
                                <td class="text-warning fw-bold"><?= formatPrice($h['TotalAmount']) ?></td>
                                <td><span class="badge bg-success">Completed</span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
