<?php
/**
 * 订单明细 - Manager查看
 * 按订单类型显示所有历史订单的详细明细
 */
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/auth_guard.php';
requireRole('Manager');
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_procedures.php';

$shopId = $_SESSION['user']['ShopID'] ?? null;
$shopType = $_SESSION['user']['ShopType'] ?? 'Retail';
$isWarehouse = ($shopType === 'Warehouse');
$type = $_GET['type'] ?? 'all';


if (!$shopId) {
    header('Location: dashboard.php');
    exit;
}

// 根据类型获取订单数据
$orders = [];
$buybacks = [];
$title = '';
$icon = '';
$color = '';

switch ($type) {
    case 'OnlineSales':
        $orders = DBProcedures::getShopOrderDetails($pdo, $shopId, 'OnlineSales');
        $title = 'Online Sales (Shipping)';
        $icon = 'fa-globe';
        $color = 'success';
        break;
    case 'OnlinePickup':
        $orders = DBProcedures::getShopOrderDetails($pdo, $shopId, 'OnlinePickup');
        $title = 'Online Pickup Orders';
        $icon = 'fa-store';
        $color = 'info';
        break;
    case 'POS':

        $orders = DBProcedures::getShopOrderDetails($pdo, $shopId, 'POS');
        $title = 'POS In-Store Sales';
        $icon = 'fa-cash-register';
        $color = 'warning';
        break;
    case 'buyback':
        $buybacks = DBProcedures::getShopBuybackDetails($pdo, $shopId);
        $title = 'Buyback Expense Details';
        $icon = 'fa-money-bill-transfer';
        $color = 'danger';
        break;
    default:
        $orders = DBProcedures::getShopOrderDetails($pdo, $shopId);
        $title = 'All Orders';
        $icon = 'fa-list';
        $color = 'secondary';
}

// 按订单分组
$groupedOrders = [];
foreach ($orders as $item) {
    $orderId = $item['OrderID'];
    if (!isset($groupedOrders[$orderId])) {
        $groupedOrders[$orderId] = [
            'OrderID' => $orderId,
            'CustomerName' => $item['CustomerName'],
            'OrderDate' => $item['OrderDate'],
            'TotalAmount' => $item['TotalAmount'],
            'ShippingCost' => $item['ShippingCost'],
            'OrderStatus' => $item['OrderStatus'],
            'OrderType' => $item['OrderType'],
            'FulfillmentType' => $item['FulfillmentType'],
            'items' => []
        ];
    }
    $groupedOrders[$orderId]['items'][] = [
        'Title' => $item['Title'],
        'ArtistName' => $item['ArtistName'],
        'ConditionGrade' => $item['ConditionGrade'],
        'PriceAtSale' => $item['PriceAtSale']
    ];
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard.php" class="text-warning">Dashboard</a></li>
                <li class="breadcrumb-item active text-light">Order Details</li>
            </ol>
        </nav>
        <h2 class="text-<?= $color ?> display-6 fw-bold">
            <i class="fa-solid <?= $icon ?> me-2"></i><?= h($title) ?>
        </h2>
        <p class="text-secondary">
            Complete order history for <?= h($_SESSION['shop_name'] ?? 'this store') ?>
            <span class="badge bg-<?= $color ?> ms-2"><?= $type === 'buyback' ? count($buybacks) : count($groupedOrders) ?> records</span>
        </p>
    </div>
</div>

<?php if ($type === 'buyback'): ?>
    <!-- Buyback明细 - 【优化】增加更详细的信息显示 -->
    <?php if (empty($buybacks)): ?>
        <div class="alert alert-info">No buyback records found.</div>
    <?php else: ?>
    <!-- 【新增】统计摘要 -->
    <?php
    $buybackGrouped = [];
    $totalBuybackAmount = 0;
    $totalItemCount = 0;
    foreach ($buybacks as $b) {
        $bid = $b['BuybackOrderID'];
        if (!isset($buybackGrouped[$bid])) {
            $buybackGrouped[$bid] = [
                'BuybackOrderID' => $bid,
                'CustomerName' => $b['CustomerName'],
                'BuybackDate' => $b['BuybackDate'],
                'TotalPayment' => $b['TotalPayment'],
                'EmployeeName' => $b['EmployeeName'] ?? 'N/A',
                'items' => []
            ];
            $totalBuybackAmount += $b['TotalPayment'];
        }
        $buybackGrouped[$bid]['items'][] = $b;
        $totalItemCount += $b['Quantity'];
    }
    ?>
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card bg-dark border-danger">
                <div class="card-body text-center">
                    <h6 class="text-danger">Total Buyback Expense</h6>
                    <h3 class="text-white"><?= formatPrice($totalBuybackAmount) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-dark border-info">
                <div class="card-body text-center">
                    <h6 class="text-info">Total Transactions</h6>
                    <h3 class="text-white"><?= count($buybackGrouped) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-dark border-warning">
                <div class="card-body text-center">
                    <h6 class="text-warning">Total Items Bought</h6>
                    <h3 class="text-white"><?= $totalItemCount ?></h3>
                </div>
            </div>
        </div>
    </div>

    <div class="accordion" id="buybackAccordion">
        <?php foreach ($buybackGrouped as $bid => $buyback): ?>
        <div class="accordion-item bg-dark border-secondary">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed bg-dark text-white" type="button"
                        data-bs-toggle="collapse" data-bs-target="#buyback<?= $bid ?>">
                    <div class="d-flex align-items-center w-100">
                        <span class="badge bg-danger me-3">#<?= $bid ?></span>
                        <div class="me-3">
                            <div><?= h($buyback['CustomerName']) ?></div>
                            <small class="text-muted"><?= date('Y-m-d H:i', strtotime($buyback['BuybackDate'])) ?></small>
                        </div>
                        <span class="badge bg-secondary me-3"><?= count($buyback['items']) ?> items</span>
                        <span class="text-danger fw-bold ms-auto me-3">-<?= formatPrice($buyback['TotalPayment']) ?></span>
                    </div>
                </button>
            </h2>
            <div id="buyback<?= $bid ?>" class="accordion-collapse collapse" data-bs-parent="#buybackAccordion">
                <div class="accordion-body">
                    <div class="mb-3 p-2 bg-secondary bg-opacity-25 rounded">
                        <small class="text-muted">
                            <i class="fa-solid fa-calendar me-1"></i>Date: <?= date('Y-m-d H:i:s', strtotime($buyback['BuybackDate'])) ?>
                            <span class="mx-2">|</span>
                            <i class="fa-solid fa-user me-1"></i>Processed by: <?= h($buyback['EmployeeName']) ?>
                        </small>
                    </div>
                    <table class="table table-dark table-sm mb-0">
                        <thead>
                            <tr>
                                <th>Album</th>
                                <th>Condition</th>
                                <th class="text-center">Qty</th>
                                <th class="text-end">Unit Price</th>
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($buyback['items'] as $item): ?>
                            <tr>
                                <td>
                                    <div class="text-white"><?= h($item['Title']) ?></div>
                                    <small class="text-muted"><?= h($item['ArtistName']) ?></small>
                                </td>
                                <td><span class="badge bg-secondary"><?= h($item['ConditionGrade']) ?></span></td>
                                <td class="text-center"><?= $item['Quantity'] ?></td>
                                <td class="text-end"><?= formatPrice($item['UnitPrice']) ?></td>
                                <td class="text-end text-danger"><?= formatPrice($item['UnitPrice'] * $item['Quantity']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

<?php else: ?>
    <!-- 订单明细 - 【优化】增加更详细的信息显示 -->
    <?php if (empty($groupedOrders)): ?>
        <div class="alert alert-info">No orders found for this category.</div>
    <?php else: ?>
    <!-- 【新增】统计摘要 -->
    <?php
    $totalRevenue = array_sum(array_column($groupedOrders, 'TotalAmount'));
    $totalShipping = array_sum(array_column($groupedOrders, 'ShippingCost'));
    $totalItems = 0;
    foreach ($groupedOrders as $order) {
        $totalItems += count($order['items']);
    }
    ?>
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-dark border-<?= $color ?>">
                <div class="card-body text-center">
                    <h6 class="text-<?= $color ?>">Total Revenue</h6>
                    <h3 class="text-white"><?= formatPrice($totalRevenue) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-dark border-info">
                <div class="card-body text-center">
                    <h6 class="text-info">Total Orders</h6>
                    <h3 class="text-white"><?= count($groupedOrders) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-dark border-warning">
                <div class="card-body text-center">
                    <h6 class="text-warning">Total Items Sold</h6>
                    <h3 class="text-white"><?= $totalItems ?></h3>
                </div>
            </div>
        </div>
        <?php if ($totalShipping > 0): ?>
        <div class="col-md-3">
            <div class="card bg-dark border-secondary">
                <div class="card-body text-center">
                    <h6 class="text-secondary">Total Shipping</h6>
                    <h3 class="text-white"><?= formatPrice($totalShipping) ?></h3>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="col-md-3">
            <div class="card bg-dark border-success">
                <div class="card-body text-center">
                    <h6 class="text-success">Avg Order Value</h6>
                    <h3 class="text-white"><?= formatPrice($totalRevenue / max(1, count($groupedOrders))) ?></h3>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="accordion" id="orderAccordion">
        <?php foreach ($groupedOrders as $orderId => $order): ?>
        <div class="accordion-item bg-dark border-secondary">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed bg-dark text-white" type="button"
                        data-bs-toggle="collapse" data-bs-target="#order<?= $orderId ?>">
                    <div class="d-flex align-items-center w-100 flex-wrap">
                        <span class="badge bg-<?= $color ?> me-3">#<?= $orderId ?></span>
                        <div class="me-3">
                            <div><?= h($order['CustomerName']) ?></div>
                            <small class="text-muted"><?= date('Y-m-d H:i', strtotime($order['OrderDate'])) ?></small>
                        </div>
                        <span class="badge bg-<?= $order['OrderStatus'] === 'Completed' ? 'success' : 'info' ?> me-2">
                            <?= $order['OrderStatus'] ?>
                        </span>
                        <span class="badge bg-secondary me-2"><?= count($order['items']) ?> items</span>
                        <span class="text-success fw-bold ms-auto me-3"><?= formatPrice($order['TotalAmount']) ?></span>
                        <?php if ($order['ShippingCost'] > 0): ?>
                        <small class="text-muted">(+<?= formatPrice($order['ShippingCost']) ?> shipping)</small>
                        <?php endif; ?>
                    </div>
                </button>
            </h2>
            <div id="order<?= $orderId ?>" class="accordion-collapse collapse" data-bs-parent="#orderAccordion">
                <div class="accordion-body">
                    <!-- 【新增】订单详细信息 -->
                    <div class="mb-3 p-2 bg-secondary bg-opacity-25 rounded">
                        <div class="row">
                            <div class="col-md-6">
                                <small class="text-muted">
                                    <i class="fa-solid fa-calendar me-1"></i>Order Date: <?= date('Y-m-d H:i:s', strtotime($order['OrderDate'])) ?>
                                </small>
                            </div>
                            <div class="col-md-6">
                                <small class="text-muted">
                                    <i class="fa-solid fa-truck me-1"></i>Fulfillment: <?= h($order['FulfillmentType'] ?? 'N/A') ?>
                                    <?php if ($order['OrderType'] === 'InStore'): ?>
                                        <span class="badge bg-warning text-dark ms-1">POS</span>
                                    <?php endif; ?>
                                </small>
                            </div>
                        </div>
                    </div>
                    <table class="table table-dark table-sm mb-0">
                        <thead>
                            <tr>
                                <th>Album</th>
                                <th>Artist</th>
                                <th>Condition</th>
                                <th class="text-end">Price</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($order['items'] as $item): ?>
                            <tr>
                                <td class="text-white"><?= h($item['Title']) ?></td>
                                <td><small class="text-muted"><?= h($item['ArtistName']) ?></small></td>
                                <td><span class="badge bg-secondary"><?= h($item['ConditionGrade']) ?></span></td>
                                <td class="text-end text-success"><?= formatPrice($item['PriceAtSale']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="border-top border-secondary">
                            <tr>
                                <td colspan="3" class="text-end"><strong>Subtotal:</strong></td>
                                <td class="text-end text-success"><?= formatPrice($order['TotalAmount'] - ($order['ShippingCost'] ?? 0)) ?></td>
                            </tr>
                            <?php if ($order['ShippingCost'] > 0): ?>
                            <tr>
                                <td colspan="3" class="text-end"><strong>Shipping:</strong></td>
                                <td class="text-end text-info"><?= formatPrice($order['ShippingCost']) ?></td>
                            </tr>
                            <tr class="table-success bg-opacity-25">
                                <td colspan="3" class="text-end"><strong>Total:</strong></td>
                                <td class="text-end text-success fw-bold"><?= formatPrice($order['TotalAmount']) ?></td>
                            </tr>
                            <?php endif; ?>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
<?php endif; ?>

<div class="mt-4">
    <a href="dashboard.php" class="btn btn-outline-secondary">
        <i class="fa-solid fa-arrow-left me-1"></i>Back to Dashboard
    </a>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
