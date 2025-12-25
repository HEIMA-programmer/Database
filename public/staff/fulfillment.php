<?php
/**
 * 【架构重构】订单履约页面
 * 表现层 - 仅负责数据展示和用户交互
 */
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/auth_guard.php';
require_once __DIR__ . '/../../includes/functions.php';
requireRole('Staff');

// ========== POST 请求处理 ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $orderId = $_POST['order_id'] ?? 0;
    $action = $_POST['action'] ?? '';

    if ($action === 'ship') {
        $result = handleShipOrder($pdo, $orderId);
    } elseif ($action === 'complete') {
        $result = handleDeliveryConfirmation($pdo, $orderId);
    } else {
        $result = ['success' => false, 'message' => 'Unknown action.'];
    }

    flash($result['message'], $result['success'] ? 'success' : 'danger');
    header("Location: fulfillment.php");
    exit();
}

// ========== 数据准备 ==========
$pageData = prepareFulfillmentPageData($pdo);
$paidOrders = $pageData['paid_orders'];
$shippedOrders = $pageData['shipped_orders'];

require_once __DIR__ . '/../../includes/header.php';
?>

<!-- ========== 表现层 ========== -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="text-warning"><i class="fa-solid fa-truck-fast me-2"></i>Online Order Fulfillment</h2>
    <div>
        <span class="badge bg-info me-2"><?= count($paidOrders) ?> Awaiting Shipment</span>
        <span class="badge bg-warning text-dark"><?= count($shippedOrders) ?> In Transit</span>
    </div>
</div>

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
                                <small class="text-muted"><?= h($o['CustomerEmail'] ?? '-') ?></small>
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
                                <small class="text-muted"><?= h($o['CustomerEmail'] ?? '-') ?></small>
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
