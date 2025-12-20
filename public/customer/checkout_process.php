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
    header("Location: cart.php");
    exit();
}

try {
    $pdo->beginTransaction();

    // A. 再次检查库存可用性 (FOR UPDATE 锁)
    $placeholders = implode(',', array_fill(0, count($cartIds), '?'));
    $chkSql = "SELECT StockItemID FROM StockItem WHERE StockItemID IN ($placeholders) AND Status = 'Available' FOR UPDATE";
    $stmt = $pdo->prepare($chkSql);
    $stmt->execute($cartIds);
    $availableItems = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (count($availableItems) != count($cartIds)) {
        throw new Exception("One or more items in your cart are no longer available.");
    }

    // B. 创建订单头
    // 默认由 Online Warehouse (ID=3) 履约，实际逻辑可扩展
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

    // C. 创建订单行 & 扣减库存
    $insLine = "INSERT INTO OrderLine (OrderID, StockItemID, PriceAtSale) VALUES (:oid, :sid, :price)";
    $updStock = "UPDATE StockItem SET Status = 'Sold' WHERE StockItemID = :sid";
    $stmtLine = $pdo->prepare($insLine);
    $stmtStock = $pdo->prepare($updStock);

    // 获取当前单价
    $priceSql = "SELECT StockItemID, UnitPrice FROM StockItem WHERE StockItemID IN ($placeholders)";
    $stmtPrice = $pdo->prepare($priceSql);
    $stmtPrice->execute($cartIds);
    $prices = $stmtPrice->fetchAll(PDO::FETCH_KEY_PAIR);

    foreach ($cartIds as $sid) {
        $price = $prices[$sid];
        $stmtLine->execute([':oid' => $orderId, ':sid' => $sid, ':price' => $price]);
        $stmtStock->execute([':sid' => $sid]);
    }

    // D. 积分与会员升级逻辑 (Membership Upgrade Logic)
    $pointsEarned = floor($totals['total']);
    
    // D1. 增加积分
    $updPoints = "UPDATE Customer SET Points = Points + :pts WHERE CustomerID = :cid";
    $stmt = $pdo->prepare($updPoints);
    $stmt->execute([':pts' => $pointsEarned, ':cid' => $customerId]);

    // D2. 检查升级资格
    // 获取最新积分
    $stmt = $pdo->prepare("SELECT Points, TierID FROM Customer WHERE CustomerID = ?");
    $stmt->execute([$customerId]);
    $custData = $stmt->fetch();
    $currentPoints = $custData['Points'];
    $currentTier = $custData['TierID'];

    // 查询所有等级规则
    $tiers = $pdo->query("SELECT * FROM MembershipTier ORDER BY MinPoints DESC")->fetchAll();
    
    $newTierId = $currentTier;
    $newTierName = '';

    foreach ($tiers as $tier) {
        if ($currentPoints >= $tier['MinPoints']) {
            $newTierId = $tier['TierID'];
            $newTierName = $tier['TierName'];
            break; // 找到满足条件的最高等级
        }
    }

    $upgradeMsg = "";
    if ($newTierId != $currentTier) {
        // 执行升级
        $updTier = $pdo->prepare("UPDATE Customer SET TierID = ? WHERE CustomerID = ?");
        $updTier->execute([$newTierId, $customerId]);
        
        // 更新 Session
        $_SESSION['tier_id'] = $newTierId;
        $upgradeMsg = " <strong>Congratulations! You've been upgraded to $newTierName Status!</strong>";
    }

    // 4. 提交事务
    $pdo->commit();

    // 5. 清理
    $_SESSION['cart'] = [];
    unset($_SESSION['checkout_totals']);

    flash("Order #$orderId placed successfully! You earned $pointsEarned points." . $upgradeMsg, 'success');
    header("Location: orders.php");
    exit();

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Checkout Error: " . $e->getMessage());
    flash("Checkout failed: " . $e->getMessage(), 'danger');
    header("Location: cart.php");
    exit();
}
?>