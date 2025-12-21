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
    flash("Cart is empty.", 'warning');
    header("Location: pos.php");
    exit();
}

$shopId = $_SESSION['shop_id'];
$cartIds = $_SESSION['pos_cart'];
$customerEmail = trim($_POST['customer_email'] ?? '');
$customerId = null;

try {
    $pdo->beginTransaction();

    // 1. 获取商品详情和总价
    $placeholders = implode(',', array_fill(0, count($cartIds), '?'));
    $stmt = $pdo->prepare("SELECT StockItemID, UnitPrice FROM StockItem WHERE StockItemID IN ($placeholders) AND Status = 'Available' FOR UPDATE");
    $stmt->execute($cartIds);
    $items = $stmt->fetchAll();

    if (count($items) != count($cartIds)) {
        throw new Exception("Some items are no longer available. Please clear cart and rescan.");
    }

    $total = 0;
    foreach ($items as $item) {
        $total += $item['UnitPrice'];
    }

    // 2. 查找关联会员
    if (!empty($customerEmail)) {
        $custStmt = $pdo->prepare("SELECT CustomerID, Name FROM Customer WHERE Email = ?");
        $custStmt->execute([$customerEmail]);
        $cust = $custStmt->fetch();
        if ($cust) {
            $customerId = $cust['CustomerID'];
        } else {
            // 如果输入了邮箱但没找到，是否报错？为了不中断结账，我们转为匿名，但给出提示
            // 或者抛出异常让店员核对。这里选择转为匿名并提示，保证效率。
            flash("Customer email not found. Proceeding as Guest.", 'warning');
        }
    }

    // 3. 创建订单
    $sql = "INSERT INTO CustomerOrder (CustomerID, FulfilledByShopID, OrderDate, TotalAmount, OrderStatus, OrderType) 
            VALUES (?, ?, NOW(), ?, 'Completed', 'InStore')";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$customerId, $shopId, $total]);
    $orderId = $pdo->lastInsertId();

    // 4. 插入 OrderLine 并更新库存
    $lineSql = "INSERT INTO OrderLine (OrderID, StockItemID, PriceAtSale) VALUES (?, ?, ?)";
    $stockSql = "UPDATE StockItem SET Status = 'Sold', DateSold = NOW() WHERE StockItemID = ?";
    
    $stmtLine = $pdo->prepare($lineSql);
    $stmtStock = $pdo->prepare($stockSql);

    foreach ($items as $item) {
        // 记录销售明细
        $stmtLine->execute([$orderId, $item['StockItemID'], $item['UnitPrice']]);
        // 标记库存已售出
        $stmtStock->execute([$item['StockItemID']]);
    }

    // 5. [New] 处理积分
    $upgradeMsg = "";
    if ($customerId) {
        $res = addPointsAndCheckUpgrade($pdo, $customerId, $total);
        if ($res && $res['upgraded']) {
            $upgradeMsg = " Customer upgraded to {$res['new_tier_name']}!";
        }
    }

    $pdo->commit();
    
    $_SESSION['pos_cart'] = []; // 清空购物车
    flash("Transaction #$orderId completed. Amount: " . formatPrice($total) . "." . $upgradeMsg, 'success');
    header("Location: pos.php");

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log($e->getMessage());
    flash("Error processing transaction: " . $e->getMessage(), 'danger');
    header("Location: pos.php");
}
?>