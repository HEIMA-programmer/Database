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
requireRole('Staff');

// 获取员工信息
$employeeId = $_SESSION['user']['EmployeeID'];
$stmt = $pdo->prepare("
    SELECT e.*, s.Name as ShopName, s.Type as ShopType
    FROM Employee e
    JOIN Shop s ON e.ShopID = s.ShopID
    WHERE e.EmployeeID = ?
");
$stmt->execute([$employeeId]);
$employee = $stmt->fetch(PDO::FETCH_ASSOC);
$shopId = $employee['ShopID'];

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
                // 验证库存属于本店铺且可用
                $stmt = $pdo->prepare("
                    SELECT si.*, r.Title, a.Name as ArtistName
                    FROM StockItem si
                    JOIN `Release` r ON si.ReleaseID = r.ReleaseID
                    JOIN Artist a ON r.ArtistID = a.ArtistID
                    WHERE si.StockItemID = ? AND si.ShopID = ? AND si.Status = 'Available'
                ");
                $stmt->execute([$stockItemId, $shopId]);
                $item = $stmt->fetch(PDO::FETCH_ASSOC);
                
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
            
            try {
                $pdo->beginTransaction();
                
                // 创建门店订单
                $stmt = $pdo->prepare("
                    INSERT INTO CustomerOrder (CustomerID, ShopID, OrderType, OrderStatus, FulfillmentType)
                    VALUES (?, ?, 'InStore', 'Pending', 'Pickup')
                ");
                $stmt->execute([$customerId ?: null, $shopId]);
                $orderId = $pdo->lastInsertId();
                
                // 添加订单行
                foreach ($_SESSION['pos_cart'] as $stockItemId) {
                    DBProcedures::addOrderItem($pdo, $orderId, $stockItemId);
                }
                
                // 【修复】直接完成订单 - 现在支持InStore订单从Pending完成
                DBProcedures::completeOrder($pdo, $orderId);
                
                $pdo->commit();
                
                // 清空购物车
                $_SESSION['pos_cart'] = [];
                
                flash("Sale completed! Order #$orderId", 'success');
                
            } catch (Exception $e) {
                $pdo->rollBack();
                flash('Checkout failed: ' . $e->getMessage(), 'danger');
            }
            break;
    }
    
    header('Location: pos.php');
    exit;
}

// 获取购物车商品
$cartItems = [];
$total = 0;
if (!empty($_SESSION['pos_cart'])) {
    $placeholders = implode(',', array_fill(0, count($_SESSION['pos_cart']), '?'));
    $stmt = $pdo->prepare("
        SELECT si.*, r.Title, a.Name as ArtistName
        FROM StockItem si
        JOIN `Release` r ON si.ReleaseID = r.ReleaseID
        JOIN Artist a ON r.ArtistID = a.ArtistID
        WHERE si.StockItemID IN ($placeholders) AND si.Status = 'Available'
    ");
    $stmt->execute($_SESSION['pos_cart']);
    $cartItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($cartItems as $item) {
        $total += $item['UnitPrice'];
    }
    
    // 清理已不可用的商品
    $availableIds = array_column($cartItems, 'StockItemID');
    $_SESSION['pos_cart'] = array_values(array_intersect($_SESSION['pos_cart'], $availableIds));
}

// 获取可用库存（用于搜索添加）
$search = $_GET['q'] ?? '';
$availableStock = [];
if ($search) {
    $stmt = $pdo->prepare("
        SELECT si.*, r.Title, a.Name as ArtistName
        FROM StockItem si
        JOIN `Release` r ON si.ReleaseID = r.ReleaseID
        JOIN Artist a ON r.ArtistID = a.ArtistID
        WHERE si.ShopID = ? AND si.Status = 'Available'
        AND (r.Title LIKE ? OR a.Name LIKE ?)
        ORDER BY r.Title
        LIMIT 20
    ");
    $searchParam = "%$search%";
    $stmt->execute([$shopId, $searchParam, $searchParam]);
    $availableStock = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// 获取客户列表（用于关联会员）
$stmt = $pdo->prepare("SELECT CustomerID, Name, Email, Phone FROM Customer ORDER BY Name LIMIT 100");
$stmt->execute();
$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/staff_nav.php';
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
                               placeholder="Search by title or artist..." value="<?= h($search) ?>">
                        <button type="submit" class="btn btn-warning">
                            <i class="fa-solid fa-search"></i>
                        </button>
                    </div>
                </form>
                
                <?php if ($search && !empty($availableStock)): ?>
                    <div class="list-group">
                        <?php foreach ($availableStock as $item): ?>
                            <?php if (!in_array($item['StockItemID'], $_SESSION['pos_cart'])): ?>
                            <div class="list-group-item bg-dark border-secondary d-flex justify-content-between align-items-center">
                                <div>
                                    <strong class="text-white"><?= h($item['Title']) ?></strong>
                                    <span class="text-warning">- <?= h($item['ArtistName']) ?></span>
                                    <span class="badge bg-secondary ms-2"><?= h($item['ConditionGrade']) ?></span>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="text-warning fw-bold"><?= formatPrice($item['UnitPrice']) ?></span>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="action" value="add_item">
                                        <input type="hidden" name="stock_item_id" value="<?= $item['StockItemID'] ?>">
                                        <button type="submit" class="btn btn-success btn-sm">
                                            <i class="fa-solid fa-plus"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php elseif ($search): ?>
                    <p class="text-muted text-center">No items found matching "<?= h($search) ?>"</p>
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

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>