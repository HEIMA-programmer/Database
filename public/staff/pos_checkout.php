<?php
/**
 * POS 结账处理
 * 【架构重构】遵循理想化分层架构
 * - 通过 DBProcedures 进行所有数据库操作
 * - 通过 functions.php 进行业务逻辑处理
 */
session_start();
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/auth_guard.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_procedures.php';

// =============================================
// 【权限验证】
// =============================================
requireRole(['Staff', 'Manager']);

// =============================================
// 【购物车验证】
// =============================================
$posCart = getPOSCart();
if (empty($posCart)) {
    flash("Cart is empty.", 'warning');
    header("Location: pos.php");
    exit();
}

// =============================================
// 【数据准备】
// =============================================
$shopId = $_SESSION['shop_id'];
$employeeId = $_SESSION['user_id'];
$customerEmail = trim($_POST['customer_email'] ?? '');
$customerId = null;

// =============================================
// 【事务处理】通过 DBProcedures 完成所有数据库操作
// =============================================
try {
    $pdo->beginTransaction();

    // 1. 从视图获取商品详情并验证可用性
    $items = DBProcedures::getPOSCartItems($pdo, $posCart);

    // 验证所有商品都可用
    $availableItems = array_filter($items, fn($item) => $item['Status'] === 'Available');
    if (count($availableItems) != count($posCart)) {
        throw new Exception("Some items are no longer available. Please clear cart and rescan.");
    }

    // 计算总额
    $total = array_sum(array_column($items, 'UnitPrice'));

    // 2. 查找关联会员（通过视图）
    if (!empty($customerEmail)) {
        $customer = DBProcedures::getCustomerByEmail($pdo, $customerEmail);
        if ($customer) {
            $customerId = $customer['CustomerID'];
        } else {
            flash("Customer email not found. Proceeding as Guest.", 'warning');
        }
    }

    // 3. 使用存储过程创建订单
    $orderId = DBProcedures::createCustomerOrder($pdo, $customerId, $shopId, $employeeId, 'InStore');
    if (!$orderId) {
        throw new Exception("Failed to create order.");
    }

    // 4. 使用存储过程添加订单商品
    foreach ($items as $item) {
        $success = DBProcedures::addOrderItem($pdo, $orderId, $item['StockItemID'], $item['UnitPrice']);
        if (!$success) {
            throw new Exception("Failed to add item '{$item['Title']}' to order.");
        }
    }

    // 5. 计算积分
    $pointsEarned = floor($total);

    // 6. 保存原始会员等级（用于检测升级）
    $oldTierInfo = null;
    if ($customerId) {
        $oldTierInfo = getCustomerTierInfo($pdo, $customerId);
    }

    // 7. 使用存储过程完成订单
    $success = DBProcedures::completeOrder($pdo, $orderId);
    if (!$success) {
        throw new Exception("Failed to complete order.");
    }

    // 8. 检测会员升级
    $upgradeMsg = "";
    if ($customerId) {
        $oldTierId = $oldTierInfo ? $oldTierInfo['TierID'] : null;
        $res = checkMembershipUpgrade($pdo, $customerId, $total, $oldTierId);
        if ($res && $res['upgraded']) {
            $upgradeMsg = " Customer upgraded to {$res['new_tier_name']}!";
        }
    }

    $pdo->commit();

    // 清空购物车
    clearPOSCart();

    flash("Transaction #$orderId completed. Amount: " . formatPrice($total) . "." . $upgradeMsg, 'success');
    header("Location: pos.php");

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("POS Checkout Error: " . $e->getMessage());
    flash("Error processing transaction: " . $e->getMessage(), 'danger');
    header("Location: pos.php");
}
?>
