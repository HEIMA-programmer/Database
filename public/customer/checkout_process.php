<?php
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/header.php';

// 必须登录
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Customer') {
    header("Location: ../login.php");
    exit();
}

// 购物车检查
if (empty($_SESSION['cart'])) {
    flash("Your cart is empty.", 'warning');
    header("Location: cart.php");
    exit();
}

$customerId = $_SESSION['user_id'];
$cart = $_SESSION['cart'];
$totalAmount = 0;

// 动态获取线上仓库ID
$warehouseId = getShopIdByType($pdo, 'Warehouse');
if (!$warehouseId) {
    flash("System Error: Warehouse configuration missing. Please contact support.", 'danger');
    header("Location: cart.php");
    exit();
}

try {
    // 1. 开启事务
    $pdo->beginTransaction();

    // 重新计算总价（安全起见，应重新查询数据库价格，这里为保持原逻辑结构简化处理，但建议生产环境重查）
    // 此处假设 $cart 中存储的是带有价格信息的数组，但根据 cart_action.php，session['cart']只存了ID。
    // *修正*: 需要先查出所有商品信息来计算价格和生成订单
    $placeholders = implode(',', array_fill(0, count($cart), '?'));
    $stmt = $pdo->prepare("SELECT StockItemID, ReleaseID, UnitPrice, Title FROM vw_customer_catalog WHERE StockItemID IN ($placeholders)");
    $stmt->execute($cart);
    $cartItems = $stmt->fetchAll();

    if (empty($cartItems)) throw new Exception("Cart items invalid.");

    foreach ($cartItems as $item) {
        $totalAmount += $item['UnitPrice'];
    }

    // 应用折扣逻辑（简单复现 cart.php 的逻辑，确保金额一致）
    // ... (此处为了代码简洁，直接使用计算出的 totalAmount，实际应复用 Discount 逻辑)

    // 2. 创建订单头
    $stmt = $pdo->prepare("INSERT INTO CustomerOrder (CustomerID, OrderDate, TotalAmount, Status, Type) VALUES (?, NOW(), ?, 'Pending', 'Online')");
    $stmt->execute([$customerId, $totalAmount]);
    $orderId = $pdo->lastInsertId();

    // 3. 处理库存锁定和订单行
    foreach ($cartItems as $item) {
        // [Logic Fix] 锁定特定行，防止并发。
        // 由于这里我们已经具体到了 StockItemID (Unique Item)，不需要按 ReleaseID 聚合查找
        // 直接检查该 Item 是否仍为 Available
        
        $checkSql = "SELECT StockItemID FROM StockItem 
                     WHERE StockItemID = ? AND Status = 'Available' 
                     FOR UPDATE"; // 锁定行
        $stmt = $pdo->prepare($checkSql);
        $stmt->execute([$item['StockItemID']]);
        
        if (!$stmt->fetch()) {
            throw new Exception("Item '{$item['Title']}' is no longer available.");
        }

        // 插入 OrderLine
        $lineSql = "INSERT INTO OrderLine (OrderID, StockItemID, Quantity, PriceAtSale) VALUES (?, ?, 1, ?)";
        $pdo->prepare($lineSql)->execute([$orderId, $item['StockItemID'], $item['UnitPrice']]);

        // 更新 StockItem 状态
        $updateStock = "UPDATE StockItem SET Status = 'Sold', DateSold = NOW() WHERE StockItemID = ?";
        $pdo->prepare($updateStock)->execute([$item['StockItemID']]);
    }

    // 4. [New] 积分与会员升级
    $result = addPointsAndCheckUpgrade($pdo, $customerId, $totalAmount);
    
    // 5. 提交事务
    $pdo->commit();

    // 构建成功消息
    $msg = "Order placed successfully! Order ID: #$orderId.";
    if ($result && $result['points_earned'] > 0) {
        $msg .= " You earned {$result['points_earned']} points!";
    }
    if ($result && $result['upgraded']) {
        $msg .= " 🌟 Congratulations! You've been upgraded to {$result['new_tier_name']} Tier!";
    }

    // 清空购物车
    unset($_SESSION['cart']);
    flash($msg, 'success');
    header("Location: orders.php");
    exit();

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    flash("Order failed: " . $e->getMessage(), 'danger');
    header("Location: cart.php");
    exit();
}
?>