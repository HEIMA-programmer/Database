<?php
session_start();
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/functions.php';

// 权限检查
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['Staff', 'Manager'])) {
    header("Location: /login.php");
    exit();
}

if (empty($_SESSION['pos_cart'])) {
    header("Location: pos.php");
    exit();
}

$shopId = $_SESSION['shop_id'];
$cart = $_SESSION['pos_cart'];
$total = 0;
foreach ($cart as $item) $total += $item['price'];

try {
    $pdo->beginTransaction();

    // 1. 创建订单 (InStore, CustomerID 为 NULL 代表匿名)
    // Assignment 1 要求 "In-store transactions are handled via point-of-sales machines" [cite: 71]
    $sql = "INSERT INTO CustomerOrder (FulfilledByShopID, OrderDate, TotalAmount, OrderStatus, OrderType) 
            VALUES (:shop, NOW(), :total, 'Completed', 'InStore')";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':shop' => $shopId, ':total' => $total]);
    $orderId = $pdo->lastInsertId();

    // 2. 插入 OrderLine 并更新库存
    $lineSql = "INSERT INTO OrderLine (OrderID, StockItemID, PriceAtSale) VALUES (:oid, :sid, :price)";
    $stockSql = "UPDATE StockItem SET Status = 'Sold' WHERE StockItemID = :sid";
    
    $stmtLine = $pdo->prepare($lineSql);
    $stmtStock = $pdo->prepare($stockSql);

    foreach ($cart as $sid => $item) {
        // 记录销售明细
        $stmtLine->execute([
            ':oid' => $orderId, 
            ':sid' => $sid, 
            ':price' => $item['price']
        ]);
        
        // 标记库存已售出
        $stmtStock->execute([':sid' => $sid]);
    }

    $pdo->commit();
    
    $_SESSION['pos_cart'] = []; // 清空购物车
    flash("Transaction #$orderId completed successfully!", 'success');
    header("Location: pos.php");

} catch (Exception $e) {
    $pdo->rollBack();
    error_log($e->getMessage());
    flash("Error processing transaction.", 'danger');
    header("Location: pos.php");
}
?>