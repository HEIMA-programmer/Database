<?php
/**
 * POS销售点页面
 * 
 * 【修复】使用修改后的sp_complete_order，支持从Pending直接完成
 * 【限制】只有门店员工可以访问，仓库员工无此功能
 */
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/auth_guard.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_procedures.php';
requireRole('Staff');

// 【修复】使用正确的Session结构
$employeeId = $_SESSION['user_id'];
$shopId = $_SESSION['shop_id'];
// 【架构重构Phase2】使用DBProcedures替换直接SQL
$employee = DBProcedures::getEmployeeShopInfo($pdo, $employeeId);

// 仓库员工不能使用POS
if ($employee['ShopType'] == 'Warehouse') {
    flash('POS is only available at retail locations.', 'warning');
    header('Location: fulfillment.php');
    exit;
}

// 初始化POS购物车
if (!isset($_SESSION['pos_cart'])) {
    $_SESSION['pos_cart'] = [];
}

// 处理操作
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'add_item':
            $stockItemId = (int)($_POST['stock_item_id'] ?? 0);
            if ($stockItemId > 0) {
                // 【架构重构Phase2】使用DBProcedures替换直接SQL
                $item = DBProcedures::validatePosCartItem($pdo, $stockItemId, $shopId);

                if ($item && !in_array($stockItemId, $_SESSION['pos_cart'])) {
                    $_SESSION['pos_cart'][] = $stockItemId;
                    flash('Item added to sale.', 'success');
                } elseif (in_array($stockItemId, $_SESSION['pos_cart'])) {
                    flash('Item already in cart.', 'warning');
                } else {
                    flash('Item not available.', 'danger');
                }
            }
            break;

        // 【新增】批量添加 - 按Release和Condition添加多个
        case 'add_multiple':
            $releaseId = (int)($_POST['release_id'] ?? 0);
            $condition = $_POST['condition'] ?? '';
            $unitPrice = (float)($_POST['unit_price'] ?? 0);
            $quantity = (int)($_POST['quantity'] ?? 1);

            if ($releaseId > 0 && $condition && $quantity > 0) {
                // 【架构重构Phase2】使用DBProcedures替换直接SQL
                $allIds = DBProcedures::getPosAvailableStockIds($pdo, $shopId, $releaseId, $condition, $unitPrice);

                // 排除已在购物车中的
                $availableIds = array_diff($allIds, $_SESSION['pos_cart']);
                $toAdd = array_slice($availableIds, 0, $quantity);

                if (count($toAdd) > 0) {
                    $_SESSION['pos_cart'] = array_merge($_SESSION['pos_cart'], $toAdd);
                    $addedCount = count($toAdd);
                    flash("$addedCount item(s) added to sale.", 'success');
                } else {
                    flash('No available items to add.', 'warning');
                }
            }
            break;
            
        case 'remove_item':
            $stockItemId = (int)($_POST['stock_item_id'] ?? 0);
            $key = array_search($stockItemId, $_SESSION['pos_cart']);
            if ($key !== false) {
                unset($_SESSION['pos_cart'][$key]);
                $_SESSION['pos_cart'] = array_values($_SESSION['pos_cart']);
                flash('Item removed.', 'info');
            }
            break;
            
        case 'clear_cart':
            $_SESSION['pos_cart'] = [];
            flash('Cart cleared.', 'info');
            break;
            
        case 'checkout':
            $customerId = (int)($_POST['customer_id'] ?? 0);

            if (empty($_SESSION['pos_cart'])) {
                flash('Cart is empty.', 'warning');
                break;
            }

            // 【架构重构Phase2】使用DBProcedures替换直接SQL
            $result = DBProcedures::createPosOrder($pdo, $customerId ?: null, $employeeId, $shopId, $_SESSION['pos_cart']);

            if (isset($result['order_id']) && $result['order_id'] > 0) {
                $_SESSION['pos_cart'] = [];
                flash("Sale completed! Order #{$result['order_id']}", 'success');
            } elseif (isset($result['error'])) {
                if ($result['error'] === 'no_available_items') {
                    flash('Checkout failed: Some items are no longer available.', 'danger');
                } else {
                    flash('Checkout failed: ' . $result['error'], 'danger');
                }
            } else {
                flash('Checkout failed: Unknown error.', 'danger');
            }
            break;
    }
    
    header('Location: pos.php');
    exit;
}

// 获取购物车商品
// 【架构重构Phase2】使用DBProcedures替换直接SQL
$cartItems = [];
$total = 0;
if (!empty($_SESSION['pos_cart'])) {
    $cartItems = DBProcedures::getCartItemsDetail($pdo, $_SESSION['pos_cart']);

    foreach ($cartItems as $item) {
        $total += $item['UnitPrice'];
    }

    // 清理已不可用的商品
    $availableIds = array_column($cartItems, 'StockItemID');
    $_SESSION['pos_cart'] = array_values(array_intersect($_SESSION['pos_cart'], $availableIds));
}

// 【架构重构Phase2】获取店铺库存分组数据
$search = $_GET['q'] ?? '';
$availableStockGrouped = DBProcedures::getPosStockGrouped($pdo, $shopId, $search);

// 计算每组中已在购物车的数量
foreach ($availableStockGrouped as &$group) {
    if ($group['Quantity'] > 0) {
        // 【架构重构Phase2】使用DBProcedures获取库存ID
        $allIds = DBProcedures::getPosAvailableStockIds($pdo, $shopId, $group['ReleaseID'], $group['ConditionGrade'], $group['UnitPrice']);

        // 计算已在购物车中的数量
        $inCartCount = count(array_intersect($allIds, $_SESSION['pos_cart']));
        $group['AvailableQuantity'] = $group['Quantity'];
        $group['InCartCount'] = $inCartCount;
        $group['RemainingQuantity'] = $group['Quantity'] - $inCartCount;
        $group['AllStockIds'] = $allIds;
    } else {
        $group['AvailableQuantity'] = 0;
        $group['InCartCount'] = 0;
        $group['RemainingQuantity'] = 0;
        $group['AllStockIds'] = [];
    }
}
unset($group);

// 【架构重构Phase2】获取客户列表
$customers = DBProcedures::getCustomerListSimple($pdo, 100);

// 【新增】获取历史交易记录
$posHistory = DBProcedures::getPosHistory($pdo, $shopId, 10);

require_once __DIR__ . '/../../includes/header.php';
// 【修复】移除staff_nav.php，因为header.php已包含员工导航菜单
?>

<div class="row mb-4">
    <div class="col">
        <h1 class="display-5 text-warning fw-bold">
            <i class="fa-solid fa-cash-register me-2"></i>Point of Sale
        </h1>
        <p class="text-muted">
            <i class="fa-solid fa-store me-1"></i><?= h($employee['ShopName']) ?>
        </p>
    </div>
</div>

<div class="row">
    <!-- 左侧：商品搜索和添加 -->
    <div class="col-lg-7">
        <div class="card bg-dark border-secondary mb-4">
            <div class="card-header bg-dark border-secondary">
                <h5 class="mb-0 text-warning"><i class="fa-solid fa-search me-2"></i>Find Items</h5>
            </div>
            <div class="card-body">
                <form method="GET" class="mb-3">
                    <div class="input-group">
                        <input type="text" name="q" class="form-control bg-dark text-white border-secondary"
                               placeholder="Search to filter by title or artist..." value="<?= h($search) ?>">
                        <button type="submit" class="btn btn-warning">
                            <i class="fa-solid fa-search"></i>
                        </button>
                        <?php if ($search): ?>
                            <a href="pos.php" class="btn btn-outline-secondary">
                                <i class="fa-solid fa-times"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                    <small class="text-muted">Showing all albums. Use search to filter results.</small>
                </form>

                <?php if (!empty($availableStockGrouped)): ?>
                    <!-- 【重构】分组显示，支持数量选择，默认显示所有 -->
                    <div class="list-group" id="searchResults" style="max-height: 500px; overflow-y: auto;">
                        <?php foreach ($availableStockGrouped as $group): ?>
                            <?php
                            $hasStock = $group['RemainingQuantity'] > 0;
                            $itemClass = $hasStock ? '' : 'opacity-50';
                            ?>
                            <div class="list-group-item bg-dark border-secondary <?= $itemClass ?>">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong class="<?= $hasStock ? 'text-white' : 'text-muted' ?>"><?= h($group['Title']) ?></strong>
                                        <span class="<?= $hasStock ? 'text-warning' : 'text-secondary' ?>">- <?= h($group['ArtistName']) ?></span>
                                        <span class="badge bg-secondary ms-2"><?= h($group['ConditionGrade']) ?></span>
                                        <?php if ($hasStock): ?>
                                            <span class="badge bg-info ms-1"><?= $group['RemainingQuantity'] ?> available</span>
                                            <?php if ($group['InCartCount'] > 0): ?>
                                                <span class="badge bg-success ms-1"><?= $group['InCartCount'] ?> in cart</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="badge bg-danger ms-1">0 in stock</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="<?= $hasStock ? 'text-warning' : 'text-muted' ?> fw-bold">
                                        <?= $group['UnitPrice'] > 0 ? formatPrice($group['UnitPrice']) : '--' ?>
                                    </div>
                                </div>
                                <?php if ($hasStock): ?>
                                    <form method="POST" class="mt-2 add-item-form">
                                        <input type="hidden" name="action" value="add_multiple">
                                        <input type="hidden" name="release_id" value="<?= $group['ReleaseID'] ?>">
                                        <input type="hidden" name="condition" value="<?= h($group['ConditionGrade']) ?>">
                                        <input type="hidden" name="unit_price" value="<?= $group['UnitPrice'] ?>">
                                        <div class="input-group input-group-sm" style="max-width: 200px; margin-left: auto;">
                                            <span class="input-group-text bg-secondary text-white border-secondary">Qty</span>
                                            <select name="quantity" class="form-select bg-dark text-white border-secondary">
                                                <?php for ($i = 1; $i <= $group['RemainingQuantity']; $i++): ?>
                                                    <option value="<?= $i ?>"><?= $i ?></option>
                                                <?php endfor; ?>
                                            </select>
                                            <button type="submit" class="btn btn-success">
                                                <i class="fa-solid fa-plus me-1"></i>Add
                                            </button>
                                        </div>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- 【新增】保留搜索词，使用AJAX避免页面刷新 -->
                    <script>
                    document.querySelectorAll('.add-item-form').forEach(function(form) {
                        form.addEventListener('submit', function(e) {
                            e.preventDefault();
                            const formData = new FormData(this);

                            fetch('pos.php', {
                                method: 'POST',
                                body: formData
                            }).then(response => {
                                // 刷新页面但保留搜索词
                                const currentSearch = '<?= h($search) ?>';
                                if (currentSearch) {
                                    window.location.href = 'pos.php?q=' + encodeURIComponent(currentSearch);
                                } else {
                                    window.location.href = 'pos.php';
                                }
                            }).catch(error => {
                                console.error('Error:', error);
                                window.location.reload();
                            });
                        });
                    });
                    </script>
                <?php else: ?>
                    <p class="text-muted text-center py-4">
                        <i class="fa-solid fa-compact-disc fa-3x mb-3 d-block opacity-50"></i>
                        No albums in the system yet.
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- 右侧：购物车和结账 -->
    <div class="col-lg-5">
        <div class="card bg-dark border-warning sticky-top" style="top: 20px;">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0"><i class="fa-solid fa-shopping-cart me-2"></i>Current Sale</h5>
            </div>
            <div class="card-body">
                <?php if (empty($cartItems)): ?>
                    <p class="text-muted text-center py-4">
                        <i class="fa-solid fa-cart-shopping fa-3x mb-3 d-block"></i>
                        No items in cart. Search and add items to begin.
                    </p>
                <?php else: ?>
                    <div class="list-group mb-3">
                        <?php foreach ($cartItems as $item): ?>
                            <div class="list-group-item bg-dark border-secondary d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="text-white"><?= h($item['Title']) ?></div>
                                    <small class="text-muted"><?= h($item['ArtistName']) ?> | <?= h($item['ConditionGrade']) ?></small>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="text-warning"><?= formatPrice($item['UnitPrice']) ?></span>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="action" value="remove_item">
                                        <input type="hidden" name="stock_item_id" value="<?= $item['StockItemID'] ?>">
                                        <button type="submit" class="btn btn-outline-danger btn-sm">
                                            <i class="fa-solid fa-times"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="d-flex justify-content-between mb-3 fs-4">
                        <span class="text-white">Total:</span>
                        <span class="text-warning fw-bold"><?= formatPrice($total) ?></span>
                    </div>
                    
                    <hr class="border-secondary">
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="checkout">
                        
                        <div class="mb-3">
                            <label class="form-label text-muted">Link to Customer (Optional)</label>
                            <select name="customer_id" class="form-select bg-dark text-white border-secondary">
                                <option value="">Walk-in Customer</option>
                                <?php foreach ($customers as $customer): ?>
                                    <option value="<?= $customer['CustomerID'] ?>">
                                        <?= h($customer['Name']) ?> (<?= h($customer['Email']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-success btn-lg">
                                <i class="fa-solid fa-check me-1"></i> Complete Sale
                            </button>
                        </div>
                    </form>
                    
                    <form method="POST" class="mt-2">
                        <input type="hidden" name="action" value="clear_cart">
                        <div class="d-grid">
                            <button type="submit" class="btn btn-outline-danger" 
                                    onclick="return confirm('Clear all items?')">
                                <i class="fa-solid fa-trash me-1"></i> Clear Cart
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- 【新增】历史交易记录 -->
<div class="row mt-5">
    <div class="col-12">
        <div class="card bg-dark border-secondary">
            <div class="card-header bg-dark border-secondary">
                <h5 class="mb-0 text-warning">
                    <i class="fa-solid fa-history me-2"></i>Recent Sales History
                </h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($posHistory)): ?>
                    <div class="p-4 text-center text-muted">
                        <i class="fa-solid fa-receipt fa-3x mb-3 d-block opacity-50"></i>
                        No sales history yet.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-dark table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Order #</th>
                                    <th>Date</th>
                                    <th>Customer</th>
                                    <th>Items</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($posHistory as $order): ?>
                                <tr>
                                    <td>#<?= $order['OrderID'] ?></td>
                                    <td><?= formatDate($order['OrderDate']) ?></td>
                                    <td><?= h($order['CustomerName']) ?></td>
                                    <td><span class="badge bg-secondary"><?= $order['ItemCount'] ?> items</span></td>
                                    <td class="text-warning fw-bold"><?= formatPrice($order['TotalAmount']) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $order['OrderStatus'] == 'Completed' ? 'success' : 'info' ?>">
                                            <?= h($order['OrderStatus']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-outline-info btn-sm"
                                                data-bs-toggle="modal" data-bs-target="#orderDetailModal"
                                                onclick="loadOrderDetail(<?= $order['OrderID'] ?>)">
                                            <i class="fa-solid fa-eye me-1"></i>Detail
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- 订单详情模态框 -->
<div class="modal fade" id="orderDetailModal" tabindex="-1" aria-labelledby="orderDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content bg-dark border-warning">
            <div class="modal-header border-secondary">
                <h5 class="modal-title text-warning" id="orderDetailModalLabel">
                    <i class="fa-solid fa-receipt me-2"></i>Order Details
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="orderDetailContent">
                <div class="text-center py-4">
                    <div class="spinner-border text-warning" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php
// 预加载订单详情数据
// 【架构重构Phase2】使用DBProcedures替换直接SQL
$orderDetailsData = [];
foreach ($posHistory as $order) {
    $orderId = $order['OrderID'];
    $orderDetailsData[$orderId] = [
        'info' => $order,
        'items' => DBProcedures::getOrderLineDetail($pdo, $orderId)
    ];
}
?>

<script>
const orderDetailsData = <?= json_encode($orderDetailsData) ?>;

function loadOrderDetail(orderId) {
    const contentEl = document.getElementById('orderDetailContent');
    const titleEl = document.getElementById('orderDetailModalLabel');

    if (orderDetailsData[orderId]) {
        const data = orderDetailsData[orderId];
        titleEl.innerHTML = '<i class="fa-solid fa-receipt me-2"></i>Order #' + orderId + ' Details';

        let html = '<div class="mb-3 p-3 bg-secondary bg-opacity-25 rounded">';
        html += '<div class="row">';
        html += '<div class="col-6"><strong class="text-muted">Customer:</strong> <span class="text-white">' + (data.info.CustomerName || 'Walk-in') + '</span></div>';
        html += '<div class="col-6"><strong class="text-muted">Date:</strong> <span class="text-white">' + data.info.OrderDate + '</span></div>';
        html += '</div>';
        html += '<div class="row mt-2">';
        html += '<div class="col-6"><strong class="text-muted">Status:</strong> <span class="badge bg-' + (data.info.OrderStatus == 'Completed' ? 'success' : 'info') + '">' + data.info.OrderStatus + '</span></div>';
        html += '<div class="col-6"><strong class="text-muted">Total:</strong> <span class="text-warning fw-bold">¥' + parseFloat(data.info.TotalAmount).toFixed(2) + '</span></div>';
        html += '</div></div>';

        html += '<h6 class="text-warning mt-3 mb-2"><i class="fa-solid fa-compact-disc me-2"></i>Items (' + data.items.length + ')</h6>';
        html += '<div class="table-responsive"><table class="table table-dark table-sm mb-0">';
        html += '<thead><tr><th>Release</th><th>Artist</th><th>Condition</th><th>Genre</th><th>Year</th><th>Price</th></tr></thead>';
        html += '<tbody>';

        data.items.forEach(function(item) {
            html += '<tr>';
            html += '<td class="text-white">' + item.Title + '</td>';
            html += '<td class="text-muted">' + item.ArtistName + '</td>';
            html += '<td><span class="badge bg-secondary">' + item.ConditionGrade + '</span></td>';
            html += '<td class="text-muted">' + (item.Genre || '-') + '</td>';
            html += '<td class="text-muted">' + (item.ReleaseYear || '-') + '</td>';
            html += '<td class="text-warning">¥' + parseFloat(item.PriceAtSale).toFixed(2) + '</td>';
            html += '</tr>';
        });

        html += '</tbody></table></div>';
        contentEl.innerHTML = html;
    } else {
        contentEl.innerHTML = '<div class="text-center py-4 text-danger"><i class="fa-solid fa-exclamation-circle fa-3x mb-3 d-block"></i>Order not found</div>';
    }
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>