<?php
/**
 * 购物车页面
 * 
 * 【修复】强制单店铺购物限制：
 * - 购物车只能包含同一店铺的商品
 * - 切换店铺时自动清空购物车
 * - 显示当前选择的店铺信息
 */
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/auth_guard.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_procedures.php';
requireRole('Customer');

// 初始化购物车
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// ========== 处理POST请求 ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'add':
            $stockItemId = (int)($_POST['stock_item_id'] ?? 0);
            $shopId = (int)($_POST['shop_id'] ?? 0);
            
            if ($stockItemId > 0 && $shopId > 0) {
                // 验证店铺一致性
                if (!isset($_SESSION['selected_shop_id'])) {
                    $_SESSION['selected_shop_id'] = $shopId;
                } elseif ($_SESSION['selected_shop_id'] != $shopId) {
                    // 不允许跨店铺添加
                    flash('Cannot add items from different stores. Please complete or clear your current cart first.', 'danger');
                    header('Location: cart.php');
                    exit;
                }
                
                // 【架构重构Phase2】使用DBProcedures替换直接SQL
                $item = DBProcedures::validateCartItem($pdo, $stockItemId, $shopId);
                
                if ($item) {
                    // 检查是否已在购物车
                    if (!in_array($stockItemId, $_SESSION['cart'])) {
                        $_SESSION['cart'][] = $stockItemId;
                        flash('Item added to cart!', 'success');
                    } else {
                        flash('Item is already in your cart.', 'warning');
                    }
                } else {
                    flash('Item is no longer available.', 'danger');
                }
            }
            break;
            
        case 'remove':
            $stockItemId = (int)($_POST['stock_item_id'] ?? 0);
            $key = array_search($stockItemId, $_SESSION['cart']);
            if ($key !== false) {
                unset($_SESSION['cart'][$key]);
                $_SESSION['cart'] = array_values($_SESSION['cart']); // 重新索引
                // 【修复】购物车清空后，同时清理店铺选择
                if (empty($_SESSION['cart'])) {
                    unset($_SESSION['selected_shop_id']);
                }
                flash('Item removed from cart.', 'info');
            }
            break;

        // 【新增】批量删除同组商品
        case 'remove_group':
            $stockItemIds = $_POST['stock_item_ids'] ?? '';
            if ($stockItemIds) {
                $idsToRemove = array_map('intval', explode(',', $stockItemIds));
                $removedCount = 0;
                foreach ($idsToRemove as $id) {
                    $key = array_search($id, $_SESSION['cart']);
                    if ($key !== false) {
                        unset($_SESSION['cart'][$key]);
                        $removedCount++;
                    }
                }
                $_SESSION['cart'] = array_values($_SESSION['cart']); // 重新索引
                // 【修复】购物车清空后，同时清理店铺选择
                if (empty($_SESSION['cart'])) {
                    unset($_SESSION['selected_shop_id']);
                }
                flash("$removedCount item(s) removed from cart.", 'info');
            }
            break;

        case 'clear':
            $_SESSION['cart'] = [];
            // 【修复】清空购物车时同时清理店铺选择
            unset($_SESSION['selected_shop_id']);
            flash('Cart cleared.', 'info');
            break;
    }
    
    header('Location: cart.php');
    exit;
}

// ========== 获取购物车数据 ==========
$cartItems = [];
$cartItemsGrouped = []; // 【新增】分组后的购物车数据
$total = 0;
$shopInfo = null;

if (!empty($_SESSION['cart'])) {
    // 【架构重构Phase2】使用DBProcedures替换直接SQL
    $cartItems = DBProcedures::getCartItemsDetail($pdo, $_SESSION['cart']);

    // 验证所有商品属于同一店铺
    $shopIds = array_unique(array_column($cartItems, 'ShopID'));
    if (count($shopIds) > 1) {
        // 不应该发生，但作为安全检查
        flash('Cart contains items from multiple stores. Clearing cart.', 'danger');
        $_SESSION['cart'] = [];
        $cartItems = [];
    } else {
        // 计算总价
        foreach ($cartItems as $item) {
            $total += $item['UnitPrice'];
        }

        // 获取店铺信息
        if (!empty($cartItems)) {
            $shopInfo = [
                'ShopID' => $cartItems[0]['ShopID'],
                'Name' => $cartItems[0]['ShopName'],
                'Type' => $cartItems[0]['ShopType']
            ];
        }

        // 【新增】按Release+Condition+Price分组
        foreach ($cartItems as $item) {
            $key = $item['ReleaseID'] . '_' . $item['ConditionGrade'] . '_' . $item['UnitPrice'];
            if (!isset($cartItemsGrouped[$key])) {
                $cartItemsGrouped[$key] = [
                    'ReleaseID' => $item['ReleaseID'],
                    'Title' => $item['Title'],
                    'ArtistName' => $item['ArtistName'],
                    'ConditionGrade' => $item['ConditionGrade'],
                    'UnitPrice' => $item['UnitPrice'],
                    'Quantity' => 1,
                    'Subtotal' => $item['UnitPrice'],
                    'StockItemIds' => [$item['StockItemID']]
                ];
            } else {
                $cartItemsGrouped[$key]['Quantity']++;
                $cartItemsGrouped[$key]['Subtotal'] += $item['UnitPrice'];
                $cartItemsGrouped[$key]['StockItemIds'][] = $item['StockItemID'];
            }
        }
    }

    // 移除不可用的商品
    $availableIds = array_column($cartItems, 'StockItemID');
    $removedCount = count($_SESSION['cart']) - count($availableIds);
    if ($removedCount > 0) {
        $_SESSION['cart'] = $availableIds;
        flash("$removedCount item(s) were removed because they are no longer available.", 'warning');
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row mb-4">
    <div class="col">
        <h1 class="display-5 text-warning fw-bold">
            <i class="fa-solid fa-shopping-cart me-2"></i>Shopping Cart
        </h1>
    </div>
</div>

<?php if (empty($cartItems)): ?>
    <div class="text-center py-5">
        <div class="display-1 text-secondary mb-3"><i class="fa-solid fa-cart-shopping"></i></div>
        <h3 class="text-white">Your cart is empty</h3>
        <p class="cart-empty-message">Browse our catalog and add some records!</p>
        <a href="catalog.php" class="btn btn-warning mt-3">
            <i class="fa-solid fa-store me-1"></i> Browse Catalog
        </a>
    </div>
<?php else: ?>
    <!-- 店铺信息提示 -->
    <div class="alert alert-info bg-dark border-info mb-4">
        <div class="d-flex align-items-center justify-content-between">
            <div>
                <i class="fa-solid <?= $shopInfo['Type'] == 'Warehouse' ? 'fa-warehouse' : 'fa-store' ?> me-2 text-info"></i>
                <strong class="text-info"><?= h($shopInfo['Name']) ?></strong>
                <?php if ($shopInfo['Type'] == 'Warehouse'): ?>
                    <span class="text-muted ms-2">| Shipping only</span>
                <?php else: ?>
                    <span class="text-muted ms-2">| Pickup or shipping available</span>
                <?php endif; ?>
            </div>
            <span class="badge bg-warning text-dark"><?= count($cartItems) ?> item(s)</span>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card bg-dark border-secondary">
                <div class="card-header bg-dark border-secondary">
                    <h5 class="mb-0 text-warning">Cart Items</h5>
                </div>
                <div class="card-body p-0">
                    <!-- 【重构】使用分组后的数据显示，合并同release同condition的商品 -->
                    <table class="table table-dark mb-0">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Condition</th>
                                <th class="text-center">Qty</th>
                                <th class="text-end">Unit Price</th>
                                <th class="text-end">Subtotal</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cartItemsGrouped as $group): ?>
                            <tr>
                                <td>
                                    <strong class="text-white"><?= h($group['Title']) ?></strong><br>
                                    <small class="text-warning"><?= h($group['ArtistName']) ?></small>
                                </td>
                                <td>
                                    <?php
                                    $condClass = match($group['ConditionGrade']) {
                                        'New', 'Mint' => 'bg-success',
                                        'NM', 'VG+'   => 'bg-info text-dark',
                                        default       => 'bg-secondary'
                                    };
                                    ?>
                                    <span class="badge <?= $condClass ?>"><?= h($group['ConditionGrade']) ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-warning text-dark"><?= $group['Quantity'] ?></span>
                                </td>
                                <td class="text-end text-muted"><?= formatPrice($group['UnitPrice']) ?></td>
                                <td class="text-end text-white fw-bold"><?= formatPrice($group['Subtotal']) ?></td>
                                <td class="text-center">
                                    <?php if ($group['Quantity'] == 1): ?>
                                        <!-- 单个商品直接删除 -->
                                        <form method="POST" class="d-inline" id="removeForm_<?= $group['StockItemIds'][0] ?>">
                                            <input type="hidden" name="action" value="remove">
                                            <input type="hidden" name="stock_item_id" value="<?= $group['StockItemIds'][0] ?>">
                                            <button type="button" class="btn btn-outline-danger btn-sm" title="Remove"
                                                    onclick="showConfirmModal('Remove Item', 'Are you sure you want to remove this item from your cart?', 'removeForm_<?= $group['StockItemIds'][0] ?>')">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <!-- 多个商品提供删除一个或全部的选项 -->
                                        <div class="btn-group btn-group-sm">
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="action" value="remove">
                                                <input type="hidden" name="stock_item_id" value="<?= $group['StockItemIds'][0] ?>">
                                                <button type="submit" class="btn btn-outline-warning btn-sm" title="Remove one">
                                                    <i class="fa-solid fa-minus"></i>
                                                </button>
                                            </form>
                                            <form method="POST" class="d-inline" id="removeGroupForm_<?= $group['StockItemIds'][0] ?>">
                                                <input type="hidden" name="action" value="remove_group">
                                                <input type="hidden" name="stock_item_ids" value="<?= implode(',', $group['StockItemIds']) ?>">
                                                <button type="button" class="btn btn-outline-danger btn-sm" title="Remove all"
                                                        onclick="showConfirmModal('Remove Items', 'Remove all <?= $group['Quantity'] ?> items of this type?', 'removeGroupForm_<?= $group['StockItemIds'][0] ?>')">
                                                    <i class="fa-solid fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="mt-3 d-flex justify-content-between">
                <a href="catalog.php" class="btn btn-outline-secondary">
                    <i class="fa-solid fa-arrow-left me-1"></i> Continue Shopping
                </a>
                <form method="POST" class="d-inline" id="clearCartForm">
                    <input type="hidden" name="action" value="clear">
                    <button type="button" class="btn btn-outline-danger"
                            onclick="showConfirmModal('Clear Cart', 'Are you sure you want to clear all items from your cart?', 'clearCartForm')">
                        <i class="fa-solid fa-trash me-1"></i> Clear Cart
                    </button>
                </form>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card bg-dark border-warning">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0"><i class="fa-solid fa-receipt me-2"></i>Order Summary</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-3">
                        <span class="text-muted">Subtotal (<?= count($cartItems) ?> items)</span>
                        <span class="text-white fw-bold"><?= formatPrice($total) ?></span>
                    </div>
                    
                    <hr class="border-secondary">
                    
                    <div class="d-flex justify-content-between mb-4">
                        <span class="fs-5 text-warning">Total</span>
                        <span class="fs-4 text-warning fw-bold"><?= formatPrice($total) ?></span>
                    </div>
                    
                    <div class="d-grid">
                        <a href="checkout.php" class="btn btn-warning btn-lg">
                            <i class="fa-solid fa-credit-card me-1"></i> Proceed to Checkout
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- 履行方式预览 -->
            <div class="card bg-dark border-secondary mt-3">
                <div class="card-body">
                    <h6 class="text-warning mb-3"><i class="fa-solid fa-truck me-2"></i>Fulfillment Options</h6>
                    <?php if ($shopInfo['Type'] == 'Warehouse'): ?>
                        <div class="d-flex align-items-center text-muted">
                            <i class="fa-solid fa-shipping-fast me-2"></i>
                            <span>Home Delivery (Standard Shipping)</span>
                        </div>
                    <?php else: ?>
                        <div class="d-flex align-items-center text-muted mb-2">
                            <i class="fa-solid fa-store me-2"></i>
                            <span>In-Store Pickup at <?= h($shopInfo['Name']) ?></span>
                        </div>
                        <div class="d-flex align-items-center text-muted">
                            <i class="fa-solid fa-shipping-fast me-2"></i>
                            <span>Home Delivery</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Custom Confirm Modal -->
<div class="modal fade custom-confirm-modal" id="confirmModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="confirmModalTitle">Confirm Action</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="confirmModalBody">
                Are you sure?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmModalBtn">Confirm</button>
            </div>
        </div>
    </div>
</div>

<script>
let confirmFormId = null;

function showConfirmModal(title, message, formId) {
    confirmFormId = formId;
    document.getElementById('confirmModalTitle').textContent = title;
    document.getElementById('confirmModalBody').textContent = message;
    const modal = new bootstrap.Modal(document.getElementById('confirmModal'));
    modal.show();
}

document.getElementById('confirmModalBtn').addEventListener('click', function() {
    if (confirmFormId) {
        document.getElementById(confirmFormId).submit();
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>