<?php
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/header.php'; // 为了加载 functions.php

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

// [Fix: Hardcoding] 动态获取线上仓库ID，不再写死 '3'
$warehouseId = getShopIdByType($pdo, 'Warehouse');

try {
    // 1. 开启事务
    $pdo->beginTransaction();

    // 2. 创建订单头 (CustomerOrder)
    // 先计算总金额 (应用生日折扣等逻辑应在这里再次校验，简化起见直接累加)
    foreach ($cart as $item) {
        $totalAmount += $item['price'] * $item['quantity'];
    }

    $stmt = $pdo->prepare("INSERT INTO CustomerOrder (CustomerID, OrderDate, TotalAmount, Status, Type) VALUES (?, NOW(), ?, 'Pending', 'Online')");
    $stmt->execute([$customerId, $totalAmount]);
    $orderId = $pdo->lastInsertId();

    // 3. 处理每一行订单项
    foreach ($cart as $releaseId => $item) {
        $qtyNeeded = $item['quantity'];
        
        // [Logic Fix] 查找该 Release 在仓库中状态为 'InStock' 的具体 StockItem
        // 使用 FOR UPDATE 锁定行，防止并发超卖
        $stockSql = "SELECT StockItemID FROM StockItem 
                     WHERE ReleaseID = ? AND LocationID = ? AND Status = 'InStock' 
                     LIMIT ? FOR UPDATE";
        $stmt = $pdo->prepare($stockSql);
        // 注意: PDO LIMIT 不支持绑定参数用于计算，需拼接或确信是整数
        // 为安全起见，这里用循环查找单件
        
        // 更稳健的做法：查出足够数量的 ID
        $stmt = $pdo->prepare("SELECT StockItemID FROM StockItem WHERE ReleaseID = ? AND LocationID = ? AND Status = 'InStock' LIMIT " . (int)$qtyNeeded . " FOR UPDATE");
        $stmt->execute([$releaseId, $warehouseId]);
        $stockItems = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (count($stockItems) < $qtyNeeded) {
            throw new Exception("Insufficient stock for album: " . $item['title']);
        }

        // 4. 将具体 StockItem 关联到 OrderLine 并更新状态
        foreach ($stockItems as $stockItemId) {
            // 插入 OrderLine
            $lineSql = "INSERT INTO OrderLine (OrderID, StockItemID, Quantity, PriceAtSale) VALUES (?, ?, 1, ?)";
            $pdo->prepare($lineSql)->execute([$orderId, $stockItemId, $item['price']]);

            // 更新 StockItem 状态为 'Sold'，并标记销售时间和订单号
            // 注意：这里我们假设 StockItem 表有 DateSold 字段，如果没有请检查 Schema
            // 根据 Assignment 1 反馈，需要 Traceability，所以更新状态是必须的
            $updateStock = "UPDATE StockItem SET Status = 'Sold', DateSold = NOW() WHERE StockItemID = ?";
            $pdo->prepare($updateStock)->execute([$stockItemId]);
        }
    }

    // 5. 提交事务
    $pdo->commit();

    // 清空购物车
    unset($_SESSION['cart']);
    flash("Order placed successfully! Order ID: #$orderId", 'success');
    header("Location: orders.php");
    exit();

} catch (Exception $e) {
    // 回滚事务
    $pdo->rollBack();
    flash("Order failed: " . $e->getMessage(), 'danger');
    header("Location: cart.php");
    exit();
}
?>