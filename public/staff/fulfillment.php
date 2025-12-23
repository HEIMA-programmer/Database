<?php
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/auth_guard.php';
requireRole('Staff');
require_once __DIR__ . '/../../includes/header.php';

// 处理发货动作
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $orderId = $_POST['order_id'] ?? 0;
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'ship') {
            // 标记为已发货
            $stmt = $pdo->prepare("UPDATE CustomerOrder SET OrderStatus = 'Shipped' WHERE OrderID = ? AND OrderType = 'Online' AND OrderStatus = 'Paid'");
            $stmt->execute([$orderId]);
            flash("Order #$orderId has been shipped!", 'success');
        } elseif ($action === 'complete') {
            // 标记为完成（已送达）
            $stmt = $pdo->prepare("UPDATE CustomerOrder SET OrderStatus = 'Completed' WHERE OrderID = ? AND OrderType = 'Online' AND OrderStatus = 'Shipped'");
            $stmt->execute([$orderId]);
            flash("Order #$orderId delivery confirmed!", 'success');
        }
    } catch (PDOException $e) {
        flash("Error updating order: " . $e->getMessage(), 'danger');
    }
    header("Location: fulfillment.php");
    exit();
}

// 查询待发货订单 (Paid) 和待确认订单 (Shipped)
$paidSql = "SELECT co.*, c.Name as CustomerName, c.Email,
               (SELECT COUNT(*) FROM OrderLine WHERE OrderID = co.OrderID) as ItemCount
        FROM CustomerOrder co
        LEFT JOIN Customer c ON co.CustomerID = c.CustomerID
        WHERE co.OrderType = 'Online' AND co.OrderStatus = 'Paid'
        ORDER BY co.OrderDate ASC";
$paidOrders = $pdo->query($paidSql)->fetchAll();

$shippedSql = "SELECT co.*, c.Name as CustomerName, c.Email,
               (SELECT COUNT(*) FROM OrderLine WHERE OrderID = co.OrderID) as ItemCount
        FROM CustomerOrder co
        LEFT JOIN Customer c ON co.CustomerID = c.CustomerID
        WHERE co.OrderType = 'Online' AND co.OrderStatus = 'Shipped'
        ORDER BY co.OrderDate ASC";
$shippedOrders = $pdo->query($shippedSql)->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="text-warning"><i class="fa-solid fa-truck-fast me-2"></i>Online Order Fulfillment</h2>
    <div>
        <span class="badge bg-info me-2"><?= count($paidOrders) ?> Awaiting Shipment</span>
        <span class="badge bg-warning text-dark"><?= count($shippedOrders) ?> In Transit</span>
    </div>
</div>

<!-- 待发货订单 -->
<div class="card bg-dark border-info mb-4">
    <div class="card-header border-info bg-transparent">
        <h5 class="mb-0 text-info"><i class="fa-solid fa-box me-2"></i>Awaiting Shipment (Paid)</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-dark table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Date</th>
                        <th>Customer</th>
                        <th>Items</th>
                        <th>Total</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($paidOrders)): ?>
                        <tr><td colspan="6" class="text-center py-4 text-muted">No orders waiting for shipment.</td></tr>
                    <?php else: ?>
                        <?php foreach ($paidOrders as $o): ?>
                        <tr>
                            <td><span class="text-white fw-bold">#<?= $o['OrderID'] ?></span></td>
                            <td><?= formatDate($o['OrderDate']) ?></td>
                            <td>
                                <div><?= h($o['CustomerName'] ?? 'Guest') ?></div>
                                <small class="text-muted"><?= h($o['Email'] ?? '-') ?></small>
                            </td>
                            <td><?= $o['ItemCount'] ?> items</td>
                            <td class="text-warning"><?= formatPrice($o['TotalAmount']) ?></td>
                            <td>
                                <form method="POST" onsubmit="return confirm('Ship Order #<?= $o['OrderID'] ?>?');">
                                    <input type="hidden" name="order_id" value="<?= $o['OrderID'] ?>">
                                    <input type="hidden" name="action" value="ship">
                                    <button type="submit" class="btn btn-sm btn-outline-info">
                                        <i class="fa-solid fa-truck me-1"></i> Ship
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- 已发货待确认订单 -->
<div class="card bg-dark border-warning">
    <div class="card-header border-warning bg-transparent">
        <h5 class="mb-0 text-warning"><i class="fa-solid fa-truck-loading me-2"></i>In Transit (Shipped)</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-dark table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Date</th>
                        <th>Customer</th>
                        <th>Items</th>
                        <th>Total</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($shippedOrders)): ?>
                        <tr><td colspan="6" class="text-center py-4 text-muted">No orders in transit.</td></tr>
                    <?php else: ?>
                        <?php foreach ($shippedOrders as $o): ?>
                        <tr>
                            <td><span class="text-white fw-bold">#<?= $o['OrderID'] ?></span></td>
                            <td><?= formatDate($o['OrderDate']) ?></td>
                            <td>
                                <div><?= h($o['CustomerName'] ?? 'Guest') ?></div>
                                <small class="text-muted"><?= h($o['Email'] ?? '-') ?></small>
                            </td>
                            <td><?= $o['ItemCount'] ?> items</td>
                            <td class="text-warning"><?= formatPrice($o['TotalAmount']) ?></td>
                            <td>
                                <form method="POST" onsubmit="return confirm('Confirm delivery for Order #<?= $o['OrderID'] ?>?');">
                                    <input type="hidden" name="order_id" value="<?= $o['OrderID'] ?>">
                                    <input type="hidden" name="action" value="complete">
                                    <button type="submit" class="btn btn-sm btn-outline-success">
                                        <i class="fa-solid fa-check-double me-1"></i> Delivered
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>