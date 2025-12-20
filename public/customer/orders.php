<?php
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/auth_guard.php';
requireRole('Customer');
require_once __DIR__ . '/../../includes/header.php';

$customerId = $_SESSION['user_id'];

// [Phase 2 Fix] 使用视图替代直接表查询
// 视图: vw_customer_my_orders_list
$sql = "SELECT * FROM vw_customer_my_orders_list WHERE CustomerID = ? ORDER BY OrderDate DESC";
$orders = $pdo->prepare($sql);
$orders->execute([$customerId]);
$orders = $orders->fetchAll();
?>

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
                    <tr>
                        <td>#<?= $o['OrderID'] ?></td>
                        <td><?= formatDate($o['OrderDate']) ?></td>
                        <td>
                            <?php if($o['OrderType'] == 'InStore'): ?>
                                <span class="badge bg-info text-dark">Pick-up</span>
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
                        </td>
                        <td class="text-warning fw-bold"><?= formatPrice($o['TotalAmount']) ?></td>
                        <td>
                            <button class="btn btn-sm btn-outline-light" disabled>Details</button>
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

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>