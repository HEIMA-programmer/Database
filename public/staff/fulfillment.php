<?php
/**
 * 员工订单履行页面
 *
 * 【修复】按员工所属店铺过滤订单：
 * - 每个员工只能看到和处理自己店铺的订单
 * - 仓库员工处理仓库订单，门店员工处理门店订单
 *
 * 【新增】店铺间调货确认功能：
 * - 显示Admin批准的调货申请，需要源店铺员工确认发货
 * - 与顾客订单运输区分开
 */
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/auth_guard.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_procedures.php';
requireRole('Staff');

// 【安全修复】从数据库验证员工店铺归属
$employeeId = $_SESSION['user_id'] ?? null;
if (!$employeeId) {
    flash('Session expired. Please re-login.', 'warning');
    header('Location: /login.php');
    exit;
}

// 【架构重构Phase2】使用DBProcedures获取并验证员工信息
$employee = DBProcedures::getEmployeeShopInfo($pdo, $employeeId);
if (!$employee) {
    flash('Employee information not found. Please contact administrator.', 'danger');
    header('Location: /login.php');
    exit;
}

// 【安全修复】使用数据库验证后的店铺ID
$shopId = $employee['ShopID'];
$shopType = $employee['ShopType'];
$_SESSION['shop_id'] = $shopId; // 同步session

// 处理订单操作
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 【新增】处理店铺间调货确认（源店铺发货）
    // 【修复】支持批量处理多个TransferID
    if ($action === 'confirm_transfer' || $action === 'cancel_transfer') {
        $transferIds = $_POST['transfer_ids'] ?? '';
        $transferIdArray = array_filter(array_map('intval', explode(',', $transferIds)));

        if (empty($transferIdArray)) {
            flash("No transfer IDs provided.", 'danger');
            header('Location: fulfillment.php?tab=transfers');
            exit;
        }

        try {
            $pdo->beginTransaction();
            $processedCount = 0;

            foreach ($transferIdArray as $transferId) {
                // 【架构重构Phase2】使用DBProcedures替换直接SQL
                $transfer = DBProcedures::validateTransferFromShop($pdo, $transferId, $shopId);

                if ($transfer && $transfer['Status'] === 'Pending') {
                    if ($action === 'confirm_transfer') {
                        // 使用存储过程确认发货
                        DBProcedures::confirmTransferDispatch($pdo, $transferId, $employeeId);
                        $processedCount++;
                    } elseif ($action === 'cancel_transfer') {
                        // 【架构重构】使用存储过程取消调拨
                        DBProcedures::cancelTransfer($pdo, $transferId, $shopId);
                        $processedCount++;
                    }
                }
            }

            $pdo->commit();
            if ($action === 'confirm_transfer') {
                flash("$processedCount item(s) confirmed and shipped.", 'success');
            } else {
                flash("$processedCount transfer(s) cancelled.", 'info');
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            flash("Error: " . $e->getMessage(), 'danger');
        }

        header('Location: fulfillment.php?tab=transfers');
        exit;
    }

    // 【新增】处理采购订单收货确认（仓库员工）
    if ($action === 'receive_supplier_order') {
        $orderId = (int)($_POST['order_id'] ?? 0);

        if ($shopType !== 'Warehouse') {
            flash('Only warehouse staff can receive supplier orders.', 'danger');
            header('Location: fulfillment.php?tab=procurement');
            exit;
        }

        if ($orderId <= 0) {
            flash('Invalid order ID.', 'danger');
            header('Location: fulfillment.php?tab=procurement');
            exit;
        }

        $result = handleProcurementReceivePOWithCondition($pdo, $orderId, $shopId, 'New');
        flash($result['message'], $result['success'] ? 'success' : 'danger');

        header('Location: fulfillment.php?tab=procurement');
        exit;
    }

    // 【新增】处理目标店铺收货确认
    // 【修复】支持批量处理多个TransferID
    if ($action === 'receive_transfer') {
        $transferIds = $_POST['transfer_ids'] ?? '';
        $transferIdArray = array_filter(array_map('intval', explode(',', $transferIds)));

        if (empty($transferIdArray)) {
            flash("No transfer IDs provided.", 'danger');
            header('Location: fulfillment.php?tab=receiving');
            exit;
        }

        try {
            $pdo->beginTransaction();
            $processedCount = 0;

            foreach ($transferIdArray as $transferId) {
                // 【架构重构Phase2】使用DBProcedures替换直接SQL
                $transfer = DBProcedures::validateTransferToShop($pdo, $transferId, $shopId);

                if ($transfer && $transfer['Status'] === 'InTransit') {
                    // 调用存储过程完成调拨（触发器会自动更新库存位置和状态）
                    DBProcedures::completeTransfer($pdo, $transferId, $employeeId);
                    $processedCount++;
                }
            }

            $pdo->commit();
            flash("$processedCount item(s) received successfully. Stock has been added to your inventory.", 'success');
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            flash("Error: " . $e->getMessage(), 'danger');
        }

        header('Location: fulfillment.php?tab=receiving');
        exit;
    }

    // 处理顾客订单操作
    $orderId = (int)($_POST['order_id'] ?? 0);

    // 【架构重构Phase2】使用DBProcedures替换直接SQL
    $order = DBProcedures::validateOrderBelongsToShop($pdo, $orderId, $shopId);

    if ($order) {
        try {
            switch ($action) {
                case 'mark_paid':
                    if ($order['OrderStatus'] == 'Pending') {
                        DBProcedures::updateOrderStatus($pdo, $orderId, 'Paid', $employeeId);
                        flash("Order #$orderId marked as Paid.", 'success');
                    }
                    break;

                case 'ship':
                    // 【修复】只有Paid状态才能发货，Pending状态（未支付）不能发货
                    if ($order['OrderStatus'] == 'Paid') {
                        DBProcedures::updateOrderStatus($pdo, $orderId, 'Shipped', $employeeId);
                        flash("Order #$orderId marked as Shipped.", 'success');
                    } else {
                        flash("Order must be paid before shipping.", 'warning');
                    }
                    break;

                case 'ready_pickup':
                    if (in_array($order['OrderStatus'], ['Pending', 'Paid'])) {
                        DBProcedures::updateOrderStatus($pdo, $orderId, 'ReadyForPickup', $employeeId);
                        flash("Order #$orderId is ready for pickup.", 'success');
                    }
                    break;

                case 'complete':
                    DBProcedures::completeOrder($pdo, $orderId);
                    flash("Order #$orderId completed successfully.", 'success');
                    break;

                case 'cancel':
                    if (!in_array($order['OrderStatus'], ['Completed', 'Cancelled'])) {
                        DBProcedures::cancelOrder($pdo, $orderId);
                        flash("Order #$orderId cancelled.", 'info');
                    }
                    break;
            }
        } catch (Exception $e) {
            flash("Error: " . $e->getMessage(), 'danger');
        }
    } elseif ($orderId > 0) {
        flash("You can only manage orders from your own store.", 'danger');
    }

    header('Location: fulfillment.php');
    exit;
}

// 【架构重构Phase2】获取本店铺订单列表
$statusFilter = $_GET['status'] ?? 'pending';
$orders = DBProcedures::getFulfillmentOrders($pdo, $shopId, $statusFilter);

// 【架构重构Phase2】获取订单状态统计
$statusCounts = DBProcedures::getFulfillmentOrderStatusCounts($pdo, $shopId);

// 【架构重构Phase2】获取待发货的店铺间调货记录（本店作为源店铺）
$pendingTransfers = DBProcedures::getFulfillmentPendingTransfersGrouped($pdo, $shopId);
$pendingTransferCount = array_sum(array_column($pendingTransfers, 'Quantity'));

// 【架构重构Phase2】获取待接收的店铺间调货记录（本店作为目标店铺）
$incomingTransfers = DBProcedures::getFulfillmentIncomingTransfersGrouped($pdo, $shopId);
$incomingTransferCount = array_sum(array_column($incomingTransfers, 'Quantity'));

// 【新增】获取仓库待收货的采购订单（仅限仓库员工）
$pendingSupplierReceipts = [];
$pendingSupplierReceiptCount = 0;
if ($shopType === 'Warehouse') {
    $pendingSupplierReceipts = DBProcedures::getWarehousePendingReceipts($pdo, $shopId);
    $pendingSupplierReceiptCount = count($pendingSupplierReceipts);
}

// 当前标签页
$currentTab = $_GET['tab'] ?? 'orders';

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

<!-- Tab Navigation: Customer Orders / Pending Shipments / Incoming Transfers / Procurement (Warehouse only) -->
<ul class="nav nav-tabs mb-4">
    <li class="nav-item">
        <a class="nav-link <?= $currentTab == 'orders' ? 'active bg-dark text-warning' : 'text-light' ?>" href="?tab=orders">
            <i class="fa-solid fa-shopping-cart me-1"></i>Customer Orders
            <?php if (($statusCounts['Pending'] ?? 0) + ($statusCounts['Paid'] ?? 0) > 0): ?>
                <span class="badge bg-warning text-dark"><?= ($statusCounts['Pending'] ?? 0) + ($statusCounts['Paid'] ?? 0) ?></span>
            <?php endif; ?>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $currentTab == 'transfers' ? 'active bg-dark text-info' : 'text-light' ?>" href="?tab=transfers">
            <i class="fa-solid fa-truck-arrow-right me-1"></i>Pending Shipments
            <?php if ($pendingTransferCount > 0): ?>
                <span class="badge bg-info"><?= $pendingTransferCount ?></span>
            <?php endif; ?>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $currentTab == 'receiving' ? 'active bg-dark text-success' : 'text-light' ?>" href="?tab=receiving">
            <i class="fa-solid fa-box-open me-1"></i>Incoming Transfers
            <?php if ($incomingTransferCount > 0): ?>
                <span class="badge bg-success"><?= $incomingTransferCount ?></span>
            <?php endif; ?>
        </a>
    </li>
    <?php if ($shopType === 'Warehouse'): ?>
    <li class="nav-item">
        <a class="nav-link <?= $currentTab == 'procurement' ? 'active bg-dark text-primary' : 'text-light' ?>" href="?tab=procurement">
            <i class="fa-solid fa-boxes-packing me-1"></i>Supplier Receipts
            <?php if ($pendingSupplierReceiptCount > 0): ?>
                <span class="badge bg-primary"><?= $pendingSupplierReceiptCount ?></span>
            <?php endif; ?>
        </a>
    </li>
    <?php endif; ?>
</ul>

<?php if ($currentTab == 'orders'): ?>
<!-- Customer Orders Section -->

<!-- Status Filter - Optimized UI -->
<div class="order-status-filter mb-4">
    <div class="status-flow">
        <a href="?tab=orders&status=pending" class="btn status-btn <?= $statusFilter == 'pending' ? 'btn-warning active' : 'btn-outline-warning' ?>">
            <i class="fa-solid fa-clock me-1"></i>Pending
            <?php if (($statusCounts['Pending'] ?? 0) + ($statusCounts['Paid'] ?? 0) > 0): ?>
                <span class="badge bg-dark ms-1"><?= ($statusCounts['Pending'] ?? 0) + ($statusCounts['Paid'] ?? 0) ?></span>
            <?php endif; ?>
        </a>
        <span class="arrow"><i class="fa-solid fa-chevron-right"></i></span>
        <a href="?tab=orders&status=shipping" class="btn status-btn <?= $statusFilter == 'shipping' ? 'btn-primary active' : 'btn-outline-primary' ?>">
            <i class="fa-solid fa-truck me-1"></i>Shipped
            <?php if (($statusCounts['Shipped'] ?? 0) > 0): ?>
                <span class="badge bg-dark ms-1"><?= $statusCounts['Shipped'] ?></span>
            <?php endif; ?>
        </a>
        <span class="arrow"><i class="fa-solid fa-chevron-right"></i></span>
        <a href="?tab=orders&status=completed" class="btn status-btn <?= $statusFilter == 'completed' ? 'btn-success active' : 'btn-outline-success' ?>">
            <i class="fa-solid fa-check me-1"></i>Completed
        </a>
    </div>
    <div class="mt-2">
        <a href="?tab=orders&status=cancelled" class="btn status-btn <?= $statusFilter == 'cancelled' ? 'btn-secondary active' : 'btn-outline-secondary' ?>">
            <i class="fa-solid fa-ban me-1"></i>Cancelled
        </a>
        <a href="?tab=orders&status=all" class="btn status-btn <?= $statusFilter == 'all' ? 'btn-light active' : 'btn-outline-light' ?>">
            All Orders
        </a>
    </div>
</div>

<?php if (empty($orders)): ?>
    <div class="text-center py-5">
        <div class="display-1 text-secondary mb-3"><i class="fa-solid fa-inbox"></i></div>
        <h3 class="text-white">No orders found</h3>
        <p class="no-orders-message">No orders matching the selected filter.</p>
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
            // 【修复】使用 null 安全的方式读取 FulfillmentType
            $fulfillmentType = $order['FulfillmentType'] ?? 'Shipping';
            $fulfillmentIcon = $fulfillmentType == 'Pickup' ? 'fa-store' : 'fa-truck';
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
                                <?= h($fulfillmentType) ?>
                            </span>
                            <?php
                            $shippingAddress = $order['ShippingAddress'] ?? '';
                            if ($fulfillmentType == 'Shipping' && $shippingAddress): ?>
                                <div class="text-muted small mt-1">
                                    <i class="fa-solid fa-location-dot me-1"></i><?= h($shippingAddress) ?>
                                </div>
                            <?php endif; ?>
                            <?php if (($order['ShippingCost'] ?? 0) > 0): ?>
                                <div class="text-warning small mt-1">
                                    <i class="fa-solid fa-truck me-1"></i>Shipping: <?= formatPrice($order['ShippingCost']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-3">
                            <small class="text-muted d-block">Items</small>
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-info"><?= $order['ItemCount'] ?> item<?= $order['ItemCount'] > 1 ? 's' : '' ?></span>
                                <small class="text-white"><?= h($order['ItemTitles']) ?></small>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (!in_array($order['OrderStatus'], ['Completed', 'Cancelled', 'Shipped'])): ?>
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

                            <?php // Only Paid status can ship ?>
                            <?php if ($fulfillmentType == 'Shipping' && $order['OrderStatus'] == 'Paid'): ?>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="order_id" value="<?= $order['OrderID'] ?>">
                                    <input type="hidden" name="action" value="ship">
                                    <button type="submit" class="btn btn-primary btn-sm">
                                        <i class="fa-solid fa-truck me-1"></i>Ship
                                    </button>
                                </form>
                            <?php endif; ?>

                            <?php // ReadyForPickup orders can be completed by staff ?>
                            <?php if ($order['OrderStatus'] == 'ReadyForPickup'): ?>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="order_id" value="<?= $order['OrderID'] ?>">
                                    <input type="hidden" name="action" value="complete">
                                    <button type="submit" class="btn btn-success btn-sm">
                                        <i class="fa-solid fa-check me-1"></i>Complete
                                    </button>
                                </form>
                            <?php endif; ?>

                            <?php // Cancel only for Pending and Paid (not shipped) ?>
                            <?php if (in_array($order['OrderStatus'], ['Pending', 'Paid'])): ?>
                            <form method="POST" class="d-inline ms-auto" id="cancelOrderForm_<?= $order['OrderID'] ?>">
                                <input type="hidden" name="order_id" value="<?= $order['OrderID'] ?>">
                                <input type="hidden" name="action" value="cancel">
                                <button type="button" class="btn btn-outline-danger btn-sm"
                                        onclick="showCancelModal(<?= $order['OrderID'] ?>)">
                                    <i class="fa-solid fa-ban me-1"></i>Cancel
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php elseif ($order['OrderStatus'] == 'Shipped'): ?>
                    <div class="card-footer bg-dark border-secondary">
                        <div class="text-center">
                            <small class="text-visible">
                                <i class="fa-solid fa-clock me-1"></i>Waiting for customer to confirm receipt
                            </small>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php elseif ($currentTab == 'transfers'): ?>
<!-- Inter-store Transfer Shipments Section -->
<div class="alert alert-info mb-4">
    <i class="fa-solid fa-info-circle me-2"></i>
    This shows transfer requests that need to be shipped from your store. When other stores request stock from your inventory and it's approved by Admin, you need to confirm the shipment here.
</div>

<?php if (empty($pendingTransfers)): ?>
    <div class="text-center py-5">
        <div class="display-1 text-secondary mb-3"><i class="fa-solid fa-truck"></i></div>
        <h3 class="text-white">No pending transfer shipments</h3>
        <p class="no-orders-message">New transfer requests will appear here when approved.</p>
    </div>
<?php else: ?>
    <div class="row row-cols-1 row-cols-lg-2 g-4">
        <?php foreach ($pendingTransfers as $transfer): ?>
            <div class="col">
                <div class="card bg-dark border-info h-100">
                    <div class="card-header bg-dark border-info d-flex justify-content-between align-items-center">
                        <div>
                            <strong class="text-info">Transfer Batch</strong>
                            <span class="badge bg-warning text-dark ms-2">Pending Shipment</span>
                            <span class="badge bg-info ms-1"><?= $transfer['Quantity'] ?> items</span>
                        </div>
                        <small class="text-muted"><?= date('M d, H:i', strtotime($transfer['TransferDate'])) ?></small>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-6">
                                <small class="text-muted">From Store</small>
                                <div class="text-white">
                                    <i class="fa-solid fa-store me-1 text-warning"></i>
                                    <?= h($transfer['FromShopName']) ?>
                                </div>
                            </div>
                            <div class="col-6 text-end">
                                <small class="text-muted">To Store</small>
                                <div class="text-info">
                                    <i class="fa-solid fa-arrow-right me-1"></i>
                                    <?= h($transfer['ToShopName']) ?>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <small class="text-muted d-block">Album</small>
                            <div class="text-white fw-bold"><?= h($transfer['ReleaseTitle']) ?></div>
                            <small class="text-warning"><?= h($transfer['ArtistName']) ?></small>
                        </div>

                        <div class="row">
                            <div class="col-4">
                                <small class="text-muted">Condition</small>
                                <div><span class="badge bg-secondary"><?= h($transfer['ConditionGrade']) ?></span></div>
                            </div>
                            <div class="col-4 text-center">
                                <small class="text-muted">Quantity</small>
                                <div class="text-warning fw-bold fs-5"><?= $transfer['Quantity'] ?></div>
                            </div>
                            <div class="col-4 text-end">
                                <small class="text-muted">Unit Price</small>
                                <div class="text-success fw-bold"><?= formatPrice($transfer['UnitPrice']) ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="card-footer bg-dark border-info">
                        <form method="POST" class="d-grid">
                            <input type="hidden" name="transfer_ids" value="<?= h($transfer['TransferIDs']) ?>">
                            <input type="hidden" name="action" value="confirm_transfer">
                            <button type="submit" class="btn btn-info">
                                <i class="fa-solid fa-truck me-1"></i>Confirm Shipment (<?= $transfer['Quantity'] ?> items)
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php elseif ($currentTab == 'receiving'): ?>
<!-- Incoming Transfers Section -->
<div class="alert alert-success mb-4">
    <i class="fa-solid fa-info-circle me-2"></i>
    This shows incoming stock transfers from other stores that are waiting for you to confirm receipt. Once confirmed, the stock will be added to your store's inventory.
</div>

<?php if (empty($incomingTransfers)): ?>
    <div class="text-center py-5">
        <div class="display-1 text-secondary mb-3"><i class="fa-solid fa-box-open"></i></div>
        <h3 class="text-white">No incoming transfers</h3>
        <p class="no-orders-message">Incoming transfers from other stores will appear here.</p>
    </div>
<?php else: ?>
    <div class="row row-cols-1 row-cols-lg-2 g-4">
        <?php foreach ($incomingTransfers as $transfer): ?>
            <div class="col">
                <div class="card bg-dark border-success h-100">
                    <div class="card-header bg-dark border-success d-flex justify-content-between align-items-center">
                        <div>
                            <strong class="text-success">Transfer Batch</strong>
                            <span class="badge bg-primary ms-2">In Transit</span>
                            <span class="badge bg-success ms-1"><?= $transfer['Quantity'] ?> items</span>
                        </div>
                        <small class="text-muted"><?= date('M d, H:i', strtotime($transfer['TransferDate'])) ?></small>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-6">
                                <small class="text-muted">From Store</small>
                                <div class="text-white">
                                    <i class="fa-solid fa-store me-1 text-info"></i>
                                    <?= h($transfer['FromShopName']) ?>
                                </div>
                            </div>
                            <div class="col-6 text-end">
                                <small class="text-muted">To Store</small>
                                <div class="text-success">
                                    <i class="fa-solid fa-arrow-right me-1"></i>
                                    <?= h($transfer['ToShopName']) ?>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <small class="text-muted d-block">Album</small>
                            <div class="text-white fw-bold"><?= h($transfer['ReleaseTitle']) ?></div>
                            <small class="text-warning"><?= h($transfer['ArtistName']) ?></small>
                        </div>

                        <div class="row">
                            <div class="col-4">
                                <small class="text-muted">Condition</small>
                                <div><span class="badge bg-secondary"><?= h($transfer['ConditionGrade']) ?></span></div>
                            </div>
                            <div class="col-4 text-center">
                                <small class="text-muted">Quantity</small>
                                <div class="text-warning fw-bold fs-5"><?= $transfer['Quantity'] ?></div>
                            </div>
                            <div class="col-4 text-end">
                                <small class="text-muted">Unit Price</small>
                                <div class="text-success fw-bold"><?= formatPrice($transfer['UnitPrice']) ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="card-footer bg-dark border-success">
                        <form method="POST" class="d-grid">
                            <input type="hidden" name="transfer_ids" value="<?= h($transfer['TransferIDs']) ?>">
                            <input type="hidden" name="action" value="receive_transfer">
                            <button type="submit" class="btn btn-success">
                                <i class="fa-solid fa-check me-1"></i>Confirm Receipt (<?= $transfer['Quantity'] ?> items)
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php elseif ($currentTab == 'procurement' && $shopType === 'Warehouse'): ?>
<!-- Supplier Order Receipts Section (Warehouse Only) -->
<div class="alert alert-primary mb-4">
    <i class="fa-solid fa-info-circle me-2"></i>
    This shows purchase orders from suppliers waiting to be received. When you confirm receipt, inventory items will be added to the warehouse.
</div>

<?php if (empty($pendingSupplierReceipts)): ?>
    <div class="text-center py-5">
        <div class="display-1 text-secondary mb-3"><i class="fa-solid fa-boxes-packing"></i></div>
        <h3 class="text-white">No pending supplier receipts</h3>
        <p class="no-orders-message">All purchase orders have been received.</p>
    </div>
<?php else: ?>
    <div class="row row-cols-1 row-cols-lg-2 g-4">
        <?php foreach ($pendingSupplierReceipts as $po): ?>
            <div class="col">
                <div class="card bg-dark border-primary h-100">
                    <div class="card-header bg-dark border-primary d-flex justify-content-between align-items-center">
                        <div>
                            <strong class="text-primary">PO #<?= $po['SupplierOrderID'] ?></strong>
                            <span class="badge bg-warning text-dark ms-2">Awaiting Receipt</span>
                        </div>
                        <small class="text-muted"><?= date('M d, H:i', strtotime($po['OrderDate'])) ?></small>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-6">
                                <small class="text-muted">Supplier</small>
                                <div class="text-white">
                                    <i class="fa-solid fa-truck-field me-1 text-info"></i>
                                    <?= h($po['SupplierName']) ?>
                                </div>
                            </div>
                            <div class="col-6 text-end">
                                <small class="text-muted">Quantity</small>
                                <div class="text-warning fw-bold fs-5"><?= $po['TotalItems'] ?> units</div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <small class="text-muted d-block">Album</small>
                            <div class="text-white fw-bold"><?= h($po['ReleaseTitle']) ?></div>
                            <small class="text-warning"><?= h($po['ArtistName']) ?></small>
                        </div>

                        <div class="row">
                            <div class="col-4">
                                <small class="text-muted">Condition</small>
                                <div><span class="badge bg-secondary"><?= h($po['ConditionGrade']) ?></span></div>
                            </div>
                            <div class="col-4 text-center">
                                <small class="text-muted">Unit Cost</small>
                                <div class="text-danger"><?= formatPrice($po['UnitCost']) ?></div>
                            </div>
                            <div class="col-4 text-end">
                                <small class="text-muted">Sale Price</small>
                                <div class="text-success fw-bold"><?= formatPrice($po['SalePrice']) ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="card-footer bg-dark border-primary">
                        <form method="POST" class="d-grid" onsubmit="return confirm('Confirm receipt of this order? This will add items to inventory.');">
                            <input type="hidden" name="order_id" value="<?= $po['SupplierOrderID'] ?>">
                            <input type="hidden" name="action" value="receive_supplier_order">
                            <button type="submit" class="btn btn-primary">
                                <i class="fa-solid fa-check me-1"></i>Confirm Receipt (<?= $po['TotalItems'] ?> items)
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php endif; ?>

<!-- Custom Confirm Modal -->
<div class="modal fade custom-confirm-modal" id="cancelOrderModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa-solid fa-exclamation-triangle me-2"></i>Cancel Order</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                Are you sure you want to cancel this order?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Keep Order</button>
                <button type="button" class="btn btn-danger" id="confirmCancelBtn">Cancel Order</button>
            </div>
        </div>
    </div>
</div>

<script>
let cancelModal;
let currentOrderId = null;

document.addEventListener('DOMContentLoaded', function() {
    cancelModal = new bootstrap.Modal(document.getElementById('cancelOrderModal'));

    document.getElementById('confirmCancelBtn').addEventListener('click', function() {
        if (currentOrderId) {
            document.getElementById('cancelOrderForm_' + currentOrderId).submit();
        }
    });
});

function showCancelModal(orderId) {
    currentOrderId = orderId;
    cancelModal.show();
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>