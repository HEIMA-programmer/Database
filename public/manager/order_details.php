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
    <!-- Buyback明细 -->
    <?php if (empty($buybacks)): ?>
        <div class="alert alert-info">No buyback records found.</div>
    <?php else: ?>
    <div class="accordion" id="buybackAccordion">
        <?php
        $buybackGrouped = [];
        foreach ($buybacks as $b) {
            $bid = $b['BuybackOrderID'];
            if (!isset($buybackGrouped[$bid])) {
                $buybackGrouped[$bid] = [
                    'BuybackOrderID' => $bid,
                    'CustomerName' => $b['CustomerName'],
                    'BuybackDate' => $b['BuybackDate'],
                    'TotalPayment' => $b['TotalPayment'],
                    'items' => []
                ];
            }
            $buybackGrouped[$bid]['items'][] = $b;
        }

        foreach ($buybackGrouped as $bid => $buyback):
        ?>
        <div class="accordion-item bg-dark border-secondary">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed bg-dark text-white" type="button"
                        data-bs-toggle="collapse" data-bs-target="#buyback<?= $bid ?>">
                    <span class="badge bg-danger me-3">#<?= $bid ?></span>
                    <span class="me-3"><?= h($buyback['CustomerName']) ?></span>
                    <small class="text-muted me-3"><?= formatDate($buyback['BuybackDate']) ?></small>
                    <span class="text-danger fw-bold ms-auto me-3">-<?= formatPrice($buyback['TotalPayment']) ?></span>
                </button>
            </h2>
            <div id="buyback<?= $bid ?>" class="accordion-collapse collapse" data-bs-parent="#buybackAccordion">
                <div class="accordion-body">
                    <table class="table table-dark table-sm mb-0">
                        <thead>
                            <tr><th>Album</th><th>Condition</th><th class="text-center">Qty</th><th class="text-end">Unit Price</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($buyback['items'] as $item): ?>
                            <tr>
                                <td>
                                    <div><?= h($item['Title']) ?></div>
                                    <small class="text-muted"><?= h($item['ArtistName']) ?></small>
                                </td>
                                <td><span class="badge bg-secondary"><?= h($item['ConditionGrade']) ?></span></td>
                                <td class="text-center"><?= $item['Quantity'] ?></td>
                                <td class="text-end"><?= formatPrice($item['UnitPrice']) ?></td>
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
    <!-- 订单明细 -->
    <?php if (empty($groupedOrders)): ?>
        <div class="alert alert-info">No orders found for this category.</div>
    <?php else: ?>
    <div class="accordion" id="orderAccordion">
        <?php foreach ($groupedOrders as $orderId => $order): ?>
        <div class="accordion-item bg-dark border-secondary">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed bg-dark text-white" type="button"
                        data-bs-toggle="collapse" data-bs-target="#order<?= $orderId ?>">
                    <span class="badge bg-<?= $color ?> me-3">#<?= $orderId ?></span>
                    <span class="me-3"><?= h($order['CustomerName']) ?></span>
                    <small class="text-muted me-3"><?= formatDate($order['OrderDate']) ?></small>
                    <span class="badge bg-<?= $order['OrderStatus'] === 'Completed' ? 'success' : 'info' ?> me-3">
                        <?= $order['OrderStatus'] ?>
                    </span>
                    <span class="text-success fw-bold ms-auto me-3"><?= formatPrice($order['TotalAmount']) ?></span>
                    <?php if ($order['ShippingCost'] > 0): ?>
                    <small class="text-muted">(+<?= formatPrice($order['ShippingCost']) ?> shipping)</small>
                    <?php endif; ?>
                </button>
            </h2>
            <div id="order<?= $orderId ?>" class="accordion-collapse collapse" data-bs-parent="#orderAccordion">
                <div class="accordion-body">
                    <table class="table table-dark table-sm mb-0">
                        <thead>
                            <tr><th>Album</th><th>Condition</th><th class="text-end">Price</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($order['items'] as $item): ?>
                            <tr>
                                <td>
                                    <div><?= h($item['Title']) ?></div>
                                    <small class="text-muted"><?= h($item['ArtistName']) ?></small>
                                </td>
                                <td><span class="badge bg-secondary"><?= h($item['ConditionGrade']) ?></span></td>
                                <td class="text-end text-success"><?= formatPrice($item['PriceAtSale']) ?></td>
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
<?php endif; ?>

<div class="mt-4">
    <a href="dashboard.php" class="btn btn-outline-secondary">
        <i class="fa-solid fa-arrow-left me-1"></i>Back to Dashboard
    </a>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
