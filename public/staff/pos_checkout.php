<?php
session_start();
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_procedures.php';

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
$employeeId = $_SESSION['user_id'];
$cartIds = $_SESSION['pos_cart'];
$customerEmail = trim($_POST['customer_email'] ?? '');
$customerId = null;

try {
    $pdo->beginTransaction();

    // 1. 从视图获取商品详情（使用POS视图）
    $placeholders = implode(',', array_fill(0, count($cartIds), '?'));
    $stmt = $pdo->prepare("SELECT StockItemID, UnitPrice, Title FROM vw_staff_pos_lookup WHERE StockItemID IN ($placeholders) AND Status = 'Available' FOR UPDATE");
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
            flash("Customer email not found. Proceeding as Guest.", 'warning');
        }
    }

    // 3. 使用存储过程创建订单
    $orderId = DBProcedures::createCustomerOrder($pdo, $customerId, $shopId, $employeeId, 'InStore');

    if (!$orderId) {
        throw new Exception("Failed to create order.");
    }

    // 4. 使用存储过程添加订单商品并更新库存
    foreach ($items as $item) {
        $success = DBProcedures::addOrderItem($pdo, $orderId, $item['StockItemID'], $item['UnitPrice']);

        if (!$success) {
            throw new Exception("Failed to add item '{$item['Title']}' to order.");
        }
    }

    // 5. 计算积分
    $pointsEarned = floor($total);

    // 6. 【修复】在完成订单前保存原始会员等级（用于检测升级）
    $oldTierInfo = null;
    if ($customerId) {
        $oldTierInfo = getCustomerTierInfo($pdo, $customerId);
    }

    // 7. 使用存储过程完成订单（触发器会自动更新积分和等级）
    $success = DBProcedures::completeOrder($pdo, $orderId, $pointsEarned);

    if (!$success) {
        throw new Exception("Failed to complete order.");
    }

    // 8. 【修复】检测会员升级（传入原始等级ID以正确检测升级）
    $upgradeMsg = "";
    if ($customerId) {
        $oldTierId = $oldTierInfo ? $oldTierInfo['TierID'] : null;
        $res = checkMembershipUpgrade($pdo, $customerId, $total, $oldTierId);
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