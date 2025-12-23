<?php
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/auth_guard.php';
requireRole('Customer');
require_once __DIR__ . '/../../includes/header.php';

$customerId = $_SESSION['user_id'];
$orderId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$orderId) {
    flash("Invalid order ID.", 'danger');
    header("Location: orders.php");
    exit();
}

// 获取订单头信息
$orderSql = "SELECT co.*, s.Name as ShopName
             FROM CustomerOrder co
             LEFT JOIN Shop s ON co.FulfilledByShopID = s.ShopID
             WHERE co.OrderID = ? AND co.CustomerID = ?";
$stmt = $pdo->prepare($orderSql);
$stmt->execute([$orderId, $customerId]);
$order = $stmt->fetch();

if (!$order) {
    flash("Order not found or access denied.", 'danger');
    header("Location: orders.php");
    exit();
}

// 获取订单明细 (使用视图)
$detailSql = "SELECT * FROM vw_customer_order_history WHERE OrderID = ? AND CustomerID = ?";
$stmt = $pdo->prepare($detailSql);
$stmt->execute([$orderId, $customerId]);
$items = $stmt->fetchAll();

// 状态样式映射
$statusClass = match($order['OrderStatus']) {
    'Paid' => 'bg-success',
    'Completed' => 'bg-success',
    'Shipped' => 'bg-info',
    'Pending' => 'bg-warning text-dark',
    'Cancelled' => 'bg-danger',
    default => 'bg-secondary'
};
?>

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
                        <?php if($order['OrderType'] == 'InStore'): ?>
                            <span class="badge bg-info text-dark">In-Store Pick-up</span>
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
        <div class="card bg-warning text-dark border-0">
            <div class="card-body">
                <h6 class="mb-2"><i class="fa-solid fa-clock me-2"></i>Payment Required</h6>
                <p class="small mb-3">Please complete your payment to process this order.</p>
                <a href="pay.php?order_id=<?= $order['OrderID'] ?>" class="btn btn-dark btn-sm">
                    <i class="fa-solid fa-credit-card me-1"></i>Pay Now
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
