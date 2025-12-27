<?php
/**
 * 客户订单历史明细 - Manager查看
 * 显示特定客户在当前店铺的所有订单，区分订单类型
 */
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/auth_guard.php';
requireRole('Manager');
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_procedures.php';

$shopId = $_SESSION['user']['ShopID'] ?? null;
$shopType = $_SESSION['user']['ShopType'] ?? 'Retail';
$isWarehouse = ($shopType === 'Warehouse');
$customerId = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : 0;

if (!$shopId || !$customerId) {
    header('Location: dashboard.php');
    exit;
}

// 获取客户在本店的订单历史
$orders = DBProcedures::getCustomerShopOrders($pdo, $customerId, $shopId);
$buybacks = [];
if (!$isWarehouse) {
    $buybacks = DBProcedures::getCustomerBuybackHistory($pdo, $customerId, $shopId);
}

// 获取客户信息
$customerName = !empty($orders) ? $orders[0]['CustomerName'] : 'Unknown Customer';

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard.php" class="text-warning">Dashboard</a></li>
                <li class="breadcrumb-item active text-light">Customer Orders</li>
            </ol>
        </nav>
        <h2 class="text-warning display-6 fw-bold">
            <i class="fa-solid fa-user me-2"></i><?= h($customerName) ?>
        </h2>
        <p class="text-secondary">Order history at <?= h($_SESSION['shop_name'] ?? 'this store') ?></p>
    </div>
</div>

<!-- 订单类型标签页 -->
<ul class="nav nav-tabs mb-4" id="orderTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="all-tab" data-bs-toggle="tab" data-bs-target="#all" type="button">
            All Orders <span class="badge bg-secondary"><?= count($orders) ?></span>
        </button>
    </li>
    <?php if (!$isWarehouse): ?>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="pos-tab" data-bs-toggle="tab" data-bs-target="#pos" type="button">
            <i class="fa-solid fa-cash-register me-1"></i>POS
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="pickup-tab" data-bs-toggle="tab" data-bs-target="#pickup" type="button">
            <i class="fa-solid fa-store me-1"></i>Online Pickup
        </button>
    </li>
    <?php endif; ?>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="online-tab" data-bs-toggle="tab" data-bs-target="#online" type="button">
            <i class="fa-solid fa-globe me-1"></i>Online Shipping
        </button>
    </li>
    <?php if (!$isWarehouse): ?>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="buyback-tab" data-bs-toggle="tab" data-bs-target="#buyback" type="button">
            <i class="fa-solid fa-recycle me-1 text-danger"></i>Buyback
            <span class="badge bg-danger"><?= count($buybacks) ?></span>
        </button>
    </li>
    <?php endif; ?>
</ul>

<div class="tab-content" id="orderTabsContent">
    <!-- All Orders -->
    <div class="tab-pane fade show active" id="all" role="tabpanel">
        <?php if (empty($orders)): ?>
            <div class="alert alert-info">No orders found for this customer at this store.</div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-dark table-striped">
                <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th class="text-end">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $o): ?>
                    <tr>
                        <td><span class="badge bg-info">#<?= $o['OrderID'] ?></span></td>
                        <td><?= formatDate($o['OrderDate']) ?></td>
                        <td>
                            <?php
                            $category = $o['OrderCategory'];
                            $badges = [
                                'POS' => '<span class="badge bg-warning text-dark"><i class="fa-solid fa-cash-register me-1"></i>POS</span>',
                                'OnlinePickup' => '<span class="badge bg-info"><i class="fa-solid fa-store me-1"></i>Pickup</span>',
                                'OnlineSales' => '<span class="badge bg-success"><i class="fa-solid fa-globe me-1"></i>Shipping</span>'
                            ];
                            echo $badges[$category] ?? '<span class="badge bg-secondary">Other</span>';
                            ?>
                        </td>
                        <td>
                            <?php
                            $statusColors = [
                                'Completed' => 'success',
                                'Paid' => 'info',
                                'Pending' => 'warning',
                                'Cancelled' => 'danger'
                            ];
                            $color = $statusColors[$o['OrderStatus']] ?? 'secondary';
                            ?>
                            <span class="badge bg-<?= $color ?>"><?= $o['OrderStatus'] ?></span>
                        </td>
                        <td class="text-end text-success fw-bold"><?= formatPrice($o['TotalAmount']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- POS Orders -->
    <?php if (!$isWarehouse): ?>
    <div class="tab-pane fade" id="pos" role="tabpanel">
        <?php
        $posOrders = array_filter($orders, fn($o) => $o['OrderCategory'] === 'POS');
        if (empty($posOrders)): ?>
            <div class="alert alert-info">No POS orders found.</div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-dark table-striped">
                <thead>
                    <tr><th>Order #</th><th>Date</th><th>Status</th><th class="text-end">Amount</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($posOrders as $o): ?>
                    <tr>
                        <td><span class="badge bg-warning text-dark">#<?= $o['OrderID'] ?></span></td>
                        <td><?= formatDate($o['OrderDate']) ?></td>
                        <td><span class="badge bg-<?= $statusColors[$o['OrderStatus']] ?? 'secondary' ?>"><?= $o['OrderStatus'] ?></span></td>
                        <td class="text-end text-success fw-bold"><?= formatPrice($o['TotalAmount']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- Online Pickup -->
    <div class="tab-pane fade" id="pickup" role="tabpanel">
        <?php
        $pickupOrders = array_filter($orders, fn($o) => $o['OrderCategory'] === 'OnlinePickup');
        if (empty($pickupOrders)): ?>
            <div class="alert alert-info">No pickup orders found.</div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-dark table-striped">
                <thead>
                    <tr><th>Order #</th><th>Date</th><th>Status</th><th class="text-end">Amount</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($pickupOrders as $o): ?>
                    <tr>
                        <td><span class="badge bg-info">#<?= $o['OrderID'] ?></span></td>
                        <td><?= formatDate($o['OrderDate']) ?></td>
                        <td><span class="badge bg-<?= $statusColors[$o['OrderStatus']] ?? 'secondary' ?>"><?= $o['OrderStatus'] ?></span></td>
                        <td class="text-end text-success fw-bold"><?= formatPrice($o['TotalAmount']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Online Shipping -->
    <div class="tab-pane fade" id="online" role="tabpanel">
        <?php
        $onlineOrders = array_filter($orders, fn($o) => $o['OrderCategory'] === 'OnlineSales');
        if (empty($onlineOrders)): ?>
            <div class="alert alert-info">No online shipping orders found.</div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-dark table-striped">
                <thead>
                    <tr><th>Order #</th><th>Date</th><th>Status</th><th class="text-end">Shipping</th><th class="text-end">Total</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($onlineOrders as $o): ?>
                    <tr>
                        <td><span class="badge bg-success">#<?= $o['OrderID'] ?></span></td>
                        <td><?= formatDate($o['OrderDate']) ?></td>
                        <td><span class="badge bg-<?= $statusColors[$o['OrderStatus']] ?? 'secondary' ?>"><?= $o['OrderStatus'] ?></span></td>
                        <td class="text-end"><?= formatPrice($o['ShippingCost'] ?? 0) ?></td>
                        <td class="text-end text-success fw-bold"><?= formatPrice($o['TotalAmount']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- Buyback -->
    <?php if (!$isWarehouse): ?>
    <div class="tab-pane fade" id="buyback" role="tabpanel">
        <?php if (empty($buybacks)): ?>
            <div class="alert alert-info">No buyback records found.</div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-dark table-striped">
                <thead>
                    <tr>
                        <th>Buyback #</th>
                        <th>Date</th>
                        <th>Album</th>
                        <th>Condition</th>
                        <th class="text-center">Qty</th>
                        <th class="text-end">Payment</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($buybacks as $b): ?>
                    <tr>
                        <td><span class="badge bg-danger">#<?= $b['BuybackOrderID'] ?></span></td>
                        <td><?= formatDate($b['BuybackDate']) ?></td>
                        <td>
                            <div><?= h($b['Title']) ?></div>
                            <small class="text-muted"><?= h($b['ArtistName']) ?></small>
                        </td>
                        <td><span class="badge bg-secondary"><?= h($b['ConditionGrade']) ?></span></td>
                        <td class="text-center"><?= $b['Quantity'] ?></td>
                        <td class="text-end text-danger fw-bold"><?= formatPrice($b['TotalPayment']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<div class="mt-4">
    <a href="dashboard.php" class="btn btn-outline-secondary">
        <i class="fa-solid fa-arrow-left me-1"></i>Back to Dashboard
    </a>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
