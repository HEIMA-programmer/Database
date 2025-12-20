<?php
session_start();
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/functions.php';

// 1. 基础验证
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Customer') {
    header("Location: /login.php");
    exit();
}

$cartIds = $_SESSION['cart'] ?? [];
if (empty($cartIds)) {
    flash('Cart is empty.', 'warning');
    header("Location: catalog.php");
    exit();
}

// 2. 准备数据
$customerId = $_SESSION['user_id'];
$orderType  = $_POST['order_type'] ?? 'Online';
$totals     = $_SESSION['checkout_totals'] ?? null;

if (!$totals) {
    // 如果 Session 过期，重回购物车重新计算
    header("Location: cart.php");
    exit();
}

try {
    // 3. 开始事务 (ACID)
    $pdo->beginTransaction();

    // A. 再次检查库存可用性 (防止并发购买)
    // 锁定这些行进行读取 (FOR UPDATE)
    $placeholders = implode(',', array_fill(0, count($cartIds), '?'));
    $chkSql = "SELECT StockItemID FROM StockItem WHERE StockItemID IN ($placeholders) AND Status = 'Available' FOR UPDATE";
    $stmt = $pdo->prepare($chkSql);
    $stmt->execute($cartIds);
    $availableItems = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (count($availableItems) != count($cartIds)) {
        throw new Exception("Some items in your cart are no longer available.");
    }

    // B. 创建订单头 (CustomerOrder)
    // 假设 FulfilledByShopID 为 3 (Online Warehouse) 对于在线订单
    // 实际逻辑可能需要根据库存位置拆单，这里简化处理：默认由 Online Warehouse 履约
    $warehouseId = 3; 
    
    $insOrder = "INSERT INTO CustomerOrder (CustomerID, FulfilledByShopID, TotalAmount, OrderType, OrderStatus) 
                 VALUES (:cust, :shop, :total, :type, 'Paid')";
    $stmt = $pdo->prepare($insOrder);
    $stmt->execute([
        ':cust'  => $customerId,
        ':shop'  => $warehouseId,
        ':total' => $totals['total'],
        ':type'  => $orderType
    ]);
    $orderId = $pdo->lastInsertId();

    // C. 创建订单行 (OrderLine) 并扣减库存
    $insLine = "INSERT INTO OrderLine (OrderID, StockItemID, PriceAtSale) VALUES (:oid, :sid, :price)";
    $updStock = "UPDATE StockItem SET Status = 'Sold' WHERE StockItemID = :sid";

    $stmtLine = $pdo->prepare($insLine);
    $stmtStock = $pdo->prepare($updStock);

    // 获取当前单价用于记录
    $priceSql = "SELECT StockItemID, UnitPrice FROM StockItem WHERE StockItemID IN ($placeholders)";
    $stmtPrice = $pdo->prepare($priceSql);
    $stmtPrice->execute($cartIds);
    $prices = $stmtPrice->fetchAll(PDO::FETCH_KEY_PAIR); // [ID => Price]

    foreach ($cartIds as $sid) {
        $price = $prices[$sid];
        
        // 插入行
        $stmtLine->execute([
            ':oid'   => $orderId,
            ':sid'   => $sid,
            ':price' => $price
        ]);

        // 更新库存
        $stmtStock->execute([':sid' => $sid]);
    }

    // D. 增加用户积分 (1 point per RMB) [cite: 52]
    $pointsEarned = floor($totals['total']);
    $updPoints = "UPDATE Customer SET Points = Points + :pts WHERE CustomerID = :cid";
    $stmtPoints = $pdo->prepare($updPoints);
    $stmtPoints->execute([':pts' => $pointsEarned, ':cid' => $customerId]);

    // 4. 提交事务
    $pdo->commit();

    // 5. 清理
    $_SESSION['cart'] = [];
    unset($_SESSION['checkout_totals']);

    flash("Order #$orderId placed successfully! You earned $pointsEarned points.", 'success');
    header("Location: orders.php");
    exit();

} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Checkout Error: " . $e->getMessage());
    flash("Checkout failed: " . $e->getMessage(), 'danger');
    header("Location: cart.php");
    exit();
}
?>