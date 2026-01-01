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

<!-- 【新增】主标签页切换：顾客订单 / 待发货调货 / 待接收调货 -->
<ul class="nav nav-tabs mb-4">
    <li class="nav-item">
        <a class="nav-link <?= $currentTab == 'orders' ? 'active bg-dark text-warning' : 'text-light' ?>" href="?tab=orders">
            <i class="fa-solid fa-shopping-cart me-1"></i>顾客订单
            <?php if (($statusCounts['Pending'] ?? 0) + ($statusCounts['Paid'] ?? 0) > 0): ?>
                <span class="badge bg-warning text-dark"><?= ($statusCounts['Pending'] ?? 0) + ($statusCounts['Paid'] ?? 0) ?></span>
            <?php endif; ?>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $currentTab == 'transfers' ? 'active bg-dark text-info' : 'text-light' ?>" href="?tab=transfers">
            <i class="fa-solid fa-truck-arrow-right me-1"></i>待发货
            <?php if ($pendingTransferCount > 0): ?>
                <span class="badge bg-info"><?= $pendingTransferCount ?></span>
            <?php endif; ?>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $currentTab == 'receiving' ? 'active bg-dark text-success' : 'text-light' ?>" href="?tab=receiving">
            <i class="fa-solid fa-box-open me-1"></i>待接收
            <?php if ($incomingTransferCount > 0): ?>
                <span class="badge bg-success"><?= $incomingTransferCount ?></span>
            <?php endif; ?>
        </a>
    </li>
</ul>

<?php if ($currentTab == 'orders'): ?>
<!-- 顾客订单部分 -->

<!-- 状态过滤器 -->
<div class="card bg-dark border-secondary mb-4">
    <div class="card-body py-2">
        <div class="d-flex gap-2 flex-wrap">
            <a href="?tab=orders&status=pending" class="btn btn-sm <?= $statusFilter == 'pending' ? 'btn-warning' : 'btn-outline-warning' ?>">
                <i class="fa-solid fa-clock me-1"></i>Pending
                <?php if (($statusCounts['Pending'] ?? 0) + ($statusCounts['Paid'] ?? 0) > 0): ?>
                    <span class="badge bg-dark"><?= ($statusCounts['Pending'] ?? 0) + ($statusCounts['Paid'] ?? 0) ?></span>
                <?php endif; ?>
            </a>
            <a href="?tab=orders&status=shipping" class="btn btn-sm <?= $statusFilter == 'shipping' ? 'btn-primary' : 'btn-outline-primary' ?>">
                <i class="fa-solid fa-truck me-1"></i>Shipped
                <?php if (($statusCounts['Shipped'] ?? 0) > 0): ?>
                    <span class="badge bg-dark"><?= $statusCounts['Shipped'] ?></span>
                <?php endif; ?>
            </a>
            <a href="?tab=orders&status=completed" class="btn btn-sm <?= $statusFilter == 'completed' ? 'btn-success' : 'btn-outline-success' ?>">
                <i class="fa-solid fa-check me-1"></i>Completed
            </a>
            <a href="?tab=orders&status=cancelled" class="btn btn-sm <?= $statusFilter == 'cancelled' ? 'btn-secondary' : 'btn-outline-secondary' ?>">
                <i class="fa-solid fa-ban me-1"></i>Cancelled
            </a>
            <a href="?tab=orders&status=all" class="btn btn-sm <?= $statusFilter == 'all' ? 'btn-light' : 'btn-outline-light' ?>">
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
                                    <i class="fa-solid fa-truck me-1"></i>运费: <?= formatPrice($order['ShippingCost']) ?>
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
                            
                            <?php // 【修复】只有Paid状态才显示Ship按钮，Pending（未支付）状态不能发货 ?>
                            <?php if ($fulfillmentType == 'Shipping' && $order['OrderStatus'] == 'Paid'): ?>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="order_id" value="<?= $order['OrderID'] ?>">
                                    <input type="hidden" name="action" value="ship">
                                    <button type="submit" class="btn btn-primary btn-sm">
                                        <i class="fa-solid fa-truck me-1"></i>Ship
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

<?php elseif ($currentTab == 'transfers'): ?>
<!-- 店铺间调货部分 -->
<div class="alert alert-info mb-4">
    <i class="fa-solid fa-info-circle me-2"></i>
    这里显示需要您发货的店铺间调货请求。当其他店铺申请从您的店铺调货并获得Admin批准后，您需要在此确认发货。
</div>

<?php if (empty($pendingTransfers)): ?>
    <div class="text-center py-5">
        <div class="display-1 text-secondary mb-3"><i class="fa-solid fa-truck"></i></div>
        <h3 class="text-white">没有待发货的调货请求</h3>
        <p class="text-muted">当有新的调货请求时会显示在这里。</p>
    </div>
<?php else: ?>
    <div class="row row-cols-1 row-cols-lg-2 g-4">
        <?php foreach ($pendingTransfers as $transfer): ?>
            <div class="col">
                <div class="card bg-dark border-info h-100">
                    <div class="card-header bg-dark border-info d-flex justify-content-between align-items-center">
                        <div>
                            <strong class="text-info">调货批次</strong>
                            <span class="badge bg-warning text-dark ms-2">待发货</span>
                            <span class="badge bg-info ms-1"><?= $transfer['Quantity'] ?> 张</span>
                        </div>
                        <small class="text-muted"><?= date('M d, H:i', strtotime($transfer['TransferDate'])) ?></small>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-6">
                                <small class="text-muted">调出店铺</small>
                                <div class="text-white">
                                    <i class="fa-solid fa-store me-1 text-warning"></i>
                                    <?= h($transfer['FromShopName']) ?>
                                </div>
                            </div>
                            <div class="col-6 text-end">
                                <small class="text-muted">目标店铺</small>
                                <div class="text-info">
                                    <i class="fa-solid fa-arrow-right me-1"></i>
                                    <?= h($transfer['ToShopName']) ?>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <small class="text-muted d-block">专辑信息</small>
                            <div class="text-white fw-bold"><?= h($transfer['ReleaseTitle']) ?></div>
                            <small class="text-muted"><?= h($transfer['ArtistName']) ?></small>
                        </div>

                        <div class="row">
                            <div class="col-4">
                                <small class="text-muted">成色</small>
                                <div><span class="badge bg-secondary"><?= h($transfer['ConditionGrade']) ?></span></div>
                            </div>
                            <div class="col-4 text-center">
                                <small class="text-muted">数量</small>
                                <div class="text-warning fw-bold fs-5"><?= $transfer['Quantity'] ?></div>
                            </div>
                            <div class="col-4 text-end">
                                <small class="text-muted">单价</small>
                                <div class="text-success fw-bold"><?= formatPrice($transfer['UnitPrice']) ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="card-footer bg-dark border-info">
                        <div class="d-flex gap-2">
                            <form method="POST" class="flex-grow-1">
                                <input type="hidden" name="transfer_ids" value="<?= h($transfer['TransferIDs']) ?>">
                                <input type="hidden" name="action" value="confirm_transfer">
                                <button type="submit" class="btn btn-info btn-sm w-100">
                                    <i class="fa-solid fa-truck me-1"></i>确认发货 (<?= $transfer['Quantity'] ?> 张)
                                </button>
                            </form>
                            <form method="POST">
                                <input type="hidden" name="transfer_ids" value="<?= h($transfer['TransferIDs']) ?>">
                                <input type="hidden" name="action" value="cancel_transfer">
                                <button type="submit" class="btn btn-outline-danger btn-sm"
                                        onclick="return confirm('确定要取消这 <?= $transfer['Quantity'] ?> 张的调货请求吗？')">
                                    <i class="fa-solid fa-ban me-1"></i>取消
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php elseif ($currentTab == 'receiving'): ?>
<!-- 【新增】待接收调货部分 -->
<div class="alert alert-success mb-4">
    <i class="fa-solid fa-info-circle me-2"></i>
    这里显示从其他店铺调拨过来、等待您确认收货的库存。确认收货后，库存将正式入库到您的店铺。
</div>

<?php if (empty($incomingTransfers)): ?>
    <div class="text-center py-5">
        <div class="display-1 text-secondary mb-3"><i class="fa-solid fa-box-open"></i></div>
        <h3 class="text-white">没有待接收的调货</h3>
        <p class="text-muted">当有从其他店铺调拨过来的货物时会显示在这里。</p>
    </div>
<?php else: ?>
    <div class="row row-cols-1 row-cols-lg-2 g-4">
        <?php foreach ($incomingTransfers as $transfer): ?>
            <div class="col">
                <div class="card bg-dark border-success h-100">
                    <div class="card-header bg-dark border-success d-flex justify-content-between align-items-center">
                        <div>
                            <strong class="text-success">调货批次</strong>
                            <span class="badge bg-primary ms-2">运输中</span>
                            <span class="badge bg-success ms-1"><?= $transfer['Quantity'] ?> 张</span>
                        </div>
                        <small class="text-muted"><?= date('M d, H:i', strtotime($transfer['TransferDate'])) ?></small>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-6">
                                <small class="text-muted">来源店铺</small>
                                <div class="text-white">
                                    <i class="fa-solid fa-store me-1 text-info"></i>
                                    <?= h($transfer['FromShopName']) ?>
                                </div>
                            </div>
                            <div class="col-6 text-end">
                                <small class="text-muted">目标店铺</small>
                                <div class="text-success">
                                    <i class="fa-solid fa-arrow-right me-1"></i>
                                    <?= h($transfer['ToShopName']) ?>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <small class="text-muted d-block">专辑信息</small>
                            <div class="text-white fw-bold"><?= h($transfer['ReleaseTitle']) ?></div>
                            <small class="text-muted"><?= h($transfer['ArtistName']) ?></small>
                        </div>

                        <div class="row">
                            <div class="col-4">
                                <small class="text-muted">成色</small>
                                <div><span class="badge bg-secondary"><?= h($transfer['ConditionGrade']) ?></span></div>
                            </div>
                            <div class="col-4 text-center">
                                <small class="text-muted">数量</small>
                                <div class="text-warning fw-bold fs-5"><?= $transfer['Quantity'] ?></div>
                            </div>
                            <div class="col-4 text-end">
                                <small class="text-muted">单价</small>
                                <div class="text-success fw-bold"><?= formatPrice($transfer['UnitPrice']) ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="card-footer bg-dark border-success">
                        <form method="POST" class="d-grid">
                            <input type="hidden" name="transfer_ids" value="<?= h($transfer['TransferIDs']) ?>">
                            <input type="hidden" name="action" value="receive_transfer">
                            <button type="submit" class="btn btn-success">
                                <i class="fa-solid fa-check me-1"></i>确认收货 (<?= $transfer['Quantity'] ?> 张)
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>