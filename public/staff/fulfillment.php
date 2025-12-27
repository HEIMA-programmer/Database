<?php
/**
 * 员工订单履行页面
 * 
 * 【修复】按员工所属店铺过滤订单：
 * - 每个员工只能看到和处理自己店铺的订单
 * - 仓库员工处理仓库订单，门店员工处理门店订单
 */
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/auth_guard.php';
require_once __DIR__ . '/../../includes/functions.php';
requireRole('Staff');

// 获取当前员工信息
$employeeId = $_SESSION['user']['EmployeeID'];
$stmt = $pdo->prepare("
    SELECT e.*, s.Name as ShopName, s.Type as ShopType
    FROM Employee e
    JOIN Shop s ON e.ShopID = s.ShopID
    WHERE e.EmployeeID = ?
");
$stmt->execute([$employeeId]);
$employee = $stmt->fetch(PDO::FETCH_ASSOC);
$shopId = $employee['ShopID'];
$shopType = $employee['ShopType'];

// 处理订单操作
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $orderId = (int)($_POST['order_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    
    // 验证订单属于本店铺
    $stmt = $pdo->prepare("SELECT ShopID, OrderStatus FROM CustomerOrder WHERE OrderID = ?");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($order && $order['ShopID'] == $shopId) {
        try {
            switch ($action) {
                case 'mark_paid':
                    if ($order['OrderStatus'] == 'Pending') {
                        $stmt = $pdo->prepare("UPDATE CustomerOrder SET OrderStatus = 'Paid' WHERE OrderID = ?");
                        $stmt->execute([$orderId]);
                        flash("Order #$orderId marked as Paid.", 'success');
                    }
                    break;
                    
                case 'ship':
                    if (in_array($order['OrderStatus'], ['Pending', 'Paid'])) {
                        $stmt = $pdo->prepare("UPDATE CustomerOrder SET OrderStatus = 'Shipped' WHERE OrderID = ?");
                        $stmt->execute([$orderId]);
                        flash("Order #$orderId marked as Shipped.", 'success');
                    }
                    break;
                    
                case 'ready_pickup':
                    if (in_array($order['OrderStatus'], ['Pending', 'Paid'])) {
                        $stmt = $pdo->prepare("UPDATE CustomerOrder SET OrderStatus = 'ReadyForPickup' WHERE OrderID = ?");
                        $stmt->execute([$orderId]);
                        flash("Order #$orderId is ready for pickup.", 'success');
                    }
                    break;
                    
                case 'complete':
                    DBProcedures::completeOrder($pdo, $orderId);
                    flash("Order #$orderId completed successfully.", 'success');
                    break;
                    
                case 'cancel':
                    if (!in_array($order['OrderStatus'], ['Completed', 'Cancelled'])) {
                        $pdo->beginTransaction();
                        // 释放库存
                        $stmt = $pdo->prepare("
                            UPDATE StockItem si
                            JOIN OrderLine ol ON si.StockItemID = ol.StockItemID
                            SET si.Status = 'Available'
                            WHERE ol.OrderID = ? AND si.Status = 'Reserved'
                        ");
                        $stmt->execute([$orderId]);
                        // 取消订单
                        $stmt = $pdo->prepare("UPDATE CustomerOrder SET OrderStatus = 'Cancelled' WHERE OrderID = ?");
                        $stmt->execute([$orderId]);
                        $pdo->commit();
                        flash("Order #$orderId cancelled.", 'info');
                    }
                    break;
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            flash("Error: " . $e->getMessage(), 'danger');
        }
    } else {
        flash("You can only manage orders from your own store.", 'danger');
    }
    
    header('Location: fulfillment.php');
    exit;
}

// 获取本店铺待处理订单
$statusFilter = $_GET['status'] ?? 'pending';
$statusCondition = match($statusFilter) {
    'pending' => "AND co.OrderStatus IN ('Pending', 'Paid')",
    'shipping' => "AND co.OrderStatus = 'Shipped'",
    'pickup' => "AND co.OrderStatus = 'ReadyForPickup'",
    'completed' => "AND co.OrderStatus = 'Completed'",
    'cancelled' => "AND co.OrderStatus = 'Cancelled'",
    default => ""
};

$stmt = $pdo->prepare("
    SELECT co.*, c.Name as CustomerName, c.Email as CustomerEmail,
           COUNT(ol.OrderLineID) as ItemCount,
           (SELECT GROUP_CONCAT(DISTINCT r.Title SEPARATOR ', ') 
            FROM OrderLine ol2 
            JOIN StockItem si ON ol2.StockItemID = si.StockItemID
            JOIN `Release` r ON si.ReleaseID = r.ReleaseID
            WHERE ol2.OrderID = co.OrderID
            LIMIT 3) as ItemTitles
    FROM CustomerOrder co
    JOIN Customer c ON co.CustomerID = c.CustomerID
    LEFT JOIN OrderLine ol ON co.OrderID = ol.OrderID
    WHERE co.ShopID = ? $statusCondition
    GROUP BY co.OrderID
    ORDER BY co.OrderDate DESC
");
$stmt->execute([$shopId]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 统计各状态数量
$stmt = $pdo->prepare("
    SELECT OrderStatus, COUNT(*) as cnt
    FROM CustomerOrder
    WHERE ShopID = ?
    GROUP BY OrderStatus
");
$stmt->execute([$shopId]);
$statusCounts = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $statusCounts[$row['OrderStatus']] = $row['cnt'];
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row mb-4">
    <div class="col">
        <h1 class="display-5 text-warning fw-bold">
            <i class="fa-solid fa-boxes-stacked me-2"></i>Order Fulfillment
        </h1>
        <p class="text-muted">
            <i class="fa-solid <?= $shopType == 'Warehouse' ? 'fa-warehouse' : 'fa-store' ?> me-1"></i>
            <?= h($employee['ShopName']) ?>
        </p>
    </div>
</div>

<!-- 状态过滤器 -->
<div class="card bg-dark border-secondary mb-4">
    <div class="card-body py-2">
        <div class="d-flex gap-2 flex-wrap">
            <a href="?status=pending" class="btn btn-sm <?= $statusFilter == 'pending' ? 'btn-warning' : 'btn-outline-warning' ?>">
                <i class="fa-solid fa-clock me-1"></i>Pending
                <?php if (($statusCounts['Pending'] ?? 0) + ($statusCounts['Paid'] ?? 0) > 0): ?>
                    <span class="badge bg-dark"><?= ($statusCounts['Pending'] ?? 0) + ($statusCounts['Paid'] ?? 0) ?></span>
                <?php endif; ?>
            </a>
            <?php if ($shopType != 'Warehouse'): ?>
            <a href="?status=pickup" class="btn btn-sm <?= $statusFilter == 'pickup' ? 'btn-info' : 'btn-outline-info' ?>">
                <i class="fa-solid fa-store me-1"></i>Ready for Pickup
                <?php if (($statusCounts['ReadyForPickup'] ?? 0) > 0): ?>
                    <span class="badge bg-dark"><?= $statusCounts['ReadyForPickup'] ?></span>
                <?php endif; ?>
            </a>
            <?php endif; ?>
            <a href="?status=shipping" class="btn btn-sm <?= $statusFilter == 'shipping' ? 'btn-primary' : 'btn-outline-primary' ?>">
                <i class="fa-solid fa-truck me-1"></i>Shipped
                <?php if (($statusCounts['Shipped'] ?? 0) > 0): ?>
                    <span class="badge bg-dark"><?= $statusCounts['Shipped'] ?></span>
                <?php endif; ?>
            </a>
            <a href="?status=completed" class="btn btn-sm <?= $statusFilter == 'completed' ? 'btn-success' : 'btn-outline-success' ?>">
                <i class="fa-solid fa-check me-1"></i>Completed
            </a>
            <a href="?status=cancelled" class="btn btn-sm <?= $statusFilter == 'cancelled' ? 'btn-secondary' : 'btn-outline-secondary' ?>">
                <i class="fa-solid fa-ban me-1"></i>Cancelled
            </a>
            <a href="?status=all" class="btn btn-sm <?= $statusFilter == 'all' ? 'btn-light' : 'btn-outline-light' ?>">
                All Orders
            </a>
        </div>
    </div>
</div>

<?php if (empty($orders)): ?>
    <div class="text-center py-5">
        <div class="display-1 text-secondary mb-3"><i class="fa-solid fa-inbox"></i></div>
        <h3 class="text-white">No orders found</h3>
        <p class="text-muted">No orders matching the selected filter.</p>
    </div>
<?php else: ?>
    <div class="row row-cols-1 row-cols-lg-2 g-4">
        <?php foreach ($orders as $order): ?>
            <?php
            $statusBadge = match($order['OrderStatus']) {
                'Pending' => 'bg-secondary',
                'Paid' => 'bg-info text-dark',
                'Shipped' => 'bg-primary',
                'ReadyForPickup' => 'bg-info',
                'Completed' => 'bg-success',
                'Cancelled' => 'bg-danger',
                default => 'bg-secondary'
            };
            $fulfillmentIcon = $order['FulfillmentType'] == 'Pickup' ? 'fa-store' : 'fa-truck';
            ?>
            <div class="col">
                <div class="card bg-dark border-secondary h-100">
                    <div class="card-header bg-dark border-secondary d-flex justify-content-between align-items-center">
                        <div>
                            <strong class="text-warning">Order #<?= $order['OrderID'] ?></strong>
                            <span class="badge <?= $statusBadge ?> ms-2"><?= h($order['OrderStatus']) ?></span>
                        </div>
                        <small class="text-muted"><?= date('M d, H:i', strtotime($order['OrderDate'])) ?></small>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-6">
                                <small class="text-muted">Customer</small>
                                <div class="text-white"><?= h($order['CustomerName']) ?></div>
                                <small class="text-muted"><?= h($order['CustomerEmail']) ?></small>
                            </div>
                            <div class="col-6 text-end">
                                <small class="text-muted">Total</small>
                                <div class="text-warning fs-5 fw-bold"><?= formatPrice($order['TotalAmount']) ?></div>
                                <small class="text-muted"><?= $order['ItemCount'] ?> item(s)</small>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <small class="text-muted d-block">Fulfillment</small>
                            <span class="badge bg-dark border border-secondary">
                                <i class="fa-solid <?= $fulfillmentIcon ?> me-1"></i>
                                <?= h($order['FulfillmentType']) ?>
                            </span>
                            <?php if ($order['FulfillmentType'] == 'Shipping' && $order['ShippingAddress']): ?>
                                <div class="text-muted small mt-1">
                                    <i class="fa-solid fa-location-dot me-1"></i><?= h($order['ShippingAddress']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-3">
                            <small class="text-muted d-block">Items</small>
                            <small class="text-white"><?= h($order['ItemTitles']) ?></small>
                        </div>
                    </div>
                    
                    <?php if (!in_array($order['OrderStatus'], ['Completed', 'Cancelled'])): ?>
                    <div class="card-footer bg-dark border-secondary">
                        <div class="d-flex gap-2 flex-wrap">
                            <?php if ($order['OrderStatus'] == 'Pending'): ?>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="order_id" value="<?= $order['OrderID'] ?>">
                                    <input type="hidden" name="action" value="mark_paid">
                                    <button type="submit" class="btn btn-info btn-sm">
                                        <i class="fa-solid fa-credit-card me-1"></i>Mark Paid
                                    </button>
                                </form>
                            <?php endif; ?>
                            
                            <?php if ($order['FulfillmentType'] == 'Shipping' && in_array($order['OrderStatus'], ['Pending', 'Paid'])): ?>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="order_id" value="<?= $order['OrderID'] ?>">
                                    <input type="hidden" name="action" value="ship">
                                    <button type="submit" class="btn btn-primary btn-sm">
                                        <i class="fa-solid fa-truck me-1"></i>Ship
                                    </button>
                                </form>
                            <?php endif; ?>
                            
                            <?php if ($order['FulfillmentType'] == 'Pickup' && in_array($order['OrderStatus'], ['Pending', 'Paid'])): ?>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="order_id" value="<?= $order['OrderID'] ?>">
                                    <input type="hidden" name="action" value="ready_pickup">
                                    <button type="submit" class="btn btn-info btn-sm">
                                        <i class="fa-solid fa-store me-1"></i>Ready for Pickup
                                    </button>
                                </form>
                            <?php endif; ?>
                            
                            <?php if (in_array($order['OrderStatus'], ['Shipped', 'ReadyForPickup'])): ?>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="order_id" value="<?= $order['OrderID'] ?>">
                                    <input type="hidden" name="action" value="complete">
                                    <button type="submit" class="btn btn-success btn-sm">
                                        <i class="fa-solid fa-check me-1"></i>Complete
                                    </button>
                                </form>
                            <?php endif; ?>
                            
                            <form method="POST" class="d-inline ms-auto">
                                <input type="hidden" name="order_id" value="<?= $order['OrderID'] ?>">
                                <input type="hidden" name="action" value="cancel">
                                <button type="submit" class="btn btn-outline-danger btn-sm" 
                                        onclick="return confirm('Cancel this order?')">
                                    <i class="fa-solid fa-ban me-1"></i>Cancel
                                </button>
                            </form>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>