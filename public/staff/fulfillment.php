<?php
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/auth_guard.php';
requireRole('Staff');
require_once __DIR__ . '/../../includes/header.php';

// 处理发货动作
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ship_order'])) {
    $orderId = $_POST['order_id'];
    try {
        // 更新订单状态为 Completed (或 Shipped)
        // 只有 'Online' 类型的订单在这里处理
        $stmt = $pdo->prepare("UPDATE CustomerOrder SET OrderStatus = 'Completed' WHERE OrderID = ? AND OrderType = 'Online'");
        $stmt->execute([$orderId]);
        flash("Order #$orderId has been marked as shipped/completed.", 'success');
    } catch (PDOException $e) {
        flash("Error updating order: " . $e->getMessage(), 'danger');
    }
    header("Location: fulfillment.php");
    exit();
}

// 查询待发货订单 (Online Warehouse usually ID=3, but let's show all Online orders needing fulfillment)
// 假设 'Pending' 是未付款，'Paid' 是已付款待发货
$sql = "SELECT co.*, c.Name as CustomerName, c.Email,
               (SELECT COUNT(*) FROM OrderLine WHERE OrderID = co.OrderID) as ItemCount
        FROM CustomerOrder co
        JOIN Customer c ON co.CustomerID = c.CustomerID
        WHERE co.OrderType = 'Online' AND co.OrderStatus = 'Paid'
        ORDER BY co.OrderDate ASC";
$orders = $pdo->query($sql)->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="text-warning"><i class="fa-solid fa-truck-fast me-2"></i>Online Order Fulfillment</h2>
    <span class="badge bg-secondary text-info"><?= count($orders) ?> Orders Pending Shipment</span>
</div>

<div class="card bg-dark border-secondary">
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
                    <?php if (empty($orders)): ?>
                        <tr><td colspan="6" class="text-center py-4 text-muted">No orders waiting for shipment.</td></tr>
                    <?php else: ?>
                        <?php foreach ($orders as $o): ?>
                        <tr>
                            <td><span class="text-white fw-bold">#<?= $o['OrderID'] ?></span></td>
                            <td><?= $o['OrderDate'] ?></td>
                            <td>
                                <div><?= h($o['CustomerName']) ?></div>
                                <small class="text-muted"><?= h($o['Email']) ?></small>
                            </td>
                            <td><?= $o['ItemCount'] ?> items</td>
                            <td class="text-warning"><?= formatPrice($o['TotalAmount']) ?></td>
                            <td>
                                <form method="POST" onsubmit="return confirm('Confirm shipment for Order #<?= $o['OrderID'] ?>?');">
                                    <input type="hidden" name="order_id" value="<?= $o['OrderID'] ?>">
                                    <button type="submit" name="ship_order" class="btn btn-sm btn-outline-success">
                                        <i class="fa-solid fa-check me-1"></i> Ship Order
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